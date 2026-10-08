<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran merchant pembayaran per tenant lewat DOKU Partner API (KYB): tenant hanya memberi foto KTP, swafoto,
 * foto tempat usaha, dan rekening; Payoung (berstatus Partner di DOKU) mendaftarkannya sebagai merchant/brand.
 *
 * Satu baris per (tenant, penyedia). Data sensitif (`Nik`, `NomorRekening`, `KunciBersama`) disimpan terenkripsi lewat
 * cast model, jadi kolomnya `text`. Foto TIDAK disimpan permanen: `PathKtp`/`PathSwafoto`/`PathBuktiUsaha` hanya
 * menunjuk berkas sementara di disk privat dan dikosongkan begitu semua berkas terunggah ke DOKU (atau gagal permanen,
 * batal, atau lewat 24 jam). `IdFile*` = id berkas hasil Upload File DOKU (bukan rahasia).
 * `TokenCallback` dipasang di `callback_url` yang dikirim ke DOKU supaya webhook KYB bisa dikaitkan ke tenant.
 * `IdPedagangQris`/`IdTerminalQris` hanya penampung (diisi manual pengelola sampai DOKU menjawab); belum dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('PendaftaranMerchantPembayaran')) {
            return;
        }

        Schema::create('PendaftaranMerchantPembayaran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPendaftaranMerchantPembayaranIdTenant')->restrictOnDelete();
            $tabel->string('Penyedia', 20)->default('Doku');
            $tabel->string('Status', 20)->default('Draf');

            $tabel->string('NamaPemilik', 150)->nullable();
            $tabel->text('Nik')->nullable();
            $tabel->string('Email', 150)->nullable();
            $tabel->string('NomorHp', 20)->nullable();
            $tabel->string('NamaUsaha', 150)->nullable();
            $tabel->string('AlamatUsaha', 300)->nullable();
            $tabel->string('KategoriUsaha', 30)->nullable();
            $tabel->foreignId('IdReferensiBank')->nullable()->constrained('ReferensiBank', 'Id', 'FkPendaftaranMerchantPembayaranIdReferensiBank')->restrictOnDelete();
            $tabel->string('NamaPemilikRekening', 150)->nullable();
            $tabel->text('NomorRekening')->nullable();

            $tabel->string('PathKtp', 200)->nullable();
            $tabel->string('PathSwafoto', 200)->nullable();
            $tabel->string('PathBuktiUsaha', 200)->nullable();
            $tabel->timestamp('BerkasDiunggahPada')->nullable();
            $tabel->string('IdFileKtp', 100)->nullable();
            $tabel->string('IdFileSwafoto', 100)->nullable();
            $tabel->string('IdFileBuktiUsaha', 100)->nullable();

            $tabel->string('IdBisnisDoku', 100)->nullable();
            $tabel->string('IdBrandDoku', 100)->nullable();
            $tabel->text('KunciBersama')->nullable();
            $tabel->string('StatusDoku', 30)->nullable();
            $tabel->string('PesanGalat', 300)->nullable();
            $tabel->string('AlasanPenolakan', 300)->nullable();
            $tabel->string('TokenCallback', 60)->nullable();

            $tabel->timestamp('DikirimPada')->nullable();
            $tabel->timestamp('DisetujuiPada')->nullable();
            $tabel->timestamp('DiperiksaPada')->nullable();
            $tabel->timestamp('CallbackDiterimaPada')->nullable();

            // Penampung: diisi manual oleh pengelola sampai DOKU menjawab soal aktivasi QRIS per Brand. Belum dipakai di mana pun.
            $tabel->string('IdPedagangQris', 100)->nullable();
            $tabel->string('IdTerminalQris', 100)->nullable();

            $tabel->WaktuStandar();

            $tabel->unique(['IdTenant', 'Penyedia'], 'UniqPendaftaranMerchantPembayaranIdTenantPenyedia');
            $tabel->unique('TokenCallback', 'UniqPendaftaranMerchantPembayaranTokenCallback');
            // Penyapu terjadwal lintas tenant (tanpa IdTenant di WHERE): satu-satunya pengecualian indeks IdTenant-dulu, seperti D-81.
            $tabel->index(['Status', 'DiperiksaPada', 'IdTenant'], 'IdxPendaftaranMerchantPembayaranSapuStatusDiperiksaTenant');
            $tabel->index(['BerkasDiunggahPada', 'IdTenant'], 'IdxPendaftaranMerchantPembayaranSapuBerkasTenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PendaftaranMerchantPembayaran');
    }
};
