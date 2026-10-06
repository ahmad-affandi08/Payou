<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Email struk digital ke pelanggan (K3): ringkasan belanja + tautan `/s/{kodeStruk}`. Nama pengirim memakai nama
 * usaha tenant; alamat pengirim tetap alamat platform dari P-05 (domain yang terverifikasi SPF/DKIM).
 *
 * Sejak D-26 dikirim dua bagian (HTML + teks). **Tetap tanpa gambar dan tanpa piksel pelacak**: tata letak email
 * Payoung tidak memuat satu pun `<img>`, dijaga `tests/Arsitektur/SurelTes.php`.
 */
final class StrukBelanjaDigital extends SurelDasar
{
    /**
     * @param  list<array{Nama: string, Jumlah: string, Total: string}>  $baris
     */
    public function __construct(
        public readonly string $namaUsaha,
        public readonly ?string $namaOutlet,
        public readonly string $nomor,
        public readonly string $waktu,
        public readonly array $baris,
        public readonly string $total,
        public readonly bool $dibatalkan,
        public readonly string $tautan,
    ) {
        $alamat = config('mail.from.address');

        if (is_string($alamat) && $alamat !== '') {
            $this->from($alamat, $namaUsaha);
        }

        $this->subject("Struk belanja {$nomor} dari {$namaUsaha}")->IsiSurel('Tenant.StrukBelanjaDigital', [
            'NamaUsaha' => $namaUsaha,
            'NamaOutlet' => $namaOutlet,
            'Nomor' => $nomor,
            'Waktu' => $waktu,
            'Baris' => $baris,
            'Total' => $total,
            'Dibatalkan' => $dibatalkan,
            'Tautan' => $tautan,
        ]);
    }
}
