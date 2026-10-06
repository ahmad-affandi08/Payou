<?php

declare(strict_types=1);

/*
 * Edisi aplikasi & lisensi pasang sendiri (D-35, PRD §13.10). Kunci konfigurasi PascalCase (D-05).
 *
 * - `Edisi`: `Saas` (bawaan; payoung.id + dashboard + konsol) atau `Lisensi` (pembeli memasang dashboard di server &
 *   domainnya sendiri: tanpa Platform Pengelola, situs pemasaran, pendaftaran publik, maupun tagihan langganan).
 * - `KunciPublik`: kunci publik Ed25519 penerbit lisensi Payoung (base64), dibuat sekali dengan `lisensi:buat-kunci`.
 *   Sengaja ditulis di berkas ini, bukan `.env`: kunci publik bukan rahasia dan ikut dirilis bersama kode. Kunci
 *   privatnya TIDAK PERNAH masuk repo, server pembeli, maupun log; hanya dipakai `lisensi:terbitkan` di mesin Payoung.
 *
 * D-36 edisi terkunci: paket rilis Payoung Mandiri (CI) menulis `bootstrap/EdisiTerkunci.php` berisi `return 'Lisensi';`.
 * Berkas itu menang atas `.env`, jadi pembeli tidak bisa beralih ke edisi SaaS (konsol, tenant tanpa batas) hanya dengan
 * mengubah `EDISI`. Berkas penanda tidak pernah ada di repo (`.gitignore`); repo & rilis SaaS tetap membaca `.env`.
 */
$berkasTerkunci = __DIR__.'/../bootstrap/EdisiTerkunci.php';
// D-36: tanggal rilis (YYYY-MM-DD) yang ditulis paket Payoung Mandiri, dibandingkan dengan masa pembaruan lisensi.
$berkasTanggalRilis = __DIR__.'/../bootstrap/TanggalRilis.php';
$tanggalRilis = is_file($berkasTanggalRilis) ? require $berkasTanggalRilis : null;
$edisiTerkunci = is_file($berkasTerkunci) ? require $berkasTerkunci : null;

return [
    'Edisi' => is_string($edisiTerkunci) ? $edisiTerkunci : env('EDISI', 'Saas'),

    'EdisiTerkunci' => is_string($edisiTerkunci),

    'TanggalRilis' => is_string($tanggalRilis) ? $tanggalRilis : null,

    'KunciPublik' => '',
];
