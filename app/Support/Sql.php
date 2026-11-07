<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Potongan SQL mentah yang bentuknya berbeda di tiap mesin basis data.
 *
 * Laravel menyeragamkan hampir semua kueri, tetapi beberapa hal — menambah
 * hari pada tanggal, memotong teks, mengurutkan menurut daftar nilai — tidak
 * punya padanan di query builder dan harus ditulis mentah. Nama fungsinya
 * berbeda antara MySQL dan SQLite, sehingga kueri yang jalan di satu mesin
 * melempar "no such function" di mesin lain.
 *
 * Proyek ini dipasang dengan MySQL di server dan SQLite saat mencoba di
 * laptop, jadi keduanya harus didukung. Semua SQL mentah lewat kelas ini.
 */
class Sql
{
    protected static function mesin(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Urutan baris menurut daftar nilai, mis. menunggu dulu baru disetujui.
     *
     * MySQL punya FIELD(), yang tidak ada di SQLite maupun PostgreSQL.
     * CASE berlaku di ketiganya, jadi dipakai di mana-mana tanpa pencabangan.
     *
     * @param  array<int, string>  $nilai
     * @return array{0: string, 1: array<int, string>}  [sql, bindings]
     */
    public static function urutanNilai(string $kolom, array $nilai): array
    {
        $kolom = static::amankanKolom($kolom);
        $sql   = 'CASE ' . $kolom;

        foreach ($nilai as $i => $_) {
            $sql .= ' WHEN ? THEN ' . $i;
        }

        $sql .= ' ELSE ' . count($nilai) . ' END';

        return [$sql, array_values($nilai)];
    }

    /** Tanggal pada satu kolom, ditambah sekian hari. */
    public static function tambahHari(string $kolom, int $hari): string
    {
        $kolom = static::amankanKolom($kolom);

        return match (static::mesin()) {
            'sqlite' => "date($kolom, '+$hari day')",
            'pgsql'  => "($kolom + interval '$hari day')",
            default  => "DATE_ADD($kolom, INTERVAL $hari DAY)",
        };
    }

    /** Bulan dalam bentuk 2026-10, untuk pengelompokan grafik. */
    public static function bulan(string $kolom): string
    {
        $kolom = static::amankanKolom($kolom);

        return match (static::mesin()) {
            'sqlite' => "strftime('%Y-%m', $kolom)",
            'pgsql'  => "to_char($kolom, 'YYYY-MM')",
            default  => "DATE_FORMAT($kolom, '%Y-%m')",
        };
    }

    /**
     * Angka yang tersisa pada satu kolom teks mulai posisi tertentu, mis.
     * 13 dari AST-013. Dipakai agar AST-099 diurutkan sebelum AST-100,
     * bukan sesudahnya seperti kalau diurutkan sebagai teks.
     *
     * Posisi mengikuti aturan SQL: karakter pertama adalah 1, bukan 0.
     */
    public static function angkaMulaiDari(string $kolom, int $posisi): string
    {
        $kolom  = static::amankanKolom($kolom);
        $posisi = max(1, $posisi);

        return match (static::mesin()) {
            'sqlite' => "CAST(substr($kolom, $posisi) AS INTEGER)",
            'pgsql'  => "NULLIF(substring($kolom from $posisi), '')::bigint",
            default  => "CAST(SUBSTRING($kolom, $posisi) AS UNSIGNED)",
        };
    }

    /**
     * Nama kolom ditempel langsung ke SQL, jadi hanya huruf, angka, garis
     * bawah, dan titik yang diizinkan. Nilai dari pengguna tidak pernah
     * lewat sini — nilai selalu dikirim sebagai binding.
     */
    protected static function amankanKolom(string $kolom): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', $kolom)) {
            throw new \InvalidArgumentException("Nama kolom tidak wajar: $kolom");
        }

        return $kolom;
    }
}
