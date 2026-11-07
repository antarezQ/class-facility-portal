<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu barang fisik. Kodenya unik dan tidak pernah dipakai ulang, sehingga
 * riwayat peminjaman dan kerusakan tiap barang bisa ditelusuri saat audit.
 */
class AsetUnit extends Model
{
    protected $table = 'aset_units';

    protected $fillable = [
        'kategori_aset_id', 'kode', 'nama', 'merek', 'ruangan_id',
        'kondisi', 'status', 'boleh_pinjam', 'tanggal_masuk', 'sumber_dana',
        'foto', 'deskripsi', 'pengganti_id', 'catatan_penggantian',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'boleh_pinjam'  => 'boolean',
    ];

    public const KONDISI = ['baik' => 'Baik', 'rusak' => 'Rusak'];

    public const STATUS = [
        'aktif'       => 'Aktif',
        'nonaktif'    => 'Tidak Aktif',
        'maintenance' => 'Maintenance',
        'diganti'     => 'Sudah Diganti',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_aset_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    /** Unit yang menggantikan barang ini setelah rusak. */
    public function pengganti(): BelongsTo
    {
        return $this->belongsTo(AsetUnit::class, 'pengganti_id');
    }

    /** Unit yang digantikan oleh barang ini. */
    public function digantikan()
    {
        return $this->hasOne(AsetUnit::class, 'pengganti_id');
    }

    /** Unit yang masih dipakai — tidak termasuk yang sudah diganti. */
    public function scopeTerpakai(Builder $q): Builder
    {
        return $q->where('status', '!=', 'diganti');
    }

    /** Unit yang benar-benar bisa dipinjam civitas saat ini. */
    public function scopeSiapPinjam(Builder $q): Builder
    {
        return $q->where('boleh_pinjam', true)
            ->where('status', 'aktif')
            ->where('kondisi', 'baik');
    }

    public function scopeCari(Builder $q, ?string $kata): Builder
    {
        if (! $kata) {
            return $q;
        }
        $k = '%' . str_replace('%', '\%', $kata) . '%';

        return $q->where(fn (Builder $w) => $w->where('kode', 'like', $k)
            ->orWhere('nama', 'like', $k)
            ->orWhere('merek', 'like', $k));
    }

    public function getLabelKondisiAttribute(): string
    {
        return self::KONDISI[$this->kondisi] ?? $this->kondisi;
    }

    public function getLabelStatusAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    /** Nama lengkap dengan mereknya, untuk ditampilkan di daftar. */
    public function getNamaLengkapAttribute(): string
    {
        return $this->merek ? "{$this->nama} ({$this->merek})" : $this->nama;
    }
}
