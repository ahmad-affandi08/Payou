<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Closure;

/**
 * Nama usaha yang tampil kepada **pelanggan** (D-77): struk, pesan WhatsApp/email, pengingat, dan halaman publik.
 *
 * Pelanggan mengenal nama outlet ("Lil' Escape"), bukan nama akun pemilik ("Sudirman Group"), jadi nama akun hanya
 * cadangan terakhir:
 * - `UntukOutlet`: nama outlet itu bila diketahui.
 * - `UntukTenant`: bila konteksnya tidak menunjuk satu outlet (pengingat piutang, kampanye), nama outlet aktif satu-satunya;
 *   bila ada beberapa outlet, nama akun (nama payung usaha yang diisi pemilik di profil usaha).
 */
final class NamaTampilUsaha
{
    public function __construct(private readonly ProfilTenant $profil, private readonly KonteksTenant $konteks) {}

    public function UntukOutlet(?int $idOutlet, int $idTenant): string
    {
        return $this->DalamTenant($idTenant, function () use ($idOutlet, $idTenant): string {
            if ($idOutlet !== null) {
                $nama = Outlet::query()->whereKey($idOutlet)->value('Nama');

                if (is_string($nama) && trim($nama) !== '') {
                    return $nama;
                }
            }

            return $this->HitungUntukTenant($idTenant);
        });
    }

    public function UntukTenant(int $idTenant): string
    {
        return $this->DalamTenant($idTenant, fn (): string => $this->HitungUntukTenant($idTenant));
    }

    private function HitungUntukTenant(int $idTenant): string
    {
        $outlet = Outlet::query()->where('Status', StatusOrganisasi::Aktif->value)->orderBy('Id')->limit(2)->pluck('Nama');

        return $outlet->count() === 1 && trim((string) $outlet->first()) !== '' ? (string) $outlet->first() : $this->profil->Ambil($idTenant)['Nama'];
    }

    /**
     * Halaman publik memanggil ini sesudah konteks tenantnya dikosongkan, jadi konteks dipasang sementara di sini
     * (scope `MilikTenant` wajib) lalu dikembalikan seperti semula.
     *
     * @param  Closure(): string  $kerja
     */
    private function DalamTenant(int $idTenant, Closure $kerja): string
    {
        $sebelum = $this->konteks->Ambil();
        $this->konteks->Atur($idTenant);

        try {
            return $kerja();
        } finally {
            if ($sebelum === null) {
                $this->konteks->Kosongkan();
            } else {
                $this->konteks->Atur($sebelum);
            }
        }
    }
}
