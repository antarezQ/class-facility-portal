<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu kode mata kuliah boleh dipakai beberapa mata kuliah berbeda.
 *
 * Skema awal menganggap kode itu pengenal tunggal, mengikuti SUBJECTCODE pada
 * ekspor sistem akademik. Pada kenyataannya beberapa kode dipakai lebih dari
 * satu mata kuliah.
 *
 * Akibat batasan lama, mata kuliah kedua tidak bisa dibuat sama sekali. Yang
 * terjadi malah diam-diam: nama yang diketik petugas dibuang dan baris jadwal
 * menempel ke mata kuliah yang lebih dulu memakai kode itu.
 *
 * Penggantinya batasan unik gabungan (kode, nama): satu kode boleh berulang
 * asalkan nama mata kuliahnya berbeda, dan baris yang benar-benar kembar tetap
 * tertolak. Indeks biasa pada kode dipertahankan supaya pencarian tetap cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unikKode = $this->indeksUnikKode();

        if ($unikKode) {
            Schema::table('mata_kuliahs', function (Blueprint $table) use ($unikKode) {
                $table->dropUnique($unikKode);
            });
        }

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            if (! $this->punyaIndeks('mata_kuliahs_kode_index')) {
                $table->index('kode', 'mata_kuliahs_kode_index');
            }

            if (! $this->punyaIndeks('mata_kuliahs_kode_nama_unique')) {
                $table->unique(['kode', 'nama'], 'mata_kuliahs_kode_nama_unique');
            }
        });
    }

    public function down(): void
    {
        /*
         * Batasan lama hanya bisa dipasang kembali bila tidak ada kode yang
         * terlanjur dipakai lebih dari satu mata kuliah. Kalau ada, barisnya
         * dibiarkan dan indeks unik tidak dipasang — lebih baik turun tanpa
         * batasan daripada menghapus data jadwal orang.
         */
        Schema::table('mata_kuliahs', function (Blueprint $table) {
            if ($this->punyaIndeks('mata_kuliahs_kode_nama_unique')) {
                $table->dropUnique('mata_kuliahs_kode_nama_unique');
            }

            if ($this->punyaIndeks('mata_kuliahs_kode_index')) {
                $table->dropIndex('mata_kuliahs_kode_index');
            }
        });

        $kembar = \Illuminate\Support\Facades\DB::table('mata_kuliahs')
            ->whereNotNull('kode')
            ->groupBy('kode')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('kode');

        if ($kembar->isEmpty()) {
            Schema::table('mata_kuliahs', function (Blueprint $table) {
                $table->unique('kode');
            });
        }
    }

    /** Nama indeks unik yang hanya memuat kolom kode, bila masih ada. */
    protected function indeksUnikKode(): ?string
    {
        foreach (Schema::getIndexes('mata_kuliahs') as $i) {
            if (($i['unique'] ?? false) && ($i['columns'] ?? []) === ['kode']) {
                return $i['name'];
            }
        }

        return null;
    }

    protected function punyaIndeks(string $nama): bool
    {
        foreach (Schema::getIndexes('mata_kuliahs') as $i) {
            if (($i['name'] ?? null) === $nama) {
                return true;
            }
        }

        return false;
    }
};
