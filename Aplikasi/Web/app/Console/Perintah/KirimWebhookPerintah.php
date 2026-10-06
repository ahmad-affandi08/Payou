<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Aksi\ProsesKirimanWebhookJatuhTempo;
use App\Domain\Integrasi\ApiPublik\Enum\StatusKirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Model\KirimanWebhook;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use Illuminate\Console\Command;

/**
 * X7 bagian 2 (PRD §16.4 "dikirim oleh queue via cron"): tiap menit mengantrekan kiriman webhook yang jatuh tempo coba
 * ulang. Per tenant dengan `KonteksTenant` diatur sehingga semua kueri tetap lewat scope `MilikTenant`.
 */
final class KirimWebhookPerintah extends Command
{
    protected $signature = 'integrasi:kirim-webhook';

    protected $description = 'Mengantrekan kiriman webhook keluar yang jatuh tempo coba ulang (X7).';

    public function handle(KonteksPengelola $pengelola, KonteksTenant $konteks, ProsesKirimanWebhookJatuhTempo $proses): int
    {
        $sebelumnya = $konteks->Ambil();
        $diantrekan = 0;

        try {
            // Hanya tenant yang punya kiriman jatuh tempo (audit kinerja skala besar: dulu semua tenant dikunjungi tiap menit).
            // Jatuh tempo dikirim, dan tenant dengan log selesai yang melewati retensi tetap dikunjungi untuk dipangkas.
            $tenant = array_unique([
                ...$pengelola->IdTenantDenganPekerjaan(KirimanWebhook::class, fn ($q) => $q->where('Status', StatusKirimanWebhook::Menunggu->value)->where('BerikutnyaPada', '<=', now())),
                ...$pengelola->IdTenantDenganPekerjaan(KirimanWebhook::class, fn ($q) => $q->whereIn('Status', [StatusKirimanWebhook::Terkirim->value, StatusKirimanWebhook::Gagal->value])->where('DibuatPada', '<', now()->subDays(ProsesKirimanWebhookJatuhTempo::HARI_RETENSI))),
            ]);

            foreach ($tenant as $idTenant) {
                $konteks->Atur($idTenant);
                $diantrekan += $proses->Jalankan($idTenant);
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line("{$diantrekan} kiriman webhook diantrekan.");

        return self::SUCCESS;
    }
}
