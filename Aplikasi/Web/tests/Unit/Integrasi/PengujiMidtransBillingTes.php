<?php

declare(strict_types=1);

use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiMidtransBilling;
use Illuminate\Support\Facades\Http;

/*
 * P-05 penyedia Gerbang billing (P-08 langkah 3): server key Midtrans milik Payoung diuji dengan menanyakan status
 * order yang sengaja tidak ada. Midtrans menjawab 401 bila kunci salah dan 404 bila kunci benar tetapi ordernya
 * tidak dikenal — jadi 404 adalah bukti kunci diterima, tanpa membuat transaksi palsu.
 */

const KUNCI_SANDBOX = 'SB-Mid-server-abcdef123456';
const KUNCI_PRODUKSI = 'Mid-server-abcdef123456';

it('kunci sandbox yang benar: 404 dari Midtrans berarti kunci diterima', function (): void {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404)]);

    $hasil = (new PengujiMidtransBilling)->Uji(['Mode' => 'Sandbox'], ['KunciServer' => KUNCI_SANDBOX]);

    expect($hasil->berhasil)->toBeTrue()
        ->and($hasil->pesan)->toContain('Sandbox');
});

it('kunci ditolak Midtrans dilaporkan gagal', function (): void {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '401', 'status_message' => 'unauthorized'], 401)]);

    $hasil = (new PengujiMidtransBilling)->Uji(['Mode' => 'Sandbox'], ['KunciServer' => KUNCI_SANDBOX]);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain('ditolak Midtrans');
});

it('mode produksi memakai alamat produksi, bukan sandbox', function (): void {
    Http::fake(['api.midtrans.com/*' => Http::response(['status_code' => '404'], 404)]);

    $hasil = (new PengujiMidtransBilling)->Uji(['Mode' => 'Produksi'], ['KunciServer' => KUNCI_PRODUKSI]);

    expect($hasil->berhasil)->toBeTrue()
        ->and($hasil->pesan)->toContain('Produksi');
    Http::assertSent(fn ($permintaan): bool => str_starts_with($permintaan->url(), PengujiMidtransBilling::URL_PRODUKSI));
});

it('kunci sandbox dipakai di mode produksi ditolak sebelum menghubungi Midtrans', function (): void {
    // Tanpa pemeriksaan ini Midtrans hanya menjawab 401 dan pesannya menyesatkan: kuncinya benar, modenya yang salah.
    Http::fake();

    $hasil = (new PengujiMidtransBilling)->Uji(['Mode' => 'Produksi'], ['KunciServer' => KUNCI_SANDBOX]);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain('kunci Sandbox');
    Http::assertNothingSent();
});

it('kunci sandbox format baru berawalan Mid-server- diterima jika diizinkan endpoint sandbox', function (): void {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404)]);

    $hasil = (new PengujiMidtransBilling)->Uji(['Mode' => 'Sandbox'], ['KunciServer' => KUNCI_PRODUKSI]);

    expect($hasil->berhasil)->toBeTrue()
        ->and($hasil->pesan)->toContain('Sandbox');
    Http::assertSent(fn ($permintaan): bool => str_starts_with($permintaan->url(), PengujiMidtransBilling::URL_SANDBOX));
});

it('server key kosong ditolak tanpa permintaan jaringan', function (): void {
    Http::fake();

    $hasil = (new PengujiMidtransBilling)->Uji(['Mode' => 'Sandbox'], []);

    expect($hasil->berhasil)->toBeFalse();
    Http::assertNothingSent();
});

it('gerbang billing terpisah dari gerbang QRIS milik toko dan diatur di tingkat platform', function (): void {
    expect(PenyediaIntegrasi::MidtransBilling->AmbilJenis())->toBe(JenisIntegrasi::GerbangBilling)
        ->and(array_map(fn ($p) => $p->value, JenisIntegrasi::GerbangBilling->AmbilDaftarPenyedia()))->toBe(['MidtransBilling'])
        ->and(array_column(PenyediaIntegrasi::MidtransBilling->AmbilBidangKredensial(), 'Kunci'))->toBe(['KunciServer'])
        // Client key publik: dipasang di halaman bayar tenant, jadi pengaturan biasa, bukan kredensial.
        ->and(array_column(PenyediaIntegrasi::MidtransBilling->AmbilBidangPengaturan(), 'Kunci'))->toBe(['Mode', 'KunciKlien'])
        ->and(JenisIntegrasi::AmbilJenisPlatform())->toContain(JenisIntegrasi::GerbangBilling)
        // D-19: gerbang QRIS toko tetap per tenant dan tidak ikut jadi konfigurasi platform.
        ->and(JenisIntegrasi::AmbilJenisPlatform())->not->toContain(JenisIntegrasi::GerbangPembayaran);
});
