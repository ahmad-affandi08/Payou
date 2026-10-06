<?php

declare(strict_types=1);

/*
 * D-21 Situs pemasaran (payoung.id) yang diatur dari konsol. Kunci konfigurasi PascalCase (D-05).
 */
return [
    // Disk penyimpanan gambar situs (bawaan `public`; bisa S3-compatible lewat .env).
    'Disk' => env('SITUS_DISK', 'public'),

    // Batas unggahan gambar (KB) & jenis yang diterima.
    'UkuranGambarMaksKb' => 3072,
    // SVG tidak diterima (bisa memuat skrip).
    'TipeGambar' => ['image/jpeg', 'image/png', 'image/webp'],

    // Pratinjau draf halaman dari konsol: tautan bertanda tangan berlaku sekian menit.
    'MenitPratinjau' => 30,

    // Editor visual: tautan pratinjau yang dibingkai di konsol berlaku lebih lama supaya tidak mati saat menyunting.
    'MenitPratinjauEditor' => 360,

    // Audit F-21: kunci HMAC sidik nomor/IP prospek, terpisah dari APP_KEY agar rotasi APP_KEY tidak mengacak sidik
    // (batas kiriman & pencarian). Kosong = memakai APP_KEY (perilaku lama).
    'KunciSidik' => env('SITUS_KUNCI_SIDIK'),
];
