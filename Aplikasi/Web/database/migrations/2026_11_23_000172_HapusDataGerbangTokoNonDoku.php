<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan pemilik produk (menggantikan D-19): seluruh tenant memakai DOKU untuk QRIS dinamis. Penyedia gerbang toko
 * lain (Midtrans, Xendit, Tripay, Duitku, iPaymu) dihapus dari kode, sehingga barisnya tidak boleh tersisa: nilai
 * `Penyedia` yang tidak lagi dikenal enum akan membuat pembacaan baris gagal.
 *
 * Hanya DATA yang dihapus (tabelnya tetap):
 * - `GerbangPembayaranTenant`: gerbang toko milik penyedia yang dihapus. Tenant yang terdampak perlu menyambungkan
 *   DOKU di Pembayaran › Gerbang QRIS; tagihan QRIS yang masih Menunggu tidak lagi bisa dicek ke gerbang lamanya dan
 *   berakhir Kedaluwarsa menurut aturan yang sudah ada.
 * - `KatalogGerbangPembayaran`: baris izin platform untuk penyedia yang dihapus.
 * - `KonfigurasiIntegrasi` jenis `GerbangPembayaran`: baris platform lama (sebelum v2.06, sudah diabaikan runtime) yang
 *   masih bernilai penyedia yang dihapus. Baris jenis `GerbangBilling` (`MidtransBilling`, tagihan langganan platform)
 *   TIDAK disentuh.
 *
 * Idempoten dan aman bila tabel kosong atau belum ada: penghapusan bersyarat nilai, bisa dijalankan ulang.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PENYEDIA_DIHAPUS = ['Midtrans', 'Xendit', 'Tripay', 'Duitku', 'Ipaymu'];

    public function up(): void
    {
        if (Schema::hasTable('GerbangPembayaranTenant')) {
            DB::table('GerbangPembayaranTenant')->whereIn('Penyedia', self::PENYEDIA_DIHAPUS)->delete();
        }

        if (Schema::hasTable('KatalogGerbangPembayaran')) {
            DB::table('KatalogGerbangPembayaran')->whereIn('Penyedia', self::PENYEDIA_DIHAPUS)->delete();
        }

        if (Schema::hasTable('KonfigurasiIntegrasi')) {
            DB::table('KonfigurasiIntegrasi')
                ->where('Jenis', 'GerbangPembayaran')
                ->whereIn('Penyedia', self::PENYEDIA_DIHAPUS)
                ->delete();
        }
    }

    /** Data yang sudah dihapus tidak bisa dipulihkan (kredensialnya milik tenant dan tidak dicadangkan di sini). */
    public function down(): void {}
};
