<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Tenant\Kueri\PemilikTenant;
use App\Domain\Pengelola\Tenant\Surel\LanggananDiaktifkanKembali;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Aktifkan kembali tenant yang ditangguhkan (P-07, BR-P07.5) oleh Keuangan atau Super Admin, dengan keputusan tertulis
 * (alasan wajib). Tujuan ditentukan `TentukanTujuan`:
 * - Trial yang habis selama ditangguhkan turun ke paket Gratis (BR-00.3).
 * - Status berbayar (Aktif/Tertunggak, atau penangguhan karena tunggakan) diperiksa terhadap periode langganan: periode
 *   masih berjalan → Aktif; periode habis tetapi masih dalam masa tenggang → Tertunggak; lewat masa tenggang → tagihan
 *   belum lunas, sehingga tenant tidak dipulihkan (penjadwal P-08 akan langsung menangguhkannya lagi).
 *   - Penangguhan manual dalam keadaan itu dicabut menjadi penangguhan karena tunggakan (tetap Ditangguhkan), agar
 *     tenant bisa membayar dan pembayaran yang diterima Keuangan memulihkannya (BR-P08.9).
 *   - Penangguhan karena tunggakan ditolak: jalan keluarnya pembayaran diterima, bukan tombol ini.
 */
final class AktifkanKembaliTenant
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly PemilikTenant $pemilik,
    ) {}

    /**
     * Status tujuan bila langganan ini diaktifkan kembali sekarang. `null` bila tidak bisa diaktifkan kembali karena
     * tunggakan belum dibayar; `Ditangguhkan` berarti penangguhan manual dicabut tetapi tunggakan tetap berlaku.
     */
    public static function TentukanTujuan(Langganan $langganan, ?CarbonInterface $sekarang = null): ?StatusLangganan
    {
        $sekarang ??= now();
        $asal = $langganan->StatusSebelumDitangguhkan ?? StatusLangganan::Aktif;

        if ($asal === StatusLangganan::Trial && ($langganan->TrialBerakhirPada === null || ! $langganan->TrialBerakhirPada->greaterThan($sekarang))) {
            return StatusLangganan::Gratis;
        }

        if (! in_array($asal, [StatusLangganan::Aktif, StatusLangganan::Tertunggak], true) || $langganan->PeriodeSelesai === null) {
            return $asal;
        }

        if ($langganan->PeriodeSelesai->greaterThan($sekarang)) {
            return StatusLangganan::Aktif;
        }

        if ($langganan->PeriodeSelesai->copy()->addDays((int) config('tagihan.HariMasaTenggang'))->greaterThan($sekarang)) {
            return StatusLangganan::Tertunggak;
        }

        return $langganan->StatusSebelumDitangguhkan !== null ? StatusLangganan::Ditangguhkan : null;
    }

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant, string $alasan): Langganan
    {
        $langganan = DB::transaction(function () use ($pelaku, $tenant, $alasan): Langganan {
            $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('LanggananTidakAda', 'Tenant ini belum punya langganan.');

            if ($langganan->Status !== StatusLangganan::Ditangguhkan) {
                throw new PelanggaranAturanBisnis('BR-P07.5', 'Hanya tenant yang ditangguhkan yang bisa diaktifkan kembali.');
            }

            $tujuan = self::TentukanTujuan($langganan) ?? throw new PelanggaranAturanBisnis(
                'BR-P07.5',
                'Tenant ini ditangguhkan karena tagihan belum dibayar melewati masa tenggang. Tenant aktif kembali otomatis saat pembayarannya diterima di menu Tagihan.',
            );
            $nilaiLama = [
                'Status' => $langganan->Status->value,
                'StatusSebelumDitangguhkan' => $langganan->StatusSebelumDitangguhkan?->value,
                'IdPaket' => $langganan->IdPaket,
            ];
            $ubah = ['Status' => $tujuan, 'StatusSebelumDitangguhkan' => null];

            if ($tujuan === StatusLangganan::Gratis && $langganan->StatusSebelumDitangguhkan === StatusLangganan::Trial) {
                $ubah['IdPaket'] = (Paket::query()->where('Kode', (string) config('tenant.KodePaketGratis'))->first()
                    ?? throw new RuntimeException('Paket Gratis (config tenant.KodePaketGratis) tidak ditemukan.'))->Id;
            }

            $langganan->update($ubah);

            $this->audit->Catat(
                'tenant.aktifkan',
                $langganan,
                nilaiLama: $nilaiLama,
                nilaiBaru: ['Status' => $tujuan->value, 'IdPaket' => $langganan->IdPaket],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $langganan;
        });

        // Penangguhan manual yang dicabut tetapi masih tertahan tunggakan tidak diumumkan sebagai "aktif kembali";
        // banner tunggakan di back-office sudah mengarahkan Owner untuk membayar.
        $penerima = $langganan->Status === StatusLangganan::Ditangguhkan ? [] : $this->pemilik->Ambil($tenant->Id);

        foreach ($penerima as $pemilik) {
            try {
                Mail::to($pemilik['Email'])->queue(new LanggananDiaktifkanKembali($pemilik['Nama'], $tenant->Nama, $langganan->Status->AmbilLabel()));
            } catch (Throwable $galat) {
                Log::warning('Email pengaktifan kembali tenant gagal dikirim.', ['IdTenant' => $tenant->Id, 'Galat' => $galat->getMessage()]);
            }
        }

        return $langganan;
    }
}
