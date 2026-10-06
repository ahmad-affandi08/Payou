<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Integrasi\ApiPublik\Enum\StatusKirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Layanan\PenjagaAlamatWebhook;
use App\Domain\Integrasi\ApiPublik\Model\KirimanWebhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * X7 bagian 2 (PRD §16.4): satu percobaan kirim satu `KirimanWebhook`. Badan JSON ditandatangani HMAC-SHA256 atas
 * `{X-Waktu-Kirim}.{badan}` dengan rahasia webhook (`X-Tanda-Tangan: sha256=<hex>`), `X-Id-Peristiwa` untuk dedup.
 * Respons 2xx = Terkirim; selain itu coba ulang pada [JADWAL_COBA_ULANG_MENIT] lalu Gagal. Kiriman diklaim dulu dengan
 * memajukan `BerikutnyaPada` (sewa) sehingga tugas antrean & perintah terjadwal tidak mengirim kiriman yang sama
 * bersamaan. Tanpa ikut redirect; batas waktu 10 detik.
 */
final class KirimKirimanWebhook
{
    /** Jeda coba ulang setelah percobaan gagal ke-1..5 (1m, 5m, 30m, 2j, 12j). */
    public const JADWAL_COBA_ULANG_MENIT = [1, 5, 30, 120, 720];

    public const MENIT_SEWA = 5;

    public const BATAS_CUPLIKAN = 500;

    public function __construct(private readonly PenjagaAlamatWebhook $penjaga) {}

    /** @return bool true bila kiriman diproses (diklaim) oleh pemanggil ini. */
    public function Jalankan(int $idKiriman): bool
    {
        $sekarang = now();
        $diklaim = KirimanWebhook::query()->whereKey($idKiriman)
            ->where('Status', StatusKirimanWebhook::Menunggu->value)
            ->where(fn ($q) => $q->whereNull('BerikutnyaPada')->orWhere('BerikutnyaPada', '<=', $sekarang))
            ->update(['BerikutnyaPada' => $sekarang->copy()->addMinutes(self::MENIT_SEWA)]);

        if ($diklaim === 0) {
            return false;
        }

        $kiriman = KirimanWebhook::query()->with('Webhook')->findOrFail($idKiriman);
        $webhook = $kiriman->Webhook;

        if ($webhook->trashed() || ! $webhook->Aktif) {
            $this->Simpan($kiriman, StatusKirimanWebhook::Gagal, null, 'Webhook sudah dinonaktifkan atau dihapus.');

            return true;
        }

        $hasilPeriksa = $this->penjaga->Periksa($webhook->Url);

        if (! $hasilPeriksa['Aman']) {
            $this->CatatGagal($kiriman, null, $hasilPeriksa['Alasan']);

            return true;
        }

        $badan = (string) json_encode($kiriman->Muatan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $waktu = (string) $sekarang->getTimestamp();

        try {
            $respons = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'PAYOUNG-Webhook/1',
                'X-Id-Peristiwa' => $kiriman->Uuid,
                'X-Peristiwa' => $kiriman->Peristiwa,
                'X-Waktu-Kirim' => $waktu,
                'X-Tanda-Tangan' => 'sha256='.self::HitungTandaTangan($webhook->Rahasia, $waktu, $badan),
            ])->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$hasilPeriksa['Host']}:{$hasilPeriksa['Port']}:{$hasilPeriksa['Ip']}"]],
            ])->timeout(10)->connectTimeout(5)->withBody($badan, 'application/json')->post($webhook->Url);
        } catch (ConnectionException $e) {
            $this->CatatGagal($kiriman, null, 'Tidak bisa terhubung: '.Str::limit($e->getMessage(), 300));

            return true;
        }

        $cuplikan = Str::limit(trim($respons->body()), self::BATAS_CUPLIKAN - 3);

        if ($respons->successful()) {
            $this->Simpan($kiriman, StatusKirimanWebhook::Terkirim, $respons->status(), $cuplikan === '' ? null : $cuplikan);
        } else {
            $this->CatatGagal($kiriman, $respons->status(), $cuplikan === '' ? null : $cuplikan);
        }

        return true;
    }

    /** Penerima menghitung ulang ini dengan rahasia yang sama lalu membandingkan (hash_equals). */
    public static function HitungTandaTangan(string $rahasia, string $waktu, string $badan): string
    {
        return hash_hmac('sha256', $waktu.'.'.$badan, $rahasia);
    }

    private function CatatGagal(KirimanWebhook $kiriman, ?int $kode, ?string $cuplikan): void
    {
        $percobaan = $kiriman->Percobaan + 1;
        $jeda = self::JADWAL_COBA_ULANG_MENIT[$percobaan - 1] ?? null;
        $kiriman->Percobaan = $percobaan;
        $this->Simpan(
            $kiriman,
            $jeda === null ? StatusKirimanWebhook::Gagal : StatusKirimanWebhook::Menunggu,
            $kode,
            $cuplikan,
            $jeda === null ? null : now()->addMinutes($jeda),
        );
    }

    private function Simpan(KirimanWebhook $kiriman, StatusKirimanWebhook $status, ?int $kode, ?string $cuplikan, mixed $berikutnya = null): void
    {
        $kiriman->forceFill([
            'Status' => $status,
            'KodeRespons' => $kode,
            'CuplikanRespons' => $cuplikan === null ? null : mb_substr($cuplikan, 0, self::BATAS_CUPLIKAN),
            'BerikutnyaPada' => $berikutnya,
            'TerkirimPada' => $status === StatusKirimanWebhook::Terkirim ? now() : $kiriman->TerkirimPada,
        ])->save();
    }
}
