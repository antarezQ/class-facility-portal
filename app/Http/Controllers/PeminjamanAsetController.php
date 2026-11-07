<?php

namespace App\Http\Controllers;

use App\Models\AsetUnit;
use App\Models\KategoriAset;
use App\Models\PeminjamanAset;
use App\Models\PeminjamanAsetBaris;
use App\Models\Periode;
use App\Models\Ruangan;
use App\Services\KetersediaanAsetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PeminjamanAsetController extends Controller
{
    public function form(Request $request, KetersediaanAsetService $ketersediaan): View
    {
        $mulai   = Carbon::parse($request->query('mulai', now()->toDateString()));
        $selesai = Carbon::parse($request->query('selesai', now()->addDays(2)->toDateString()));

        if ($selesai->lt($mulai)) {
            $selesai = $mulai->copy();
        }

        return view('aset.pinjam', [
            'mulai'     => $mulai,
            'selesai'   => $selesai,
            'tersedia'  => $ketersediaan->unitTersedia(
                $mulai, $selesai,
                $request->query('kategori') ?: null,
                $request->query('q')
            ),
            'unitMenunggu' => $ketersediaan->unitMenunggu($mulai, $selesai),
            'kategoris' => KategoriAset::whereHas('units', fn ($q) => $q->siapPinjam())
                ->orderBy('id')->get(),
            'ruangans'  => Ruangan::with('gedung')->where('aktif', true)->orderBy('urutan')->get(),
            'periode'   => Periode::berjalan(),
        ]);
    }

    /** Ketersediaan satu unit, dipakai formulir tanpa memuat ulang halaman. */
    public function cekUnit(Request $request, KetersediaanAsetService $ketersediaan): JsonResponse
    {
        $data = $request->validate([
            'unit'    => ['required', 'exists:aset_units,id'],
            'mulai'   => ['required', 'date'],
            'selesai' => ['required', 'date', 'after_or_equal:mulai'],
        ]);

        $unit  = AsetUnit::findOrFail($data['unit']);
        $pesan = $ketersediaan->periksa(
            $unit, Carbon::parse($data['mulai']), Carbon::parse($data['selesai'])
        );

        return response()->json([
            'bisa'   => $pesan === null,
            'pesan'  => $pesan,
            'saran'  => $pesan ? $ketersediaan->saranPengganti(
                $unit, Carbon::parse($data['mulai']), Carbon::parse($data['selesai'])
            ) : [],
        ]);
    }

    public function store(Request $request, KetersediaanAsetService $ketersediaan): RedirectResponse
    {
        $data = $request->validate([
            'nama'                   => ['required', 'string', 'max:120'],
            'identitas'              => ['nullable', 'string', 'max:30'],
            'peran'                  => ['required', 'string', 'max:30'],
            'unit_organisasi'        => ['nullable', 'string', 'max:120'],
            'kontak'                 => ['required', 'string', 'max:40'],
            'keperluan'              => ['required', 'string', 'max:1000'],
            'ruangan_id'             => ['nullable', 'exists:ruangans,id'],
            'baris'                  => ['required', 'array', 'min:1', 'max:20'],
            'baris.*.aset_unit_id'   => ['required', 'exists:aset_units,id'],
            'baris.*.tanggal_pinjam' => ['required', 'date'],
            'baris.*.tanggal_kembali' => ['required', 'date'],
        ], [
            'baris.required' => 'Pilih minimal satu barang yang ingin dipinjam.',
        ], [
            'nama' => 'nama pemohon', 'kontak' => 'nomor WhatsApp',
        ]);

        $galat = [];
        $siap  = [];
        $sudah = [];

        foreach ($data['baris'] as $i => $b) {
            $unit    = AsetUnit::find($b['aset_unit_id']);
            $mulai   = Carbon::parse($b['tanggal_pinjam']);
            $selesai = Carbon::parse($b['tanggal_kembali']);
            $label   = 'Barang ke-' . ($i + 1) . ': ';

            if ($selesai->lt($mulai)) {
                $galat["baris.{$i}.tanggal_kembali"] = $label . 'tanggal kembali mendahului tanggal pinjam.';
                continue;
            }

            if ($mulai->lt(now()->startOfDay())) {
                $galat["baris.{$i}.tanggal_pinjam"] = $label . 'tanggal pinjam sudah lewat.';
                continue;
            }

            // Unit yang sama tidak boleh dipilih dua kali dalam satu pengajuan.
            if (in_array($unit->id, $sudah, true)) {
                $galat["baris.{$i}.aset_unit_id"] = $label . "{$unit->kode} sudah dipilih pada baris lain.";
                continue;
            }

            if ($pesan = $ketersediaan->periksa($unit, $mulai, $selesai)) {
                $galat["baris.{$i}.aset_unit_id"] = $label . $pesan;
                continue;
            }

            $sudah[] = $unit->id;
            $siap[]  = [
                'aset_unit_id'    => $unit->id,
                'tanggal_pinjam'  => $mulai->toDateString(),
                'tanggal_kembali' => $selesai->toDateString(),
            ];
        }

        if ($galat) {
            return back()->withInput()->withErrors($galat);
        }

        $pengajuan = DB::transaction(function () use ($data, $siap) {
            $induk = PeminjamanAset::create([
                'nomor'      => PeminjamanAset::nomorBaru(),
                'nama'       => $data['nama'],
                'identitas'  => $data['identitas'] ?? null,
                'peran'      => $data['peran'],
                'unit'       => $data['unit_organisasi'] ?? null,
                'kontak'     => $data['kontak'],
                'keperluan'  => $data['keperluan'],
                'ruangan_id' => $data['ruangan_id'] ?? null,
            ]);

            foreach ($siap as $baris) {
                $induk->baris()->create($baris);
            }

            return $induk;
        });

        return back()->with('pesan',
            "Pengajuan {$pengajuan->nomor} terkirim dengan " . count($siap)
            . " barang. Petugas akan menghubungi Anda lewat WhatsApp.");
    }
}
