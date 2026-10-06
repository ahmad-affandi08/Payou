<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\DataBawaan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Domain\Lisensi\Kueri\LisensiBerlaku;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pajak\Peristiwa\TarifPajakTerbit;
use App\Domain\Referensi\Enum\JenisHariLibur;
use App\Domain\Referensi\Model\HariLibur;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * D-35 edisi Lisensi: memasang paket data master hasil `EksporDataMasterLisensi` dari server SaaS Payoung (tarif pajak
 * & hari libur yang sudah terbit lewat four-eyes di konsol). Idempoten; dijalankan setiap Payoung mengirim paket baru
 * (misal tarif PPN berubah atau hari libur tahun depan terbit).
 *
 * - Tarif: yang nilainya sama dengan tarif terbit terakhir (jenis & wilayah sama) dilewati, termasuk tarif bawaan yang
 *   terbit saat pemasangan. Tarif baru yang berlaku setelah tarif terbit terakhir diterbitkan dan mengakhiri tarif
 *   lama sehari sebelumnya (seperti BR-P02.1); tarif yang berlaku lebih awal dari tarif terbit terakhir dilewati
 *   karena tarif terbit tidak boleh diubah mundur.
 * - Hari libur: ditambahkan bila tanggal & namanya belum ada; pembatalan di paket ikut membatalkan baris setempat.
 * - D-36: berkas yang dibuat setelah masa pembaruan lisensi berakhir ditolak (pembaruan data master termasuk layanan
 *   pemeliharaan). Lisensi format 1 tanpa `PembaruanSampai` tidak dibatasi.
 */
final class ImporDataMasterLisensi
{
    public function __construct(private readonly LisensiBerlaku $lisensiBerlaku) {}

    /**
     * @return array{TarifBaru: int, TarifDilewati: int, HariLiburBaru: int, HariLiburDibatalkan: int}
     */
    public function Jalankan(string $isiBerkas): array
    {
        if (! EdisiAplikasi::CekLisensi()) {
            throw new PelanggaranAturanBisnis('D-35', 'Impor data master hanya untuk edisi Lisensi. Di edisi SaaS data master diterbitkan lewat konsol.');
        }

        $paket = json_decode($isiBerkas, true);

        if (! is_array($paket) || ($paket['Format'] ?? null) !== EksporDataMasterLisensi::VERSI_FORMAT
            || ! is_array($paket['TarifPajak'] ?? null) || ! is_array($paket['HariLibur'] ?? null)) {
            throw new PelanggaranAturanBisnis('D-35', 'Berkas data master rusak atau formatnya tidak dikenal.');
        }

        $this->PastikanDalamMasaPembaruan($paket['DibuatPada'] ?? null);

        $hasil = DB::transaction(fn (): array => [
            ...$this->ImporTarif($paket['TarifPajak']),
            ...$this->ImporHariLibur($paket['HariLibur']),
        ]);

        foreach ($hasil['IdTarifTerbit'] as $idTarif) {
            TarifPajakTerbit::dispatch($idTarif);
        }

        return [
            'TarifBaru' => count($hasil['IdTarifTerbit']),
            'TarifDilewati' => $hasil['TarifDilewati'],
            'HariLiburBaru' => $hasil['HariLiburBaru'],
            'HariLiburDibatalkan' => $hasil['HariLiburDibatalkan'],
        ];
    }

    private function PastikanDalamMasaPembaruan(mixed $dibuatPada): void
    {
        $lisensi = $this->lisensiBerlaku->Ambil();

        if ($lisensi?->pembaruanSampai === null) {
            return;
        }

        try {
            $tanggal = is_string($dibuatPada) ? CarbonImmutable::parse($dibuatPada)->setTimezone('Asia/Jakarta')->toDateString() : null;
        } catch (\Throwable) {
            $tanggal = null;
        }

        if ($tanggal === null) {
            throw new PelanggaranAturanBisnis('D-35', 'Berkas data master rusak: tanggal pembuatan tidak terbaca.');
        }

        if (! $lisensi->CekDalamMasaPembaruan($tanggal)) {
            throw new PelanggaranAturanBisnis('D-36', "Berkas data master ini dibuat {$tanggal}, setelah masa pembaruan lisensi {$lisensi->nomor} berakhir ({$lisensi->pembaruanSampai}). Perpanjang pemeliharaan ke Payoung untuk menerima tarif pajak & hari libur terbaru.");
        }
    }

    /**
     * @param  array<mixed>  $daftar
     * @return array{IdTarifTerbit: list<int>, TarifDilewati: int}
     */
    private function ImporTarif(array $daftar): array
    {
        $terbit = [];
        $dilewati = 0;

        foreach ($daftar as $baris) {
            $data = self::BacaTarif($baris);
            $jenis = JenisPajak::query()->where('Kode', $data['KodeJenisPajak'])->lockForUpdate()->first();

            if ($jenis === null) {
                throw new PelanggaranAturanBisnis('D-35', "Jenis pajak {$data['KodeJenisPajak']} belum ada. Jalankan lisensi:siapkan-data dulu.");
            }

            $terakhir = TarifPajak::query()
                ->where('IdJenisPajak', $jenis->Id)
                ->where('KodeWilayah', $data['KodeWilayah'])
                ->where('Status', StatusDataMaster::Terbit->value)
                ->orderByDesc('BerlakuMulai')
                ->lockForUpdate()
                ->first();

            if ($terakhir !== null && (self::CekNilaiSama($terakhir, $data) || $data['BerlakuMulai'] <= $terakhir->BerlakuMulai->toDateString())) {
                $dilewati++;

                continue;
            }

            if ($terakhir !== null) {
                $terakhir->update(['BerlakuSampai' => now()->parse($data['BerlakuMulai'])->subDay()->toDateString()]);
            }

            $baru = TarifPajak::query()->create([
                'IdJenisPajak' => $jenis->Id,
                'Tarif' => $data['Tarif'],
                'PengaliDppPembilang' => $data['PengaliDppPembilang'],
                'PengaliDppPenyebut' => $data['PengaliDppPenyebut'],
                'KodeWilayah' => $data['KodeWilayah'],
                'BiayaLayananMasukDpp' => $data['BiayaLayananMasukDpp'],
                'BerlakuMulai' => $data['BerlakuMulai'],
                'Status' => StatusDataMaster::Terbit,
                'NomorDasarHukum' => $data['NomorDasarHukum'],
                'TautanDasarHukum' => $data['TautanDasarHukum'],
            ]);
            $terbit[] = $baru->Id;
        }

        return ['IdTarifTerbit' => $terbit, 'TarifDilewati' => $dilewati];
    }

    /**
     * @param  array<mixed>  $daftar
     * @return array{HariLiburBaru: int, HariLiburDibatalkan: int}
     */
    private function ImporHariLibur(array $daftar): array
    {
        $baru = 0;
        $batal = 0;

        foreach ($daftar as $baris) {
            if (! is_array($baris) || ! is_string($baris['Tanggal'] ?? null) || ! is_string($baris['Nama'] ?? null)
                || preg_match('/^\d{4}-\d{2}-\d{2}$/', $baris['Tanggal']) !== 1) {
                throw new PelanggaranAturanBisnis('D-35', 'Baris hari libur di berkas data master tidak lengkap.');
            }

            $jenis = JenisHariLibur::tryFrom(is_string($baris['Jenis'] ?? null) ? $baris['Jenis'] : '') ?? JenisHariLibur::Nasional;
            $dibatalkan = ($baris['Dibatalkan'] ?? false) === true;
            $ada = HariLibur::query()
                ->whereDate('Tanggal', $baris['Tanggal'])
                ->where('Nama', $baris['Nama'])
                ->lockForUpdate()
                ->first();

            if ($ada === null) {
                HariLibur::query()->create([
                    'Tanggal' => $baris['Tanggal'],
                    'Nama' => $baris['Nama'],
                    'Jenis' => $jenis,
                    'NomorDasarHukum' => is_string($baris['NomorDasarHukum'] ?? null) ? $baris['NomorDasarHukum'] : null,
                    'Status' => $dibatalkan ? StatusDataMaster::Dibatalkan : StatusDataMaster::Terbit,
                    'DibatalkanPada' => $dibatalkan ? now() : null,
                ]);
                $baru++;

                continue;
            }

            if ($dibatalkan && $ada->Status === StatusDataMaster::Terbit) {
                $ada->update([
                    'Status' => StatusDataMaster::Dibatalkan,
                    'DibatalkanPada' => now(),
                    'AlasanPembatalan' => 'Dibatalkan di paket data master Payoung.',
                ]);
                $batal++;
            }
        }

        return ['HariLiburBaru' => $baru, 'HariLiburDibatalkan' => $batal];
    }

    /**
     * @return array{KodeJenisPajak: string, Tarif: string, PengaliDppPembilang: int, PengaliDppPenyebut: int, KodeWilayah: string|null, BiayaLayananMasukDpp: bool, BerlakuMulai: string, NomorDasarHukum: string|null, TautanDasarHukum: string|null}
     */
    private static function BacaTarif(mixed $baris): array
    {
        if (! is_array($baris) || ! is_string($baris['KodeJenisPajak'] ?? null) || ! is_string($baris['Tarif'] ?? null)
            || ! is_int($baris['PengaliDppPembilang'] ?? null) || ! is_int($baris['PengaliDppPenyebut'] ?? null)
            || ! is_string($baris['BerlakuMulai'] ?? null) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $baris['BerlakuMulai']) !== 1) {
            throw new PelanggaranAturanBisnis('D-35', 'Baris tarif pajak di berkas data master tidak lengkap.');
        }

        try {
            BigDecimal::of($baris['Tarif']);
        } catch (MathException) {
            throw new PelanggaranAturanBisnis('D-35', 'Tarif pajak di berkas data master bukan angka desimal.');
        }

        return [
            'KodeJenisPajak' => $baris['KodeJenisPajak'],
            'Tarif' => $baris['Tarif'],
            'PengaliDppPembilang' => $baris['PengaliDppPembilang'],
            'PengaliDppPenyebut' => $baris['PengaliDppPenyebut'],
            'KodeWilayah' => is_string($baris['KodeWilayah'] ?? null) ? $baris['KodeWilayah'] : null,
            'BiayaLayananMasukDpp' => ($baris['BiayaLayananMasukDpp'] ?? false) === true,
            'BerlakuMulai' => $baris['BerlakuMulai'],
            'NomorDasarHukum' => is_string($baris['NomorDasarHukum'] ?? null) ? $baris['NomorDasarHukum'] : null,
            'TautanDasarHukum' => is_string($baris['TautanDasarHukum'] ?? null) ? $baris['TautanDasarHukum'] : null,
        ];
    }

    /**
     * @param  array{Tarif: string, PengaliDppPembilang: int, PengaliDppPenyebut: int, BiayaLayananMasukDpp: bool}  $data
     */
    private static function CekNilaiSama(TarifPajak $tarif, array $data): bool
    {
        return BigDecimal::of($tarif->Tarif)->isEqualTo(BigDecimal::of($data['Tarif']))
            && $tarif->PengaliDppPembilang === $data['PengaliDppPembilang']
            && $tarif->PengaliDppPenyebut === $data['PengaliDppPenyebut']
            && $tarif->BiayaLayananMasukDpp === $data['BiayaLayananMasukDpp'];
    }
}
