<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 bagian 4: kios pesan sendiri di web (layar sentuh di outlet, seperti kios restoran cepat saji).
 * - `Outlet.KiosAktif` (sakelar, bawaan mati) dan `Outlet.TokenKios` (token acak 32 karakter di URL kios, null sampai
 *   pertama kali diaktifkan; "Buat ulang tautan" menggantinya sehingga tablet lama tidak berlaku lagi).
 * - `PesananOnline.Sumber` (`Web` = toko online, `Kios` = kios outlet), `JenisSantap` (makan di tempat / bawa pulang,
 *   hanya kios) dan `NomorAntrian` (urut harian per outlet, hanya kios). Pesanan kios memakai jalur pesanan online yang
 *   sudah ada, sehingga muncul di aplikasi Kasir, bisa ditagih, dan jurnalnya sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->boolean('KiosAktif')->default(false)->after('TokoOnlineAktif');
            $tabel->string('TokenKios', 32)->nullable()->after('KiosAktif')->unique('UniqOutletTokenKios');
        });

        Schema::table('PesananOnline', function (Blueprint $tabel): void {
            $tabel->string('Sumber', 10)->default('Web')->after('Nomor');
            $tabel->string('JenisSantap', 20)->nullable()->after('Sumber');
            $tabel->unsignedSmallInteger('NomorAntrian')->nullable()->after('JenisSantap');
            $tabel->index(['IdTenant', 'IdOutlet', 'Sumber', 'DibuatPada'], 'IdxPesananOnlineIdTenantOutletSumberDibuat');
        });
    }

    public function down(): void
    {
        Schema::table('PesananOnline', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxPesananOnlineIdTenantOutletSumberDibuat');
            $tabel->dropColumn(['Sumber', 'JenisSantap', 'NomorAntrian']);
        });

        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropUnique('UniqOutletTokenKios');
            $tabel->dropColumn(['KiosAktif', 'TokenKios']);
        });
    }
};
