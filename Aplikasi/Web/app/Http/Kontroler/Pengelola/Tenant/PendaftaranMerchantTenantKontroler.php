<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Tenant;

use App\Domain\Integrasi\Enum\StatusPendaftaranMerchant;
use App\Domain\Pengelola\Tenant\Aksi\AturPenampungQrisMerchantTenant;
use App\Domain\Pengelola\Tenant\Aksi\SegarkanPendaftaranMerchantTenant;
use App\Domain\Tenant\Model\Tenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Tenant\AturPenampungQrisPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * Pendaftaran merchant pembayaran (DOKU Partner) tenant di konsol: segarkan status dan isi manual penampung merchant
 * QRIS. Izin `integrasi.kelola`; penampung menambah 2FA baru. Pengelola tidak pernah melihat foto (sudah dihapus),
 * NIK, atau nomor rekening utuh.
 */
final class PendaftaranMerchantTenantKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Segarkan(Tenant $tenant, SegarkanPendaftaranMerchantTenant $segarkan): RedirectResponse
    {
        $pendaftaran = $segarkan->Jalankan($this->AmbilPelaku(), $tenant);

        return back()->with('Kilat', match ($pendaftaran->Status) {
            StatusPendaftaranMerchant::Aktif => "Status {$tenant->Nama} disegarkan: disetujui DOKU.",
            StatusPendaftaranMerchant::Ditolak => "Status {$tenant->Nama} disegarkan: ditolak DOKU.",
            default => "Status {$tenant->Nama} disegarkan: {$pendaftaran->Status->AmbilLabel()}.",
        });
    }

    public function AturPenampungQris(Tenant $tenant, AturPenampungQrisPermintaan $permintaan, AturPenampungQrisMerchantTenant $atur): RedirectResponse
    {
        $atur->Jalankan($this->AmbilPelaku(), $tenant, $permintaan->AmbilIdPedagang(), $permintaan->AmbilIdTerminal());

        return back()->with('Kilat', 'ID pedagang dan terminal QRIS disimpan (hanya penampung, belum dipakai sistem).');
    }
}
