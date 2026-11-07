<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan perubahan jadwal permanen. Dibuat agar petugas bisa menelusuri
 * apa yang pernah diubah, terutama karena mengunggah berkas baru akan
 * menghapus seluruh suntingan.
 */
class LogJadwal extends Model
{
    protected $fillable = [
        'periode_id', 'jadwal_lama_id', 'jadwal_baru_id', 'aksi',
        'mata_kuliah', 'kelas', 'sebelum', 'sesudah', 'berlaku_mulai',
        'alasan', 'oleh', 'nama_petugas',
    ];

    protected $casts = [
        'sebelum'       => 'array',
        'sesudah'       => 'array',
        'berlaku_mulai' => 'date',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function getLabelAksiAttribute(): string
    {
        return [
            'pindah'   => 'Pindah jadwal',
            'batal'    => 'Pembatalan perubahan',
            'pulihkan' => 'Pemulihan jadwal asal',
        ][$this->aksi] ?? $this->aksi;
    }

    /** Ringkasan satu baris untuk ditampilkan di daftar log. */
    public function getRingkasPerubahanAttribute(): string
    {
        $rangkai = function (?array $x) {
            if (! $x) {
                return '—';
            }

            return ($x['hari'] ?? '-') . ' ' . ($x['jam'] ?? '-') . ' di ' . ($x['ruangan'] ?? '-');
        };

        return $rangkai($this->sebelum) . '  →  ' . $rangkai($this->sesudah);
    }
}
