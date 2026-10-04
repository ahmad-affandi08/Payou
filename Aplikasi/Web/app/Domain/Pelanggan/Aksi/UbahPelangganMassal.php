<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use Illuminate\Support\Facades\DB;

/**
 * Aksi massal pelanggan terpilih (F-16a/F-16b, izin `pelanggan.kelola`): arsipkan, pulihkan, atau atur tier. Tiap
 * pelanggan diproses lewat Aksi satuannya (`UbahStatusPelanggan`, `AturTierPelanggan`) sehingga audit per pelanggan
 * sama persis. Semua atau tidak sama sekali (satu transaksi). Pelanggan yang sudah berstatus tujuan dilewati (bukan
 * galat) karena pilihan massal sering campuran. `Tier` dengan tier kosong melepas tier; tier ditetapkan manual tidak
 * dikunci (`TierTetap` false) kecuali [tierTetap].
 */
final class UbahPelangganMassal
{
    public const MAKS = 200;

    public const AKSI = ['Arsipkan', 'Pulihkan', 'Tier'];

    public function __construct(
        private readonly UbahStatusPelanggan $ubahStatus,
        private readonly AturTierPelanggan $aturTier,
    ) {}

    /**
     * @param  list<string>  $uuid
     * @return array{Diubah: int, Dilewati: int}
     *
     * @throws PelanggaranAturanBisnis AksiTidakDikenal, PilihanKosong, TerlaluBanyak, PelangganTidakDikenal, TierTidakDikenal, TierDiarsipkan
     */
    public function Jalankan(string $aksi, array $uuid, int $idPengguna, ?string $uuidTier = null, bool $tierTetap = false): array
    {
        if (! in_array($aksi, self::AKSI, true)) {
            throw new PelanggaranAturanBisnis('AksiTidakDikenal', 'Aksi massal tidak dikenal.', 'Aksi');
        }

        $uuid = array_values(array_unique($uuid));

        if ($uuid === []) {
            throw new PelanggaranAturanBisnis('PilihanKosong', 'Pilih minimal satu pelanggan.', 'Uuid');
        }

        if (count($uuid) > self::MAKS) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Maksimal '.self::MAKS.' pelanggan sekali proses.', 'Uuid');
        }

        $tier = null;

        if ($aksi === 'Tier' && $uuidTier !== null) {
            $tier = TierPelanggan::query()->where('Uuid', $uuidTier)->first()
                ?? throw new PelanggaranAturanBisnis('TierTidakDikenal', 'Tier tidak ditemukan.', 'UuidTier');
        }

        return DB::transaction(function () use ($aksi, $uuid, $idPengguna, $tier, $tierTetap): array {
            $pelanggan = Pelanggan::query()->whereIn('Uuid', $uuid)->orderBy('Id')->get();

            if ($pelanggan->count() !== count($uuid)) {
                throw new PelanggaranAturanBisnis('PelangganTidakDikenal', 'Sebagian pelanggan tidak ditemukan. Muat ulang halaman.', 'Uuid');
            }

            $diubah = 0;

            foreach ($pelanggan as $p) {
                if ($aksi === 'Tier') {
                    $this->aturTier->Jalankan($p, $tier, $tierTetap, $idPengguna);
                    $diubah++;

                    continue;
                }

                $tujuan = $aksi === 'Arsipkan' ? StatusPelanggan::Diarsipkan : StatusPelanggan::Aktif;

                if ($p->Status === $tujuan) {
                    continue;
                }

                $this->ubahStatus->Jalankan($p, $tujuan, $idPengguna);
                $diubah++;
            }

            return ['Diubah' => $diubah, 'Dilewati' => $pelanggan->count() - $diubah];
        });
    }
}
