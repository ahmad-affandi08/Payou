<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use Carbon\CarbonInterface;

/**
 * Syarat membeli add-on mandiri (D-49): langganan berbayar `Aktif` yang periodenya belum berakhir, bukan ditangguhkan
 * manual, dan paketnya bukan Gratis / harga negosiasi (add-on paket negosiasi diurus tim kami lewat tiket). Mengembalikan
 * alasan penolakan dalam bahasa pengguna, atau null bila boleh. Murni dari data yang diberikan.
 */
final class KelayakanBeliAddon
{
    public function Periksa(?Langganan $langganan, ?Paket $paket, CarbonInterface $sekarang): ?string
    {
        if ($langganan === null || $paket === null) {
            return 'Data langganan usaha ini tidak ditemukan. Hubungi tim kami.';
        }

        if ($langganan->CekDitangguhkanManual()) {
            return 'Usaha ini sedang ditangguhkan oleh tim kami. Hubungi tim kami lewat menu Bantuan.';
        }

        if ($langganan->Status === StatusLangganan::Trial) {
            return 'Add-on dibeli setelah Anda berlangganan paket berbayar. Selama masa coba, pilih paket dulu di menu Langganan.';
        }

        if ($langganan->Status === StatusLangganan::Tertunggak) {
            return 'Perpanjangan paket Anda belum dibayar. Selesaikan pembayarannya dulu, lalu beli add-on.';
        }

        if ($langganan->Status !== StatusLangganan::Aktif) {
            return 'Langganan usaha ini belum aktif. Aktifkan paket berbayar dulu di menu Langganan.';
        }

        if ($paket->Kode === (string) config('tenant.KodePaketGratis')) {
            return 'Paket Gratis tidak bisa ditambah add-on. Naik ke paket berbayar dulu di menu Langganan.';
        }

        if ($paket->HargaNegosiasi) {
            return 'Paket Anda berharga khusus, jadi add-on diatur oleh tim kami.';
        }

        if ($langganan->PeriodeMulai === null || $langganan->PeriodeSelesai === null || ! $langganan->PeriodeSelesai->greaterThan($sekarang)) {
            return 'Periode langganan Anda sudah berakhir. Perpanjang paket dulu, lalu beli add-on.';
        }

        return null;
    }
}
