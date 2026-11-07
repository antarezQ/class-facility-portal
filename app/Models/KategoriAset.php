<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis barang. Kodenya dipakai untuk menyusun kode unit secara otomatis.
 */
class KategoriAset extends Model
{
    protected $table = 'kategori_asets';

    protected $fillable = ['kode', 'nama', 'keterangan', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function units(): HasMany
    {
        return $this->hasMany(AsetUnit::class, 'kategori_aset_id');
    }

    /**
     * Kode unit berikutnya, mis. IC-HDMI051 bila yang terakhir IC-HDMI050.
     * Formatnya mengikuti label fisik pada barang. Nomor diambil dari kode
     * tertinggi yang pernah ada — bukan dari jumlah unit — supaya penghapusan
     * satu unit tidak membuat kode kembar.
     */
    public function kodeBerikutnya(): string
    {
        $awalan = 'IC-' . strtoupper($this->kode);

        $terakhir = $this->units()
            ->where('kode', 'like', $awalan . '%')
            ->orderByRaw(\App\Support\Sql::angkaMulaiDari('kode', strlen($awalan) + 1) . ' DESC')
            ->value('kode');

        $nomor = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan . str_pad((string) $nomor, 3, '0', STR_PAD_LEFT);
    }

    /** Jumlah unit dihitung, bukan disimpan, agar tidak pernah meleset. */
    public function getJumlahUnitAttribute(): int
    {
        return $this->units()->where('status', '!=', 'diganti')->count();
    }

    public function getJumlahBisaDipinjamAttribute(): int
    {
        return $this->units()
            ->where('boleh_pinjam', true)
            ->where('status', 'aktif')
            ->where('kondisi', 'baik')
            ->count();
    }
}
