<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan bahasa tampilan: ?lang= lalu cookie, dengan bawaan English.
 */
class AturBahasa
{
    private const TERSEDIA = ['en', 'id'];

    private const COOKIE = 'bahasa';

    private const SATU_TAHUN = 525600;

    public function handle(Request $request, Closure $next): Response
    {
        $pilihan = $request->query('lang');

        if (in_array($pilihan, self::TERSEDIA, true)) {
            Cookie::queue(self::COOKIE, $pilihan, self::SATU_TAHUN);
        } else {
            $pilihan = $request->cookie(self::COOKIE);
        }

        $bahasa = in_array($pilihan, self::TERSEDIA, true) ? $pilihan : 'en';

        app()->setLocale($bahasa);
        Carbon::setLocale($bahasa);

        return $next($request);
    }
}
