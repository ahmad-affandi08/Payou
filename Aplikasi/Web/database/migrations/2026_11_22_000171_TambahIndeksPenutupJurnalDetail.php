<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit kinerja skala besar (D-81, gelombang 2): Neraca, Neraca Saldo, dan saldo awal Buku Besar menjumlahkan seluruh
 * riwayat `JurnalDetail` tenant (`WHERE Tanggal <= ? GROUP BY IdAkun`). Indeks lama `(IdTenant, IdAkun, Tanggal)` tidak
 * memuat kolom nominal, jadi setiap baris dibaca dari tabel. Indeks penutup ini membuat kueri itu selesai di indeks saja.
 *
 * Terukur pada 1 juta baris jurnal satu tenant: 5,4 detik menjadi 0,53 detik (tambahan ruang indeks sekitar 49 MB per juta
 * baris). Indeks lama dibiarkan (dipakai kueri lain); menghapus yang mubazir menunggu rilis berikutnya (expand → contract).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('JurnalDetail', function (Blueprint $tabel): void {
            $tabel->index(['IdTenant', 'IdAkun', 'Tanggal', 'IdOutlet', 'Debit', 'Kredit'], 'IdxJurnalDetailPenutupSaldoAkun');
        });
    }

    public function down(): void
    {
        Schema::table('JurnalDetail', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxJurnalDetailPenutupSaldoAkun');
        });
    }
};
