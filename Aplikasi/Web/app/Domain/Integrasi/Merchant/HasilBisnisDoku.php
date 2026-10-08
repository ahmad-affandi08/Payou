<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Merchant;

use App\Domain\Integrasi\Enum\StatusPendaftaranMerchant;

/**
 * Hasil Business Registration / Get Business Data DOKU yang sudah dirapikan. Bentuk respons diurai longgar karena
 * contoh respons belum diverifikasi dengan panggilan nyata.
 */
final readonly class HasilBisnisDoku
{
    /**
     * @param  list<array{Kode: string, Nama: string, Status: string}>  $layanan
     */
    public function __construct(
        public ?string $idBisnis,
        public string $statusBisnis,
        public ?string $idBrand,
        public string $statusBrand,
        public ?string $kunciBersama,
        public array $layanan = [],
        public ?string $alasanPenolakan = null,
    ) {}

    /** Petakan status DOKU ke status pendaftaran kita. Hanya `ACTIVE` lengkap dengan Brand yang dianggap Aktif. */
    public function AmbilStatusPendaftaran(): StatusPendaftaranMerchant
    {
        $ditolak = ['REJECTED', 'DECLINED', 'DENIED', 'FAILED'];

        if (in_array($this->statusBisnis, $ditolak, true) || in_array($this->statusBrand, $ditolak, true)) {
            return StatusPendaftaranMerchant::Ditolak;
        }

        $brandSiap = $this->idBrand !== null && ($this->statusBrand === '' || $this->statusBrand === 'ACTIVE');

        return $this->statusBisnis === 'ACTIVE' && $brandSiap ? StatusPendaftaranMerchant::Aktif : StatusPendaftaranMerchant::Ditinjau;
    }
}
