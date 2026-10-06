<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * X7 Open API v1 bagian 1 (PRD §16.1 lapisan Publik): token API milik tenant untuk integrasi pihak ketiga. Token asli
 * (`payoung_{IdTenant}_{rahasia}`) hanya ditampilkan sekali saat dibuat; yang disimpan hanya SHA-256 rahasianya dan
 * `Prefiks` untuk dikenali di daftar. `Cakupan` = daftar scope (`produk:baca`, ...). Dicabut = `DicabutPada` terisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TokenApiTenant', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTokenApiTenantIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->string('Prefiks', 24);
            $tabel->char('HashToken', 64);
            $tabel->json('Cakupan');
            $tabel->foreignId('DibuatOleh')->constrained('Pengguna', 'Id', 'FkTokenApiTenantDibuatOleh')->restrictOnDelete();
            $tabel->timestamp('TerakhirDipakaiPada')->nullable();
            $tabel->timestamp('KedaluwarsaPada')->nullable();
            $tabel->timestamp('DicabutPada')->nullable();
            $tabel->foreignId('DicabutOleh')->nullable()->constrained('Pengguna', 'Id', 'FkTokenApiTenantDicabutOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique('HashToken', 'UnqTokenApiTenantHashToken');
            $tabel->index(['IdTenant', 'DicabutPada'], 'IdxTokenApiTenantIdTenantDicabutPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TokenApiTenant');
    }
};
