<?php

namespace App\Support;

/**
 * Penyusun tautan WhatsApp. Dikumpulkan di satu tempat supaya nada pesannya
 * seragam dan tidak berserakan di banyak halaman.
 */
class Wa
{
    /** Ubah 08xx / +62xx menjadi format internasional tanpa tanda baca. */
    public static function normalkan(?string $nomor): ?string
    {
        $bersih = preg_replace('/\D+/', '', (string) $nomor);

        if (! $bersih) {
            return null;
        }
        if (str_starts_with($bersih, '0')) {
            return '62' . substr($bersih, 1);
        }
        if (str_starts_with($bersih, '62')) {
            return $bersih;
        }
        if (str_starts_with($bersih, '8')) {
            return '62' . $bersih;
        }

        return $bersih;
    }

    public static function tautan(?string $nomor, string $pesan): ?string
    {
        $tujuan = self::normalkan($nomor);

        return $tujuan ? 'https://wa.me/' . $tujuan . '?text=' . rawurlencode($pesan) : null;
    }

    /** Sapaan pembuka yang dipakai semua pesan. */
    protected static function pembuka(string $nama): string
    {
        return "Halo {$nama}, ini dari unit pelayanan " . config('jadwal.nama_pendek') . ".\n\n";
    }

    /** Pemberitahuan keputusan atas satu pengajuan. */
    public static function pesanKeputusan(
        string $nama,
        string $nomorPengajuan,
        string $jenis,
        string $ringkasan,
        string $status,
        ?string $catatan = null
    ): string {
        $kalimat = match ($status) {
            'disetujui' => "Pengajuan {$jenis} Anda dengan nomor {$nomorPengajuan} sudah DISETUJUI.",
            'ditolak'   => "Mohon maaf, pengajuan {$jenis} Anda dengan nomor {$nomorPengajuan} BELUM DAPAT DISETUJUI.",
            default     => "Pengajuan {$jenis} Anda dengan nomor {$nomorPengajuan} masih dalam proses.",
        };

        $pesan = self::pembuka($nama) . $kalimat . "\n\n" . $ringkasan;

        if ($catatan) {
            $pesan .= "\n\nCatatan petugas:\n" . $catatan;
        }

        if ($status === 'disetujui') {
            $pesan .= "\n\nMohon ruangan digunakan sesuai jam yang disetujui dan dikembalikan dalam keadaan rapi.";
        }

        return $pesan . "\n\nTerima kasih.";
    }

    /** Pemberitahuan bahwa ruangan yang sudah dipesan akan ditutup. */
    public static function pesanRuanganDitutup(
        string $nama,
        string $nomorPengajuan,
        string $ruangan,
        string $waktu,
        string $alasan,
        string $rentangTutup
    ): string {
        return self::pembuka($nama)
            . "Mohon maaf, ruangan {$ruangan} yang Anda pesan pada {$waktu} "
            . "(pengajuan {$nomorPengajuan}) tidak dapat digunakan.\n\n"
            . "Ruangan tersebut ditutup pada {$rentangTutup} dengan alasan: {$alasan}.\n\n"
            . "Silakan ajukan ruangan pengganti melalui menu Cari Ruang Kosong, "
            . "atau balas pesan ini bila memerlukan bantuan.\n\nTerima kasih atas pengertiannya.";
    }
}
