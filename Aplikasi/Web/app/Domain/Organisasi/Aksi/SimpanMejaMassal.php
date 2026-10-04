<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataMeja;
use App\Domain\Organisasi\Enum\BentukMeja;
use App\Domain\Organisasi\Model\Meja;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * F-10a: buat banyak meja sekaligus di satu outlet, bernomor berurutan ("Meja 1" sampai "Meja 20"; awalan dipisah satu spasi dari nomor). Tiap meja lewat
 * `SimpanMeja` (nama unik per outlet, area aktif, mode meja aktif, audit `meja.buat` per meja). Semua atau tidak sama
 * sekali: satu nama yang sudah dipakai menggagalkan seluruhnya dan pesannya menyebut nama itu. Urutan tampil mengikuti
 * nomor. Maksimal [MAKS] meja sekali buat; panjang nama gabungan (awalan + nomor) maksimal 30 karakter.
 */
final class SimpanMejaMassal
{
    public const MAKS = 100;

    public function __construct(private readonly SimpanMeja $simpan) {}

    /**
     * @return list<Meja>
     *
     * @throws PelanggaranAturanBisnis JumlahTidakValid, NamaTerlaluPanjang, NamaMejaDipakai, AreaTidakValid, serta penolakan penjaga mode meja
     */
    public function Jalankan(Outlet $outlet, string $awalan, int $mulai, int $jumlah, ?string $uuidArea, int $kapasitas, BentukMeja $bentuk): array
    {
        if ($jumlah < 1 || $jumlah > self::MAKS) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah meja antara 1 dan '.self::MAKS.'.', 'Jumlah');
        }

        if ($mulai < 0 || $mulai + $jumlah - 1 > 9999) {
            throw new PelanggaranAturanBisnis('NomorTidakValid', 'Nomor meja antara 0 dan 9999.', 'Mulai');
        }

        // Awalan selalu dipisah satu spasi dari nomor ("Meja" + 7 = "Meja 7"); tanpa awalan, nama hanyalah nomor.
        $awalan = trim($awalan);
        $pemisah = $awalan === '' ? '' : ' ';

        if (mb_strlen($awalan.$pemisah) + strlen((string) ($mulai + $jumlah - 1)) > 30) {
            throw new PelanggaranAturanBisnis('NamaTerlaluPanjang', 'Awalan terlalu panjang. Nama meja paling panjang 30 karakter.', 'Awalan');
        }

        return DB::transaction(function () use ($outlet, $awalan, $pemisah, $mulai, $jumlah, $uuidArea, $kapasitas, $bentuk): array {
            $dibuat = [];

            for ($nomor = $mulai; $nomor < $mulai + $jumlah; $nomor++) {
                $dibuat[] = $this->simpan->Jalankan($outlet, null, new DataMeja($awalan.$pemisah.$nomor, $uuidArea, $kapasitas, $bentuk, $nomor));
            }

            return $dibuat;
        });
    }
}
