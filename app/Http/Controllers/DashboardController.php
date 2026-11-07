<?php

namespace App\Http\Controllers;

use App\Models\Gedung;
use App\Models\Jadwal;
use App\Models\Periode;
use App\Services\AnalitikService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const TAB = ['ruangan', 'jadwal', 'timeline'];

    public function index(Request $request, string $tab = 'ruangan'): View
    {
        abort_unless(in_array($tab, self::TAB, true), 404);

        $periode = Periode::berjalan();

        if (! $periode) {
            return view('dashboard.kosong');
        }

        $gedung   = $this->gedungTerpilih($request, $periode->id);
        $analitik = new AnalitikService($periode->id, $gedung?->id);

        $data = [
            'tab'      => $tab,
            'tabBoleh' => self::TAB,
            'periode'  => $periode,
            'gedung'   => $gedung,
            'gedungs'  => $this->gedungPeriode($periode->id),
        ];

        $data = array_merge($data, match ($tab) {
            'ruangan'  => $this->dataRuangan($analitik),
            'jadwal'   => $this->dataJadwal($request, $periode->id, $gedung?->id),
            'timeline' => $this->dataTimeline($request, $periode->id, $gedung?->id, $analitik),
        });

        return view('dashboard.index', $data);
    }

    protected function gedungTerpilih(Request $request, int $periodeId): ?Gedung
    {
        $id = $request->query('gedung');

        return $id ? $this->gedungPeriode($periodeId)->firstWhere('id', (int) $id) : null;
    }

    protected function gedungPeriode(int $periodeId)
    {
        $ids = Jadwal::where('periode_id', $periodeId)->distinct()->pluck('gedung_id');

        return Gedung::whereIn('id', $ids)->orderBy('nama')->get();
    }

    protected function dataRuangan(AnalitikService $analitik): array
    {
        return [
            // Seluruh ruangan aktif, termasuk yang belum punya jadwal. Daftar
            // Ruangan menjawab "ruang apa saja yang ada", bukan "ruang mana
            // yang terpakai" — ruangan yang baru didaftarkan harus tetap
            // kelihatan meski mata kuliahnya belum diisi.
            'ruanganStat' => $analitik->perRuangan(true)->sortBy('urutan')->values(),
            // Objek ruangan dipakai untuk menampilkan foto, fasilitas, dan
            // keterangan. Harus memakai daftar yang sama, kalau tidak ruangan
            // tanpa jadwal muncul tanpa tipe dan fasilitasnya.
            'ruanganObjek' => $analitik->ruanganSemua()->keyBy('kode'),
            // Penutupan yang masih berlaku atau akan datang, ditandai pada kartu.
            'penutupanAktif' => \App\Models\PenutupanRuangan::with('ruangan')
                ->belumLewat()
                ->get()
                ->groupBy(fn ($t) => $t->ruangan->kode),
        ];
    }

    /**
     * Berapa baris jadwal per halaman. Pilihan "semua" diterjemahkan menjadi
     * sebesar jumlah jadwal pada periode itu, dengan batas bawah 1 agar
     * paginator tidak pernah menerima angka nol.
     */
    protected function jumlahBaris(Request $request, int $periodeId, ?int $gedungId): int
    {
        $per = $request->query('per', 50);

        if ($per === 'semua') {
            return max(1, Jadwal::untukPeriode($periodeId)->untukGedung($gedungId)->count());
        }

        return in_array((int) $per, [10, 20, 50], true) ? (int) $per : 50;
    }

    protected function dataJadwal(Request $request, int $periodeId, ?int $gedungId): array
    {
        $jadwals = Jadwal::with(['ruangan', 'dosen', 'mataKuliah'])
            ->untukPeriode($periodeId)
            ->untukGedung($gedungId)
            ->untukHari($request->filled('hari') ? (int) $request->query('hari') : null)
            ->cari($request->query('q'))
            ->when($request->filled('ruangan'), fn ($q) => $q->whereHas('ruangan', fn ($r) => $r->where('kode', $request->query('ruangan'))))
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi', $request->query('prodi')))
            ->join('ruangans', 'ruangans.id', '=', 'jadwals.ruangan_id')
            ->orderBy('jadwals.hari')->orderBy('jadwals.menit_mulai')->orderBy('ruangans.urutan')
            ->select('jadwals.*')
            /*
             * Jumlah baris mengikuti pilihan pemakai. "semua" tetap memakai
             * paginator supaya tampilan dan tautannya tidak bercabang —
             * cukup satu halaman yang memuat seluruh hasil saringan.
             */
            ->paginate($this->jumlahBaris($request, $periodeId, $gedungId))
            ->withQueryString();

        return [
            'jadwals'    => $jadwals,
            'totalTanpaFilter' => Jadwal::untukPeriode($periodeId)->untukGedung($gedungId)->count(),
            'daftarProdi' => Jadwal::untukPeriode($periodeId)->untukGedung($gedungId)->distinct()->orderBy('prodi')->pluck('prodi'),
        ];
    }

    protected function dataTimeline(Request $request, int $periodeId, ?int $gedungId, AnalitikService $analitik): array
    {
        $tanggal = $request->query('tanggal', now()->toDateString());
        $carbon  = \Carbon\Carbon::parse($tanggal);
        $hari    = $carbon->dayOfWeekIso - 1;          // 0 = Senin

        // Jadwal hanya berlaku di dalam masa perkuliahan. Di luar itu,
        // seluruh ruangan memang kosong meski harinya sama.
        $periode = Periode::find($periodeId);
        $adaHari = $hari >= 0 && $hari <= 5 && $periode->mencakup($carbon);

        // Satu daftar berisi perkuliahan dan reservasi yang sudah disetujui,
        // masing-masing membawa jenisnya sendiri untuk pewarnaan.
        $layanan = new \App\Services\PemakaianRuanganService($periode);

        // Data pribadi pemohon tidak boleh ikut terkirim ke halaman.
        $pemakaian = $layanan->untukPublik($layanan->padaTanggal($carbon, $gedungId, false));

        return [
            'tanggal'   => $carbon,
            'hari'      => $hari,
            'pemakaian' => $pemakaian,
            'ruangans'  => $analitik->ruanganTampil(),
            'celah'     => $adaHari ? $analitik->celahKosong($hari) : collect(),
        ];
    }
}
