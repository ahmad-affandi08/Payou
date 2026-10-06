<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Pencairan;
use App\Domain\Penjualan\Model\PencairanDetail;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Pembayaran non-tunai yang **belum dicairkan** (F-08, BR-08.4): isi akun kliring yang masih menunggu uang masuk
 * rekening. Ini juga jawaban atas pertanyaan yang sebelumnya tidak bisa dijawab siapa pun — "uang mana yang belum
 * sampai?".
 *
 * Yang tidak ikut: pembayaran dari penjualan yang di-void (J-07.1-nya sudah dibalik, tidak ada sisa di akun kliring)
 * dan pembayaran yang sudah diklaim pencairan lain (`PencairanDetail.IdPembayaranAktif`). Penjualan yang **diretur**
 * tetap ikut, karena platform tetap menyetor nilai brutonya dan pengembalian uang ke pembeli adalah peristiwa lain.
 */
final class PembayaranBelumDicairkan
{
    /** Dibatasi supaya halaman tidak pernah memuat ribuan baris sekaligus; setoran dicatat per periode, bukan setahun. */
    public const MAKS_BARIS = 500;

    /**
     * Batas wajar menunggu pencairan, per jenis metode (hari kalender sejak tanggal transaksi).
     *
     * Satu angka untuk semua jenis akan salah dua kali: QRIS biasanya cair H+1 sehingga tiga hari sudah pantas
     * ditanyakan, sedangkan platform ojol menyetor mingguan sehingga tiga hari cuma menghasilkan peringatan palsu
     * setiap hari. Angka per jenis ini bawaan yang masuk akal, bukan kesepakatan dengan platform; tenant bisa menimpanya
     * per metode lewat `MetodePembayaran.BatasHariMenunggu` (lihat `AmbilBatasHari`).
     *
     * @return array<string, int>
     */
    public static function BatasHariMenunggu(): array
    {
        return [
            JenisMetodePembayaran::QrisStatis->value => 3,
            JenisMetodePembayaran::QrisDinamis->value => 3,
            JenisMetodePembayaran::Edc->value => 5,
            JenisMetodePembayaran::Ewallet->value => 3,
            JenisMetodePembayaran::Transfer->value => 3,
            // Ojol & marketplace umumnya menyetor mingguan.
            JenisMetodePembayaran::Marketplace->value => 10,
        ];
    }

    /** Batas terlama yang boleh diatur tenant (hari kalender). */
    public const BATAS_HARI_MAKSIMAL = 60;

    /**
     * Batas hari menunggu **berlaku** untuk satu metode: buatan tenant bila ada, selain itu bawaan jenisnya (3 hari untuk
     * jenis yang tidak dikenal). Null bila metodenya tidak lewat akun kliring (tunai, tempo, deposit) sehingga tidak ada
     * yang perlu ditunggu.
     */
    public static function AmbilBatasHari(MetodePembayaran $metode): ?int
    {
        if (! in_array($metode->Jenis, self::JENIS_KLIRING, true)) {
            return null;
        }

        return $metode->BatasHariMenunggu ?? self::BatasHariMenunggu()[$metode->Jenis->value] ?? 3;
    }

    /** Metode yang uangnya lewat akun kliring dan karena itu perlu dicairkan (BR-08.3). */
    public const JENIS_KLIRING = [
        JenisMetodePembayaran::QrisStatis,
        JenisMetodePembayaran::QrisDinamis,
        JenisMetodePembayaran::Edc,
        JenisMetodePembayaran::Ewallet,
        JenisMetodePembayaran::Transfer,
        JenisMetodePembayaran::Marketplace,
    ];

    /**
     * Metode pembayaran yang bisa dicairkan, untuk pilihan formulir & saring daftar.
     *
     * @return list<array{Uuid: string, Nama: string, Jenis: string, PersenBiaya: string, BiayaTetap: string}>
     */
    public function Metode(): array
    {
        return array_values($this->MetodeModel()
            ->map(fn (MetodePembayaran $m): array => [
                'Uuid' => $m->Uuid,
                'Nama' => $m->Nama,
                'Jenis' => $m->Jenis->value,
                'PersenBiaya' => $m->PersenBiaya,
                'BiayaTetap' => $m->BiayaTetap,
            ])
            ->all());
    }

    /**
     * Pembayaran belum dicairkan untuk satu metode & satu outlet, urut tanggal penjualan.
     *
     * @return array{Data: list<array<string, mixed>>, Total: string, Terpotong: bool}
     */
    public function Ambil(string $uuidMetode, int $idOutlet, ?CarbonImmutable $sampai = null): array
    {
        $idMetode = MetodePembayaran::query()->where('Uuid', $uuidMetode)->value('Id');

        if ($idMetode === null) {
            return ['Data' => [], 'Total' => '0.00', 'Terpotong' => false];
        }

        $batasTanggal = $sampai?->toDateString();
        $idPenjualan = Penjualan::query()
            ->where('IdOutlet', $idOutlet)
            ->where('Status', '!=', StatusPenjualan::Void->value)
            ->when($batasTanggal !== null, fn ($q) => $q->where('TanggalBisnis', '<=', $batasTanggal))
            ->select('Id');

        $pembayaran = PenjualanPembayaran::query()
            ->where('IdMetodePembayaran', $idMetode)
            ->whereIn('IdPenjualan', $idPenjualan)
            ->whereNotIn('Id', PencairanDetail::query()->whereNotNull('IdPembayaranAktif')->select('IdPembayaranAktif'))
            ->orderBy('DibayarPada')
            ->orderBy('Id')
            ->limit(self::MAKS_BARIS + 1)
            ->get();

        $terpotong = $pembayaran->count() > self::MAKS_BARIS;
        $pembayaran = $pembayaran->take(self::MAKS_BARIS);
        $penjualan = Penjualan::query()->whereIn('Id', $pembayaran->pluck('IdPenjualan')->all())->get()->keyBy('Id');
        $total = Uang::Nol();
        $data = [];

        foreach ($pembayaran as $satu) {
            $dokumen = $penjualan->get($satu->IdPenjualan);
            $total = $total->Tambah($satu->AmbilJumlah());
            $data[] = [
                'Uuid' => $satu->Uuid,
                'NomorPenjualan' => $dokumen->Nomor ?? '',
                'TanggalPenjualan' => ($dokumen->TanggalBisnis ?? $satu->DibayarPada)->format('Y-m-d'),
                'StatusPenjualan' => $dokumen?->Status->value ?? '',
                'LabelStatusPenjualan' => $dokumen?->Status->AmbilLabel() ?? '',
                'Jumlah' => $satu->Jumlah,
                'Referensi' => $satu->Referensi,
                'RefEksternal' => $satu->RefEksternal,
            ];
        }

        return ['Data' => $data, 'Total' => $total->KeString(), 'Terpotong' => $terpotong];
    }

    /**
     * Ringkasan per metode: berapa banyak pembayaran & berapa nilai yang masih menunggu pencairan di satu outlet.
     * Dipakai halaman daftar supaya operator tahu ada uang menggantung tanpa membuka formulirnya.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array{Uuid: string, Nama: string, Jumlah: int, Total: string}>
     */
    public function Ringkasan(?array $idOutletBoleh): array
    {
        $metode = $this->MetodeModel();
        $idPenjualan = Penjualan::query()
            ->where('Status', '!=', StatusPenjualan::Void->value)
            ->when($idOutletBoleh !== null, fn ($q) => $q->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->select('Id');

        $hasil = [];

        foreach ($metode as $satu) {
            $baris = PenjualanPembayaran::query()
                ->where('IdMetodePembayaran', $satu->Id)
                ->whereIn('IdPenjualan', $idPenjualan)
                ->whereNotIn('Id', PencairanDetail::query()->whereNotNull('IdPembayaranAktif')->select('IdPembayaranAktif'))
                // Dijumlahkan di DB, dan hasilnya di-CAST ke teks: SUM(DECIMAL) yang lewat PDO bisa sampai sebagai
                // float, dan uang tidak pernah melewati float (CLAUDE.md #7).
                ->selectRaw('COUNT(*) as JumlahBaris, CAST(COALESCE(SUM(Jumlah), 0) AS CHAR) as TotalNilai')
                ->first();

            $jumlah = (int) ($baris?->getAttribute('JumlahBaris') ?? 0);

            if ($jumlah === 0) {
                continue;
            }

            $hasil[] = [
                'Uuid' => $satu->Uuid,
                'Nama' => $satu->Nama,
                'Jumlah' => $jumlah,
                'Total' => Uang::Dari((string) ($baris?->getAttribute('TotalNilai') ?? '0'))->KeString(),
            ];
        }

        return $hasil;
    }

    /**
     * Metode yang pembayarannya **sudah menunggu terlalu lama** menurut `BatasHariMenunggu` per jenisnya — inilah tanda
     * platform belum menyetor, dan satu-satunya cara toko mengetahuinya sebelum menutup buku.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array{Uuid: string, Nama: string, Jenis: string, Jumlah: int, Total: string, TanggalTerlama: string, UmurHari: int, BatasHari: int}>
     */
    public function MenungguTerlaluLama(?array $idOutletBoleh, CarbonImmutable $hariIni): array
    {
        $hasil = [];

        foreach ($this->MetodeModel() as $metode) {
            $batasHari = self::AmbilBatasHari($metode) ?? 3;
            $sampai = $hariIni->subDays($batasHari);
            $idPenjualan = Penjualan::query()
                ->where('Status', '!=', StatusPenjualan::Void->value)
                ->where('TanggalBisnis', '<=', $sampai->toDateString())
                ->when($idOutletBoleh !== null, fn ($q) => $q->whereIn('IdOutlet', $idOutletBoleh ?? []))
                ->select('Id');

            $pembayaran = PenjualanPembayaran::query()
                ->where('IdMetodePembayaran', $metode->Id)
                ->whereIn('IdPenjualan', $idPenjualan)
                ->whereNotIn('Id', PencairanDetail::query()->whereNotNull('IdPembayaranAktif')->select('IdPembayaranAktif'))
                ->orderBy('DibayarPada')
                ->get(['Id', 'IdPenjualan', 'Jumlah', 'DibayarPada']);

            if ($pembayaran->isEmpty()) {
                continue;
            }

            $total = Uang::Nol();

            foreach ($pembayaran as $satu) {
                $total = $total->Tambah($satu->AmbilJumlah());
            }

            $terlama = Penjualan::query()->whereKey($pembayaran->first()->IdPenjualan)->value('TanggalBisnis');
            $tanggalTerlama = $terlama instanceof Carbon ? CarbonImmutable::parse($terlama->toDateString()) : $hariIni;

            $hasil[] = [
                'Uuid' => $metode->Uuid,
                'Nama' => $metode->Nama,
                'Jenis' => $metode->Jenis->value,
                'Jumlah' => $pembayaran->count(),
                'Total' => $total->KeString(),
                'TanggalTerlama' => $tanggalTerlama->toDateString(),
                'UmurHari' => (int) $tanggalTerlama->diffInDays($hariIni),
                'BatasHari' => $batasHari,
            ];
        }

        return $hasil;
    }

    /** Jumlah pencairan yang sudah tercatat, untuk keadaan kosong halaman daftar. */
    public function CekAdaPencairan(): bool
    {
        return Pencairan::query()->exists();
    }

    /**
     * @return Collection<int, MetodePembayaran>
     */
    private function MetodeModel(): Collection
    {
        return MetodePembayaran::query()
            ->whereIn('Jenis', array_map(fn (JenisMetodePembayaran $j): string => $j->value, self::JENIS_KLIRING))
            ->orderBy('Urutan')
            ->orderBy('Id')
            ->get();
    }
}
