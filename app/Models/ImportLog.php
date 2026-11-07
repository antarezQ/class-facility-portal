<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'nama_berkas', 'periode_id', 'baris_terbaca', 'baris_ditolak',
        'jumlah_overtime', 'jumlah_bentrok', 'catatan', 'user_id',
    ];

    protected $casts = ['catatan' => 'array'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }
}
