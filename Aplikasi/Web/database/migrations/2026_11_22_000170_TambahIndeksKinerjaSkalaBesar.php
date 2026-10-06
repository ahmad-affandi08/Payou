<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit kinerja skala besar (target 100.000 pengguna): indeks komposit untuk kueri yang sudah ada tetapi tidak
 * terlayani indeks. Hanya menambah indeks (tanpa mengubah kolom atau perilaku); semua diawali `IdTenant` karena scope
 * `MilikTenant` menambahkannya ke setiap kueri.
 *
 * - `PerluTinjauan` (Kotak Tindakan & Beranda): indeks lama `(IdTenant, Status, PerluTinjauan)` tidak terpakai bila
 *   kueri hanya menyaring `PerluTinjauan`; ReturPenjualan, IsiDeposit, dan TagihanQris belum punya indeks sama sekali.
 * - Pesanan online & tagihan QRIS: penyapu terjadwal dan Kotak Tindakan menyaring `Status` tanpa `IdOutlet`.
 * - `HashIp` pesanan online: pembatas spam di endpoint publik menghitung per IP.
 * - LogAudit: filter rentang waktu dan kalibrasi wajah (`Peristiwa` + `DibuatPada`).
 * - KunciIdempotensi: pemangkasan per perangkat dijalankan di setiap tulis POS.
 * - KirimanWebhook: pembersihan retensi per tenant memakai `DibuatPada`.
 * - PenjualanPembayaran: pencairan dana non-tunai mencari per metode & waktu bayar.
 * - Penyapu terjadwal (`KonteksPengelola::IdTenantDenganPekerjaan`): satu kueri lintas tenant per putaran untuk mencari
 *   tenant yang punya pekerjaan. Kueri itu tidak menyebut `IdTenant` di WHERE, jadi indeks `IdTenant`-dulu tidak
 *   terpakai; indeks berikut sengaja diawali kolom status/waktu (satu-satunya pengecualian aturan indeks `IdTenant`-dulu).
 */
return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: list<string>, 2: string}>
     */
    private const INDEKS = [
        ['Penjualan', ['IdTenant', 'PerluTinjauan', 'IdOutlet'], 'IdxPenjualanIdTenantPerluTinjauanIdOutlet'],
        ['ReturPenjualan', ['IdTenant', 'PerluTinjauan', 'IdOutlet'], 'IdxReturPenjualanIdTenantPerluTinjauanOutlet'],
        ['IsiDeposit', ['IdTenant', 'PerluTinjauan', 'IdOutlet'], 'IdxIsiDepositIdTenantPerluTinjauanIdOutlet'],
        ['TagihanQris', ['IdTenant', 'PerluTinjauan', 'IdOutlet'], 'IdxTagihanQrisIdTenantPerluTinjauanIdOutlet'],
        ['TagihanQris', ['IdTenant', 'Status', 'KedaluwarsaPada'], 'IdxTagihanQrisIdTenantStatusKedaluwarsa'],
        ['PesananOnline', ['IdTenant', 'Status', 'DibuatPada'], 'IdxPesananOnlineIdTenantStatusDibuat'],
        ['PesananOnline', ['IdTenant', 'HashIp', 'Status'], 'IdxPesananOnlineIdTenantHashIpStatus'],
        ['PesananOnline', ['IdTenant', 'IdOutlet', 'Status', 'DibayarPada'], 'IdxPesananOnlineIdTenantOutletStatusBayar'],
        ['LogAudit', ['IdTenant', 'DibuatPada'], 'IdxLogAuditIdTenantDibuatPada'],
        ['LogAudit', ['IdTenant', 'Peristiwa', 'DibuatPada'], 'IdxLogAuditIdTenantPeristiwaDibuatPada'],
        ['KunciIdempotensi', ['IdTenant', 'IdPerangkat', 'KedaluwarsaPada'], 'IdxKunciIdempotensiTenantPerangkatKedaluwarsa'],
        ['KirimanWebhook', ['IdTenant', 'DibuatPada'], 'IdxKirimanWebhookIdTenantDibuatPada'],
        ['PenjualanPembayaran', ['IdTenant', 'IdMetodePembayaran', 'DibayarPada'], 'IdxPenjualanPembayaranTenantMetodeDibayar'],
        ['KirimanWebhook', ['Status', 'BerikutnyaPada', 'IdTenant'], 'IdxKirimanWebhookSapuStatusBerikutnyaTenant'],
        ['KirimanWebhook', ['Status', 'DibuatPada', 'IdTenant'], 'IdxKirimanWebhookSapuStatusDibuatTenant'],
        ['PesananOnline', ['Status', 'DibayarPada', 'IdTenant'], 'IdxPesananOnlineSapuStatusDibayarTenant'],
        ['TagihanQris', ['Status', 'KedaluwarsaPada', 'IdTenant'], 'IdxTagihanQrisSapuStatusKedaluwarsaTenant'],
        ['KampanyePesan', ['Status', 'DijadwalkanPada', 'IdTenant'], 'IdxKampanyePesanSapuStatusJadwalTenant'],
    ];

    public function up(): void
    {
        foreach (self::INDEKS as [$tabel, $kolom, $nama]) {
            Schema::table($tabel, function (Blueprint $blueprint) use ($kolom, $nama): void {
                $blueprint->index($kolom, $nama);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEKS) as [$tabel, , $nama]) {
            Schema::table($tabel, function (Blueprint $blueprint) use ($nama): void {
                $blueprint->dropIndex($nama);
            });
        }
    }
};
