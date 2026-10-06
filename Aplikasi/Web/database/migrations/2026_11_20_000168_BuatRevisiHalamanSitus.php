<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-63 bagian 2: riwayat revisi halaman situs (salinan isi saat diterbitkan atau sebelum dipulihkan, supaya tidak ada
 * isi yang hilang) dan jadwal terbit (`JadwalTerbitPada`, dijalankan `situs:terbitkan-terjadwal`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RevisiHalamanSitus', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->char('Uuid', 26);
            $tabel->unsignedBigInteger('IdHalamanSitus');
            $tabel->string('Jenis', 20);
            $tabel->string('Judul', 150);
            $tabel->string('JudulSeo', 70)->nullable();
            $tabel->string('DeskripsiSeo', 170)->nullable();
            $tabel->char('UuidGambarOg', 26)->nullable();
            $tabel->json('Bagian');
            $tabel->unsignedBigInteger('IdPenggunaPengelola')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('Uuid', 'UniqRevisiHalamanSitusUuid');
            $tabel->index(['IdHalamanSitus', 'Id'], 'IdxRevisiHalamanSitusHalaman');
            $tabel->foreign('IdHalamanSitus', 'FkRevisiHalamanSitusHalaman')->references('Id')->on('HalamanSitus')->cascadeOnDelete();
        });

        Schema::table('HalamanSitus', function (Blueprint $tabel): void {
            $tabel->timestamp('JadwalTerbitPada')->nullable()->after('DiterbitkanPada');
            $tabel->unsignedBigInteger('IdPenggunaPengelolaPenjadwal')->nullable()->after('JadwalTerbitPada');
            $tabel->index('JadwalTerbitPada', 'IdxHalamanSitusJadwalTerbit');
        });
    }

    public function down(): void
    {
        Schema::table('HalamanSitus', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxHalamanSitusJadwalTerbit');
            $tabel->dropColumn(['JadwalTerbitPada', 'IdPenggunaPengelolaPenjadwal']);
        });
        Schema::dropIfExists('RevisiHalamanSitus');
    }
};
