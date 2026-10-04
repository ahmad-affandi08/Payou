<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Data\RincianTagihanLangganan;
use App\Domain\Tenant\Enum\JenisKupon;
use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Kalkulasi tagihan langganan (P-08 langkah 1, PRD §12.2). Murni: tanpa kueri, tanpa float, pembulatan eksplisit.
 *
 * Urutan & pembulatan (keputusan agen P-08, dikonfirmasi pemilik produk):
 * 1. Subtotal = harga paket per siklus (Bulanan/Tahunan) dari `HargaPaket` berlaku.
 * 2. Diskon kupon (BR-P04.7), dibulatkan ke rupiah terdekat (HalfUp) dan tidak melebihi subtotal:
 *    - Persen: Subtotal × Nilai% × BulanDiskon ÷ JumlahBulan (kupon 3 bulan pada tagihan tahunan = 3/12 bagian).
 *    - Nominal: Nilai per bulan × BulanDiskon.
 * 3. DPP = (Subtotal − Diskon) × PengaliDpp (pecahan, misal 11/12), dibulatkan ke bawah ke rupiah penuh.
 * 4. PPN = DPP × Tarif%, dibulatkan ke bawah ke rupiah penuh (kebiasaan faktur pajak).
 * 5. Total = Subtotal − Diskon + PPN.
 *
 * D-49: `tambahan` = jumlah add-on pada tagihan perpanjangan. Kupon hanya mengurangi harga paket (diskon dihitung dari
 * `subtotal` paket), sedangkan add-on masuk ke Subtotal tagihan, DPP, dan PPN: Total = (paket + add-on) − Diskon + PPN.
 */
final class KalkulatorTagihanLangganan
{
    /**
     * @param  int  $jumlahBulan  1 (Bulanan) atau 12 (Tahunan)
     * @param  int  $bulanDiskon  bulan yang tercakup kupon pada tagihan ini (0 = tanpa kupon)
     * @param  string|null  $tarifPersen  tarif PPN persen string desimal, null = platform non-PKP (tanpa PPN)
     */
    public function Hitung(
        Uang $subtotal,
        int $jumlahBulan,
        ?JenisKupon $jenisKupon = null,
        ?string $nilaiKupon = null,
        int $bulanDiskon = 0,
        ?string $tarifPersen = null,
        int $pengaliDppPembilang = 1,
        int $pengaliDppPenyebut = 1,
        ?Uang $tambahan = null,
    ): RincianTagihanLangganan {
        if ($jumlahBulan < 1 || $subtotal->BernilaiNegatif()) {
            throw new InvalidArgumentException('Subtotal tidak boleh negatif dan jumlah bulan minimal 1.');
        }

        if ($pengaliDppPembilang < 1 || $pengaliDppPenyebut < 1) {
            throw new InvalidArgumentException('Pengali DPP harus pecahan positif.');
        }

        $bulanDiskon = $jenisKupon === null || $nilaiKupon === null ? 0 : max(0, min($bulanDiskon, $jumlahBulan));
        $diskon = $bulanDiskon === 0 ? Uang::Nol() : $this->HitungDiskon($subtotal, $jumlahBulan, $jenisKupon, (string) $nilaiKupon, $bulanDiskon);
        $tambahan ??= Uang::Nol();

        if ($tambahan->BernilaiNegatif()) {
            throw new InvalidArgumentException('Jumlah add-on tidak boleh negatif.');
        }

        $setelahDiskon = $subtotal->Kurangi($diskon)->Tambah($tambahan);
        $subtotal = $subtotal->Tambah($tambahan);

        if ($tarifPersen === null) {
            return new RincianTagihanLangganan($subtotal, $diskon, Uang::Nol(), Uang::Nol(), $setelahDiskon, $bulanDiskon);
        }

        $dpp = self::KeRupiah(
            BigRational::of($setelahDiskon->KeString())->multipliedBy(BigRational::ofFraction($pengaliDppPembilang, $pengaliDppPenyebut)),
            RoundingMode::Down,
        );
        $ppn = self::KeRupiah(
            BigRational::of($dpp->KeString())->multipliedBy(BigRational::of($tarifPersen))->dividedBy(100),
            RoundingMode::Down,
        );

        return new RincianTagihanLangganan($subtotal, $diskon, $dpp, $ppn, $setelahDiskon->Tambah($ppn), $bulanDiskon);
    }

    private function HitungDiskon(Uang $subtotal, int $jumlahBulan, ?JenisKupon $jenis, string $nilai, int $bulanDiskon): Uang
    {
        $mentah = match ($jenis) {
            JenisKupon::Persen => BigRational::of($subtotal->KeString())
                ->multipliedBy(BigRational::of($nilai))
                ->dividedBy(100)
                ->multipliedBy(BigRational::ofFraction($bulanDiskon, $jumlahBulan)),
            JenisKupon::Nominal => BigRational::of($nilai)->multipliedBy($bulanDiskon),
            null => BigRational::zero(),
        };
        $diskon = self::KeRupiah($mentah, RoundingMode::HalfUp);

        return $diskon->Bandingkan($subtotal) > 0 ? $subtotal : $diskon;
    }

    private static function KeRupiah(BigRational $nilai, RoundingMode $mode): Uang
    {
        return Uang::Dari(BigDecimal::of($nilai->toScale(0, $mode)));
    }
}
