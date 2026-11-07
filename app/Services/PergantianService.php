<?php

namespace App\Services;

use App\Models\PengajuanBaris;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menjawab satu pertanyaan: pada tanggal tertentu, pertemuan mana yang
 * sebenarnya batal dan pertemuan pengganti mana yang benar-benar terjadi.
 *
 * Pertanyaan ini muncul karena jadwal kuliah disimpan sebagai pola mingguan
 * ("Kalkulus setiap Senin 09:30 di A.09.17"), sedangkan pergantian jadwal
 * berlaku untuk satu tanggal saja. Tanpa kelas ini, pencarian ruang kosong
 * tetap menganggap Kalkulus memakai A.09.17 pada Senin 12 Oktober padahal
 * pertemuannya sudah dipindah — dan sebaliknya, ruangan pada tanggal
 * penggantinya tidak terkunci.
 *
 * Satu hal yang mudah terlewat: pertemuan pengganti bisa dipindah lagi.
 * 12 Oktober pindah ke 13 Oktober, lalu 13 Oktober pindah ke 20 Oktober.
 * Yang berlaku hanya ujung rantainya — pada 13 Oktober tidak ada kuliah
 * sama sekali, dan ruangannya harus bebas untuk orang lain.
 */
class PergantianService
{
    /**
     * Jadwal yang pertemuannya dibatalkan pada tanggal itu.
     *
     * Sekali dibatalkan tetap batal, walaupun penggantinya kemudian dipindah
     * lagi — yang pindah adalah pertemuan penggantinya, bukan yang ini.
     *
     * @return Collection<int, int>  daftar jadwal_id
     */
    public static function jadwalDibatalkan(Carbon $tanggal): Collection
    {
        return PengajuanBaris::query()
            ->whereDate('tanggal_asal', $tanggal->toDateString())
            ->aktif()
            ->whereNotNull('jadwal_id')
            ->pluck('jadwal_id')
            ->unique()
            ->values();
    }

    /**
     * Pertemuan pengganti yang benar-benar berlangsung pada tanggal itu,
     * yaitu yang belum dipindah lagi ke tanggal lain.
     *
     * @return Collection<int, PengajuanBaris>
     */
    public static function penggantiPada(Carbon $tanggal, ?int $gedungId = null): Collection
    {
        return PengajuanBaris::with(['ruangan', 'pengajuan', 'jadwal.mataKuliah', 'jadwal.dosen'])
            ->whereDate('tanggal', $tanggal->toDateString())
            ->aktif()
            ->belumDipindahLagi()
            ->whereNotNull('ruangan_id')
            ->when($gedungId, fn ($q) => $q->whereHas('ruangan', fn ($r) => $r->where('gedung_id', $gedungId)))
            ->orderBy('menit_mulai')
            ->get();
    }

    /**
     * Ruangan yang terpakai kuliah pengganti pada rentang jam tertentu.
     *
     * @return Collection<int, int>  daftar ruangan_id
     */
    public static function ruanganTerpakai(Carbon $tanggal, int $menitMulai, int $menitSelesai): Collection
    {
        return PengajuanBaris::query()
            ->whereDate('tanggal', $tanggal->toDateString())
            ->aktif()
            ->belumDipindahLagi()
            ->whereNotNull('ruangan_id')
            ->where('menit_mulai', '<', $menitSelesai)
            ->where('menit_selesai', '>', $menitMulai)
            ->pluck('ruangan_id')
            ->unique()
            ->values();
    }
}
