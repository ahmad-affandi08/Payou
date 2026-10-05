<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Layanan\PenentuWajibDuaFaktor;
use App\Domain\Organisasi\Model\Pengguna;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * §20.2 + BR-00.8: Owner, Admin, dan Akuntan tenant berpaket Bisnis ke atas wajib mengaktifkan 2FA sebelum membuka menu `/kelola` lain.
 * Halaman keamanan akun & persetujuan legal tetap terbuka agar ia bisa menyelesaikannya. Berjalan setelah
 * `IdentifikasiTenantSesi`. Verifikasi kode saat masuk tidak diurus di sini: pengguna ber-2FA baru dianggap masuk
 * setelah kodenya terverifikasi (SesiKontroler). D-38: selama trial kewajiban ditunda (hanya banner, lihat
 * `BagikanDataInertia` prop `PengingatDuaFaktor`).
 */
final class WajibDuaFaktorTenant
{
    public const RUTE_KEAMANAN = 'kelola.keamanan';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenentuWajibDuaFaktor $penentu,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = Auth::guard('web')->user();
        $idTenant = $this->konteks->Ambil();

        if ($request->routeIs(self::RUTE_KEAMANAN, self::RUTE_KEAMANAN.'.*', WajibPersetujuanLegal::RUTE_PERSETUJUAN.'*')
            || ! $pengguna instanceof Pengguna
            || $idTenant === null
            || $pengguna->CekDuaFaktorAktif()
            // D-57: Masuk dengan Google menggantikan 2FA.
            || $request->session()->get(SesiAutentikasiTenant::MASUK_GOOGLE) === true
            || ! $this->penentu->CekWajib($pengguna->Id, $idTenant)
            || $this->penentu->CekDitunda($idTenant)) {
            return $next($request);
        }

        return redirect()->route(self::RUTE_KEAMANAN)
            ->with('Kilat', 'Paket langganan usaha ini mewajibkan verifikasi dua langkah untuk peran Anda. Aktifkan dulu sebelum membuka menu lain.');
    }
}
