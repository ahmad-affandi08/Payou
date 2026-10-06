<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Pajak\Enum\KategoriJenisPajak;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Pembelian\Data\DataPajakPembelian;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

/**
 * PPN masukan pembelian (F-04 fase 1, CLAUDE.md #12): hanya bila pemasok PKP; tarif & pengali DPP dari `TarifPajak`
 * nasional berkategori PPN yang berlaku pada tanggal dokumen (tanpa angka tarif di kode). PPN = Σ dasar × pengali DPP ×
 * tarif ÷ 100, dibulatkan setengah ke atas per dokumen. Dikreditkan bila outlet pembeli PKP (`ProfilPajakOutlet`; lokasi tanpa outlet memakai status PKP tenant).
 */
final class PenghitungPajakPembelian
{
    public function __construct(
        private readonly TarifPajakBerlaku $tarifBerlaku,
        private readonly ProfilPajakOutlet $profilPajak,
        private readonly ProfilTenant $profilTenant,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis TarifPajakBelumAda
     */
    public function Hitung(bool $pemasokPkp, ?int $idOutlet, CarbonInterface $tanggal, Uang $dasar): DataPajakPembelian
    {
        if (! $pemasokPkp) {
            return DataPajakPembelian::TanpaPajak();
        }

        $tarif = $this->tarifBerlaku->CariDataKategoriNasional(KategoriJenisPajak::Ppn, $tanggal);

        if ($tarif === null) {
            throw new PelanggaranAturanBisnis('TarifPajakBelumAda', 'Tarif PPN yang berlaku pada tanggal ini belum tersedia. Hubungi tim Payoung.', 'Tanggal');
        }

        $pkpPembeli = $idOutlet !== null
            ? $this->profilPajak->Ambil($idOutlet)?->pkp === true
            : ($this->profilTenant->Ambil($this->konteks->Wajib())['Pkp'] ?? false) === true;

        return self::HitungDenganTarif($tarif->tarif, $tarif->pengaliDppPembilang, $tarif->pengaliDppPenyebut, $dasar, $pkpPembeli);
    }

    public static function HitungDenganTarif(string $tarif, int $pembilang, int $penyebut, Uang $dasar, bool $dikreditkan): DataPajakPembelian
    {
        $pajak = BigRational::of($dasar->KeString())
            ->multipliedBy(BigRational::ofFraction($pembilang, $penyebut))
            ->multipliedBy(BigRational::of($tarif))
            ->dividedBy(100)
            ->toScale(Uang::SKALA, RoundingMode::HalfUp);

        return new DataPajakPembelian($tarif, $pembilang, $penyebut, Uang::Dari($pajak), $dikreditkan);
    }
}
