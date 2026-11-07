<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal bisa berubah di tengah semester — kampus mengizinkan perubahan
     * studi pada dua pekan pertama, sehingga jadwal baru mengunci di pekan
     * ketiga. Dua kolom ini memungkinkan satu mata kuliah punya dua baris
     * yang tidak pernah aktif bersamaan, sehingga riwayat pekan sebelumnya
     * tetap utuh dan analitik tidak menghitung ganda.
     */
    public function up(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            $table->date('berlaku_mulai')->nullable()->after('menit_selesai');
            $table->date('berlaku_sampai')->nullable()->after('berlaku_mulai');

            // Menunjuk baris jadwal yang digantikan, agar rantai perubahan
            // bisa ditelusuri.
            $table->foreignId('induk_id')->nullable()->after('berlaku_sampai')
                ->constrained('jadwals')->nullOnDelete();

            $table->index(['periode_id', 'berlaku_mulai', 'berlaku_sampai'], 'jadwal_masa_berlaku_idx');
        });

        // Catatan setiap perubahan jadwal permanen oleh petugas.
        Schema::create('log_jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jadwal_lama_id')->nullable()->constrained('jadwals')->nullOnDelete();
            $table->foreignId('jadwal_baru_id')->nullable()->constrained('jadwals')->nullOnDelete();

            $table->string('aksi', 20);                  // pindah | batal | pulihkan
            $table->string('mata_kuliah', 150)->nullable();
            $table->string('kelas', 40)->nullable();
            $table->json('sebelum')->nullable();
            $table->json('sesudah')->nullable();
            $table->date('berlaku_mulai')->nullable();
            $table->text('alasan')->nullable();

            $table->unsignedBigInteger('oleh')->nullable();
            $table->string('nama_petugas', 120)->nullable();
            $table->timestamps();

            $table->index(['periode_id', 'created_at'], 'log_jadwal_periode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_jadwals');

        Schema::table('jadwals', function (Blueprint $table) {
            $table->dropForeign(['induk_id']);
            $table->dropColumn(['berlaku_mulai', 'berlaku_sampai', 'induk_id']);
        });
    }
};
