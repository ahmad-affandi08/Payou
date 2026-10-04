<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use App\Domain\Pengelola\Integrasi\Kueri\PeringatanIntegrasi;
use App\Domain\Pengelola\Operasional\Kueri\KondisiOperasional;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Kueri\SuperAdminAktif;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Middleware;

/**
 * Perantara Inertia khusus Platform Pengelola: view root & bundle JS terpisah dari tenant (PRD §13.8).
 */
final class BagikanDataInertiaPengelola extends Middleware
{
    protected $rootView = 'Pengelola';

    public function __construct(
        private readonly SuperAdminAktif $superAdminAktif,
        private readonly PeringatanIntegrasi $peringatanIntegrasi,
        // P-11
        private readonly KondisiOperasional $kondisiOperasional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        return [
            ...parent::share($request),
            'NamaAplikasi' => config('app.name'),
            'Lingkungan' => app()->isProduction() ? 'Produksi' : (app()->environment('staging') ? 'Staging' : 'Lokal'),
            // `always`: ikut di setiap respons (juga muat ulang sebagian), supaya pesan lama tidak menempel dan dialog hasil
            // tidak muncul lagi setiap polling.
            'Kilat' => Inertia::always(fn () => $request->session()->get('Kilat')),
            'Pengguna' => fn () => $pengguna instanceof PenggunaPengelola ? [
                'Uuid' => $pengguna->Uuid,
                'Nama' => $pengguna->Nama,
                'Email' => $pengguna->Email,
                'KodePeran' => $pengguna->AmbilKodePeran(),
                'Izin' => $pengguna->AmbilDaftarIzin(),
            ] : null,
            // BR-P01.1: peringatan selama Super Admin aktif kurang dari 2.
            'PeringatanSuperAdmin' => fn () => $pengguna instanceof PenggunaPengelola
                && $pengguna->PunyaPeran(PeranPengelolaBawaan::SuperAdmin)
                && $this->superAdminAktif->Hitung() < (int) config('pengelola.MinimalSuperAdminAktif'),
            // BR-P05.3, BR-P05.5: banner status integrasi untuk semua anggota yang sudah masuk.
            'PeringatanIntegrasi' => fn () => $pengguna instanceof PenggunaPengelola ? $this->peringatanIntegrasi->Ambil() : [],
            // P-11 BR-P11.1: banner kondisi operasional (scheduler, antrean, backup) dihitung dari data terkini.
            'PeringatanOperasional' => fn () => $pengguna instanceof PenggunaPengelola ? array_values($this->kondisiOperasional->AmbilMasalah()) : [],
        ];
    }
}
