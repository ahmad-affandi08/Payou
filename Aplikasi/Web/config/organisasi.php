<?php

declare(strict_types=1);

/*
 * Setup organisasi tenant (F-02). Kunci PascalCase (D-05).
 */
return [
    // F-02 langkah 3: masa berlaku tautan undangan anggota.
    'JamBerlakuUndangan' => 72,

    // F-02b: kode aktivasi perangkat & PIN kasir (§20.2: kunci 5 menit setelah 5 kali gagal).
    // Bawaan 15 menit. `MENIT_BERLAKU_KODE_AKTIVASI` boleh dinaikkan sementara (maks 10080 = 7 hari) untuk peninjauan
    // Google Play, karena peninjau memakai kode berjam-jam sampai berhari-hari setelah dibuat. Kembalikan setelahnya.
    'MenitBerlakuKodeAktivasi' => is_numeric(env('MENIT_BERLAKU_KODE_AKTIVASI')) ? min(10080, max(1, (int) env('MENIT_BERLAKU_KODE_AKTIVASI'))) : 15,
    'PercobaanPinMaksimal' => 5,
    'MenitKunciPin' => 5,
];
