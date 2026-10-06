<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Enum\StatusKomisiMitra;
use App\Domain\Tenant\Enum\StatusMitra;
use App\Domain\Tenant\Model\AtribusiMitra;
use App\Domain\Tenant\Model\KomisiMitra;
use App\Domain\Tenant\Model\Mitra;
use App\Domain\Tenant\Model\TagihanLangganan;
use Brick\Math\BigDecimal;

/**
 * P-12 langkah 4 (BR-P12.1): komisi mitra dihitung otomatis saat tagihan langganan **lunas**, di transaksi pelunasan
 * yang sama (`PelunasTagihanLangganan`). Dasar komisi = Subtotal − Diskon (tanpa PPN, karena PPN bukan pendapatan
 * Payoung); jumlah = dasar × `PersenKomisi` %, dibulatkan setengah ke atas per rupiah sen. Mitra non-berulang (referral)
 * hanya berkomisi dari tagihan lunas pertama tenant itu. Tidak berkomisi bila tenant tidak dirujuk, mitra
 * ditangguhkan, persen 0, atribusi sudah berakhir, atau dasarnya nol. Idempoten per tagihan.
 */
final class PencatatKomisiMitra
{
    public function CatatDariTagihan(TagihanLangganan $tagihan): ?KomisiMitra
    {
        $atribusi = AtribusiMitra::query()->where('IdTenant', $tagihan->IdTenant)->first();

        if (! $atribusi instanceof AtribusiMitra || ($atribusi->BerakhirPada !== null && $atribusi->BerakhirPada->isPast())) {
            return null;
        }

        $mitra = Mitra::query()->whereKey($atribusi->IdMitra)->first();

        if (! $mitra instanceof Mitra || $mitra->Status !== StatusMitra::Aktif || BigDecimal::of($mitra->PersenKomisi)->isZero()) {
            return null;
        }

        $sudah = KomisiMitra::query()->where('IdTagihanLangganan', $tagihan->Id)->first();

        if ($sudah instanceof KomisiMitra) {
            return $sudah;
        }

        if (! $mitra->KomisiBerulang && KomisiMitra::query()->where('IdMitra', $mitra->Id)->where('IdTenant', $tagihan->IdTenant)
            ->where('Status', '!=', StatusKomisiMitra::Dibatalkan->value)->exists()) {
            return null;
        }

        $dasar = Uang::Dari($tagihan->Subtotal)->Kurangi(Uang::Dari($tagihan->Diskon));

        if ($dasar->BernilaiNol() || $dasar->BernilaiNegatif()) {
            return null;
        }

        return KomisiMitra::query()->create([
            'IdMitra' => $mitra->Id,
            'IdTenant' => $tagihan->IdTenant,
            'IdTagihanLangganan' => $tagihan->Id,
            'NomorTagihan' => $tagihan->Nomor,
            'DasarKomisi' => $dasar->KeString(),
            'PersenKomisi' => $mitra->PersenKomisi,
            'Jumlah' => $dasar->Kali(BigDecimal::of($mitra->PersenKomisi)->dividedBy(100, 6))->KeString(),
            'Status' => StatusKomisiMitra::Tertunda,
        ]);
    }
}
