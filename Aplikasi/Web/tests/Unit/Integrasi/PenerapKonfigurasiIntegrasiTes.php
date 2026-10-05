<?php

declare(strict_types=1);

use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Layanan\PenerapKonfigurasiIntegrasi;

/*
 * Penjaga P-05: `PenerapKonfigurasiIntegrasi::Terapkan()` memakai `match` tanpa `default` atas `JenisIntegrasi`,
 * supaya jenis integrasi baru gagal keras alih-alih diam-diam tidak pernah aktif.
 *
 * Bug yang ditutup test ini nyata: v2.69 menambah `JenisIntegrasi::Push` tanpa arm di penerap. Efeknya tidak
 * terlihat sampai seseorang benar-benar mengaktifkan integrasi Push di konsol — sejak saat itu `UnhandledMatchError`
 * dilempar di SETIAP request, karena penerap dijalankan di boot aplikasi. Test statis ini menangkapnya tanpa perlu
 * membuat konfigurasi aktif lebih dulu.
 */

it('setiap jenis integrasi punya arm di penerap konfigurasi', function (): void {
    $kode = file_get_contents((new ReflectionClass(PenerapKonfigurasiIntegrasi::class))->getFileName());
    $badan = substr($kode, (int) strpos($kode, 'public function Terapkan'));
    $badan = substr($badan, 0, (int) strpos($badan, "\n    }"));

    $tidakDitangani = array_values(array_filter(
        JenisIntegrasi::cases(),
        fn (JenisIntegrasi $jenis): bool => ! str_contains($badan, "JenisIntegrasi::{$jenis->name}"),
    ));

    expect(array_map(fn (JenisIntegrasi $j): string => $j->value, $tidakDitangani))->toBe(
        [],
        'Jenis integrasi baru wajib diterbitkan di PenerapKonfigurasiIntegrasi::Terapkan(), '
        .'kalau tidak `match` tanpa default akan melempar UnhandledMatchError di setiap request begitu integrasinya diaktifkan.'
    );
});

it('jenis yang dibaca lewat config integrasi memakai kunci sesuai nilainya', function (): void {
    // Push & gerbang billing dibaca layanan pemakainya sebagai config('integrasi.Push') / ('integrasi.GerbangBilling').
    foreach ([JenisIntegrasi::Whatsapp, JenisIntegrasi::Push, JenisIntegrasi::GerbangBilling, JenisIntegrasi::LoginSosial] as $jenis) {
        expect($jenis->value)->toBe($jenis->name);
    }
});
