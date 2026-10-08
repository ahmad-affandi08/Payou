<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Enum\StatusSubAkunPembayaran;
use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\Model\SubAkunPembayaran;
use App\Domain\Integrasi\SubAkun\KlienSubAkunDoku;
use App\Domain\Pengelola\Tenant\Kueri\PemilikTenant;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Membuat sub account DOKU untuk satu tenant dari konsol pengelola (wadah untuk rute dana QRIS ke rekening tenant).
 * Memakai akun induk DOKU platform (kredensial `DokuBilling`), email Owner tenant, dan nama usaha.
 *
 * - Idempoten: bila `IdSubAkun` sudah ada, DOKU tidak dipanggil lagi dan baris yang ada dikembalikan.
 * - Galat 4xx pasti dari DOKU = `Gagal` + `PesanGalat` (boleh dicoba lagi setelah diperbaiki). 5xx/jaringan/respons tak
 *   terbaca = tetap `Menunggu` dengan pesan (sub account mungkin sudah terbentuk di DOKU; periksa dasbor lalu coba lagi).
 * - Gerbang platform belum diisi = galat `GerbangBelumAktif`, tanpa memanggil DOKU.
 * - Satu pembuatan per tenant sekaligus (kunci cache), dan tercatat di `LogAuditPengelola` (`tenant.subakun.buat`).
 */
final class BuatSubAkunPembayaranTenant
{
    public const AKSI_AUDIT = 'tenant.subakun.buat';

    private const DETIK_KUNCI = 30;

    public function __construct(
        private readonly KlienSubAkunDoku $klien,
        private readonly PemilikTenant $pemilik,
        private readonly KonteksPengelola $konteks,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant): SubAkunPembayaran
    {
        if (! $this->klien->CekAktif()) {
            throw new PelanggaranAturanBisnis('GerbangBelumAktif', 'Isi kredensial DOKU platform dulu di menu Integrasi (Akun DOKU Payoung), lalu coba lagi.', statusHttp: 409);
        }

        $email = $this->pemilik->Ambil($tenant->Id)[0]['Email'] ?? '';

        if ($email === '') {
            throw new PelanggaranAturanBisnis('PemilikTenantTidakAda', 'Tenant ini belum punya Owner aktif dengan email, jadi sub account belum bisa dibuat.');
        }

        try {
            return Cache::lock("sub-akun-pembayaran:{$tenant->Id}", self::DETIK_KUNCI)->block(5, fn (): SubAkunPembayaran => $this->Buat($pelaku, $tenant, $email));
        } catch (LockTimeoutException) {
            throw new PelanggaranAturanBisnis('SedangDiproses', 'Pembuatan sub account tenant ini sedang berjalan. Muat ulang halaman sebentar lagi.', statusHttp: 409);
        }
    }

    private function Buat(PenggunaPengelola $pelaku, Tenant $tenant, string $email): SubAkunPembayaran
    {
        return $this->konteks->JalankanLintasTenant('Membuat sub account pembayaran DOKU', function () use ($pelaku, $tenant, $email): SubAkunPembayaran {
            $subAkun = SubAkunPembayaran::query()->firstOrCreate(
                ['IdTenant' => $tenant->Id, 'Penyedia' => SubAkunPembayaran::PENYEDIA_DOKU],
                ['Status' => StatusSubAkunPembayaran::Menunggu, 'DibuatOleh' => $pelaku->Id],
            );

            if ($subAkun->CekSudahAda()) {
                return $subAkun;
            }

            $lama = ['Status' => $subAkun->Status->value, 'PesanGalat' => $subAkun->PesanGalat];

            try {
                $hasil = $this->klien->BuatSubAkun($email, $tenant->Nama);
                $subAkun->update([
                    'IdSubAkun' => $hasil->idSubAkun,
                    'Status' => $hasil->status,
                    'PesanGalat' => null,
                    'DiubahOleh' => $pelaku->Id,
                ]);
            } catch (GalatGerbang $galat) {
                // Hanya penolakan pasti yang jadi Gagal; yang tidak pasti tetap Menunggu supaya boleh dicoba lagi.
                $subAkun->update([
                    'Status' => $galat->tidakPasti ? StatusSubAkunPembayaran::Menunggu : StatusSubAkunPembayaran::Gagal,
                    'PesanGalat' => $galat->getMessage(),
                    'DiubahOleh' => $pelaku->Id,
                ]);
            }

            $this->audit->Catat(
                self::AKSI_AUDIT,
                $subAkun,
                nilaiLama: $lama,
                nilaiBaru: ['Status' => $subAkun->Status->value, 'IdSubAkun' => $subAkun->IdSubAkun, 'PesanGalat' => $subAkun->PesanGalat],
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $subAkun;
        }, $tenant->Id);
    }
}
