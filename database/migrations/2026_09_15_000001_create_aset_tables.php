<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jenis barang. Kode dua huruf menjadi dasar penomoran unit:
        // kategori HC menghasilkan ICHC-001, ICHC-002, dan seterusnya.
        Schema::create('kategori_asets', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 6)->unique();
            $table->string('nama', 80);
            $table->string('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Satu baris untuk SATU barang fisik. Dua kabel HDMI yang sama
        // tetap dua baris, supaya riwayat tiap barang bisa ditelusuri.
        Schema::create('aset_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_aset_id')->constrained()->cascadeOnDelete();
            $table->string('kode', 20)->unique();          // ICHC-001
            $table->string('nama', 120);
            $table->string('merek', 80)->nullable();

            // Ruangan tempat barang normalnya berada. Barang yang punya
            // ruangan otomatis muncul sebagai fasilitas ruangan tersebut.
            $table->foreignId('ruangan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('kondisi', 10)->default('baik');    // baik | rusak
            $table->string('status', 14)->default('aktif');    // aktif | nonaktif | maintenance | diganti
            $table->boolean('boleh_pinjam')->default(false);   // ditentukan per unit

            $table->date('tanggal_masuk')->nullable();
            $table->string('sumber_dana', 80)->nullable();
            $table->string('foto')->nullable();
            $table->text('deskripsi')->nullable();

            // Unit rusak tidak dihapus. Ia ditandai 'diganti' dan ditautkan
            // ke unit penggantinya, agar riwayat kerusakan tetap utuh.
            $table->foreignId('pengganti_id')->nullable()->constrained('aset_units')->nullOnDelete();
            $table->text('catatan_penggantian')->nullable();

            $table->timestamps();

            $table->index(['kategori_aset_id', 'status'], 'aset_kategori_status_idx');
            $table->index(['ruangan_id', 'status'], 'aset_ruangan_status_idx');
            $table->index('boleh_pinjam');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_units');
        Schema::dropIfExists('kategori_asets');
    }
};
