<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu pengajuan pergantian bisa memuat beberapa pemindahan sekaligus —
     * misalnya dosen izin dua pekan berturut-turut. Tiap baris punya waktu,
     * ruangan, hasil pemeriksaan, dan STATUSNYA SENDIRI, sehingga petugas
     * dapat menyetujui sebagian dan menolak sisanya.
     */
    public function up(): void
    {
        Schema::create('pengajuan_barises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jadwal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ruangan_id')->nullable()->constrained()->nullOnDelete();

            $table->date('tanggal_asal')->nullable();      // pertemuan yang dibatalkan
            $table->date('tanggal');                       // tanggal pengganti
            $table->unsignedTinyInteger('hari');
            $table->unsignedSmallInteger('menit_mulai');
            $table->unsignedSmallInteger('menit_selesai');
            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->boolean('bentrok')->default(false);
            $table->json('catatan_bentrok')->nullable();
            $table->boolean('bentrok_dosen')->default(false);
            $table->json('catatan_dosen')->nullable();
            $table->boolean('bentrok_kelas')->default(false);
            $table->json('catatan_kelas')->nullable();

            $table->string('status', 12)->default('menunggu');
            $table->text('catatan_petugas')->nullable();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamps();

            $table->index(['pengajuan_id', 'status']);
            $table->index(['ruangan_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_barises');
    }
};
