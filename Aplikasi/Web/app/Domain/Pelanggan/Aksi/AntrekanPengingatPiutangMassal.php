<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\JenisPengingatPiutang;
use App\Domain\Pelanggan\Model\Piutang;

/**
 * Aksi massal piutang (F-12, D-23 D): kirim pengingat WhatsApp untuk piutang terpilih. Tiap piutang diproses lewat
 * `AntrekanPengingatPiutang` (nomor sah, WhatsApp aktif, jeda 12 jam per piutang), tetapi berbeda dari aksi massal
 * lain: kegagalan satu piutang (tanpa nomor HP, baru diingatkan, sudah lunas) tidak membatalkan yang lain, karena
 * pengingat saling lepas. Hasilnya dirangkum: jumlah diantrekan dan alasan yang dilewati.
 */
final class AntrekanPengingatPiutangMassal
{
    public const MAKS = 100;

    public function __construct(private readonly AntrekanPengingatPiutang $antrekan) {}

    /**
     * @param  list<string>  $uuid
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return array{Diantrekan: int, Dilewati: array<string, int>}
     *
     * @throws PelanggaranAturanBisnis PilihanKosong, TerlaluBanyak, PiutangTidakDikenal
     */
    public function Jalankan(int $idTenant, array $uuid, ?int $idPengguna, ?array $idOutletBoleh = null): array
    {
        $uuid = array_values(array_unique($uuid));

        if ($uuid === []) {
            throw new PelanggaranAturanBisnis('PilihanKosong', 'Pilih minimal satu piutang.', 'Uuid');
        }

        if (count($uuid) > self::MAKS) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Maksimal '.self::MAKS.' piutang sekali kirim.', 'Uuid');
        }

        $piutang = Piutang::query()->whereIn('Uuid', $uuid)->orderBy('JatuhTempo')->orderBy('Id')->get();
        $diizinkan = $idOutletBoleh === null ? $piutang : $piutang->filter(fn (Piutang $p): bool => in_array($p->IdOutlet, $idOutletBoleh, true));

        if ($diizinkan->count() !== count($uuid)) {
            throw new PelanggaranAturanBisnis('PiutangTidakDikenal', 'Sebagian piutang tidak ditemukan. Muat ulang halaman.', 'Uuid');
        }

        $diantrekan = 0;
        $dilewati = [];

        foreach ($diizinkan as $p) {
            try {
                $this->antrekan->Jalankan($idTenant, $p, JenisPengingatPiutang::Manual, $idPengguna);
                $diantrekan++;
            } catch (PelanggaranAturanBisnis $galat) {
                $dilewati[$galat->getMessage()] = ($dilewati[$galat->getMessage()] ?? 0) + 1;
            }
        }

        return ['Diantrekan' => $diantrekan, 'Dilewati' => $dilewati];
    }
}
