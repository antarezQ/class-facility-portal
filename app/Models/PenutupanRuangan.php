<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ruangan yang ditutup sementara — perbaikan AC, pengecatan, dan sebagainya.
 * Selama rentang tanggalnya, ruangan hilang dari pencarian dan tidak bisa
 * direservasi.
 */
class PenutupanRuangan extends Model
{
    protected $table = 'penutupan_ruangans';

    protected $fillable = [
        'ruangan_id', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'catatan', 'dibuat_oleh',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    /** Penutupan yang mencakup tanggal tertentu. */
    public function scopePadaTanggal(Builder $q, Carbon $tanggal): Builder
    {
        return $q->whereDate('tanggal_mulai', '<=', $tanggal->toDateString())
            ->whereDate('tanggal_selesai', '>=', $tanggal->toDateString());
    }

    /** Penutupan yang beririsan dengan satu rentang tanggal. */
    public function scopeBeririsan(Builder $q, Carbon $mulai, Carbon $selesai): Builder
    {
        return $q->whereDate('tanggal_mulai', '<=', $selesai->toDateString())
            ->whereDate('tanggal_selesai', '>=', $mulai->toDateString());
    }

    public function scopeBelumLewat(Builder $q): Builder
    {
        return $q->whereDate('tanggal_selesai', '>=', now()->toDateString());
    }

    public function getRentangTeksAttribute(): string
    {
        if ($this->tanggal_mulai->isSameDay($this->tanggal_selesai)) {
            return $this->tanggal_mulai->translatedFormat('j F Y');
        }

        return $this->tanggal_mulai->translatedFormat('j M Y')
            . ' – ' . $this->tanggal_selesai->translatedFormat('j M Y');
    }

    public function getSedangBerlakuAttribute(): bool
    {
        return now()->betweenIncluded($this->tanggal_mulai, $this->tanggal_selesai->endOfDay());
    }
}
