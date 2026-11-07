<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model
{
    use HasFactory;

    protected $fillable = ['kode', 'tahun_ajaran', 'semester', 'nama', 'aktif', 'tanggal_mulai', 'tanggal_selesai'];

    protected $casts = [
        'aktif'           => 'boolean',
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    /**
     * Ubah "2526" + "1" menjadi "TA 2025/2026 Ganjil".
     */
    public static function namaDari(string $tahunAjaran, string $semester): string
    {
        $awal  = '20' . substr($tahunAjaran, 0, 2);
        $akhir = '20' . substr($tahunAjaran, 2, 2);
        $label = match ($semester) {
            '1'     => 'Ganjil',
            '2'     => 'Genap',
            default => 'Antara',
        };

        return "TA {$awal}/{$akhir} {$label}";
    }

    /**
     * Periode yang ditandai berjalan oleh petugas. Mengembalikan null
     * bila tidak ada yang ditandai — dipakai header agar tidak menampilkan
     * semester yang sebenarnya tidak sedang ditayangkan.
     */
    public static function berjalan(): ?self
    {
        return once(fn () => static::where('aktif', true)->first());
    }

    /** Rentang tanggal sudah diisi atau belum. */
    public function punyaTanggal(): bool
    {
        return $this->tanggal_mulai && $this->tanggal_selesai;
    }

    /** Apakah tanggal tertentu jatuh di dalam masa perkuliahan. */
    public function mencakup(\Carbon\Carbon $tanggal): bool
    {
        if (! $this->punyaTanggal()) {
            return true;   // belum diisi, jangan halangi apa pun
        }

        return $tanggal->betweenIncluded($this->tanggal_mulai, $this->tanggal_selesai);
    }

    /** Pekan ke berapa tanggal tersebut, dihitung dari tanggal mulai. */
    public function pekanKe(\Carbon\Carbon $tanggal): ?int
    {
        if (! $this->punyaTanggal() || ! $this->mencakup($tanggal)) {
            return null;
        }

        return (int) floor($this->tanggal_mulai->copy()->startOfWeek()->diffInWeeks($tanggal)) + 1;
    }

    /** Jumlah pekan yang tercakup rentang tanggal, untuk hitungan per semester. */
    public function jumlahPekan(): int
    {
        if (! $this->punyaTanggal()) {
            return (int) config('jadwal.pekan_efektif', 16);
        }

        return max(1, (int) ceil($this->tanggal_mulai->diffInDays($this->tanggal_selesai) / 7));
    }

    /**
     * Status semester relatif terhadap hari ini:
     * 'tanpa_tanggal' | 'sebelum' | 'berjalan' | 'sesudah'
     */
    public function statusSekarang(): string
    {
        if (! $this->punyaTanggal()) {
            return 'tanpa_tanggal';
        }
        if (now()->lt($this->tanggal_mulai)) {
            return 'sebelum';
        }
        if (now()->gt($this->tanggal_selesai)) {
            return 'sesudah';
        }

        return 'berjalan';
    }

    public function sedangBerjalan(): bool
    {
        return $this->statusSekarang() === 'berjalan';
    }

    /** Tanggal yang masuk akal dijadikan bawaan pada formulir pencarian. */
    public function tanggalAcuan(): \Carbon\Carbon
    {
        return match ($this->statusSekarang()) {
            'sebelum' => $this->tanggal_mulai->copy(),
            'sesudah' => $this->tanggal_selesai->copy(),
            default   => now()->startOfDay(),
        };
    }

    public function rentangTeks(): string
    {
        if (! $this->punyaTanggal()) {
            return 'Rentang tanggal belum diisi';
        }

        return $this->tanggal_mulai->translatedFormat('j M Y')
            . ' – ' . $this->tanggal_selesai->translatedFormat('j M Y')
            . ' (' . $this->jumlahPekan() . ' pekan)';
    }

    public static function dariKode(string $tahunAjaran, string $semester): self
    {
        return static::firstOrCreate(
            ['kode' => "{$tahunAjaran}-{$semester}"],
            [
                'tahun_ajaran' => $tahunAjaran,
                'semester'     => $semester,
                'nama'         => static::namaDari($tahunAjaran, $semester),
            ]
        );
    }
}
