<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Induk pengajuan: identitas pemohon dan keperluannya.
        Schema::create('peminjaman_asets', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 20)->unique();          // PJA-000001
            $table->string('nama', 120);
            $table->string('identitas', 30)->nullable();
            $table->string('peran', 30);
            $table->string('unit', 120)->nullable();
            $table->string('kontak', 40);
            $table->text('keperluan');

            // Bila peminjaman terkait kegiatan di ruangan tertentu.
            $table->foreignId('ruangan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 14)->default('menunggu');
            $table->unsignedBigInteger('diproses_oleh')->nullable();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Satu baris untuk satu unit aset. Tanggal kembali boleh berbeda
        // tiap unit, dan tiap baris punya statusnya sendiri sehingga
        // petugas dapat menyetujui sebagian dan menolak sisanya.
        Schema::create('peminjaman_aset_barises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peminjaman_aset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aset_unit_id')->constrained()->cascadeOnDelete();

            $table->date('tanggal_pinjam');
            $table->date('tanggal_kembali');                 // jatuh tempo per unit

            $table->string('status', 16)->default('menunggu');
            // menunggu | disetujui | ditolak | dipinjam | selesai | batal

            // Bukti serah terima dan pengembalian, difoto petugas.
            $table->string('foto_serah')->nullable();
            $table->timestamp('diserahkan_pada')->nullable();
            $table->string('foto_kembali')->nullable();
            $table->timestamp('dikembalikan_pada')->nullable();

            $table->string('kondisi_kembali', 10)->nullable();   // baik | rusak
            $table->text('catatan_kembali')->nullable();
            $table->text('catatan_petugas')->nullable();

            // Diisi bila barang dikembalikan rusak dan harus diganti peminjam.
            $table->boolean('wajib_ganti')->default(false);
            $table->boolean('sudah_diganti')->default(false);

            $table->timestamps();

            $table->index(['aset_unit_id', 'status'], 'pinjam_aset_status_idx');
            $table->index(['status', 'tanggal_kembali'], 'pinjam_status_tempo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_aset_barises');
        Schema::dropIfExists('peminjaman_asets');
    }
};
