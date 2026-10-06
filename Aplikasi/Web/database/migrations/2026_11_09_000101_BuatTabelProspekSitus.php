<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Situs pemasaran bagian B (§13.9): prospek dari formulir kontak/minta demo di situs `payoung.id` (data platform, bukan
 * tenant). Nomor HP & email disimpan terenkripsi (UU 27/2022 PDP); `SidikIp` = HMAC alamat IP untuk membatasi spam tanpa
 * menyimpan IP mentah. Prospek lebih dari 24 bulan dihapus otomatis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProspekSitus', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Jenis', 20);
            $tabel->string('Nama', 100);
            $tabel->string('NamaUsaha', 150)->nullable();
            $tabel->text('NoHp');
            $tabel->string('SidikNoHp', 64);
            $tabel->text('Email')->nullable();
            $tabel->string('JenisUsaha', 60)->nullable();
            $tabel->string('Kota', 100)->nullable();
            $tabel->string('Pesan', 1000)->nullable();
            $tabel->string('HalamanAsal', 200)->nullable();
            $tabel->string('Status', 20);
            $tabel->string('Catatan', 1000)->nullable();
            $tabel->foreignId('IdPenggunaPengelolaPenangan')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkProspekSitusIdPenangan')->nullOnDelete();
            $tabel->timestamp('DitanganiPada')->nullable();
            $tabel->timestamp('PersetujuanPada');
            $tabel->string('SidikIp', 64);
            $tabel->WaktuStandar();
            $tabel->index(['Status', 'DibuatPada'], 'IdxProspekSitusStatusDibuatPada');
            $tabel->index(['SidikNoHp', 'DibuatPada'], 'IdxProspekSitusSidikNoHpDibuatPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProspekSitus');
    }
};
