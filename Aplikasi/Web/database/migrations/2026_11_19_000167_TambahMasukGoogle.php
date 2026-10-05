<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-57 Masuk dengan Google: akun pengguna boleh ditautkan ke satu akun Google (`GoogleSub` = klaim `sub` token ID,
 * pengenal tetap Google, bukan email). `KataSandiOtomatis` menandai kata sandi acak yang dibuat sistem (pendaftaran
 * lewat Google, atau kata sandi lama yang dianggap tidak tepercaya saat tautan pertama); akun seperti itu baru boleh
 * melepas Google setelah mengatur kata sandi sendiri. `TokenAksesPengguna.MasukGoogle` menandai token Aplikasi Owner
 * yang diterbitkan lewat Google, yang menggantikan 2FA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Pengguna', function (Blueprint $tabel): void {
            $tabel->string('GoogleSub', 64)->nullable()->unique('UniqPenggunaGoogleSub')->after('Email');
            $tabel->timestamp('GoogleDitautkanPada')->nullable()->after('GoogleSub');
            $tabel->boolean('KataSandiOtomatis')->default(false)->after('WajibGantiKataSandi');
        });

        Schema::table('TokenAksesPengguna', function (Blueprint $tabel): void {
            $tabel->boolean('MasukGoogle')->default(false)->after('Kemampuan');
        });
    }

    public function down(): void
    {
        Schema::table('TokenAksesPengguna', function (Blueprint $tabel): void {
            $tabel->dropColumn('MasukGoogle');
        });

        Schema::table('Pengguna', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqPenggunaGoogleSub');
            $tabel->dropColumn(['GoogleSub', 'GoogleDitautkanPada', 'KataSandiOtomatis']);
        });
    }
};
