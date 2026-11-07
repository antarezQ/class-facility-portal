<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gedung extends Model
{
    protected $fillable = ['nama', 'kode_pendek'];

    public function ruangans(): HasMany
    {
        return $this->hasMany(Ruangan::class);
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    /**
     * "A-GEDUNG UTARA" -> "A", "Building B" -> "Building".
     */
    public static function pendekkan(string $nama): string
    {
        if (preg_match('/^([A-Z]{2,4}\d?)\s*-/', $nama, $m)) {
            return $m[1];
        }

        return strtok($nama, ' ') ?: $nama;
    }
}
