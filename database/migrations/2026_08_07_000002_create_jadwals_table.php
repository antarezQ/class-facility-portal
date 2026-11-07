<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gedung_id')->constrained();
            $table->foreignId('ruangan_id')->constrained();
            $table->foreignId('dosen_id')->nullable()->constrained();
            $table->foreignId('mata_kuliah_id')->nullable()->constrained();

            $table->unsignedTinyInteger('hari');             // 0 = Senin ... 5 = Sabtu
            $table->unsignedSmallInteger('menit_mulai');     // 810 = 13:30, dipakai untuk hitung cepat
            $table->unsignedSmallInteger('menit_selesai');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->unsignedSmallInteger('durasi_menit');

            $table->string('kelas', 40)->nullable();
            $table->string('prodi')->nullable();
            $table->unsignedSmallInteger('jml_mhs')->default(0);
            $table->boolean('overtime')->default(false);
            $table->timestamps();

            $table->index(['periode_id', 'hari']);
            $table->index(['ruangan_id', 'hari']);
            $table->index(['periode_id', 'gedung_id']);
        });

        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('nama_berkas');
            $table->foreignId('periode_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('baris_terbaca')->default(0);
            $table->unsignedInteger('baris_ditolak')->default(0);
            $table->unsignedInteger('jumlah_overtime')->default(0);
            $table->unsignedInteger('jumlah_bentrok')->default(0);
            $table->json('catatan')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
        Schema::dropIfExists('jadwals');
    }
};
