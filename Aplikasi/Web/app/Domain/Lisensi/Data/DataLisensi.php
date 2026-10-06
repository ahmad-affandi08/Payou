<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Data;

use App\Domain\Lisensi\Galat\LisensiTidakSah;

/**
 * Isi berkas lisensi yang ditandatangani Payoung (D-35). Lisensi berlaku selamanya (sekali beli), untuk satu usaha di
 * satu domain, dengan semua fitur. Yang dibatasi hanya jumlah outlet, perangkat per outlet, dan pengguna (null = tak
 * terbatas).
 *
 * Urutan kunci `KeArray()` adalah bentuk kanonik yang ditandatangani: jangan diubah urutannya (lisensi lama tidak
 * akan lolos verifikasi). Kolom baru hanya boleh ditambah di akhir dengan versi format baru.
 *
 * Format 2 (D-36) menambah `PembaruanSampai`: hak pakai tetap selamanya, tetapi berkas data master (tarif pajak &
 * hari libur) yang dibuat setelah tanggal itu ditolak dan rilis yang lebih baru diberi peringatan. Lisensi format 1
 * tetap sah dan tidak dibatasi masa pembaruannya.
 */
final readonly class DataLisensi
{
    public const VERSI_FORMAT = 2;

    /** @var list<int> */
    public const FORMAT_DIKENAL = [1, 2];

    public function __construct(
        public string $nomor,
        public string $namaPemegang,
        public string $domain,
        public ?int $batasOutlet,
        public ?int $batasPerangkatPerOutlet,
        public ?int $batasPengguna,
        public string $diterbitkanPada,
        public ?string $pembaruanSampai = null,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function DariArray(array $data, int $format = self::VERSI_FORMAT): self
    {
        $teks = static function (string $kunci) use ($data): string {
            $nilai = $data[$kunci] ?? null;

            if (! is_string($nilai) || trim($nilai) === '') {
                throw new LisensiTidakSah("Berkas lisensi rusak: {$kunci} kosong.");
            }

            return trim($nilai);
        };
        $batas = static function (string $kunci) use ($data): ?int {
            $nilai = $data[$kunci] ?? null;

            if ($nilai !== null && (! is_int($nilai) || $nilai < 1)) {
                throw new LisensiTidakSah("Berkas lisensi rusak: {$kunci} harus bilangan bulat ≥ 1 atau kosong.");
            }

            return $nilai;
        };

        $domain = strtolower($teks('Domain'));

        if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $domain) !== 1) {
            throw new LisensiTidakSah('Berkas lisensi rusak: Domain bukan nama host yang sah.');
        }

        $tanggal = $teks('DiterbitkanPada');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) !== 1) {
            throw new LisensiTidakSah('Berkas lisensi rusak: DiterbitkanPada harus berformat YYYY-MM-DD.');
        }

        $pembaruanSampai = null;

        if ($format >= 2) {
            $pembaruanSampai = $teks('PembaruanSampai');

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pembaruanSampai) !== 1 || $pembaruanSampai < $tanggal) {
                throw new LisensiTidakSah('Berkas lisensi rusak: PembaruanSampai harus berformat YYYY-MM-DD dan tidak sebelum tanggal terbit.');
            }
        } elseif (array_key_exists('PembaruanSampai', $data)) {
            throw new LisensiTidakSah('Berkas lisensi rusak: PembaruanSampai tidak dikenal di format 1.');
        }

        return new self(
            nomor: $teks('Nomor'),
            namaPemegang: $teks('NamaPemegang'),
            domain: $domain,
            batasOutlet: $batas('BatasOutlet'),
            batasPerangkatPerOutlet: $batas('BatasPerangkatPerOutlet'),
            batasPengguna: $batas('BatasPengguna'),
            diterbitkanPada: $tanggal,
            pembaruanSampai: $pembaruanSampai,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function KeArray(): array
    {
        $data = [
            'Nomor' => $this->nomor,
            'NamaPemegang' => $this->namaPemegang,
            'Domain' => $this->domain,
            'BatasOutlet' => $this->batasOutlet,
            'BatasPerangkatPerOutlet' => $this->batasPerangkatPerOutlet,
            'BatasPengguna' => $this->batasPengguna,
            'DiterbitkanPada' => $this->diterbitkanPada,
        ];

        if ($this->pembaruanSampai !== null) {
            $data['PembaruanSampai'] = $this->pembaruanSampai;
        }

        return $data;
    }

    /** Format berkas: 2 bila membawa masa pembaruan (D-36), 1 untuk lisensi lama tanpa batas pembaruan. */
    public function AmbilFormat(): int
    {
        return $this->pembaruanSampai === null ? 1 : 2;
    }

    /** Tanggal (YYYY-MM-DD) masih dalam masa pembaruan & dukungan lisensi ini (D-36). */
    public function CekDalamMasaPembaruan(string $tanggal): bool
    {
        return $this->pembaruanSampai === null || $tanggal <= $this->pembaruanSampai;
    }

    /**
     * Peringatan bila rilis yang terpasang terbit setelah masa pembaruan berakhir (D-36). Sengaja hanya peringatan:
     * memblokir setelah `migrate` rilis baru berjalan bisa membuat toko tidak bisa kembali ke rilis lama.
     */
    public function AmbilPeringatanRilis(?string $tanggalRilis): ?string
    {
        if ($tanggalRilis === null || $this->CekDalamMasaPembaruan($tanggalRilis)) {
            return null;
        }

        return "Rilis ini ({$tanggalRilis}) terbit setelah masa pembaruan lisensi {$this->nomor} berakhir ({$this->pembaruanSampai}). Perpanjang pemeliharaan ke Payoung atau pakai rilis sebelum tanggal itu.";
    }

    /** Host permintaan cocok dengan domain lisensi (tanpa memperhatikan huruf besar & port). */
    public function CekDomainCocok(string $host): bool
    {
        return strtolower($host) === $this->domain;
    }
}
