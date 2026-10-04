---
paths:
  - "Aplikasi/**"
  - "Paket/**"
---

# Aturan aplikasi Flutter (PRD §17.2, §17.3, §18)

- Folder & file di `lib/` PascalCase (`Fitur/Keranjang/KalkulatorKeranjang.dart`). Nama paket di `pubspec.yaml` huruf kecil (`mesin_kasir`) karena syarat Dart. Method PascalCase, kecuali override Flutter (`build`, `initState`, `dispose`, ...).
- Arsitektur feature-first berlapis: Tampilan → Aplikasi → Domain → Data. State & DI dengan Riverpod.
- Uang & jumlah dengan paket `decimal` (value object `Uang`/`Kuantitas`). **Dilarang `double`** untuk uang/kuantitas.
- `Paket/MesinKasir` adalah Dart murni (tanpa import Flutter) dan wajib lolos test vector `Spesifikasi/VektorUjiKalkulasi/`.
- Aplikasi POS **offline-first**: simpan Penjualan + detail + pembayaran + entri Outbox dalam **satu transaksi SQLite (Drift)**. ID dibuat di perangkat (ULID) sebagai `UuidKlien`. Nomor dokumen memakai kode perangkat.
- Sinkron hanya lewat `/api/pos/v1` (device token). Migrasi skema Drift **tidak boleh** menghapus outbox yang belum terkirim.
- Rahasia di secure storage; DB lokal terenkripsi SQLCipher bila disyaratkan (§17.2.6). Jangan log PIN, token, atau data pelanggan.
- Font di-bundle (bukan paket `google_fonts`). Ukuran & warna dari token `SistemDesain`. Target sentuh ≥ 48dp.
- **Ruang Kerja Kasir (D-16, §17.2.7):** Aplikasi POS adalah ruang kerja untuk dipakai berjam-jam, elegan tetapi mudah. Semua layar setelah masuk dibungkus bingkai `Tampilan/RuangKerja/` (bilah atas, rel navigasi, area kerja, bilah status); layar fitur hanya mengisi area kerja. Tugas rutin dibuka sebagai panel/lembar di atas area kerja, bukan pindah halaman. Wajib: kunci cepat & otomatis, ganti kasir tanpa tutup shift, ukuran tampilan Normal/Besar, posisi keranjang kiri/kanan, pemindai tanpa fokus, pintasan keyboard di desktop. Komponen visual bersama (ubin produk, baris keranjang, papan angka, panel samping, bilah status) di `Paket/SistemDesain`.
- **Dua kolom kerja (D-40):** layar ruang kerja selain Jual memakai `IsiAreaKerja` dengan satu `SorotanAngka` (angka yang menjawab pertanyaan pertama kasir di layar itu), bagian kerja `BagianKerja`, dan `panelSamping` untuk konteks (data pendukung, daftar periksa, rincian item terpilih). Bagian kosong wajib `KeadaanKosong` (ikon + kalimat apa yang akan muncul), bukan satu baris teks abu-abu. Daftar yang punya rincian = master-detail di area kerja ≥ 960dp.
- Test widget layar ruang kerja di lebar 360, 800, dan 1280dp; golden test untuk layar Jual & Bayar.
- Hardware lewat abstraksi `Paket/AdaptorPerangkat` (`TransportPrinter`, `KemampuanPerangkat`). Kode fitur tidak memanggil SDK vendor langsung.
- Test: unit (MesinKasir, repositori, sinkron), widget/golden, integration (alur jual online & offline).
- File test berakhiran `_test.dart` (syarat `flutter test`), misal `test/Nilai/Uang_test.dart`. Pengecekan: `melos run periksa` dari akar repo (sekali: `dart pub global activate melos 7.8.2`).
