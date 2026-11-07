<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu unit aset di dalam pengajuan peminjaman. Statusnya berdiri sendiri
 * supaya petugas bisa menyetujui sebagian dan menolak sisanya.
 */
class PeminjamanAsetBaris extends Model
{
    protected $table = 'peminjaman_aset_barises';

    protected $fillable = [
        'peminjaman_aset_id', 'aset_unit_id', 'tanggal_pinjam', 'tanggal_kembali',
        'status', 'foto_serah', 'diserahkan_pada', 'foto_kembali', 'dikembalikan_pada',
        'kondisi_kembali', 'catatan_kembali', 'catatan_petugas',
        'wajib_ganti', 'sudah_diganti',
    ];

    protected $casts = [
        'tanggal_pinjam'    => 'date',
        'tanggal_kembali'   => 'date',
        'diserahkan_pada'   => 'datetime',
        'dikembalikan_pada' => 'datetime',
        'wajib_ganti'       => 'boolean',
        'sudah_diganti'     => 'boolean',
    ];

    public const STATUS = [
        'menunggu'  => 'Menunggu',
        'disetujui' => 'Disetujui',
        'ditolak'   => 'Ditolak',
        'dipinjam'  => 'Sedang dipinjam',
        'selesai'   => 'Selesai',
        'batal'     => 'Batal',
    ];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(PeminjamanAset::class, 'peminjaman_aset_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AsetUnit::class, 'aset_unit_id');
    }

    /** Barang yang masih di tangan peminjam dan sudah lewat jatuh tempo. */
    public function scopeTerlambat(Builder $q): Builder
    {
        return $q->where('status', 'dipinjam')
            ->whereDate('tanggal_kembali', '<', now()->toDateString());
    }

    /** Barang yang sedang keluar, dipakai untuk memeriksa ketersediaan. */
    public function scopeSedangKeluar(Builder $q): Builder
    {
        return $q->whereIn('status', ['disetujui', 'dipinjam']);
    }

    public function getTerlambatKahAttribute(): bool
    {
        return $this->status === 'dipinjam'
            && $this->tanggal_kembali->isBefore(now()->startOfDay());
    }

    public function getHariTerlambatAttribute(): int
    {
        return $this->terlambat_kah
            ? $this->tanggal_kembali->diffInDays(now()->startOfDay())
            : 0;
    }

    public function getLabelStatusAttribute(): string
    {
        if ($this->terlambat_kah) {
            return 'Terlambat ' . $this->hari_terlambat . ' hari';
        }

        return self::STATUS[$this->status] ?? $this->status;
    }

    public function getWarnaStatusAttribute(): string
    {
        if ($this->terlambat_kah) {
            return 'red';
        }

        return [
            'menunggu' => 'amber', 'disetujui' => 'blue', 'ditolak' => 'gray',
            'dipinjam' => 'green', 'selesai' => 'gray', 'batal' => 'gray',
        ][$this->status] ?? 'gray';
    }

    public function getFotoSerahUrlAttribute(): ?string
    {
        return $this->foto_serah ? asset('storage/' . $this->foto_serah) : null;
    }

    public function getFotoKembaliUrlAttribute(): ?string
    {
        return $this->foto_kembali ? asset('storage/' . $this->foto_kembali) : null;
    }
}
