<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruangans', function (Blueprint $table) {
            // 'impor'  = terbaca dari berkas jadwal
            // 'manual' = ditambahkan petugas, mis. ruang multimedia atau ruang
            //            konsultasi yang tidak pernah muncul di jadwal kuliah
            $table->string('sumber', 10)->default('impor')->after('kode')->index();
            $table->string('foto')->nullable()->after('fasilitas');
            $table->text('keterangan')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('ruangans', function (Blueprint $table) {
            $table->dropColumn(['sumber', 'foto', 'keterangan']);
        });
    }
};
