<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Data\SaringLaporanKeuangan;
use App\Domain\Akuntansi\Kueri\ArusKas;
use App\Domain\Akuntansi\Kueri\BukuBesar;
use App\Domain\Akuntansi\Kueri\LabaRugi;
use App\Domain\Akuntansi\Kueri\Neraca;
use App\Domain\Akuntansi\Kueri\NeracaSaldo;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Laporan\ItemRingkasan;
use App\Domain\Bersama\Laporan\JenisKolom;
use App\Domain\Bersama\Laporan\KolomLaporan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Laporan keuangan F-13a (FIN-06, FIN-07) dari `JurnalDetail`, izin `laporan.keuangan.lihat`: buku besar per
 * akun, neraca saldo, laba rugi, neraca, dan arus kas, disaring periode (`dari`, `sampai`) & outlet (`outlet`), plus ekspor Excel/CSV/cetak (kop seragam, D-43) dengan
 * saringan yang sama. Pelaku berbatas outlet hanya menjumlah baris jurnal outlet aksesnya.
 */
final class LaporanKeuanganKontroler extends DasarAkuntansiKontroler
{
    public function BukuBesar(Request $permintaan, BukuBesar $kueri): Response|JsonResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $akun = $this->CariAkun($permintaan, $kueri);
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), [], '');
        $mutasi = fn (): array => $akun === null
            ? ['Data' => [], 'Meta' => ['Halaman' => 1, 'PerHalaman' => $tabel->perHalaman, 'Total' => 0, 'JumlahHalaman' => 1]]
            : $kueri->AmbilTabel($akun, $saring, $tabel);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Akuntansi/Laporan/BukuBesar', 'Mutasi', $mutasi, fn (): array => [
            'Saring' => [...self::PetakanSaring($saring, $permintaan), 'Akun' => $akun->Uuid ?? ''],
            'Akun' => $akun === null ? null : ['Uuid' => $akun->Uuid, 'Kode' => $akun->Kode, 'Nama' => $akun->Nama, 'SaldoNormal' => $akun->SaldoNormal->value],
            'OpsiAkun' => $kueri->AmbilOpsiAkun(),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporBukuBesar(Request $permintaan, BukuBesar $kueri): SymfonyResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $akun = $this->CariAkun($permintaan, $kueri);
        abort_if($akun === null, 404);

        return $this->SajikanKeuangan(
            $permintaan,
            $saring,
            'Buku Besar',
            'buku-besar-'.$akun->Kode.'-'.$saring->dari.'-'.$saring->sampai,
            [
                new KolomLaporan('Tanggal', JenisKolom::Tanggal), new KolomLaporan('Nomor jurnal', JenisKolom::Teks, 20), new KolomLaporan('Keterangan', JenisKolom::Teks, 38),
                new KolomLaporan('Sumber', JenisKolom::Teks, 18), new KolomLaporan('Nomor sumber', JenisKolom::Teks, 20), new KolomLaporan('Outlet', JenisKolom::Teks, 22),
                new KolomLaporan('Debit', JenisKolom::Uang, jumlahkan: true), new KolomLaporan('Kredit', JenisKolom::Uang, jumlahkan: true), new KolomLaporan('Saldo', JenisKolom::Uang),
            ],
            // Closure: dibaca dua kali (ringkasan otomatis lalu penulisan), jadi tiap panggilan membuat generator baru.
            function () use ($kueri, $akun, $saring): iterable {
                foreach ($kueri->AmbilSemua($akun, $saring) as $b) {
                    yield [
                        (string) $b['Tanggal'], (string) $b['NomorJurnal'], (string) ($b['Memo'] ?? $b['Keterangan']), (string) $b['LabelSumber'],
                        is_string($b['NomorSumber']) ? $b['NomorSumber'] : '', is_string($b['NamaOutlet']) ? $b['NamaOutlet'] : '',
                        (string) $b['Debit'], (string) $b['Kredit'], (string) $b['Saldo'],
                    ];
                }
            },
            [['Akun', "{$akun->Kode} {$akun->Nama}"]],
        );
    }

    public function NeracaSaldo(Request $permintaan, NeracaSaldo $kueri): Response
    {
        $saring = $this->AmbilSaringLaporan($permintaan);

        return Inertia::render('Kelola/Akuntansi/Laporan/NeracaSaldo', [
            'Saring' => self::PetakanSaring($saring, $permintaan),
            'Laporan' => $kueri->Ambil($saring),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporNeracaSaldo(Request $permintaan, NeracaSaldo $kueri): SymfonyResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $laporan = $kueri->Ambil($saring);
        $kolom = ['SaldoAwalDebit', 'SaldoAwalKredit', 'Debit', 'Kredit', 'SaldoAkhirDebit', 'SaldoAkhirKredit'];
        $baris = array_map(fn (array $b): array => [(string) $b['Kode'], (string) $b['Nama'], (string) $b['LabelJenis'], ...array_map(fn (string $k): string => (string) $b[$k], $kolom)], $laporan['Baris']);
        $baris[] = ['', 'Total', '', ...array_map(fn (string $k): string => $laporan['Total'][$k], $kolom)];
        $uang = static fn (string $judul): KolomLaporan => new KolomLaporan($judul, JenisKolom::Uang);

        return $this->SajikanKeuangan(
            $permintaan,
            $saring,
            'Neraca Saldo',
            'neraca-saldo-'.$saring->dari.'-'.$saring->sampai,
            [new KolomLaporan('Kode', JenisKolom::Teks, 12), new KolomLaporan('Nama akun', JenisKolom::Teks, 38), new KolomLaporan('Tipe', JenisKolom::Teks, 16), $uang('Saldo awal debit'), $uang('Saldo awal kredit'), $uang('Debit'), $uang('Kredit'), $uang('Saldo akhir debit'), $uang('Saldo akhir kredit')],
            $baris,
            [],
            [
                new ItemRingkasan('Total Debit', $laporan['Total']['Debit'], JenisKolom::Uang),
                new ItemRingkasan('Total Kredit', $laporan['Total']['Kredit'], JenisKolom::Uang),
                new ItemRingkasan('Status', $laporan['Seimbang'] ? 'Seimbang' : 'Tidak seimbang', JenisKolom::Teks),
            ],
        );
    }

    public function LabaRugi(Request $permintaan, LabaRugi $kueri): Response
    {
        $saring = $this->AmbilSaringLaporan($permintaan);

        return Inertia::render('Kelola/Akuntansi/Laporan/LabaRugi', [
            'Saring' => self::PetakanSaring($saring, $permintaan),
            'Laporan' => $kueri->Ambil($saring),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporLabaRugi(Request $permintaan, LabaRugi $kueri): SymfonyResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $laporan = $kueri->Ambil($saring);
        $periode = $laporan['Periode'];

        return $this->SajikanKeuangan(
            $permintaan,
            $saring,
            'Laba Rugi',
            'laba-rugi-'.$saring->dari.'-'.$saring->sampai,
            [new KolomLaporan('Kode', JenisKolom::Teks, 12), new KolomLaporan('Keterangan', JenisKolom::Teks, 44), new KolomLaporan("{$periode['Dari']} s.d. {$periode['Sampai']}", JenisKolom::Uang, 24), new KolomLaporan("{$periode['DariSebelumnya']} s.d. {$periode['SampaiSebelumnya']}", JenisKolom::Uang, 24)],
            array_map(fn (array $b): array => [
                is_string($b['Kode']) ? $b['Kode'] : '',
                (string) $b['Label'],
                is_string($b['Nilai']) ? $b['Nilai'] : '',
                is_string($b['NilaiSebelumnya']) ? $b['NilaiSebelumnya'] : '',
            ], $laporan['Baris']),
            [['Pembanding', "{$periode['DariSebelumnya']} s.d. {$periode['SampaiSebelumnya']}"]],
            $this->RingkasanLaporan($laporan['Ringkasan'], 'Nilai'),
        );
    }

    public function Neraca(Request $permintaan, Neraca $kueri): Response
    {
        $saring = $this->AmbilSaringLaporan($permintaan);

        return Inertia::render('Kelola/Akuntansi/Laporan/Neraca', [
            'Saring' => self::PetakanSaring($saring, $permintaan),
            'Laporan' => $kueri->Ambil($saring),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporNeraca(Request $permintaan, Neraca $kueri): SymfonyResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $laporan = $kueri->Ambil($saring);
        $posisi = $laporan['Posisi'];

        return $this->SajikanKeuangan(
            $permintaan,
            $saring,
            'Neraca',
            'neraca-'.$posisi['Akhir'],
            [new KolomLaporan('Kode', JenisKolom::Teks, 12), new KolomLaporan('Keterangan', JenisKolom::Teks, 44), new KolomLaporan("Posisi {$posisi['Akhir']}", JenisKolom::Uang, 24), new KolomLaporan("Posisi {$posisi['Awal']}", JenisKolom::Uang, 24)],
            array_map(fn (array $b): array => [
                is_string($b['Kode']) ? $b['Kode'] : '',
                (string) $b['Label'],
                is_string($b['Nilai']) ? $b['Nilai'] : '',
                is_string($b['NilaiAwal']) ? $b['NilaiAwal'] : '',
            ], $laporan['Baris']),
            [['Posisi Per', $posisi['Akhir']], ['Pembanding', $posisi['Awal']]],
            [...$this->RingkasanLaporan($laporan['Ringkasan'], 'Nilai'), new ItemRingkasan('Status', $laporan['Seimbang'] ? 'Seimbang' : 'Tidak seimbang', JenisKolom::Teks)],
        );
    }

    public function ArusKas(Request $permintaan, ArusKas $kueri): Response
    {
        $saring = $this->AmbilSaringLaporan($permintaan);

        return Inertia::render('Kelola/Akuntansi/Laporan/ArusKas', [
            'Saring' => self::PetakanSaring($saring, $permintaan),
            'Laporan' => $kueri->Ambil($saring),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
        ]);
    }

    public function EksporArusKas(Request $permintaan, ArusKas $kueri): SymfonyResponse
    {
        $saring = $this->AmbilSaringLaporan($permintaan);
        $laporan = $kueri->Ambil($saring);

        return $this->SajikanKeuangan(
            $permintaan,
            $saring,
            'Arus Kas',
            'arus-kas-'.$saring->dari.'-'.$saring->sampai,
            [new KolomLaporan('Aktivitas', JenisKolom::Teks, 22), new KolomLaporan('Kode', JenisKolom::Teks, 12), new KolomLaporan('Keterangan', JenisKolom::Teks, 44), new KolomLaporan("{$saring->dari} s.d. {$saring->sampai}", JenisKolom::Uang, 24)],
            array_map(fn (array $b): array => [
                (string) $b['Aktivitas'],
                is_string($b['Kode']) ? $b['Kode'] : '',
                (string) $b['Label'],
                is_string($b['Nilai']) ? $b['Nilai'] : '',
            ], $laporan['Baris']),
            [],
            $this->RingkasanLaporan($laporan['Ringkasan'], null),
        );
    }

    /**
     * Kop laporan keuangan seragam (D-43): periode dan outlet dari saringan, lalu Excel/CSV/cetak sesuai `?format=`.
     *
     * @param  list<KolomLaporan>  $kolom
     * @param  iterable<list<string|int|null>>|Closure(): iterable<list<string|int|null>>  $baris
     * @param  list<array{0: string, 1: string}>  $saringanTambahan
     * @param  list<ItemRingkasan>|null  $ringkasan
     */
    private function SajikanKeuangan(Request $permintaan, SaringLaporanKeuangan $saring, string $judul, string $namaBerkas, array $kolom, iterable|Closure $baris, array $saringanTambahan = [], ?array $ringkasan = null): SymfonyResponse
    {
        $namaOutlet = '';

        foreach ($this->AmbilOpsiOutlet() as $o) {
            if ($saring->idOutlet !== null && $o['Uuid'] === $permintaan->query('outlet')) {
                $namaOutlet = $o['Nama'];
            }
        }

        $dari = CarbonImmutable::parse($saring->dari);
        $sampai = CarbonImmutable::parse($saring->sampai);

        return $this->SajikanLaporan(
            $permintaan,
            "Laporan Keuangan | {$judul}",
            $namaBerkas,
            $kolom,
            $baris,
            [['Periode', $this->LabelPeriode($dari, $sampai)], ...$saringanTambahan],
            $ringkasan,
            $this->LabelCakupan($namaOutlet),
        );
    }

    /**
     * Blok ringkasan dari `Ringkasan` kueri laporan: kunci PascalCase jadi label ("LabaBersih" → "Laba Bersih").
     *
     * @param  array<string, array<string, string>|string>  $ringkasan
     * @return list<ItemRingkasan>
     */
    private function RingkasanLaporan(array $ringkasan, ?string $kunciNilai): array
    {
        $hasil = [];

        foreach ($ringkasan as $kunci => $nilai) {
            $angka = is_array($nilai) ? ($nilai[$kunciNilai ?? 'Nilai'] ?? null) : $nilai;

            if (is_string($angka)) {
                $hasil[] = new ItemRingkasan(trim((string) preg_replace('/(?<!^)(?=[A-Z])/', ' ', (string) $kunci)), $angka, JenisKolom::Uang);
            }
        }

        return $hasil;
    }

    private function CariAkun(Request $permintaan, BukuBesar $kueri): ?Akun
    {
        $uuid = $permintaan->query('akun');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $akun = $kueri->CariAkun($uuid);
        abort_if($akun === null, 404);

        return $akun;
    }
}
