<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Billing;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\GerbangPembayaran\ProtokolDoku;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Gerbang pembayaran untuk **tagihan langganan Payoung sendiri** (P-08 langkah 3, BR-P08.11): akun DOKU milik
 * platform, dikonfigurasi di konsol pengelola sebagai integrasi `GerbangBilling` (P-05, penyedia `DokuBilling`).
 *
 * Sengaja terpisah dari `Domain\Integrasi\GerbangPembayaran` (QRIS milik toko, D-19): di sana akun dan kredensialnya
 * milik tenant dan dipakai menagih pembeli, di sini akunnya milik Payoung dan dipakai menagih tenant. Keduanya memakai
 * protokol DOKU yang sama (`ProtokolDoku`) tanpa saling memakai kredensial.
 *
 * Alurnya DOKU Checkout: Payoung membuat transaksi (`POST /checkout/v1/payment`) lalu peramban tenant diarahkan ke
 * halaman bayar DOKU. Pelunasan tagihan **tidak** pernah dari peramban (bisa dipalsukan), hanya dari notifikasi
 * webhook bertanda tangan yang statusnya dikonfirmasi ulang lewat API status DOKU, atau dari rekonsiliasi.
 */
final class GerbangBillingPlatform
{
    /** Batas bayar satu transaksi; setelahnya tenant membuat transaksi baru dari tagihan yang sama. */
    public const MENIT_KEDALUWARSA = 60;

    private const TARGET_BUAT = '/checkout/v1/payment';

    private const TARGET_STATUS = '/orders/v1/status/';

    public function CekAktif(): bool
    {
        return $this->IdKlien() !== '' && $this->KunciRahasia() !== '';
    }

    public function CekSandbox(): bool
    {
        return trim((string) config('integrasi.GerbangBilling.Pengaturan.Mode', '')) !== 'Produksi';
    }

    /**
     * @param  string  $urlKembali  halaman yang dibuka DOKU setelah tenant selesai di halaman bayar
     *
     * @throws GalatGerbang
     */
    public function BuatTransaksi(
        string $nomorPesanan,
        Uang $total,
        string $namaPembayar,
        string $emailPembayar,
        string $urlKembali,
    ): HasilPembayaranBilling {
        $bagian = NomorPesananBilling::Urai($nomorPesanan);
        $isi = (string) json_encode([
            'order' => [
                'amount' => (int) $total->KeString(),
                'invoice_number' => $nomorPesanan,
                'callback_url' => $urlKembali,
                'auto_redirect' => true,
            ],
            'payment' => ['payment_due_date' => self::MENIT_KEDALUWARSA],
            'customer' => [
                'id' => $bagian !== null ? 'T'.$bagian['IdTenant'] : 'T0',
                'name' => mb_substr($namaPembayar, 0, 100),
                'email' => $emailPembayar,
            ],
        ], JSON_UNESCAPED_SLASHES);

        try {
            $respons = Http::timeout(15)->acceptJson()
                ->withHeaders(ProtokolDoku::BuatHeader($this->IdKlien(), $this->KunciRahasia(), self::TARGET_BUAT, $isi))
                ->withBody($isi, 'application/json')
                ->post($this->AlamatDasar().self::TARGET_BUAT);
        } catch (ConnectionException) {
            // Transaksi mungkin sudah terbentuk di DOKU; pembayaran dibiarkan Menunggu dan webhook tetap berlaku.
            throw new GalatGerbang('Gerbang pembayaran tidak bisa dihubungi. Coba lagi sebentar lagi.', tidakPasti: true);
        }

        $url = $respons->json('response.payment.url');

        if (! $respons->successful() || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new GalatGerbang(
                $this->Saring("Gerbang pembayaran menolak permintaan (HTTP {$respons->status()}): ".$this->PesanPenyedia($respons)),
                // Respons sukses yang tidak terbaca berarti transaksinya mungkin ada; 5xx juga belum tentu gagal.
                tidakPasti: $respons->successful() || $respons->serverError() || in_array($respons->status(), [408, 409, 425, 429], true),
            );
        }

        return new HasilPembayaranBilling($url);
    }

    /** DOKU mengirim galat sebagai `message` (daftar teks) atau `error.message`; bentuk lain diringkas jadi pesan umum. */
    private function PesanPenyedia(Response $respons): string
    {
        $pesan = $respons->json('message.0') ?? $respons->json('error.message');

        return is_string($pesan) && $pesan !== '' ? $pesan : 'transaksi tidak dibuat.';
    }

    /**
     * Notifikasi HTTP DOKU sah bila `Client-Id` milik akun platform dan `Signature` cocok untuk path webhook & badan
     * mentahnya (badan yang diubah sedikit pun membuat tanda tangan tidak cocok). Gerbang belum aktif = tidak sah.
     */
    public function CekTandaTanganSah(Request $permintaan): bool
    {
        return $this->CekAktif() && ProtokolDoku::CekNotifikasiSah($permintaan, $this->IdKlien(), $this->KunciRahasia());
    }

    /**
     * Mengurai badan notifikasi DOKU yang tanda tangannya sudah terbukti sah. Nomor pesanan bukan format Payoung
     * (misal notifikasi uji dari dasbor DOKU) atau badan tak terbaca = null. **Status di sini belum dipercaya:**
     * pemanggil wajib mengonfirmasinya lewat `Konfirmasi()` sebelum melunasi apa pun.
     */
    public function UraiNotifikasi(Request $permintaan): ?NotifikasiBilling
    {
        if (! $this->CekTandaTanganSah($permintaan)) {
            return null;
        }

        $data = json_decode($permintaan->getContent(), true);
        $nomor = is_array($data) ? ($data['order']['invoice_number'] ?? null) : null;
        $bagian = is_string($nomor) ? NomorPesananBilling::Urai($nomor) : null;

        if (! is_string($nomor) || $bagian === null) {
            return null;
        }

        $statusAsli = (string) ($data['transaction']['status'] ?? '');

        return new NotifikasiBilling(
            nomorPesanan: $nomor,
            idTenant: $bagian['IdTenant'],
            uuidPembayaran: $bagian['Uuid'],
            status: ProtokolDoku::PetakanStatus($statusAsli),
            jumlah: self::AmbilTeks($data['order']['amount'] ?? null),
            idTransaksi: self::AmbilTeks($data['transaction']['original_request_id'] ?? null),
            statusAsli: $statusAsli,
        );
    }

    /**
     * Konfirmasi notifikasi webhook ke API status DOKU: isi webhook saja tidak dipercaya untuk melunasi tagihan.
     * Hasilnya notifikasi bersumber API status; null bila DOKU tidak bisa dihubungi, transaksinya tidak dikenal, atau
     * statusnya belum sama dengan yang diklaim webhook (pemanggil menjawab "coba lagi" supaya DOKU mengulang).
     */
    public function Konfirmasi(NotifikasiBilling $notifikasi): ?NotifikasiBilling
    {
        $respons = $this->TanyaStatus($notifikasi->nomorPesanan);
        $hasil = $respons === null ? null : $this->UraiStatus($notifikasi->nomorPesanan, $respons, $notifikasi->idTransaksi);

        if ($hasil === null || $hasil->status !== $notifikasi->status) {
            return null;
        }

        // Jumlah dari webhook bertanda tangan dipakai bila respons status tidak memuatnya.
        return $hasil->jumlah === '' && $notifikasi->jumlah !== ''
            ? new NotifikasiBilling($hasil->nomorPesanan, $hasil->idTenant, $hasil->uuidPembayaran, $hasil->status, $notifikasi->jumlah, $hasil->idTransaksi, $hasil->statusAsli)
            : $hasil;
    }

    /**
     * Rekonsiliasi (P-08, v4.06): tanyakan status satu transaksi ke API status DOKU (`GET /orders/v1/status/{invoice}`),
     * untuk pembayaran yang notifikasi webhook-nya tidak pernah tiba. Hasilnya dibentuk sama dengan notifikasi webhook
     * supaya diproses jalur yang sama (`TerimaNotifikasiBillingLangganan`).
     *
     * - Transaksi tidak ada di DOKU (halaman bayar dibuka tetapi tidak pernah dipilih cara bayarnya, atau transaksi
     *   lama milik penyedia sebelum DOKU) dan sudah lewat `MENIT_KEDALUWARSA` sejak `$dibuatPada` = `Kedaluwarsa`;
     *   sebelum itu = `Menunggu`.
     * - Gagal menghubungi gerbang, respons tak terbaca, atau gerbang belum aktif = null (dicoba lagi putaran berikutnya).
     */
    public function CekStatus(string $nomorPesanan, DateTimeInterface $dibuatPada): ?NotifikasiBilling
    {
        $bagian = NomorPesananBilling::Urai($nomorPesanan);

        if (! $this->CekAktif() || $bagian === null) {
            return null;
        }

        $respons = $this->TanyaStatus($nomorPesanan);

        if ($respons === null) {
            return null;
        }

        if ($respons->status() === 404) {
            $lewat = $dibuatPada->getTimestamp() + (self::MENIT_KEDALUWARSA + 15) * 60 < CarbonImmutable::now()->getTimestamp();

            return new NotifikasiBilling(
                nomorPesanan: $nomorPesanan,
                idTenant: $bagian['IdTenant'],
                uuidPembayaran: $bagian['Uuid'],
                status: $lewat ? StatusPembayaranGerbang::Kedaluwarsa : StatusPembayaranGerbang::Menunggu,
                jumlah: '',
                idTransaksi: '',
                statusAsli: $lewat ? 'EXPIRED' : 'PENDING',
            );
        }

        return $this->UraiStatus($nomorPesanan, $respons, '');
    }

    private function TanyaStatus(string $nomorPesanan): ?Response
    {
        if (! $this->CekAktif() || NomorPesananBilling::Urai($nomorPesanan) === null) {
            return null;
        }

        $target = self::TARGET_STATUS.rawurlencode($nomorPesanan);

        try {
            return Http::timeout(15)->acceptJson()
                ->withHeaders(ProtokolDoku::BuatHeader($this->IdKlien(), $this->KunciRahasia(), $target, null))
                ->get($this->AlamatDasar().$target);
        } catch (ConnectionException) {
            return null;
        }
    }

    private function UraiStatus(string $nomorPesanan, Response $respons, string $idTransaksi): ?NotifikasiBilling
    {
        $bagian = NomorPesananBilling::Urai($nomorPesanan);
        $statusAsli = $respons->json('transaction.status');
        $nomorDiJawaban = $respons->json('order.invoice_number');

        if ($bagian === null || ! $respons->successful() || ! is_string($statusAsli) || $statusAsli === '' || ($nomorDiJawaban !== null && $nomorDiJawaban !== $nomorPesanan)) {
            return null;
        }

        return new NotifikasiBilling(
            nomorPesanan: $nomorPesanan,
            idTenant: $bagian['IdTenant'],
            uuidPembayaran: $bagian['Uuid'],
            status: ProtokolDoku::PetakanStatus($statusAsli),
            jumlah: self::AmbilTeks($respons->json('order.amount')),
            idTransaksi: $idTransaksi !== '' ? $idTransaksi : self::AmbilTeks($respons->json('transaction.original_request_id')),
            statusAsli: $statusAsli,
        );
    }

    private static function AmbilTeks(mixed $nilai): string
    {
        return is_string($nilai) || is_int($nilai) || is_float($nilai) ? trim((string) $nilai) : '';
    }

    private function AlamatDasar(): string
    {
        return ProtokolDoku::AmbilAlamatDasar($this->CekSandbox());
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
        $kunci = $this->KunciRahasia();

        return mb_substr($kunci !== '' ? str_replace($kunci, '••••', $pesan) : $pesan, 0, 300);
    }
}
