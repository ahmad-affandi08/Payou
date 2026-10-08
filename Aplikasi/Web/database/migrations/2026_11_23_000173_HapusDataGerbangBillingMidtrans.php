<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan pemilik produk: seluruh pembayaran pindah ke DOKU, termasuk tagihan langganan Payoung ke tenant. Penyedia
 * integrasi platform `MidtransBilling` (jenis `GerbangBilling`) dihapus dari kode dan diganti `DokuBilling`, sehingga
 * barisnya tidak boleh tersisa: nilai `Penyedia` yang tidak lagi dikenal enum akan membuat pembacaan baris gagal.
 *
 * Hanya DATA yang dihapus (tabelnya tetap). Kredensial DOKU platform harus diisi ulang di konsol pengelola
 * (Integrasi › Gerbang pembayaran tagihan langganan); kredensial Midtrans lama tidak bisa dipakai ulang. Pembayaran
 * langganan lama yang masih `Menunggu` dengan nomor pesanan Midtrans tidak diubah di sini: rekonsiliasi
 * `tagihan:rekonsiliasi-gerbang` menanyakannya ke DOKU, tidak menemukannya, dan menutupnya sebagai kedaluwarsa setelah
 * batas bayar, sehingga tenant bisa membayar ulang lewat DOKU.
 *
 * Idempoten dan aman bila tabel kosong atau belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('KonfigurasiIntegrasi')) {
            DB::table('KonfigurasiIntegrasi')
                ->where('Jenis', 'GerbangBilling')
                ->where('Penyedia', 'MidtransBilling')
                ->delete();
        }
    }

    /** Data yang sudah dihapus tidak bisa dipulihkan (kredensialnya terenkripsi dan tidak dicadangkan di sini). */
    public function down(): void {}
};
