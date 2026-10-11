<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 21. Paket Langganan & Monetisasi

> Harga mengikuti D-86 (v5.22): ≈ 40–55% dari paket Majoo yang setara untuk 1 outlet, dan satu tagihan per akun (Majoo per outlet). Sumber kebenaran harga di server adalah `database/Data/KatalogPaket.json` & `KatalogAddon.json`, diterapkan ke server berjalan dengan `php artisan katalog:terapkan`.

| Paket | Harga/bulan per paket (tahunan diskon ±20%) | Untuk | Batas & fitur utama |
|---|---|---|---|
| **Gratis** | Rp 0 | Usaha mikro coba-coba | 1 outlet, 1 perangkat, 2 pengguna, 100 SKU, 100 MB, POS retail/quick, laporan dasar, offline, watermark struk |
| **Starter** | Rp 99.000 (tahunan Rp 950.400) | UMKM 1 outlet | 1 outlet, 2 perangkat, 5 pengguna, SKU tak terbatas, 1 GB, stok & belanja stok, pelanggan, laporan lengkap, L/R sederhana |
| **Pro** | Rp 249.000 (tahunan Rp 2.390.400) | Usaha berkembang | 3 outlet, 5 perangkat/outlet, 20 pengguna, 100 pesan WA/bulan, 5 GB, mode table/service, KDS, PO & supplier, opname, promo engine, loyalti, karyawan & komisi, akuntansi penuh |
| **Bisnis** | Rp 449.000 (tahunan Rp 4.310.400) | Multi-outlet | 10 outlet, perangkat tak terbatas, 100 pengguna, 500 pesan WA/bulan, 20 GB, multi-gudang, transfer, approval jarak jauh, anti-fraud, price list, piutang/grosir, API & webhook, 2FA wajib |
| **Enterprise** | Negosiasi | Chain/franchise | Tanpa batas, franchise & royalti, database terdedikasi (VPS), SLA, onboarding khusus |

**Add-on (per bulan, D-86):** outlet tambahan Rp 79.000, perangkat tambahan Rp 29.000, kuota WhatsApp 1.000 pesan Rp 49.000 (angka ini menunggu tarif penyedia WhatsApp; wajib ≥ 2× biaya per pesan), self-order QR Rp 49.000, toko online Rp 79.000, forecast & insight Rp 49.000; jasa sekali bayar: migrasi data berbantuan Rp 500.000–1.500.000 (sesuai jumlah produk) dan pelatihan on-site Rp 750.000 per hari + transport. Harga paket berlaku **per paket** (D-11); outlet/perangkat/kuota di atas batas paket dibeli sebagai add-on. Add-on & kupon dikelola Keuangan tanpa persetujuan kedua (harga add-on berlaku untuk tagihan berikutnya).

**Pendapatan lain:** margin MDR payment gateway (sesuai perjanjian dengan PJP), penjualan bundel hardware (opsional, via mitra), program reseller/agen daerah.
