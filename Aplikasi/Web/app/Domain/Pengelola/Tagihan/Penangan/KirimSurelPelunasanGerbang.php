<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Penangan;

use App\Domain\Pengelola\Tagihan\Surel\PembayaranLanggananDiterima;
use App\Domain\Tenant\Peristiwa\TagihanLanggananDilunasiGerbang;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * BR-P08.11: email "pembayaran diterima" ke Owner setelah tagihan lunas lewat gerbang. Templatnya sama dengan jalur
 * transfer manual, karena bagi Owner isinya memang sama — tagihan lunas dan periodenya sampai kapan.
 *
 * Berjalan di antrean setelah commit: notifikasi gerbang harus dijawab cepat, dan SMTP yang lambat atau mati tidak
 * boleh membuat DOKU mengulang notifikasi yang sudah berhasil dibukukan. Kegagalan kirim muncul sebagai job
 * gagal di dasbor operasional (P-11).
 */
final class KirimSurelPelunasanGerbang implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(TagihanLanggananDilunasiGerbang $peristiwa): void
    {
        Mail::to($peristiwa->email)->send(new PembayaranLanggananDiterima(
            nama: $peristiwa->nama,
            nomorTagihan: $peristiwa->nomorTagihan,
            total: $peristiwa->total,
            namaPaket: $peristiwa->namaPaket,
            periodeSelesai: $peristiwa->periodeSelesai,
        ));
    }
}
