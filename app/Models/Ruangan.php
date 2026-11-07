<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruangan extends Model
{
    protected $fillable = [
        'gedung_id', 'kode', 'sumber', 'lantai', 'tipe',
        'kapasitas', 'kapasitas_estimasi', 'fasilitas', 'foto', 'keterangan',
        'aktif', 'urutan',
    ];

    /** Kunci urut selalu ikut diperbarui saat kode ruangan disimpan. */
    protected static function booted(): void
    {
        static::saving(function (self $ruangan) {
            if ($ruangan->isDirty('kode') || empty($ruangan->urutan)) {
                $ruangan->urutan = self::kunciUrut($ruangan->kode);
            }
        });
    }

    protected $casts = [
        'fasilitas' => 'array',
        'aktif'     => 'boolean',
    ];

    public function gedung(): BelongsTo
    {
        return $this->belongsTo(Gedung::class);
    }

    /** Ruangan yang ditambahkan petugas, bukan dari berkas jadwal. */
    public function scopeManual($q)
    {
        return $q->where('sumber', 'manual');
    }

    /**
     * Ruangan yang benar-benar dikelola.
     *
     * Berkas jadwal memuat seluruh gedung kampus — gedung lain — karena
     * kelas yang ingin dipindah bisa berasal dari mana saja. Tetapi yang
     * boleh dipesan dan dijadikan ruangan tujuan hanyalah lantai yang
     * dikelola, diatur lewat config('jadwal.ruangan_dikelola').
     *
     * Ruangan khusus yang ditambahkan petugas sendiri (ruang multimedia,
     * ruang konsul) selalu ikut, berapa pun kodenya, karena memang sengaja
     * didaftarkan untuk dipinjamkan.
     */
    public function scopeDikelola($q)
    {
        $awalan = (array) config('jadwal.ruangan_dikelola', []);

        if (! $awalan) {
            return $q;
        }

        return $q->where(function ($w) use ($awalan) {
            $w->where('sumber', 'manual');

            // Awalan diisi sendiri di config dan selalu berupa kode polos
            // seperti A.03, jadi tidak perlu lolos karakter LIKE — yang
            // aturannya berbeda antara MySQL dan SQLite.
            foreach ($awalan as $a) {
                $w->orWhere('kode', 'like', $a . '%');
            }
        });
    }

    /** Apakah ruangan ini termasuk yang dikelola. */
    public function getDikelolaAttribute(): bool
    {
        if ($this->adalah_manual) {
            return true;
        }

        foreach ((array) config('jadwal.ruangan_dikelola', []) as $a) {
            if (str_starts_with(strtoupper($this->kode), strtoupper($a))) {
                return true;
            }
        }

        return ! config('jadwal.ruangan_dikelola');
    }

    public function getAdalahManualAttribute(): bool
    {
        return $this->sumber === 'manual';
    }

    /** Alamat foto ruangan, atau null bila belum ada. */
    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function penutupans(): HasMany
    {
        return $this->hasMany(PenutupanRuangan::class);
    }

    /** Aset yang rumahnya di ruangan ini. */
    public function asets(): HasMany
    {
        return $this->hasMany(AsetUnit::class);
    }

    /**
     * Fasilitas ruangan berasal dari dua sumber yang saling melengkapi:
     * kolom `fasilitas` untuk yang terpasang permanen dan tidak pernah
     * dipinjamkan (AC, glassboard), serta daftar aset yang rumahnya di sini
     * untuk barang yang bisa dipindah (TV, kabel HDMI). Dengan begitu daftar
     * ikut berubah sendiri saat barang dipindah, tanpa diketik dua kali.
     */
    public function getSeluruhFasilitasAttribute(): array
    {
        $tetap = collect($this->fasilitas ?? [])
            ->map(fn ($f) => ['nama' => $f, 'sumber' => 'tetap', 'kode' => null]);

        $dariAset = $this->asets
            ->where('status', 'aktif')
            ->map(fn (AsetUnit $a) => [
                'nama'   => $a->nama_lengkap,
                'sumber' => 'aset',
                'kode'   => $a->kode,
            ]);

        return $tetap->concat($dariAset)->values()->all();
    }

    /** Penutupan yang berlaku pada tanggal tertentu, bila ada. */
    public function penutupanPada(\Carbon\Carbon $tanggal): ?PenutupanRuangan
    {
        return $this->penutupans()->padaTanggal($tanggal)->first();
    }

    /**
     * Kapasitas resmi bila data ruangan sudah diisi,
     * kalau belum pakai estimasi dari jumlah mahasiswa.
     */
    public function getKapasitasEfektifAttribute(): int
    {
        return (int) ($this->kapasitas ?: $this->kapasitas_estimasi ?: 0);
    }

    public function getKapasitasPerkiraanAttribute(): bool
    {
        return empty($this->kapasitas);
    }

    /**
     * Kunci pengurutan ruangan: gedung dulu (sesuai urutan pada
     * config/jadwal.php), lalu lantai dan nomor ruang sebagai ANGKA.
     *
     * Tanpa ini, pengurutan teks biasa menaruh A.08.7 setelah A.08.29,
     * karena "7" lebih besar daripada "2" bila dibaca huruf per huruf.
     *
     *   A.03.03  -> 10|0003|0003
     *   A.08.29  -> 10|0008|0029
     *   B.15.15 -> 20|0015|0015
     */
    public static function kunciUrut(string $kode): string
    {
        $kode = trim($kode);

        // Nama gedung = bagian sebelum titik atau spasi pertama.
        preg_match('/^([A-Za-z0-9]+)/', $kode, $m);
        $gedung = strtoupper($m[1] ?? '');

        $urutanGedung = array_map('strtoupper', (array) config('jadwal.urutan_gedung', []));
        $posisi = array_search($gedung, $urutanGedung, true);
        $prioritas = $posisi === false ? 90 : ($posisi + 1) * 10;

        // Seluruh angka setelah nama gedung, masing-masing dijadikan 4 digit.
        $sisa = substr($kode, strlen($m[1] ?? ''));
        preg_match_all('/\d+/', $sisa, $angka);

        $bagian = array_map(fn ($n) => str_pad($n, 4, '0', STR_PAD_LEFT), $angka[0]);
        $bagian = array_pad($bagian, 3, '0000');

        return sprintf('%02d', $prioritas) . '|' . implode('|', array_slice($bagian, 0, 3))
            . '|' . strtoupper($kode);   // pembeda terakhir bila angkanya sama persis
    }

    /**
     * Ambil nomor lantai dari kode ruangan: A.08.19 -> 8, B.15.14 -> 15.
     */
    public static function lantaiDari(string $kode): int
    {
        if (preg_match('/^[A-Z]+\d?\.(\d+)\./', $kode, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\s(\d+)\./', $kode, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
