<?php

namespace App\Services;

use App\Models\AsetUnit;
use App\Models\PeminjamanAsetBaris;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menentukan unit aset mana yang benar-benar bisa dipinjam pada rentang
 * tanggal tertentu. Satu unit hanya boleh dipegang satu orang pada saat
 * yang sama, jadi peminjaman yang beririsan harus saling menutup.
 */
class KetersediaanAsetService
{
    /** Unit yang sedang dipegang orang lain pada rentang tanggal itu. */
    public function unitTerpakai(Carbon $mulai, Carbon $selesai, ?int $abaikanBarisId = null): Collection
    {
        return PeminjamanAsetBaris::sedangKeluar()
            ->when($abaikanBarisId, fn ($q) => $q->where('id', '!=', $abaikanBarisId))
            ->whereDate('tanggal_pinjam', '<=', $selesai->toDateString())
            ->whereDate('tanggal_kembali', '>=', $mulai->toDateString())
            ->pluck('aset_unit_id')
            ->unique();
    }

    /**
     * Unit yang sudah diminta pengajuan lain dan masih menunggu keputusan.
     * Belum mengunci unit, tetapi sebaiknya dipilih paling akhir supaya dua
     * pemohon tidak mendapat unit yang sama.
     */
    public function unitMenunggu(Carbon $mulai, Carbon $selesai): Collection
    {
        return PeminjamanAsetBaris::where('status', 'menunggu')
            ->whereDate('tanggal_pinjam', '<=', $selesai->toDateString())
            ->whereDate('tanggal_kembali', '>=', $mulai->toDateString())
            ->pluck('aset_unit_id')
            ->unique();
    }

    /** Unit yang bisa dipinjam pada rentang itu, siap ditampilkan. */
    public function unitTersedia(
        Carbon $mulai,
        Carbon $selesai,
        ?int $kategoriId = null,
        ?string $kata = null
    ): Collection {
        $terpakai = $this->unitTerpakai($mulai, $selesai);

        return AsetUnit::with(['kategori', 'ruangan'])
            ->siapPinjam()
            ->whereNotIn('id', $terpakai)
            ->when($kategoriId, fn ($q) => $q->where('kategori_aset_id', $kategoriId))
            ->cari($kata)
            ->orderBy('kategori_aset_id')
            ->orderBy('kode')
            ->get();
    }

    /**
     * Periksa satu unit. Mengembalikan pesan bila tidak bisa dipinjam,
     * atau null bila aman.
     */
    public function periksa(AsetUnit $unit, Carbon $mulai, Carbon $selesai, ?int $abaikanBarisId = null): ?string
    {
        if (! $unit->boleh_pinjam) {
            return "{$unit->kode} tidak ditandai boleh dipinjam publik.";
        }

        if ($unit->status !== 'aktif') {
            return "{$unit->kode} berstatus {$unit->label_status}.";
        }

        if ($unit->kondisi !== 'baik') {
            return "{$unit->kode} dalam kondisi rusak.";
        }

        if ($bentrok = $this->bentrokDengan($unit, $mulai, $selesai, $abaikanBarisId)) {
            // Pesan ini sampai ke publik lewat /pinjam-aset, jadi nama peminjam tidak disebut.
            return "{$unit->kode} sedang dipinjam "
                . "({$bentrok->peminjaman->nomor}) sampai "
                . $bentrok->tanggal_kembali->translatedFormat('j M Y') . '.';
        }

        return null;
    }

    /** Peminjaman disetujui atau berjalan yang memegang unit ini pada rentang itu. */
    public function bentrokDengan(AsetUnit $unit, Carbon $mulai, Carbon $selesai, ?int $abaikanBarisId = null): ?PeminjamanAsetBaris
    {
        return PeminjamanAsetBaris::with('peminjaman')
            ->sedangKeluar()
            ->where('aset_unit_id', $unit->id)
            ->when($abaikanBarisId, fn ($q) => $q->where('id', '!=', $abaikanBarisId))
            ->whereDate('tanggal_pinjam', '<=', $selesai->toDateString())
            ->whereDate('tanggal_kembali', '>=', $mulai->toDateString())
            ->first();
    }

    /**
     * Unit sejenis yang masih bebas, ditawarkan sebagai jalan keluar saat
     * unit yang diminta ternyata sedang dipinjam.
     */
    public function saranPengganti(AsetUnit $unit, Carbon $mulai, Carbon $selesai, int $maksimal = 6): array
    {
        return $this->unitTersedia($mulai, $selesai, $unit->kategori_aset_id)
            ->where('id', '!=', $unit->id)
            ->take($maksimal)
            ->map(fn (AsetUnit $u) => [
                'id'    => $u->id,
                'kode'  => $u->kode,
                'nama'  => $u->nama_lengkap,
            ])->values()->all();
    }
}
