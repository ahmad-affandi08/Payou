<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Pencairan;
use App\Domain\Penjualan\Model\PencairanDetail;

/**
 * Detail satu pencairan (F-08, BR-08.4): header, pembayaran yang dicairkan, jurnal J-08.1, dan riwayat status.
 *
 * Akun tujuan & akun kliring dibaca lewat kueri publik domain Akuntansi (aturan #14), bukan dari tabel `Akun`.
 */
final class DetailPencairan
{
    public function __construct(
        private readonly PetaUuidOutlet $petaOutlet,
        private readonly DaftarAkunPilihan $akun,
        private readonly JurnalSumber $jurnal,
        private readonly DaftarAnggota $anggota,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Ambil(Pencairan $pencairan): array
    {
        $metode = MetodePembayaran::query()->whereKey($pencairan->IdMetodePembayaran)->first(['Id', 'Nama', 'Jenis', 'PersenBiaya', 'BiayaTetap']);
        $akun = $this->akun->AmbilBanyak(array_values(array_filter([$pencairan->IdAkunTujuan, $pencairan->IdAkunKliring])));
        $selisih = $pencairan->AmbilBiaya()->Kurangi(Uang::Dari($pencairan->BiayaDiharapkan));

        return [
            'Pencairan' => [
                'Uuid' => $pencairan->Uuid,
                'Nomor' => $pencairan->Nomor,
                'Tanggal' => $pencairan->Tanggal->format('Y-m-d'),
                'Status' => $pencairan->Status->value,
                'LabelStatus' => $pencairan->Status->AmbilLabel(),
                'NamaMetode' => $metode->Nama ?? '',
                'JenisMetode' => $metode?->Jenis->value ?? '',
                'KodeOutlet' => $this->petaOutlet->AmbilKode([$pencairan->IdOutlet])[$pencairan->IdOutlet] ?? '',
                'AkunTujuan' => self::Label($akun[$pencairan->IdAkunTujuan] ?? null),
                // Null = metode tanpa akun kliring sendiri; yang dikredit peran `PiutangPencairan` (lihat J-08.1).
                'AkunKliring' => $pencairan->IdAkunKliring === null ? null : self::Label($akun[$pencairan->IdAkunKliring] ?? null),
                'JumlahKotor' => $pencairan->JumlahKotor,
                'JumlahBersih' => $pencairan->JumlahBersih,
                'Biaya' => $pencairan->Biaya,
                'BiayaDiharapkan' => $pencairan->BiayaDiharapkan,
                'SelisihBiaya' => $selisih->KeString(),
                'Referensi' => $pencairan->Referensi,
                'Catatan' => $pencairan->Catatan,
                'AlasanBatal' => $pencairan->AlasanBatal,
            ],
            'Baris' => array_values(PencairanDetail::query()->where('IdPencairan', $pencairan->Id)->orderBy('Urutan')->get()->map(
                fn (PencairanDetail $d): array => [
                    'Urutan' => $d->Urutan,
                    'NomorPenjualan' => $d->NomorPenjualan,
                    'TanggalPenjualan' => $d->TanggalPenjualan->format('Y-m-d'),
                    'Jumlah' => $d->Jumlah,
                    'RefEksternal' => $d->RefEksternal,
                ],
            )->all()),
            'Jurnal' => $this->jurnal->Ambil(JenisSumberJurnal::Pencairan, $pencairan->Id),
            'Riwayat' => $this->Riwayat($pencairan->Id),
        ];
    }

    /**
     * @param  array{Id: int, Uuid: string, Kode: string, Nama: string, Jenis: string}|null  $akun
     */
    private static function Label(?array $akun): string
    {
        return $akun === null ? '' : "{$akun['Kode']} | {$akun['Nama']}";
    }

    /**
     * @return list<array{StatusKe: string, Oleh: string|null, Pada: string, Alasan: string|null}>
     */
    private function Riwayat(int $id): array
    {
        $riwayat = RiwayatStatusDokumen::query()
            ->where('JenisDokumen', Pencairan::JENIS_DOKUMEN)
            ->where('IdDokumen', $id)
            ->orderBy('Id')
            ->get();
        $idPengguna = array_values(array_unique(array_filter($riwayat->pluck('DiubahOleh')->all(), 'is_int')));
        $nama = $idPengguna === [] ? [] : $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), $idPengguna);

        return array_values($riwayat->map(fn (RiwayatStatusDokumen $r): array => [
            'StatusKe' => (string) $r->StatusKe,
            'Oleh' => $r->DiubahOleh === null ? null : ($nama[$r->DiubahOleh] ?? null),
            'Pada' => $r->DiubahPada?->toIso8601String() ?? '',
            'Alasan' => $r->Alasan,
        ])->all());
    }
}
