<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Pencairan;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daftar pencairan untuk `TabelData` (F-08, BR-08.4). Cari: nomor dokumen & referensi setoran. Saring: `Status`,
 * `Metode` (Uuid), rentang `Tanggal`. Dibatasi outlet yang boleh diakses pelaku (null = semua).
 *
 * `SelisihBiaya` = `Biaya − BiayaDiharapkan`, dikirim supaya daftarnya bisa langsung menunjukkan setoran yang
 * potongannya menyimpang dari pengaturan metode — itulah baris yang perlu ditanyakan ke platform, dan alasan utama
 * daftar ini ada.
 */
final class DaftarPencairan
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'JumlahKotor', 'JumlahBersih', 'Biaya'];

    public const KOLOM_SARING = ['Status', 'Metode', 'Tanggal'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(private readonly PetaUuidOutlet $petaOutlet) {}

    /**
     * Rekap potongan per metode untuk pencairan **yang sedang disaring** (status, metode, rentang tanggal), hanya yang
     * `Diposting`. Inilah angka yang dibawa pemilik ke platform saat potongannya menyimpang: berapa yang diserahkan,
     * berapa yang dipotong, berapa yang seharusnya menurut kesepakatan, dan persen efektif yang sebenarnya terjadi.
     *
     * Pencairan yang dibatalkan tidak ikut — jurnalnya sudah dibalik, jadi memasukkannya akan menghitung potongan yang
     * tidak pernah terjadi.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array{Nama: string, Jumlah: int, JumlahKotor: string, Biaya: string, BiayaDiharapkan: string, Selisih: string, PersenEfektif: string}>
     */
    public function RekapPotongan(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $baris = $this->Saring($p, $idOutletBoleh)
            ->where('Status', StatusDokumenTerposting::Diposting->value)
            ->get(['IdMetodePembayaran', 'JumlahKotor', 'Biaya', 'BiayaDiharapkan']);

        if ($baris->isEmpty()) {
            return [];
        }

        $nama = MetodePembayaran::query()->whereIn('Id', $baris->pluck('IdMetodePembayaran')->all())->pluck('Nama', 'Id');
        $rekap = [];

        foreach ($baris as $satu) {
            $kunci = (int) $satu->IdMetodePembayaran;
            $rekap[$kunci] ??= ['Jumlah' => 0, 'Kotor' => Uang::Nol(), 'Biaya' => Uang::Nol(), 'Diharapkan' => Uang::Nol()];
            $rekap[$kunci]['Jumlah']++;
            $rekap[$kunci]['Kotor'] = $rekap[$kunci]['Kotor']->Tambah(Uang::Dari($satu->JumlahKotor));
            $rekap[$kunci]['Biaya'] = $rekap[$kunci]['Biaya']->Tambah(Uang::Dari($satu->Biaya));
            $rekap[$kunci]['Diharapkan'] = $rekap[$kunci]['Diharapkan']->Tambah(Uang::Dari($satu->BiayaDiharapkan));
        }

        $hasil = array_values(array_map(fn (array $r, int $id): array => [
            'Nama' => $nama->get($id) ?? '',
            'Jumlah' => $r['Jumlah'],
            'JumlahKotor' => $r['Kotor']->KeString(),
            'Biaya' => $r['Biaya']->KeString(),
            'BiayaDiharapkan' => $r['Diharapkan']->KeString(),
            'Selisih' => $r['Biaya']->Kurangi($r['Diharapkan'])->KeString(),
            'PersenEfektif' => self::PersenEfektif($r['Biaya'], $r['Kotor']),
        ], $rekap, array_keys($rekap)));

        // Nilai transaksi terbesar dulu. Dibandingkan sebagai Uang, bukan teks: sebagai teks "9000.00" akan dianggap
        // lebih besar daripada "150000.00".
        usort($hasil, fn (array $a, array $b): int => Uang::Dari($b['JumlahKotor'])->Bandingkan(Uang::Dari($a['JumlahKotor']))
            ?: strcmp($a['Nama'], $b['Nama']));

        return $hasil;
    }

    /**
     * Potongan sebagai persen dari nilai transaksi, 4 desimal. Tanpa float: pembagiannya lewat `BigDecimal`
     * (CLAUDE.md #7). Nol bila tidak ada nilai transaksi, karena persen dari nol tidak punya arti.
     */
    private static function PersenEfektif(Uang $biaya, Uang $kotor): string
    {
        if ($kotor->BernilaiNol()) {
            return '0.0000';
        }

        return (string) BigDecimal::of($biaya->KeString())->multipliedBy(100)->dividedBy(BigDecimal::of($kotor->KeString()), 4, RoundingMode::HalfUp);
    }

    /**
     * `Ringkasan` = rekap potongan per metode untuk **saringan yang sama**, dan itu sengaja ikut di muatan tabel alih-alih
     * dikirim sebagai prop halaman: prop halaman hanya dihitung saat kunjungan Inertia, sehingga rekapnya akan basi
     * begitu operator mengganti rentang tanggal lewat `TabelData` (yang memuat ulang JSON-nya saja).
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}, Ringkasan: list<array<string, mixed>>}
     */
    public function Ambil(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $kueri = $this->Saring($p, $idOutletBoleh);
        $urut = ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'JumlahKotor' => 'JumlahKotor', 'JumlahBersih' => 'JumlahBersih', 'Biaya' => 'Biaya'];

        $hasil = PenerapKueriTabel::Terapkan($kueri, $p, $urut, function (Collection $baris): array {
            $outlet = $this->petaOutlet->AmbilKode(array_values(array_unique(array_filter($baris->pluck('IdOutlet')->all(), 'is_int'))));
            $metode = MetodePembayaran::query()->whereIn('Id', $baris->pluck('IdMetodePembayaran')->all())->pluck('Nama', 'Id');

            return array_values($baris->map(fn (Pencairan $d): array => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Tanggal' => $d->Tanggal->format('Y-m-d'),
                'NamaMetode' => $metode->get($d->IdMetodePembayaran) ?? '',
                'KodeOutlet' => $outlet[$d->IdOutlet] ?? '',
                'Status' => $d->Status->value,
                'LabelStatus' => $d->Status->AmbilLabel(),
                'JumlahKotor' => $d->JumlahKotor,
                'JumlahBersih' => $d->JumlahBersih,
                'Biaya' => $d->Biaya,
                'BiayaDiharapkan' => $d->BiayaDiharapkan,
                'SelisihBiaya' => $d->AmbilBiaya()->Kurangi(Uang::Dari($d->BiayaDiharapkan))->KeString(),
                'Referensi' => $d->Referensi,
            ])->all());
        });

        return [...$hasil, 'Ringkasan' => $this->RekapPotongan($p, $idOutletBoleh)];
    }

    /**
     * Saringan bersama daftar & rekap: outlet boleh, status, metode (Uuid), rentang tanggal, dan cari nomor/referensi.
     * Satu tempat supaya rekap potongan tidak pernah menjumlahkan himpunan yang berbeda dari tabel di atasnya.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return Builder<Pencairan>
     */
    private function Saring(DataPermintaanTabel $p, ?array $idOutletBoleh): Builder
    {
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusDokumenTerposting $s): string => $s->value, StatusDokumenTerposting::cases()));
        $uuidMetode = $p->saring['Metode'] ?? null;
        $idMetode = $uuidMetode === null ? null : (MetodePembayaran::query()->where('Uuid', (string) $uuidMetode)->value('Id') ?? 0);
        ['Dari' => $dari, 'Sampai' => $sampai] = $p->AmbilRentangTanggal('Tanggal');
        $pola = PenerapKueriTabel::PolaCari($p->cari);

        return Pencairan::query()
            ->when($idOutletBoleh !== null, fn ($q) => $q->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($status !== [], fn ($q) => $q->whereIn('Status', $status))
            ->when($idMetode !== null, fn ($q) => $q->where('IdMetodePembayaran', $idMetode))
            ->when($dari !== null, fn ($q) => $q->where('Tanggal', '>=', (string) $dari))
            ->when($sampai !== null, fn ($q) => $q->where('Tanggal', '<=', (string) $sampai))
            ->when($p->cari !== '', fn ($q) => $q->where(fn ($dalam) => $dalam
                ->where('Nomor', 'like', $pola)
                ->orWhere('Referensi', 'like', $pola)));
    }
}
