<?php

namespace Tests\Feature;

use App\Models\AsetUnit;
use App\Models\Dosen;
use App\Models\Gedung;
use App\Models\Jadwal;
use App\Models\KategoriAset;
use App\Models\MataKuliah;
use App\Models\PeminjamanAset;
use App\Models\Pengajuan;
use App\Models\Periode;
use App\Models\Ruangan;
use App\Support\TerjemahPesan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Redesign hanya boleh mengubah tampilan: nama field, nilai yang disimpan, dan
 * hook JavaScript (kontrak tampilan) harus tetap ada, dan alur kirim
 * harus tetap bekerja dalam kedua bahasa.
 */
class TampilanPublikTest extends TestCase
{
    use RefreshDatabase;

    private Ruangan $ruangan;

    private Jadwal $jadwal;

    private AsetUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 09:00:00');

        $periode = Periode::create([
            'kode' => '2627-1', 'tahun_ajaran' => '2026/2027', 'semester' => 1,
            'nama' => 'Semester Uji', 'aktif' => true,
        ]);
        $gedung = Gedung::create(['nama' => 'Gedung Uji', 'kode_pendek' => 'A']);
        $this->ruangan = Ruangan::create(['gedung_id' => $gedung->id, 'kode' => 'A.03.11', 'lantai' => 3, 'aktif' => true]);

        $this->jadwal = Jadwal::create([
            'periode_id' => $periode->id, 'gedung_id' => $gedung->id, 'ruangan_id' => $this->ruangan->id,
            'dosen_id' => Dosen::create(['kode' => 'ABC', 'nip' => '199001012020', 'nama' => 'Dosen Uji'])->id,
            'mata_kuliah_id' => MataKuliah::create(['kode' => 'MK01', 'nama' => 'Kalkulus Uji'])->id,
            'hari' => 2, 'menit_mulai' => 660, 'menit_selesai' => 780,
            'jam_mulai' => '11:00:00', 'jam_selesai' => '13:00:00', 'durasi_menit' => 120,
            'kelas' => 'IT-50-01', 'prodi' => 'Informatika', 'jml_mhs' => 30,
        ]);

        $this->unit = AsetUnit::create([
            'kategori_aset_id' => KategoriAset::create(['kode' => 'PRJ', 'nama' => 'Proyektor'])->id,
            'kode' => 'AST-001', 'nama' => 'Proyektor Uji',
            'kondisi' => 'baik', 'status' => 'aktif', 'boleh_pinjam' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function assertMemuat(string $isi, array $daftar): void
    {
        foreach ($daftar as $teks) {
            $this->assertStringContainsString($teks, $isi);
        }
    }

    /* ----------------------- Keputusan M53 dan ketentuan ----------------------- */

    public function test_nama_mata_kuliah_tampil_persis_seperti_data(): void
    {
        $this->jadwal->mataKuliah->update(['nama' => 'xAPI Lab LEVEL 4 for AI']);

        $halaman = [
            '/papan', '/dashboard/jadwal', '/dashboard/timeline?tanggal=2026-11-04',
            '/cari?cari=1&tanggal=2026-11-04&mulai=11:00&selesai=13:00&ruangan=A.03.11',
            '/cari?cari=1&tanggal=2026-11-04&mulai=14:00&selesai=15:00',
        ];

        foreach (['en', 'id'] as $bahasa) {
            foreach ($halaman as $url) {
                $this->get($url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . $bahasa)
                    ->assertOk()
                    ->assertSee('xAPI Lab LEVEL 4 for AI')
                    ->assertDontSee('Xapi Lab');
            }
        }

        $this->getJson('/jadwal/cari?q=MK01')->assertOk()->assertJsonPath('0.nama', 'xAPI Lab LEVEL 4 for AI');
    }

    public function test_nama_mata_kuliah_tidak_diterjemahkan(): void
    {
        $this->jadwal->mataKuliah->update(['nama' => 'Perkuliahan']);

        $isi = $this->get('/dashboard/timeline?tanggal=2026-11-04&lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('<b>Perkuliahan</b>', $isi);
    }

    public function test_ketentuan_selalu_bahasa_inggris(): void
    {
        $tamuId = $this->get('/cari?lang=id');
        $tamuEn = $this->get('/cari?lang=en');

        foreach ([$tamuId, $tamuEn] as $respons) {
            $respons->assertOk()
                ->assertSee('Room Reservation Terms')
                ->assertSee('Class Reschedule Terms')
                ->assertSee('Submit your request at least 1 working day before the date of use.')
                ->assertSee('By submitting a request, you agree to the terms above.')
                ->assertDontSee('Ketentuan Peminjaman Ruangan')
                ->assertDontSee('Pengajuan disampaikan paling lambat');
        }
    }

    /* ------------------------------ Bahasa ------------------------------ */

    public function test_bawaan_english_dan_lang_tersimpan_di_cookie(): void
    {
        $this->get('/')->assertOk()->assertSee('Welcome')->assertSee('<html lang="en">', false);

        $this->get('/?lang=id')
            ->assertOk()
            ->assertSee('Selamat Datang')
            ->assertSee('<html lang="id">', false)
            ->assertCookie('bahasa', 'id');
    }

    public function test_pilihan_bahasa_tidak_dikenal_diabaikan(): void
    {
        $this->get('/?lang=fr')->assertOk()->assertSee('Welcome')->assertCookieMissing('bahasa');
    }

    public function test_pemilih_bahasa_mempertahankan_query_string(): void
    {
        $isi = $this->get('/cari?cari=1&tanggal=2026-11-04')->getContent();

        $this->assertMemuat(html_entity_decode($isi), ['cari=1', 'tanggal=2026-11-04', 'lang=id']);
    }

    /* ------------------------------ Kontrak ----------------------------- */

    public function test_hook_dan_field_cari_utuh(): void
    {
        $isi = $this->get('/cari?cari=1&tanggal=2026-11-04')->assertOk()->getContent();

        $this->assertMemuat($isi, [
            'name="periode"', 'name="cari"', 'name="tanggal"', 'name="mulai"', 'name="selesai"', 'name="kapasitas"',
            'name="gedung"', 'name="ruangan"', 'data-aturan="ruangan"', 'data-aturan="pergantian"',
            'id="aturanRuangan"', 'id="aturanPergantian"', 'class="setuju-aturan"', 'lanjut-aturan', 'id="modalRuangan"',
            'id="modalPergantian"', 'data-tutup', 'data-isi="tanggal"', 'id="peranReservasi"', 'id="unitReservasi"',
            'id="ruanganReservasi"', 'id="mulaiReservasi"', 'id="selesaiReservasi"', 'id="daftarBaris"', 'id="cetakanBaris"',
            'id="tambahBaris"', 'baris-ganti', 'baris-nomor', 'hapus-baris', 'cari-mk', 'hasil-cari', 'isi-jadwal',
            'kartu-jadwal', 'isi-asal', 'petunjuk-hari', 'isi-mulai', 'isi-selesai', 'isi-ruangan', 'isi-tanggal',
            'catatan-durasi', 'baris[__I__][jadwal_id]', 'baris[__I__][tanggal_asal]', 'baris[__I__][ruangan_id]',
            'baris[__I__][tanggal]', 'baris[__I__][mulai]', 'baris[__I__][selesai]', 'name="jumlah_peserta"',
            'name="keperluan"', 'name="kontak"', 'name="identitas"', 'name="peran"', 'name="unit"',
        ]);

        // Form pencarian harus lebih dulu daripada pop-up (salinDariPencarian).
        $this->assertLessThan(strpos($isi, 'id="modalRuangan"'), strpos($isi, 'id="cTanggal"'));
    }

    public function test_nilai_peran_tetap_indonesia_walau_label_inggris(): void
    {
        $this->get('/cari')->assertSee('value="Mahasiswa International"', false)->assertSee('International student');
        $this->get('/pinjam-aset')->assertSee('value="Tenaga Kependidikan"', false)->assertSee('Administrative staff');
    }

    public function test_hook_dan_field_pinjam_utuh(): void
    {
        $isi = $this->get('/pinjam-aset')->assertOk()->getContent();

        $this->assertMemuat($isi, [
            'id="formPinjam"', 'id="isianBaris"', 'id="tombolKirim"', 'id="ringkasPilihan"', 'data-barang=', 'data-unit=',
            'jumlah-barang', 'kurang-barang', 'tambah-barang', 'tgl-kembali',
            "'aset_unit_id'", "'tanggal_pinjam'", "'tanggal_kembali'",
            'name="unit_organisasi"', 'name="ruangan_id"', 'name="q"', 'name="kategori"',
        ]);
    }

    public function test_pinjam_meringkas_unit_sejenis_menjadi_satu_barang(): void
    {
        foreach (['AST-002', 'AST-003'] as $kode) {
            AsetUnit::create([
                'kategori_aset_id' => $this->unit->kategori_aset_id, 'kode' => $kode, 'nama' => 'Proyektor Uji',
                'kondisi' => 'baik', 'status' => 'aktif', 'boleh_pinjam' => true,
            ]);
        }
        AsetUnit::create([
            'kategori_aset_id' => $this->unit->kategori_aset_id, 'kode' => 'AST-004', 'nama' => 'Proyektor Uji', 'merek' => 'Merek Lain',
            'kondisi' => 'baik', 'status' => 'aktif', 'boleh_pinjam' => true,
        ]);

        $isi = $this->get('/pinjam-aset')->assertOk()->assertSee('3 available')->assertSee('1 available')->getContent();

        $this->assertSame(2, substr_count($isi, 'class="jumlah-barang"'));
        $this->assertStringNotContainsString('AST-002', $isi);
    }

    public function test_pinjam_mendahulukan_unit_yang_belum_diminta_orang_lain(): void
    {
        $unitLain = AsetUnit::create([
            'kategori_aset_id' => $this->unit->kategori_aset_id, 'kode' => 'AST-002', 'nama' => 'Proyektor Uji',
            'kondisi' => 'baik', 'status' => 'aktif', 'boleh_pinjam' => true,
        ]);
        $pinjam = PeminjamanAset::create([
            'nomor' => 'AST-000778', 'nama' => 'Alex Example', 'peran' => 'Mahasiswa',
            'kontak' => '+6281200000000', 'keperluan' => 'Uji',
        ]);
        $pinjam->baris()->create([
            'aset_unit_id' => $this->unit->id, 'tanggal_pinjam' => '2026-11-04',
            'tanggal_kembali' => '2026-11-06', 'status' => 'menunggu',
        ]);

        // Rentang beririsan: unit yang masih menunggu keputusan dipilih paling akhir.
        $this->get('/pinjam-aset?mulai=2026-11-05&selesai=2026-11-05')->assertOk()
            ->assertSee('2 available')
            ->assertSee('data-unit="[' . $unitLain->id . ',' . $this->unit->id . ']"', false);

        // Rentang tidak beririsan: urutan biasa.
        $this->get('/pinjam-aset?mulai=2026-11-10&selesai=2026-11-11')->assertOk()
            ->assertSee('data-unit="[' . $this->unit->id . ',' . $unitLain->id . ']"', false);
    }

    public function test_pinjam_tidak_menawarkan_unit_yang_sedang_dipinjam(): void
    {
        $pinjam = PeminjamanAset::create([
            'nomor' => 'AST-000777', 'nama' => 'Alex Example', 'peran' => 'Mahasiswa',
            'kontak' => '+6281200000000', 'keperluan' => 'Uji',
        ]);
        $pinjam->baris()->create([
            'aset_unit_id' => $this->unit->id, 'tanggal_pinjam' => '2026-11-04',
            'tanggal_kembali' => '2026-11-06', 'status' => 'dipinjam',
        ]);
        $unitLain = AsetUnit::create([
            'kategori_aset_id' => $this->unit->kategori_aset_id, 'kode' => 'AST-002', 'nama' => 'Proyektor Uji',
            'kondisi' => 'baik', 'status' => 'aktif', 'boleh_pinjam' => true,
        ]);

        $this->get('/pinjam-aset?mulai=2026-11-04&selesai=2026-11-05')
            ->assertOk()
            ->assertSee('1 available')
            ->assertSee('data-unit="[' . $unitLain->id . ']"', false);
    }

    public function test_hook_papan_utuh(): void
    {
        $isi = $this->get('/papan')->assertOk()->getContent();

        $this->assertMemuat($isi, ['id="pTotal"', 'id="pPakai"', 'id="pKosong"', 'id="pJam"', 'id="pUpd"', 'id="rinci"', 'id="rinciJudul"', 'id="rinciTag"', 'id="rinciIsi"', 'data-rinci-tutup', 'window.rinciTerbuka']);
    }

    public function test_papan_menampilkan_ruangan_yang_kosong(): void
    {
        Ruangan::create(['gedung_id' => $this->ruangan->gedung_id, 'kode' => 'A.09.99', 'lantai' => 9, 'aktif' => true]);

        $this->get('/papan')->assertOk()->assertSee('A.03.11');
    }

    public function test_tab_dashboard_tamu_terbuka_dalam_dua_bahasa(): void
    {
        foreach (['ruangan', 'jadwal', 'timeline'] as $tab) {
            $this->get("/dashboard/{$tab}")->assertOk()->assertSee('Building Dashboard');
            $this->get("/dashboard/{$tab}?lang=id")->assertOk()->assertSee('Dashboard Gedung');
        }
    }

    public function test_status_timeline_dihitung_di_server(): void
    {
        // 09:00, kelas mulai 11:00: masih Available dan belum Soon busy.
        $this->get('/dashboard/timeline?tanggal=2026-11-04')->assertOk()->assertSee('pub-badge--available', false);

        Carbon::setTestNow('2026-11-04 10:45:00');
        $this->get('/dashboard/timeline?tanggal=2026-11-04')->assertOk()->assertSee('pub-badge--soon', false);

        Carbon::setTestNow('2026-11-04 11:30:00');
        $this->get('/dashboard/timeline?tanggal=2026-11-04')->assertOk()->assertSee('pub-badge--occupied', false);
    }

    /* ------------------------------ Alur kirim ------------------------------ */

    private function dataReservasi(array $ganti = []): array
    {
        return array_merge([
            'nama' => 'Alex Example', 'peran' => 'Mahasiswa International', 'kontak' => '+6281211111111',
            'tanggal' => '2026-11-04', 'mulai' => '08:00', 'selesai' => '10:00',
            'ruangan_id' => $this->ruangan->id, 'jumlah_peserta' => 5, 'keperluan' => 'Rapat Uji',
        ], $ganti);
    }

    public function test_reservasi_berhasil_menampilkan_nomor_dan_tombol_salin(): void
    {
        $this->from('/cari')->post('/pengajuan/ruangan', $this->dataReservasi());

        $nomor = Pengajuan::first()->nomor;

        $this->get('/cari')->assertOk()
            ->assertSee("Request {$nomor} has been sent")
            ->assertSee('data-salin="' . $nomor . '"', false);
    }

    public function test_reservasi_gagal_validasi_membuka_modal_dan_mempertahankan_isian(): void
    {
        $respons = $this->from('/cari')->followingRedirects()->post('/pengajuan/ruangan', $this->dataReservasi(['nama' => '']));

        $respons->assertOk()
            ->assertSee('data-ringkasan-galat', false)
            ->assertSee('applicant name')
            ->assertSee('value="+6281211111111"', false);

        $this->assertStringNotContainsString('id="modalRuangan" hidden', preg_replace('/\s+/', ' ', $respons->getContent()));
    }

    public function test_reservasi_bentrok_diterjemahkan_dan_memberi_saran(): void
    {
        $this->from('/cari')->post('/pengajuan/ruangan', $this->dataReservasi());
        $nomor = Pengajuan::first()->nomor;
        $this->flushSession();

        $this->from('/cari')->followingRedirects()->post('/pengajuan/ruangan', $this->dataReservasi(['nama' => 'Pemohon Lain']))
            ->assertOk()
            ->assertSee('the room is already reserved at')
            ->assertDontSee($nomor)
            ->assertDontSee('Alex Example');
    }

    public function test_pergantian_ditolak_memulihkan_baris_dan_galatnya(): void
    {
        $respons = $this->from('/cari')->followingRedirects()->post('/pengajuan/pergantian', [
            'nama' => 'Dosen Pemohon', 'peran' => 'Dosen', 'kontak' => '+6281211111111', 'keperluan' => 'Tugas luar kota',
            'baris' => [[
                'jadwal_id' => $this->jadwal->id, 'tanggal_asal' => '2026-11-02', 'ruangan_id' => $this->ruangan->id,
                'tanggal' => '2026-11-05', 'mulai' => '10:00', 'selesai' => '09:00',
            ]],
        ]);

        $respons->assertOk();
        $isi = html_entity_decode($respons->getContent());

        $this->assertMemuat($isi, ['BARIS_LAMA', 'Kalkulus Uji', '2026-11-05', 'the end time must be after the start time', 'Change 1:']);
    }

    public function test_pinjam_berhasil_dan_pesan_tampil_sekali(): void
    {
        $respons = $this->from('/pinjam-aset')->followingRedirects()->post('/pinjam-aset', [
            'nama' => 'Alex Example', 'peran' => 'Mahasiswa', 'kontak' => '+6281211111111', 'keperluan' => 'Uji',
            'baris' => [['aset_unit_id' => $this->unit->id, 'tanggal_pinjam' => '2026-11-04', 'tanggal_kembali' => '2026-11-05']],
        ]);

        $nomor = PeminjamanAset::first()->nomor;
        $respons->assertOk()->assertSee("Request {$nomor} has been sent with 1 items");
        $this->assertSame(1, substr_count($respons->getContent(), "Request {$nomor} has been sent"));
    }

    public function test_pinjam_gagal_mempertahankan_pilihan_barang(): void
    {
        $respons = $this->from('/pinjam-aset?mulai=2026-11-04&selesai=2026-11-06')->followingRedirects()->post('/pinjam-aset', [
            'nama' => 'Alex Example', 'peran' => 'Mahasiswa', 'kontak' => '+6281211111111', 'keperluan' => 'Uji',
            'baris' => [['aset_unit_id' => $this->unit->id, 'tanggal_pinjam' => '2026-11-04', 'tanggal_kembali' => '2026-11-03']],
        ]);

        $respons->assertOk()
            ->assertSee('the return date is before the borrowing date')
            ->assertSee('pub-item--galat', false)
            ->assertSee('value="2026-11-03"', false);

        $this->assertMatchesRegularExpression('/class="jumlah-barang"[^>]*value="1"/s', $respons->getContent());
    }

    /* ---------------------------- Pemetaan pesan ---------------------------- */

    public function test_pola_pesan_diterjemahkan_ke_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('Change 2: the end time must be after the start time.', TerjemahPesan::teks('Pergantian ke-2: jam selesai harus setelah jam mulai.'));
        $this->assertSame('Item 1: AST-1 is on loan (AST-000777) until 8 Nov 2026.', TerjemahPesan::teks('Barang ke-1: AST-1 sedang dipinjam (AST-000777) sampai 8 Nov 2026.'));
        $this->assertSame('Change 1: this course runs every Monday, not Tuesday.', TerjemahPesan::teks('Pergantian ke-1: mata kuliah ini dijadwalkan setiap hari Senin, bukan Selasa.'));
        $this->assertSame('the room is already reserved at 08:00–10:00.', TerjemahPesan::teks('ruangan sudah dipesan pada 08:00–10:00.'));
        $this->assertSame('2 h 30 min', TerjemahPesan::teks('2 jam 30 menit'));
        $this->assertSame('Room', TerjemahPesan::label('Ruangan'));
    }

    public function test_teks_tak_dikenal_tampil_apa_adanya(): void
    {
        app()->setLocale('en');

        $this->assertSame('Kalimat baru dari controller.', TerjemahPesan::teks('Kalimat baru dari controller.'));
        $this->assertSame('', TerjemahPesan::teks(null));

        app()->setLocale('id');

        $this->assertSame('Pergantian ke-2: jam selesai harus setelah jam mulai.', TerjemahPesan::teks('Pergantian ke-2: jam selesai harus setelah jam mulai.'));
    }
}
