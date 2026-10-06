<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pembelian\Enum\KelompokUmurHutang;
use App\Domain\Pembelian\Enum\StatusFakturPembelian;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PembayaranHutang;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Pembelian\Model\ReturPembelian;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Daftar dokumen pembelian untuk `TabelData` (F-04 fase 1): PO, penerimaan, faktur, hutang (faktur terbuka + umur),
 * pembayaran, dan retur. Semua dibatasi outlet yang boleh diakses pelaku (`idOutletBoleh` null = semua; dokumen tanpa
 * outlet hanya untuk pelaku semua outlet). Cari: nomor, nama pemasok (dan nomor faktur pemasok/surat jalan). Saring:
 * `Status` (pilihan banyak), `Pemasok` (Uuid), `Tanggal` (rentang).
 */
final class DaftarDokumenPembelian
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'Total', 'JatuhTempo'];

    public const KOLOM_SARING = ['Status', 'Pemasok', 'Tanggal', 'Umur'];

    public const URUT_BAWAAN = '-Tanggal';

    public const URUT_BAWAAN_HUTANG = 'JatuhTempo';

    public function __construct(private readonly PetaNamaPembelian $peta) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Pesanan(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $kueri = $this->Saring(PesananPembelian::query(), $p, $idOutletBoleh, array_map(fn (StatusPesananPembelian $s): string => $s->value, StatusPesananPembelian::cases()));

        return PenerapKueriTabel::Terapkan($kueri, $p, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'Total' => 'Total'], function (Collection $baris): array {
            $pemasok = $this->peta->Pemasok($baris->pluck('IdPemasok')->all());
            $gudang = $this->peta->Gudang($baris->pluck('IdGudang')->all());

            return array_values($baris->map(fn (PesananPembelian $d): array => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Tanggal' => $d->Tanggal->format('Y-m-d'),
                'PerkiraanTiba' => $d->PerkiraanTiba?->format('Y-m-d'),
                'NamaPemasok' => $pemasok[$d->IdPemasok]['Nama'] ?? '',
                'NamaGudang' => $gudang[$d->IdGudang]->nama ?? '',
                'NamaOutlet' => $gudang[$d->IdGudang]->namaOutlet ?? null,
                'Status' => $d->Status->value,
                'LabelStatus' => $d->Status->AmbilLabel(),
                'Total' => $d->Total,
            ])->all());
        });
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Penerimaan(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $kueri = $this->Saring(PenerimaanBarang::query(), $p, $idOutletBoleh, array_map(fn (StatusDokumenTerposting $s): string => $s->value, StatusDokumenTerposting::cases()), 'NomorSuratJalan');

        return PenerapKueriTabel::Terapkan($kueri, $p, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'Total' => 'TotalNilai'], function (Collection $baris): array {
            $pemasok = $this->peta->Pemasok($baris->pluck('IdPemasok')->all());
            $gudang = $this->peta->Gudang($baris->pluck('IdGudang')->all());
            $po = PesananPembelian::query()->whereIn('Id', $baris->pluck('IdPesananPembelian')->filter()->all())->pluck('Nomor', 'Id');

            return array_values($baris->map(fn (PenerimaanBarang $d): array => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Tanggal' => $d->Tanggal->format('Y-m-d'),
                'NomorPesanan' => $d->IdPesananPembelian === null ? null : $po->get($d->IdPesananPembelian),
                'NamaPemasok' => $d->IdPemasok === null ? null : ($pemasok[$d->IdPemasok]['Nama'] ?? null),
                'NamaGudang' => $gudang[$d->IdGudang]->nama ?? '',
                'NamaOutlet' => $gudang[$d->IdGudang]->namaOutlet ?? null,
                'Status' => $d->Status->value,
                'LabelStatus' => $d->Status->AmbilLabel(),
                'BelanjaStok' => $d->BelanjaStok,
                'Difakturkan' => $d->IdFakturPembelian !== null,
                'Total' => $d->TotalNilai,
            ])->all());
        });
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Faktur(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $kueri = $this->Saring(FakturPembelian::query(), $p, $idOutletBoleh, array_map(fn (StatusFakturPembelian $s): string => $s->value, StatusFakturPembelian::cases()), 'NomorFakturPemasok');

        return PenerapKueriTabel::Terapkan($kueri, $p, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'Total' => 'Total', 'JatuhTempo' => 'JatuhTempo'], fn (Collection $baris): array => $this->PetakanFaktur($baris));
    }

    /**
     * Daftar hutang: faktur terbuka (BelumDibayar/DibayarSebagian) urut jatuh tempo, dengan sisa & umur per tanggal
     * `hariIni`; saring `Umur` (kelompok umur, pilihan banyak). `Ringkasan` = Σ sisa per kelompok umur (dari saringan
     * pemasok & outlet yang sama).
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}, Ringkasan: array{Total: string, Kelompok: list<array{Kunci: string, Label: string, Sisa: string, Jumlah: int}>}}
     */
    public function Hutang(DataPermintaanTabel $p, ?array $idOutletBoleh, CarbonImmutable $hariIni): array
    {
        $terbuka = [StatusFakturPembelian::BelumDibayar->value, StatusFakturPembelian::DibayarSebagian->value];
        $dasar = fn (): Builder => $this->Saring(FakturPembelian::query(), $p, $idOutletBoleh, $terbuka, 'NomorFakturPemasok', true)->whereIn('Status', $terbuka);
        $kueri = $dasar();
        $umur = $p->AmbilDaftar('Umur', array_map(fn (KelompokUmurHutang $k): string => $k->value, KelompokUmurHutang::cases()));
        $hari = $hariIni->toDateString();

        if ($umur !== []) {
            $kueri->where(function (Builder $dalam) use ($umur, $hari): void {
                foreach ($umur as $k) {
                    [$min, $maks] = match (KelompokUmurHutang::from($k)) {
                        KelompokUmurHutang::BelumJatuhTempo => [null, -1],
                        KelompokUmurHutang::Hari0Sampai30 => [0, 30],
                        KelompokUmurHutang::Hari31Sampai60 => [31, 60],
                        KelompokUmurHutang::Hari61Sampai90 => [61, 90],
                        KelompokUmurHutang::LebihDari90 => [91, null],
                    };
                    $dalam->orWhere(fn (Builder $satu) => $satu
                        ->when($min !== null, fn ($q) => $q->whereRaw('DATEDIFF(?, `JatuhTempo`) >= ?', [$hari, $min]))
                        ->when($maks !== null, fn ($q) => $q->whereRaw('DATEDIFF(?, `JatuhTempo`) <= ?', [$hari, $maks])));
                }
            });
        }

        $tabel = PenerapKueriTabel::Terapkan($kueri, $p, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'Total' => 'Total', 'JatuhTempo' => 'JatuhTempo'], fn (Collection $baris): array => $this->PetakanFaktur($baris, $hariIni));
        $kelompok = [];
        $total = Uang::Nol();

        foreach (KelompokUmurHutang::cases() as $k) {
            $kelompok[$k->value] = ['Kunci' => $k->value, 'Label' => $k->AmbilLabel(), 'Sisa' => Uang::Nol(), 'Jumlah' => 0];
        }

        foreach ($dasar()->get(['JatuhTempo', 'Total', 'JumlahDibayar', 'JumlahRetur']) as $f) {
            $k = KelompokUmurHutang::DariHariLewat((int) $f->JatuhTempo->diffInDays($hariIni, false))->value;
            $kelompok[$k]['Sisa'] = $kelompok[$k]['Sisa']->Tambah($f->AmbilSisa());
            $kelompok[$k]['Jumlah']++;
            $total = $total->Tambah($f->AmbilSisa());
        }

        return [...$tabel, 'Ringkasan' => [
            'Total' => $total->KeString(),
            'Kelompok' => array_values(array_map(fn (array $k): array => [...$k, 'Sisa' => $k['Sisa']->KeString()], $kelompok)),
        ]];
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Pembayaran(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $kueri = $this->Saring(PembayaranHutang::query(), $p, $idOutletBoleh, array_map(fn (StatusDokumenTerposting $s): string => $s->value, StatusDokumenTerposting::cases()));

        return PenerapKueriTabel::Terapkan($kueri, $p, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'Total' => 'Jumlah'], function (Collection $baris): array {
            $pemasok = $this->peta->Pemasok($baris->pluck('IdPemasok')->all());

            return array_values($baris->map(fn (PembayaranHutang $d): array => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Tanggal' => $d->Tanggal->format('Y-m-d'),
                'NamaPemasok' => $d->IdPemasok === null ? null : ($pemasok[$d->IdPemasok]['Nama'] ?? null),
                'Status' => $d->Status->value,
                'LabelStatus' => $d->Status->AmbilLabel(),
                'BelanjaStok' => $d->BelanjaStok,
                'Kompensasi' => $d->Kompensasi,
                'Total' => $d->Jumlah,
            ])->all());
        });
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Retur(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $kueri = $this->Saring(ReturPembelian::query(), $p, $idOutletBoleh, array_map(fn (StatusDokumenTerposting $s): string => $s->value, StatusDokumenTerposting::cases()), 'Alasan');

        return PenerapKueriTabel::Terapkan($kueri, $p, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'Total' => 'NilaiHutang'], function (Collection $baris): array {
            $pemasok = $this->peta->Pemasok($baris->pluck('IdPemasok')->all());
            $grn = PenerimaanBarang::query()->whereIn('Id', $baris->pluck('IdPenerimaanBarang')->all())->pluck('Nomor', 'Id');

            return array_values($baris->map(fn (ReturPembelian $d): array => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Tanggal' => $d->Tanggal->format('Y-m-d'),
                'NomorPenerimaan' => $grn->get($d->IdPenerimaanBarang),
                'NamaPemasok' => $d->IdPemasok === null ? null : ($pemasok[$d->IdPemasok]['Nama'] ?? null),
                'Alasan' => $d->Alasan,
                'Status' => $d->Status->value,
                'LabelStatus' => $d->Status->AmbilLabel(),
                'Total' => Uang::Dari($d->NilaiHutang)->Tambah(Uang::Dari($d->Pajak))->KeString(),
            ])->all());
        });
    }

    /**
     * @param  Collection<int, FakturPembelian>  $baris
     * @return list<array<string, mixed>>
     */
    private function PetakanFaktur(Collection $baris, ?CarbonImmutable $hariIni = null): array
    {
        $pemasok = $this->peta->Pemasok($baris->pluck('IdPemasok')->all());

        return array_values($baris->map(function (FakturPembelian $d) use ($pemasok, $hariIni): array {
            $lewat = $hariIni === null ? null : (int) $d->JatuhTempo->diffInDays($hariIni, false);

            return [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'NomorFakturPemasok' => $d->NomorFakturPemasok,
                'Tanggal' => $d->Tanggal->format('Y-m-d'),
                'JatuhTempo' => $d->JatuhTempo->format('Y-m-d'),
                'NamaPemasok' => $d->IdPemasok === null ? null : ($pemasok[$d->IdPemasok]['Nama'] ?? null),
                'UuidPemasok' => $d->IdPemasok === null ? null : ($pemasok[$d->IdPemasok]['Uuid'] ?? null),
                'Status' => $d->Status->value,
                'LabelStatus' => $d->Status->AmbilLabel(),
                'BelanjaStok' => $d->BelanjaStok,
                'Total' => $d->Total,
                'Sisa' => $d->Status === StatusFakturPembelian::Dibatalkan ? '0.00' : $d->AmbilSisa()->KeString(),
                'HariLewat' => $lewat,
                'Umur' => $lewat === null ? null : KelompokUmurHutang::DariHariLewat($lewat)->value,
                'LabelUmur' => $lewat === null ? null : KelompokUmurHutang::DariHariLewat($lewat)->AmbilLabel(),
            ];
        })->all());
    }

    /**
     * Saringan bersama: outlet boleh, status, pemasok, rentang tanggal, dan cari (nomor, kolom teks tambahan, nama pemasok).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $kueri
     * @param  list<int>|null  $idOutletBoleh
     * @param  list<string>  $statusBoleh
     * @return Builder<TModel>
     */
    private function Saring(Builder $kueri, DataPermintaanTabel $p, ?array $idOutletBoleh, array $statusBoleh, ?string $kolomCari = null, bool $abaikanStatus = false): Builder
    {
        $status = $abaikanStatus ? [] : $p->AmbilDaftar('Status', $statusBoleh);
        $uuidPemasok = $p->saring['Pemasok'] ?? null;
        $idPemasok = $uuidPemasok === null ? null : (Pemasok::query()->withTrashed()->where('Uuid', $uuidPemasok)->value('Id') ?? 0);
        ['Dari' => $dari, 'Sampai' => $sampai] = $p->AmbilRentangTanggal('Tanggal');
        $pola = PenerapKueriTabel::PolaCari($p->cari);

        return $kueri
            ->when($idOutletBoleh !== null, fn ($q) => $q->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($status !== [], fn ($q) => $q->whereIn('Status', $status))
            ->when($idPemasok !== null, fn ($q) => $q->where('IdPemasok', $idPemasok))
            ->when($dari !== null, fn ($q) => $q->where('Tanggal', '>=', (string) $dari))
            ->when($sampai !== null, fn ($q) => $q->where('Tanggal', '<=', (string) $sampai))
            ->when($p->cari !== '', fn ($q) => $q->where(fn ($dalam) => $dalam
                ->where('Nomor', 'like', $pola)
                ->when($kolomCari !== null, fn ($atau) => $atau->orWhere((string) $kolomCari, 'like', $pola))
                ->orWhereIn('IdPemasok', Pemasok::query()->withTrashed()->where('Nama', 'like', $pola)->select('Id'))));
    }
}
