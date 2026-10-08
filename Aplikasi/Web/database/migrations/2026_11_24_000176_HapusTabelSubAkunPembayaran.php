<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendekatan "sub account" wallet DOKU (migrasi 000174) salah arah: Sub Account bukan onboarding merchant. Gantinya
 * pendaftaran merchant lewat DOKU Partner API (`PendaftaranMerchantPembayaran`, migrasi 000175).
 *
 * Tabel `SubAkunPembayaran` baru dibuat di rilis sebelumnya dan belum pernah berisi data yang dipakai, jadi dihapus
 * langsung (tanpa tahap expand → contract). Migrasi 000174 sengaja tidak diubah karena sudah di-merge. Idempoten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('SubAkunPembayaran');
    }

    /** Membentuk ulang tabel kosong seperti di migrasi 000174 supaya rollback tidak merusak urutan migrasi. */
    public function down(): void
    {
        if (Schema::hasTable('SubAkunPembayaran')) {
            return;
        }

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
            $tabel->unique(['Penyedia', 'IdSubAkun'], 'UniqSubAkunPembayaranPenyediaIdSubAkun');
        });
    }
};
