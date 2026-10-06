<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Aksi\SimpanOutlet;
use App\Domain\Organisasi\Data\DataOutlet;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Data\DataProfilUsaha;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Enum\StatusLangkahPanduan;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Kueri\WilayahKota;
use App\Domain\Tenant\Aksi\UbahProfilUsaha;
use App\Domain\Tenant\Data\DataProfilUsahaTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 1: profil usaha (nama, NPWP, PKP, logo) di tenant, alamat & kota di outlet wizard lewat `SimpanOutlet`
 * (F-02). Zona waktu tenant & outlet mengikuti kota. Satu transaksi; langkah ditandai Selesai.
 */
final class SimpanProfilUsaha
{
    public function __construct(
        private readonly WilayahKota $wilayahKota,
        private readonly UbahProfilUsaha $ubahProfil,
        private readonly SimpanOutlet $simpanOutlet,
        private readonly TandaiLangkahPanduan $tandai,
    ) {}

    public function Jalankan(Outlet $outlet, DataProfilUsaha $data, ?UploadedFile $logo, bool $hapusLogo): void
    {
        $kota = $this->wilayahKota->Cari($data->kodeKota)
            ?? throw new PelanggaranAturanBisnis('KotaTidakDikenal', 'Pilih kabupaten/kota dari daftar.', 'KodeKota');

        DB::transaction(function () use ($outlet, $data, $logo, $hapusLogo, $kota): void {
            $this->ubahProfil->Jalankan(
                new DataProfilUsahaTenant(nama: $data->namaUsaha, npwp: $data->npwp, pkp: $data->pkp, zonaWaktu: $kota['ZonaWaktuIana']),
                $logo,
                $hapusLogo,
            );

            // D-78: pemilik hanya mengisi satu nama. Outlet pertama yang masih bernama bawaan (nama usaha lama atau
            // "Outlet Utama") ikut nama baru; outlet yang sudah diberi nama sendiri tidak disentuh.
            $masihBawaan = in_array($outlet->Nama, ['Outlet Utama', $outlet->Merek->Nama], true);

            $profilPajak = $outlet->ProfilPajak ?? [];
            $this->simpanOutlet->Jalankan($outlet, new DataOutlet(
                nama: $masihBawaan ? $data->namaUsaha : $outlet->Nama,
                kode: $outlet->Kode,
                uuidMerek: $outlet->Merek->Uuid,
                alamat: $data->alamat,
                kodeKota: $data->kodeKota,
                zonaWaktu: ZonaWaktu::Wib->value,
                jamTutupBuku: $outlet->JamTutupBuku,
                pkp: $data->pkp,
                nitku: is_string($profilPajak['Nitku'] ?? null) ? $profilPajak['Nitku'] : null,
                pungutPbjt: ($profilPajak['PungutPbjt'] ?? false) === true,
            ));

            $this->tandai->Jalankan(LangkahPanduan::ProfilUsaha, StatusLangkahPanduan::Selesai, $outlet->Id);
        });
    }
}
