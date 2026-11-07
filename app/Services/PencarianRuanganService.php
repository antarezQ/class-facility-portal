<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Pengajuan;
use App\Models\PenutupanRuangan;
use App\Models\Ruangan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menjawab dua pertanyaan yang paling sering masuk ke unit pelayanan:
 *
 *   1. "Hari X jam sekian sampai sekian, ruang mana yang kosong?"
 *   2. "Kalau kelas ini dipindah ke ruang X jam sekian, bentrok tidak?"
 *
 * Keduanya memakai satu aturan irisan waktu yang sama:
 * dua kegiatan beririsan bila  mulai_A < selesai_B  DAN  mulai_B < selesai_A.
 */
class PencarianRuanganService
{
    public function __construct(protected int $periodeId)
    {
    }

    /**
     * Ruangan yang bebas sepanjang rentang jam yang diminta.
     *
     * Hasilnya diurutkan dari kapasitas terkecil yang masih memenuhi kebutuhan,
     * supaya ruang besar tidak terpakai untuk rombongan kecil.
     */
    public function ruangKosong(
        int $hari,
        int $menitMulai,
        int $menitSelesai,
        ?int $gedungId = null,
        int $kapasitasMinimal = 0,
        ?string $tipe = null,
        bool $jadwalBerlaku = true,
        ?Carbon $tanggal = null
    ): Collection {
        // Di luar masa perkuliahan tidak ada kelas yang berjalan,
        // sehingga seluruh ruangan dianggap bebas.
        // Pertemuan yang sudah dipindah ke tanggal lain tidak lagi memakai
        // ruangannya pada tanggal ini, jadi ruangannya kembali bebas.
        $dibatalkan = $tanggal ? PergantianService::jadwalDibatalkan($tanggal) : collect();

        $terpakai = $jadwalBerlaku
            ? Jadwal::query()
                ->untukPeriode($this->periodeId)
                ->berlakuPada($tanggal)
                ->where('hari', $hari)
                ->where('menit_mulai', '<', $menitSelesai)
                ->where('menit_selesai', '>', $menitMulai)
                ->when($dibatalkan->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $dibatalkan))
                ->pluck('ruangan_id')
                ->unique()
            : collect();

        // Reservasi yang sudah disetujui menutup ruangan pada tanggal itu,
        // meski di luar masa perkuliahan. Kuliah pengganti pun begitu —
        // tanggalnya sendiri, bukan pola mingguan jadwal.
        if ($tanggal) {
            $terpakai = $terpakai
                ->merge($this->ruanganDireservasi($tanggal, $menitMulai, $menitSelesai))
                ->merge(PergantianService::ruanganTerpakai($tanggal, $menitMulai, $menitSelesai))
                ->merge($this->ruanganDitutup($tanggal))
                ->unique();
        }

        // Hanya ruangan yang benar-benar dikelola yang boleh ditawarkan.
        // Gedung lain tetap ada di basis data karena jadwalnya diperlukan
        // untuk memindahkan kelas, tetapi tidak untuk dipinjamkan.
        $kandidat = Ruangan::with('gedung')
            ->dikelola()
            ->when($gedungId, fn ($q) => $q->where('gedung_id', $gedungId))
            ->when($tipe, fn ($q) => $q->where('tipe', $tipe))
            ->where('aktif', true)
            ->whereIn('id', $this->ruanganTerdaftar())
            ->whereNotIn('id', $terpakai)
            ->get();

        // Jadwal lain pada hari itu, dipakai untuk memberi konteks
        // "sebelum/sesudah" agar petugas tahu jeda yang tersisa.
        $jadwalHari = $jadwalBerlaku
            ? Jadwal::with('mataKuliah')
                ->untukPeriode($this->periodeId)
                ->berlakuPada($tanggal)
                ->where('hari', $hari)
                ->whereIn('ruangan_id', $kandidat->pluck('id'))
                ->orderBy('menit_mulai')
                ->get()
                ->groupBy('ruangan_id')
            : collect();

        return $kandidat
            ->filter(fn (Ruangan $r) => $r->kapasitas_efektif >= $kapasitasMinimal)
            ->map(function (Ruangan $r) use ($jadwalHari, $menitMulai, $menitSelesai) {
                $isi = $jadwalHari[$r->id] ?? collect();

                $sebelum = $isi->filter(fn ($j) => $j->menit_selesai <= $menitMulai)->last();
                $sesudah = $isi->filter(fn ($j) => $j->menit_mulai >= $menitSelesai)->first();

                return [
                    'ruangan'      => $r,
                    'sebelum'      => $sebelum,
                    'sesudah'      => $sesudah,
                    'jeda_sebelum' => $sebelum ? $menitMulai - $sebelum->menit_selesai : null,
                    'jeda_sesudah' => $sesudah ? $sesudah->menit_mulai - $menitSelesai : null,
                    'kosong_penuh' => $isi->isEmpty(),
                    'sisa_kursi'   => $r->kapasitas_efektif,
                ];
            })
            // Diurutkan menurut kode ruangan supaya mudah ditelusuri.
            // Kapasitas tetap terlihat di kolomnya sendiri.
            ->sortBy(fn ($baris) => $baris['ruangan']->urutan)
            ->values();
    }

    /**
     * Cek apakah satu rencana pemakaian bertabrakan dengan jadwal yang ada.
     * $abaikanJadwalId dipakai saat memindahkan kelas: jadwal aslinya tidak
     * boleh dihitung sebagai bentrok dengan dirinya sendiri.
     */
    public function periksaBentrok(
        int $ruanganId,
        int $hari,
        int $menitMulai,
        int $menitSelesai,
        ?int $abaikanJadwalId = null,
        bool $jadwalBerlaku = true,
        ?Carbon $tanggal = null
    ): Collection {
        if (! $jadwalBerlaku) {
            return collect();
        }

        // Pertemuan yang pada tanggal itu sudah dipindah ke hari lain juga
        // bukan bentrok — kelasnya tidak jadi berlangsung di sini.
        $dibatalkan = $tanggal ? PergantianService::jadwalDibatalkan($tanggal) : collect();

        return Jadwal::with(['mataKuliah', 'dosen', 'ruangan'])
            ->untukPeriode($this->periodeId)
            // Baris jadwal yang masa berlakunya sudah ditutup (karena pernah
            // dipindah permanen atau dihapus) bukan bentrok — kelasnya memang
            // tidak berjalan lagi pada tanggal itu.
            ->berlakuPada($tanggal)
            ->when($dibatalkan->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $dibatalkan))
            ->where('ruangan_id', $ruanganId)
            ->where('hari', $hari)
            ->where('menit_mulai', '<', $menitSelesai)
            ->where('menit_selesai', '>', $menitMulai)
            ->when($abaikanJadwalId, fn ($q) => $q->where('id', '!=', $abaikanJadwalId))
            ->orderBy('menit_mulai')
            ->get();
    }

    /**
     * Saat memindah kelas, dosen dan rombongan kelasnya juga tidak boleh
     * punya agenda lain di jam yang sama. Ini yang paling sering terlewat
     * kalau pengecekan dilakukan manual lewat Excel.
     */
    public function bentrokDosenDanKelas(
        ?int $dosenId,
        ?string $kelas,
        int $hari,
        int $menitMulai,
        int $menitSelesai,
        ?int $abaikanJadwalId = null,
        ?Carbon $tanggal = null
    ): array {
        $dasar = fn () => Jadwal::with(['mataKuliah', 'ruangan', 'dosen'])
            ->untukPeriode($this->periodeId)
            ->berlakuPada($tanggal)
            ->where('hari', $hari)
            ->where('menit_mulai', '<', $menitSelesai)
            ->where('menit_selesai', '>', $menitMulai)
            ->when($abaikanJadwalId, fn ($q) => $q->where('id', '!=', $abaikanJadwalId));

        return [
            'dosen' => $dosenId ? $dasar()->where('dosen_id', $dosenId)->get() : collect(),
            'kelas' => $kelas ? $dasar()->where('kelas', $kelas)->get() : collect(),
        ];
    }

    /**
     * Saran jam lain pada tanggal yang sama, ketika yang bentrok adalah
     * dosen atau rombongan kelas. Menawarkan ruangan lain tidak menolong
     * untuk kasus itu, karena orangnya yang tidak bisa hadir.
     *
     * @return array<int, array{mulai:string, selesai:string, ruangan_bebas:int}>
     */
    public function saranJam(
        ?int $dosenId,
        ?string $kelas,
        int $hari,
        int $durasiMenit,
        Carbon $tanggal,
        ?int $abaikanJadwalId = null,
        int $maksimal = 6
    ): array {
        $buka  = $this->keMenit(config('jadwal.jam_buka'));
        $tutup = $this->keMenit(config('jadwal.jam_tutup'));
        $saran = [];

        for ($mulai = $buka; $mulai + $durasiMenit <= $tutup; $mulai += 60) {
            $selesai = $mulai + $durasiMenit;

            $bentrok = $this->bentrokDosenDanKelas($dosenId, $kelas, $hari, $mulai, $selesai, $abaikanJadwalId, $tanggal);
            if ($bentrok['dosen']->isNotEmpty() || $bentrok['kelas']->isNotEmpty()) {
                continue;
            }

            $bebas = $this->ruangKosong($hari, $mulai, $selesai, null, 0, null, true, $tanggal);
            if ($bebas->isEmpty()) {
                continue;
            }

            $saran[] = [
                'mulai'         => $this->keJam($mulai),
                'selesai'       => $this->keJam($selesai),
                'ruangan_bebas' => $bebas->count(),
                'contoh'        => $bebas->take(3)->map(fn ($x) => $x['ruangan']->kode)->all(),
            ];

            if (count($saran) >= $maksimal) {
                break;
            }
        }

        return $saran;
    }

    protected function keMenit(string $jam): int
    {
        [$h, $m] = array_map('intval', explode(':', $jam));

        return $h * 60 + $m;
    }

    protected function keJam(int $menit): string
    {
        return sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60);
    }

    /**
     * Saran jam pengganti pada satu tanggal: jam berapa saja dosen dan
     * rombongan kelas sama-sama luang, dan masih ada ruangan yang bebas.
     *
     * Dipakai saat pengajuan pergantian ditolak karena dosen atau kelasnya
     * bentrok — menawarkan ruangan lain tidak menolong, karena orangnya
     * memang tidak bisa hadir.
     */
    public function saranWaktu(
        Carbon $tanggal,
        int $durasiMenit,
        ?int $dosenId,
        ?string $kelas,
        ?int $abaikanJadwalId = null,
        int $maksimal = 6
    ): Collection {
        $hari  = $tanggal->dayOfWeekIso - 1;
        $buka  = $this->keMenit(config('jadwal.jam_buka'));
        $tutup = $this->keMenit(config('jadwal.jam_tutup'));

        $saran = collect();

        for ($mulai = $buka; $mulai + $durasiMenit <= $tutup; $mulai += 30) {
            $selesai = $mulai + $durasiMenit;

            $halangan = $this->bentrokDosenDanKelas($dosenId, $kelas, $hari, $mulai, $selesai, $abaikanJadwalId, $tanggal);
            if ($halangan['dosen']->isNotEmpty() || $halangan['kelas']->isNotEmpty()) {
                continue;
            }

            $ruangBebas = $this->ruangKosong($hari, $mulai, $selesai, null, 0, null, true, $tanggal);
            if ($ruangBebas->isEmpty()) {
                continue;
            }

            $saran->push([
                'mulai'        => $this->keJam($mulai),
                'selesai'      => $this->keJam($selesai),
                'jumlah_ruang' => $ruangBebas->count(),
                'contoh_ruang' => $ruangBebas->take(3)->map(fn ($x) => $x['ruangan']->kode)->all(),
            ]);

            if ($saran->count() >= $maksimal) {
                break;
            }
        }

        return $saran;
    }

    /** Bentuk ringkas satu jadwal untuk disimpan sebagai catatan bentrok. */
    public static function ringkasJadwal(Jadwal $j): array
    {
        return [
            'mata_kuliah' => $j->mataKuliah->nama ?? '-',
            'kelas'       => $j->kelas,
            'dosen'       => $j->dosen->kode ?? '-',
            'ruangan'     => $j->ruangan->kode ?? '-',
            'jam'         => $j->rentang_jam,
        ];
    }

    /** Ruangan yang sudah dipesan lewat reservasi disetujui pada tanggal itu. */
    public function ruanganDireservasi(Carbon $tanggal, int $menitMulai, int $menitSelesai): Collection
    {
        // Pengajuan yang masih menunggu pun ikut mengunci ruangan.
        // Siapa yang mengajukan lebih dulu, dialah yang memegang slotnya,
        // supaya dua orang tidak sama-sama merasa mendapat ruang yang sama.
        //
        // Hanya reservasi ruangan. Induk pengajuan pergantian menyimpan
        // salinan baris pertamanya, dan salinan itu tidak ikut diperbarui
        // ketika pertemuannya dipindah lagi — memakainya di sini membuat
        // ruangan terkunci di tanggal yang kelasnya sudah pergi.
        // Pemakaian kuliah pengganti dihitung lewat PergantianService.
        return Pengajuan::jenis('ruangan')
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('status', ['disetujui', 'menunggu'])
            ->whereNotNull('ruangan_id')
            ->where('menit_mulai', '<', $menitSelesai)
            ->where('menit_selesai', '>', $menitMulai)
            ->pluck('ruangan_id')
            ->unique();
    }

    /** Ruangan yang sedang ditutup untuk perbaikan pada tanggal itu. */
    public function ruanganDitutup(Carbon $tanggal): Collection
    {
        return PenutupanRuangan::padaTanggal($tanggal)->pluck('ruangan_id')->unique();
    }

    /** Reservasi disetujui yang menghalangi satu ruangan pada tanggal itu. */
    public function reservasiBentrok(int $ruanganId, Carbon $tanggal, int $menitMulai, int $menitSelesai): Collection
    {
        return Pengajuan::jenis('ruangan')
            ->where('ruangan_id', $ruanganId)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('status', ['disetujui', 'menunggu'])
            ->where('menit_mulai', '<', $menitSelesai)
            ->where('menit_selesai', '>', $menitMulai)
            ->orderBy('menit_mulai')
            ->get();
    }

    /**
     * Ruangan yang boleh muncul sebagai kandidat: yang dipakai jadwal pada
     * periode ini, ditambah ruangan khusus yang ditambahkan petugas —
     * ruang multimedia dan sejenisnya tidak pernah ada di berkas jadwal,
     * tetapi tetap bisa dipinjam.
     */
    /**
     * Seluruh ruangan yang BOLEH ditawarkan sebagai ruang kosong — sebelum
     * disaring oleh jam. Dipakai sebagai penyebut "x dari y ruang kosong",
     * supaya angkanya berasal dari himpunan yang sama dengan daftarnya.
     *
     * Tanpa ini, pembilang memakai aturan dikelola()+aktif+terdaftar
     * sementara penyebutnya memakai seluruh ruangan, dan daftarnya bisa
     * lebih pendek daripada angka yang tertulis di atasnya.
     */
    public function ruanganKandidat(?int $gedungId = null): Collection
    {
        return Ruangan::with('gedung')
            ->dikelola()
            ->when($gedungId, fn ($q) => $q->where('gedung_id', $gedungId))
            ->where('aktif', true)
            ->whereIn('id', $this->ruanganTerdaftar())
            ->orderBy('urutan')
            ->get();
    }

    protected function ruanganTerdaftar(): Collection
    {
        return Jadwal::untukPeriode($this->periodeId)->distinct()->pluck('ruangan_id')
            ->merge(Ruangan::manual()->pluck('id'))
            ->unique()
            ->values();
    }
}
