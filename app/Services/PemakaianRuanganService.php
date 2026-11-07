<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Pengajuan;
use App\Models\PenutupanRuangan;
use App\Models\Periode;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menggabungkan dua sumber pemakaian ruangan menjadi satu daftar:
 *   1. jadwal perkuliahan (berulang tiap pekan, hanya berlaku dalam masa kuliah);
 *   2. pengajuan reservasi yang sudah disetujui (terikat tanggal tertentu).
 *
 * Dipakai Timeline, Papan lift, dan pencarian ruang kosong supaya ketiganya
 * membaca kenyataan yang sama.
 */
class PemakaianRuanganService
{
    /**
     * Baris rincian yang boleh dilihat tanpa login, per jenis kegiatan.
     *
     * Sengaja daftar izin, bukan daftar larangan: baris baru di padaTanggal()
     * tetap tersembunyi dari publik sampai ditambahkan di sini. Identitas dan
     * kontak pemohon, NIP, nomor pengajuan, keperluan reservasi, catatan
     * petugas, dan alasan pergantian hanya untuk petugas.
     */
    protected const RINCIAN_PUBLIK = [
        'kuliah' => [
            'Kode mata kuliah', 'Kelas', 'Program studi', 'Dosen', 'Kode dosen', 'Ruangan',
            'Kapasitas ruangan', 'Hari', 'Jam', 'Durasi', 'Jumlah mahasiswa',
            'Berlaku mulai', 'Berlaku sampai',
        ],
        'rsv' => ['Ruangan', 'Tanggal', 'Jam', 'Jumlah peserta', 'Status'],
        'ganti' => [
            'Kode mata kuliah', 'Kelas', 'Program studi', 'Dosen', 'Kode dosen', 'Ruangan',
            'Pertemuan asal', 'Tanggal pengganti', 'Jam', 'Jumlah mahasiswa', 'Status',
        ],
        'tutup' => ['Ruangan', 'Alasan', 'Rentang'],
    ];

    public function __construct(protected Periode $periode)
    {
    }

    /**
     * Membuang data pribadi dari hasil padaTanggal() sebelum dikirim ke halaman
     * yang bisa dibuka tanpa login. Isi `data-rinci` terbaca lewat sumber
     * halaman, jadi menyembunyikannya di layar saja tidak cukup.
     */
    public function untukPublik(Collection $kegiatan): Collection
    {
        return $kegiatan->map(function (array $x) {
            // `judul` pada reservasi berisi keperluan, `ket` berisi nama pemohon
            // dan nomor pengajuan.
            if ($x['jenis'] === 'rsv') {
                $x['judul'] = __('publik.event.reserved');
                $x['ket'] = '';
            }

            $jenis = $x['jenis'] === 'lewat' ? 'kuliah' : $x['jenis'];
            $boleh = self::RINCIAN_PUBLIK[$jenis] ?? [];

            if (isset($x['detail'])) {
                $x['detail']['baris'] = array_values(array_filter(
                    $x['detail']['baris'] ?? [],
                    fn (array $b) => in_array($b['label'], $boleh, true)
                ));
            }

            return $x;
        })->values();
    }

    /**
     * Seluruh pemakaian ruangan pada satu tanggal.
     *
     * Tiap baris berisi: ruangan_id, mulai, selesai (menit), jenis, judul,
     * keterangan, dan status. Jenis menentukan warnanya di layar:
     *   'kuliah'  biru   — perkuliahan reguler
     *   'lewat'   jingga — perkuliahan di luar jam operasional
     *   'rsv'     hijau  — reservasi ruangan
     *   'ganti'   merah muda — pergantian jadwal kuliah
     */
    public function padaTanggal(Carbon $tanggal, ?int $gedungId = null, bool $termasukMenunggu = true): Collection
    {
        $hari = $tanggal->dayOfWeekIso - 1;
        $out  = collect();

        // --- 1. Perkuliahan reguler ---
        // Pertemuan yang dipindah ke tanggal lain tidak ditampilkan di sini;
        // yang muncul pada tanggal penggantinya ada di bagian 2.
        $dibatalkan = PergantianService::jadwalDibatalkan($tanggal);

        if ($hari >= 0 && $hari <= 5 && $this->periode->mencakup($tanggal)) {
            Jadwal::with(['mataKuliah', 'dosen', 'ruangan.gedung'])
                ->untukPeriode($this->periode->id)
                ->berlakuPada($tanggal)
                ->untukGedung($gedungId)
                ->untukHari($hari)
                ->when($dibatalkan->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $dibatalkan))
                ->orderBy('menit_mulai')
                ->get()
                ->each(fn (Jadwal $j) => $out->push([
                    'ruangan_id' => $j->ruangan_id,
                    'ruangan'    => $j->ruangan->kode,
                    'urutan'     => $j->ruangan->urutan,
                    'mulai'      => $j->menit_mulai,
                    'selesai'    => $j->menit_selesai,
                    'jenis'      => $j->overtime ? 'lewat' : 'kuliah',
                    'judul'      => $j->mataKuliah->nama ?? '-',
                    'ket'        => trim($j->kelas . ' · ' . ($j->dosen->kode ?? '')),
                    'jml'        => $j->jml_mhs,
                    'status'     => 'kuliah',
                    'detail'     => [
                        'tag'   => $j->overtime
                            ? 'Perkuliahan di luar waktu normal'
                            : 'Perkuliahan',
                        'baris' => $this->pasangan([
                            ['Kode mata kuliah',  $j->mataKuliah->kode ?? null],
                            ['Kelas',             $j->kelas],
                            ['Program studi',     $j->prodi],
                            ['Dosen',             $j->dosen->nama ?? null],
                            ['Kode dosen',        $j->dosen->kode ?? null],
                            ['NIP',               $j->dosen->nip ?? null],
                            ['Ruangan',           $j->ruangan->kode
                                . ($j->ruangan->gedung ? ' · ' . $j->ruangan->gedung->nama : '')],
                            ['Kapasitas ruangan', $j->ruangan->kapasitas_efektif
                                ? $j->ruangan->kapasitas_efektif . ' kursi' : null],
                            ['Hari',              Jadwal::HARI[$j->hari] ?? null],
                            ['Jam',               $this->jamTeks($j->menit_mulai)
                                . ' – ' . $this->jamTeks($j->menit_selesai)],
                            ['Durasi',            $this->durasiTeks($j->durasi_menit)],
                            ['Jumlah mahasiswa',  $j->jml_mhs ? $j->jml_mhs . ' orang' : null],
                            ['Berlaku mulai',     $j->berlaku_mulai?->translatedFormat('j F Y')],
                            ['Berlaku sampai',    $j->berlaku_sampai?->translatedFormat('j F Y')],
                        ]),
                    ],
                ]));
        }

        // --- 2. Reservasi ---
        // Pengajuan yang menunggu keputusan tetap ditampilkan dan tetap
        // menutup ruangan, dengan gaya berbeda agar bisa dibedakan.
        $status = $termasukMenunggu ? ['disetujui', 'menunggu'] : ['disetujui'];

        Pengajuan::with('ruangan')
            ->jenis('ruangan')
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('status', $status)
            ->whereNotNull('ruangan_id')
            ->when($gedungId, fn ($q) => $q->whereHas('ruangan', fn ($r) => $r->where('gedung_id', $gedungId)))
            ->orderBy('menit_mulai')
            ->get()
            ->each(fn (Pengajuan $p) => $out->push([
                'ruangan_id' => $p->ruangan_id,
                'ruangan'    => $p->ruangan->kode,
                'urutan'     => $p->ruangan->urutan,
                'mulai'      => $p->menit_mulai,
                'selesai'    => $p->menit_selesai,
                'jenis'      => 'rsv',
                'judul'      => $p->keperluan ?: $p->label_jenis,
                'ket'        => $p->nama . ' · ' . $p->nomor,
                'jml'        => $p->jumlah_peserta,
                'status'     => $p->status,
                'detail'     => [
                    'tag'   => $p->label_jenis,
                    'baris' => $this->pasangan([
                        ['Nomor pengajuan',   $p->nomor],
                        ['Keperluan',         $p->keperluan],
                        ['Pemohon',           $p->nama],
                        ['Status pemohon',    $p->peran],
                        ['NIM / NIP',         $p->identitas],
                        ['Unit / organisasi', $p->unit],
                        ['Kontak',            $p->kontak],
                        ['Ruangan',           $p->ruangan->kode ?? null],
                        ['Tanggal',           $p->tanggal?->translatedFormat('l, j F Y')],
                        ['Jam',               $this->jamTeks($p->menit_mulai)
                            . ' – ' . $this->jamTeks($p->menit_selesai)],
                        ['Jumlah peserta',    $p->jumlah_peserta ? $p->jumlah_peserta . ' orang' : null],
                        ['Status',            ucfirst($p->status)],
                        ['Catatan petugas',   $p->catatan_petugas],
                    ]),
                ],
            ]));

        // --- 2b. Kuliah pengganti ---
        // Diambil dari tabel baris, bukan dari induk pengajuan. Induk hanya
        // menyimpan salinan baris pertama, sehingga pengajuan dengan beberapa
        // pergantian sekaligus dulu cuma tampil satu di papan.
        PergantianService::penggantiPada($tanggal, $gedungId)
            ->filter(fn ($b) => in_array($b->status, $status, true))
            ->each(fn ($b) => $out->push([
                'ruangan_id' => $b->ruangan_id,
                'ruangan'    => $b->ruangan->kode,
                'urutan'     => $b->ruangan->urutan,
                'mulai'      => $b->menit_mulai,
                'selesai'    => $b->menit_selesai,
                'jenis'      => 'ganti',
                'judul'      => $b->jadwal->mataKuliah->nama ?? 'Kuliah pengganti',
                'ket'        => trim(($b->jadwal->kelas ?? '') . ' · '
                                . ($b->jadwal->dosen->kode ?? '')) . ' · pengganti '
                                . $b->tanggal_asal->translatedFormat('j M'),
                'jml'        => $b->jadwal->jml_mhs ?? 0,
                'status'     => $b->status,
                'detail'     => [
                    'tag'   => 'Kuliah pengganti',
                    'baris' => $this->pasangan([
                        ['Kode mata kuliah',  $b->jadwal->mataKuliah->kode ?? null],
                        ['Kelas',             $b->jadwal->kelas ?? null],
                        ['Program studi',     $b->jadwal->prodi ?? null],
                        ['Dosen',             $b->jadwal->dosen->nama ?? null],
                        ['Kode dosen',        $b->jadwal->dosen->kode ?? null],
                        ['NIP',               $b->jadwal->dosen->nip ?? null],
                        ['Ruangan',           $b->ruangan->kode ?? null],
                        ['Pertemuan asal',    $b->tanggal_asal?->translatedFormat('l, j F Y')],
                        ['Tanggal pengganti', $b->tanggal?->translatedFormat('l, j F Y')],
                        ['Jam',               $this->jamTeks($b->menit_mulai)
                            . ' – ' . $this->jamTeks($b->menit_selesai)],
                        ['Jumlah mahasiswa',  ($b->jadwal->jml_mhs ?? 0) ? $b->jadwal->jml_mhs . ' orang' : null],
                        ['Nomor pengajuan',   $b->pengajuan->nomor ?? null],
                        ['Diajukan oleh',     $b->pengajuan->nama ?? null],
                        ['Kontak',            $b->pengajuan->kontak ?? null],
                        ['Alasan',            $b->pengajuan->keperluan ?? null],
                        ['Status',            ucfirst($b->status)],
                        ['Catatan petugas',   $b->catatan_petugas],
                    ]),
                ],
            ]));

        // --- 3. Ruangan yang ditutup untuk perbaikan ---
        // Ditampilkan memenuhi jam operasional agar terlihat jelas bahwa
        // ruangan itu tidak bisa dipakai seharian.
        $buka  = $this->keMenit(config('jadwal.jam_buka'));
        $tutup = $this->keMenit(config('jadwal.jam_tutup'));

        PenutupanRuangan::with('ruangan')
            ->padaTanggal($tanggal)
            ->when($gedungId, fn ($q) => $q->whereHas('ruangan', fn ($r) => $r->where('gedung_id', $gedungId)))
            ->get()
            ->each(fn (PenutupanRuangan $t) => $out->push([
                'ruangan_id' => $t->ruangan_id,
                'ruangan'    => $t->ruangan->kode,
                'urutan'     => $t->ruangan->urutan,
                'mulai'      => $buka,
                'selesai'    => $tutup,
                'jenis'      => 'tutup',
                'judul'      => 'Ditutup: ' . $t->alasan,
                'ket'        => $t->rentang_teks,
                'jml'        => null,
                'status'     => 'tutup',
                'detail'     => [
                    'tag'   => 'Ruangan ditutup',
                    'baris' => $this->pasangan([
                        ['Ruangan', $t->ruangan->kode ?? null],
                        ['Alasan',  $t->alasan],
                        ['Rentang', $t->rentang_teks],
                    ]),
                ],
            ]));

        return $out->sortBy('mulai')->values();
    }

    protected function keMenit(string $jam): int
    {
        [$h, $m] = array_map('intval', explode(':', $jam));

        return $h * 60 + $m;
    }

    protected function jamTeks(int $menit): string
    {
        return sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60);
    }

    protected function durasiTeks(?int $menit): ?string
    {
        if (! $menit) {
            return null;
        }

        $jam = intdiv($menit, 60);
        $sisa = $menit % 60;

        return trim(($jam ? $jam . ' jam ' : '') . ($sisa ? $sisa . ' menit' : ''));
    }

    /**
     * Membuang pasangan yang nilainya kosong, supaya rincian di layar tidak
     * penuh baris bertuliskan "-". Data sistem akademik sering tidak memuat NIP,
     * nama dosen, atau program studi, dan itu wajar.
     *
     * @param  array<int, array{0: string, 1: mixed}>  $pasangan
     * @return array<int, array{label: string, nilai: string}>
     */
    protected function pasangan(array $pasangan): array
    {
        $out = [];

        foreach ($pasangan as [$label, $nilai]) {
            if ($nilai === null || $nilai === '' || $nilai === '·') {
                continue;
            }

            $bersih = trim((string) $nilai, " \t·");

            if ($bersih !== '') {
                $out[] = ['label' => $label, 'nilai' => $bersih];
            }
        }

        return $out;
    }

    /** Ruangan yang terpakai pada rentang jam tertentu di tanggal itu. */
    public function ruanganTerpakai(Carbon $tanggal, int $mulai, int $selesai, ?int $gedungId = null): Collection
    {
        return $this->padaTanggal($tanggal, $gedungId)
            ->filter(fn ($x) => $x['mulai'] < $selesai && $x['selesai'] > $mulai)
            ->pluck('ruangan_id')
            ->unique()
            ->values();
    }
}
