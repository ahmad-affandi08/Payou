<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Kueri\TokenApiBerdasarkanToken;
use App\Domain\Integrasi\ApiPublik\Model\TokenApiTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use App\Http\Respons\GalatApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi token API publik untuk `/api/v1/*` (X7, PRD §16.1 lapisan Publik): Bearer `payoung_{IdTenant}_{rahasia}`
 * yang belum dicabut/kedaluwarsa menetapkan tenant aktif. Paket tenant wajib masih punya fitur `api.publik` (turun
 * paket = 403 `FiturTidakTersedia`). Parameter perantara = cakupan yang diwajibkan rute (403 `CakupanTidakCukup`).
 */
final class AutentikasiTokenApi
{
    public const ATRIBUT = 'TokenApi';

    public function __construct(
        private readonly TokenApiBerdasarkanToken $cariToken,
        private readonly PemeriksaFiturTenant $fitur,
        private readonly KonteksTenant $konteks,
    ) {}

    public function handle(Request $request, Closure $next, ?string $cakupan = null): Response
    {
        $bearer = $request->bearerToken();
        $token = is_string($bearer) ? $this->cariToken->Cari($bearer) : null;

        if ($token === null) {
            return GalatApi::Buat('TokenApiTidakValid', 'Token API tidak dikenal, sudah dicabut, atau kedaluwarsa.', 401);
        }

        if (! $this->fitur->CekAktif($token->IdTenant, 'api.publik')) {
            $this->konteks->Kosongkan();

            return GalatApi::Buat('FiturTidakTersedia', 'API publik tidak termasuk paket langganan usaha ini.', 403);
        }

        if ($cakupan !== null && ! $token->CekCakupan($cakupan)) {
            return GalatApi::Buat('CakupanTidakCukup', "Token ini tidak punya akses {$cakupan}.", 403, ['Dibutuhkan' => $cakupan]);
        }

        $request->attributes->set(self::ATRIBUT, $token);

        return $next($request);
    }

    public static function AmbilToken(Request $request): TokenApiTenant
    {
        $token = $request->attributes->get(self::ATRIBUT);
        abort_unless($token instanceof TokenApiTenant, 401);

        return $token;
    }
}
