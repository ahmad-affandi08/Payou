<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-01 BR-P01.2 (D-42): perangkat tepercaya konsol. Setelah kode 2FA benar dan anggota mencentang "Percayai
 * perangkat ini", browser itu menerima cookie berisi token acak; login berikutnya di browser yang sama cukup kata
 * sandi selama 90 hari. Hanya hash token yang disimpan. Dicabut otomatis saat kata sandi diganti, 2FA diaktifkan
 * ulang, atau akun dinonaktifkan; bisa dicabut manual oleh pemilik akun atau Super Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PerangkatTepercayaPengelola', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdPenggunaPengelola')
                ->constrained('PenggunaPengelola', 'Id', 'FkPerangkatTepercayaPengelolaIdPenggunaPengelola')
                ->cascadeOnDelete();
            $tabel->char('HashToken', 64)->unique('UniqPerangkatTepercayaPengelolaHashToken');
            $tabel->string('Keterangan', 120);
            $tabel->string('AlamatIp', 45)->nullable();
            $tabel->timestamp('TerakhirDipakaiPada')->nullable();
            $tabel->timestamp('BerlakuSampai');
            $tabel->timestamp('DicabutPada')->nullable();
            $tabel->WaktuStandar();

            $tabel->index(['IdPenggunaPengelola', 'DicabutPada'], 'IdxPerangkatTepercayaPengelolaPengguna');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PerangkatTepercayaPengelola');
    }
};
