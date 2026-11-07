<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Zona waktu aplikasi.
         *
         * Proyek ini memakai kerangka ramping Laravel 13 yang tidak memuat
         * config/app.php — folder config/ hanya berisi jadwal.php dan
         * peraturan.php. Akibatnya APP_TIMEZONE di .env tidak pernah sampai
         * ke app.timezone, dan PHP tetap memakai UTC: tujuh jam di belakang
         * WIB. Gejalanya halus dan menyesatkan, karena jam besar di papan
         * lift tetap benar (dihitung peramban), sementara seluruh keputusan
         * sisi server — ruangan mana yang sedang dipakai, daftar ruang
         * kosong, penanda hari ini di Timeline — memakai jam yang salah.
         *
         * Dipasang di register(), bukan boot(), supaya sudah berlaku sebelum
         * provider lain sempat membaca waktu.
         *
         * Ditaruh di sini dan bukan di config/app.php buatan sendiri, karena
         * berkas config yang tidak lengkap berisiko menimpa nilai bawaan
         * framework seperti APP_KEY dan cipher. Di sini yang disetel hanya
         * zona waktunya.
         */
        $zona = env('APP_TIMEZONE') ?: 'Asia/Jakarta';

        config(['app.timezone' => $zona]);
        date_default_timezone_set($zona);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
