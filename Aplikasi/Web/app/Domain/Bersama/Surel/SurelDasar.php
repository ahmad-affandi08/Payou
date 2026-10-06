<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Surel;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Induk semua email Payoung (D-26).
 *
 * Setiap email dikirim **dua bagian**: HTML bermerek (`Surel.Html.{Grup}.{Nama}`) untuk klien biasa, dan teks
 * biasa (`Surel.{Grup}.{Nama}`) sebagai cadangan untuk klien yang memblokir HTML. Bagian teks juga menjaga
 * reputasi pengiriman: email HTML tanpa pasangan teks lebih sering dinilai spam.
 *
 * Keduanya dipasang lewat satu method supaya tidak bisa terpisah. Sebelumnya tiap mailable memanggil
 * `->text()` sendiri, dan itulah celah yang membuat badan email tidak pernah diuji.
 */
abstract class SurelDasar extends Mailable
{
    /**
     * Pasang badan HTML dan teks sekaligus dari satu nama templat.
     *
     * @param  string  $templat  Nama templat tanpa awalan, misal `Tenant.VerifikasiEmail`.
     * @param  array<string, mixed>  $data
     */
    protected function IsiSurel(string $templat, array $data = []): static
    {
        return $this->view($this->PastikanTemplatAda('Surel.Html.'.$templat), $data)
            ->text($this->PastikanTemplatAda('Surel.'.$templat), $data);
    }

    /**
     * Pastikan nama templat yang disusun benar-benar ada, lalu kembalikan sebagai nama view.
     *
     * Nama kedua badan disusun dari satu argumen supaya HTML dan teks tidak bisa terpisah. Konsekuensinya
     * Larastan tidak bisa lagi memeriksanya: tipe `view-string` hanya berlaku untuk nama view yang literal,
     * bukan hasil penggabungan string. Pemeriksaan yang hilang itu dikembalikan di sini — templat yang tidak
     * ada gagal saat mailable dibuat, bukan diam-diam saat email dirender di antrean — dan `SurelTes`
     * menjaga hal yang sama di tingkat test untuk seluruh mailable sekaligus.
     *
     * @return view-string
     */
    private function PastikanTemplatAda(string $nama): string
    {
        if (! View::exists($nama)) {
            throw new InvalidArgumentException("Templat email [{$nama}] tidak ada.");
        }

        /** @var view-string $terperiksa */
        $terperiksa = $nama;

        return $terperiksa;
    }
}
