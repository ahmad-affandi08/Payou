<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Tagihan\Kueri\DaftarTagihanPlatform;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;

/**
 * Aksi massal antrean verifikasi (P-08, izin `tagihan.verifikasi`): terima banyak bukti transfer sekaligus. Tiap
 * pembayaran lewat `TerimaPembayaranLangganan` dengan jumlah diterima = jumlah yang dilaporkan pada pembayaran itu;
 * `PelunasTagihanLangganan` tetap menolak bila tidak sama dengan total tagihan. Pembayaran saling lepas, jadi yang
 * gagal (bukan lagi Menunggu, jumlah tidak cocok) dilewati dan alasannya dirangkum, bukan membatalkan yang lain.
 * Pemanggil wajib menegaskan bahwa setiap mutasi rekening sudah dicocokkan (`$sudahDicocokkan`); tanpa itu ditolak.
 */
final class TerimaPembayaranLanggananMassal
{
    public const MAKS = 50;

    public function __construct(
        private readonly DaftarTagihanPlatform $daftar,
        private readonly TerimaPembayaranLangganan $terima,
    ) {}

    /**
     * @param  list<string>  $uuidPembayaran
     * @return array{Diterima: int, Dilewati: array<string, int>}
     *
     * @throws PelanggaranAturanBisnis PilihanKosong, TerlaluBanyak, BelumDicocokkan
     */
    public function Jalankan(PenggunaPengelola $verifikator, array $uuidPembayaran, bool $sudahDicocokkan, ?string $catatan = null): array
    {
        $uuidPembayaran = array_values(array_unique($uuidPembayaran));

        if ($uuidPembayaran === []) {
            throw new PelanggaranAturanBisnis('PilihanKosong', 'Pilih minimal satu pembayaran.', 'Uuid');
        }

        if (count($uuidPembayaran) > self::MAKS) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Maksimal '.self::MAKS.' pembayaran sekali proses.', 'Uuid');
        }

        if (! $sudahDicocokkan) {
            throw new PelanggaranAturanBisnis('BelumDicocokkan', 'Centang bahwa setiap bukti sudah dicocokkan dengan mutasi rekening.', 'SudahDicocokkan');
        }

        $diterima = 0;
        $dilewati = [];

        foreach ($uuidPembayaran as $uuid) {
            $pembayaran = $this->daftar->CariPembayaran($uuid);

            try {
                if ($pembayaran === null || $pembayaran->Status !== StatusPembayaranLangganan::Menunggu) {
                    throw new PelanggaranAturanBisnis('BukanMenunggu', 'Pembayaran tidak lagi menunggu verifikasi.');
                }

                $this->terima->Jalankan($verifikator, $uuid, $pembayaran->Jumlah, $catatan);
                $diterima++;
            } catch (PelanggaranAturanBisnis $galat) {
                $dilewati[$galat->getMessage()] = ($dilewati[$galat->getMessage()] ?? 0) + 1;
            }
        }

        return ['Diterima' => $diterima, 'Dilewati' => $dilewati];
    }
}
