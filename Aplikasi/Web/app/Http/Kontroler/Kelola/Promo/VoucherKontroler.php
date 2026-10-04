<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Promo;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Laporan\Layanan\PenulisCsvLaporan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Promo\Aksi\BuatVoucher;
use App\Domain\Promo\Aksi\UbahStatusVoucher;
use App\Domain\Promo\Aksi\UbahVoucherMassal;
use App\Domain\Promo\Enum\StatusVoucher;
use App\Domain\Promo\Kueri\DaftarPromo;
use App\Domain\Promo\Kueri\DaftarVoucher;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\Voucher;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Voucher promo back-office (F-16c bagian 2, `/kelola/promo/{promo}/voucher`): daftar (`TabelData` mode server), tambah
 * satu kode atau kode massal, nonaktifkan/aktifkan, dan ekspor CSV untuk dibagikan. Lihat: `pelanggan.lihat`; ubah &
 * ekspor: `pelanggan.kelola`. Tanggal kedaluwarsa diisi per hari di zona waktu tenant (inklusif, disimpan sebagai awal
 * hari berikutnya dalam UTC).
 */
final class VoucherKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, string $promo, DaftarVoucher $daftar): Response|JsonResponse
    {
        $data = $this->CariPromo($promo);
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarVoucher::KOLOM_URUT, DaftarVoucher::URUT_BAWAAN, DaftarVoucher::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Promo/Voucher', 'Voucher', fn (): array => $daftar->AmbilTabel($data, $tabel), fn (): array => [
            'Promo' => [...DaftarPromo::Petakan($data), 'Definisi' => null],
            'Ringkasan' => $daftar->AmbilRingkasan($data),
            'JumlahMaksimal' => BuatVoucher::JUMLAH_MAKSIMAL,
            'Izin' => ['Kelola' => app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PelangganKelola)],
        ]);
    }

    public function Simpan(Request $permintaan, string $promo, BuatVoucher $buat): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Cara' => ['required', 'in:Satu,Massal'],
            'Kode' => ['required_if:Cara,Satu', 'nullable', 'string', 'max:30'],
            'Jumlah' => ['required_if:Cara,Massal', 'nullable', 'integer', 'min:1', 'max:'.BuatVoucher::JUMLAH_MAKSIMAL],
            'Awalan' => ['nullable', 'string', 'max:12'],
            'MaksimalPakai' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'TanggalKedaluwarsa' => ['nullable', 'date_format:Y-m-d'],
        ], attributes: [
            'Kode' => 'kode voucher',
            'Jumlah' => 'jumlah voucher',
            'MaksimalPakai' => 'batas pakai',
            'TanggalKedaluwarsa' => 'tanggal kedaluwarsa',
        ]);
        $satu = $valid['Cara'] === 'Satu';
        $zona = (string) app(ProfilTenant::class)->Ambil($this->IdTenant())['ZonaWaktu'];
        $tanggal = isset($valid['TanggalKedaluwarsa']) ? (string) $valid['TanggalKedaluwarsa'] : null;
        $kode = $buat->Jalankan(
            $this->CariPromo($promo),
            $satu ? (string) $valid['Kode'] : null,
            $satu ? 1 : (int) $valid['Jumlah'],
            isset($valid['Awalan']) ? (string) $valid['Awalan'] : null,
            isset($valid['MaksimalPakai']) ? (int) $valid['MaksimalPakai'] : null,
            $tanggal === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $tanggal, $zona)?->startOfDay()->addDay()->utc(),
            $this->Pelaku()->Id,
        );

        return back()->with('Kilat', $satu ? "Voucher {$kode[0]} ditambahkan." : count($kode).' voucher ditambahkan.');
    }

    public function Nonaktifkan(string $voucher, UbahStatusVoucher $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariVoucher($voucher), StatusVoucher::Nonaktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "Voucher {$hasil->Kode} dinonaktifkan.");
    }

    /** Aksi massal voucher terpilih satu promo: nonaktifkan atau aktifkan. */
    public function Massal(Request $permintaan, string $promo, UbahVoucherMassal $ubah): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Aksi' => ['required', 'string', Rule::in(UbahVoucherMassal::AKSI)],
            'Uuid' => ['required', 'array', 'min:1', 'max:'.UbahVoucherMassal::MAKS],
            'Uuid.*' => ['required', 'ulid'],
        ], attributes: ['Uuid' => 'voucher terpilih']);
        /** @var list<string> $uuid */
        $uuid = array_values($valid['Uuid']);
        $hasil = $ubah->Jalankan($this->CariPromo($promo), $valid['Aksi'], $uuid, $this->Pelaku()->Id);
        $kata = $valid['Aksi'] === 'Nonaktifkan' ? 'dinonaktifkan' : 'diaktifkan';
        $lewat = $hasil['Dilewati'] > 0 ? " {$hasil['Dilewati']} dilewati karena sudah berstatus itu." : '';

        return back()->with('Kilat', "{$hasil['Diubah']} voucher {$kata}.{$lewat}");
    }

    /** Nonaktifkan semua voucher aktif promo ini yang sudah lewat tanggal kedaluwarsanya. */
    public function NonaktifkanKedaluwarsa(string $promo, UbahVoucherMassal $ubah): RedirectResponse
    {
        $hasil = $ubah->NonaktifkanKedaluwarsa($this->CariPromo($promo), $this->Pelaku()->Id);

        return back()->with('Kilat', $hasil['Diubah'] === 0 ? 'Tidak ada voucher aktif yang sudah kedaluwarsa.' : "{$hasil['Diubah']} voucher kedaluwarsa dinonaktifkan.");
    }

    public function Aktifkan(string $voucher, UbahStatusVoucher $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariVoucher($voucher), StatusVoucher::Aktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "Voucher {$hasil->Kode} diaktifkan.");
    }

    public function Ekspor(string $promo, DaftarVoucher $daftar): StreamedResponse
    {
        $data = $this->CariPromo($promo);

        return PenulisCsvLaporan::Alirkan('voucher-'.strtolower($data->Kode), ['Kode', 'BatasPakai', 'Dipakai', 'KedaluwarsaPada', 'Status'], $daftar->AmbilUntukEkspor($data));
    }

    private function CariPromo(string $uuid): Promo
    {
        return Promo::query()->where('Uuid', $uuid)->firstOrFail();
    }

    private function CariVoucher(string $uuid): Voucher
    {
        return Voucher::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
