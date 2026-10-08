<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\KomposisiPenjualan;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Layanan\PencatatPiutangPenjualan;
use App\Domain\Penjualan\Data\DataSudahDiretur;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Layanan\PenghitungNilaiRetur;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Carbon\CarbonImmutable;

/**
 * `GET /api/pos/v1/penjualan/cari?nomor=` (F-09 fase 1): struk asal untuk layar retur POS. Hanya penjualan outlet
 * perangkat (lainnya = null → 404). Per baris: snapshot harga & nilai, jumlah yang sudah dan masih bisa diretur, serta
 * sisa nilai (`NilaiBisaDiretur`, dipakai perangkat bila retur menghabiskan sisa baris). `BisaDiretur` = status
 * lunas/diretur sebagian dan belum lewat `BatasHariRetur` (dihitung dari tanggal bisnis outlet saat ini);
 * `AlasanTidakBisaDiretur`: `Void`, `SudahDireturPenuh`, `LewatBatasHari`. Kunci tambahan per baris (kompatibel mundur):
 * `BolehDesimal` (satuan dasar produk boleh jumlah desimal) dan `UuidProdukSatuan` (satuan jual produk yang dipakai
 * baris; null bila tidak ditemukan lagi). F-12: `SisaPiutang` (null = bukan penjualan tempo): retur memotong piutang ini lebih
 * dulu lewat metode Tempo, sisanya tunai/transfer. F-16d: `BisaRefundDeposit` (penjualan berpelanggan).
 */
final class CariPenjualanPos
{
    public function __construct(
        private readonly PenghitungNilaiRetur $penghitung,
        private readonly AnggotaOutlet $anggota,
        private readonly KomposisiPenjualan $komposisi,
        private readonly InfoProdukStok $infoProduk,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PengaturanKasirTenant $pengaturanKasir,
        private readonly PencatatPiutangPenjualan $piutang,
    ) {}

    /**
     * Uuid penjualan (ULID 26 karakter) dari tautan/kode struk digital; null bila [teks] bukan kode struk.
     */
    public static function AmbilUuidDariKodeStruk(string $teks): ?string
    {
        if (preg_match('/(?:^|[\/.])([0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26})(?:[?#].*)?$/', trim($teks), $cocok) !== 1) {
            return null;
        }

        return strtoupper($cocok[1]);
    }

    /**
     * Penjualan outlet yang masih bisa diretur untuk dipilih di layar retur (`GET penjualan/kandidat?kata=`): kasir
     * cukup mengetik sebagian nomor (misal empat angka terakhir) atau memilih dari yang terbaru, tanpa mengetik nomor
     * struk utuh. Hanya lunas/diretur sebagian dalam `BatasHariRetur`, terbaru dulu, paling banyak 10. [kata] kosong =
     * yang terbaru; kata pendek (1 karakter) ditolak agar tidak mengembalikan semuanya. Kata berupa angka juga dicocokkan ke total struk.
     *
     * @return list<array<string, mixed>>
     */
    public function CariKandidat(string $kata, int $idOutlet): array
    {
        $kata = trim($kata);
        $batasHari = $this->pengaturanKasir->Ambil()->batasHariRetur;
        $sejak = $this->tanggalBisnis->Hitung($idOutlet)->subDays($batasHari)->toDateString();
        $kueri = Penjualan::query()
            ->where('IdOutlet', $idOutlet)
            ->whereIn('Status', [StatusPenjualan::Lunas->value, StatusPenjualan::DireturSebagian->value])
            ->where('TanggalBisnis', '>=', $sejak);

        if ($kata !== '') {
            if (mb_strlen($kata) < 2) {
                return [];
            }

            $aman = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $kata);
            // Nomor memuat kata itu, atau (kata hanya angka/pemisah ribuan) total struk sama dengan angka itu: kasir sering
            // hanya ingat nominalnya.
            $nominal = preg_match('/^\d{1,3}([.,]\d{3})*$|^\d+$/', $kata) === 1 ? str_replace(['.', ','], '', $kata) : null;
            $kueri->where(function ($cocok) use ($aman, $nominal): void {
                $cocok->where('Nomor', 'like', '%'.$aman.'%');

                if ($nominal !== null) {
                    $cocok->orWhere('TotalAkhir', $nominal);
                }
            });
        }

        $baris = $kueri->orderByDesc('DibuatOfflinePada')->orderByDesc('Id')->limit(10)->get()
            ->map(fn (Penjualan $p): array => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'DibuatPada' => $p->DibuatOfflinePada->utc()->toIso8601ZuluString(),
                'TotalAkhir' => $p->TotalAkhir,
            ])
            ->all();

        return array_values($baris);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function Ambil(string $nomor, int $idOutlet): ?array
    {
        $p = Penjualan::query()->where('Nomor', $nomor)->where('IdOutlet', $idOutlet)->first();

        // Hasil pindai QR struk digital (`.../s/{kode}`, kode = tenant.Uuid) atau Uuid penjualan: nomor struk panjang
        // tidak perlu diketik. Tetap dibatasi outlet perangkat dan scope tenant.
        if ($p === null && ($uuid = self::AmbilUuidDariKodeStruk($nomor)) !== null) {
            $p = Penjualan::query()->where('Uuid', $uuid)->where('IdOutlet', $idOutlet)->first();
        }

        if ($p === null) {
            return null;
        }

        $detail = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get();
        $sudah = $this->penghitung->AmbilSudahDiretur(array_values(array_map('intval', $detail->pluck('Id')->all())));
        $simbol = $this->komposisi->AmbilSimbolSatuan(array_values(array_map('intval', $detail->pluck('IdSatuan')->all())));
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map('intval', $detail->pluck('IdProduk')->all()))), true);
        $satuanProduk = $this->komposisi->AmbilProduk(array_values(array_filter(array_map(fn ($info): string => $info->uuid, $produk))));
        $nama = $this->anggota->AmbilNama([$p->IdPengguna]);
        $batasHari = $this->pengaturanKasir->Ambil()->batasHariRetur;
        $batasSampai = CarbonImmutable::parse($p->TanggalBisnis->toDateString())->addDays($batasHari);
        $alasan = match (true) {
            $p->Status === StatusPenjualan::Void => 'Void',
            $p->Status === StatusPenjualan::Diretur => 'SudahDireturPenuh',
            $this->tanggalBisnis->Hitung($idOutlet)->greaterThan($batasSampai) => 'LewatBatasHari',
            default => null,
        };
        $metode = MetodePembayaran::query()
            ->whereIn('Id', PenjualanPembayaran::query()->where('IdPenjualan', $p->Id)->select('IdMetodePembayaran'))
            ->pluck('Uuid', 'Id')
            ->all();

        return [
            'Penjualan' => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'DibuatPada' => $p->DibuatOfflinePada->utc()->toIso8601ZuluString(),
                'NamaKasir' => $nama[$p->IdPengguna]['Nama'] ?? '',
                'HargaTermasukPajak' => $p->HargaTermasukPajak,
                'Subtotal' => $p->Subtotal,
                'TotalDiskon' => $p->TotalDiskon,
                'BiayaLayanan' => $p->BiayaLayanan,
                'TotalPajak' => $p->TotalPajak,
                'Pembulatan' => $p->Pembulatan,
                'TotalAkhir' => $p->TotalAkhir,
                'TotalDibayar' => $p->TotalDibayar,
                'Kembalian' => $p->Kembalian,
                'BatasHariRetur' => $batasHari,
                'BatasReturSampai' => $batasSampai->toDateString(),
                'BisaDiretur' => $alasan === null,
                'AlasanTidakBisaDiretur' => $alasan,
                'SisaPiutang' => $this->piutang->AmbilSisa($p->Id)?->KeString(),
                // F-16d bagian 1 (tambahan kompatibel mundur): refund boleh ke deposit bila penjualan berpelanggan.
                'BisaRefundDeposit' => $p->IdPelanggan !== null,
            ],
            'Baris' => array_values($detail->map(function (PenjualanDetail $d) use ($sudah, $simbol, $produk, $satuanProduk): array {
                $s = $sudah[$d->Id] ?? DataSudahDiretur::Kosong();
                $info = $produk[$d->IdProduk] ?? null;
                $uuidSatuan = null;

                foreach ($info === null ? [] : ($satuanProduk[$info->uuid]->satuan ?? []) as $uuid => $satuan) {
                    if ($satuan['IdSatuan'] === $d->IdSatuan) {
                        $uuidSatuan = $uuid;

                        break;
                    }
                }

                return [
                    'Uuid' => $d->Uuid,
                    'UuidProduk' => $produk[$d->IdProduk]->uuid ?? null,
                    'NamaProduk' => $d->NamaProduk,
                    'SimbolSatuan' => $simbol[$d->IdSatuan] ?? '',
                    'UuidProdukSatuan' => $uuidSatuan,
                    'BolehDesimal' => $info->bolehDesimal ?? false,
                    'Jumlah' => $d->Jumlah,
                    'HargaSatuan' => $d->HargaSatuan,
                    'HargaPilihan' => $d->HargaPilihan,
                    'Pilihan' => $d->Pilihan ?? [],
                    'Bruto' => $d->Bruto,
                    'JumlahDiskon' => $d->JumlahDiskon,
                    'JumlahDiskonPesanan' => $d->JumlahDiskonPesanan,
                    'BiayaLayanan' => $d->BiayaLayanan,
                    'JumlahPajak' => $d->JumlahPajak,
                    'TotalBaris' => $d->TotalBaris,
                    'SnapshotPajak' => $d->SnapshotPajak ?? [],
                    'JumlahSudahDiretur' => $s->jumlah->KeString(),
                    // Apotek: obat racikan tidak bisa diretur (`ReturRacikanTidakDidukung`); sisa 0 agar aplikasi lama pun
                    // tidak membuat retur yang pasti ditolak, `Racikan` agar aplikasi baru menjelaskan alasannya.
                    'Racikan' => $d->Racikan !== null,
                    'JumlahBisaDiretur' => $d->Racikan !== null ? '0.0000' : $this->penghitung->HitungSisa($d, $s)->KeString(),
                    'NilaiBisaDiretur' => $d->Racikan !== null ? '0.00' : $this->penghitung->HitungSisaNilai($d, $s)->KeString(),
                ];
            })->all()),
            'Pembayaran' => array_values(PenjualanPembayaran::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get()->map(fn (PenjualanPembayaran $b): array => [
                'Uuid' => $b->Uuid,
                'UuidMetodePembayaran' => $metode[$b->IdMetodePembayaran] ?? null,
                'JenisMetode' => $b->JenisMetode->value,
                'NamaMetode' => $b->NamaMetode,
                'Jumlah' => $b->Jumlah,
                'Referensi' => $b->Referensi,
            ])->all()),
            'Retur' => array_values(ReturPenjualan::query()->where('IdPenjualanAsal', $p->Id)->orderBy('Id')->get()->map(fn (ReturPenjualan $r): array => [
                'Uuid' => $r->Uuid,
                'Nomor' => $r->Nomor,
                'DibuatPada' => $r->DibuatOfflinePada->utc()->toIso8601ZuluString(),
                'TotalRefund' => $r->TotalRefund,
            ])->all()),
        ];
    }
}
