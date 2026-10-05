<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Tenant\Aksi\CatatAtribusiMitra;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P-12: tenant baru diatribusikan ke mitra dari cookie tautan (`kode|unix detik klik pertama`); cookie lalu dihapus.
 * Gagal tidak menggagalkan pendaftaran. Dipakai pendaftaran email dan pendaftaran lewat Google (D-57).
 */
final class PencatatMitraPendaftaran
{
    public function Catat(Request $permintaan, int $idTenant): void
    {
        $nilai = $permintaan->cookie(PendaftaranKontroler::COOKIE_MITRA);

        if (! is_string($nilai) || preg_match('/^([A-Z0-9-]{3,20})\|(\d{1,12})$/', $nilai, $cocok) !== 1) {
            return;
        }

        Cookie::queue(Cookie::forget(PendaftaranKontroler::COOKIE_MITRA));

        try {
            app(CatatAtribusiMitra::class)->Jalankan($idTenant, $cocok[1], CarbonImmutable::createFromTimestamp((int) $cocok[2]));
        } catch (Throwable $galat) {
            Log::error('Atribusi mitra gagal dicatat.', ['Pesan' => $galat->getMessage()]);
        }
    }
}
