<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Gedung;
use App\Models\Jadwal;
use App\Models\MataKuliah;
use App\Models\Pengajuan;
use App\Models\PenutupanRuangan;
use App\Models\Periode;
use App\Models\Ruangan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh untuk mencoba aplikasi. Seluruh isinya rekaan: gedung, ruangan,
 * mata kuliah, dosen, pemohon, dan barang tidak mewakili data sungguhan.
 */
class DatabaseSeeder extends Seeder
{
    private const MATA_KULIAH = [
        ['CS101', 'Introduction to Programming'],
        ['CS204', 'Data Structures'],
        ['CS310', 'Database Systems'],
        ['CS320', 'Web Application Development'],
        ['IS215', 'Business Process Analysis'],
        ['IS330', 'UI/UX Design'],
        ['MA110', 'Calculus 1'],
        ['MA205', 'Statistics for AI'],
        ['MG120', 'Principles of Management'],
        ['MG240', 'Digital Marketing'],
        ['EN100', 'Academic English'],
        ['EN210', 'Business Communication'],
    ];

    private const PRODI = ['Computer Science', 'Information Systems', 'Management', 'Data Science'];

    /** Jam mulai dan selesai dalam menit. */
    private const SESI = [[510, 630], [630, 750], [810, 930], [930, 1050]];

    public function run(): void
    {
        DB::transaction(function () {
            $periode = Periode::create([
                'kode' => 'DEMO', 'tahun_ajaran' => now()->year . '/' . (now()->year + 1), 'semester' => 1,
                'nama' => 'Demo Semester', 'aktif' => true,
                'tanggal_mulai' => now()->subDays(30)->toDateString(),
                'tanggal_selesai' => now()->addDays(90)->toDateString(),
            ]);

            $ruangans = $this->buatRuangan();
            $this->buatJadwal($periode, $ruangans);
            $this->buatPemakaianHariIni($periode, $ruangans);
        });
    }

    private function buatRuangan(): array
    {
        $gedungA = Gedung::create(['nama' => 'Building A', 'kode_pendek' => 'A']);
        $gedungB = Gedung::create(['nama' => 'Building B', 'kode_pendek' => 'B']);
        $ruangans = [];

        $susunan = [[$gedungA, 'A', 3, 6], [$gedungA, 'A', 4, 4], [$gedungB, 'B', 2, 4]];

        foreach ($susunan as [$gedung, $awalan, $lantai, $jumlah]) {
            for ($i = 1; $i <= $jumlah; $i++) {
                $ruangans[] = Ruangan::create([
                    'gedung_id' => $gedung->id,
                    'kode' => sprintf('%s.%02d.%02d', $awalan, $lantai, $i),
                    'lantai' => $lantai,
                    'tipe' => 'Classroom',
                    'kapasitas' => [30, 40, 50][$i % 3],
                    'aktif' => true,
                ]);
            }
        }

        return $ruangans;
    }

    private function buatJadwal(Periode $periode, array $ruangans): void
    {
        $dosens = [];
        foreach (['L01', 'L02', 'L03', 'L04', 'L05', 'L06', 'L07', 'L08'] as $i => $kode) {
            $dosens[] = Dosen::create(['kode' => $kode, 'nama' => 'Sample Lecturer ' . ($i + 1)]);
        }

        $mataKuliah = array_map(
            fn ($mk) => MataKuliah::create(['kode' => $mk[0], 'nama' => $mk[1]]),
            self::MATA_KULIAH
        );

        $n = 0;

        // Selang-seling supaya ada ruangan terpakai, segera terpakai, dan kosong.
        foreach ($ruangans as $r => $ruangan) {
            foreach (range(0, 5) as $hari) {
                foreach (self::SESI as $s => [$mulai, $selesai]) {
                    if (($r + $hari + $s) % 3 !== 0) {
                        continue;
                    }

                    Jadwal::create([
                        'periode_id' => $periode->id,
                        'gedung_id' => $ruangan->gedung_id,
                        'ruangan_id' => $ruangan->id,
                        'dosen_id' => $dosens[$n % count($dosens)]->id,
                        'mata_kuliah_id' => $mataKuliah[$n % count($mataKuliah)]->id,
                        'hari' => $hari,
                        'menit_mulai' => $mulai,
                        'menit_selesai' => $selesai,
                        'jam_mulai' => sprintf('%02d:%02d:00', intdiv($mulai, 60), $mulai % 60),
                        'jam_selesai' => sprintf('%02d:%02d:00', intdiv($selesai, 60), $selesai % 60),
                        'durasi_menit' => $selesai - $mulai,
                        'kelas' => sprintf('CL-%02d-%02d', 20 + $n % 4, $n % 9 + 1),
                        'prodi' => self::PRODI[$n % count(self::PRODI)],
                        'jml_mhs' => 20 + $n % 21,
                        'overtime' => false,
                    ]);

                    $n++;
                }
            }
        }
    }

    /** Satu reservasi dan satu penutupan, supaya labelnya terlihat di Timeline. */
    private function buatPemakaianHariIni(Periode $periode, array $ruangans): void
    {
        Pengajuan::create([
            'nomor' => 'RSV-000001', 'jenis' => 'ruangan', 'periode_id' => $periode->id,
            'nama' => 'Sample Applicant', 'peran' => 'Lainnya', 'kontak' => '+62 800-0000-0000',
            'tanggal' => now()->toDateString(), 'hari' => now()->dayOfWeekIso - 1,
            'menit_mulai' => 1080, 'menit_selesai' => 1170,
            'jam_mulai' => '18:00:00', 'jam_selesai' => '19:30:00',
            'ruangan_id' => $ruangans[0]->id, 'jumlah_peserta' => 25,
            'keperluan' => 'Student club meeting', 'status' => 'disetujui',
        ]);

        PenutupanRuangan::create([
            'ruangan_id' => end($ruangans)->id,
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->addDays(7)->toDateString(),
            'alasan' => 'Projector maintenance',
        ]);
    }
}
