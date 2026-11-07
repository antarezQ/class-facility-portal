<?php

namespace App\Http\Controllers;

use App\Models\Gedung;
use App\Models\Jadwal;
use App\Models\PenutupanRuangan;
use App\Models\Periode;
use App\Models\Ruangan;
use App\Services\PencarianRuanganService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CariRuanganController extends Controller
{
    public function index(Request $request): View
    {
        $periode = Periode::berjalan();

        if (! $periode) {
            return view('dashboard.kosong');
        }

        $data = [
            'periode'  => $periode,
            // Hanya gedung yang punya ruangan kelolaan. Tanpa ini,
            // memilih gedung lain akan selalu memberi hasil kosong tanpa penjelasan.
            'gedungs'  => Gedung::whereHas('ruangans', fn ($q) => $q->dikelola()->where('aktif', true))
                ->orderBy('nama')->get(),
            // Dipakai oleh formulir reservasi di dalam pop-up.
            'daftarRuangan' => Ruangan::with('gedung')->dikelola()->where('aktif', true)->orderBy('urutan')->get(),
            'hasil'    => null,
            'bentrok'  => null,
            'isian'    => $this->isian($request, $periode),
        ];

        if ($request->filled('cari')) {
            // Pakai array_merge, bukan operator +=. Operator union tidak
            // menimpa kunci yang sudah ada, sehingga 'hasil' akan tetap null
            // dan halaman selalu tampak kosong.
            $data = array_merge($data, $this->jalankanPencarian($request, $periode, $data['isian']));
        }

        return view('cari.index', $data);
    }

    protected function isian(Request $request, ?Periode $periode = null): array
    {
        // Kalau semester belum mulai atau sudah lewat, tanggal bawaan
        // diarahkan ke dalam masa perkuliahan supaya pencarian langsung berguna.
        $bawaan  = $periode?->tanggalAcuan()->toDateString() ?? now()->toDateString();
        $tanggal = $request->query('tanggal', $bawaan);
        $carbon  = Carbon::parse($tanggal);

        return [
            'tanggal'   => $carbon->toDateString(),
            'hari'      => $carbon->dayOfWeekIso - 1,          // 0 = Senin
            'nama_hari' => $carbon->translatedFormat('l, j F Y'),
            'mulai'     => $request->query('mulai', '13:00'),
            'selesai'   => $request->query('selesai', '15:00'),
            'kapasitas' => (int) $request->query('kapasitas', 0),
            'gedung'    => $request->query('gedung'),
            'ruangan'   => $request->query('ruangan'),
        ];
    }

    protected function jalankanPencarian(Request $request, Periode $periode, array $isian): array
    {
        $mulai   = $this->keMenit($isian['mulai']);
        $selesai = $this->keMenit($isian['selesai']);

        if ($isian['hari'] < 0 || $isian['hari'] > 5) {
            return ['pesanGalat' => 'Tanggal yang dipilih jatuh pada hari Minggu, tidak ada jadwal perkuliahan.'];
        }

        $tanggal = Carbon::parse($isian['tanggal']);

        // Di luar masa perkuliahan tidak ada kelas, jadi pencarian tetap
        // dijalankan — hasilnya justru seluruh ruangan bebas.
        $jadwalBerlaku = $periode->mencakup($tanggal);
        if ($selesai <= $mulai) {
            return ['pesanGalat' => 'Jam selesai harus lebih besar daripada jam mulai.'];
        }

        $service = new PencarianRuanganService($periode->id);

        $hasil = $service->ruangKosong(
            $isian['hari'],
            $mulai,
            $selesai,
            $isian['gedung'] ? (int) $isian['gedung'] : null,
            $isian['kapasitas'],
            null,
            $jadwalBerlaku,
            $tanggal
        );

        // Bila pemohon menyebut ruangan tertentu, sekalian tampilkan
        // apa yang menghalangi ruangan itu.
        $bentrok = null;
        if ($isian['ruangan']) {
            $ruangan = Ruangan::where('kode', $isian['ruangan'])->first();
            if ($ruangan) {
                $bentrok = [
                    'ruangan' => $ruangan,
                    'daftar'  => $service->periksaBentrok(
                        $ruangan->id, $isian['hari'], $mulai, $selesai, null, $jadwalBerlaku
                    ),
                    'reservasi' => $service->reservasiBentrok($ruangan->id, $tanggal, $mulai, $selesai),
                    // Kuliah pengganti memakai ruangan juga, dan sumbernya
                    // tabel baris — bukan induk pengajuan seperti reservasi.
                    'pengganti' => \App\Services\PergantianService::penggantiPada($tanggal)
                        ->where('ruangan_id', $ruangan->id)
                        ->filter(fn ($b) => $b->menit_mulai < $selesai && $b->menit_selesai > $mulai)
                        ->values(),
                    // Ruangan bisa saja bebas dari jadwal tetapi sedang ditutup
                    // untuk perbaikan — itu pun harus diberitahukan.
                    'ditutup'   => PenutupanRuangan::where('ruangan_id', $ruangan->id)
                        ->padaTanggal($tanggal)->first(),
                ];
            }
        }

        return [
            'jadwalBerlaku' => $jadwalBerlaku,
            // Ruangan yang ditutup pada tanggal ini, ditampilkan sebagai
            // pemberitahuan agar civitas tahu sebelum mengajukan.
            'penutupanHariItu' => \App\Models\PenutupanRuangan::with('ruangan.gedung')
                ->padaTanggal($tanggal)
                ->get()
                ->sortBy(fn ($t) => $t->ruangan->urutan)
                ->values(),
            'pekanKe' => $periode->pekanKe($tanggal),
            'hasil'   => $hasil,
            'bentrok' => $bentrok,
            'durasi'  => round(($selesai - $mulai) / 60, 1),
            'terpakai' => $jadwalBerlaku
                ? Jadwal::untukPeriode($periode->id)
                    ->where('hari', $isian['hari'])
                    ->where('menit_mulai', '<', $selesai)
                    ->where('menit_selesai', '>', $mulai)
                    ->count()
                : 0,
        ];
    }

    protected function keMenit(string $jam): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $jam)), 2, 0);

        return $h * 60 + $m;
    }
}
