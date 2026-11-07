<?php

use App\Http\Controllers\CariRuanganController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PapanController;
use App\Http\Controllers\PeminjamanAsetController;
use App\Http\Controllers\PengajuanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'index'])->name('menu');

Route::get('/papan', [PapanController::class, 'index'])->name('papan.index');
Route::get('/papan/data', [PapanController::class, 'json'])->name('papan.json');

Route::get('/cari', [CariRuanganController::class, 'index'])->name('cari.index');

// Kotak cari mata kuliah pada formulir pergantian jadwal.
Route::get('/jadwal/cari', [PengajuanController::class, 'cariJadwal'])->name('jadwal.cari');

Route::get('/pinjam-aset', [PeminjamanAsetController::class, 'form'])->name('aset.pinjam');
Route::get('/pinjam-aset/cek', [PeminjamanAsetController::class, 'cekUnit'])->name('aset.pinjam.cek');
Route::post('/pinjam-aset', [PeminjamanAsetController::class, 'store'])->name('aset.pinjam.store');

// Formulir reservasi dikirim dari halaman Cari Ruang Kosong.
Route::post('/pengajuan/{jenis}', [PengajuanController::class, 'store'])->name('pengajuan.store');

Route::get('/dashboard/{tab?}', [DashboardController::class, 'index'])->name('dashboard');
