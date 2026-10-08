<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\SubAkun;

use App\Domain\Integrasi\Enum\StatusSubAkunPembayaran;
use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\GerbangPembayaran\ProtokolDoku;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien DOKU Sub Account API (`POST /sac-merchant/v1/accounts`) memakai **akun induk DOKU milik Payoung**: kredensial
 * yang sama dengan tagihan langganan (integrasi platform `GerbangBilling`/`DokuBilling`, satu Client ID/secret/mode).
 * Tanda tangannya protokol DOKU non-SNAP yang sama dengan Checkout (`ProtokolDoku`).
 *
 * Hanya membuat sub account. Rute dana QRIS ke sub account belum terdokumentasi DOKU, jadi jalur QRIS toko
 * (`AdaptorDoku` dengan kredensial tenant) sengaja tidak disentuh di sini.
 *
 * Bentuk respons diasumsikan dari dokumentasi DOKU (`account.id`, `account.status` = PENDING|ACTIVE) dan belum
 * diverifikasi dengan panggilan nyata.
 */
final class KlienSubAkunDoku
{
    private const TARGET_BUAT = '/sac-merchant/v1/accounts';

    public function CekAktif(): bool
    {
        return $this->IdKlien() !== '' && $this->KunciRahasia() !== '';
    }

    public function CekSandbox(): bool
    {
        return trim((string) config('integrasi.GerbangBilling.Pengaturan.Mode', '')) !== 'Produksi';
    }

    /**
     * @throws GalatGerbang `tidakPasti` = false hanya untuk penolakan 4xx pasti; selain itu sub account mungkin sudah
     *                      terbentuk di DOKU dan permintaan boleh dicoba lagi.
     */
    public function BuatSubAkun(string $email, string $nama): HasilSubAkun
    {
        $isi = (string) json_encode([
            'account' => ['email' => $email, 'type' => 'STANDARD', 'name' => mb_substr($nama, 0, 100)],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $respons = Http::timeout(15)->acceptJson()
                ->withHeaders(ProtokolDoku::BuatHeader($this->IdKlien(), $this->KunciRahasia(), self::TARGET_BUAT, $isi))
                ->withBody($isi, 'application/json')
                ->post(ProtokolDoku::AmbilAlamatDasar($this->CekSandbox()).self::TARGET_BUAT);
        } catch (ConnectionException) {
            throw new GalatGerbang('DOKU tidak bisa dihubungi. Sub account mungkin sudah terbentuk; coba lagi sebentar lagi.', tidakPasti: true);
        }

        $id = $respons->json('account.id');

        if ($respons->successful()) {
            if (! is_string($id) || trim($id) === '') {
                throw new GalatGerbang('DOKU menjawab berhasil tetapi ID sub account tidak terbaca. Periksa dasbor DOKU sebelum mencoba lagi.', tidakPasti: true);
            }

            // Respons tak jelas = Menunggu: sub account DOKU lahir PENDING dan baru aktif setelah verifikasi.
            $aktif = strtoupper((string) $respons->json('account.status')) === 'ACTIVE';

            return new HasilSubAkun(trim($id), $aktif ? StatusSubAkunPembayaran::Aktif : StatusSubAkunPembayaran::Menunggu);
        }

        throw new GalatGerbang(
            $this->Saring("DOKU menolak pembuatan sub account (HTTP {$respons->status()}): ".$this->PesanPenyedia($respons)),
            tidakPasti: $respons->serverError() || in_array($respons->status(), [408, 409, 425, 429], true),
        );
    }

    /** DOKU mengirim galat sebagai `message` (daftar atau teks) atau `error.message`; bentuk lain jadi pesan umum. */
    private function PesanPenyedia(Response $respons): string
    {
        $pesan = $respons->json('message.0') ?? $respons->json('message') ?? $respons->json('error.message');

        return is_string($pesan) && $pesan !== '' ? $pesan : 'permintaan tidak diterima.';
    }

    private function IdKlien(): string
    {
        return trim((string) config('integrasi.GerbangBilling.Pengaturan.IdKlien', ''));
    }

    private function KunciRahasia(): string
    {
        return trim((string) config('integrasi.GerbangBilling.Kredensial.KunciRahasia', ''));
    }

    private function Saring(string $pesan): string
    {
        $pesan = str_replace([$this->KunciRahasia(), $this->IdKlien()], '••••', $pesan);

        return mb_substr($pesan, 0, 300);
    }
}
