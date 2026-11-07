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
use App\Services\PemakaianRuanganService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /papan, /papan/data, dan timeline terbuka tanpa login, jadi tidak boleh
 * membawa data pribadi pemohon maupun NIP dosen.
 */
class PapanDataPrivasiTest extends TestCase
{
    use RefreshDatabase;

    private const RAHASIA = ['Alex Example', '1301999999', '+6281200000000', 'Unit Rahasia', 'RSV-000123', 'Catatan Rahasia', '199001012020', 'Rapat Panitia'];

    private Periode $periode;

    private Ruangan $ruangan;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, supaya hasilnya tidak bergantung pada hari tes dijalankan.
        Carbon::setTestNow('2026-11-04 09:00:00');

        $this->periode = Periode::create([
            'kode' => '2627-1', 'tahun_ajaran' => '2026/2027', 'semester' => 1,
            'nama' => 'Semester Uji', 'aktif' => true,
        ]);
        $gedung = Gedung::create(['nama' => 'Gedung Uji', 'kode_pendek' => 'A']);
        $this->ruangan = Ruangan::create(['gedung_id' => $gedung->id, 'kode' => 'A.03.11', 'lantai' => 3, 'aktif' => true]);

        Pengajuan::create([
            'nomor' => 'RSV-000123', 'jenis' => 'ruangan', 'periode_id' => $this->periode->id,
            'nama' => 'Alex Example', 'identitas' => '1301999999', 'peran' => 'Lainnya',
            'unit' => 'Unit Rahasia', 'kontak' => '+6281200000000',
            'tanggal' => now()->toDateString(), 'hari' => 2,
            'menit_mulai' => 480, 'menit_selesai' => 600,
            'jam_mulai' => '08:00:00', 'jam_selesai' => '10:00:00',
            'ruangan_id' => $this->ruangan->id, 'jumlah_peserta' => 10,
            'keperluan' => 'Rapat Panitia', 'status' => 'disetujui', 'catatan_petugas' => 'Catatan Rahasia',
        ]);

        Jadwal::create([
            'periode_id' => $this->periode->id, 'gedung_id' => $gedung->id, 'ruangan_id' => $this->ruangan->id,
            'dosen_id' => Dosen::create(['kode' => 'ABC', 'nip' => '199001012020', 'nama' => 'Dosen Uji'])->id,
            'mata_kuliah_id' => MataKuliah::create(['kode' => 'MK01', 'nama' => 'Kalkulus Uji'])->id,
            'hari' => 2, 'menit_mulai' => 660, 'menit_selesai' => 780,
            'jam_mulai' => '11:00:00', 'jam_selesai' => '13:00:00', 'durasi_menit' => 120,
            'kelas' => 'IT-50-01', 'prodi' => 'Informatika', 'jml_mhs' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function assertTanpaRahasia(string $isi): void
    {
        foreach (self::RAHASIA as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $isi);
        }
    }

    public function test_sumber_data_memang_membawa_data_pribadi(): void
    {
        // Tanpa ini, tes penyaringan di bawah bisa lulus karena datanya tidak pernah ada.
        $sumber = json_encode((new PemakaianRuanganService($this->periode))->padaTanggal(now()));

        foreach (self::RAHASIA as $rahasia) {
            $this->assertStringContainsString($rahasia, $sumber);
        }
    }

    public function test_json_papan_hanya_membawa_field_aman(): void
    {
        $respons = $this->getJson('/papan/data')->assertOk();

        foreach ($respons->json('kegiatan') as $x) {
            $this->assertEqualsCanonicalizing(['ruangan_id', 'ruangan', 'mulai', 'selesai', 'jenis', 'status'], array_keys($x));
        }

        $this->assertCount(2, $respons->json('kegiatan'));
        $this->assertTanpaRahasia($respons->getContent());
    }

    public function test_html_papan_tanpa_data_pribadi(): void
    {
        $respons = $this->get('/papan')->assertOk();

        $respons->assertSee('Reserved');
        $respons->assertSee('Kalkulus Uji');
        $respons->assertSee('data-rinci', false);
        $this->assertTanpaRahasia($respons->getContent());
    }

    public function test_timeline_tamu_tanpa_data_pribadi(): void
    {
        $respons = $this->get('/dashboard/timeline?tanggal=2026-11-04')->assertOk();

        $respons->assertSee('Reserved');
        $respons->assertSee('Kalkulus Uji');
        $this->assertTanpaRahasia($respons->getContent());
    }

    public function test_info_bentrok_di_cari_tanpa_nama_untuk_tamu(): void
    {
        $url = '/cari?cari=1&tanggal=2026-11-04&mulai=08:00&selesai=10:00&ruangan=A.03.11';

        $tamu = $this->get($url)->assertOk();
        $tamu->assertDontSee('RSV-000123');
        $tamu->assertDontSee('Alex Example');

    }

    public function test_nomor_pergantian_tidak_tampil(): void
    {
        $jadwal = Jadwal::first();
        $isian = fn (string $asal) => [
            'nama' => 'Pemohon Uji', 'peran' => 'Dosen', 'kontak' => '+6281211111111', 'keperluan' => 'Uji',
            'baris' => [[
                'jadwal_id' => $jadwal->id, 'tanggal_asal' => $asal, 'tanggal' => '2026-11-05',
                'mulai' => '08:00', 'selesai' => '10:00', 'ruangan_id' => $this->ruangan->id,
            ]],
        ];

        $this->post('/pengajuan/pergantian', $isian('2026-11-04'))->assertSessionHasNoErrors();
        $pergantian = Pengajuan::jenis('pergantian')->first();
        $pergantian->baris()->update(['status' => 'disetujui']);
        $nomor = $pergantian->nomor;
        $this->flushSession();

        // Info bentrok di /cari
        $url = '/cari?cari=1&tanggal=2026-11-05&mulai=08:00&selesai=10:00&ruangan=A.03.11';
        $this->get($url)->assertOk()->assertSee('Used by a replacement class')->assertDontSee($nomor);

        // Pesan gagal: pertemuan yang sudah dipindah
        $galat = $this->from('/cari')->post('/pengajuan/pergantian', $isian('2026-11-04'))
            ->assertSessionHasErrors('baris.0.tanggal_asal')
            ->getSession()->get('errors')->first('baris.0.tanggal_asal');
        $this->assertStringContainsString('sudah disetujui', $galat);
        $this->assertStringNotContainsString($nomor, $galat);

        // Pesan gagal: ruangan dipakai kuliah pengganti
        $galat = $this->from('/cari')->post('/pengajuan/ruangan', [
            'nama' => 'Pemohon Lain', 'peran' => 'Lainnya', 'kontak' => '+6281211111111',
            'tanggal' => '2026-11-05', 'mulai' => '08:00', 'selesai' => '10:00',
            'ruangan_id' => $this->ruangan->id, 'jumlah_peserta' => 5, 'keperluan' => 'Uji',
        ])->assertSessionHasErrors('ruangan_id')->getSession()->get('errors')->first('ruangan_id');
        $this->assertStringContainsString('kuliah pengganti', $galat);
        $this->assertStringNotContainsString($nomor, $galat);
    }

    public function test_keperluan_tidak_sampai_ke_tamu_di_halaman_mana_pun(): void
    {
        $halaman = [
            '/', '/papan', '/papan/data', '/pinjam-aset', '/cari',
            '/cari?cari=1&tanggal=2026-11-04&mulai=08:00&selesai=10:00&ruangan=A.03.11',
            '/cari?cari=1&tanggal=2026-11-04&mulai=10:00&selesai=11:00',
            '/dashboard', '/dashboard/ruangan', '/dashboard/jadwal',
            '/dashboard/timeline?tanggal=2026-11-04',
        ];

        foreach (['en', 'id'] as $bahasa) {
            foreach ($halaman as $url) {
                $isi = $this->get($url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . $bahasa)->assertOk()->getContent();

                $this->assertStringNotContainsString('Rapat Panitia', $isi, "$url ($bahasa)");
                $this->assertStringNotContainsString('RSV-000123', $isi, "$url ($bahasa)");
            }
        }
    }

    public function test_tamu_melihat_label_reservasi_sesuai_bahasa(): void
    {
        $url = '/dashboard/timeline?tanggal=2026-11-04';

        $this->get($url . '&lang=en')->assertSee('Reserved')->assertDontSee('Direservasi');
        $this->get($url . '&lang=id')->assertSee('Direservasi')->assertDontSee('Reserved');

        $hasil = (new PemakaianRuanganService($this->periode))->untukPublik(
            (new PemakaianRuanganService($this->periode))->padaTanggal(now())
        )->firstWhere('jenis', 'rsv');

        $this->assertSame('Direservasi', $hasil['judul']);
        $this->assertNotContains('Keperluan', array_column($hasil['detail']['baris'], 'label'));
    }

    public function test_keperluan_tidak_tampil_di_info_bentrok_cari(): void
    {
        $url = '/cari?cari=1&tanggal=2026-11-04&mulai=08:00&selesai=10:00&ruangan=A.03.11';

        $this->get($url . '&lang=en')->assertSee('Reserved')->assertDontSee('Rapat Panitia');
        $this->get($url . '&lang=id')->assertSee('Direservasi')->assertDontSee('Rapat Panitia');

    }

    public function test_pesan_bentrok_reservasi_tanpa_nama(): void
    {
        $respons = $this->from('/cari')->post('/pengajuan/ruangan', [
            'nama' => 'Pemohon Lain', 'peran' => 'Lainnya', 'kontak' => '+6281211111111',
            'tanggal' => '2026-11-04', 'mulai' => '08:00', 'selesai' => '10:00',
            'ruangan_id' => $this->ruangan->id, 'jumlah_peserta' => 5, 'keperluan' => 'Uji',
        ]);

        $pesan = $respons->assertSessionHasErrors('ruangan_id')->getSession()->get('errors')->first('ruangan_id');

        $this->assertStringContainsString('ruangan sudah dipesan pada', $pesan);
        $this->assertStringNotContainsString('RSV-000123', $pesan);
        $this->assertStringNotContainsString('Alex Example', $pesan);
        $this->assertStringNotContainsString('Rapat Panitia', $pesan);
    }

    public function test_cek_unit_aset_tanpa_nama_peminjam(): void
    {
        $unit = AsetUnit::create([
            'kategori_aset_id' => KategoriAset::create(['kode' => 'PRJ', 'nama' => 'Proyektor'])->id,
            'kode' => 'AST-001', 'nama' => 'Proyektor Uji',
            'kondisi' => 'baik', 'status' => 'aktif', 'boleh_pinjam' => true,
        ]);
        $pinjam = PeminjamanAset::create([
            'nomor' => 'AST-000777', 'nama' => 'Alex Example', 'peran' => 'Mahasiswa',
            'kontak' => '+6281200000000', 'keperluan' => 'Uji',
        ]);
        $pinjam->baris()->create([
            'aset_unit_id' => $unit->id, 'tanggal_pinjam' => '2026-11-04',
            'tanggal_kembali' => '2026-11-06', 'status' => 'dipinjam',
        ]);

        $respons = $this->getJson("/pinjam-aset/cek?unit={$unit->id}&mulai=2026-11-04&selesai=2026-11-05")
            ->assertOk()
            ->assertJsonPath('bisa', false);

        $this->assertStringContainsString('AST-000777', $respons->json('pesan'));
        $this->assertStringNotContainsString('Alex Example', $respons->getContent());
    }

    public function test_nip_tidak_tampil_di_tab_jadwal(): void
    {
        $tamu = $this->get('/dashboard/jadwal')->assertOk();
        $tamu->assertSee('Kalkulus Uji');
        $tamu->assertDontSee('199001012020');
    }

    public function test_rincian_pengganti_membuang_alasan_dan_pengaju(): void
    {
        $hasil = (new PemakaianRuanganService($this->periode))->untukPublik(collect([[
            'jenis'  => 'ganti',
            'ket'    => 'IT-50-01 · ABC · pengganti 2 Nov',
            'detail' => ['baris' => [
                ['label' => 'Kelas', 'nilai' => 'IT-50-01'],
                ['label' => 'Diajukan oleh', 'nilai' => 'Alex Example'],
                ['label' => 'Kontak', 'nilai' => '+6281200000000'],
                ['label' => 'Alasan', 'nilai' => 'Alasan pribadi'],
                ['label' => 'Nomor pengajuan', 'nilai' => 'GNT-1'],
            ]],
        ]]));

        $this->assertSame([['label' => 'Kelas', 'nilai' => 'IT-50-01']], $hasil[0]['detail']['baris']);
        $this->assertSame('IT-50-01 · ABC · pengganti 2 Nov', $hasil[0]['ket']);
    }
}
