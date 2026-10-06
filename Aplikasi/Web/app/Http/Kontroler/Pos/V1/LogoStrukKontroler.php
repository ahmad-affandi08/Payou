<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Layanan\PenyimpanLogoTenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /api/pos/v1/logo-struk` (PRD v1.79): logo usaha tenant perangkat untuk dicetak di kepala struk (disimpan
 * offline di perangkat). Tanpa logo atau logo dimatikan di pengaturan struk = 404.
 */
final class LogoStrukKontroler extends Kontroler
{
    public function Unduh(Request $permintaan, PengaturanStrukTenant $pengaturan, PenyimpanLogoTenant $penyimpan, OutletPenjualan $outlet): StreamedResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $path = $pengaturan->AmbilPathLogo($perangkat->IdTenant, $outlet->Ambil($perangkat->IdOutlet, $perangkat->Id)?->idMerek);
        abort_if($path === null, 404);

        return $penyimpan->Unduh($path);
    }
}
