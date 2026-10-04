<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Promo\Enum\StatusPromo;
use App\Domain\Promo\Model\Promo;
use Illuminate\Support\Facades\DB;

/**
 * Aksi massal promo terpilih (F-16c, izin `pelanggan.kelola`): arsipkan atau aktifkan kembali. Lewat `UbahStatusPromo`
 * (audit per promo); yang sudah berstatus tujuan dilewati dan dihitung. Semua atau tidak sama sekali.
 */
final class UbahPromoMassal
{
    public const MAKS = 200;

    public const AKSI = ['Arsipkan', 'Pulihkan'];

    public function __construct(private readonly UbahStatusPromo $ubahStatus) {}

    /**
     * @param  list<string>  $uuid
     * @return array{Diubah: int, Dilewati: int}
     *
     * @throws PelanggaranAturanBisnis AksiTidakDikenal, PilihanKosong, TerlaluBanyak, PromoTidakDikenal
     */
    public function Jalankan(string $aksi, array $uuid, int $idPengguna): array
    {
        if (! in_array($aksi, self::AKSI, true)) {
            throw new PelanggaranAturanBisnis('AksiTidakDikenal', 'Aksi massal tidak dikenal.', 'Aksi');
        }

        $uuid = array_values(array_unique($uuid));

        if ($uuid === []) {
            throw new PelanggaranAturanBisnis('PilihanKosong', 'Pilih minimal satu promo.', 'Uuid');
        }

        if (count($uuid) > self::MAKS) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Maksimal '.self::MAKS.' promo sekali proses.', 'Uuid');
        }

        return DB::transaction(function () use ($aksi, $uuid, $idPengguna): array {
            $promo = Promo::query()->whereIn('Uuid', $uuid)->orderBy('Id')->get();

            if ($promo->count() !== count($uuid)) {
                throw new PelanggaranAturanBisnis('PromoTidakDikenal', 'Sebagian promo tidak ditemukan. Muat ulang halaman.', 'Uuid');
            }

            $tujuan = $aksi === 'Arsipkan' ? StatusPromo::Diarsipkan : StatusPromo::Aktif;
            $diubah = 0;

            foreach ($promo as $p) {
                if ($p->Status === $tujuan) {
                    continue;
                }

                $this->ubahStatus->Jalankan($p, $tujuan, $idPengguna);
                $diubah++;
            }

            return ['Diubah' => $diubah, 'Dilewati' => $promo->count() - $diubah];
        });
    }
}
