<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penutupan_ruangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruangan_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('alasan');           // perbaikan AC, pengecatan, dan sebagainya
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->timestamps();

            // Nama indeks disebut sendiri; nama bawaan Laravel untuk tiga kolom
            // ini melewati batas 64 karakter yang diizinkan MySQL.
            $table->index(['ruangan_id', 'tanggal_mulai', 'tanggal_selesai'], 'penutupan_ruangan_rentang_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penutupan_ruangans');
    }
};
