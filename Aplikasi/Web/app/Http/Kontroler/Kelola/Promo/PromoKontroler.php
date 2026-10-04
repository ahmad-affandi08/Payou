<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Promo;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\NamaProduk;
use App\Domain\Katalog\Kueri\PohonKategori;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pelanggan\Kueri\DaftarTierPelanggan;
use App\Domain\Pembelian\Kueri\DaftarPemasok;
use App\Domain\Penjualan\Enum\JenisAksiPromo;
use App\Domain\Penjualan\Enum\JenisKondisiPromo;
use App\Domain\Penjualan\Enum\JenisUlangTahunPromo;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Enum\ModeResolusiPromo;
use App\Domain\Penjualan\Enum\PeriodeBatasPelangganPromo;
use App\Domain\Penjualan\Kueri\DaftarMetodePembayaran;
use App\Domain\Promo\Aksi\SimpanPengaturanPromo;
use App\Domain\Promo\Aksi\SimpanPromo;
use App\Domain\Promo\Aksi\UbahPromoMassal;
use App\Domain\Promo\Aksi\UbahStatusPromo;
use App\Domain\Promo\Data\DataPromo;
use App\Domain\Promo\Enum\StatusPromo;
use App\Domain\Promo\Kueri\DaftarPromo;
use App\Domain\Promo\Kueri\EfektivitasPromo;
use App\Domain\Promo\Kueri\PromoBerlaku;
use App\Domain\Promo\Model\Promo;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Promo back-office (F-16c, `/kelola/promo`): daftar dengan ringkasan pemakaian, formulir tambah/ubah, arsip/pulihkan,
 * dan mode resolusi konflik. Lihat: `pelanggan.lihat`; ubah: `pelanggan.kelola`. Tanggal mulai/selesai diisi per hari
 * di zona waktu tenant (selesai inklusif, disimpan sebagai awal hari berikutnya dalam UTC).
 */
final class PromoKontroler extends DasarKelolaKontroler
{
    public function Daftar(DaftarPromo $daftar, PromoBerlaku $berlaku): Response
    {
        return Inertia::render('Kelola/Promo/Daftar', [
            'Promo' => array_map(fn (array $p): array => [...$p, 'Definisi' => null], $daftar->AmbilSemua()),
            'ModeResolusi' => $berlaku->AmbilMode()->value,
            'FiturAktif' => $berlaku->CekFiturAktif(),
            'Izin' => ['Kelola' => $this->CekKelola()],
        ]);
    }

    public function Buat(PromoBerlaku $berlaku): Response
    {
        return Inertia::render('Kelola/Promo/Formulir', [...$this->AmbilOpsi(), 'Promo' => null, 'FiturAktif' => $berlaku->CekFiturAktif()]);
    }

    /** F-16c bagian 4c: efektivitas promo (pakai, potongan, bagian pemasok, uplift vs periode sebelumnya). */
    public function Efektivitas(string $promo, EfektivitasPromo $efektivitas): Response
    {
        $data = $this->CariPromo($promo);

        return Inertia::render('Kelola/Promo/Efektivitas', [
            'Promo' => ['Uuid' => $data->Uuid, 'Kode' => $data->Kode, 'Nama' => $data->Nama, 'Status' => $data->Status->value],
            'Efektivitas' => $efektivitas->Hitung($data),
        ]);
    }

    public function Ubah(string $promo, NamaProduk $namaProduk, PromoBerlaku $berlaku): Response
    {
        $data = $this->CariPromo($promo);
        $zona = $this->AmbilZona();
        /** @var array{Kondisi?: array{Jenis?: string, Uuid?: list<string>}} $definisi */
        $definisi = $data->Definisi;
        $uuidKondisi = ($definisi['Kondisi']['Jenis'] ?? 'Semua') === JenisKondisiPromo::Produk->value ? ($definisi['Kondisi']['Uuid'] ?? []) : [];

        return Inertia::render('Kelola/Promo/Formulir', [
            ...$this->AmbilOpsi(),
            'Promo' => [
                ...DaftarPromo::Petakan($data),
                'TanggalMulai' => $data->MulaiPada === null ? null : CarbonImmutable::instance($data->MulaiPada)->setTimezone($zona)->toDateString(),
                'TanggalSelesai' => $data->SelesaiPada === null ? null : CarbonImmutable::instance($data->SelesaiPada)->setTimezone($zona)->subDay()->toDateString(),
                'NamaProduk' => $namaProduk->Ambil(array_values($uuidKondisi)),
                'UuidPemasok' => $data->IdPemasok === null ? null : (app(DaftarPemasok::class)->AmbilRingkas([$data->IdPemasok])[$data->IdPemasok]['Uuid'] ?? null),
                'PersenDanaPemasok' => (string) $data->PersenDanaPemasok,
            ],
            'FiturAktif' => $berlaku->CekFiturAktif(),
        ]);
    }

    public function Simpan(Request $permintaan, SimpanPromo $simpan): RedirectResponse
    {
        $promo = $simpan->Jalankan($this->AmbilData($permintaan, true));

        return to_route('kelola.promo.daftar')->with('Kilat', "Promo {$promo->Nama} ditambahkan.");
    }

    public function Perbarui(Request $permintaan, string $promo, SimpanPromo $simpan): RedirectResponse
    {
        $hasil = $simpan->Jalankan($this->AmbilData($permintaan, false), $this->CariPromo($promo));

        return to_route('kelola.promo.daftar')->with('Kilat', "Promo {$hasil->Nama} disimpan.");
    }

    /** Aksi massal promo terpilih: arsipkan atau aktifkan kembali. */
    public function Massal(Request $permintaan, UbahPromoMassal $ubah): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Aksi' => ['required', 'string', Rule::in(UbahPromoMassal::AKSI)],
            'Uuid' => ['required', 'array', 'min:1', 'max:'.UbahPromoMassal::MAKS],
            'Uuid.*' => ['required', 'ulid'],
        ], attributes: ['Uuid' => 'promo terpilih']);
        /** @var list<string> $uuid */
        $uuid = array_values($valid['Uuid']);
        $hasil = $ubah->Jalankan($valid['Aksi'], $uuid, $this->Pelaku()->Id);
        $kata = $valid['Aksi'] === 'Arsipkan' ? 'diarsipkan' : 'diaktifkan kembali';
        $lewat = $hasil['Dilewati'] > 0 ? " {$hasil['Dilewati']} dilewati karena sudah berstatus itu." : '';

        return back()->with('Kilat', "{$hasil['Diubah']} promo {$kata}.{$lewat}");
    }

    public function Arsipkan(string $promo, UbahStatusPromo $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariPromo($promo), StatusPromo::Diarsipkan, $this->Pelaku()->Id);

        return back()->with('Kilat', "Promo {$hasil->Nama} diarsipkan.");
    }

    public function Pulihkan(string $promo, UbahStatusPromo $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariPromo($promo), StatusPromo::Aktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "Promo {$hasil->Nama} dipulihkan.");
    }

    public function SimpanPengaturan(Request $permintaan, SimpanPengaturanPromo $simpan): RedirectResponse
    {
        $valid = $permintaan->validate(['ModeResolusi' => ['required', Rule::enum(ModeResolusiPromo::class)]]);
        $simpan->Jalankan(ModeResolusiPromo::from((string) $valid['ModeResolusi']), $this->Pelaku()->Id);

        return back()->with('Kilat', 'Pengaturan promo disimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function AmbilOpsi(): array
    {
        return [
            'OpsiOutlet' => array_map(fn (array $o): array => ['Nilai' => $o['Uuid'], 'Label' => $o['Nama']], app(PetaUuidOutlet::class)->AmbilRingkas(null, true)),
            'OpsiTier' => array_map(fn (array $t): array => ['Nilai' => $t['Nilai'], 'Label' => $t['Label']], app(DaftarTierPelanggan::class)->AmbilOpsi()),
            'OpsiKategori' => array_map(fn (array $k): array => ['Nilai' => $k['Uuid'], 'Label' => $k['Jalur']], app(PohonKategori::class)->AmbilOpsi()),
            'OpsiKanal' => array_map(fn (KanalPenjualan $k): array => ['Nilai' => $k->value, 'Label' => $k->AmbilLabel()], KanalPenjualan::cases()),
            // F-16c bagian 3.
            'OpsiMetodeBayar' => array_values(array_map(
                fn (array $m): array => ['Nilai' => $m['Uuid'], 'Label' => $m['Nama'].($m['Aktif'] ? '' : ' (nonaktif)')],
                app(DaftarMetodePembayaran::class)->Ambil(),
            )),
            'OpsiUlangTahun' => array_map(fn (JenisUlangTahunPromo $j): array => ['Nilai' => $j->value, 'Label' => $j->AmbilLabel()], JenisUlangTahunPromo::cases()),
            'OpsiPeriodeBatas' => array_map(fn (PeriodeBatasPelangganPromo $p): array => ['Nilai' => $p->value, 'Label' => $p->AmbilLabel()], PeriodeBatasPelangganPromo::cases()),
            // F-16c bagian 4b: pemasok yang ikut menanggung potongan promo.
            'OpsiPemasok' => array_map(fn (array $p): array => ['Nilai' => $p['Uuid'], 'Label' => $p['Nama']], app(DaftarPemasok::class)->AmbilPilihan()),
        ];
    }

    private function AmbilData(Request $permintaan, bool $baru): DataPromo
    {
        $uang = ['nullable', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'];
        $persen = ['nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'];
        $valid = $permintaan->validate([
            'Kode' => $baru ? ['required', 'string', 'max:30'] : ['nullable'],
            'Nama' => ['required', 'string', 'max:100'],
            'Prioritas' => ['nullable', 'integer', 'between:0,999'],
            'Eksklusif' => ['boolean'],
            'TanggalMulai' => ['nullable', 'date_format:Y-m-d'],
            'TanggalSelesai' => ['nullable', 'date_format:Y-m-d'],
            'Kuota' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'Hari' => ['array', 'max:7'],
            'Hari.*' => ['integer', 'between:1,7'],
            'JamMulai' => ['nullable', 'string', 'max:5'],
            'JamSelesai' => ['nullable', 'string', 'max:5'],
            'Outlet' => ['array', 'max:200'],
            'Outlet.*' => ['string', 'ulid'],
            'Kanal' => ['array', 'max:6'],
            'Kanal.*' => [Rule::enum(KanalPenjualan::class)],
            'Tier' => ['array', 'max:20'],
            'Tier.*' => ['string', 'max:30'],
            'MinimalSubtotal' => $uang,
            'JenisKondisi' => ['required', Rule::enum(JenisKondisiPromo::class)],
            'UuidKondisi' => ['array', 'max:200'],
            'UuidKondisi.*' => ['string', 'ulid'],
            'JumlahMinimal' => ['nullable', 'string', 'regex:/^\d{1,6}(\.\d{1,4})?$/'],
            'JenisAksi' => ['required', Rule::enum(JenisAksiPromo::class)],
            'Persen' => $persen,
            'Jumlah' => $uang,
            'Harga' => $uang,
            'Beli' => ['nullable', 'integer'],
            'Gratis' => ['nullable', 'integer'],
            'PersenGratis' => $persen,
            'Pengali' => ['nullable', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'UuidPemasok' => ['nullable', 'string', 'ulid'],
            'PersenDanaPemasok' => ['nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'BatasPerTransaksi' => ['nullable', 'integer'],
            'WajibVoucher' => ['boolean'],
            'MetodeBayar' => ['array', 'max:20'],
            'MetodeBayar.*' => ['string', 'ulid'],
            'UlangTahun' => ['nullable', Rule::enum(JenisUlangTahunPromo::class)],
            'HariUlangTahun' => ['nullable', 'integer'],
            'TransaksiPertama' => ['boolean'],
            'BatasPerPelanggan' => ['nullable', 'integer'],
            'PeriodeBatasPelanggan' => ['nullable', Rule::enum(PeriodeBatasPelangganPromo::class)],
        ], attributes: [
            'Nama' => 'nama promo',
            'TanggalMulai' => 'tanggal mulai',
            'TanggalSelesai' => 'tanggal selesai',
            'MinimalSubtotal' => 'minimal belanja',
            'JumlahMinimal' => 'jumlah minimal',
        ]);
        $zona = $this->AmbilZona();
        $teks = fn (string $kunci): ?string => isset($valid[$kunci]) && $valid[$kunci] !== '' ? (string) $valid[$kunci] : null;
        $bulat = fn (string $kunci): ?int => isset($valid[$kunci]) ? (int) $valid[$kunci] : null;
        /** @var list<string> $outlet */
        $outlet = array_values(array_map('strtoupper', (array) ($valid['Outlet'] ?? [])));
        /** @var list<string> $tier */
        $tier = array_values(array_map('strval', (array) ($valid['Tier'] ?? [])));
        /** @var list<string> $metodeBayar */
        $metodeBayar = array_values(array_map('strtoupper', (array) ($valid['MetodeBayar'] ?? [])));
        $metodeDikenal = array_column(app(DaftarMetodePembayaran::class)->Ambil(), 'Uuid');

        if (array_diff($metodeBayar, array_map('strtoupper', $metodeDikenal)) !== []) {
            throw ValidationException::withMessages(['MetodeBayar' => 'Pilih metode pembayaran dari daftar.']);
        }

        $idPemasok = null;

        if (($uuidPemasok = $teks('UuidPemasok')) !== null) {
            $idPemasok = app(DaftarPemasok::class)->AmbilIdDariUuid(strtoupper($uuidPemasok))
                ?? throw ValidationException::withMessages(['UuidPemasok' => 'Pilih pemasok dari daftar.']);
        }

        /** @var list<string> $uuidKondisi */
        $uuidKondisi = array_values(array_map('strtoupper', (array) ($valid['UuidKondisi'] ?? [])));

        return new DataPromo(
            kode: (string) ($valid['Kode'] ?? ''),
            nama: (string) $valid['Nama'],
            prioritas: (int) ($valid['Prioritas'] ?? 0),
            eksklusif: $permintaan->boolean('Eksklusif'),
            mulaiPada: ($t = $teks('TanggalMulai')) === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $t, $zona)?->startOfDay()->utc(),
            selesaiPada: ($t = $teks('TanggalSelesai')) === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $t, $zona)?->startOfDay()->addDay()->utc(),
            kuota: $bulat('Kuota'),
            hari: array_values(array_map('intval', (array) ($valid['Hari'] ?? []))),
            jamMulai: $teks('JamMulai'),
            jamSelesai: $teks('JamSelesai'),
            uuidOutlet: $outlet,
            kanal: array_values(array_map(fn ($k): KanalPenjualan => KanalPenjualan::from((string) $k), (array) ($valid['Kanal'] ?? []))),
            tier: $tier,
            minimalSubtotal: Uang::Dari($teks('MinimalSubtotal') ?? '0'),
            kondisi: JenisKondisiPromo::from((string) $valid['JenisKondisi']),
            uuidKondisi: $uuidKondisi,
            jumlahMinimal: Kuantitas::Dari($teks('JumlahMinimal') ?? '0'),
            aksi: JenisAksiPromo::from((string) $valid['JenisAksi']),
            persen: ($t = $teks('Persen')) === null ? null : BigDecimal::of($t),
            jumlah: ($t = $teks('Jumlah')) === null ? null : Uang::Dari($t),
            harga: ($t = $teks('Harga')) === null ? null : Uang::Dari($t),
            beli: $bulat('Beli'),
            gratis: $bulat('Gratis'),
            persenGratis: ($t = $teks('PersenGratis')) === null ? null : BigDecimal::of($t),
            batasPerTransaksi: $bulat('BatasPerTransaksi'),
            idPengguna: $this->Pelaku()->Id,
            wajibVoucher: $permintaan->boolean('WajibVoucher'),
            metodeBayar: $metodeBayar,
            ulangTahun: ($t = $teks('UlangTahun')) === null ? null : JenisUlangTahunPromo::from($t),
            hariUlangTahun: $bulat('HariUlangTahun') ?? 0,
            transaksiPertama: $permintaan->boolean('TransaksiPertama'),
            batasPerPelanggan: $bulat('BatasPerPelanggan'),
            periodeBatasPelanggan: PeriodeBatasPelangganPromo::tryFrom($teks('PeriodeBatasPelanggan') ?? '') ?? PeriodeBatasPelangganPromo::Hari,
            pengali: ($t = $teks('Pengali')) === null ? null : BigDecimal::of($t),
            idPemasok: $idPemasok,
            persenDanaPemasok: ($t = $teks('PersenDanaPemasok')) === null ? null : BigDecimal::of($t),
        );
    }

    private function AmbilZona(): string
    {
        return (string) app(ProfilTenant::class)->Ambil($this->IdTenant())['ZonaWaktu'];
    }

    private function CariPromo(string $uuid): Promo
    {
        return Promo::query()->where('Uuid', $uuid)->firstOrFail();
    }

    private function CekKelola(): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PelangganKelola);
    }
}
