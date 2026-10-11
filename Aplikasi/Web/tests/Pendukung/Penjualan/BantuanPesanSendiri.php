<?php

declare(strict_types=1);

namespace Tests\Pendukung\Penjualan;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Katalog\Pilihan\Model\ProdukKelompokPilihan;
use App\Domain\Organisasi\Layanan\PenyediaTokenPesanSendiri;
use App\Domain\Organisasi\Model\Meja;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Tenant\Enum\JenisOverride;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use App\Domain\Tenant\Model\OverrideTenant;
use App\Domain\Tenant\Model\PaketFitur;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Str;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\TestCase;

/**
 * Prasyarat test F-17 Self-Order QR Meja: restoran `BantuanPesananTerbuka::SiapkanRestoran()` + fitur
 * `kanal.self-order` (override pengelola, karena add-on belum tersedia), sakelar outlet hidup, token QR meja 7 & 9,
 * kelompok pilihan "Level Gula" (wajib 1) & "Topping" (0–2) pada kopi. `Alamat` = URL publik meja 7.
 */
final class BantuanPesanSendiri
{
    /**
     * @return array<string, mixed>
     */
    public static function Siapkan(TestCase $tes, bool $aktifkanFitur = true): array
    {
        $k = BantuanPesananTerbuka::SiapkanRestoran($tes);

        if ($aktifkanFitur) {
            self::AktifkanFitur($k['Tenant']);
        }

        /** @var Outlet $outlet */
        $outlet = $k['Outlet'];
        $outlet->refresh()->forceFill(['PesanSendiriAktif' => true])->save();
        $penyedia = app(PenyediaTokenPesanSendiri::class);
        $token = $penyedia->Pastikan($k['Meja']);
        $token9 = $penyedia->Pastikan($k['Meja9']);
        $pilihan = self::PasangPilihan($k['Kopi']);
        $slug = $k['Tenant']->refresh()->Slug;

        return $k + $pilihan + [
            'Slug' => $slug,
            'TokenMeja' => $token,
            'TokenMeja9' => $token9,
            'Alamat' => "/{$slug}/meja/{$token}",
            'Alamat9' => "/{$slug}/meja/{$token9}",
        ];
    }

    /**
     * D-86: Pro ke atas sudah memuat `kanal.self-order` (paritas Majoo Advance). Test yang menguji perilaku "fitur di
     * luar paket" mencabutnya dari paket di database uji, seperti keadaan paket yang belum memuatnya.
     */
    public static function CabutFiturDariPaket(): void
    {
        PaketFitur::query()->where('KunciFitur', 'kanal.self-order')->delete();
    }

    public static function AktifkanFitur(Tenant $tenant): void
    {
        OverrideTenant::query()->create([
            'IdTenant' => $tenant->Id,
            'Jenis' => JenisOverride::Fitur,
            'Kunci' => PemeriksaFiturTenant::KUNCI_PESAN_SENDIRI,
            'BerakhirPada' => now()->addDays(30),
            'Alasan' => 'Uji coba self-order QR untuk restoran',
            'DibuatOleh' => BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin)->Id,
        ]);
        BantuanOrganisasi::AturKonteks($tenant->Id);
    }

    /**
     * @return array{Gula: KelompokPilihan, GulaNormal: Pilihan, GulaSedikit: Pilihan, Topping: KelompokPilihan, Boba: Pilihan, Keju: Pilihan, Jeli: Pilihan}
     */
    public static function PasangPilihan(Produk $produk): array
    {
        $gula = KelompokPilihan::query()->create(['Nama' => 'Level Gula', 'MinimalPilih' => 1, 'MaksimalPilih' => 1, 'Urutan' => 1]);
        $normal = Pilihan::query()->create(['IdKelompokPilihan' => $gula->Id, 'Nama' => 'Normal', 'Harga' => '0', 'Urutan' => 1]);
        $sedikit = Pilihan::query()->create(['IdKelompokPilihan' => $gula->Id, 'Nama' => 'Sedikit gula', 'Harga' => '0', 'Urutan' => 2]);
        $topping = KelompokPilihan::query()->create(['Nama' => 'Topping', 'MinimalPilih' => 0, 'MaksimalPilih' => 2, 'Urutan' => 2]);
        $boba = Pilihan::query()->create(['IdKelompokPilihan' => $topping->Id, 'Nama' => 'Boba brown sugar', 'Harga' => '5000', 'Urutan' => 1]);
        $keju = Pilihan::query()->create(['IdKelompokPilihan' => $topping->Id, 'Nama' => 'Keju cheddar', 'Harga' => '6000', 'Urutan' => 2]);
        $jeli = Pilihan::query()->create(['IdKelompokPilihan' => $topping->Id, 'Nama' => 'Jeli kopi', 'Harga' => '4000', 'Urutan' => 3]);
        ProdukKelompokPilihan::query()->create(['IdProduk' => $produk->Id, 'IdKelompokPilihan' => $gula->Id, 'Urutan' => 1]);
        ProdukKelompokPilihan::query()->create(['IdProduk' => $produk->Id, 'IdKelompokPilihan' => $topping->Id, 'Urutan' => 2]);

        return ['Gula' => $gula, 'GulaNormal' => $normal, 'GulaSedikit' => $sedikit, 'Topping' => $topping, 'Boba' => $boba, 'Keju' => $keju, 'Jeli' => $jeli];
    }

    /**
     * Kiriman `POST .../pesan`. Baris: `[Produk, Jumlah, list<Pilihan>, Catatan?]`.
     *
     * @param  list<array{0: Produk, 1: int, 2?: list<Pilihan>, 3?: string|null, 4?: Produk}>  $baris
     * @return array<string, mixed>
     */
    public static function Kiriman(array $baris, ?string $uuid = null, ?string $nama = 'Bu Ratna', ?string $catatan = null): array
    {
        return [
            'Uuid' => $uuid ?? (string) Str::ulid(),
            'NamaPemesan' => $nama,
            'Catatan' => $catatan,
            'Baris' => array_map(fn (array $b): array => [
                'Uuid' => (string) Str::ulid(),
                'UuidProduk' => $b[0]->Uuid,
                'Jumlah' => $b[1],
                'Pilihan' => array_map(fn (Pilihan $p): string => $p->Uuid, $b[2] ?? []),
                'Catatan' => $b[3] ?? null,
                // PRD v2.06: anak varian pilihan tamu (indeks 4) bila produk induk varian.
                ...(isset($b[4]) ? ['UuidVarian' => $b[4]->Uuid] : []),
            ], $baris),
        ];
    }

    /** Meja baru di outlet dengan token QR (untuk batas pesanan per meja). */
    public static function BuatMeja(Outlet $outlet, string $nama): Meja
    {
        $meja = Meja::query()->create(['IdOutlet' => $outlet->Id, 'Nama' => $nama, 'Kapasitas' => 2]);
        app(PenyediaTokenPesanSendiri::class)->Pastikan($meja);

        return $meja;
    }
}
