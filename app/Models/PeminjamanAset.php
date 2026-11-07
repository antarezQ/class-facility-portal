<?php

namespace App\Models;

use App\Support\Wa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeminjamanAset extends Model
{
    protected $fillable = [
        'nomor', 'nama', 'identitas', 'peran', 'unit', 'kontak',
        'keperluan', 'ruangan_id', 'status', 'diproses_oleh', 'diproses_pada',
    ];

    protected $casts = ['diproses_pada' => 'datetime'];

    public function baris(): HasMany
    {
        return $this->hasMany(PeminjamanAsetBaris::class)->orderBy('tanggal_kembali');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    public static function nomorBaru(): string
    {
        $urut = static::max('id') + 1;

        return 'PJA-' . str_pad((string) $urut, 6, '0', STR_PAD_LEFT);
    }

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('status', $status) : $q;
    }

    /** Pengajuan yang punya baris terlambat dikembalikan. */
    public function scopeTerlambat(Builder $q): Builder
    {
        return $q->whereHas('baris', fn (Builder $b) => $b->terlambat());
    }

    /**
     * Status gabungan dari seluruh baris. Satu pengajuan bisa berisi unit
     * yang sudah kembali dan unit yang masih dipinjam sekaligus.
     */
    public function getStatusGabunganAttribute(): string
    {
        if ($this->baris->isEmpty()) {
            return $this->status;
        }

        if ($this->baris->contains(fn ($b) => $b->terlambat_kah)) {
            return 'terlambat';
        }

        $set = $this->baris->pluck('status')->unique();

        return $set->count() === 1 ? $set->first() : 'sebagian diproses';
    }

    public function getRingkasanAttribute(): string
    {
        $baris = $this->baris->map(
            fn (PeminjamanAsetBaris $b) => '- ' . $b->unit->kode . ' ' . $b->unit->nama_lengkap
                . ' (kembali ' . $b->tanggal_kembali->translatedFormat('j M Y') . ')'
        )->implode("\n");

        return "Barang yang diajukan:\n" . $baris
            . ($this->ruangan ? "\nUntuk kegiatan di: " . $this->ruangan->kode : '');
    }

    public function tautanWa(?string $status = null): ?string
    {
        return Wa::tautan($this->kontak, Wa::pesanKeputusan(
            $this->nama,
            $this->nomor,
            'peminjaman aset',
            $this->ringkasan,
            $status ?? $this->status
        ));
    }

    /** Pesan penagihan barang yang lewat jatuh tempo. */
    public function tautanWaTagih(): ?string
    {
        $telat = $this->baris->filter(fn ($b) => $b->terlambat_kah);

        if ($telat->isEmpty()) {
            return null;
        }

        $daftar = $telat->map(
            fn ($b) => '- ' . $b->unit->kode . ' ' . $b->unit->nama_lengkap
                . ' (jatuh tempo ' . $b->tanggal_kembali->translatedFormat('j M Y')
                . ', terlambat ' . $b->hari_terlambat . ' hari)'
        )->implode("\n");

        $pesan = "Halo {$this->nama}, ini dari unit pelayanan " . config('jadwal.nama_pendek') . ".\n\n"
            . "Kami mencatat barang berikut dari peminjaman {$this->nomor} belum dikembalikan:\n\n"
            . $daftar . "\n\n"
            . "Mohon segera dikembalikan ke kantor layanan, atau balas pesan ini bila memerlukan "
            . "perpanjangan waktu.\n\nTerima kasih.";

        return Wa::tautan($this->kontak, $pesan);
    }
}
