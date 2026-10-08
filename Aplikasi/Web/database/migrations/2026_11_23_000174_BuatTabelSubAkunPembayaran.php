<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub account pembayaran per tenant (wadah untuk rute dana QRIS ke rekening tenant; tahap 3 pindah ke DOKU).
 *
 * Satu baris per (tenant, penyedia). `IdSubAkun` = pengenal sub account di penyedia (DOKU: `account.id`), kosong
 * selama pembuatan belum berhasil. Tidak menyimpan rahasia apa pun: kredensial akun induk ada di integrasi platform
 * `DokuBilling` (terenkripsi), bukan di sini. `PesanGalat` sudah disaring dari rahasia sebelum disimpan.
 * `DibuatOleh`/`DiubahOleh` = pengelola yang memicu pembuatan / percobaan ulang (jejak lengkap ada di LogAuditPengelola).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SubAkunPembayaran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkSubAkunPembayaranIdTenant')->restrictOnDelete();
            $tabel->string('Penyedia', 20);
            $tabel->string('IdSubAkun', 100)->nullable();
            $tabel->string('Status', 20)->default('Menunggu');
            $tabel->string('PesanGalat', 300)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkSubAkunPembayaranDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkSubAkunPembayaranDiubahOleh')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Penyedia'], 'UniqSubAkunPembayaranIdTenantPenyedia');
            // Satu sub account di penyedia tidak boleh menempel ke dua tenant (kolom kosong boleh berulang).
            $tabel->unique(['Penyedia', 'IdSubAkun'], 'UniqSubAkunPembayaranPenyediaIdSubAkun');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SubAkunPembayaran');
    }
};
