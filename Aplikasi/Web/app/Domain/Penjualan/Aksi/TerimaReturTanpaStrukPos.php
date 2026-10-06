<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Akuntansi\Layanan\PenyediaAkunPeran;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Kasir\Data\DataInfoShift;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Harga\Kueri\HargaProdukBerlaku;
use App\Domain\Katalog\Kontrak\PenyediaHppBahan;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Organisasi\Data\DataOutletPenjualan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Pelanggan\Layanan\PencatatDepositPenjualan;
use App\Domain\Penjualan\Data\DataBarisReturTanpaStrukPos;
use App\Domain\Penjualan\Data\DataReturTanpaStrukPos;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;
use App\Domain\Penjualan\Enum\MetodeRefund;
use App\Domain\Penjualan\Enum\StatusReturPenjualan;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\HasilBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\HasilKalkulasi;
use App\Domain\Penjualan\Layanan\PemeriksaPelakuPascaPenjualan;
use App\Domain\Penjualan\Layanan\PenghitungGrosir;
use App\Domain\Penjualan\Layanan\PenyusunJurnalReturPenjualan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\ReturPenjualanDetail;
use App\Domain\Penjualan\Model\ReturPenjualanPembayaran;
use App\Domain\Penjualan\Peristiwa\ReturPenjualanDiterima;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Layanan\PetaAkunPersediaan;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * K28 (F-09, PRD v4.01): retur **tanpa struk** dari item outbox `ReturPenjualan.TanpaStruk`. Pembeli mengembalikan
 * barang tanpa bukti beli, sehingga retur tidak merujuk penjualan mana pun. Keputusan pemilik produk: wajib PIN pemilik
 * atau pengguna yang berhak. Rincian (keputusan agen, D-17):
 *
 * - **Penyetuju** wajib ber-izin `penjualan.retur.tanpa-struk` (bawaan Pemilik & Admin), kasir ber-izin retur (K-22).
 * - **Nilai** = harga jual berlaku saat retur dari price engine untuk kanal Bawa pulang (tanpa tier atau promo, karena
 *   pembelinya tidak diketahui; kanal sama dengan bawaan kasir), dihitung `PenghitungGrosir::HitungRinci` (mesin kalkulasi yang sama dengan kasir; tanpa biaya layanan &
 *   pembulatan), pajak dengan tarif berlaku pada tanggal bisnis. Perangkat menghitung dengan cara yang sama;
 *   selisih = `HitunganTidakCocok`.
 * - **Refund** hanya `Tukar` (barang pengganti) atau `Deposit` pelanggan terdaftar, **tidak pernah uang tunai/transfer**:
 *   tanpa bukti beli, uang yang keluar dari laci adalah celah kecurangan paling umum.
 * - Hanya produk berstok biasa (bukan batch/seri, resep, paket, jasa): barang ber-batch/bernomor seri harus kembali ke
 *   batch/unit asalnya, yang hanya diketahui dari struk.
 * - Stok kembali (`MutasiStok` `ReturPenjualan`) bernilai HPP rata-rata lokasi Toko saat ini; HPP belum ada = 0 dan
 *   tinjauan. Rusak → lokasi Rusak outlet bila ada. Jurnal J-09.2 seperti retur biasa.
 * - Σ retur tanpa struk outlet pada tanggal bisnis itu di atas `BatasReturTanpaStrukHarian` tidak ditolak (barang sudah
 *   diterima offline, §18.3) melainkan menjadi tinjauan di Kotak Tindakan.
 */
final class TerimaReturTanpaStrukPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoShift $infoShift,
        private readonly OutletPenjualan $outletPenjualan,
        private readonly PemeriksaPelakuPascaPenjualan $pelaku,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly PengaturanKasirTenant $pengaturanKasir,
        private readonly IdentitasPelanggan $identitasPelanggan,
        private readonly HargaProdukBerlaku $harga,
        private readonly PenghitungGrosir $penghitung,
        private readonly PenyediaHppBahan $hpp,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly PetaAkunPersediaan $petaAkunPersediaan,
        private readonly PenyusunJurnalReturPenjualan $penyusunJurnal,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
        private readonly PencatatDepositPenjualan $deposit,
        private readonly PenyediaAkunPeran $penyediaAkun,
    ) {}

    public function Jalankan(DataReturTanpaStrukPos $data): StatusItemSinkron
    {
        if ($data->dibuatPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu retur ada di masa depan. Periksa jam perangkat.', 'DibuatPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => $this->Proses($data));
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) === 1062) {
                // Kiriman ganda bersamaan: yang kalah membaca ulang dokumen pemenang.
                $duplikat = $this->CekDuplikat($data);

                if ($duplikat !== null) {
                    return $duplikat;
                }

                if (str_contains($galat->getMessage(), 'UniqReturPenjualanIdTenantNomor')) {
                    throw new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$data->nomor} sudah dipakai retur lain.", 'Nomor', 409);
                }

                throw self::GalatUuidDipakai();
            }

            throw $galat;
        }
    }

    /**
     * `Duplikat` bila Uuid ini sudah tercatat dengan data yang sama; galat bila dipakai data lain; null bila baru.
     *
     * @phpstan-impure
     */
    private function CekDuplikat(DataReturTanpaStrukPos $data): ?StatusItemSinkron
    {
        $lama = ReturPenjualan::query()->where('Uuid', $data->uuid)->first();

        if ($lama === null) {
            return null;
        }

        if ($lama->TanpaStruk && $lama->Nomor === $data->nomor && Uang::Dari($lama->TotalRefund)->SamaDengan($data->totalRefund)) {
            return StatusItemSinkron::Duplikat;
        }

        throw self::GalatUuidDipakai();
    }

    private function Proses(DataReturTanpaStrukPos $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();

        $duplikat = $this->CekDuplikat($data);

        if ($duplikat !== null) {
            return $duplikat;
        }

        $shift = $this->infoShift->CariDiPerangkat($data->uuidShift, $data->idPerangkat);

        if ($shift === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Shift retur ini tidak ditemukan di perangkat ini. Kirim data shift lebih dulu.', 'UuidShift');
        }

        if ($data->dibuatPada->lessThan($shift->dibukaPada->subSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu retur lebih awal dari waktu buka shift.', 'DibuatPada');
        }

        $outlet = $this->outletPenjualan->Ambil($shift->idOutlet, $data->idPerangkat);

        if ($outlet === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Outlet shift retur ini tidak ditemukan.', 'UuidShift');
        }

        if ($outlet->idGudangToko === null) {
            throw new PelanggaranAturanBisnis('LokasiStokTidakAda', 'Outlet ini belum punya lokasi stok Toko untuk menerima barang retur.', 'UuidShift');
        }

        $tanggalBisnis = $this->tanggalBisnis->Hitung($outlet->idOutlet, $data->dibuatPada);

        // K28: kasir ber-izin retur; penyetuju (PIN) ber-izin retur tanpa struk.
        $kasir = $this->pelaku->CariKasir($idTenant, $data->uuidPengguna, $outlet->idOutlet, IzinTenant::PenjualanRetur);
        $penyetuju = $this->pelaku->CariPenyetuju($idTenant, $data->uuidPenyetuju, $outlet->idOutlet, IzinTenant::PenjualanReturTanpaStruk);

        $this->PeriksaNomor($data, $outlet, $tanggalBisnis);
        $pergeseranPeriode = $this->penjagaPeriode->JelaskanPergeseran($tanggalBisnis);

        $idPelanggan = null;

        if ($data->uuidPelanggan !== null) {
            $idPelanggan = $this->identitasPelanggan->CariId($data->uuidPelanggan)
                ?? throw new PelanggaranAturanBisnis('PelangganTidakDitemukan', 'Pelanggan retur ini tidak ditemukan.', 'UuidPelanggan', 404);
        }

        $barisProduk = $this->SusunBaris($data, $outlet);
        [$hasil, $pajakDokumen] = $this->penghitung->HitungRinci($outlet->idOutlet, $outlet->kodeKota, array_map(
            fn (array $b): array => [
                'Jumlah' => $b['Jumlah'],
                'HargaSatuan' => $b['HargaSatuan'],
                'Diskon' => Uang::Nol(),
                'IdKelompokPajak' => $b['Produk']->IdKelompokPajak,
                'HargaTermasukPajak' => $b['Produk']->HargaTermasukPajak,
            ],
            $barisProduk,
        ), CarbonImmutable::parse($tanggalBisnis->toDateString()));
        $total = $hasil->totalAkhir;

        if (! $total->SamaDengan($data->totalRefund)) {
            throw new PelanggaranAturanBisnis(
                'HitunganTidakCocok',
                "Hitungan Ringkasan.TotalRefund di perangkat ({$data->totalRefund->FormatRupiah()}) berbeda dengan hitungan server ({$total->FormatRupiah()}). Perbarui data harga di aplikasi lalu ulangi retur.",
                'Ringkasan.TotalRefund',
                422,
                ['Perangkat' => $data->totalRefund->KeString(), 'Server' => $total->KeString()],
            );
        }

        $metode = $this->AmbilMetode($data, $total, $idPelanggan !== null);

        $tinjauan = $shift->aktif ? [] : ['ShiftSudahDitutup: retur diterima setelah shift ditutup'];

        if ($pergeseranPeriode !== null) {
            $tinjauan[] = $pergeseranPeriode;
        }

        $idGudangRusak = $this->outletPenjualan->AmbilIdGudangRusak($outlet->idOutlet);

        if ($idGudangRusak === null && array_filter($data->baris, fn (DataBarisReturTanpaStrukPos $b): bool => $b->kondisi === KondisiBarangRetur::Rusak) !== []) {
            $tinjauan[] = 'LokasiRusakTidakAda: barang rusak dikembalikan ke lokasi Toko karena outlet belum punya lokasi stok Rusak';
        }

        $batas = $this->pengaturanKasir->Ambil()->batasReturTanpaStrukHarian;
        $hariIni = Uang::Dari((string) ReturPenjualan::query()
            ->where('IdOutlet', $outlet->idOutlet)
            ->where('TanpaStruk', true)
            ->where('TanggalBisnis', $tanggalBisnis->toDateString())
            ->lockForUpdate()
            ->sum('TotalRefund'))->Tambah($total);

        if ($hariIni->Bandingkan($batas) > 0) {
            $tinjauan[] = "BatasReturTanpaStruk: retur tanpa struk hari ini {$hariIni->FormatRupiah()} melewati batas {$batas->FormatRupiah()}";
        }

        $rencana = $this->RencanakanStok($barisProduk, (int) $outlet->idGudangToko, $idGudangRusak, $tinjauan);
        $retur = $this->SimpanRetur($data, $shift, $outlet, $kasir->id, $penyetuju->id, $idPelanggan, $tanggalBisnis, $hasil, $pajakDokumen, $metode, $rencana, $tinjauan);
        $idDetail = $this->SimpanDetail($retur, $barisProduk, $hasil, $rencana);
        $this->SimpanRefund($data, $retur, $metode);
        $persediaan = $this->CatatStok($retur, $rencana, $idDetail, $kasir->id, $data->idPerangkat);

        $refundJurnal = [];

        foreach ($data->refund as $r) {
            $refundJurnal[] = [$metode[$r->uuidMetodePembayaran], $r->jumlah];

            if ($metode[$r->uuidMetodePembayaran]->Jenis === JenisMetodePembayaran::Tukar) {
                $this->penyediaAkun->Pastikan(PeranAkun::KliringTukarBarang, '2-1800', 'Kliring Tukar Barang');
            }
        }

        $pajak = [];

        foreach ($hasil->pajak as $p) {
            if (! $p->jumlah->BernilaiNol()) {
                $pajak[$p->kode] = $p->jumlah;
            }
        }

        $jurnal = $this->penyusunJurnal->Susun($retur, $total, Uang::Nol(), $pajak, $refundJurnal, $persediaan);

        if (array_filter($jurnal->baris, fn ($b): bool => ! $b->debit->BernilaiNol() || ! $b->kredit->BernilaiNol()) !== []) {
            $retur->IdJurnal = $this->postingJurnal->Jalankan($jurnal)->idJurnal;
            $retur->save();
        }

        $refundDeposit = Uang::Nol();

        foreach ($data->refund as $r) {
            if ($metode[$r->uuidMetodePembayaran]->Jenis === JenisMetodePembayaran::Deposit) {
                $refundDeposit = $refundDeposit->Tambah($r->jumlah);
            }
        }

        if (! $refundDeposit->BernilaiNol() && $idPelanggan !== null) {
            $this->deposit->CatatRefund($idPelanggan, $retur->Id, $retur->Nomor, $refundDeposit, $tanggalBisnis, $kasir->id);
        }

        $this->riwayat->Catat(ReturPenjualan::JENIS_DOKUMEN, $retur->Id, null, StatusReturPenjualan::Selesai->value, $kasir->id);
        $this->audit->Catat('penjualan.retur-tanpa-struk', $retur, nilaiBaru: [
            'Nomor' => $retur->Nomor,
            'TotalRefund' => $retur->TotalRefund,
            'MetodeRefund' => $retur->MetodeRefund->value,
            'Alasan' => $retur->Alasan,
            'DisetujuiOleh' => $penyetuju->nama,
            'PerluTinjauan' => $retur->PerluTinjauan,
        ], idPengguna: $kasir->id);

        ReturPenjualanDiterima::dispatch($retur->IdTenant, $retur->IdOutlet, $retur->TanggalBisnis->toDateString(), $retur->Id);

        return StatusItemSinkron::Diterima;
    }

    /** `RJ/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}`, seri yang sama dengan retur biasa. */
    private function PeriksaNomor(DataReturTanpaStrukPos $data, DataOutletPenjualan $outlet, CarbonImmutable $tanggalBisnis): void
    {
        $pola = '#^RJ/'.preg_quote($outlet->kodeOutlet, '#').'/(\d{6})/'.preg_quote($outlet->kodePerangkat, '#').'-\d{4,}$#';
        $tanggalBoleh = [$tanggalBisnis->format('ymd'), $data->dibuatPada->setTimezone($outlet->zonaWaktu)->format('ymd')];

        if (preg_match($pola, $data->nomor, $cocok) !== 1 || ! in_array($cocok[1], $tanggalBoleh, true)) {
            throw new PelanggaranAturanBisnis(
                'NomorTidakValid',
                "Nomor {$data->nomor} tidak sesuai format RJ/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001.",
                'Nomor',
                detail: ['Contoh' => "RJ/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001"],
            );
        }

        if (ReturPenjualan::query()->where('Nomor', $data->nomor)->exists()) {
            throw new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$data->nomor} sudah dipakai retur lain.", 'Nomor', 409);
        }
    }

    /**
     * Produk, satuan, dan harga berlaku per baris. Hanya produk berstok biasa yang aktif.
     *
     * @return list<array{Baris: DataBarisReturTanpaStrukPos, Produk: Produk, Satuan: ProdukSatuan, Jumlah: Kuantitas, JumlahDasar: Kuantitas, HargaSatuan: Uang}>
     */
    private function SusunBaris(DataReturTanpaStrukPos $data, DataOutletPenjualan $outlet): array
    {
        $hasil = [];

        foreach ($data->baris as $indeks => $baris) {
            $produk = Produk::query()->where('Uuid', $baris->uuidProduk)->first();

            if (! $produk instanceof Produk) {
                throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk baris ke-'.($indeks + 1).' tidak ditemukan.', "Baris.{$indeks}.UuidProduk");
            }

            if ($produk->Jenis !== JenisProduk::Stok) {
                throw new PelanggaranAturanBisnis('JenisProdukTidakDidukung', "{$produk->Nama} bukan barang berstok biasa sehingga tidak bisa diretur tanpa struk.", "Baris.{$indeks}.UuidProduk");
            }

            if ($produk->Pelacakan !== PelacakanProduk::Tidak) {
                throw new PelanggaranAturanBisnis('ReturTanpaStrukButuhStruk', "{$produk->Nama} ber-batch atau bernomor seri sehingga hanya bisa diretur dengan struk.", "Baris.{$indeks}.UuidProduk");
            }

            $satuan = $baris->uuidProdukSatuan === null
                ? ProdukSatuan::query()->where('IdProduk', $produk->Id)->where('IdSatuan', $produk->IdSatuanDasar)->first()
                : ProdukSatuan::query()->where('Uuid', $baris->uuidProdukSatuan)->where('IdProduk', $produk->Id)->first();

            if (! $satuan instanceof ProdukSatuan) {
                throw new PelanggaranAturanBisnis('SatuanTidakDikenal', "Satuan {$produk->Nama} tidak ditemukan.", "Baris.{$indeks}.UuidProdukSatuan");
            }

            $harga = $this->harga->Tentukan($produk, $satuan, $baris->jumlah, $outlet->idOutlet, KanalPenjualan::BawaPulang, null, $data->dibuatPada)
                ?? throw new PelanggaranAturanBisnis('HargaBelumDiatur', "{$produk->Nama} belum punya harga jual untuk satuan itu.", "Baris.{$indeks}.UuidProdukSatuan");

            $hasil[] = [
                'Baris' => $baris,
                'Produk' => $produk,
                'Satuan' => $satuan,
                'Jumlah' => $baris->jumlah,
                'JumlahDasar' => $baris->jumlah->Kali($satuan->KonversiKeDasar),
                'HargaSatuan' => $harga->harga,
            ];
        }

        return $hasil;
    }

    /**
     * Refund retur tanpa struk: hanya `Tukar` atau `Deposit` (pelanggan wajib), Σ refund = total.
     *
     * @return array<string, MetodePembayaran> kunci = Uuid metode
     */
    private function AmbilMetode(DataReturTanpaStrukPos $data, Uang $total, bool $berpelanggan): array
    {
        $uuid = array_values(array_unique(array_map(fn ($r): string => $r->uuidMetodePembayaran, $data->refund)));
        $metode = $uuid === [] ? [] : MetodePembayaran::query()->whereIn('Uuid', $uuid)->get()->keyBy('Uuid')->all();
        $jumlah = Uang::Nol();

        foreach ($data->refund as $indeks => $r) {
            $m = $metode[$r->uuidMetodePembayaran] ?? null;

            if (! $m instanceof MetodePembayaran) {
                throw new PelanggaranAturanBisnis('MetodeBayarTidakDikenal', 'Metode refund tidak ditemukan.', "Refund.{$indeks}.UuidMetodePembayaran");
            }

            if ($m->Jenis === JenisMetodePembayaran::Deposit && ! $berpelanggan) {
                throw new PelanggaranAturanBisnis('DepositTanpaPelanggan', 'Refund ke deposit wajib memilih pelanggan.', 'UuidPelanggan');
            }

            if (! in_array($m->Jenis, [JenisMetodePembayaran::Tukar, JenisMetodePembayaran::Deposit], true)) {
                throw new PelanggaranAturanBisnis('MetodeBayarBelumDidukung', "Retur tanpa struk tidak bisa direfund lewat {$m->Jenis->AmbilLabel()}. Pakai tukar barang atau deposit pelanggan.", "Refund.{$indeks}.UuidMetodePembayaran");
            }

            $jumlah = $jumlah->Tambah($r->jumlah);
        }

        if (! $jumlah->SamaDengan($total)) {
            throw new PelanggaranAturanBisnis(
                'RefundTidakSesuai',
                "Jumlah refund {$jumlah->FormatRupiah()} harus sama dengan nilai retur {$total->FormatRupiah()}.",
                'Refund',
                422,
                ['TotalRetur' => $total->KeString(), 'TotalRefund' => $jumlah->KeString()],
            );
        }

        return $metode;
    }

    /**
     * Satu mutasi masuk per baris: jumlah dasar ke lokasi Toko (Rusak ke lokasi Rusak bila ada), bernilai HPP rata-rata
     * lokasi Toko saat ini.
     *
     * @param  list<array{Baris: DataBarisReturTanpaStrukPos, Produk: Produk, Satuan: ProdukSatuan, Jumlah: Kuantitas, JumlahDasar: Kuantitas, HargaSatuan: Uang}>  $baris
     * @param  list<string>  $tinjauan
     * @return list<array{IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>
     */
    private function RencanakanStok(array $baris, int $idGudangToko, ?int $idGudangRusak, array &$tinjauan): array
    {
        $rencana = [];
        $tanpaHpp = [];

        foreach ($baris as $b) {
            $hpp = $this->hpp->AmbilHppSatuan($b['Produk']->Id, $idGudangToko);

            if ($hpp === null) {
                $tanpaHpp[] = $b['Produk']->Nama;
                $hpp = BigDecimal::zero();
            }

            $rusak = $b['Baris']->kondisi === KondisiBarangRetur::Rusak && $idGudangRusak !== null;
            $rencana[] = [
                'IdProduk' => $b['Produk']->Id,
                'IdGudang' => $rusak ? (int) $idGudangRusak : $idGudangToko,
                'Jumlah' => $b['JumlahDasar'],
                'Nilai' => Uang::Dari($hpp->multipliedBy($b['JumlahDasar']->KeDesimal())->toScale(Uang::SKALA, RoundingMode::HalfUp)),
                'HppSatuan' => $hpp,
            ];
        }

        if ($tanpaHpp !== []) {
            $tinjauan[] = 'HppTidakDiketahui: '.implode(', ', array_unique($tanpaHpp)).' kembali ke stok bernilai 0';
        }

        return $rencana;
    }

    /**
     * @param  array<string, DataPajakKalkulasi>  $pajakDokumen
     * @param  array<string, MetodePembayaran>  $metode
     * @param  list<array{IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>  $rencana
     * @param  list<string>  $tinjauan
     */
    private function SimpanRetur(
        DataReturTanpaStrukPos $data,
        DataInfoShift $shift,
        DataOutletPenjualan $outlet,
        int $idKasir,
        int $idPenyetuju,
        ?int $idPelanggan,
        CarbonImmutable $tanggalBisnis,
        HasilKalkulasi $hasil,
        array $pajakDokumen,
        array $metode,
        array $rencana,
        array $tinjauan,
    ): ReturPenjualan {
        $jenis = [];

        foreach ($data->refund as $r) {
            $jenis[$metode[$r->uuidMetodePembayaran]->Jenis->value] = true;
        }

        $metodeRefund = match (true) {
            count($jenis) > 1 => MetodeRefund::Campuran,
            isset($jenis[JenisMetodePembayaran::Deposit->value]) => MetodeRefund::Deposit,
            default => MetodeRefund::Tukar,
        };

        $rincianPajak = [];

        foreach ($hasil->pajak as $p) {
            if (! $p->jumlah->BernilaiNol()) {
                $rincianPajak[] = [
                    'Kode' => $p->kode,
                    'Tarif' => (string) ($pajakDokumen[$p->kode]->tarif ?? '0'),
                    'Dpp' => $p->dpp->KeString(),
                    'Jumlah' => $p->jumlah->KeString(),
                ];
            }
        }

        return ReturPenjualan::query()->create([
            'Uuid' => $data->uuid,
            'IdPenjualanAsal' => null,
            'TanpaStruk' => true,
            'IdPelanggan' => $idPelanggan,
            'IdOutlet' => $outlet->idOutlet,
            'IdShift' => $shift->id,
            'IdPerangkat' => $data->idPerangkat,
            'Nomor' => $data->nomor,
            'Status' => StatusReturPenjualan::Selesai,
            'Alasan' => mb_substr($data->alasan, 0, 255),
            'MetodeRefund' => $metodeRefund,
            'IdPengguna' => $idKasir,
            'IdPenyetuju' => $idPenyetuju,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'TotalNilai' => $hasil->totalAkhir->KeString(),
            'TotalPajak' => $hasil->totalPajak->KeString(),
            'RincianPajak' => $rincianPajak === [] ? null : $rincianPajak,
            'TotalBiayaLayanan' => '0.00',
            'TotalRefund' => $hasil->totalAkhir->KeString(),
            'RefundTunai' => '0.00',
            'TotalHpp' => array_reduce($rencana, fn (Uang $t, array $r): Uang => $t->Tambah($r['Nilai']), Uang::Nol())->KeString(),
            'PerluTinjauan' => $tinjauan !== [],
            'AlasanTinjauan' => $tinjauan === [] ? null : mb_substr(implode('; ', $tinjauan), 0, 255),
            'DibuatOfflinePada' => $data->dibuatPada,
            'DiterimaPada' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  list<array{Baris: DataBarisReturTanpaStrukPos, Produk: Produk, Satuan: ProdukSatuan, Jumlah: Kuantitas, JumlahDasar: Kuantitas, HargaSatuan: Uang}>  $baris
     * @param  list<array{IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>  $rencana
     * @return list<int>
     */
    private function SimpanDetail(ReturPenjualan $retur, array $baris, HasilKalkulasi $hasil, array $rencana): array
    {
        $id = [];

        foreach ($baris as $indeks => $b) {
            /** @var HasilBarisKalkulasi $h */
            $h = $hasil->baris[$indeks];

            $id[] = ReturPenjualanDetail::query()->create([
                'Uuid' => $b['Baris']->uuid,
                'IdReturPenjualan' => $retur->Id,
                'IdPenjualanDetail' => null,
                'Urutan' => $indeks + 1,
                'IdProduk' => $b['Produk']->Id,
                'IdProdukSatuan' => $b['Satuan']->Id,
                'NamaProduk' => mb_substr($b['Produk']->Nama, 0, 200),
                'Jumlah' => $b['Jumlah']->KeString(),
                'JumlahDasar' => $b['JumlahDasar']->KeString(),
                'NilaiBaris' => $h->totalBaris->KeString(),
                'Pajak' => $h->pajak->KeString(),
                'BiayaLayanan' => '0.00',
                'HppSatuan' => (string) $rencana[$indeks]['HppSatuan'],
                'TotalHpp' => $rencana[$indeks]['Nilai']->KeString(),
                'Kondisi' => $b['Baris']->kondisi,
                'IdGudang' => $rencana[$indeks]['IdGudang'],
            ])->Id;
        }

        return $id;
    }

    /**
     * @param  array<string, MetodePembayaran>  $metode
     */
    private function SimpanRefund(DataReturTanpaStrukPos $data, ReturPenjualan $retur, array $metode): void
    {
        foreach ($data->refund as $indeks => $r) {
            $m = $metode[$r->uuidMetodePembayaran];

            ReturPenjualanPembayaran::query()->create([
                'Uuid' => $r->uuid,
                'IdReturPenjualan' => $retur->Id,
                'Urutan' => $indeks + 1,
                'IdMetodePembayaran' => $m->Id,
                'JenisMetode' => $m->Jenis,
                'NamaMetode' => mb_substr($m->Nama, 0, 100),
                'Jumlah' => $r->jumlah->KeString(),
            ]);
        }
    }

    /**
     * @param  list<array{IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>  $rencana
     * @param  list<int>  $idDetail
     * @return array<string, Uang> nilai PeranAkun persediaan → nilai stok yang kembali
     */
    private function CatatStok(ReturPenjualan $retur, array $rencana, array $idDetail, int $idKasir, int $idPerangkat): array
    {
        $hasil = $this->catatMutasi->Jalankan(new DataDokumenMutasi(
            jenisReferensi: JenisReferensiMutasi::ReturPenjualan,
            idReferensi: $retur->Id,
            uuidReferensi: $retur->Uuid,
            nomorReferensi: $retur->Nomor,
            tanggalBisnis: CarbonImmutable::parse($retur->TanggalBisnis->toDateString()),
            idPengguna: $idKasir,
            idPerangkat: $idPerangkat,
            baris: array_map(fn (array $r, int $indeks): DataBarisMutasi => new DataBarisMutasi(
                kunciBaris: (string) $idDetail[$indeks],
                idProduk: $r['IdProduk'],
                idGudang: $r['IdGudang'],
                jenisMutasi: JenisMutasi::ReturPenjualan,
                jumlah: $r['Jumlah'],
                modeNilai: ModeNilaiMutasi::Ditentukan,
                nilai: $r['Nilai'],
                hppSatuan: $r['HppSatuan'],
                idReferensiDetail: $idDetail[$indeks],
            ), $rencana, array_keys($rencana)),
        ));

        $persediaan = [];

        foreach ($hasil->baris as $b) {
            $produk = Produk::query()->whereKey($b->idProduk)->firstOrFail();
            $peran = $this->petaAkunPersediaan->UntukJenis($produk->Jenis)->value;
            $persediaan[$peran] = ($persediaan[$peran] ?? Uang::Nol())->Tambah($b->totalHpp);
        }

        return $persediaan;
    }

    private static function GalatUuidDipakai(): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik retur ini sudah dipakai data lain. Buat ulang retur di aplikasi.', 'Uuid');
    }
}
