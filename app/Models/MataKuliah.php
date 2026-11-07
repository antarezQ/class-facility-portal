<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataKuliah extends Model
{
    protected $table = 'mata_kuliahs';

    protected $fillable = ['kode', 'nama'];

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    /**
     * Cari menurut pasangan kode + nama; buat bila belum ada.
     *
     * Yang menentukan satu mata kuliah adalah KODE BESERTA NAMANYA, bukan
     * kodenya saja, karena satu kode bisa dipakai beberapa mata kuliah. Dulu kode
     * dijadikan penentu tunggal, sehingga nama yang diketik petugas dibuang
     * dan barisnya menempel ke mata kuliah yang kebetulan lebih dulu memakai
     * kode itu.
     *
     * Kode tetap boleh kosong: kegiatan seperti tes bahasa
     * memang tidak punya kode, dan nama menjadi satu-satunya pengenal.
     */
    public static function temukanAtauBuat(?string $kode, string $nama): self
    {
        $kode = trim((string) $kode);
        $nama = trim($nama);

        // 1. Pasangan kode + nama yang sama persis.
        if ($kode !== '' && $nama !== '') {
            $mk = static::where('kode', $kode)
                ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])
                ->first();

            if ($mk) {
                return $mk;
            }
        }

        if ($nama !== '') {
            $q = static::whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)]);

            // Bila petugas membawa kode, hanya baris yang kodenya masih kosong
            // yang boleh dipakai — supaya mata kuliah bernama sama tetapi
            // berkode lain tidak ikut tersambar.
            if ($kode !== '') {
                $q->whereNull('kode');
            }

            if ($mk = $q->first()) {
                if ($kode !== '' && ! $mk->kode) {
                    $mk->update(['kode' => $kode]);
                }

                return $mk;
            }
        }

        // 2. Hanya kode yang diketahui, namanya tidak ikut terkirim.
        if ($kode !== '' && $nama === '' && $mk = static::where('kode', $kode)->first()) {
            return $mk;
        }

        return static::create([
            'kode' => $kode !== '' ? $kode : null,
            'nama' => $nama !== '' ? $nama : $kode,
        ]);
    }
}
