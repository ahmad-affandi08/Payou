<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Dukungan\Enum\JenisPengirimPesan;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Dukungan\Model\TiketDukunganPesan;
use Illuminate\Support\Collection;

/**
 * Tiket dukungan dari sisi tenant (P-09): selalu dibatasi tenant aktif lewat MilikTenant, dan catatan internal tim
 * tidak pernah ikut.
 */
final class TiketDukunganTenant
{
    public const KOLOM_URUT = ['DibuatPada', 'Nomor'];

    public const KOLOM_SARING = ['Keadaan'];

    /**
     * Daftar untuk `TabelData` (D-16): cari nomor/judul, saring `Keadaan` (`Semua`; bawaan hanya tiket terbuka).
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $pola = PenerapKueriTabel::PolaCari($permintaan->cari);
        $kueri = TiketDukungan::query()
            ->when(($permintaan->saring['Keadaan'] ?? 'Terbuka') !== 'Semua', fn ($kueri) => $kueri->whereIn('Status', array_map(
                fn (StatusTiketDukungan $status) => $status->value,
                StatusTiketDukungan::AmbilTerbuka(),
            )))
            ->when($permintaan->cari !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam->where('Nomor', 'like', $pola)->orWhere('Judul', 'like', $pola)));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['DibuatPada' => 'DibuatPada', 'Nomor' => 'Nomor'], fn (Collection $tiket): array => array_values($tiket->map(fn (TiketDukungan $t): array => self::PetakanRingkas($t))->all()));
    }

    public function Cari(string $uuid): TiketDukungan
    {
        return TiketDukungan::query()->where('Uuid', $uuid)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilDetail(TiketDukungan $tiket): array
    {
        $pesan = TiketDukunganPesan::query()
            ->where('IdTiketDukungan', $tiket->Id)
            ->where('CatatanInternal', false)
            ->orderBy('Id')
            ->get();

        return [
            ...self::PetakanRingkas($tiket),
            'BisaDibalas' => $tiket->Status->CekTerbuka() || $tiket->CekBisaDibukaLagi(),
            'BisaDiselesaikan' => $tiket->Status->CekTerbuka(),
            'Pesan' => array_values($pesan->map(fn (TiketDukunganPesan $baris): array => [
                'Uuid' => $baris->Uuid,
                'JenisPengirim' => $baris->JenisPengirim->value,
                'NamaPengirim' => match ($baris->JenisPengirim) {
                    JenisPengirimPesan::Pengelola => trim(($baris->NamaPengirim ?? '').' | Tim Dukungan', ' |'),
                    JenisPengirimPesan::Sistem => 'Sistem',
                    JenisPengirimPesan::Pengguna => $baris->NamaPengirim ?? 'Pengguna',
                },
                'Isi' => $baris->Isi,
                'Lampiran' => self::PetakanLampiran($baris),
                'DibuatPada' => $baris->DibuatPada->toIso8601String(),
            ])->all()),
        ];
    }

    /**
     * Lampiran hanya bisa diunduh dari pesan yang terlihat oleh tenant.
     *
     * @return array{NamaAsli: string, Mime: string, Path: string}|null
     */
    public function CariLampiran(TiketDukungan $tiket, string $uuidLampiran): ?array
    {
        $pesan = TiketDukunganPesan::query()
            ->where('IdTiketDukungan', $tiket->Id)
            ->where('CatatanInternal', false)
            ->whereNotNull('Lampiran')
            ->get();

        foreach ($pesan as $baris) {
            $lampiran = $baris->CariLampiran($uuidLampiran);

            if ($lampiran !== null) {
                return $lampiran;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function PetakanRingkas(TiketDukungan $tiket): array
    {
        return [
            'Uuid' => $tiket->Uuid,
            'Nomor' => $tiket->Nomor,
            'Judul' => $tiket->Judul,
            'Kategori' => $tiket->Kategori->value,
            'LabelKategori' => $tiket->Kategori->AmbilLabel(),
            'Prioritas' => $tiket->Prioritas->value,
            'LabelPrioritas' => $tiket->Prioritas->AmbilLabel(),
            'Status' => $tiket->Status->value,
            'LabelStatus' => $tiket->Status->AmbilLabel(),
            'DibuatPada' => $tiket->DibuatPada->toIso8601String(),
            'PesanTerakhirPada' => $tiket->PesanTerakhirPada?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{Uuid: string, NamaAsli: string, Mime: string, UkuranByte: int}>
     */
    public static function PetakanLampiran(TiketDukunganPesan $pesan): array
    {
        return array_values(array_map(fn (array $lampiran): array => [
            'Uuid' => $lampiran['Uuid'],
            'NamaAsli' => $lampiran['NamaAsli'],
            'Mime' => $lampiran['Mime'],
            'UkuranByte' => $lampiran['UkuranByte'],
        ], $pesan->Lampiran ?? []));
    }
}
