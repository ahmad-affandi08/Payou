<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-49 (F-19): pembelian add-on mandiri.
 *
 * - `LanggananAddon`: add-on yang dimiliki tenant (satu baris per tenant per add-on). Aktif bila
 *   `MulaiPada ≤ sekarang < SelesaiPada`. `SelesaiPada` mengikuti akhir periode langganan paket; perpanjangan paket
 *   (tagihan Perpanjangan) menagih add-on ber-`PerpanjangOtomatis` dan memperpanjang `SelesaiPada`.
 *   `BerhentiPada` terisi saat pemilik berhenti berlangganan: tetap aktif sampai `SelesaiPada`, tidak ditagih lagi.
 * - `TagihanLanggananAddon`: rincian add-on pada satu tagihan (snapshot nama & harga). Tagihan `Addon` berisi satu baris
 *   prorata; tagihan `Perpanjangan` memuat satu baris per add-on yang diperpanjang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LanggananAddon', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkLanggananAddonIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdAddon')->constrained('Addon', 'Id', 'FkLanggananAddonIdAddon')->restrictOnDelete();
            $tabel->unsignedSmallInteger('Jumlah')->default(1);
            $tabel->timestamp('MulaiPada');
            $tabel->timestamp('SelesaiPada');
            $tabel->boolean('PerpanjangOtomatis')->default(true);
            $tabel->timestamp('BerhentiPada')->nullable();
            $tabel->foreignId('IdTagihanLanggananAsal')->nullable()->constrained('TagihanLangganan', 'Id', 'FkLanggananAddonIdTagihanAsal')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdAddon'], 'UniqLanggananAddonTenantAddon');
            $tabel->index(['IdTenant', 'SelesaiPada'], 'IdxLanggananAddonTenantSelesai');
        });

        Schema::create('TagihanLanggananAddon', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTagihanLanggananAddonIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdTagihanLangganan')->constrained('TagihanLangganan', 'Id', 'FkTagihanLanggananAddonIdTagihan')->restrictOnDelete();
            $tabel->foreignId('IdAddon')->constrained('Addon', 'Id', 'FkTagihanLanggananAddonIdAddon')->restrictOnDelete();
            $tabel->string('KodeAddon', 30);
            $tabel->string('NamaAddon', 150);
            $tabel->unsignedSmallInteger('Jumlah')->default(1);
            $tabel->decimal('HargaBulanan', 18, 2);
            $tabel->unsignedTinyInteger('JumlahBulan');
            // Prorata (pembelian di tengah periode): hari yang ditagih dari total hari periode berjalan.
            $tabel->boolean('Prorata')->default(false);
            $tabel->unsignedSmallInteger('HariDitagih')->nullable();
            $tabel->unsignedSmallInteger('HariPeriode')->nullable();
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->timestamp('MulaiPada');
            $tabel->timestamp('SelesaiPada');
            $tabel->WaktuStandar();
            $tabel->index(['IdTagihanLangganan'], 'IdxTagihanLanggananAddonTagihan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TagihanLanggananAddon');
        Schema::dropIfExists('LanggananAddon');
    }
};
