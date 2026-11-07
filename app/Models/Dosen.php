<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dosen extends Model
{
    protected $table = 'dosens';

    protected $fillable = ['kode', 'nip', 'nama'];

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    /**
     * Cari menurut kode, lalu menurut nama; buat bila belum ada.
     *
     * Berkas jadwal hanya memuat kode dosen, sehingga kode itulah pengenal
     * utamanya. Nama lengkap boleh menyusul belakangan.
     */
    public static function temukanAtauBuat(?string $kode, ?string $nama = null, ?string $nip = null): ?self
    {
        $kode = trim((string) $kode);
        $nama = trim((string) $nama);
        $nip  = trim((string) $nip);

        if ($kode === '' && $nama === '' && $nip === '') {
            return null;
        }

        // Keterangan yang baru diisi dilengkapi ke baris yang sudah ada,
        // tanpa menimpa yang sudah terisi sebelumnya.
        $lengkapi = function (self $d) use ($kode, $nama, $nip) {
            $isi = [];

            if ($kode !== '' && ! $d->kode) { $isi['kode'] = $kode; }
            if ($nama !== '' && ! $d->nama) { $isi['nama'] = $nama; }
            if ($nip !== ''  && ! $d->nip)  { $isi['nip']  = $nip; }

            if ($isi) {
                $d->update($isi);
            }

            return $d;
        };

        if ($kode !== '' && $d = static::where('kode', $kode)->first()) {
            return $lengkapi($d);
        }

        if ($nip !== '' && $d = static::where('nip', $nip)->first()) {
            return $lengkapi($d);
        }

        if ($nama !== '' && $d = static::whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->first()) {
            return $lengkapi($d);
        }

        return static::create([
            'kode' => $kode !== '' ? $kode : null,
            'nama' => $nama !== '' ? $nama : null,
            'nip'  => $nip !== '' ? $nip : null,
        ]);
    }
}
