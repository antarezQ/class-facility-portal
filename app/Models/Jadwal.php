<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jadwal extends Model
{
    protected $fillable = [
        'periode_id', 'gedung_id', 'ruangan_id', 'dosen_id', 'mata_kuliah_id',
        'berlaku_mulai', 'berlaku_sampai', 'induk_id',
        'hari', 'menit_mulai', 'menit_selesai', 'jam_mulai', 'jam_selesai',
        'durasi_menit', 'kelas', 'prodi', 'jml_mhs', 'overtime',
    ];

    protected $casts = [
        'berlaku_mulai'  => 'date',
        'berlaku_sampai' => 'date','overtime' => 'boolean'];

    public const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function gedung(): BelongsTo
    {
        return $this->belongsTo(Gedung::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    /*
     * Nama scope sengaja diberi awalan "untuk" agar tidak bentrok dengan
     * nama relasi periode(), gedung(), dan atribut hari. Bila namanya sama,
     * PHP menemukan method relasi lebih dulu saat dipanggil secara statis
     * dan melempar "Non-static method cannot be called statically".
     */
    public function scopeUntukPeriode(Builder $q, ?int $periodeId): Builder
    {
        return $periodeId ? $q->where('periode_id', $periodeId) : $q;
    }

    public function scopeUntukGedung(Builder $q, ?int $gedungId): Builder
    {
        return $gedungId ? $q->where('gedung_id', $gedungId) : $q;
    }

    /**
     * Hanya baris yang berlaku pada tanggal itu. Satu mata kuliah bisa punya
     * beberapa baris hasil perpindahan permanen, tetapi tidak pernah dua
     * baris aktif bersamaan — sehingga analitik tidak menghitung ganda.
     */
    public function scopeBerlakuPada(Builder $q, ?Carbon $tanggal = null): Builder
    {
        $t = ($tanggal ?? now())->toDateString();

        return $q->where(fn (Builder $w) => $w->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $t))
            ->where(fn (Builder $w) => $w->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $t));
    }

    /** Baris yang sudah ditutup karena digantikan perpindahan permanen. */
    public function scopeSudahLewat(Builder $q, ?Carbon $tanggal = null): Builder
    {
        return $q->whereNotNull('berlaku_sampai')
            ->whereDate('berlaku_sampai', '<', ($tanggal ?? now())->toDateString());
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'induk_id');
    }

    public function turunan()
    {
        return $this->hasMany(Jadwal::class, 'induk_id');
    }

    /** Baris ini hasil perpindahan permanen, bukan dari berkas impor. */
    public function getHasilPindahAttribute(): bool
    {
        return $this->induk_id !== null;
    }

    public function scopeUntukHari(Builder $q, ?int $hari): Builder
    {
        return $hari === null ? $q : $q->where('hari', $hari);
    }

    /**
     * Pencarian bebas pada mata kuliah, dosen, kelas, prodi, dan ruangan.
     */
    public function scopeCari(Builder $q, ?string $kata): Builder
    {
        if (! $kata) {
            return $q;
        }
        $k = '%' . str_replace('%', '\%', $kata) . '%';

        return $q->where(function (Builder $w) use ($k) {
            $w->where('kelas', 'like', $k)
              ->orWhere('prodi', 'like', $k)
              ->orWhereHas('mataKuliah', fn (Builder $m) => $m->where('nama', 'like', $k)->orWhere('kode', 'like', $k))
              ->orWhereHas('dosen', fn (Builder $d) => $d->where('kode', 'like', $k)->orWhere('nip', 'like', $k)->orWhere('nama', 'like', $k))
              ->orWhereHas('ruangan', fn (Builder $r) => $r->where('kode', 'like', $k));
        });
    }

    public function getNamaHariAttribute(): string
    {
        return self::HARI[$this->hari] ?? '-';
    }

    public function getDurasiJamAttribute(): float
    {
        return round($this->durasi_menit / 60, 2);
    }

    public function getRentangJamAttribute(): string
    {
        return substr((string) $this->jam_mulai, 0, 5) . '–' . substr((string) $this->jam_selesai, 0, 5);
    }
}
