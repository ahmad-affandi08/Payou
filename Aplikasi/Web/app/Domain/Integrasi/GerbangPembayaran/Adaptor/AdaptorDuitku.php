<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran\Adaptor;

use App\Domain\Integrasi\GerbangPembayaran\HasilQris;
use App\Domain\Integrasi\GerbangPembayaran\HasilWebhook;
use App\Domain\Integrasi\GerbangPembayaran\PermintaanQris;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use App\Domain\Integrasi\HasilUjiLayanan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Duitku API v2 (`/webapi/api/merchant/v2/inquiry`, kanal QRIS sesuai pengaturan, misal SP/NQ/GQ). Signature
 * permintaan MD5(kode merchant + merchantOrderId + paymentAmount + API key); callback MD5(kode merchant + amount +
 * merchantOrderId + API key). MD5 di sini adalah format tanda tangan yang diwajibkan protokol Duitku (bukan
 * penyimpanan sandi), sehingga dihitung lewat `TandaMd5()`.
 */
final class AdaptorDuitku extends AdaptorDasar
{
    public function AmbilKode(): string
    {
        return 'Duitku';
    }

    private function AlamatDasar(): string
    {
        return $this->CekSandbox() ? 'https://sandbox.duitku.com' : 'https://passport.duitku.com';
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $kode = $this->Pengaturan('KodeMerchant');
        $waktu = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $respons = $this->Kirim(fn () => $this->Http()->post($this->AlamatDasar().'/webapi/api/merchant/paymentmethod/getpaymentmethod', [
            'merchantcode' => $kode,
            'amount' => 10000,
            'datetime' => $waktu,
            'signature' => hash('sha256', $kode.'10000'.$waktu.$this->Kredensial('KunciApi')),
        ]));

        return $respons->successful() && (string) $respons->json('responseCode') === '00'
            ? HasilUjiLayanan::Berhasil('Kode merchant & API key diterima Duitku.')
            : HasilUjiLayanan::Gagal($this->Saring('Duitku menolak kredensial: '.($respons->json('responseMessage') ?? $respons->json('Message') ?? "HTTP {$respons->status()}")));
    }

    public function BuatQris(PermintaanQris $permintaan): HasilQris
    {
        $kode = $this->Pengaturan('KodeMerchant');
        $jumlah = $permintaan->AmbilJumlahBulat();
        $menit = max(1, (int) ceil(CarbonImmutable::now()->diffInMinutes($permintaan->kedaluwarsaPada, true)));
        $respons = $this->Kirim(fn () => $this->Http()->post($this->AlamatDasar().'/webapi/api/merchant/v2/inquiry', [
            'merchantCode' => $kode,
            'paymentAmount' => $jumlah,
            'paymentMethod' => $this->Pengaturan('KanalQris') !== '' ? $this->Pengaturan('KanalQris') : 'SP',
            'merchantOrderId' => $permintaan->nomorPesanan,
            'productDetails' => mb_substr($permintaan->keterangan, 0, 255),
            'email' => $permintaan->emailPelanggan,
            'customerVaName' => $permintaan->namaPelanggan,
            'callbackUrl' => $permintaan->urlNotifikasi,
            'returnUrl' => $permintaan->urlNotifikasi,
            'expiryPeriod' => $menit,
            'signature' => self::TandaMd5($kode.$permintaan->nomorPesanan.$jumlah.$this->Kredensial('KunciApi')),
        ]));
        $qr = $respons->json('qrString');

        if ((string) $respons->json('statusCode') !== '00' || ! is_string($qr) || $qr === '') {
            throw $this->GagalRespons($respons, (string) ($respons->json('statusMessage') ?? $respons->json('Message') ?? 'QRIS tidak dibuat.'));
        }

        return new HasilQris((string) $respons->json('reference'), $qr, $permintaan->kedaluwarsaPada);
    }

    /** Status ditanyakan dengan nomor pesanan (order id) milik Payoung (audit P0 F-02). */
    public function CekDapatCekDariNomorPesanan(): bool
    {
        return true;
    }

    public function CekStatus(string $nomorPesanan, string $idReferensi): StatusPembayaranGerbang
    {
        $kode = $this->Pengaturan('KodeMerchant');
        $respons = $this->Kirim(fn () => $this->Http()->post($this->AlamatDasar().'/webapi/api/merchant/transactionStatus', [
            'merchantCode' => $kode,
            'merchantOrderId' => $nomorPesanan,
            'signature' => self::TandaMd5($kode.$nomorPesanan.$this->Kredensial('KunciApi')),
        ]));

        return match ((string) $respons->json('statusCode')) {
            '00' => StatusPembayaranGerbang::Lunas,
            '02' => StatusPembayaranGerbang::Gagal,
            default => StatusPembayaranGerbang::Menunggu,
        };
    }

    public function UraiWebhook(Request $permintaan): ?HasilWebhook
    {
        $kode = (string) $permintaan->input('merchantCode');
        $jumlah = (string) $permintaan->input('amount');
        $pesanan = (string) $permintaan->input('merchantOrderId');
        $harapan = self::TandaMd5($kode.$jumlah.$pesanan.$this->Kredensial('KunciApi'));

        if ($pesanan === '' || $kode !== $this->Pengaturan('KodeMerchant') || ! hash_equals($harapan, (string) $permintaan->input('signature'))) {
            return null;
        }

        return new HasilWebhook(
            $pesanan,
            (string) $permintaan->input('resultCode') === '00' ? StatusPembayaranGerbang::Lunas : StatusPembayaranGerbang::Gagal,
            $jumlah,
            (string) $permintaan->input('reference'),
        );
    }

    /** Tanda tangan MD5 sesuai spesifikasi API Duitku. */
    private static function TandaMd5(string $isi): string
    {
        return hash('md5', $isi);
    }
}
