<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Data\DataPengaturanStruk;
use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use App\Domain\Tenant\Layanan\PenyimpanLogoTenant;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah pengaturan struk tenant (PLT-06, PRD v1.79): ditulis ke `Tenant.Pengaturan.Struk`; saklar tampil & logo
 * boleh berbeda per outlet di `Tenant.Pengaturan.StrukOutlet.{IdOutlet}` (D-76). Berlaku di struk berikutnya setelah perangkat kasir memperbarui data. Tanpa perubahan =
 * tidak ada yang ditulis. Audit `struk.pengaturan.ubah`.
 */
final class UbahPengaturanStruk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PengaturanStrukTenant $pengaturan,
        private readonly PencatatAudit $audit,
        private readonly PenyimpanLogoTenant $penyimpanLogo,
    ) {}

    /**
     * Tanpa `$idOutlet`: seluruh pengaturan ke tenant (perilaku lama). Dengan `$idOutlet` (D-70): saklar tampil & logo
     * disimpan khusus outlet itu, teks isian tetap ke tenant. `$logo` mengganti logo outlet, `$hapusLogo` mengembalikannya
     * ke logo usaha.
     */
    public function Jalankan(DataPengaturanStruk $baru, ?int $idOutlet = null, ?UploadedFile $logo = null, bool $hapusLogo = false): void
    {
        self::Validasi($baru);
        $pathBaru = $idOutlet !== null && $logo !== null ? $this->penyimpanLogo->Simpan($this->konteks->Wajib(), $logo) : null;
        $pathDihapus = null;

        try {
            DB::transaction(function () use ($baru, $idOutlet, $pathBaru, $hapusLogo, &$pathDihapus): void {
                $tenant = $this->penguncian->Kunci($this->konteks->Wajib());
                $pengaturan = $tenant->Pengaturan ?? [];
                $lamaTenant = $this->pengaturan->Ambil($tenant->Id)->KeLarik();
                $nilaiBaru = $baru->KeLarik();
                $lamaOutlet = $idOutlet === null ? null : $this->pengaturan->Ambil($tenant->Id, $idOutlet)->KeLarik();
                $logoLama = $idOutlet === null ? null : $this->pengaturan->AmbilPathLogoOutlet($tenant->Id, $idOutlet);
                $perubahanLogo = $pathBaru !== null || ($hapusLogo && $logoLama !== null);

                if ($idOutlet === null) {
                    if ($lamaTenant === $nilaiBaru) {
                        return;
                    }

                    $pengaturan['Struk'] = $nilaiBaru;
                    $this->Simpan($tenant, $pengaturan, $lamaTenant, $nilaiBaru);

                    return;
                }

                $teksBaru = array_diff_key($nilaiBaru, array_flip(PengaturanStrukTenant::SAKLAR_OUTLET));
                $saklarBaru = array_intersect_key($nilaiBaru, array_flip(PengaturanStrukTenant::SAKLAR_OUTLET));
                $strukTenantBaru = [...$lamaTenant, ...$teksBaru];
                $saklarLama = array_intersect_key($lamaOutlet ?? [], $saklarBaru);

                if ($strukTenantBaru === $lamaTenant && $saklarLama === $saklarBaru && ! $perubahanLogo) {
                    return;
                }

                $timpaan = is_array($pengaturan['StrukOutlet'][(string) $idOutlet] ?? null) ? $pengaturan['StrukOutlet'][(string) $idOutlet] : [];
                $timpaan = [...$timpaan, ...$saklarBaru];

                if ($pathBaru !== null) {
                    $timpaan['PathLogo'] = $pathBaru;
                    $pathDihapus = $logoLama;
                } elseif ($hapusLogo && $logoLama !== null) {
                    unset($timpaan['PathLogo']);
                    $pathDihapus = $logoLama;
                }

                $pengaturan['Struk'] = $strukTenantBaru;
                $pengaturan['StrukOutlet'] = [...(is_array($pengaturan['StrukOutlet'] ?? null) ? $pengaturan['StrukOutlet'] : []), (string) $idOutlet => $timpaan];
                $this->Simpan(
                    $tenant,
                    $pengaturan,
                    [...$lamaTenant, 'IdOutlet' => $idOutlet, ...$saklarLama, 'Logo' => $logoLama],
                    [...$strukTenantBaru, 'IdOutlet' => $idOutlet, ...$saklarBaru, 'Logo' => $timpaan['PathLogo'] ?? null],
                );
            });
        } catch (\Throwable $galat) {
            $this->penyimpanLogo->Hapus($pathBaru);

            throw $galat;
        }

        $this->penyimpanLogo->Hapus($pathDihapus);
    }

    /**
     * @param  array<string, mixed>  $pengaturan
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     */
    private function Simpan(Tenant $tenant, array $pengaturan, array $lama, array $baru): void
    {
        $tenant->Pengaturan = $pengaturan;
        $tenant->save();

        $this->audit->Catat('struk.pengaturan.ubah', nilaiLama: $lama, nilaiBaru: $baru);
    }

    private static function Validasi(DataPengaturanStruk $data): void
    {
        $baris = DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL;

        foreach (['NamaDicetak' => $data->namaDicetak, 'TeksPenutup' => $data->teksPenutup] as $bidang => $teks) {
            if ($teks !== null && mb_strlen($teks) > $baris) {
                throw new PelanggaranAturanBisnis('TeksStrukTerlaluPanjang', "Teks ini paling banyak {$baris} karakter agar muat satu baris struk.", $bidang);
            }
        }

        if (count($data->teksKepala) > DataPengaturanStruk::JUMLAH_TEKS_KEPALA_MAKSIMAL) {
            throw new PelanggaranAturanBisnis('TeksKepalaTerlaluBanyak', 'Teks kepala struk paling banyak '.DataPengaturanStruk::JUMLAH_TEKS_KEPALA_MAKSIMAL.' baris.', 'TeksKepala');
        }

        foreach ($data->teksKepala as $i => $teks) {
            if ($teks === '' || mb_strlen($teks) > $baris) {
                throw new PelanggaranAturanBisnis('TeksStrukTerlaluPanjang', "Setiap baris kepala struk berisi 1 sampai {$baris} karakter.", "TeksKepala.{$i}");
            }
        }

        if ($data->catatanKaki !== null && mb_strlen($data->catatanKaki) > DataPengaturanStruk::PANJANG_CATATAN_KAKI_MAKSIMAL) {
            throw new PelanggaranAturanBisnis('TeksStrukTerlaluPanjang', 'Catatan kaki struk paling banyak '.DataPengaturanStruk::PANJANG_CATATAN_KAKI_MAKSIMAL.' karakter.', 'CatatanKaki');
        }
    }
}
