<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Billing;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Gerbang pembayaran untuk **tagihan langganan Payoung sendiri** (P-08 langkah 3, BR-P08.11): akun Midtrans milik
 * platform, dikonfigurasi di konsol pengelola sebagai integrasi `GerbangBilling` (P-05).
 *
 * Sengaja terpisah dari `Domain\Integrasi\GerbangPembayaran` (QRIS milik toko, D-19): di sana akun dan kredensialnya
 * milik tenant dan dipakai menagih pembeli, di sini akunnya milik Payoung dan dipakai menagih tenant. Keduanya bisa
 * aktif bersamaan dengan penyedia yang sama tanpa saling memakai kredensial.
 *
 * Alurnya Snap: Payoung membuat transaksi lalu tenant membayar di popup Snap.js. Pelunasan tagihan **tidak** pernah
 * dari respons popup (bisa dipalsukan peramban), hanya dari notifikasi webhook bertanda tangan.
 */
final class GerbangBillingPlatform
{
    private const URL_SANDBOX = 'https://app.sandbox.midtrans.com';

    private const URL_PRODUKSI = 'https://app.midtrans.com';

    private const URL_API_SANDBOX = 'https://api.sandbox.midtrans.com';

    private const URL_API_PRODUKSI = 'https://api.midtrans.com';

    /** Batas bayar satu transaksi Snap; setelahnya tenant membuat transaksi baru dari tagihan yang sama. */
    public const MENIT_KEDALUWARSA = 60;

    public function CekAktif(): bool
    {
        return $this->KunciServer() !== '' && $this->KunciKlien() !== '';
    }

    public function CekSandbox(): bool
    {
        return $this->Pengaturan('Mode') !== 'Produksi';
    }

    public function KunciKlien(): string
    {
        return $this->Pengaturan('KunciKlien');
    }

    /** Alamat Snap.js yang dimuat halaman tagihan; harus sesuai lingkungan agar token dikenali. */
    public function UrlSnapJs(): string
    {
        return $this->AlamatDasar().'/snap/snap.js';
    }

    /**
     * @throws GalatGerbang
     */
    public function BuatTransaksiSnap(
        string $nomorPesanan,
        Uang $total,
        string $namaTagihan,
        string $namaPembayar,
        string $emailPembayar,
    ): HasilSnap {
        $jumlah = (int) $total->KeString();

        try {
            $respons = Http::timeout(15)->acceptJson()
                ->withBasicAuth($this->KunciServer(), '')
                ->post($this->AlamatDasar().'/snap/v1/transactions', [
                    'transaction_details' => ['order_id' => $nomorPesanan, 'gross_amount' => $jumlah],
                    'item_details' => [['id' => 'LANGGANAN', 'price' => $jumlah, 'quantity' => 1, 'name' => mb_substr($namaTagihan, 0, 50)]],
                    'customer_details' => ['first_name' => mb_substr($namaPembayar, 0, 50), 'email' => $emailPembayar],
                    'expiry' => ['unit' => 'minute', 'duration' => self::MENIT_KEDALUWARSA],
                    'credit_card' => ['secure' => true],
                ]);
        } catch (ConnectionException) {
            // Transaksi mungkin sudah terbentuk di Midtrans; pembayaran dibiarkan Menunggu dan webhook tetap berlaku.
            throw new GalatGerbang('Gerbang pembayaran tidak bisa dihubungi. Coba lagi sebentar lagi.', tidakPasti: true);
        }

        $token = $respons->json('token');
        $redirect = $respons->json('redirect_url');

        if (! $respons->successful() || ! is_string($token) || $token === '' || ! is_string($redirect) || $redirect === '') {
            throw new GalatGerbang(
                $this->Saring("Gerbang pembayaran menolak permintaan (HTTP {$respons->status()}): ".$this->PesanPenyedia($respons)),
                // Respons sukses yang tidak terbaca berarti transaksinya mungkin ada; 5xx juga belum tentu gagal.
                tidakPasti: $respons->successful() || $respons->serverError(),
            );
        }

        return new HasilSnap($token, $redirect);
    }

    /** Midtrans mengirim `error_messages` berupa daftar teks; apa pun bentuk lainnya diringkas jadi pesan umum. */
    private function PesanPenyedia(Response $respons): string
    {
        $pesan = $respons->json('error_messages');

        if (! is_array($pesan)) {
            return 'transaksi tidak dibuat.';
        }

        $teks = array_filter(array_map(static fn (mixed $baris): string => is_string($baris) ? $baris : '', $pesan));

        return $teks === [] ? 'transaksi tidak dibuat.' : implode('; ', $teks);
    }

    public function CekTandaTanganSah(Request $permintaan): bool
    {
        $kunci = $this->KunciServer();
        $nomor = (string) $permintaan->input('order_id');
        $kodeStatus = (string) $permintaan->input('status_code');
        $jumlah = (string) $permintaan->input('gross_amount');
        $tanda = (string) $permintaan->input('signature_key');

        if ($kunci === '' || $nomor === '' || $tanda === '') {
            return false;
        }

        return hash_equals(hash('sha512', $nomor.$kodeStatus.$jumlah.$kunci), $tanda);
    }

    /**
     * Notifikasi HTTP Midtrans: `signature_key = SHA512(order_id + status_code + gross_amount + ServerKey)`.
     * Tanda tangan tidak sah, nomor pesanan bukan milik Payoung, atau gerbang belum aktif = null.
     */
    public function UraiNotifikasi(Request $permintaan): ?NotifikasiBilling
    {
        if (! $this->CekTandaTanganSah($permintaan)) {
            return null;
        }

        $nomor = (string) $permintaan->input('order_id');
        $jumlah = (string) $permintaan->input('gross_amount');

        $bagian = NomorPesananBilling::Urai($nomor);

        if ($bagian === null) {
            return null;
        }

        $statusAsli = (string) $permintaan->input('transaction_status');

        return new NotifikasiBilling(
            nomorPesanan: $nomor,
            idTenant: $bagian['IdTenant'],
            uuidPembayaran: $bagian['Uuid'],
            status: self::PetakanStatus($statusAsli, (string) $permintaan->input('fraud_status')),
            jumlah: $jumlah,
            idTransaksi: (string) $permintaan->input('transaction_id'),
            statusAsli: $statusAsli,
        );
    }

    /**
     * Rekonsiliasi (P-08, v4.06): tanyakan status satu transaksi ke API status Midtrans
     * (`GET /v2/{order_id}/status`), untuk pembayaran yang notifikasi webhook-nya tidak pernah tiba. Hasilnya dibentuk
     * sama dengan notifikasi webhook supaya diproses jalur yang sama (`TerimaNotifikasiBillingLangganan`).
     *
     * - Transaksi tidak ada di Midtrans (popup Snap dibuka tetapi tidak pernah dipilih cara bayarnya) dan sudah lewat
     *   `MENIT_KEDALUWARSA` sejak `$dibuatPada` = `Kedaluwarsa`; sebelum itu = `Menunggu`.
     * - Gagal menghubungi gerbang, respons tak terbaca, atau gerbang belum aktif = null (dicoba lagi putaran berikutnya).
     */
    public function CekStatus(string $nomorPesanan, DateTimeInterface $dibuatPada): ?NotifikasiBilling
    {
        $bagian = NomorPesananBilling::Urai($nomorPesanan);

        if (! $this->CekAktif() || $bagian === null) {
            return null;
        }

        try {
            $respons = Http::timeout(15)->acceptJson()
                ->withBasicAuth($this->KunciServer(), '')
                ->get(($this->CekSandbox() ? self::URL_API_SANDBOX : self::URL_API_PRODUKSI).'/v2/'.rawurlencode($nomorPesanan).'/status');
        } catch (ConnectionException) {
            return null;
        }

        $kodeStatus = (string) $respons->json('status_code');

        if ($respons->status() === 404 || $kodeStatus === '404') {
            $lewat = $dibuatPada->getTimestamp() + (self::MENIT_KEDALUWARSA + 15) * 60 < CarbonImmutable::now()->getTimestamp();

            return new NotifikasiBilling(
                nomorPesanan: $nomorPesanan,
                idTenant: $bagian['IdTenant'],
                uuidPembayaran: $bagian['Uuid'],
                status: $lewat ? StatusPembayaranGerbang::Kedaluwarsa : StatusPembayaranGerbang::Menunggu,
                jumlah: '0',
                idTransaksi: '',
                statusAsli: $lewat ? 'expire' : 'pending',
            );
        }

        $statusAsli = $respons->json('transaction_status');

        if (! $respons->successful() || ! is_string($statusAsli) || $statusAsli === '' || $respons->json('order_id') !== $nomorPesanan) {
            return null;
        }

        return new NotifikasiBilling(
            nomorPesanan: $nomorPesanan,
            idTenant: $bagian['IdTenant'],
            uuidPembayaran: $bagian['Uuid'],
            status: self::PetakanStatus($statusAsli, (string) $respons->json('fraud_status')),
            jumlah: (string) $respons->json('gross_amount'),
            idTransaksi: (string) $respons->json('transaction_id'),
            statusAsli: $statusAsli,
        );
    }

    /**
     * Berbeda dari adaptor QRIS toko, `capture` di sini hanya dianggap lunas bila `fraud_status` = accept: kartu
     * kredit bisa berhenti di `challenge` (menunggu tinjauan manual) dan uangnya belum tentu jadi masuk.
     */
    private static function PetakanStatus(string $status, string $statusFraud): StatusPembayaranGerbang
    {
        return match ($status) {
            'settlement' => StatusPembayaranGerbang::Lunas,
            'capture' => $statusFraud === 'accept' ? StatusPembayaranGerbang::Lunas : StatusPembayaranGerbang::Menunggu,
            'expire' => StatusPembayaranGerbang::Kedaluwarsa,
            'cancel', 'deny', 'failure' => StatusPembayaranGerbang::Gagal,
            default => StatusPembayaranGerbang::Menunggu,
        };
    }

    private function AlamatDasar(): string
    {
        return $this->CekSandbox() ? self::URL_SANDBOX : self::URL_PRODUKSI;
    }

    private function KunciServer(): string
    {
        return trim((string) config('integrasi.GerbangBilling.Kredensial.KunciServer', ''));
    }

    private function Pengaturan(string $kunci): string
    {
        return trim((string) config('integrasi.GerbangBilling.Pengaturan.'.$kunci, ''));
    }

    private function Saring(string $pesan): string
    {
        $kunci = $this->KunciServer();

        return mb_substr($kunci !== '' ? str_replace($kunci, '••••', $pesan) : $pesan, 0, 300);
    }
}
