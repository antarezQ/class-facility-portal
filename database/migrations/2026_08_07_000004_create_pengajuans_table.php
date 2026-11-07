<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 20)->unique();          // RSV-000123 / PGJ-000045

            // 'ruangan'    = pinjam ruang kosong
            // 'pergantian' = memindahkan jadwal kuliah yang sudah ada
            $table->string('jenis', 12);

            $table->foreignId('periode_id')->constrained()->cascadeOnDelete();

            // Pemohon
            $table->string('nama');
            $table->string('identitas', 30)->nullable();     // NIM / NIP
            $table->string('peran', 30)->nullable();         // Mahasiswa / Dosen / Tendik
            $table->string('unit')->nullable();              // prodi, himpunan, unit kerja
            $table->string('kontak', 40)->nullable();        // nomor WhatsApp

            // Waktu yang diminta
            $table->date('tanggal');
            $table->unsignedTinyInteger('hari');             // 0 = Senin
            $table->unsignedSmallInteger('menit_mulai');
            $table->unsignedSmallInteger('menit_selesai');
            $table->time('jam_mulai');
            $table->time('jam_selesai');

            // Ruangan yang diminta, dan untuk pergantian: jadwal asalnya
            $table->foreignId('ruangan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('jadwal_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('jumlah_peserta')->nullable();
            $table->text('keperluan')->nullable();

            // Hasil pemeriksaan otomatis saat pengajuan dikirim
            $table->boolean('bentrok')->default(false);
            $table->json('catatan_bentrok')->nullable();

            $table->string('status', 12)->default('menunggu');   // menunggu | disetujui | ditolak
            $table->text('catatan_petugas')->nullable();
            $table->unsignedBigInteger('diproses_oleh')->nullable();
            $table->timestamp('diproses_pada')->nullable();

            $table->timestamps();

            $table->index(['jenis', 'status']);
            $table->index(['periode_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuans');
    }
};
