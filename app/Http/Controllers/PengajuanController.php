<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Pengajuan;
use App\Models\PengajuanBaris;
use App\Models\PenutupanRuangan;
use App\Models\Periode;
use App\Models\Ruangan;
use App\Services\PencarianRuanganService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengajuanController extends Controller
{
    /**
     * Pencarian mata kuliah untuk kotak cari pada formulir pergantian jadwal.
     * Mengembalikan JSON agar bisa dipakai tanpa memuat ulang halaman.
     */
    public function cariJadwal(Request $request): \Illuminate\Http\JsonResponse
    {
        $periode = Periode::berjalan();
        $kata    = trim((string) $request->query('q'));

        if (! $periode || mb_strlen($kata) < 2) {
            return response()->json([]);
        }

        $hasil = Jadwal::with(['mataKuliah', 'dosen', 'ruangan'])
            ->untukPeriode($periode->id)
            ->cari($kata)
            ->orderBy('hari')->orderBy('menit_mulai')
            ->limit(25)
            ->get();

        /*
         * Pemindahan yang sudah tercatat untuk mata kuliah ini, dikirim
         * bersama hasil pencarian supaya formulir bisa memperingatkan di
         * tempat — sebelum pengajuan dikirim dan ditolak pelayan.
         */
        $pindah = PengajuanBaris::whereIn('jadwal_id', $hasil->pluck('id'))
            ->whereIn('status', ['menunggu', 'disetujui'])
            ->get()
            ->groupBy('jadwal_id')
            ->map(fn ($baris) => $baris->map(fn (PengajuanBaris $x) => [
                'dari'   => $x->tanggal_asal->toDateString(),
                'ke'     => $x->tanggal->toDateString(),
                'dari_teks' => $x->tanggal_asal->translatedFormat('j F Y'),
                'ke_teks'   => $x->tanggal->translatedFormat('j F Y'),
                'status' => $x->status,
            ])->values());

        $hasil = $hasil
            ->map(fn (Jadwal $j) => [
                'pindah'   => $pindah->get($j->id, collect())->all(),
                'id'       => $j->id,
                'kode'     => $j->mataKuliah->kode ?? '',
                'nama'     => $j->mataKuliah->nama ?? '-',
                'dosen'    => $j->dosen->kode ?? '-',
                'kelas'    => $j->kelas,
                'hari'     => $j->nama_hari,
                'hari_ke'  => $j->hari,
                'mulai'    => substr($j->jam_mulai, 0, 5),
                'selesai'  => substr($j->jam_selesai, 0, 5),
                'durasi'   => $j->durasi_menit,
                'ruangan'  => $j->ruangan->kode ?? '-',
                'ruangan_id' => $j->ruangan_id,
            ]);

        return response()->json($hasil);
    }

    /**
     * Dikirim dari halaman Cari Ruang Kosong oleh civitas.
     * Saat disimpan, sistem sekalian memeriksa bentrok agar petugas
     * tidak perlu mengeceknya lagi secara manual.
     */
    public function store(Request $request, string $jenis): RedirectResponse
    {
        abort_unless(array_key_exists($jenis, Pengajuan::JENIS), 404);

        $periode = Periode::berjalan();

        if (! $periode) {
            return back()->withInput()->withErrors([
                'tanggal' => 'Belum ada semester yang sedang berjalan, jadi pengajuan belum bisa diterima. '
                    . 'Hubungi petugas.',
            ]);
        }

        return $jenis === 'ruangan'
            ? $this->simpanReservasi($request, $periode)
            : $this->simpanPergantian($request, $periode);
    }

    /* =====================================================================
     | Reservasi ruangan — satu waktu, satu ruangan.
     ===================================================================== */
    protected function simpanReservasi(Request $request, Periode $periode): RedirectResponse
    {
        $data = $request->validate([
            'nama'           => ['required', 'string', 'max:120'],
            'identitas'      => ['nullable', 'string', 'max:30'],
            'peran'          => ['required', 'string', 'max:30'],
            'unit'           => ['nullable', 'string', 'max:120'],
            'kontak'         => ['required', 'string', 'max:40'],
            'tanggal'        => ['required', 'date'],
            'mulai'          => ['required', 'date_format:H:i'],
            'selesai'        => ['required', 'date_format:H:i', 'after:mulai'],
            'ruangan_id'     => ['required', Rule::exists('ruangans', 'id')->where(fn ($q) => $q->whereIn('id', \App\Models\Ruangan::dikelola()->pluck('id')))],
            'jumlah_peserta' => ['required', 'integer', 'min:1', 'max:1000'],
            'keperluan'      => ['required', 'string', 'max:1000'],
        ], [], [
            'nama' => 'nama pemohon', 'kontak' => 'nomor WhatsApp',
            'mulai' => 'jam mulai', 'selesai' => 'jam selesai', 'ruangan_id' => 'ruangan',
        ]);

        $tanggal = Carbon::parse($data['tanggal']);
        $hari    = $tanggal->dayOfWeekIso - 1;

        if ($hari < 0 || $hari > 5) {
            return $this->tolak('ruangan', ['tanggal' => 'Tanggal jatuh pada hari Minggu, gedung tidak beroperasi.']);
        }

        $mulai   = $this->keMenit($data['mulai']);
        $selesai = $this->keMenit($data['selesai']);
        $berlaku = $periode->mencakup($tanggal);
        $cari    = new PencarianRuanganService($periode->id);

        $halangan = $this->periksaSatuWaktu(
            $cari, (int) $data['ruangan_id'], $tanggal, $hari, $mulai, $selesai, $berlaku
        );

        if ($halangan['pesan']) {
            return $this->tolak('ruangan', ['ruangan_id' => $halangan['pesan']], [
                'jenis'   => 'ruangan',
                'ruangan' => $this->saranRuangan($cari, $hari, $mulai, $selesai, $berlaku, $tanggal,
                    (int) $data['jumlah_peserta']),
                'waktu'   => [],
            ]);
        }

        $pengajuan = Pengajuan::create([
            'nomor'          => Pengajuan::nomorBaru('ruangan'),
            'jenis'          => 'ruangan',
            'periode_id'     => $periode->id,
            'nama'           => $data['nama'],
            'identitas'      => $data['identitas'] ?? null,
            'peran'          => $data['peran'],
            'unit'           => $data['unit'] ?? null,
            'kontak'         => $data['kontak'],
            'tanggal'        => $tanggal->toDateString(),
            'hari'           => $hari,
            'menit_mulai'    => $mulai,
            'menit_selesai'  => $selesai,
            'jam_mulai'      => $data['mulai'] . ':00',
            'jam_selesai'    => $data['selesai'] . ':00',
            'ruangan_id'     => $data['ruangan_id'],
            'jumlah_peserta' => $data['jumlah_peserta'],
            'keperluan'      => $data['keperluan'],
        ]);

        return back()->with('pesan',
            "Pengajuan {$pengajuan->nomor} terkirim. Petugas akan menghubungi Anda lewat WhatsApp."
            . ($berlaku ? '' : ' Tanggal ini di luar masa perkuliahan, jadi tidak ada kelas yang terganggu.'));
    }

    /* =====================================================================
     | Pergantian jadwal — boleh beberapa pemindahan dalam satu pengajuan.
     ===================================================================== */
    protected function simpanPergantian(Request $request, Periode $periode): RedirectResponse
    {
        $data = $request->validate([
            'nama'                  => ['required', 'string', 'max:120'],
            'identitas'             => ['nullable', 'string', 'max:30'],
            'peran'                 => ['required', 'string', 'max:30'],
            'unit'                  => ['nullable', 'string', 'max:120'],
            'kontak'                => ['required', 'string', 'max:40'],
            'keperluan'             => ['required', 'string', 'max:1000'],
            'baris'                 => ['required', 'array', 'min:1', 'max:10'],
            'baris.*.jadwal_id'     => ['required', 'exists:jadwals,id'],
            'baris.*.tanggal_asal'  => ['required', 'date'],
            'baris.*.tanggal'       => ['required', 'date'],
            'baris.*.mulai'         => ['required', 'date_format:H:i'],
            'baris.*.selesai'       => ['required', 'date_format:H:i'],
            'baris.*.ruangan_id'    => ['required', Rule::exists('ruangans', 'id')->where(fn ($q) => $q->whereIn('id', \App\Models\Ruangan::dikelola()->pluck('id')))],
        ], [
            'baris.required' => 'Isi minimal satu pergantian.',
        ], [
            'nama' => 'nama pemohon', 'kontak' => 'nomor WhatsApp',
        ]);

        $cari     = new PencarianRuanganService($periode->id);
        $siap     = [];
        $galat    = [];
        $saran    = ['jenis' => null, 'ruangan' => [], 'waktu' => []];

        // Pertemuan yang dibatalkan di dalam pengajuan ini sendiri, supaya
        // satu formulir tidak bisa membatalkan tanggal yang sama dua kali.
        $dibatalkan = [];

        foreach ($data['baris'] as $i => $b) {
            $asal        = Jadwal::find($b['jadwal_id']);
            $tanggalAsal = Carbon::parse($b['tanggal_asal']);
            $tanggal     = Carbon::parse($b['tanggal']);
            $hari        = $tanggal->dayOfWeekIso - 1;
            $mulai       = $this->keMenit($b['mulai']);
            $selesai     = $this->keMenit($b['selesai']);
            $label       = 'Pergantian ke-' . ($i + 1) . ': ';

            if ($selesai <= $mulai) {
                $galat["baris.{$i}.selesai"] = $label . 'jam selesai harus setelah jam mulai.';
                continue;
            }

            /*
             * Satu pertemuan hanya ada satu. Begitu pertemuan 12 Juni dipindah
             * ke 20 Juni, pertemuan 12 Juni itu habis — yang berikutnya bisa
             * dipindah adalah penggantinya, yaitu 20 Juni.
             *
             * Tanpa penjagaan ini satu pertemuan bisa dibatalkan berkali-kali
             * oleh orang berbeda, dan petugas baru menyadarinya setelah
             * menyetujui dua-duanya.
             */
            $kunci = $asal?->id . '|' . $tanggalAsal->toDateString();

            if (isset($dibatalkan[$kunci])) {
                $galat["baris.{$i}.tanggal_asal"] = $label . 'pertemuan '
                    . $tanggalAsal->translatedFormat('j F Y')
                    . ' sudah dibatalkan pada pergantian ke-' . $dibatalkan[$kunci]
                    . ' di formulir ini.';
                continue;
            }

            // Rantai pemindahan ditelusuri sampai ujung. Untuk 12 → 13 → 20,
            // peringatan harus menyebut 20 Oktober: tanggal 13 sudah ikut
            // ditinggalkan, dan menyuruh pemohon mengisi 13 hanya akan
            // membuat mereka ditolak sekali lagi.
            $sudahPindah = $asal
                ? PengajuanBaris::rantaiPindah($asal->id, $tanggalAsal->toDateString())
                : null;

            if ($sudahPindah) {
                $galat["baris.{$i}.tanggal_asal"] = $label . 'perkuliahan pada '
                    . $tanggalAsal->translatedFormat('j F Y') . ' sudah diubah ke '
                    . $sudahPindah->tanggal->translatedFormat('j F Y')
                    . ' ('
                    . ($sudahPindah->status === 'disetujui'
                        ? 'sudah disetujui'
                        : 'masih menunggu persetujuan')
                    . '). Bila yang ingin dipindah adalah pertemuan penggantinya, isi '
                    . $sudahPindah->tanggal->translatedFormat('j F Y')
                    . ' sebagai tanggal yang dibatalkan.';
                continue;
            }

            // Tanggal pengganti dari pemindahan sebelumnya sah dipakai sebagai
            // tanggal yang dibatalkan, walaupun jatuh di hari selain hari
            // kuliah aslinya — pertemuan itu memang sudah pindah ke sana.
            $adalahPengganti = $asal && PengajuanBaris::where('jadwal_id', $asal->id)
                ->whereDate('tanggal', $tanggalAsal->toDateString())
                ->whereIn('status', ['menunggu', 'disetujui'])
                ->exists();

            // Pertemuan yang dibatalkan harus jatuh pada hari kuliahnya.
            if ($asal && ! $adalahPengganti && ($tanggalAsal->dayOfWeekIso - 1) !== $asal->hari) {
                $galat["baris.{$i}.tanggal_asal"] = $label . 'mata kuliah ini dijadwalkan setiap hari '
                    . $asal->nama_hari . ', bukan ' . $tanggalAsal->translatedFormat('l') . '.';
                continue;
            }

            if (! $periode->mencakup($tanggalAsal)) {
                $galat["baris.{$i}.tanggal_asal"] = $label . 'tanggal itu di luar masa perkuliahan.';
                continue;
            }

            if ($hari < 0 || $hari > 5) {
                $galat["baris.{$i}.tanggal"] = $label . 'tanggal pengganti jatuh pada hari Minggu.';
                continue;
            }

            $berlaku  = $periode->mencakup($tanggal);
            $halangan = $this->periksaSatuWaktu(
                $cari, (int) $b['ruangan_id'], $tanggal, $hari, $mulai, $selesai, $berlaku, $asal?->id
            );

            $bentrokOrang = $berlaku && $asal
                ? $cari->bentrokDosenDanKelas($asal->dosen_id, $asal->kelas, $hari, $mulai, $selesai, $asal->id)
                : ['dosen' => collect(), 'kelas' => collect()];

            if ($halangan['pesan']) {
                $galat["baris.{$i}.ruangan_id"] = $label . $halangan['pesan'];
                $saran['jenis']   = 'ruangan';
                $saran['ruangan'] = $this->saranRuangan($cari, $hari, $mulai, $selesai, $berlaku, $tanggal);
                continue;
            }

            if ($bentrokOrang['dosen']->isNotEmpty() || $bentrokOrang['kelas']->isNotEmpty()) {
                $galat["baris.{$i}.mulai"] = $label . ($bentrokOrang['dosen']->isNotEmpty()
                    ? 'dosen pengampu sedang mengajar di tempat lain pada jam itu.'
                    : 'kelas ' . $asal->kelas . ' punya mata kuliah lain pada jam itu.');

                $saran['jenis'] = 'waktu';
                $saran['waktu'] = $cari->saranWaktu(
                    $tanggal, $selesai - $mulai, $asal->dosen_id, $asal->kelas, $asal->id
                )->all();
                continue;
            }

            $dibatalkan[$kunci] = $i + 1;

            $siap[] = [
                'jadwal_id'     => $asal?->id,
                'ruangan_id'    => $b['ruangan_id'],
                'tanggal_asal'  => $tanggalAsal->toDateString(),
                'tanggal'       => $tanggal->toDateString(),
                'hari'          => $hari,
                'menit_mulai'   => $mulai,
                'menit_selesai' => $selesai,
                'jam_mulai'     => $b['mulai'] . ':00',
                'jam_selesai'   => $b['selesai'] . ':00',
                'bentrok'       => false,
                'bentrok_dosen' => false,
                'bentrok_kelas' => false,
            ];
        }

        if ($galat) {
            return $this->tolak('pergantian', $galat, $saran);
        }

        $pertama = $siap[0];

        $pengajuan = Pengajuan::create([
            'nomor'         => Pengajuan::nomorBaru('pergantian'),
            'jenis'         => 'pergantian',
            'periode_id'    => $periode->id,
            'nama'          => $data['nama'],
            'identitas'     => $data['identitas'] ?? null,
            'peran'         => $data['peran'],
            'unit'          => $data['unit'] ?? null,
            'kontak'        => $data['kontak'],
            'keperluan'     => $data['keperluan'],
            // Kolom waktu pada induk diisi dari baris pertama, dipakai untuk
            // penyortiran dan ringkasan di daftar.
            'tanggal'       => $pertama['tanggal'],
            'tanggal_asal'  => $pertama['tanggal_asal'],
            'hari'          => $pertama['hari'],
            'menit_mulai'   => $pertama['menit_mulai'],
            'menit_selesai' => $pertama['menit_selesai'],
            'jam_mulai'     => $pertama['jam_mulai'],
            'jam_selesai'   => $pertama['jam_selesai'],
            'ruangan_id'    => $pertama['ruangan_id'],
            'jadwal_id'     => $pertama['jadwal_id'],
        ]);

        foreach ($siap as $baris) {
            $pengajuan->baris()->create($baris);
        }

        return back()->with('pesan', "Pengajuan {$pengajuan->nomor} terkirim dengan "
            . count($siap) . " pergantian. Petugas akan menghubungi Anda lewat WhatsApp.");
    }

    /**
     * Periksa satu rentang waktu terhadap penutupan ruangan, jadwal kuliah,
     * dan pengajuan lain. Mengembalikan pesan bila ada halangan.
     */
    protected function periksaSatuWaktu(
        PencarianRuanganService $cari,
        int $ruanganId,
        Carbon $tanggal,
        int $hari,
        int $mulai,
        int $selesai,
        bool $jadwalBerlaku,
        ?int $abaikanJadwal = null
    ): array {
        $ditutup = PenutupanRuangan::where('ruangan_id', $ruanganId)->padaTanggal($tanggal)->first();

        if ($ditutup) {
            return ['pesan' => 'ruangan sedang ditutup pada ' . $ditutup->rentang_teks
                . ' karena ' . $ditutup->alasan . '.'];
        }

        // Hanya reservasi ruangan; pemakaian kuliah pengganti diperiksa
        // di bawah lewat tabel baris, yang isinya selalu terkini.
        $dipesan = Pengajuan::jenis('ruangan')
            ->where('ruangan_id', $ruanganId)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('status', ['disetujui', 'menunggu'])
            ->where('menit_mulai', '<', $selesai)
            ->where('menit_selesai', '>', $mulai)
            ->first();

        if ($dipesan) {
            // Formulir ini terbuka tanpa login, jadi pemesan dan nomornya tidak disebut.
            return ['pesan' => 'ruangan sudah dipesan pada ' . $dipesan->rentang_jam . '.'];
        }

        /*
         * Kuliah pengganti juga memakai ruangan. Baris pergantian punya
         * tanggal dan jamnya sendiri, dan hanya baris pertama yang ikut
         * tersalin ke induk pengajuan — jadi memeriksa tabel pengajuans
         * saja membuat pertemuan pengganti kedua dan seterusnya tidak
         * mengunci ruangannya sama sekali.
         */
        $dipakaiPengganti = PengajuanBaris::with(['pengajuan', 'jadwal.mataKuliah'])
            ->where('ruangan_id', $ruanganId)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->aktif()
            // Pengganti yang sudah dipindah lagi tidak memakai ruangan ini.
            ->belumDipindahLagi()
            ->where('menit_mulai', '<', $selesai)
            ->where('menit_selesai', '>', $mulai)
            ->first();

        if ($dipakaiPengganti) {
            return ['pesan' => 'ruangan sudah dipakai kuliah pengganti '
                . ($dipakaiPengganti->jadwal->mataKuliah->nama ?? 'kelas lain')
                . ' pada ' . $dipakaiPengganti->rentang_jam . '.'];
        }

        $bentrok = $cari->periksaBentrok($ruanganId, $hari, $mulai, $selesai, $abaikanJadwal, $jadwalBerlaku);

        if ($bentrok->isNotEmpty()) {
            return ['pesan' => 'ruangan dipakai ' . ($bentrok->first()->mataKuliah->nama ?? 'kegiatan lain')
                . ' (' . $bentrok->first()->rentang_jam . ').'];
        }

        return ['pesan' => null];
    }

    protected function tolak(string $jenis, array $galat, ?array $saran = null): RedirectResponse
    {
        $balik = back()->withInput()->withErrors($galat)->with('bukaModal', $jenis);

        return $saran ? $balik->with('saran', $saran) : $balik;
    }

    /** Ruangan lain yang bebas pada rentang jam itu, untuk ditawarkan ke pemohon. */
    protected function saranRuangan(
        PencarianRuanganService $pencarian,
        int $hari,
        int $mulai,
        int $selesai,
        bool $jadwalBerlaku,
        Carbon $tanggal,
        int $kapasitasMinimal = 0
    ): array {
        return $pencarian
            ->ruangKosong($hari, $mulai, $selesai, null, $kapasitasMinimal, null, $jadwalBerlaku, $tanggal)
            ->take(8)
            ->map(fn ($x) => [
                'id'        => $x['ruangan']->id,
                'kode'      => $x['ruangan']->kode,
                'gedung'    => $x['ruangan']->gedung->kode_pendek ?? '',
                'kapasitas' => $x['ruangan']->kapasitas_efektif,
                'taksiran'  => $x['ruangan']->kapasitas_perkiraan,
            ])->values()->all();
    }

    protected function keMenit(string $jam): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $jam)), 2, 0);

        return $h * 60 + $m;
    }
}
