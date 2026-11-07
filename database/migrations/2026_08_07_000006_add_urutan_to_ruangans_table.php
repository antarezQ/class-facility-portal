<?php

use App\Models\Ruangan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruangans', function (Blueprint $table) {
            // Kunci pengurutan alami: gedung, lantai, lalu nomor ruang sebagai angka.
            $table->string('urutan', 60)->nullable()->after('kode')->index();
        });

        // Isi untuk ruangan yang sudah terlanjur ada.
        Ruangan::query()->chunkById(200, function ($ruangans) {
            foreach ($ruangans as $r) {
                $r->updateQuietly(['urutan' => Ruangan::kunciUrut($r->kode)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ruangans', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
