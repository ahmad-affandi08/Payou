<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\ProfilPemilik;
use App\Domain\Organisasi\Layanan\PenentuWajibDuaFaktor;
use App\Http\Respons\GalatApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant aktif Aplikasi Owner dari header `X-Tenant: {UuidTenant}` (OWN-01). Keanggotaan aktif diperiksa ulang setiap
 * request (anggota yang dikeluarkan langsung kehilangan akses); tenant yang bukan milik pengguna diperlakukan sama
 * dengan yang tidak ada (403 `TenantTidakDiizinkan`). Sama dengan back-office (`WajibDuaFaktorTenant`), peran yang
 * diwajibkan 2FA oleh paket tenant ditolak (403 `DuaFaktorWajib`) sampai 2FA diaktifkan. Endpoint pemilik v1 hanya
 * membaca data, sehingga tenant yang langganannya ditangguhkan tetap boleh dilihat (sama dengan `BatasiTenantDitangguhkan`).
 * Berjalan setelah `AutentikasiPemilik`.
 */
final class IdentifikasiTenantPemilik
{
    public const HEADER = 'X-Tenant';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilPemilik $profil,
        private readonly PenentuWajibDuaFaktor $wajibDuaFaktor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = AutentikasiPemilik::AmbilPengguna($request);
        $uuid = $request->header(self::HEADER);
        $idTenant = is_string($uuid) && $uuid !== '' ? $this->profil->CariIdTenant($pengguna, trim($uuid)) : null;

        if ($idTenant === null) {
            return GalatApi::Buat('TenantTidakDiizinkan', 'Anda bukan anggota aktif usaha ini. Pilih usaha lain.', 403);
        }

        // D-38: selama trial kewajiban 2FA ditunda, sama dengan back-office.
        // D-57: token dari Masuk dengan Google menggantikan 2FA.
        if (! $pengguna->CekDuaFaktorAktif() && ! AutentikasiPemilik::AmbilToken($request)->MasukGoogle && $this->wajibDuaFaktor->CekWajib($pengguna->Id, $idTenant) && ! $this->wajibDuaFaktor->CekDitunda($idTenant)) {
            return GalatApi::Buat('DuaFaktorWajib', 'Paket langganan usaha ini mewajibkan verifikasi dua langkah untuk peran Anda. Aktifkan dulu di menu Keamanan akun back-office.', 403);
        }

        $this->konteks->Atur($idTenant);

        return $next($request);
    }
}
