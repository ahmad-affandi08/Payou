<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Menguji server key Midtrans milik platform (P-05, P-08 langkah 3) dengan menanyakan status sebuah order yang
 * sengaja tidak ada.
 *
 * Midtrans membedakan dua hal itu dengan jelas: kunci salah dijawab 401, sedangkan kunci benar dengan order tak
 * dikenal dijawab 404 "Transaction doesn't exist". Jadi **404 justru bukti kunci diterima** — dan tidak ada
 * transaksi palsu yang tercipta, berbeda dengan uji yang membuat transaksi sungguhan lalu harus dibatalkan.
 *
 * Akun ini milik Payoung untuk menagih tenant, terpisah dari gerbang QRIS milik toko (D-19) yang akunnya milik tenant.
 */
final class PengujiMidtransBilling implements PengujiKoneksi
{
    public const URL_SANDBOX = 'https://api.sandbox.midtrans.com';

    public const URL_PRODUKSI = 'https://api.midtrans.com';

    /** Order yang dipakai menguji; sengaja bernama jelas agar terbaca di log Midtrans bila tim mereka melihatnya. */
    private const ORDER_UJI = 'payoung-uji-koneksi';

    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        $kunci = $kredensial['KunciServer'] ?? '';

        if (! is_string($kunci) || $kunci === '') {
            return HasilUjiKoneksi::Gagal('Server key belum diisi.');
        }

        $produksi = ($pengaturan['Mode'] ?? 'Sandbox') === 'Produksi';
        $dasar = $produksi ? self::URL_PRODUKSI : self::URL_SANDBOX;

        // Kunci dengan awalan SB-Mid-server- sudah pasti milik Sandbox; memakainya di mode Produksi selalu 401.
        // Sebaliknya, akun Sandbox versi baru Midtrans juga bisa memakai awalan Mid-server- (tanpa SB-),
        // sehingga keabsahan kunci di mode Sandbox diserahkan langsung ke API Midtrans.
        $kunciPastiSandbox = str_starts_with($kunci, 'SB-Mid-server-');

        if ($produksi && $kunciPastiSandbox) {
            return HasilUjiKoneksi::Gagal('Mode Produksi dipilih, tetapi server key-nya kunci Sandbox (diawali SB-Mid-server-).');
        }

        try {
            // Basic auth Midtrans: server key sebagai nama pengguna, kata sandi kosong.
            $respons = Http::withBasicAuth($kunci, '')->acceptJson()->timeout(10)->get("{$dasar}/v2/".self::ORDER_UJI.'/status');
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Tidak bisa menghubungi Midtrans: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }

        $kode = (string) ($respons->json('status_code') ?? $respons->status());

        if ($kode === '401') {
            return HasilUjiKoneksi::Gagal('Server key ditolak Midtrans. Periksa apakah kuncinya disalin utuh dan sesuai lingkungannya.');
        }

        if ($kode === '404') {
            $label = $produksi ? 'Produksi' : 'Sandbox';

            return HasilUjiKoneksi::Berhasil("Server key diterima Midtrans (lingkungan {$label}).");
        }

        if ($respons->successful()) {
            return HasilUjiKoneksi::Berhasil('Server key diterima Midtrans.');
        }

        return HasilUjiKoneksi::Gagal("Midtrans menjawab status {$kode}.");
    }
}
