<?php

declare(strict_types=1);

/*
 * Pembagian domain produksi (D-20, PRD §14.1). Kunci konfigurasi PascalCase (D-05).
 *
 * - `Pemasaran`: situs pemasaran/landing page, misal `payoung.id`. Hanya melayani beranda, dokumen legal, dan daftar
 *   kompatibilitas perangkat; alamat lain diarahkan ke domain tenant dengan jalur yang sama.
 * - `Tenant`: aplikasi toko, misal `dashboard.payoung.id` (masuk/daftar, back-office `/kelola`, API POS & Pemilik,
 *   webhook, struk digital, pesan sendiri). `APP_URL` diisi alamat ini agar tautan email & antrean benar.
 * - Platform Pengelola memakai `PENGELOLA_DOMAIN` (config/pengelola.php), misal `consol.payoung.id`.
 *
 * Kosong (bawaan pengembangan & test) = semua bagian tenant dan pemasaran dilayani di satu host.
 */
return [
    'Pemasaran' => env('DOMAIN_PEMASARAN'),
    'Tenant' => env('DOMAIN_TENANT'),
];
