<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kode mata kuliah dan kode dosen boleh kosong.
 *
 * Skema awal mengikuti bentuk ekspor sistem akademik, yang selalu memuat SUBJECTCODE
 * dan LECTURERCODE. Ketika jadwal disusun manual, anggapan itu tidak berlaku:
 * kegiatan seperti tes bahasa memang tidak
 * punya kode mata kuliah sama sekali.
 *
 * Indeks unique dipertahankan. Baik MySQL maupun SQLite memperlakukan NULL
 * sebagai nilai yang tidak pernah sama dengan NULL lain, jadi beberapa baris
 * tanpa kode tetap boleh berdampingan — sementara kode yang benar-benar diisi
 * tetap tidak boleh kembar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Baris lama yang kodenya terlanjur berisi teks kosong disamakan
        // dengan NULL, supaya hanya ada satu bentuk "tidak punya kode".
        DB::table('mata_kuliahs')->where('kode', '')->update(['kode' => null]);
        DB::table('dosens')->where('kode', '')->update(['kode' => null]);

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            $table->string('kode', 30)->nullable()->change();
        });

        Schema::table('dosens', function (Blueprint $table) {
            $table->string('kode', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Kolomnya tidak bisa dikembalikan menjadi wajib selama masih ada
        // baris tanpa kode, jadi barisnya diberi kode sementara lebih dulu.
        DB::table('mata_kuliahs')->whereNull('kode')->get()->each(function ($mk) {
            DB::table('mata_kuliahs')->where('id', $mk->id)->update(['kode' => 'MK-' . $mk->id]);
        });

        DB::table('dosens')->whereNull('kode')->get()->each(function ($d) {
            DB::table('dosens')->where('id', $d->id)->update(['kode' => 'DSN-' . $d->id]);
        });

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            $table->string('kode', 30)->nullable(false)->change();
        });

        Schema::table('dosens', function (Blueprint $table) {
            $table->string('kode', 20)->nullable(false)->change();
        });
    }
};
