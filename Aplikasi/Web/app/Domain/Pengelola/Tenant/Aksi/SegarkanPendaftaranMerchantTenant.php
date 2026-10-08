<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Aksi\SegarkanStatusPendaftaranMerchant;
use App\Domain\Integrasi\Merchant\GalatPartnerDoku;
use App\Domain\Integrasi\Merchant\KlienPartnerDoku;
use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\Tenant;

/**
 * Tombol "Segarkan status" di detail tenant (konsol): membaca status KYB terbaru pendaftaran merchant tenant dari DOKU
 * (Get Business Data). Tercatat di `LogAuditPengelola` (`tenant.merchant.segarkan`). Pesan galat DOKU sudah disaring.
 */
final class SegarkanPendaftaranMerchantTenant
{
    public const AKSI_AUDIT = 'tenant.merchant.segarkan';

    public function __construct(
        private readonly SegarkanStatusPendaftaranMerchant $segarkan,
        private readonly KlienPartnerDoku $klien,
        private readonly KonteksPengelola $konteks,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant): PendaftaranMerchantPembayaran
    {
        if (! $this->klien->CekAktif()) {
            throw new PelanggaranAturanBisnis('PartnerBelumAktif', 'Isi kredensial DOKU Partner dulu di menu Integrasi, lalu coba lagi.', statusHttp: 409);
        }

        return $this->konteks->JalankanLintasTenant('Menyegarkan status pendaftaran merchant DOKU', function () use ($pelaku, $tenant): PendaftaranMerchantPembayaran {
            $pendaftaran = PendaftaranMerchantPembayaran::query()->where('Penyedia', PendaftaranMerchantPembayaran::PENYEDIA_DOKU)->first();

            if ($pendaftaran === null || ! $pendaftaran->CekSudahTerdaftar()) {
                throw new PelanggaranAturanBisnis('BelumTerdaftar', 'Tenant ini belum mengirim pendaftaran ke DOKU, jadi belum ada status yang bisa disegarkan.', statusHttp: 409);
            }

            $lama = $pendaftaran->Status->value;

            try {
                $baru = $this->segarkan->Jalankan() ?? $pendaftaran;
            } catch (GalatPartnerDoku $galat) {
                throw new PelanggaranAturanBisnis('DokuGalat', $galat->getMessage(), statusHttp: 502);
            }

            $this->audit->Catat(
                self::AKSI_AUDIT,
                $baru,
                nilaiLama: ['Status' => $lama],
                nilaiBaru: ['Status' => $baru->Status->value, 'StatusDoku' => $baru->StatusDoku],
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $baru;
        }, $tenant->Id);
    }
}
