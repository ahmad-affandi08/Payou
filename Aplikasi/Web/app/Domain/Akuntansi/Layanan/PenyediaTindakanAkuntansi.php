<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Enum\ArahGiro;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Akuntansi\Model\Giro;
use App\Domain\Akuntansi\Model\JadwalKasBank;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Penjualan\Kueri\PembayaranBelumDicairkan;

/**
 * Kotak Tindakan domain Akuntansi (D-23 C, izin `akuntansi.kelola`): transaksi rutin yang gagal dicatat otomatis
 * (D-23 D), uang non-tunai yang belum cair dari platform (F-08 BR-08.4), giro yang sudah jatuh tempo (v3.42), dan — mulai tanggal [TANGGAL_PENGINGAT] setiap
 * bulan — bulan lalu yang sudah ada jurnalnya tetapi belum ditutup buku (F-15). Semuanya butir pengingat yang selesai
 * sendiri saat keadaannya berubah, jadi tidak ada yang perlu ditandai "sudah dicek".
 *
 * Pembayaran yang belum dicairkan dibaca lewat kueri publik domain Penjualan (aturan #14), bukan dengan membaca tabel
 * penjualan dari sini.
 */
final class PenyediaTindakanAkuntansi implements PenyediaTindakan
{
    public const TANGGAL_PENGINGAT = 10;

    private const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function __construct(private readonly PembayaranBelumDicairkan $belumDicairkan) {}

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('akuntansi.kelola')) {
            return [];
        }

        // D-23 D: transaksi kas & bank berulang yang ditolak saat dicatat otomatis (misal periode terkunci).
        $gagal = JadwalKasBank::query()->where('Aktif', true)->whereNotNull('GalatTerakhir')->orderBy('Id')->get();
        $butir = [new DataButirTindakan(
            'kas-bank.berulang-gagal',
            'Keuangan',
            TingkatTindakan::Penting,
            'Transaksi rutin gagal dicatat otomatis',
            'Perbaiki penyebabnya (misal buka kunci periode atau aktifkan akun), atau hentikan jadwalnya.',
            $gagal->count(),
            '/kelola/akuntansi/kas-bank/berulang',
            'Lihat jadwal',
            array_values($gagal->take(DataButirTindakan::BATAS_RINCIAN)->map(fn (JadwalKasBank $j): DataRincianTindakan => new DataRincianTindakan(
                $j->Uuid,
                $j->Keterangan,
                (string) $j->GalatTerakhir,
                $j->TanggalBerikutnya->toDateString(),
                null,
            ))->all()),
        )];

        // F-08 BR-08.4: uang non-tunai yang lewat batas wajar menunggu pencairan. Ini satu-satunya tempat yang bisa
        // memberi tahu toko bahwa platform belum menyetor — akun kliring sendiri tidak berteriak.
        $menunggu = $this->belumDicairkan->MenungguTerlaluLama($konteks->idOutletBoleh, $konteks->hariIni);

        if ($menunggu !== []) {
            $butir[] = new DataButirTindakan(
                'pencairan.belum-cair',
                'Keuangan',
                TingkatTindakan::Penting,
                'Uang belum cair dari platform',
                'Nilainya masih tercatat di akun kliring, belum di rekening. Cocokkan dengan mutasi rekening, atau tanyakan ke platformnya.',
                count($menunggu),
                '/kelola/akuntansi/pencairan',
                'Cairkan',
                array_values(array_map(fn (array $m): DataRincianTindakan => new DataRincianTindakan(
                    $m['Uuid'],
                    $m['Nama'],
                    "{$m['Jumlah']} pembayaran, tertua {$m['UmurHari']} hari (wajar sampai {$m['BatasHari']} hari)",
                    $m['TanggalTerlama'],
                    '/kelola/akuntansi/pencairan/buat?metode='.$m['Uuid'],
                ), array_slice($menunggu, 0, DataButirTindakan::BATAS_RINCIAN))),
            );
        }

        // v3.42 (F-12): giro/cek mundur yang sudah sampai tanggal efektif tetapi belum dicatat cair atau ditolak.
        $giro = Giro::query()->where('Status', StatusGiro::Menunggu->value)->where('TanggalJatuhTempo', '<=', $konteks->hariIni->toDateString())
            ->orderBy('TanggalJatuhTempo')->orderBy('Id')->get();

        if ($giro->isNotEmpty()) {
            $butir[] = new DataButirTindakan(
                'giro.jatuh-tempo',
                'Keuangan',
                TingkatTindakan::Penting,
                'Giro sudah jatuh tempo',
                'Setor/kliring gironya ke bank lalu catat cair, atau catat ditolak bila bank menolaknya.',
                $giro->count(),
                '/kelola/akuntansi/giro?saring[Status]=Menunggu',
                'Lihat giro',
                array_values($giro->take(DataButirTindakan::BATAS_RINCIAN)->map(fn (Giro $g): DataRincianTindakan => new DataRincianTindakan(
                    $g->Uuid,
                    "{$g->NomorGiro} {$g->NamaBank}",
                    ($g->Arah === ArahGiro::Masuk ? 'Dari ' : 'Ke ').$g->NamaPihak.' | '.Uang::Dari($g->Jumlah)->FormatRupiah(),
                    $g->TanggalJatuhTempo->toDateString(),
                    '/kelola/akuntansi/giro?cari='.$g->Uuid,
                ))->all()),
            );
        }

        if ($konteks->hariIni->day < self::TANGGAL_PENGINGAT) {
            return $butir;
        }

        $bulanLalu = $konteks->hariIni->startOfMonth()->subMonth();
        $periode = $bulanLalu->format('Y-m');
        $terkunci = KunciPeriode::query()->where('Periode', $periode)->whereNotNull('DikunciPada')->exists();
        $adaJurnal = ! $terkunci && Jurnal::query()->where('Periode', $periode)->exists();

        return [...$butir, new DataButirTindakan(
            'periode.belum-ditutup',
            'Keuangan',
            TingkatTindakan::Perhatian,
            'Tutup buku '.self::BULAN[$bulanLalu->month - 1].' '.$bulanLalu->year,
            'Bulan lalu belum ditutup buku. Setelah dicek, tutup agar laporan keuangan tidak berubah lagi.',
            $adaJurnal ? 1 : 0,
            '/kelola/akuntansi/tutup-buku',
            'Tutup buku',
        )];
    }

    public function AmbilJenisDokumen(): array
    {
        return [];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return [];
    }
}
