<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pemindahan di dalam pengajuan pergantian jadwal. Statusnya berdiri
 * sendiri supaya petugas bisa menyetujui pemindahan pekan pertama dan
 * menolak pekan kedua bila salah satunya bentrok.
 */
class PengajuanBaris extends Model
{
    protected $table = 'pengajuan_barises';

    protected $fillable = [
        'pengajuan_id', 'jadwal_id', 'ruangan_id', 'tanggal_asal', 'tanggal',
        'hari', 'menit_mulai', 'menit_selesai', 'jam_mulai', 'jam_selesai',
        'bentrok', 'catatan_bentrok', 'bentrok_dosen', 'catatan_dosen',
        'bentrok_kelas', 'catatan_kelas', 'status', 'catatan_petugas', 'diproses_pada',
    ];

    protected $casts = [
        'tanggal'         => 'date',
        'tanggal_asal'    => 'date',
        'bentrok'         => 'boolean',
        'bentrok_dosen'   => 'boolean',
        'bentrok_kelas'   => 'boolean',
        'catatan_bentrok' => 'array',
        'catatan_dosen'   => 'array',
        'catatan_kelas'   => 'array',
        'diproses_pada'   => 'datetime',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    /** Status apa pun yang masih mengikat: sudah disetujui atau menunggu. */
    public function scopeAktif(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->whereIn('status', ['menunggu', 'disetujui']);
    }

    /**
     * Pemindahan yang ujungnya masih di tanggal ini.
     *
     * Bila pertemuan 12 Oktober dipindah ke 13 Oktober, lalu yang 13 Oktober
     * dipindah lagi ke 20 Oktober, maka baris 12→13 sudah tidak berlaku:
     * tidak ada kuliah apa pun pada 13 Oktober, dan ruangannya harus bebas.
     * Tanpa saringan ini ruangan 13 Oktober tetap terkunci selamanya.
     */
    public function scopeBelumDipindahLagi(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('pengajuan_barises as lanjutan')
                ->whereColumn('lanjutan.jadwal_id', 'pengajuan_barises.jadwal_id')
                ->whereColumn('lanjutan.tanggal_asal', 'pengajuan_barises.tanggal')
                ->whereIn('lanjutan.status', ['menunggu', 'disetujui']);
        });
    }

    /**
     * Telusuri rantai pemindahan sampai ujungnya, lalu kembalikan baris
     * terakhir. Untuk 12→13→20, mencari dari 12 Oktober menghasilkan baris
     * 13→20, sehingga peringatan bisa menyebut tanggal yang benar-benar
     * berlaku sekarang, bukan tanggal antara yang sudah ditinggalkan.
     *
     * Rantai melingkar (12→13 lalu 13→12) tidak mungkin lolos pemeriksaan,
     * tetapi penjaga $lewat tetap dipasang supaya satu data aneh di basis
     * data tidak pernah membuat permintaan menggantung selamanya.
     */
    public static function rantaiPindah(int $jadwalId, string $tanggal): ?self
    {
        $terakhir = null;
        $kini     = $tanggal;
        $lewat    = [];

        while (! isset($lewat[$kini])) {
            $lewat[$kini] = true;

            $lanjut = static::with('pengajuan')
                ->where('jadwal_id', $jadwalId)
                ->whereDate('tanggal_asal', $kini)
                ->aktif()
                ->first();

            if (! $lanjut) {
                break;
            }

            $terakhir = $lanjut;
            $kini     = $lanjut->tanggal->toDateString();
        }

        return $terakhir;
    }

    public function getRentangJamAttribute(): string
    {
        return substr((string) $this->jam_mulai, 0, 5) . '–' . substr((string) $this->jam_selesai, 0, 5);
    }

    public function getAdaBentrokAttribute(): bool
    {
        return $this->bentrok || $this->bentrok_dosen || $this->bentrok_kelas;
    }

    public function getHasilPeriksaAttribute(): array
    {
        return [
            'Ruangan' => ['bentrok' => $this->bentrok, 'rincian' => $this->catatan_bentrok ?? []],
            'Dosen'   => ['bentrok' => $this->bentrok_dosen, 'rincian' => $this->catatan_dosen ?? []],
            'Kelas'   => ['bentrok' => $this->bentrok_kelas, 'rincian' => $this->catatan_kelas ?? []],
        ];
    }

    /** Durasi berbeda dari jadwal aslinya. */
    public function getDurasiBerbedaAttribute(): bool
    {
        return $this->jadwal
            && ($this->menit_selesai - $this->menit_mulai) !== $this->jadwal->durasi_menit;
    }
}
