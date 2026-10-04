<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Enum\StatusRekapGaji;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\RekapGaji;
use App\Domain\Karyawan\Model\RekapGajiBaris;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rekap gaji (F-18 bagian 3): daftar periode (`TabelData` mode server, saring status) dan rincian satu rekap (baris per
 * karyawan + sisa kasbon aktif untuk batas potongan).
 */
final class DaftarRekapGaji
{
    public const KOLOM_URUT = ['Periode', 'TotalBersih'];

    public const KOLOM_SARING = ['Status'];

    public const URUT_BAWAAN = '-Periode';

    public function __construct(
        private readonly DaftarKasbon $kasbon,
        private readonly DaftarAkunPilihan $akun,
        private readonly JurnalSumber $jurnal,
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
    ) {}

    /**
     * Periode yang bisa dibuatkan rekap: 12 bulan terakhir sampai bulan berjalan (zona waktu tenant) yang belum punya
     * rekap, terbaru dulu.
     *
     * @return list<array{Nilai: string, Label: string}>
     */
    public function AmbilOpsiPeriode(): array
    {
        $bulan = CarbonImmutable::now($this->profil->Ambil($this->konteks->Wajib())['ZonaWaktu'])->startOfMonth();
        $ada = RekapGaji::query()->pluck('Periode')->all();
        $hasil = [];

        for ($i = 0; $i < 12; $i++) {
            $periode = $bulan->subMonthsNoOverflow($i)->format('Y-m');

            if (! in_array($periode, $ada, true)) {
                $hasil[] = ['Nilai' => $periode, 'Label' => PenjagaKunciPeriode::FormatPeriode($periode)];
            }
        }

        return $hasil;
    }

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Ambil(DataPermintaanTabel $p): array
    {
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusRekapGaji $s): string => $s->value, StatusRekapGaji::cases()));
        $kueri = RekapGaji::query()
            ->when($status !== [], fn (Builder $k) => $k->whereIn('Status', $status))
            ->when($p->cari !== '', fn (Builder $k) => $k->where('Periode', 'like', PenerapKueriTabel::PolaCari($p->cari)));

        return PenerapKueriTabel::Terapkan($kueri, $p, ['Periode' => 'Periode', 'TotalBersih' => 'TotalBersih'], fn (Collection $baris): array => array_values($baris->map(
            fn (RekapGaji $r): array => $this->PetakanRingkas($r),
        )->all()));
    }

    /**
     * @return array{Rekap: array<string, mixed>, Baris: list<array<string, mixed>>}
     */
    public function AmbilDetail(RekapGaji $rekap): array
    {
        $baris = RekapGajiBaris::query()->where('IdRekapGaji', $rekap->Id)->get();
        $karyawan = Karyawan::query()->whereKey($baris->pluck('IdKaryawan')->all())->get(['Id', 'Uuid', 'Nama', 'Jabatan'])->keyBy('Id');
        $sisa = [];

        foreach ($this->kasbon->AmbilAktifPerKaryawan(array_values(array_map('intval', $baris->pluck('IdKaryawan')->all()))) as $id => $daftar) {
            $sisa[$id] = array_reduce($daftar, fn (Uang $t, $k): Uang => $t->Tambah(Uang::Dari($k->Sisa)), Uang::Nol())->KeString();
        }

        $akun = $this->akun->AmbilBanyak(array_values(array_filter([$rekap->IdAkunKasBank, $rekap->IdAkunBeban])));
        $jurnal = $rekap->Status === StatusRekapGaji::Dibayar ? ($this->jurnal->Ambil(JenisSumberJurnal::RekapGaji, $rekap->Id)[0] ?? null) : null;
        $namaAkun = fn (?int $id): ?string => $id !== null && isset($akun[$id]) ? $akun[$id]['Kode'].' '.$akun[$id]['Nama'] : null;

        $hasil = $baris->map(fn (RekapGajiBaris $b): array => [
            'UuidKaryawan' => $karyawan->get($b->IdKaryawan)->Uuid ?? '',
            'Nama' => $karyawan->get($b->IdKaryawan)->Nama ?? '',
            'Jabatan' => $karyawan->get($b->IdKaryawan)->Jabatan ?? null,
            'GajiPokok' => $b->GajiPokok,
            'Komisi' => $b->Komisi,
            'Tambahan' => $b->Tambahan,
            'LemburMenit' => $b->LemburMenit,
            'Lembur' => $b->Lembur,
            'TerlambatMenit' => $b->TerlambatMenit,
            'PotonganTerlambat' => $b->PotonganTerlambat,
            'HariTidakMasuk' => $b->HariTidakMasuk,
            'PotonganTidakMasuk' => $b->PotonganTidakMasuk,
            'Kotor' => $b->HitungKotor()->KeString(),
            'PotonganKasbon' => $b->PotonganKasbon,
            'PotonganLain' => $b->PotonganLain,
            'Bersih' => $b->Bersih,
            'Catatan' => $b->Catatan,
            // Sisa kasbon aktif saat ini (batas potongan kasbon selama draf).
            'SisaKasbon' => $sisa[$b->IdKaryawan] ?? '0.00',
        ])->sortBy('Nama', SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'Rekap' => $this->PetakanRingkas($rekap) + [
                'AkunKasBank' => $namaAkun($rekap->IdAkunKasBank),
                'AkunBeban' => $namaAkun($rekap->IdAkunBeban),
                'Jurnal' => $jurnal === null ? null : ['Uuid' => $jurnal['Uuid'], 'Nomor' => $jurnal['Nomor']],
            ],
            'Baris' => array_values($hasil->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function PetakanRingkas(RekapGaji $r): array
    {
        return [
            'Uuid' => $r->Uuid,
            'Periode' => $r->Periode,
            'LabelPeriode' => PenjagaKunciPeriode::FormatPeriode($r->Periode),
            'Status' => $r->Status->value,
            'LabelStatus' => $r->Status->AmbilLabel(),
            'TotalKotor' => $r->TotalKotor,
            'TotalPotongan' => $r->TotalPotongan,
            'TotalBersih' => $r->TotalBersih,
            'TanggalBayar' => $r->TanggalBayar?->toDateString(),
        ];
    }
}
