<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengajuan extends Model
{
    protected $table = 'pengajuans';

    protected $fillable = [
        'nomor', 'jenis', 'periode_id', 'nama', 'identitas', 'peran', 'unit', 'kontak',
        'tanggal', 'hari', 'menit_mulai', 'menit_selesai', 'jam_mulai', 'jam_selesai',
        'ruangan_id', 'jadwal_id', 'tanggal_awal', 'jumlah_peserta', 'keperluan',
        'bentrok', 'catatan_bentrok',
        'bentrok_dosen', 'catatan_dosen', 'bentrok_kelas', 'catatan_kelas',
        'status', 'catatan_petugas', 'diproses_oleh', 'diproses_pada',
    ];

    protected $casts = [
        'tanggal'         => 'date',
        'tanggal_asal'    => 'date',
        'tanggal_awal'    => 'date',
        'bentrok'         => 'boolean',
        'catatan_bentrok' => 'array',
        'bentrok_dosen'   => 'boolean',
        'catatan_dosen'   => 'array',
        'bentrok_kelas'   => 'boolean',
        'catatan_kelas'   => 'array',
        'diproses_pada'   => 'datetime',
    ];

    public const JENIS = [
        'ruangan'    => 'Reservasi Ruangan',
        'pergantian' => 'Reservasi Pergantian Jadwal',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    /** Baris pemindahan pada pengajuan pergantian jadwal. */
    public function baris(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PengajuanBaris::class)->orderBy('tanggal');
    }

    /** Ringkasan status seluruh baris: menunggu, sebagian, disetujui, ditolak. */
    public function getStatusGabunganAttribute(): string
    {
        if ($this->baris->isEmpty()) {
            return $this->status;
        }

        $set = $this->baris->pluck('status')->unique();

        if ($set->count() === 1) {
            return $set->first();
        }

        return $set->contains('menunggu') ? 'sebagian diproses' : 'sebagian disetujui';
    }

    public function scopeJenis(Builder $q, ?string $jenis): Builder
    {
        return $jenis ? $q->where('jenis', $jenis) : $q;
    }

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('status', $status) : $q;
    }

    /** Pengajuan yang punya temuan bentrok pada salah satu dari tiga sisi. */
    public function scopeAdaBentrok(Builder $q): Builder
    {
        return $q->where(fn (Builder $w) => $w->where('bentrok', true)
            ->orWhere('bentrok_dosen', true)
            ->orWhere('bentrok_kelas', true));
    }

    /** Nomor urut berformat RSV-000123 atau PGJ-000045. */
    public static function nomorBaru(string $jenis): string
    {
        $awalan = $jenis === 'ruangan' ? 'RSV' : 'PGJ';
        $urut   = static::where('jenis', $jenis)->count() + 1;

        return sprintf('%s-%06d', $awalan, $urut);
    }

    public function getRentangJamAttribute(): string
    {
        return substr((string) $this->jam_mulai, 0, 5) . '–' . substr((string) $this->jam_selesai, 0, 5);
    }

    public function getNamaHariAttribute(): string
    {
        return Jadwal::HARI[$this->hari] ?? '-';
    }

    /**
     * Durasi pengganti berbeda dari jadwal aslinya. Dihitung saat ditampilkan,
     * jadi tidak perlu kolom tambahan di basis data.
     */
    public function getDurasiBerbedaAttribute(): bool
    {
        if (! $this->jadwal) {
            return false;
        }

        return ($this->menit_selesai - $this->menit_mulai) !== $this->jadwal->durasi_menit;
    }

    public function getDurasiMenitAttribute(): int
    {
        return $this->menit_selesai - $this->menit_mulai;
    }

    /** Ada temuan bentrok apa pun — ruangan, dosen, atau kelas. */
    public function getAdaBentrokAttribute(): bool
    {
        return $this->bentrok || $this->bentrok_dosen || $this->bentrok_kelas;
    }

    /** Ringkasan pemeriksaan untuk ditampilkan sebagai tiga label. */
    public function getHasilPeriksaAttribute(): array
    {
        return [
            'Ruangan' => ['bentrok' => $this->bentrok, 'rincian' => $this->catatan_bentrok ?? []],
            'Dosen'   => ['bentrok' => $this->bentrok_dosen, 'rincian' => $this->catatan_dosen ?? []],
            'Kelas'   => ['bentrok' => $this->bentrok_kelas, 'rincian' => $this->catatan_kelas ?? []],
        ];
    }

    /** Ringkasan satu baris untuk dimuat pada pesan WhatsApp. */
    public function getRingkasanAttribute(): string
    {
        $baris = [
            'Ruangan  : ' . ($this->ruangan->kode ?? 'belum ditentukan'),
            'Tanggal  : ' . $this->tanggal->translatedFormat('l, j F Y'),
            'Jam      : ' . $this->rentang_jam,
            'Keperluan: ' . $this->keperluan,
        ];

        if ($this->jadwal) {
            array_unshift($baris, 'Mata kuliah: ' . ($this->jadwal->mataKuliah->nama ?? '-')
                . ' (' . $this->jadwal->kelas . ')');
        }

        return implode("\n", $baris);
    }

    /** Tautan WhatsApp berisi pemberitahuan keputusan. */
    public function tautanWa(?string $status = null): ?string
    {
        return \App\Support\Wa::tautan(
            $this->kontak,
            \App\Support\Wa::pesanKeputusan(
                $this->nama,
                $this->nomor,
                mb_strtolower($this->label_jenis),
                $this->ringkasan,
                $status ?? $this->status,
                $this->catatan_petugas
            )
        );
    }

    public function getLabelJenisAttribute(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }
}
