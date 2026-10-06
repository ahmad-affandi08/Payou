<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Kueri\CariPelangganPos;
use App\Domain\Penjualan\Aksi\BuatPesananKios;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\PesananOnline;
use App\Domain\Penjualan\Model\PesananOnlineDetail;
use App\Domain\Promo\Layanan\PemakaiVoucher;
use Carbon\CarbonImmutable;

/**
 * Pesanan online aktif untuk dimuat kasir ke keranjang POS; belum menyentuh stok/jurnal.
 *
 * F-17 bagian 2: `SisaUangMuka` adalah uang pelanggan yang sudah diterima (J-17.1) dan belum dipakai penjualan mana
 * pun. Kasir memakainya sebagai baris bayar **Uang muka** dengan `UuidPesananOnline`, persis seperti pre-order F-12,
 * sehingga pesanan berbayar tidak ditagihkan dua kali.
 *
 * F-17 bagian 3: `Pelanggan` (null untuk tamu) = pembeli yang masuk dengan kode WhatsApp, dalam bentuk yang sama
 * dengan hasil cari pelanggan POS (`GET /pelanggan`), supaya kasir memasangnya ke keranjang tanpa mencarinya lagi:
 * poin, tier, dan promo pelanggan berlaku seperti belanja di toko. Kontrak hanya bertambah (kompatibel mundur).
 */
final class PesananOnlineOutlet
{
    public function __construct(
        private readonly CariPelangganPos $pelanggan,
        private readonly TanggalBisnisOutlet $tanggal,
        private readonly PemakaiVoucher $voucher,
    ) {}

    /**
     * BR-17.3 (v3.33): ringkasan untuk polling aplikasi kasir tiap 10 detik — murah (tiga hitungan, tanpa detail).
     * `Baru` = pesanan yang masuk antrean toko (sudah dibayar bila bayar di muka) sejak `$sejak`.
     *
     * @return array{Menunggu: int, PerluDitagih: int, Baru: int, WaktuServer: string}
     */
    public function AmbilRingkas(int $idOutlet, ?CarbonImmutable $sejak): array
    {
        // Satu kueri (dulu tiga COUNT terpisah) karena endpoint ini dipolling tiap 10 detik oleh setiap kasir.
        // Pesanan bayar di muka baru "masuk" saat dibayar; yang lain saat dibuat.
        $menunggu = StatusPesananOnline::MenungguKonfirmasi->value;
        $baru = $sejak === null
            ? '0'
            : 'SUM(CASE WHEN `Status` = ? AND (`DibuatPada` > ? OR `DibayarPada` > ?) THEN 1 ELSE 0 END)';
        $ikatBaru = $sejak === null ? [] : [$menunggu, $sejak, $sejak];

        $hitung = PesananOnline::query()
            ->where('IdOutlet', $idOutlet)
            ->whereIn('Status', [$menunggu, StatusPesananOnline::Siap->value])
            ->selectRaw(
                'SUM(CASE WHEN `Status` = ? THEN 1 ELSE 0 END) AS Menunggu, '
                .'SUM(CASE WHEN `Status` = ? AND `IdPenjualan` IS NULL THEN 1 ELSE 0 END) AS PerluDitagih, '
                ."{$baru} AS Baru",
                [$menunggu, StatusPesananOnline::Siap->value, ...$ikatBaru],
            )
            ->toBase()
            ->first();

        return [
            'Menunggu' => (int) ($hitung->Menunggu ?? 0),
            'PerluDitagih' => (int) ($hitung->PerluDitagih ?? 0),
            'Baru' => (int) ($hitung->Baru ?? 0),
            'WaktuServer' => CarbonImmutable::now()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array{Pesanan: list<array<string, mixed>>, TanggalBisnis: string, MetodeUangMuka: array{Uuid: string, Nama: string}|null}
     */
    public function AmbilAktif(int $idOutlet): array
    {
        // BR-17.3 (v3.33): pesanan yang menunggu konfirmasi ikut dikirim supaya staf di kasir bisa menerima/menolaknya.
        $daftar = PesananOnline::query()->with('Detail')->where('IdOutlet', $idOutlet)
            ->whereIn('Status', [StatusPesananOnline::MenungguKonfirmasi->value, StatusPesananOnline::Dikonfirmasi->value, StatusPesananOnline::Diproses->value, StatusPesananOnline::Siap->value])
            ->whereNull('IdPenjualan')->orderBy('DibuatPada')->get();
        $tanggalBisnis = $this->tanggal->Hitung($idOutlet)->toDateString();
        $pelanggan = $this->pelanggan->AmbilPerId(array_values(array_filter($daftar->pluck('IdPelanggan')->all(), 'is_int')), $tanggalBisnis);
        $hasil = [];

        foreach ($daftar as $p) {
            $hasil[] = [
                'Uuid' => $p->Uuid, 'Nomor' => $p->Nomor, 'NamaPelanggan' => $p->NamaPelanggan,
                'JenisPemenuhan' => $p->JenisPemenuhan->value, 'MetodePembayaran' => $p->MetodePembayaran->value,
                'Status' => $p->Status->value, 'Subtotal' => $p->Subtotal, 'Ongkir' => $p->Ongkir, 'DiskonOngkir' => $p->DiskonOngkir, 'Total' => $p->Total,
                'Catatan' => $p->Catatan, 'DibuatPada' => $p->DibuatPada?->toIso8601ZuluString(),
                // F-17 bagian 4 (aditif): pesanan kios membawa nomor antrian (K001) dan pilihan makan di sini/bawa pulang.
                'Sumber' => $p->Sumber->value, 'JenisSantap' => $p->JenisSantap?->value,
                'NomorAntrian' => $p->NomorAntrian === null ? null : BuatPesananKios::LabelAntrian($p->NomorAntrian),
                'SudahDibayar' => $p->DibayarPada !== null, 'SisaUangMuka' => $p->AmbilSisaUangMuka()->KeString(),
                'Pelanggan' => $p->IdPelanggan === null ? null : ($pelanggan[$p->IdPelanggan] ?? null),
                // v3.46 (aditif): voucher checkout yang sudah dipesan untuk pesanan; kasir memuatnya tanpa memesan ulang.
                'Voucher' => $this->voucher->AmbilUntukPos($p->KodeVoucher),
                'Baris' => $p->Detail->map(fn (PesananOnlineDetail $d): array => [
                    'UuidProduk' => $d->UuidProduk, 'UuidProdukSatuan' => $d->UuidProdukSatuan,
                    'NamaProduk' => $d->NamaProduk, 'Jumlah' => $d->Jumlah, 'HargaSatuan' => $d->HargaSatuan,
                    'HargaPilihan' => $d->HargaPilihan, 'Pilihan' => $d->Pilihan ?? [], 'Catatan' => $d->Catatan,
                ])->values()->all(),
            ];
        }

        $metode = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::UangMuka->value)->first(['Uuid', 'Nama']);

        return [
            'Pesanan' => $hasil,
            // Acuan hitungan harian `Pelanggan.PemakaianPromo` (sama dengan `GET /pelanggan`).
            'TanggalBisnis' => $tanggalBisnis,
            'MetodeUangMuka' => $metode === null ? null : ['Uuid' => $metode->Uuid, 'Nama' => $metode->Nama],
        ];
    }
}
