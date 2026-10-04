<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Promo\Enum\StatusVoucher;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Aksi massal voucher satu promo (F-16c bagian 2, izin `pelanggan.kelola`): nonaktifkan atau aktifkan voucher
 * terpilih, atau nonaktifkan sekaligus semua voucher aktif yang sudah lewat tanggal kedaluwarsanya sendiri. Voucher
 * tidak dihapus: riwayat pemakaian tetap utuh dan kode yang nonaktif tidak bisa dipesan kasir. Lewat `UbahStatusVoucher`
 * (audit per voucher); yang sudah berstatus tujuan dilewati. Voucher harus milik promo yang sedang dibuka.
 */
final class UbahVoucherMassal
{
    public const MAKS = 1000;

    public const AKSI = ['Nonaktifkan', 'Aktifkan'];

    public function __construct(private readonly UbahStatusVoucher $ubahStatus) {}

    /**
     * @param  list<string>  $uuid
     * @return array{Diubah: int, Dilewati: int}
     *
     * @throws PelanggaranAturanBisnis AksiTidakDikenal, PilihanKosong, TerlaluBanyak, VoucherTidakDikenal
     */
    public function Jalankan(Promo $promo, string $aksi, array $uuid, int $idPengguna): array
    {
        if (! in_array($aksi, self::AKSI, true)) {
            throw new PelanggaranAturanBisnis('AksiTidakDikenal', 'Aksi massal tidak dikenal.', 'Aksi');
        }

        $uuid = array_values(array_unique($uuid));

        if ($uuid === []) {
            throw new PelanggaranAturanBisnis('PilihanKosong', 'Pilih minimal satu voucher.', 'Uuid');
        }

        if (count($uuid) > self::MAKS) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Maksimal '.self::MAKS.' voucher sekali proses.', 'Uuid');
        }

        return DB::transaction(function () use ($promo, $aksi, $uuid, $idPengguna): array {
            $voucher = Voucher::query()->where('IdPromo', $promo->Id)->whereIn('Uuid', $uuid)->orderBy('Id')->get();

            if ($voucher->count() !== count($uuid)) {
                throw new PelanggaranAturanBisnis('VoucherTidakDikenal', 'Sebagian voucher tidak ditemukan di promo ini. Muat ulang halaman.', 'Uuid');
            }

            return $this->Terapkan($voucher->all(), $aksi === 'Nonaktifkan' ? StatusVoucher::Nonaktif : StatusVoucher::Aktif, $idPengguna);
        });
    }

    /**
     * Nonaktifkan semua voucher aktif promo ini yang tanggal kedaluwarsanya sendiri sudah lewat.
     *
     * @return array{Diubah: int, Dilewati: int}
     */
    public function NonaktifkanKedaluwarsa(Promo $promo, int $idPengguna): array
    {
        return DB::transaction(function () use ($promo, $idPengguna): array {
            $voucher = Voucher::query()
                ->where('IdPromo', $promo->Id)
                ->where('Status', StatusVoucher::Aktif->value)
                ->whereNotNull('KedaluwarsaPada')
                ->where('KedaluwarsaPada', '<=', now())
                ->orderBy('Id')
                ->get();

            return $this->Terapkan($voucher->all(), StatusVoucher::Nonaktif, $idPengguna);
        });
    }

    /**
     * @param  array<int, Voucher>  $voucher
     * @return array{Diubah: int, Dilewati: int}
     */
    private function Terapkan(array $voucher, StatusVoucher $tujuan, int $idPengguna): array
    {
        $diubah = 0;

        foreach ($voucher as $v) {
            if ($v->Status === $tujuan) {
                continue;
            }

            $this->ubahStatus->Jalankan($v, $tujuan, $idPengguna);
            $diubah++;
        }

        return ['Diubah' => $diubah, 'Dilewati' => count($voucher) - $diubah];
    }
}
