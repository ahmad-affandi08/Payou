<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Integrasi\GerbangPembayaran\ProtokolDoku;
use App\Domain\Integrasi\Merchant\KlienPartnerDoku;
use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Menguji Brand ID dan secret key Partner DOKU dengan Generate Token (`POST /adv-core-api/partner/v1.0/token`): panggilan
 * bertanda tangan yang hanya menerbitkan token sementara, tanpa mendaftarkan atau mengubah apa pun di DOKU.
 *
 * 401/403 = kredensial salah. 5xx = DOKU bermasalah sehingga kredensial belum terbukti (dilaporkan gagal). Token yang
 * diterbitkan tidak disimpan dan pesan galat tidak pernah memuat secret key (BR-P05.6). Skema tanda tangan Partner API
 * diasumsikan sama dengan Checkout non-SNAP (belum terverifikasi): bila UAT menolak kredensial yang benar, periksa itu dulu.
 */
final class PengujiDokuPartner implements PengujiKoneksi
{
    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        $idKlien = trim((string) ($pengaturan['IdKlien'] ?? ''));
        $kunci = trim((string) ($kredensial['KunciRahasia'] ?? ''));

        if ($idKlien === '') {
            return HasilUjiKoneksi::Gagal('Brand ID partner belum diisi.');
        }

        if ($kunci === '') {
            return HasilUjiKoneksi::Gagal('Secret key belum diisi.');
        }

        $produksi = ($pengaturan['Mode'] ?? 'Sandbox') === 'Produksi';
        $label = $produksi ? 'Produksi' : 'Sandbox (UAT)';
        $target = KlienPartnerDoku::AWALAN.'/token';
        $isi = (string) json_encode(['grant_type' => 'client_credentials', 'valid_time' => '360']);

        try {
            $respons = Http::acceptJson()->timeout(10)
                ->withHeaders(ProtokolDoku::BuatHeader($idKlien, $kunci, $target, $isi))
                ->withBody($isi, 'application/json')
                ->post(($produksi ? KlienPartnerDoku::HOST_PRODUKSI : KlienPartnerDoku::HOST_UAT).$target);
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Tidak bisa menghubungi DOKU: '.PenyaringPesan::Saring($galat->getMessage(), ['KunciRahasia' => $kunci]));
        }

        if (in_array($respons->status(), [401, 403], true)) {
            return HasilUjiKoneksi::Gagal("Brand ID atau secret key ditolak DOKU. Periksa apakah kuncinya disalin utuh dan sesuai lingkungan {$label}, dan Payoung sudah berstatus Partner.");
        }

        if (! $respons->successful()) {
            return HasilUjiKoneksi::Gagal("DOKU menjawab status {$respons->status()}. Coba lagi sebentar lagi.");
        }

        $token = $respons->json('token');

        if (! is_string($token) || trim($token) === '') {
            return HasilUjiKoneksi::Gagal('DOKU menjawab berhasil tetapi token tidak terbaca. Periksa pengaturan akun Partner.');
        }

        return HasilUjiKoneksi::Berhasil("Brand ID & secret key diterima DOKU Partner (lingkungan {$label}).");
    }
}
