<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;

/**
 * Aksi massal tiket dukungan terpilih (P-09, konsol): tandai selesai atau tutup banyak tiket sekaligus. Tiap tiket
 * lewat `UbahStatusTiketDukungan` (state machine, pesan sistem ke tenant, audit per tiket). Tiket saling lepas, jadi
 * tiket yang tidak boleh berubah (sudah ditutup, status asal tidak mengizinkan) dilewati dan alasannya dirangkum,
 * bukan membatalkan yang lain. Menutup tiket wajib alasan yang sama untuk semuanya.
 */
final class UbahStatusTiketDukunganMassal
{
    public const MAKS = 100;

    /** Status tujuan yang boleh dipakai massal; membuka/menangani tetap satu per satu karena perlu penanggung jawab. */
    public const TUJUAN = ['Selesai', 'Ditutup'];

    public function __construct(private readonly UbahStatusTiketDukungan $ubah) {}

    /**
     * @param  list<string>  $uuid
     * @return array{Diubah: int, Dilewati: array<string, int>}
     *
     * @throws PelanggaranAturanBisnis PilihanKosong, TerlaluBanyak, StatusTidakDikenal, AlasanWajib
     */
    public function Jalankan(PenggunaPengelola $pelaku, array $uuid, string $tujuan, ?string $alasan): array
    {
        if (! in_array($tujuan, self::TUJUAN, true)) {
            throw new PelanggaranAturanBisnis('StatusTidakDikenal', 'Status massal hanya Selesai atau Ditutup.', 'Status');
        }

        $uuid = array_values(array_unique($uuid));

        if ($uuid === []) {
            throw new PelanggaranAturanBisnis('PilihanKosong', 'Pilih minimal satu tiket.', 'Uuid');
        }

        if (count($uuid) > self::MAKS) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Maksimal '.self::MAKS.' tiket sekali proses.', 'Uuid');
        }

        if ($tujuan === 'Ditutup' && trim((string) $alasan) === '') {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Isi alasan menutup tiket.', 'Alasan');
        }

        $status = StatusTiketDukungan::from($tujuan);
        $diubah = 0;
        $dilewati = [];

        foreach ($uuid as $u) {
            try {
                $this->ubah->Jalankan($pelaku, $u, $status, $alasan);
                $diubah++;
            } catch (PelanggaranAturanBisnis $galat) {
                $dilewati[$galat->getMessage()] = ($dilewati[$galat->getMessage()] ?? 0) + 1;
            }
        }

        return ['Diubah' => $diubah, 'Dilewati' => $dilewati];
    }
}
