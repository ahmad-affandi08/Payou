<?php

declare(strict_types=1);

/*
 * Pendaftaran merchant pembayaran lewat DOKU Partner API (KYB). Foto KTP, swafoto, dan foto tempat usaha hanya
 * singgah di disk privat (terenkripsi) sampai terunggah ke DOKU, lalu dihapus; sisanya dibersihkan penyapu setelah
 * `JamBerkasKedaluwarsa`.
 */
return [
    'DiskBerkas' => 'local',
    'UkuranMaksimalFotoKb' => 5120,
    'JamBerkasKedaluwarsa' => 24,
    'KategoriUsahaBawaan' => 'RETAIL',
    // Batas tenant yang diperiksa penyapu status per putaran (jalur cadangan callback).
    'BatasPenyapuStatus' => 200,
];
