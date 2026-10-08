<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Tenant\Enum\KategoriPenangguhan;
use App\Domain\Pengelola\Tenant\Kueri\PemilikTenant;
use App\Domain\Pengelola\Tenant\Surel\LanggananDitangguhkan;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tangguhkan manual oleh Super Admin (P-07, BR-P07.4): kategori (penipuan, penyalahgunaan, permintaan hukum, lainnya)
 * dan catatan wajib. Langganan menjadi `Ditangguhkan`; status sebelumnya disimpan untuk dipulihkan saat diaktifkan
 * kembali. Tidak ada data tenant yang dihapus (BR-P07.1). Owner diberi tahu lewat email setelah transaksi selesai;
 * kegagalan kirim email dicatat di log dan tidak membatalkan penangguhan.
 */
final class TangguhkanTenant
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly PemilikTenant $pemilik,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant, KategoriPenangguhan $kategori, string $catatan): Langganan
    {
        $langganan = DB::transaction(function () use ($pelaku, $tenant, $kategori, $catatan): Langganan {
            $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('LanggananTidakAda', 'Tenant ini belum punya langganan.');
            $asal = $langganan->Status;

            if ($asal === StatusLangganan::Ditangguhkan) {
                throw new PelanggaranAturanBisnis('BR-P07.4', 'Tenant ini sudah ditangguhkan.');
            }

            if (! $asal->BisaBerubahKe(StatusLangganan::Ditangguhkan)) {
                throw new PelanggaranAturanBisnis('BR-P07.4', "Langganan berstatus {$asal->AmbilLabel()} tidak bisa ditangguhkan.");
            }

            $langganan->update(['Status' => StatusLangganan::Ditangguhkan, 'StatusSebelumDitangguhkan' => $asal]);

            $this->audit->Catat(
                'tenant.tangguhkan',
                $langganan,
                nilaiLama: ['Status' => $asal->value],
                nilaiBaru: ['Status' => StatusLangganan::Ditangguhkan->value, 'Kategori' => $kategori->value],
                alasan: "{$kategori->AmbilLabel()}: {$catatan}",
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $langganan;
        });

        foreach ($this->pemilik->Ambil($tenant->Id) as $pemilik) {
            try {
                Mail::to($pemilik['Email'])->queue(new LanggananDitangguhkan($pemilik['Nama'], $tenant->Nama, $kategori->AmbilLabel()));
            } catch (Throwable $galat) {
                Log::warning('Email penangguhan tenant gagal dikirim.', ['IdTenant' => $tenant->Id, 'Galat' => $galat->getMessage()]);
            }
        }

        return $langganan;
    }
}
