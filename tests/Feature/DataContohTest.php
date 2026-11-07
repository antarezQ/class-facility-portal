<?php

namespace Tests\Feature;

use App\Models\AsetUnit;
use App\Models\Jadwal;
use App\Models\Pengajuan;
use App\Models\Ruangan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataContohTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_data_contoh_terisi(): void
    {
        $this->assertSame(14, Ruangan::count());
        $this->assertGreaterThan(50, Jadwal::count());
        $this->assertSame(80, AsetUnit::count());
        $this->assertSame(1, Pengajuan::count());
    }

    public function test_semua_halaman_terbuka_dalam_dua_bahasa(): void
    {
        $halaman = [
            '/', '/papan', '/papan/data', '/cari', '/pinjam-aset',
            '/dashboard/ruangan', '/dashboard/jadwal', '/dashboard/timeline',
            '/cari?cari=1&tanggal=' . now()->toDateString() . '&mulai=10:00&selesai=11:00',
        ];

        foreach (['en', 'id'] as $bahasa) {
            foreach ($halaman as $url) {
                $this->get($url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . $bahasa)->assertOk();
            }
        }
    }

    public function test_keperluan_dan_pemohon_contoh_tidak_tampil(): void
    {
        $isi = $this->get('/dashboard/timeline')->assertOk()->getContent();

        $this->assertStringContainsString('Reserved', $isi);
        $this->assertStringNotContainsString('Student club meeting', $isi);
        $this->assertStringNotContainsString('Sample Applicant', $isi);
    }

    public function test_barang_tampil_sebagai_satu_baris_per_jenis(): void
    {
        $isi = $this->get('/pinjam-aset')->assertOk()->getContent();

        $this->assertSame(14, substr_count($isi, 'class="jumlah-barang"'));
        $this->assertStringNotContainsString('AST-HDMI002', $isi);
    }

    public function test_rute_petugas_tidak_ada(): void
    {
        foreach (['/login', '/admin', '/dashboard/overtime', '/dashboard/analitik', '/dashboard/timetable'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
