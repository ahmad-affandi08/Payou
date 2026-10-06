<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\DataBawaan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Domain\Lisensi\Kontrak\PenyiapDataBawaan;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pajak\Peristiwa\TarifPajakTerbit;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\Katalog\Aksi\SiapkanKatalogBawaan;
use App\Domain\Pengelola\Referensi\Aksi\SiapkanPajakBawaan;
use App\Domain\Pengelola\Referensi\Aksi\SiapkanSatuanStandarBawaan;
use App\Domain\Pengelola\Referensi\Aksi\SiapkanWilayahBawaan;
use App\Domain\Pengelola\TemplateSektor\Aksi\SiapkanTemplateSektorBawaan;
use App\Domain\Pengelola\TemplateSektor\Layanan\ValidatorTemplate;
use Illuminate\Support\Facades\DB;

/**
 * D-35 edisi Lisensi: data master platform tanpa Platform Pengelola. Data bawaan yang ikut rilis (`database/Data`:
 * satuan, wilayah, jenis & tarif pajak, katalog fitur, template sektor) dimuat lalu langsung diterbitkan, karena di
 * server pembeli tidak ada konsol maupun peninjau. Four-eyes (BR-P02.2, BR-P03.5) tetap berlaku di edisi SaaS; di sini
 * yang menjamin isinya adalah rilis Payoung itu sendiri (berkas data ditinjau sebelum dirilis).
 *
 * Idempoten, dijalankan saat pemasangan pertama dan setelah setiap pembaruan (`lisensi:siapkan-data`):
 * - Tarif pajak berstatus Draf diterbitkan hanya bila jenis pajaknya belum punya tarif terbit (tarif terbit tidak
 *   pernah diubah; perubahan tarif nasional datang lewat rilis berikutnya).
 * - Template sektor versi Draf diterbitkan bila lolos validasi otomatis (BR-P03.3) dan templatenya belum punya versi
 *   terbit; yang gagal validasi dibiarkan Draf dan dilaporkan.
 */
final class SiapkanDataBawaanLisensi implements PenyiapDataBawaan
{
    public function __construct(
        private readonly SiapkanSatuanStandarBawaan $satuan,
        private readonly SiapkanWilayahBawaan $wilayah,
        private readonly SiapkanPajakBawaan $pajak,
        private readonly SiapkanKatalogBawaan $katalog,
        private readonly SiapkanTemplateSektorBawaan $template,
        private readonly ValidatorTemplate $validator,
    ) {}

    /**
     * @return array{TarifTerbit: int, TemplateTerbit: int, TemplateGagal: list<string>}
     */
    public function Jalankan(): array
    {
        if (! EdisiAplikasi::CekLisensi()) {
            throw new PelanggaranAturanBisnis('D-35', 'Data bawaan langsung terbit hanya di edisi Lisensi. Di edisi SaaS terbitkan lewat konsol.');
        }

        $this->satuan->Jalankan();
        $this->wilayah->Jalankan();
        $this->pajak->Jalankan();
        $this->katalog->Jalankan();
        $this->template->Jalankan();

        $tarifTerbit = DB::transaction(fn (): array => $this->TerbitkanTarif());

        foreach ($tarifTerbit as $idTarif) {
            TarifPajakTerbit::dispatch($idTarif);
        }

        [$templateTerbit, $templateGagal] = DB::transaction(fn (): array => $this->TerbitkanTemplate());

        return ['TarifTerbit' => count($tarifTerbit), 'TemplateTerbit' => $templateTerbit, 'TemplateGagal' => $templateGagal];
    }

    /**
     * @return list<int>
     */
    private function TerbitkanTarif(): array
    {
        $terbit = [];
        $draf = TarifPajak::query()->where('Status', StatusDataMaster::Draf->value)->orderBy('Id')->lockForUpdate()->get();

        foreach ($draf as $tarif) {
            $sudahAda = TarifPajak::query()
                ->where('IdJenisPajak', $tarif->IdJenisPajak)
                ->where('KodeWilayah', $tarif->KodeWilayah)
                ->where('Status', StatusDataMaster::Terbit->value)
                ->exists();

            if (! $sudahAda) {
                $tarif->update(['Status' => StatusDataMaster::Terbit]);
                $terbit[] = $tarif->Id;
            }
        }

        return $terbit;
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function TerbitkanTemplate(): array
    {
        $jumlah = 0;
        $gagal = [];
        $draf = TemplateSektorVersi::query()
            ->with('TemplateSektor')
            ->where('Status', StatusTemplateSektor::Draf->value)
            ->orderBy('Id')
            ->lockForUpdate()
            ->get();

        foreach ($draf as $versi) {
            $adaTerbit = TemplateSektorVersi::query()
                ->where('IdTemplateSektor', $versi->IdTemplateSektor)
                ->where('Status', StatusTemplateSektor::Terbit->value)
                ->exists();

            if ($adaTerbit) {
                continue;
            }

            $validasi = $this->validator->Validasi($versi->Isi);
            $versi->update(['HasilValidasi' => $validasi, 'DivalidasiPada' => now()]);

            if (! $validasi['Lolos']) {
                $gagal[] = $versi->TemplateSektor->Kode;

                continue;
            }

            $versi->update(['Status' => StatusTemplateSektor::Terbit, 'DiterbitkanPada' => now()]);
            $jumlah++;
        }

        return [$jumlah, $gagal];
    }
}
