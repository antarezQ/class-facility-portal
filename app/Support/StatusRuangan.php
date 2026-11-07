<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Status satu ruangan untuk ditampilkan, dihitung dari kegiatannya hari itu.
 * Urutan pemeriksaannya mengikuti DESIGN §3.3 dan harus sama dengan
 * hitungStatus() di public/js/publik.js, yang menyegarkannya tiap menit.
 */
class StatusRuangan
{
    public const SEGERA_MENIT = 30;

    public const IKON = [
        'available' => 'circle-check',
        'soon' => 'clock-alert',
        'occupied' => 'circle-minus',
        'closed' => 'lock',
    ];

    /** @return array{kunci: string, teks: string, kini: ?array, lanjut: ?array} */
    public static function hitung(Collection $kegiatan, int $menit): array
    {
        $urut = $kegiatan->sortBy('mulai')->values();
        $tutup = $urut->firstWhere('jenis', 'tutup');
        $kini = $urut->first(fn ($x) => $x['jenis'] !== 'tutup' && $x['mulai'] <= $menit && $menit < $x['selesai']);
        $lanjut = $urut->first(fn ($x) => $x['jenis'] !== 'tutup' && $x['mulai'] > $menit);

        if ($tutup) {
            return ['kunci' => 'closed', 'teks' => __('publik.status.closed'), 'kini' => $tutup, 'lanjut' => null];
        }

        if ($kini) {
            return [
                'kunci' => 'occupied',
                'teks' => __('publik.status.busy_until', ['t' => self::jam($kini['selesai'])]),
                'kini' => $kini,
                'lanjut' => $lanjut,
            ];
        }

        if ($lanjut && $lanjut['mulai'] - $menit <= self::SEGERA_MENIT) {
            return [
                'kunci' => 'soon',
                'teks' => __('publik.status.starts_in', ['n' => $lanjut['mulai'] - $menit]),
                'kini' => null,
                'lanjut' => $lanjut,
            ];
        }

        return [
            'kunci' => 'available',
            'teks' => $lanjut
                ? __('publik.status.free_until', ['t' => self::jam($lanjut['mulai'])])
                : __('publik.status.free_rest'),
            'kini' => null,
            'lanjut' => $lanjut,
        ];
    }

    public static function jam(int $menit): string
    {
        return sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60);
    }

    /** Judul blok kegiatan. Nama mata kuliah tampil persis seperti di datanya. */
    public static function judul(array $x): string
    {
        return match (true) {
            $x['jenis'] === 'tutup' => TerjemahPesan::teks($x['judul']),
            $x['jenis'] === 'rsv', $x['judul'] === 'Kuliah pengganti' => Str::title(TerjemahPesan::teks($x['judul'])),
            default => $x['judul'],
        };
    }

    /** Isi atribut data-rinci untuk panel rincian, dalam bahasa aktif. */
    public static function rinci(array $x, string $judul): ?string
    {
        if (! isset($x['detail'])) {
            return null;
        }

        return json_encode([
            'judul' => $judul,
            'tag' => TerjemahPesan::teks($x['detail']['tag'] ?? ''),
            'baris' => TerjemahPesan::baris($x['detail']['baris'] ?? []),
        ], JSON_UNESCAPED_UNICODE);
    }
}
