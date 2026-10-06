# Rilis Google Play: Payoung POS & Payoung Owner

Semua bahan untuk mengisi Play Console dua aplikasi. Aset gambar ada di `Spesifikasi/Merek/PlayStore/`;
teks di bawah siap ditempel. Bagian bertanda **[isi pemilik]** adalah data yang tidak boleh ditebak agent.

| | Payoung POS | Payoung Owner |
|---|---|---|
| ID paket | `id.payoung.kasir` | `id.payoung.pemilik` |
| Versi saat ini (`pubspec.yaml`) | `1.0.0+1` | `1.0.0+1` |
| Kategori | Bisnis | Bisnis |
| Tag | Kasir, POS, Bisnis kecil | Bisnis, Analitik |
| Harga | Gratis (langganan dibayar di dashboard web, bukan lewat Play) | Gratis |

## 1. Aset grafis

Buat ulang kapan saja (setelah UI berubah):

```bash
cd Aplikasi/Kasir   && flutter test AlatSitus/FotoPlayStore_test.dart   # ±30 detik
cd Aplikasi/Pemilik && flutter test AlatSitus/FotoPlayStore_test.dart
python3 Spesifikasi/Merek/PlayStore/BuatAsetPlayStore.py
```

| Slot Play Console | Payoung POS (`Kasir/`) | Payoung Owner (`Pemilik/`) |
|---|---|---|
| Ikon aplikasi 512×512 (PNG 32-bit) | `Ikon512.png` | `Ikon512.png` |
| Grafis fitur 1024×500 | `GrafikFitur.png` | `GrafikFitur.png` |
| Screenshot ponsel (2–8, 1080×2160) | `Hp/01…03*.jpg` | `Hp/01…06*.jpg` |
| Screenshot tablet 7 inci (1920×1200) | `Tablet7/01…06*.jpg` | tidak perlu (aplikasi ponsel) |
| Screenshot tablet 10 inci (2560×1600) | `Tablet10/01…06*.jpg` | tidak perlu |

Urutan nama berkas = urutan unggah. Data di tangkapan layar adalah data demo (toko "Kopi Senja"), dirender dari
widget asli aplikasi, bukan mockup.

## 2. Teks listing: Payoung POS

**Nama aplikasi** (≤ 30): `Payoung POS - Aplikasi Kasir`

**Deskripsi singkat** (≤ 80):
`Kasir offline untuk kafe, resto, toko, salon & laundry. Stok & laporan otomatis.`

**Deskripsi lengkap** (≤ 4000):

```
Payoung POS adalah aplikasi kasir untuk usaha Indonesia: kafe, restoran, toko ritel, grosir, salon, laundry, bengkel, dan apotek. Tetap bisa jualan saat internet putus; semua transaksi tersinkron otomatis begitu online lagi.

JUAL CEPAT
• Katalog per kategori, pencarian, dan pindai barcode (kamera atau scanner USB/Bluetooth)
• Bayar tunai dua ketukan, QRIS dinamis, kartu, transfer, tempo, deposit pelanggan
• Bagi tagihan, simpan pesanan, diskon dengan PIN supervisor
• Mode meja untuk restoran, dapur (KDS), nomor antrian, dan pesanan bawa pulang/antar

STRUK & PERANGKAT
• Printer thermal Bluetooth, USB, LAN, serta printer bawaan Sunmi/iMin
• Struk digital lewat WhatsApp, laci uang, layar pelanggan

SHIFT & KAS YANG RAPI
• Buka dan tutup shift dengan hitung pecahan uang
• Kas masuk/keluar tercatat beserta foto bukti
• Ringkasan omzet, metode bayar, produk terlaris, dan penjualan per jam

RETUR & KONTROL
• Riwayat transaksi lengkap, void, retur, dan tukar barang dengan PIN penyetuju
• Hak akses per peran: kasir, supervisor, pelayan, salesman

TERHUBUNG KE DASHBOARD Payoung
Stok, jurnal akuntansi, laporan laba rugi, pelanggan & poin, promo, dan karyawan dikelola di dashboard web Payoung. Pemilik bisa memantau semuanya dari aplikasi Payoung Owner.

Aplikasi ini memerlukan akun usaha Payoung. Daftar di payoung.id, lalu aktifkan perangkat kasir dengan kode atau QR dari dashboard.
```

**Catatan rilis 1.0.0**:
`Rilis pertama Payoung POS: jual offline, QRIS, printer Bluetooth/USB/LAN, shift & kas, retur, mode meja.`

## 3. Teks listing: Payoung Owner

**Nama aplikasi** (≤ 30): `Payoung Owner - Pantau Toko`

**Deskripsi singkat** (≤ 80):
`Pantau omzet, shift, stok, dan karyawan semua outlet Payoung dari HP Anda.`

**Deskripsi lengkap**:

```
Payoung Owner adalah aplikasi pendamping untuk pemilik usaha yang memakai Payoung POS. Lihat kondisi toko kapan saja tanpa harus datang ke outlet.

• Omzet hari ini semua outlet, dibanding kemarin dan minggu lalu
• Laporan penjualan per jam, per outlet, dan produk terlaris
• Setujui diskon, void, kas keluar, dan penjualan tempo dari jauh
• Pantau shift yang sedang buka dan selisih kas saat tutup
• Kehadiran karyawan, komisi, dan target bulan berjalan
• Insight mingguan: tren penjualan dan saran restock
• Notifikasi penting langsung ke HP

Masuk dengan akun pemilik usaha Payoung yang sama dengan dashboard web.
```

**Catatan rilis 1.0.0**: `Rilis pertama Payoung Owner: dasbor omzet, laporan, persetujuan jarak jauh, shift, karyawan, insight.`

## 4. Detail kontak & kebijakan

| Isian | Nilai |
|---|---|
| Situs web | `https://payoung.id` |
| Email dukungan | **[isi pemilik]** (wajib, tampil publik) |
| Telepon / WhatsApp | **[isi pemilik]** (opsional) |
| Kebijakan privasi | `https://payoung.id/legal/kebijakan-privasi` (P-06; pastikan versi berlaku sudah diterbitkan di konsol) |

## 5. Akses aplikasi (untuk peninjau Google)

Kedua aplikasi butuh akun, jadi pilih "Semua atau sebagian fungsi dibatasi" dan siapkan **tenant demo khusus peninjau**
(jangan akun toko sungguhan):

- **Payoung POS**: buat perangkat kasir di tenant demo, tulis kode aktivasi + PIN kasir demo di instruksi.
  Kode aktivasi sekali pakai, jadi buat yang baru setiap kali kirim ulang ke peninjauan.
- **Payoung Owner**: email + kata sandi pemilik tenant demo, 2FA dimatikan untuk akun ini. Peninjau memakai email + kata sandi; tombol "Masuk dengan Google" (D-57) boleh tetap ada tetapi jangan jadi satu-satunya jalan masuk, dan cantumkan di instruksi bahwa masuk Google bersifat opsional (panduan: `Panduan/LoginGoogle.md`).

Kredensial hanya diisi di Play Console, **tidak** di repo atau dokumen ini.

## 6. Keamanan data (Data safety)

Jawaban umum kedua aplikasi:
- Mengumpulkan/membagikan data: **Ya** mengumpulkan; **Tidak** membagikan ke pihak ketiga (server Payoung adalah pemroses
  data milik toko; gerbang QRIS & WhatsApp dipanggil server atas nama toko, bukan oleh aplikasi).
- Dienkripsi saat transit: **Ya** (HTTPS). Basis data lokal kasir juga terenkripsi (K-7).
- Permintaan penghapusan data: **Ya**, lewat dukungan Payoung / pemilik toko (data milik tenant).
- Iklan: **Tidak ada**. SDK analitik/iklan pihak ketiga: **tidak ada**.

### Payoung POS

| Jenis data | Dikumpulkan | Tujuan | Wajib? |
|---|---|---|---|
| Nama (staf, pelanggan) | Ya | Fungsi aplikasi, manajemen akun | Wajib |
| Nomor telepon (pelanggan) | Ya | Fungsi aplikasi (struk WA, poin) | Opsional |
| Alamat (pelanggan grosir/salesman, antar) | Ya | Fungsi aplikasi | Opsional |
| Riwayat pembelian | Ya | Fungsi aplikasi, analitik toko | Wajib |
| Foto (swafoto absensi, bukti kas) | Ya | Fungsi aplikasi | Opsional |
| Lokasi persis | Ya (kunjungan salesman, absensi) | Fungsi aplikasi | Opsional |
| Log error / diagnostik | Ya (dikirim ke server Payoung, PII disaring) | Analitik, perbaikan | Wajib |
| ID perangkat (ID perangkat kasir Payoung) | Ya | Keamanan, fungsi | Wajib |

Informasi pembayaran pelanggan (kartu/rekening) **tidak** dikumpulkan; QRIS diproses gerbang pembayaran milik toko.

Izin yang dijelaskan saat diminta: Kamera (pindai barcode, swafoto absensi, foto bukti), Perangkat sekitar /
Bluetooth (printer & laci), Lokasi (kunjungan salesman, absensi).

### Payoung Owner

| Jenis data | Dikumpulkan | Tujuan | Wajib? |
|---|---|---|---|
| Email, nama | Ya | Manajemen akun | Wajib |
| Riwayat pembelian (agregat laporan toko) | Ya | Fungsi aplikasi | Wajib |
| ID perangkat (token push Firebase) | Ya | Notifikasi | Opsional |

Firebase Cloud Messaging hanya untuk notifikasi push; tidak ada analitik Firebase.

## 7. Rating konten & audiens

- Kuesioner IARC kategori **Utilitas, Produktivitas, Komunikasi, atau Lainnya**: semua jawaban "Tidak"
  (tanpa kekerasan, konten seksual, perjudian, interaksi pengguna, berbagi lokasi ke pengguna lain, pembelian digital).
  Hasil yang diharapkan: **Semua umur / PEGI 3**.
- Target usia: **18 tahun ke atas** (aplikasi bisnis). Tidak menarik bagi anak.
- Aplikasi berita: Tidak. Aplikasi pemerintah: Tidak. Fitur keuangan: **Tidak** (bukan pinjaman/dompet; hanya
  mencatat pembayaran toko).
- Iklan: Tidak.

## 8. Build & tanda tangan

1. Buat keystore unggah sekali, simpan di pengelola rahasia (bukan repo):
   `keytool -genkey -v -keystore payoung-unggah.jks -keyalg RSA -keysize 2048 -validity 10000 -alias payoung`
2. Isi `android/key.properties` (diabaikan Git) atau variabel `PAYOUNG_KEYSTORE_FILE`, `PAYOUNG_KEYSTORE_PASSWORD`,
   `PAYOUNG_KEY_ALIAS`, `PAYOUNG_KEY_PASSWORD`. Build rilis sengaja gagal bila kunci kosong.
3. Aktifkan **Play App Signing** (Google memegang kunci aplikasi; keystore di atas = kunci unggah).
4. Build AAB dengan alamat server produksi:
   ```bash
   cd Aplikasi/Kasir   && flutter build appbundle --release --dart-define=ALAMAT_SERVER=https://dashboard.payoung.id/
   cd Aplikasi/Pemilik && flutter build appbundle --release --dart-define=ALAMAT_SERVER=https://dashboard.payoung.id/
   ```
   Hasil: `build/app/outputs/bundle/release/app-release.aab`. Setiap unggahan wajib menaikkan angka build (`+N`).
5. Pemilik: siapkan `google-services.json` proyek Firebase produksi (tidak di repo) sebelum build.

## 9. Urutan rilis yang disarankan

1. **Pengujian internal** (≤ 100 penguji, tanpa peninjauan panjang): uji printer, QRIS, offline di 2–3 perangkat asli.
2. **Pengujian tertutup** 14 hari dengan ≥ 12 penguji. Ini syarat Google untuk akun developer pribadi baru;
   akun organisasi (D-U-N-S) tidak terkena.
3. **Produksi** bertahap 10% → 50% → 100% (P-10 rilis bertahap di konsol bisa dipakai bersamaan).
4. Setelah tayang, daftarkan versi di konsol P-10 (versi minimum & catatan rilis) agar kasir lama diminta memperbarui.

## Daftar periksa

- [ ] Email dukungan & kebijakan privasi terbit di `payoung.id/legal/kebijakan-privasi`
- [ ] Tenant demo + kode aktivasi + akun Owner untuk peninjau
- [x] Versi Pemilik dinaikkan ke 1.0.0, angka build naik tiap unggah
- [ ] Keystore unggah tersimpan aman + Play App Signing aktif
- [ ] AAB dibangun dengan `ALAMAT_SERVER` produksi
- [ ] Ikon, grafis fitur, screenshot ponsel & tablet diunggah
- [ ] Data safety, rating konten, audiens, iklan, akses aplikasi terisi
- [ ] Uji internal di perangkat asli (printer Bluetooth, kamera, QRIS, offline) lulus
