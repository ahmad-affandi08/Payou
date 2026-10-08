<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Enum\StatusUjiGerbang;
use App\Domain\Integrasi\Layanan\KatalogPenyediaGerbang;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengaktifkan atau menonaktifkan gerbang pembayaran tenant (v2.06). Aktif hanya bila uji koneksi terakhir berhasil
 * setelah perubahan terakhir (pola BR-P05.4) dan penyedianya masih diizinkan platform.
 */
final class UbahStatusGerbangPembayaranTenant
{
    public function __construct(
        private readonly KatalogPenyediaGerbang $katalog,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(bool $aktif): GerbangPembayaranTenant
    {
        return DB::transaction(function () use ($aktif): GerbangPembayaranTenant {
            $gerbang = GerbangPembayaranTenant::query()->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('GerbangBelumDiatur', 'Simpan gerbang pembayaran dulu.');

            if ($aktif && $gerbang->StatusUji !== StatusUjiGerbang::Berhasil) {
                throw new PelanggaranAturanBisnis('UjiDulu', 'Uji koneksi sampai berhasil sebelum mengaktifkan gerbang pembayaran.');
            }

            if ($aktif && ! $this->katalog->CekDiizinkan($gerbang->Penyedia)) {
                throw new PelanggaranAturanBisnis('PenyediaTidakDiizinkan', 'Penyedia ini tidak tersedia lagi dari platform. Hubungi dukungan Payoung.', 'Penyedia');
            }

            if ($gerbang->Aktif === $aktif) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', $aktif ? 'Gerbang pembayaran sudah aktif.' : 'Gerbang pembayaran sudah nonaktif.');
            }

            $gerbang->update(['Aktif' => $aktif]);
            $this->audit->Catat(
                $aktif ? 'gerbang-pembayaran.aktifkan' : 'gerbang-pembayaran.nonaktifkan',
                $gerbang,
                ['Aktif' => ! $aktif],
                ['Aktif' => $aktif, 'Penyedia' => $gerbang->Penyedia->value, 'Lingkungan' => $gerbang->Lingkungan->value],
            );

            return $gerbang;
        });
    }
}
