<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Enum\HasilKunjungan;
use App\Domain\Penjualan\Model\KunjunganSales;
use App\Domain\Penjualan\Model\PesananGrosir;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daftar kunjungan salesman untuk `TabelData` back-office (Modul Salesman bagian 1, Grosir › Kunjungan). Dibatasi
 * outlet yang boleh diakses pelaku (null = semua). Cari: nama pelanggan (min. 3 huruf). Saring: `Salesman` (Uuid
 * pengguna, banyak), `Hasil` (banyak), `Tanggal` (rentang, tanggal kunjungan di zona waktu outlet). Urut: `MasukPada`.
 *
 * Nama pelanggan & salesman lewat kueri publik domain Pelanggan & Organisasi, bukan membaca tabelnya.
 */
final class DaftarKunjunganSales
{
    public const KOLOM_URUT = ['MasukPada'];

    public const KOLOM_SARING = ['Salesman', 'Hasil', 'Tanggal'];

    public const URUT_BAWAAN = '-MasukPada';

    /** Batas baris ekspor CSV (saringan yang sama dengan tabel). */
    public const MAKS_EKSPOR = 5000;

    public function __construct(
        private readonly IdentitasPelanggan $identitas,
        private readonly AnggotaOutlet $anggota,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Ambil(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        return PenerapKueriTabel::Terapkan($this->Saring($p, $idOutletBoleh), $p, ['MasukPada' => 'MasukPada'], fn (Collection $baris): array => $this->Petakan($baris));
    }

    /**
     * Semua baris sesuai saringan (maks. [MAKS_EKSPOR]), urut waktu masuk terbaru, untuk ekspor CSV.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array<string, mixed>>
     */
    public function AmbilSemua(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        return $this->Petakan($this->Saring($p, $idOutletBoleh)->orderByDesc('MasukPada')->orderByDesc('Id')->limit(self::MAKS_EKSPOR)->get());
    }

    /**
     * Salesman yang pernah tercatat berkunjung (opsi saring), urut nama.
     *
     * @return list<array{Nilai: string, Label: string}>
     */
    public function OpsiSalesman(): array
    {
        $opsi = array_values(array_map(fn (array $n): array => ['Nilai' => $n['Uuid'], 'Label' => $n['Nama']], $this->PetaSalesman()));
        usort($opsi, fn (array $a, array $b): int => strcasecmp($a['Label'], $b['Label']));

        return $opsi;
    }

    /**
     * @return list<array{Nilai: string, Label: string}>
     */
    public static function OpsiHasil(): array
    {
        return array_map(fn (HasilKunjungan $h): array => ['Nilai' => $h->value, 'Label' => $h->AmbilLabel()], HasilKunjungan::cases());
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return Builder<KunjunganSales>
     */
    private function Saring(DataPermintaanTabel $p, ?array $idOutletBoleh): Builder
    {
        $hasil = $p->AmbilDaftar('Hasil', array_map(fn (HasilKunjungan $h): string => $h->value, HasilKunjungan::cases()));
        $uuidSalesman = $p->AmbilDaftar('Salesman');
        $idSalesman = $uuidSalesman === [] ? [] : array_keys(array_filter($this->PetaSalesman(), fn (array $n): bool => in_array($n['Uuid'], $uuidSalesman, true)));
        ['Dari' => $dari, 'Sampai' => $sampai] = $p->AmbilRentangTanggal('Tanggal');
        $idPelanggan = $p->cari === '' ? null : $this->identitas->CariIdPos($p->cari);

        return KunjunganSales::query()
            ->when($idOutletBoleh !== null, fn ($q) => $q->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($uuidSalesman !== [], fn ($q) => $q->whereIn('IdPengguna', $idSalesman === [] ? [0] : $idSalesman))
            ->when($hasil !== [], fn ($q) => $q->whereIn('Hasil', $hasil))
            ->when($dari !== null, fn ($q) => $q->where('Tanggal', '>=', (string) $dari))
            ->when($sampai !== null, fn ($q) => $q->where('Tanggal', '<=', (string) $sampai))
            ->when($idPelanggan !== null, fn ($q) => $q->whereIn('IdPelanggan', $idPelanggan === [] ? [0] : $idPelanggan));
    }

    /**
     * @return array<int, array{Uuid: string, Nama: string}>
     */
    private function PetaSalesman(): array
    {
        return $this->anggota->AmbilNama(array_values(array_map('intval', KunjunganSales::query()->distinct()->pluck('IdPengguna')->all())));
    }

    /**
     * @param  Collection<int, KunjunganSales>  $baris
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $baris): array
    {
        $pelanggan = $this->identitas->AmbilNamaBanyak($baris->pluck('IdPelanggan')->all());
        $salesman = $this->anggota->AmbilNama(array_values(array_unique(array_map('intval', $baris->pluck('IdPengguna')->all()))));
        $pesanan = PesananGrosir::query()->whereIn('Id', $baris->pluck('IdPesananGrosir')->filter()->all())->get(['Id', 'Uuid', 'Nomor'])->keyBy('Id');

        return array_values($baris->map(fn (KunjunganSales $k): array => [
            'Uuid' => $k->Uuid,
            'Tanggal' => $k->Tanggal->format('Y-m-d'),
            'MasukPada' => $k->MasukPada->copy()->utc()->toIso8601ZuluString(),
            'KeluarPada' => $k->KeluarPada?->copy()->utc()->toIso8601ZuluString(),
            'DurasiMenit' => $k->KeluarPada === null ? null : (int) $k->MasukPada->diffInMinutes($k->KeluarPada),
            'NamaSalesman' => $salesman[$k->IdPengguna]['Nama'] ?? '',
            'NamaPelanggan' => $pelanggan[$k->IdPelanggan]['Nama'] ?? '',
            'Hasil' => $k->Hasil->value,
            'LabelHasil' => $k->Hasil->AmbilLabel(),
            'Catatan' => $k->Catatan,
            'UuidPesananGrosir' => $k->IdPesananGrosir === null ? null : $pesanan->get($k->IdPesananGrosir)?->Uuid,
            'NomorPesananGrosir' => $k->IdPesananGrosir === null ? null : $pesanan->get($k->IdPesananGrosir)?->Nomor,
            'Latitude' => $k->Latitude,
            'Longitude' => $k->Longitude,
            'AkurasiMeter' => $k->AkurasiMeter,
        ])->all());
    }
}
