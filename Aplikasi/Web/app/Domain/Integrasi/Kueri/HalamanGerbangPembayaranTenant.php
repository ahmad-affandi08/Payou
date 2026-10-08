<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Kueri;

use App\Domain\Integrasi\Enum\LingkunganGerbang;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;
use App\Domain\Integrasi\Layanan\KatalogPenyediaGerbang;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use App\Domain\Integrasi\Model\SubAkunPembayaran;

/**
 * Data halaman gerbang pembayaran tenant (v2.06): penyedia yang diizinkan platform beserta bidangnya, dan gerbang
 * tersimpan tanpa kredensial (hanya petunjuk 4 karakter terakhir, BR-P05.1) plus URL webhook tenant. `SubAkun` = status
 * sub account DOKU tenant ini (dibuat pengelola), hanya baca: status dan labelnya, tanpa ID dan tanpa pesan galat.
 */
final class HalamanGerbangPembayaranTenant
{
    public function __construct(private readonly KatalogPenyediaGerbang $katalog) {}

    /**
     * @return array<string, mixed>
     */
    public function Ambil(): array
    {
        $gerbang = GerbangPembayaranTenant::query()->first();
        $diizinkan = $this->katalog->AmbilDiizinkan();
        $subAkun = SubAkunPembayaran::query()->where('Penyedia', SubAkunPembayaran::PENYEDIA_DOKU)->first(['Id', 'IdTenant', 'Penyedia', 'Status']);

        return [
            'SubAkun' => $subAkun === null ? null : ['Status' => $subAkun->Status->value, 'LabelStatus' => $subAkun->Status->AmbilLabel()],
            'Gerbang' => $gerbang === null ? null : [
                'Uuid' => $gerbang->Uuid,
                'Penyedia' => $gerbang->Penyedia->value,
                'LabelPenyedia' => $gerbang->Penyedia->AmbilLabel(),
                'PenyediaDiizinkan' => in_array($gerbang->Penyedia, $diizinkan, true),
                'Lingkungan' => $gerbang->Lingkungan->value,
                'Pengaturan' => $gerbang->Pengaturan,
                'PetunjukKredensial' => $gerbang->PetunjukKredensial,
                'StatusUji' => $gerbang->StatusUji->value,
                'LabelStatusUji' => $gerbang->StatusUji->AmbilLabel(),
                'PesanUji' => $gerbang->PesanUji,
                'DiujiPada' => $gerbang->DiujiPada?->toIso8601String(),
                'Aktif' => $gerbang->Aktif,
                'UrlWebhook' => PembuatGerbangPembayaran::BuatUrlWebhook($gerbang->Penyedia, $gerbang->TokenWebhook),
                'WebhookDiterimaPada' => $gerbang->WebhookDiterimaPada?->toIso8601String(),
                'WebhookDitolakPada' => $gerbang->WebhookDitolakPada?->toIso8601String(),
            ],
            'DaftarPenyedia' => array_map(fn (PenyediaGerbang $p): array => [
                'Nilai' => $p->value,
                'Label' => $p->AmbilLabel(),
                'Keterangan' => $p->AmbilKeterangan(),
                'BidangPengaturan' => $p->AmbilBidangPengaturan(),
                'BidangKredensial' => $p->AmbilBidangKredensial(),
            ], $diizinkan),
            'DaftarLingkungan' => array_map(fn (LingkunganGerbang $l): array => ['Nilai' => $l->value, 'Label' => $l->AmbilLabel()], LingkunganGerbang::cases()),
        ];
    }
}
