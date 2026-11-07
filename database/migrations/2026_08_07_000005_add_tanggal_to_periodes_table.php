<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodes', function (Blueprint $table) {
            // Berkas jadwal hanya menyebut hari, tidak menyebut tanggal.
            // Rentang tanggal semester diisi manual lewat halaman Kelola Data,
            // supaya jadwal mingguan bisa dipetakan ke kalender sungguhan.
            $table->date('tanggal_mulai')->nullable()->after('nama');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
        });
    }

    public function down(): void
    {
        Schema::table('periodes', function (Blueprint $table) {
            $table->dropColumn(['tanggal_mulai', 'tanggal_selesai']);
        });
    }
};
