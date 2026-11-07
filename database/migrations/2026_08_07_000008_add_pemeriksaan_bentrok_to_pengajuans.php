<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            // Ruangan kosong saja tidak cukup. Dosennya bisa sedang mengajar
            // di ruang lain, dan rombongan kelasnya bisa punya mata kuliah
            // lain pada jam yang sama.
            $table->boolean('bentrok_dosen')->default(false)->after('catatan_bentrok');
            $table->json('catatan_dosen')->nullable()->after('bentrok_dosen');
            $table->boolean('bentrok_kelas')->default(false)->after('catatan_dosen');
            $table->json('catatan_kelas')->nullable()->after('bentrok_kelas');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropColumn(['bentrok_dosen', 'catatan_dosen', 'bentrok_kelas', 'catatan_kelas']);
        });
    }
};
