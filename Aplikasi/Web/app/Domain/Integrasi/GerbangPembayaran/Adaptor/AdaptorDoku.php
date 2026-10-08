<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran\Adaptor;

use App\Domain\Integrasi\GerbangPembayaran\HasilQris;
use App\Domain\Integrasi\GerbangPembayaran\HasilWebhook;
use App\Domain\Integrasi\GerbangPembayaran\PermintaanQris;
use App\Domain\Integrasi\GerbangPembayaran\ProtokolDoku;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use App\Domain\Integrasi\HasilUjiLayanan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * DOKU Checkout (`POST /checkout/v1/payment`, metode QRIS). DOKU Checkout memberi halaman bayar, bukan string QRIS,
 * jadi layar kasir menampilkan QR berisi URL halaman bayar. Tanda tangan: `HMACSHA256=` + base64(HMAC-SHA256(
 * "Client-Id:..\nRequest-Id:..\nRequest-Timestamp:..\nRequest-Target:..[\nDigest:..]", kunci rahasia)); notifikasi
 * diverifikasi dengan komponen yang sama.
 */
final class AdaptorDoku extends AdaptorDasar
{
    public function AmbilKode(): string
    {
        return 'Doku';
    }

    private function AlamatDasar(): string
    {
        return ProtokolDoku::AmbilAlamatDasar($this->CekSandbox());
    }

    /**
     * @return array<string, string>
     */
    private function Header(string $target, ?string $isi): array
    {
        return ProtokolDoku::BuatHeader($this->Pengaturan('IdKlien'), $this->Kredensial('KunciRahasia'), $target, $isi);
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $target = '/orders/v1/status/UJI-'.Str::upper(Str::random(10));
        $respons = $this->Kirim(fn () => $this->Http()->withHeaders($this->Header($target, null))->get($this->AlamatDasar().$target));

        return in_array($respons->status(), [401, 403], true)
            ? HasilUjiLayanan::Gagal('DOKU menolak Client ID atau kunci rahasia (tanda tangan tidak sah).')
            : HasilUjiLayanan::Berhasil('Client ID & kunci rahasia diterima DOKU.');
    }

    public function BuatQris(PermintaanQris $permintaan): HasilQris
    {
        $target = '/checkout/v1/payment';
        $menit = max(1, (int) ceil(CarbonImmutable::now()->diffInMinutes($permintaan->kedaluwarsaPada, true)));
        $isi = (string) json_encode([
            'order' => [
                'amount' => $permintaan->AmbilJumlahBulat(),
                'invoice_number' => $permintaan->nomorPesanan,
                'callback_url' => $permintaan->urlNotifikasi,
            ],
            'payment' => ['payment_due_date' => $menit, 'payment_method_types' => ['QRIS']],
            'customer' => ['name' => $permintaan->namaPelanggan, 'email' => $permintaan->emailPelanggan],
        ], JSON_UNESCAPED_SLASHES);
        $respons = $this->Kirim(fn () => $this->Http()->withHeaders($this->Header($target, $isi))
            ->withBody($isi, 'application/json')->post($this->AlamatDasar().$target));
        $url = $respons->json('response.payment.url');

        if (! $respons->successful() || ! is_string($url) || $url === '') {
            throw $this->GagalRespons($respons, (string) ($respons->json('message.0') ?? $respons->json('error.message') ?? 'Tagihan tidak dibuat.'));
        }

        return new HasilQris((string) ($respons->json('response.payment.token_id') ?? $permintaan->nomorPesanan), $url, $permintaan->kedaluwarsaPada, true);
    }

    /** Status ditanyakan dengan nomor pesanan (order id) milik Payoung (audit P0 F-02). */
    public function CekDapatCekDariNomorPesanan(): bool
    {
        return true;
    }

    public function CekStatus(string $nomorPesanan, string $idReferensi): StatusPembayaranGerbang
    {
        $target = '/orders/v1/status/'.rawurlencode($nomorPesanan);
        $respons = $this->Kirim(fn () => $this->Http()->withHeaders($this->Header($target, null))->get($this->AlamatDasar().$target));

        return ProtokolDoku::PetakanStatus((string) $respons->json('transaction.status'));
    }

    public function UraiWebhook(Request $permintaan): ?HasilWebhook
    {
        if (! ProtokolDoku::CekNotifikasiSah($permintaan, $this->Pengaturan('IdKlien'), $this->Kredensial('KunciRahasia'))) {
            return null;
        }

        $isi = $permintaan->getContent();
        $data = json_decode($isi, true);
        $pesanan = is_array($data) ? ($data['order']['invoice_number'] ?? null) : null;

        if (! is_string($pesanan) || $pesanan === '') {
            return null;
        }

        return new HasilWebhook(
            $pesanan,
            ProtokolDoku::PetakanStatus((string) ($data['transaction']['status'] ?? '')),
            isset($data['order']['amount']) ? (string) $data['order']['amount'] : null,
        );
    }
}
