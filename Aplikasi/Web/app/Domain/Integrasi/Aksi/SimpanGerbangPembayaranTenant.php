<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\Data\DataGerbangPembayaranTenant;
use App\Domain\Integrasi\Enum\StatusUjiGerbang;
use App\Domain\Integrasi\Layanan\KatalogPenyediaGerbang;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyimpan gerbang pembayaran QRIS dinamis milik tenant (F-08, P-05 v2.06): penyedia harus diizinkan platform,
 * kredensial langsung terenkripsi dengan petunjuk 4 karakter terakhir (BR-P05.1), ganti penyedia = kredensial wajib
 * diisi ulang. Perubahan penyedia/lingkungan/pengaturan/kredensial mengembalikan status ke `BelumDiuji` dan
 * menonaktifkan gerbang sampai diuji ulang (pola BR-P05.4). Log audit hanya memuat nama bidang kredensial yang diganti.
 */
final class SimpanGerbangPembayaranTenant
{
    public const PANJANG_TOKEN = 40;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly KatalogPenyediaGerbang $katalog,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataGerbangPembayaranTenant $data): GerbangPembayaranTenant
    {
        if (! $this->katalog->CekDiizinkan($data->penyedia)) {
            throw new PelanggaranAturanBisnis('PenyediaTidakDiizinkan', 'Penyedia ini tidak tersedia dari platform. Hubungi dukungan Payoung.', 'Penyedia');
        }

        try {
            return DB::transaction(fn (): GerbangPembayaranTenant => $this->Simpan($data));
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('SudahAda', 'Gerbang pembayaran baru saja disimpan pengguna lain. Muat ulang halaman.');
        }
    }

    private function Simpan(DataGerbangPembayaranTenant $data): GerbangPembayaranTenant
    {
        $gerbang = GerbangPembayaranTenant::query()->lockForUpdate()->first();
        $gantiPenyedia = $gerbang !== null && $gerbang->Penyedia !== $data->penyedia;
        $kredensialLama = $gerbang === null || $gantiPenyedia ? [] : $gerbang->Kredensial;
        $kredensialBaru = [];
        $kredensialBerubah = [];

        foreach ($data->penyedia->AmbilBidangKredensial() as $bidang) {
            $kunci = $bidang['Kunci'];
            $nilai = $data->kredensial[$kunci] ?? '';

            if ($nilai === '') {
                if (! isset($kredensialLama[$kunci])) {
                    throw new PelanggaranAturanBisnis('KredensialWajib', "{$bidang['Label']} wajib diisi.", "Kredensial.{$kunci}");
                }

                $kredensialBaru[$kunci] = $kredensialLama[$kunci];

                continue;
            }

            $kredensialBaru[$kunci] = $nilai;

            if (($kredensialLama[$kunci] ?? null) !== $nilai) {
                $kredensialBerubah[] = $kunci;
            }
        }

        $isiBerubah = $gerbang === null
            || $gantiPenyedia
            || $gerbang->Lingkungan !== $data->lingkungan
            || $gerbang->Pengaturan !== $data->pengaturan
            || $kredensialBerubah !== [];
        $nilaiLama = $gerbang === null ? null : self::AmbilRingkasan($gerbang);

        $gerbang ??= new GerbangPembayaranTenant(['TokenWebhook' => self::BuatToken($this->konteks->Wajib())]);
        $gerbang->fill([
            'Penyedia' => $data->penyedia,
            'Lingkungan' => $data->lingkungan,
            'Pengaturan' => $data->pengaturan,
            'Kredensial' => $kredensialBaru,
            'PetunjukKredensial' => array_map(self::BuatPetunjuk(...), $kredensialBaru),
        ]);

        if ($isiBerubah) {
            // Pola BR-P05.4: isian yang belum terbukti tersambung tidak dipakai transaksi.
            $gerbang->fill(['StatusUji' => StatusUjiGerbang::BelumDiuji, 'PesanUji' => null, 'DiujiPada' => null, 'Aktif' => false]);
        }

        $gerbang->save();

        $this->audit->Catat(
            $nilaiLama === null ? 'gerbang-pembayaran.buat' : 'gerbang-pembayaran.ubah',
            $gerbang,
            $nilaiLama,
            [...self::AmbilRingkasan($gerbang), 'KredensialDiganti' => $kredensialBerubah],
        );

        return $gerbang;
    }

    /** `{IdTenant basis-36}-{acak}`: bagian tenant menetapkan scope pencarian webhook tanpa query lintas tenant. */
    public static function BuatToken(int $idTenant): string
    {
        return base_convert((string) $idTenant, 10, 36).'-'.Str::random(self::PANJANG_TOKEN);
    }

    /** BR-P05.1: hanya 4 karakter terakhir; nilai pendek tidak ditampilkan sama sekali. */
    public static function BuatPetunjuk(string $nilai): string
    {
        return mb_strlen($nilai) >= 12 ? '••••'.mb_substr($nilai, -4) : '••••';
    }

    /**
     * @return array<string, mixed>
     */
    public static function AmbilRingkasan(GerbangPembayaranTenant $gerbang): array
    {
        return [
            'Penyedia' => $gerbang->Penyedia->value,
            'Lingkungan' => $gerbang->Lingkungan->value,
            'Pengaturan' => $gerbang->Pengaturan,
            'StatusUji' => $gerbang->StatusUji->value,
            'Aktif' => $gerbang->Aktif,
        ];
    }
}
