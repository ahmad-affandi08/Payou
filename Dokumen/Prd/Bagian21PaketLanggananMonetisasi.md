<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 21. Paket Langganan & Monetisasi

> Harga mengikuti D-86 (v5.22): strategi peluncuran **volume**. Harga yang dibayar pelanggan saat peluncuran sudah ≈ 40% lebih murah dari harga normal dan ≈ 70% lebih murah dari paket Majoo yang setara (per outlet, harga resmi majoo.id/harga; Majoo menagih per outlet, Payoung per akun). Sumber kebenaran harga di server adalah `database/Data/KatalogPaket.json` & `KatalogAddon.json`, diterapkan ke server berjalan dengan `php artisan katalog:terapkan`.

| Paket | Harga peluncuran/bulan (tahunan −20%) | Harga normal/bulan | Untuk | Batas & fitur utama |
|---|---|---|---|---|
| **Gratis** | Rp 0 | Rp 0 | Usaha mikro coba-coba | 1 outlet, 1 perangkat, 2 pengguna, 100 SKU, 100 MB, POS retail/quick, laporan dasar, offline, watermark struk |
| **Starter** | **Rp 59.000** (tahunan Rp 566.400) | Rp 99.000 (tahunan Rp 950.400) | UMKM 1 outlet | 1 outlet, 2 perangkat, 5 pengguna, SKU tak terbatas, 1 GB, stok & belanja stok, pelanggan, laporan lengkap, L/R sederhana |
| **Pro** | **Rp 149.000** (tahunan Rp 1.430.400) | Rp 249.000 (tahunan Rp 2.390.400) | Usaha berkembang | 3 outlet, 5 perangkat/outlet, 20 pengguna, 100 pesan WA/bulan, 5 GB, mode table/service, KDS, **self-order QR & toko online (sudah termasuk, setara Majoo Advance)**, PO & supplier, opname, promo engine, loyalti, deposit, karyawan & komisi, akuntansi penuh |
| **Bisnis** | **Rp 299.000** (tahunan Rp 2.870.400) | Rp 449.000 (tahunan Rp 4.310.400) | Multi-outlet | 10 outlet, perangkat tak terbatas, 100 pengguna, 500 pesan WA/bulan, 20 GB, multi-gudang, transfer, approval jarak jauh, anti-fraud, price list, piutang/grosir, API & webhook, 2FA wajib |
| **Enterprise** | Negosiasi | Negosiasi | Chain/franchise | Tanpa batas, franchise & royalti, database terdedikasi (VPS), SLA, onboarding khusus |

**Diskon peluncuran (D-86):** berlaku sampai 31 Januari 2027. Diskon itu **nyata, bukan harga coret kosong**: harga normal terbit otomatis 1 Februari 2027 sebagai versi harga terjadwal (`HargaPaket`), dan situs hanya menampilkan coretan bila versi normal itu memang sudah terjadwal (`PaketPublik`). Langganan yang dimulai sebelum 1 Februari 2027 (termasuk semua tenant yang sudah ada) **tetap di harga peluncuran selama langganannya berjalan** (grandfathering BR-P04.1, `TerapkanKePelangganLama` = false pada versi normal); pendaftar baru sesudahnya membayar harga normal.

**Setara Majoo (tolok ukur fitur, halaman harga resmi majoo.id):** Pro ⊇ Majoo Advance (meja gabung/pisah/pindah, deposit pelanggan, struk digital, e-menu QR & toko online, kupon, jurnal otomatis, owner app, hak akses karyawan); Bisnis ⊇ sebagian besar Majoo Prime (grosir, jasa/reservasi, batch & expired, nomor seri, absensi geolokasi + wajah, jadwal kerja, rekap gaji, KDS). Yang Majoo punya dan **belum** ada di Payoung (jangan diklaim): local server multi-perangkat tanpa internet (mode LAN, K-28 ditunda), integrasi API marketplace (GoFood/GrabFood/ShopeeFood; baru input manual per kanal), pembayaran gaji otomatis lewat bank, struk lewat SMS. Yang di Majoo add-on Rp 499.000 per modul (loyalty, WhatsApp, laporan keuangan/akuntansi, paket akuntansi Rp 1,7–5 juta/bulan) di Payoung sudah termasuk Pro.

**Add-on (per bulan, D-86):** outlet tambahan Rp 59.000, perangkat tambahan Rp 19.000, kuota WhatsApp 1.000 pesan Rp 49.000 (menunggu tarif penyedia WhatsApp; wajib ≥ 2× biaya per pesan), self-order QR Rp 39.000 dan toko online Rp 59.000 (untuk Starter; sudah termasuk di Pro ke atas), forecast & insight Rp 39.000; jasa sekali bayar: migrasi data berbantuan Rp 500.000–1.500.000 (sesuai jumlah produk) dan pelatihan on-site Rp 750.000 per hari + transport. Harga paket berlaku **per paket** (D-11); outlet/perangkat/kuota di atas batas paket dibeli sebagai add-on. Add-on & kupon dikelola Keuangan tanpa persetujuan kedua (harga add-on berlaku untuk tagihan berikutnya).

**Pendapatan lain:** margin MDR payment gateway (sesuai perjanjian dengan PJP), penjualan bundel hardware (opsional, via mitra), program reseller/agen daerah.
