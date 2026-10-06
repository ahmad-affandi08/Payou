<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran\Adaptor;

use App\Domain\Integrasi\GerbangPembayaran\HasilQris;
use App\Domain\Integrasi\GerbangPembayaran\HasilWebhook;
use App\Domain\Integrasi\GerbangPembayaran\PermintaanQris;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use App\Domain\Integrasi\HasilUjiLayanan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Midtrans Core API QRIS (`POST /v2/charge` payment_type `qris`). Notifikasi HTTP diverifikasi dengan
 * `signature_key = SHA512(order_id + status_code + gross_amount + ServerKey)`.
 */
final class AdaptorMidtrans extends AdaptorDasar
{
    public function AmbilKode(): string
    {
        return 'Midtrans';
    }

    private function AlamatDasar(): string
    {
        return $this->CekSandbox() ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
    }

    private function Klien(): PendingRequest
    {
        return $this->Http()->withBasicAuth($this->Kredensial('KunciServer'), '');
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Kirim(fn () => $this->Klien()->get($this->AlamatDasar().'/v2/uji-'.Str::lower(Str::random(12)).'/status'));
        $kode = (string) ($respons->json('status_code') ?? $respons->status());

        return $kode === '401' || $respons->status() === 401
            ? HasilUjiLayanan::Gagal('Server key ditolak Midtrans. Periksa kunci & mode (Sandbox/Produksi).')
            : HasilUjiLayanan::Berhasil('Server key diterima Midtrans.');
    }

    public function BuatQris(PermintaanQris $permintaan): HasilQris
    {
        $menit = max(1, (int) ceil(CarbonImmutable::now()->diffInMinutes($permintaan->kedaluwarsaPada, true)));
        $respons = $this->Kirim(fn () => $this->Klien()->withHeaders(['X-Override-Notification' => $permintaan->urlNotifikasi])
            ->post($this->AlamatDasar().'/v2/charge', [
                'payment_type' => 'qris',
                'transaction_details' => ['order_id' => $permintaan->nomorPesanan, 'gross_amount' => $permintaan->AmbilJumlahBulat()],
                'item_details' => [['id' => 'TAGIHAN', 'price' => $permintaan->AmbilJumlahBulat(), 'quantity' => 1, 'name' => mb_substr($permintaan->keterangan, 0, 50)]],
                'customer_details' => ['first_name' => $permintaan->namaPelanggan, 'email' => $permintaan->emailPelanggan],
                'qris' => ['acquirer' => $this->Pengaturan('Akuisitor') !== '' ? $this->Pengaturan('Akuisitor') : 'gopay'],
                'custom_expiry' => ['expiry_duration' => $menit, 'unit' => 'minute'],
            ]));
        $kode = (string) $respons->json('status_code');
        $qr = $respons->json('qr_string');

        if (! in_array($kode, ['200', '201'], true) || ! is_string($qr) || $qr === '') {
            throw $this->GagalRespons($respons, (string) ($respons->json('status_message') ?? 'QRIS tidak dibuat.'));
        }

        $kedaluwarsa = $respons->json('expiry_time');

        return new HasilQris(
            (string) $respons->json('transaction_id'),
            $qr,
            is_string($kedaluwarsa) ? CarbonImmutable::parse($kedaluwarsa, 'Asia/Jakarta')->utc() : $permintaan->kedaluwarsaPada,
        );
    }

    /** Status ditanyakan dengan nomor pesanan (order id) milik Payoung (audit P0 F-02). */
    public function CekDapatCekDariNomorPesanan(): bool
    {
        return true;
    }

    public function CekStatus(string $nomorPesanan, string $idReferensi): StatusPembayaranGerbang
    {
        $respons = $this->Kirim(fn () => $this->Klien()->get($this->AlamatDasar().'/v2/'.rawurlencode($nomorPesanan).'/status'));

        return self::PetakanStatus((string) $respons->json('transaction_status'));
    }

    public function UraiWebhook(Request $permintaan): ?HasilWebhook
    {
        $pesanan = (string) $permintaan->input('order_id');
        $kodeStatus = (string) $permintaan->input('status_code');
        $jumlah = (string) $permintaan->input('gross_amount');
        $tanda = (string) $permintaan->input('signature_key');
        $harapan = hash('sha512', $pesanan.$kodeStatus.$jumlah.$this->Kredensial('KunciServer'));

        if ($pesanan === '' || ! hash_equals($harapan, $tanda)) {
            return null;
        }

        return new HasilWebhook($pesanan, self::PetakanStatus((string) $permintaan->input('transaction_status')), $jumlah, (string) $permintaan->input('transaction_id'));
    }

    private static function PetakanStatus(string $status): StatusPembayaranGerbang
    {
        return match ($status) {
            'settlement', 'capture' => StatusPembayaranGerbang::Lunas,
            'expire' => StatusPembayaranGerbang::Kedaluwarsa,
            'cancel', 'deny', 'failure' => StatusPembayaranGerbang::Gagal,
            default => StatusPembayaranGerbang::Menunggu,
        };
    }
}
