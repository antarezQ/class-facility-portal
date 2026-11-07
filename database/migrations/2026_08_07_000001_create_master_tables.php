<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 12)->unique();           // 2526-1
            $table->string('tahun_ajaran', 8);              // 2526
            $table->string('semester', 4);                  // 1 | 2 | 3
            $table->string('nama', 60);                     // TA 2025/2026 Ganjil
            $table->boolean('aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('gedungs', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();               // Building A
            $table->string('kode_pendek', 20)->nullable();  // A
            $table->timestamps();
        });

        Schema::create('ruangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gedung_id')->constrained('gedungs')->cascadeOnDelete();
            $table->string('kode', 40)->unique();           // A.08.19
            $table->unsignedTinyInteger('lantai')->default(0);
            $table->string('tipe', 40)->nullable();         // Ruang Kuliah | Ruang Sidang | Ruang Rapat
            $table->unsignedSmallInteger('kapasitas')->nullable();           // dari master ruangan
            $table->unsignedSmallInteger('kapasitas_estimasi')->nullable();  // dihitung dari JML_MHS
            $table->json('fasilitas')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index('gedung_id');
        });

        Schema::create('dosens', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();           // LECTURERCODE
            $table->string('nip', 30)->nullable();
            $table->string('nama')->nullable();
            $table->timestamps();
        });

        Schema::create('mata_kuliahs', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();           // SUBJECTCODE
            $table->string('nama');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_kuliahs');
        Schema::dropIfExists('dosens');
        Schema::dropIfExists('ruangans');
        Schema::dropIfExists('gedungs');
        Schema::dropIfExists('periodes');
    }
};
