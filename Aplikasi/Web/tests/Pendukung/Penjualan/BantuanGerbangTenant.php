<?php

declare(strict_types=1);

namespace Tests\Pendukung\Penjualan;

use App\Domain\Integrasi\Aksi\SimpanGerbangPembayaranTenant;
use App\Domain\Integrasi\Enum\LingkunganGerbang;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\Enum\StatusUjiGerbang;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\TestCase;

/**
 * Gerbang pembayaran QRIS dinamis milik tenant (v2.06) untuk test: langsung aktif & lolos uji (jalur simpan → uji →
 * aktifkan diuji terpisah di `GerbangPembayaranTenantTes`). Sejak seluruh tenant memakai DOKU, helper ini membuat
 * gerbang DOKU beserta DOKU palsu (`Http::fake`) dan notifikasi bertanda tangan `HMACSHA256` yang sama dengan yang
 * diverifikasi `AdaptorDoku`.
 */
final class BantuanGerbangTenant
{
    public const ID_KLIEN_UJI = 'BRN-UJI-0001';

    public const KUNCI_RAHASIA_UJI = 'SK-doku-uji-rahasia-9911';

    /**
     * @param  array<string, string>|null  $kredensial
     * @param  array<string, string|int>|null  $pengaturan
     */
    public static function Aktifkan(
        int $idTenant,
        PenyediaGerbang $penyedia = PenyediaGerbang::Doku,
        ?array $kredensial = null,
        ?array $pengaturan = null,
    ): GerbangPembayaranTenant {
        $kredensial ??= ['KunciRahasia' => self::KUNCI_RAHASIA_UJI];
        $pengaturan ??= ['IdKlien' => self::ID_KLIEN_UJI];
        BantuanOrganisasi::AturKonteks($idTenant);

        return GerbangPembayaranTenant::query()->create([
            'Uuid' => (string) Str::ulid(),
            'Penyedia' => $penyedia,
            'Lingkungan' => LingkunganGerbang::Sandbox,
            'Pengaturan' => $pengaturan,
            'Kredensial' => $kredensial,
            'PetunjukKredensial' => array_map(SimpanGerbangPembayaranTenant::BuatPetunjuk(...), $kredensial),
            'StatusUji' => StatusUjiGerbang::Berhasil,
            'Aktif' => true,
            'TokenWebhook' => SimpanGerbangPembayaranTenant::BuatToken($idTenant),
        ]);
    }

    public static function Nonaktifkan(int $idTenant): void
    {
        BantuanOrganisasi::AturKonteks($idTenant);
        GerbangPembayaranTenant::query()->update(['Aktif' => false]);
    }

    /** Permintaan membuat tagihan ke DOKU Checkout. */
    public static function CekPermintaanBuat(PermintaanHttp $permintaan): bool
    {
        return str_ends_with($permintaan->url(), '/checkout/v1/payment');
    }

    /** Permintaan menanyakan status order ke DOKU. */
    public static function CekPermintaanStatus(PermintaanHttp $permintaan): bool
    {
        return str_contains($permintaan->url(), '/orders/v1/status/');
    }

    /** Jawaban DOKU Checkout yang sukses: halaman bayar + token transaksi `{awalan}-{nomor pesanan}`. */
    public static function ResponsBuat(PermintaanHttp $permintaan, string $awalan = 'trx'): PromiseInterface
    {
        $nomor = (string) ($permintaan['order']['invoice_number'] ?? '');

        return Http::response(['response' => ['payment' => [
            'url' => "https://sandbox.doku.com/checkout/link/{$nomor}",
            'token_id' => "{$awalan}-{$nomor}",
        ]]]);
    }

    /** Jawaban status order DOKU (`PENDING`, `SUCCESS`, `EXPIRED`, `FAILED`). */
    public static function ResponsStatus(string $status): PromiseInterface
    {
        return Http::response(['transaction' => ['status' => $status]]);
    }

    /** Jawaban penolakan DOKU (HTTP 401) yang pesannya memuat kunci rahasia, untuk menguji penyaringan. */
    public static function ResponsTolak(string $kunci = self::KUNCI_RAHASIA_UJI): PromiseInterface
    {
        return Http::response(['message' => ["Invalid signature for key {$kunci}"]], 401);
    }

    /**
     * Notifikasi DOKU ke URL webhook tenant: `/webhook/{kode}/{tokenWebhook}`, ditandatangani HMACSHA256 atas
     * Client-Id, Request-Id, Request-Timestamp, Request-Target, dan Digest isi mentah, persis seperti yang
     * diverifikasi `AdaptorDoku::UraiWebhook`. `$jumlah` boleh berbentuk desimal ('38500.00'); DOKU mengirim bilangan bulat.
     *
     * @return TestResponse<Response>
     */
    public static function KirimWebhook(
        TestCase $tes,
        string $tokenWebhook,
        string $nomorPesanan,
        string $jumlah = '38500.00',
        string $status = 'SUCCESS',
        string $kunci = self::KUNCI_RAHASIA_UJI,
        string $idKlien = self::ID_KLIEN_UJI,
        string $kodeUrl = 'doku',
    ): TestResponse {
        $isi = (string) json_encode([
            'order' => ['invoice_number' => $nomorPesanan, 'amount' => (int) explode('.', $jumlah)[0]],
            'transaction' => ['status' => $status],
        ]);
        $target = "/webhook/{$kodeUrl}/{$tokenWebhook}";
        $idPermintaan = (string) Str::uuid();
        $waktu = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $komponen = "Client-Id:{$idKlien}\nRequest-Id:{$idPermintaan}\nRequest-Timestamp:{$waktu}\nRequest-Target:{$target}"
            ."\nDigest:".base64_encode(hash('sha256', $isi, true));

        return $tes->call('POST', $target, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_CLIENT_ID' => $idKlien,
            'HTTP_REQUEST_ID' => $idPermintaan,
            'HTTP_REQUEST_TIMESTAMP' => $waktu,
            'HTTP_SIGNATURE' => 'HMACSHA256='.base64_encode(hash_hmac('sha256', $komponen, $kunci, true)),
        ], $isi);
    }
}
