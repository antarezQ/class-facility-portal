<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'admin' = petugas, boleh mengunggah data dan melihat seluruh modul.
            // 'guest' = civitas, hanya melihat jadwal dan ketersediaan ruangan.
            $table->string('peran', 10)->default('guest')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('peran');
        });
    }
};
