<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $kartu = collect([
            [
                'judul'  => 'Cari Ruang Kosong',
                'teks'   => 'Cari ruangan bebas menurut tanggal, jam, dan kapasitas',
                'tautan' => route('cari.index'),
                'ikon'   => 'search',
                'siap'   => true,
            ],
            [
                'judul'  => 'Dashboard Gedung',
                'teks'   => 'Daftar ruangan, jadwal perkuliahan, dan timeline ruangan',
                'tautan' => route('dashboard', 'ruangan'),
                'ikon'   => 'grid',
                'siap'   => true,
            ],
        ]);

        return view('menu.index', [
            'kartu'   => $kartu,
            'periode' => Periode::berjalan(),
        ]);
    }
}
