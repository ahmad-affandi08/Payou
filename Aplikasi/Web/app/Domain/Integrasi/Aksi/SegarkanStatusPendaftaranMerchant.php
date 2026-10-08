<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Integrasi\Enum\StatusPendaftaranMerchant;
use App\Domain\Integrasi\Merchant\GalatPartnerDoku;
use App\Domain\Integrasi\Merchant\KlienPartnerDoku;
use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;

/**
 * Membaca status KYB terbaru dari DOKU (Get Business Data, sumber kebenaran) dan menyimpannya: `UPDATING` = Ditinjau,
 * `ACTIVE` = Aktif (menyimpan ID Brand dan shared key terenkripsi), ditolak = Ditolak beserta alasannya. Dipakai
 * penyapu terjadwal (jalur cadangan), tugas pemicu webhook KYB, dan tombol "Segarkan status" di konsol.
 *
 * Konteks tenant harus sudah diatur pemanggil. Pendaftaran yang belum terdaftar di DOKU diabaikan (null). Galat DOKU
 * dilempar sebagai `GalatPartnerDoku` (pesan sudah tersaring) dan tidak mengubah status yang tersimpan.
 */
final class SegarkanStatusPendaftaranMerchant
{
    public const AKSI_AUDIT = 'merchant-pembayaran.status';

    public function __construct(
        private readonly KlienPartnerDoku $klien,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws GalatPartnerDoku
     */
    public function Jalankan(): ?PendaftaranMerchantPembayaran
    {
        $pendaftaran = PendaftaranMerchantPembayaran::query()->where('Penyedia', PendaftaranMerchantPembayaran::PENYEDIA_DOKU)->first();

        if ($pendaftaran === null || ! $pendaftaran->CekSudahTerdaftar() || ! $this->klien->CekAktif()) {
            return null;
        }

        $hasil = $this->klien->AmbilDataBisnis($pendaftaran->IdBrandDoku ?? (string) $pendaftaran->IdBisnisDoku);
        $lama = $pendaftaran->Status;
        $baru = $hasil->AmbilStatusPendaftaran();
        $dapatBerubah = in_array($lama, [StatusPendaftaranMerchant::Ditinjau, StatusPendaftaranMerchant::Aktif, StatusPendaftaranMerchant::Ditolak], true);

        $pendaftaran->DiperiksaPada = now();
        $pendaftaran->StatusDoku = $hasil->statusBisnis === '' ? $pendaftaran->StatusDoku : $hasil->statusBisnis;

        if ($hasil->idBrand !== null) {
            $pendaftaran->IdBrandDoku = $hasil->idBrand;
        }

        if ($hasil->kunciBersama !== null) {
            $pendaftaran->KunciBersama = $hasil->kunciBersama;
        }

        if ($dapatBerubah) {
            $pendaftaran->Status = $baru;
            $pendaftaran->AlasanPenolakan = $baru === StatusPendaftaranMerchant::Ditolak ? ($hasil->alasanPenolakan ?? $pendaftaran->AlasanPenolakan ?? 'DOKU menolak pendaftaran ini.') : null;

            if ($baru === StatusPendaftaranMerchant::Aktif && $pendaftaran->DisetujuiPada === null) {
                $pendaftaran->DisetujuiPada = now();
            }
        }

        $pendaftaran->save();

        if ($dapatBerubah && $lama !== $baru) {
            $this->audit->Catat(self::AKSI_AUDIT, $pendaftaran, ['Status' => $lama->value], ['Status' => $baru->value]);
        }

        return $pendaftaran;
    }
}
