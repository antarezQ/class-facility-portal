<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Ruangan;
use Illuminate\Support\Collection;

/**
 * Semua perhitungan utilisasi dan okupansi dipusatkan di sini
 * supaya rumusnya hanya ditulis satu kali.
 *
 * Rumus dasar:
 *   jam operasional  = jam_tutup - jam_buka          (06:30–18:30 = 12 jam)
 *   kapasitas pekan  = jumlah ruang x jam operasional x hari operasional
 *   utilisasi        = total jam terjadwal / kapasitas pekan x 100%
 */
class AnalitikService
{
    protected float $jamOperasional;
    protected int $hariOperasional;
    protected int $pekanEfektif;

    public function __construct(
        protected int $periodeId,
        protected ?int $gedungId = null,
        protected ?\Carbon\Carbon $tanggalAcuan = null
    ) {
        $buka  = $this->keMenit(config('jadwal.jam_buka'));
        $tutup = $this->keMenit(config('jadwal.jam_tutup'));

        $this->jamOperasional  = ($tutup - $buka) / 60;
        $this->hariOperasional = (int) config('jadwal.hari_operasional', 6);
        // Kalau rentang tanggal semester sudah diisi, pakai jumlah pekan
        // sebenarnya. Kalau belum, jatuh ke nilai bawaan pada config.
        $periode = \App\Models\Periode::find($periodeId);
        $this->pekanEfektif = $periode ? $periode->jumlahPekan() : (int) config('jadwal.pekan_efektif', 16);
    }

    protected function keMenit(string $jam): int
    {
        [$h, $m] = array_map('intval', explode(':', $jam));

        return $h * 60 + $m;
    }

    /**
     * Hanya baris jadwal yang berlaku pada tanggal acuan. Satu mata kuliah
     * yang pernah dipindah permanen punya dua baris, dan tanpa saringan ini
     * keduanya ikut terhitung sehingga utilisasi menggelembung.
     */
    protected function query()
    {
        return Jadwal::query()
            ->untukPeriode($this->periodeId)
            ->berlakuPada($this->tanggalAcuan)
            ->untukGedung($this->gedungId);
    }

    /**
     * Seperti query(), tetapi hanya hari yang diperhitungkan sebagai kapasitas.
     *
     * Dipakai KHUSUS untuk pembilang utilisasi dan okupansi. Dengan
     * hari_operasional = 5, kelas Sabtu tidak ikut dijumlahkan — sama seperti
     * Sabtu juga tidak ikut dihitung di penyebutnya.
     *
     * Jangan dipakai untuk overtime(): lembur CS pada hari Sabtu tetap harus
     * terhitung meski harinya tidak masuk perhitungan kapasitas. Jangan pula
     * untuk perHari(), yang memang bertugas menampilkan semua hari apa adanya.
     */
    protected function queryHitung()
    {
        return $this->query()->where('hari', '<', $this->hariOperasional);
    }

    /** Ruangan yang benar-benar terpakai pada periode & gedung ini. */
    public function ruangan(): Collection
    {
        $ids = $this->query()->distinct()->pluck('ruangan_id');

        return Ruangan::with('gedung')->whereIn('id', $ids)->orderBy('urutan')->get();
    }

    /**
     * Ruangan untuk tampilan timeline: yang dipakai jadwal ditambah ruangan
     * khusus, karena ruangan khusus juga bisa dipesan meski tak punya jadwal.
     */
    public function ruanganTampil(): Collection
    {
        $ids = $this->query()->distinct()->pluck('ruangan_id')
            ->merge(Ruangan::manual()->where('aktif', true)->pluck('id'))
            ->unique();

        return Ruangan::with([
                'gedung',
                'penutupans' => fn ($q) => $q->belumLewat(),
                'asets'      => fn ($q) => $q->where('status', 'aktif')->orderBy('kode'),
            ])
            ->whereIn('id', $ids)->orderBy('urutan')->get();
    }

    /**
     * Seluruh ruangan aktif pada gedung ini, dipakai jadwal atau belum.
     *
     * Dipakai tab Daftar Ruangan. Daftar itu menjawab "ruang apa saja yang
     * ada di gedung ini", bukan "ruang mana yang terpakai", jadi ruangan yang
     * baru didaftarkan dan belum diisi mata kuliah tetap
     * harus muncul — lengkap dengan kapasitas dan fasilitasnya.
     *
     * Berbeda dari ruangan() dan ruanganTampil(), penyaring gedungnya dibaca
     * dari ruangannya sendiri, bukan dari jadwal — ruangan tanpa jadwal tidak
     * punya baris jadwal yang bisa dijadikan acuan gedung.
     */
    public function ruanganSemua(): Collection
    {
        return Ruangan::with([
                'gedung',
                'penutupans' => fn ($q) => $q->belumLewat(),
                'asets'      => fn ($q) => $q->where('status', 'aktif')->orderBy('kode'),
            ])
            ->where('aktif', true)
            ->when($this->gedungId, fn ($q) => $q->where('gedung_id', $this->gedungId))
            ->orderBy('urutan')
            ->get();
    }

    public function ringkasan(): array
    {
        $ruangan   = $this->ruangan();
        // Pembilang memakai queryHitung: hanya hari yang kapasitasnya diakui.
        $totalJam  = $this->queryHitung()->sum('durasi_menit') / 60;
        $kapasitas = $ruangan->count() * $this->jamOperasional * $this->hariOperasional;

        return [
            'jumlah_ruangan'    => $ruangan->count(),
            'kapasitas_kursi'   => $ruangan->sum(fn (Ruangan $r) => $r->kapasitas_efektif),
            'kapasitas_taksiran' => $ruangan->every(fn (Ruangan $r) => $r->kapasitas_perkiraan),
            // Jumlah jadwal sengaja dihitung utuh, termasuk Sabtu: ini cacah
            // isi jadwal, bukan masukan perhitungan kapasitas.
            'jumlah_jadwal'     => $this->query()->count(),
            'jadwal_dihitung'   => $this->queryHitung()->count(),
            'jam_diabaikan'     => round(
                ($this->query()->sum('durasi_menit') - $this->queryHitung()->sum('durasi_menit')) / 60, 1
            ),
            'jam_per_pekan'     => round($totalJam, 1),
            'jam_per_semester'  => round($totalJam * $this->pekanEfektif, 1),
            'kapasitas_jam'     => $kapasitas,
            'utilisasi'         => $kapasitas > 0 ? round($totalJam / $kapasitas * 100, 2) : 0.0,
        ];
    }

    /**
     * Utilisasi versi hari kerja berdampingan dengan versi yang memasukkan
     * Sabtu, supaya selisihnya terlihat dan bisa dipertanggungjawabkan.
     *
     * Keduanya konsisten di dalam dirinya sendiri: versi 5 hari memakai jam
     * Senin–Jumat dibagi kapasitas 5 hari, versi 6 hari memakai seluruh jam
     * dibagi kapasitas 6 hari. Tidak disediakan versi campur, karena jam
     * Sabtu di pembilang tanpa kapasitas Sabtu di penyebut hanya menghasilkan
     * angka yang terdengar bagus tetapi keliru.
     *
     * @return array<int, array{hari: int, label: string, jam: float, kapasitas: float, utilisasi: float, dipakai: bool}>
     */
    public function bandingHari(): array
    {
        $ruang = $this->ruangan()->count();
        $out   = [];

        foreach ([$this->hariOperasional, 6] as $hari) {
            $jam = $this->query()->where('hari', '<', $hari)->sum('durasi_menit') / 60;
            $kap = $ruang * $this->jamOperasional * $hari;

            $out[$hari] = [
                'hari'      => $hari,
                'label'     => $hari >= 6 ? 'Senin–Sabtu' : 'Senin–Jumat',
                'jam'       => round($jam, 1),
                'kapasitas' => $kap,
                'utilisasi' => $kap > 0 ? round($jam / $kap * 100, 2) : 0.0,
                'dipakai'   => $hari === $this->hariOperasional,
            ];
        }

        return array_values($out);
    }

    /** Tabel Analitik [Hari]: total jam & tingkat penggunaan per pekan dan per semester. */
    public function perHari(): Collection
    {
        $jumlahRuang = $this->ruangan()->count();
        $kapasitasHarian = $jumlahRuang * $this->jamOperasional;

        $agregat = $this->query()
            ->selectRaw('hari, COUNT(*) as jumlah, SUM(durasi_menit) as menit')
            ->groupBy('hari')
            ->pluck('menit', 'hari');

        $jumlah = $this->query()
            ->selectRaw('hari, COUNT(*) as total')
            ->groupBy('hari')
            ->pluck('total', 'hari');

        return collect(Jadwal::HARI)->map(function (string $nama, int $i) use ($agregat, $jumlah, $kapasitasHarian) {
            $jam = ($agregat[$i] ?? 0) / 60;
            $pct = $kapasitasHarian > 0 ? $jam / $kapasitasHarian * 100 : 0;

            return [
                'hari'                  => $nama,
                'jam_pekan'             => round($jam, 1),
                'penggunaan_pekan'      => round($pct, 2),
                'jam_semester'          => round($jam * $this->pekanEfektif, 1),
                'penggunaan_semester'   => round($pct, 2),
                'jumlah_jadwal'         => $jumlah[$i] ?? 0,
            ];
        });
    }

    /**
     * Tabel Analitik [Ruang Kelas]: rasio mahasiswa dan okupansi per ruangan.
     *
     * @param  bool      $semuaRuangan  true untuk ikut menghitung ruangan yang
     *                                  belum punya jadwal sama sekali — angkanya
     *                                  nol, tetapi ruangannya tetap terdaftar.
     *                                  Dipakai tab Daftar Ruangan.
     * @param  int|null  $hariHitung    Rentang hari yang dipakai, dihitung dari
     *                                  Senin. Mengubahnya menggeser PEMBILANG
     *                                  dan PENYEBUT sekaligus, sehingga hasilnya
     *                                  tetap konsisten. Dipakai halaman Analitik
     *                                  untuk menampilkan versi dengan dan tanpa
     *                                  Sabtu berdampingan. Null memakai angka
     *                                  dari config.
     */
    public function perRuangan(bool $semuaRuangan = false, ?int $hariHitung = null): Collection
    {
        $hari = $hariHitung ?? $this->hariOperasional;
        $kapasitasPekan = $this->jamOperasional * $hari;

        // Seluruh angka di baris ini — jam, jumlah sesi, okupansi — dihitung
        // dari hari yang sama, supaya jam dan jumlah sesinya tidak berasal
        // dari cakupan yang berbeda.
        $agregat = $this->query()
            ->where('hari', '<', $hari)
            ->selectRaw('ruangan_id, COUNT(*) as jumlah, SUM(durasi_menit) as menit, AVG(jml_mhs) as rata, MAX(jml_mhs) as maks')
            ->groupBy('ruangan_id')
            ->get()
            ->keyBy('ruangan_id');

        $daftar = $semuaRuangan ? $this->ruanganSemua() : $this->ruangan();

        return $daftar->map(function (Ruangan $r) use ($agregat, $kapasitasPekan) {
            $a   = $agregat[$r->id] ?? null;
            $jam = $a ? $a->menit / 60 : 0;
            $kap = $r->kapasitas_efektif;

            return [
                'kode'            => $r->kode,
                'urutan'          => $r->urutan,
                'gedung'          => $r->gedung->kode_pendek ?? $r->gedung->nama,
                'lantai'          => $r->lantai,
                'jumlah_jadwal'   => $a->jumlah ?? 0,
                'kapasitas'       => $kap,
                'kapasitas_taksiran' => $r->kapasitas_perkiraan,
                'rata_mahasiswa'  => round((float) ($a->rata ?? 0), 1),
                'maks_mahasiswa'  => (int) ($a->maks ?? 0),
                'rasio'           => $kap > 0 ? round(((float) ($a->rata ?? 0)) / $kap * 100, 1) : 0,
                'jam_pekan'       => round($jam, 1),
                'jam_semester'    => round($jam * $this->pekanEfektif, 1),
                'okupansi'        => $kapasitasPekan > 0 ? round($jam / $kapasitasPekan * 100, 2) : 0,
            ];
        })->sortBy('urutan')->values();   // gedung, lantai, lalu nomor ruang
    }

    /**
     * Utilisasi masing-masing gedung, dihitung memakai kapasitas gedung itu
     * sendiri. Menggabungkan dua gedung menjadi satu angka menyesatkan,
     * karena jumlah ruang dan pola pemakaiannya berbeda.
     */
    public function perGedung(): Collection
    {
        $ruangan = $this->ruangan()->groupBy('gedung_id');

        return $ruangan->map(function ($daftar) {
            $gedung = $daftar->first()->gedung;
            $ids    = $daftar->pluck('id');
            $dasar  = $this->queryHitung()->whereIn('ruangan_id', $ids);

            // Dulu memakai Jadwal::untukPeriode() langsung, tanpa berlakuPada.
            // Akibatnya mata kuliah yang pernah dipindah permanen terhitung
            // dua kali di sini, padahal tabel lain sudah menyaringnya — angka
            // per gedung jadi lebih besar daripada jumlah seluruh ruangnya.
            $menit = (clone $dasar)->sum('durasi_menit');

            $jam       = $menit / 60;
            $kapasitas = $daftar->count() * $this->jamOperasional * $this->hariOperasional;

            return [
                'gedung'         => $gedung->kode_pendek ?? $gedung->nama,
                'nama'           => $gedung->nama,
                'jumlah_ruangan' => $daftar->count(),
                'jumlah_jadwal'  => (clone $dasar)->count(),
                'jam_pekan'      => round($jam, 1),
                'jam_semester'   => round($jam * $this->pekanEfektif, 1),
                'kapasitas_jam'  => $kapasitas,
                'utilisasi'      => $kapasitas > 0 ? round($jam / $kapasitas * 100, 2) : 0,
                // Overtime memakai query() yang masih memuat Sabtu: lembur CS
                // pada hari Sabtu tetap nyata dan tetap harus dibayar.
                'overtime'       => $this->query()->whereIn('ruangan_id', $ids)
                    ->where('overtime', true)->count(),
            ];
        })->sortBy('gedung')->values();
    }

    /**
     * Beban tiap jam: berapa baris jadwal yang sedang berjalan pada jam itu,
     * DIJUMLAHKAN atas seluruh hari yang diperhitungkan.
     *
     * Perlu ditegaskan karena mudah disalahpahami: angkanya bukan jumlah kelas
     * yang berjalan serentak dalam satu hari. Pukul 09.30 bernilai 98 berarti
     * ada 98 baris jadwal sepanjang Senin–Jumat yang melintasi jam itu, bukan
     * 98 kelas sekaligus — yang mustahil di gedung berisi 31 ruang. Untuk
     * angka per hari, bagi dengan jumlah hari yang diperhitungkan.
     */
    public function perJam(): Collection
    {
        $buka   = $this->keMenit(config('jadwal.jam_buka'));
        $tutup  = $this->keMenit(config('jadwal.jam_tutup'));
        $akhir  = $this->keMenit(config('jadwal.jam_tampil_akhir', '22:00'));
        $rows   = $this->queryHitung()->get(['menit_mulai', 'menit_selesai']);

        $out = collect();
        for ($m = $buka; $m < $akhir; $m += 60) {
            $out->push([
                'menit'  => $m,
                'jam'    => sprintf('%02d:%02d', intdiv($m, 60), $m % 60),
                'jumlah' => $rows->filter(fn ($r) => $r->menit_mulai <= $m && $r->menit_selesai > $m)->count(),
                'lewat'  => $m >= $tutup,   // di luar jam operasional
            ]);
        }

        return $out;
    }

    public function perProdi(): Collection
    {
        return $this->query()
            ->selectRaw('prodi, COUNT(*) as jumlah, SUM(durasi_menit) as menit')
            ->groupBy('prodi')
            ->orderByDesc('menit')
            ->get()
            ->map(fn ($r) => [
                'prodi'  => $r->prodi,
                'jumlah' => $r->jumlah,
                'jam'    => round($r->menit / 60, 1),
            ]);
    }

    /** Jadwal yang keluar dari jam operasional — dasar perhitungan lembur CS. */
    public function overtime(): Collection
    {
        return $this->query()
            ->with(['ruangan', 'dosen', 'mataKuliah'])
            ->where('overtime', true)
            ->join('ruangans', 'ruangans.id', '=', 'jadwals.ruangan_id')
            ->orderBy('jadwals.hari')
            ->orderBy('jadwals.menit_mulai')
            ->orderBy('ruangans.urutan')
            ->select('jadwals.*')
            ->get();
    }

    /**
     * Celah kosong minimal $minimalMenit pada satu hari,
     * dipakai untuk mencari ruangan yang bisa dipinjam.
     */
    public function celahKosong(int $hari, int $minimalMenit = 60): Collection
    {
        $buka  = $this->keMenit(config('jadwal.jam_buka'));
        $tutup = $this->keMenit(config('jadwal.jam_tutup'));

        $terpakai = $this->query()->untukHari($hari)->get(['ruangan_id', 'menit_mulai', 'menit_selesai'])
            ->groupBy('ruangan_id');

        return $this->ruangan()->map(function (Ruangan $r) use ($terpakai, $buka, $tutup, $minimalMenit) {
            $isi = ($terpakai[$r->id] ?? collect())->sortBy('menit_mulai');
            $celah = [];
            $kursor = $buka;

            foreach ($isi as $j) {
                if ($j->menit_mulai - $kursor >= $minimalMenit) {
                    $celah[] = ['mulai' => $kursor, 'selesai' => $j->menit_mulai];
                }
                $kursor = max($kursor, $j->menit_selesai);
            }
            if ($tutup - $kursor >= $minimalMenit) {
                $celah[] = ['mulai' => $kursor, 'selesai' => $tutup];
            }

            return ['ruangan' => $r, 'celah' => $celah];
        })->filter(fn ($x) => count($x['celah']) > 0)->values();
    }
}
