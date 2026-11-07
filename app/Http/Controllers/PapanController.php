<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use App\Services\AnalitikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Papan "Today" untuk layar di lobi/depan lift.
 * Halaman dirender sekali, lalu menyegarkan datanya lewat endpoint JSON.
 */
class PapanController extends Controller
{
    public function index(Request $request): View
    {
        return view('papan.index', $this->data($request));
    }

    public function data(Request $request): array
    {
        // Papan hanya menayangkan semester yang benar-benar ditandai berjalan.
        // Tanpa penanda itu, tidak ada yang boleh tampil — jadwal periode lain
        // belum tentu berlaku hari ini.
        $periode = Periode::berjalan();

        if (! $periode) {
            return [
                'periode'    => null,
                'diLuarMasa' => false,
                'pekanKe'    => null,
                'kegiatan'   => collect(),
                'ruangans'   => collect(),
                'terpakai'   => collect(),   // dipakai tampilan; tanpa ini papan error saat data kosong
                'hari'       => null,
            ];
        }

        $gedungId = $request->query('gedung') ? (int) $request->query('gedung') : null;
        $hari     = now()->dayOfWeekIso - 1;
        $analitik = new AnalitikService($periode->id, $gedungId);

        // Jadwal berulang tiap pekan, tapi hanya berlaku selama masa
        // perkuliahan. Di luar rentang itu gedung memang tidak dipakai kuliah.
        $diLuarMasa = ! $periode->mencakup(now());

        // Perkuliahan dan reservasi disetujui digabung agar papan menampilkan
        // kenyataan pemakaian ruangan, bukan jadwal kuliah saja.
        //
        // Selalu disaring, juga saat petugas sedang masuk: papan tampil di layar
        // umum depan lift. Rincian lengkap untuk petugas ada di Timeline.
        $pemakaian = new \App\Services\PemakaianRuanganService($periode);
        $kegiatan  = $pemakaian->untukPublik($pemakaian->padaTanggal(now(), $gedungId));

        $menitSekarang = now()->hour * 60 + now()->minute;
        $terpakai = $kegiatan
            ->filter(fn ($x) => $x['mulai'] <= $menitSekarang && $menitSekarang < $x['selesai'])
            ->pluck('ruangan_id')->unique();

        return [
            'periode'    => $periode,
            'diLuarMasa' => $diLuarMasa,
            'pekanKe'    => $periode->pekanKe(now()),
            'hari'       => $hari,
            'kegiatan' => $kegiatan,
            'ruangans' => $analitik->ruangan(),
            'terpakai' => $terpakai,
        ];
    }

    /** Dipanggil berkala oleh papan supaya layar tidak perlu dimuat ulang. */
    public function json(Request $request): JsonResponse
    {
        $d = $this->data($request);

        return response()->json([
            'periode'      => $d['periode']?->nama,
            'di_luar_masa' => $d['diLuarMasa'],
            'pekan_ke'     => $d['pekanKe'],
            'jam'      => now()->format('H.i.s'),
            'total'    => $d['ruangans']->count(),
            'terpakai' => $d['terpakai']->count() ?? 0,
            'kosong'   => $d['ruangans']->count() - ($d['terpakai']->count() ?? 0),
            // Endpoint ini terbuka tanpa login, jadi field dipilih satu per satu.
            // `judul`, `ket`, dan `detail` tidak boleh ikut: pada reservasi isinya
            // keperluan, nama pemohon, NIM/NIP, dan nomor WhatsApp.
            'kegiatan' => $d['kegiatan']
                ->map(fn ($x) => collect($x)->only(['ruangan_id', 'ruangan', 'mulai', 'selesai', 'jenis', 'status']))
                ->values(),
        ]);
    }
}
