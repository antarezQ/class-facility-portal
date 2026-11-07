<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            // Jadwal kuliah berulang tiap pekan. Tanpa kolom ini sistem tidak
            // tahu pertemuan pekan ke berapa yang dibatalkan, sehingga ruangan
            // pada tanggal aslinya tetap dianggap terpakai.
            $table->date('tanggal_asal')->nullable()->after('jadwal_id');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropColumn('tanggal_asal');
        });
    }
};
