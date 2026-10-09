# Ilustrasi keadaan kosong Payoung

Ilustrasi datar untuk tampilan kosong back-office (keputusan D-18, PRD §17.6). Bukan ikon navigasi dan bukan aset logo.

- 10 subjek: Produk, Penjualan, Stok, Pembelian, Laporan, Akuntansi, Pelanggan, Promo, Outlet, Shift kasir.
- `*.png` di folder ini: sumber 1024×1024 transparan (untuk Flutter/materi lain). `Pratinjau.png`: lembar pratinjau.
- Versi SVG yang dipakai web: `Aplikasi/Web/resources/js/Aset/KeadaanKosong/`, dirender lewat `Komponen/Katalog/KeadaanKosong.tsx`.
- Lebar tampil yang disarankan 140–220 px CSS (di web: 192 px, 160 px di HP).
- Palet merek Payoung (D-61): #3B5B5D, #22383A, #1F3335, #F4A261, putih, dan sage pucat. Aset lama berpalet indigo diwarnai ulang oleh `Spesifikasi/Merek/WarnaiUlangRaster.py`.

## Set aplikasi Kasir (D-68 lanjutan)

Sembilan ilustrasi tambahan untuk aplikasi Kasir: Keranjang, Meja, Dapur, Sinkron, Kas, Cari, Kalender, Cucian, Servis.
Sumber vektor ada di `Sumber/*.svg`, dibuat oleh `BuatIlustrasiKasir.py` (gaya & palet sama dengan set di atas), dan
diekspor ke PNG 1024 px transparan dengan `RenderIlustrasi.cjs` (Chromium). Berkas yang dikirim aplikasi ada di
`Paket/SistemDesain/assets/ilustrasi/` (480 px), bersama Produk, Stok, Promo, dan Shift dari set 10 subjek di atas.
`KeadaanKosong` bentuk `ringkas` kini menampilkan ilustrasi kecil (96 px) bila `ilustrasi` diisi, ikon bila tidak.
