# Payoung

POS SaaS multi-sektor untuk usaha di Indonesia: retail, F&B, jasa, dan lainnya. Satu sistem untuk kasir (online & offline), stok, pembelian, akuntansi otomatis, pajak, dan laporan, lengkap dengan Platform Pengelola untuk tim internal.

> Sumber kebenaran produk adalah [`PRD.md`](PRD.md). Potongannya (flow, bagian PRD, keputusan) ada di [`Dokumen/`](Dokumen/Indeks.md) dan **dibuat otomatis** dari `PRD.md`. Aturan kerja pengembang & AI agent ada di [`CLAUDE.md`](CLAUDE.md) dan [`.claude/rules/`](.claude/rules).

---

## Daftar isi

1. [Gambaran produk](#gambaran-produk)
2. [Arsitektur & struktur repo](#arsitektur--struktur-repo)
3. [Teknologi](#teknologi)
4. [Menjalankan secara lokal (langkah demi langkah)](#menjalankan-secara-lokal-langkah-demi-langkah)
5. [Pengujian & pengecekan kualitas](#pengujian--pengecekan-kualitas)
6. [Konvensi wajib](#konvensi-wajib)
7. [Alur kerja pengembangan](#alur-kerja-pengembangan)
8. [Status pengembangan](#status-pengembangan)
9. [Dokumentasi lanjutan](#dokumentasi-lanjutan)

---

## Gambaran produk

| Aplikasi | Pengguna | Platform |
|---|---|---|
| **Back-office web** (`/kelola`) | Pemilik, admin, manajer outlet, akuntan, staf gudang | Peramban, responsif 360px s.d. layar lebar |
| **Aplikasi Kasir (POS)** | Kasir, supervisor | Android, iOS/iPadOS, Windows. Offline-first, minimal 72 jam tanpa internet |
| **Aplikasi Pemilik** | Pemilik usaha | Android, iOS |
| **Platform Pengelola** (`pengelola.`) | Tim internal Payoung | Peramban, akun & guard terpisah |
| **Web publik** | Calon pelanggan, pembeli (self-order/toko online, fase berikutnya) | Peramban |

Kemampuan utama:

- **Penjualan & pembayaran**: keranjang, harga bertingkat & daftar harga, pilihan/modifier, diskon manual dengan persetujuan PIN, pajak inklusif/eksklusif (PPN DPP 11/12, PB1/PBJT), biaya layanan, pembulatan tunai, split payment (tunai, QRIS statis, EDC, transfer, e-wallet).
- **Pasca-penjualan**: void di shift yang sama, retur dengan refund, daftar void & retur untuk anti-fraud.
- **Shift & kas**: buka/tutup shift, kas masuk/keluar/setoran, tutup buta, rekonsiliasi selisih, laporan X/Z.
- **Persediaan**: ledger `MutasiStok` append-only, HPP rata-rata bergerak/FIFO, batch & nomor seri, stok awal & impor, transfer, opname, penyesuaian.
- **Pembelian**: pemasok, PO dengan persetujuan, penerimaan barang, faktur & 3-way matching, hutang & pembayaran, retur pembelian.
- **Akuntansi otomatis**: setiap peristiwa keuangan menghasilkan jurnal seimbang di transaksi yang sama; bagan akun, pemetaan akun, kas & bank, buku besar, neraca saldo, laba rugi.
- **Laporan**: dashboard pemilik, laporan penjualan per dimensi, pajak, stok.
- **Platform Pengelola**: tim internal, master regulasi (tarif pajak bertanggal, wilayah, bank), template sektor, paket & fitur langganan, integrasi platform, dokumen legal, siklus hidup tenant, tagihan, tiket dukungan, monitoring.

---

## Arsitektur & struktur repo

Repo ini adalah **monorepo**: satu repositori berisi server web, dua aplikasi Flutter, paket kode bersama, dan dokumen produk.

### Fungsi setiap folder di akar repo

| Folder / berkas | Isinya | Perlu disentuh saat menjalankan lokal? |
|---|---|---|
| `Aplikasi/Web/` | **Server utama** (Laravel 13). Berisi API untuk aplikasi kasir, back-office web `/kelola` untuk pemilik & staf, Platform Pengelola untuk tim internal, dan database migration. | **Ya.** Ini yang pertama dijalankan. |
| `Aplikasi/Kasir/` | **Aplikasi kasir (POS)** berbasis Flutter untuk Android, iOS/iPad, dan Windows. Bisa bekerja offline dan menyinkronkan data ke server. | Ya, bila ingin mencoba kasir. |
| `Aplikasi/Pemilik/` | **Aplikasi pemilik usaha** (Flutter, Android & iOS). Masih tahap awal. | Opsional. |
| `Paket/` | **Kode Dart bersama** yang dipakai aplikasi Kasir & Pemilik. Tidak dijalankan sendiri. | Tidak (otomatis ikut). |
| `Paket/Inti/` | Tipe nilai uang & jumlah (tanpa float) dan pembuat ID unik (ULID). | Tidak |
| `Paket/KlienApi/` | Kode untuk memanggil API server dari aplikasi. | Tidak |
| `Paket/MesinKasir/` | Mesin hitung harga, pajak, diskon, biaya layanan, dan pembulatan (sama persis dengan versi PHP di server). | Tidak |
| `Paket/SistemDesain/` | Warna, ukuran, dan komponen tampilan bersama (ubin produk, baris keranjang, papan angka). | Tidak |
| `Spesifikasi/` | Data uji bersama: contoh perhitungan kasir (`VektorUjiKalkulasi/`) dan PIN (`VektorUjiPin/`) yang wajib dihasilkan sama oleh PHP dan Dart, plus aset merek Payoung (`Merek/`). | Tidak |
| `PRD.md` | **Dokumen kebutuhan produk** lengkap. Sumber kebenaran untuk semua fitur. | Tidak (dibaca saja) |
| `Dokumen/` | Potongan `PRD.md` per bagian & per flow agar mudah dibaca. **Dibuat otomatis**, jangan diedit langsung. | Tidak |
| `Alat/` | Skrip Python penjaga kualitas: `CekKonvensi.py` (cek penamaan & pola terlarang) dan `PecahPrd.py` (memecah PRD ke `Dokumen/`). | Tidak (dipakai saat pengecekan) |
| `CLAUDE.md` | Aturan kerja wajib untuk pengembang dan AI agent. | Tidak |
| `.claude/` | Konfigurasi AI agent: aturan per stack (`rules/`), skill (`skills/`), hook penjaga (`hooks/`), agen peninjau (`agents/`). | Tidak |
| `.github/` | CI GitHub Actions (`workflows/CekKepatuhan.yml`) dan template PR. | Tidak |
| `pubspec.yaml`, `pubspec.lock`, `analysis_options.yaml` | Pengaturan **ruang kerja Dart** untuk semua aplikasi & paket Flutter sekaligus (satu lockfile di akar) dan aturan analisis kode. | Tidak |

### Isi `Aplikasi/Web/` (server)

| Folder | Isinya |
|---|---|
| `app/Domain/` | **Logika bisnis**, dipisah per domain: `Penjualan`, `Kasir` (shift & kas), `Persediaan` (stok), `Akuntansi`, `Katalog` (produk & harga), `Pajak`, `Organisasi` (outlet, pengguna, perangkat), `Pembelian`, `Laporan`, `Tenant`, `Pengelola` (Platform Pengelola), dll. Tiap domain punya `Aksi/` (mengubah data), `Kueri/` (membaca data), `Layanan/`, `Model/`, `Data/`, `Enum/`. |
| `app/Http/` | Kontroler, validasi permintaan, dan middleware untuk halaman web & API. |
| `app/Console/` | Perintah artisan proyek (misal `pengelola:buat-super-admin`). |
| `routes/` | Daftar URL: `web.php` + berkas per modul (`Penjualan.php`, `Akuntansi.php`, …), `Pos.php` (API kasir), `console.php` (jadwal tugas). |
| `database/migrations/` | Struktur tabel database. `database/seeders/` berisi data awal (peran internal, pajak, satuan, paket, template sektor). |
| `resources/js/` | **Tampilan back-office** (React + TypeScript): `Halaman/` (satu berkas per layar), `Komponen/` (termasuk `TabelData`, `Tanggal`, `Formulir`, `Ui` dari shadcn), `TataLetak/` (menu & kerangka), `Pustaka/`, `Tipe/`. |
| `tests/` | Test PHP (Pest): `Fitur/`, `Unit/`, `Arsitektur/`, dan pembantu test di `Pendukung/`. |
| `config/`, `bootstrap/`, `public/`, `storage/`, `lang/` | Folder standar Laravel (konfigurasi, bootstrap, aset publik, berkas unggahan & log, terjemahan). |

### Isi `Aplikasi/Kasir/` (aplikasi kasir)

| Folder | Isinya |
|---|---|
| `lib/Tampilan/` | Layar-layar aplikasi. `RuangKerja/` adalah bingkai utama (bilah atas, navigasi, area kerja). |
| `lib/Domain/` | Logika: keranjang & penjualan, shift, sinkron, PIN, katalog. |
| `lib/Data/` | Database lokal SQLite (Drift) & repositori; semua transaksi disimpan bersama antrean kirim (outbox). |
| `lib/Aplikasi/` | Penyedia state (Riverpod) dan pengaturan lingkungan (alamat server). |
| `lib/UtamaDev.dart`, `UtamaStaging.dart`, `UtamaProduksi.dart` | Titik mulai aplikasi per lingkungan. |
| `test/` | Test unit, widget, dan migrasi database lokal. |

### Prinsip arsitektur

- **Domain-driven, modular monolith.** Kode server ada di `Aplikasi/Web/app/Domain/{Domain}` (Penjualan, Kasir, Persediaan, Akuntansi, Katalog, Pajak, Organisasi, Pembelian, Laporan, Pengelola, …) dengan lapisan `Aksi`, `Kueri`, `Layanan`, `Model`, `Data`, `Enum`, `Peristiwa`. Antar-domain hanya lewat Aksi/Kueri/Layanan publik atau peristiwa, dan dijaga oleh test arsitektur.
- **Multi-tenant.** Setiap tabel tenant memakai scope `MilikTenant`; lintas tenant hanya di `Domain/Pengelola`.
- **Stok & jurnal adalah turunan peristiwa.** Ditulis hanya lewat `CatatMutasiStok` dan `PostingJurnal` di transaksi DB yang sama dengan dokumennya. Efek non-kritis (ringkasan laporan, notifikasi) lewat antrean.
- **Offline-first POS.** Dokumen dibuat di perangkat (ULID sebagai `UuidKlien`, nomor dokumen berkode perangkat), disimpan bersama entri outbox dalam satu transaksi SQLite, lalu dikirim ke `POST /api/pos/v1/sinkron/kirim` secara FIFO dan idempoten.
- **Satu algoritma, dua implementasi.** Kalkulasi penjualan ada di PHP (`App\Domain\Penjualan\Kalkulasi`) dan Dart (`Paket/MesinKasir`), dan keduanya wajib lolos test vector yang sama.

---

## Teknologi

| Lapisan | Teknologi |
|---|---|
| Server | PHP 8.3, Laravel 13, MySQL 8, Sanctum (token perangkat), antrean Laravel |
| Back-office | Inertia.js + React 19 + TypeScript strict, Tailwind CSS 4, shadcn/ui, TanStack Query & TanStack Table (`TabelData`), Vite |
| Aplikasi mobile/desktop | Flutter & Dart 3.13+, Riverpod, Drift (SQLite), paket `decimal` |
| Kualitas | Pest (termasuk `arch()`), Larastan/PHPStan, Pint, Vitest, ESLint, Prettier, `flutter analyze`, melos |
| Tipografi & merek | Atkinson Hyperlegible Next (UI) & Mono, palet Payoung lewat token desain |

---

## Menjalankan secara lokal (langkah demi langkah)

Ikuti urutannya. Bagian A wajib; bagian B dan C hanya bila ingin mencoba aplikasi kasir atau Platform Pengelola.

### Prasyarat

| Perangkat lunak | Versi | Untuk |
|---|---|---|
| PHP + Composer | PHP 8.3, Composer 2 | Server |
| MySQL | 8.x | Database |
| Node.js + npm | 22 | Tampilan back-office |
| Flutter | SDK Dart ≥ 3.13 | Aplikasi kasir (bagian B) |
| Git, Python 3 | apa saja yang baru | Kode & skrip pengecekan |

Ekstensi PHP yang dibutuhkan Laravel: `bcmath`, `ctype`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd` atau `imagick`.

### A. Server & back-office web

**1. Ambil kode**

```bash
git clone https://github.com/ahmad-affandi08/Payou.git
cd Payou
```

**2. Buat database MySQL**

Masuk ke MySQL sebagai root lalu jalankan (ganti `sandi_anda` dengan sandi pilihan Anda):

```sql
CREATE DATABASE PosSaas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pos_saas'@'localhost' IDENTIFIED BY 'sandi_anda';
GRANT ALL PRIVILEGES ON PosSaas.* TO 'pos_saas'@'localhost';
FLUSH PRIVILEGES;
```

**3. Pasang dependensi & atur lingkungan**

```bash
cd Aplikasi/Web
composer install
cp .env.example .env
php artisan key:generate
```

Buka berkas `.env` dengan editor dan isi sandi database di baris `DB_PASSWORD=` (nama database `PosSaas` dan user `pos_saas` sudah menjadi nilai bawaan). Berkas `.env` tidak pernah di-commit.

**4. Buat tabel & data awal**

```bash
php artisan migrate --seed
```

Ini membuat semua tabel lalu mengisi data awal: peran tim internal, jenis & draf tarif pajak, satuan standar, paket langganan, dan tiga template sektor.

**5. Pasang dependensi tampilan**

```bash
npm install
```

**6. Jalankan**

```bash
composer dev
```

Satu perintah ini menjalankan empat proses sekaligus: server Laravel di `http://localhost:8000`, pekerja antrean, penampil log, dan Vite (tampilan web dengan muat ulang otomatis). Biarkan terminal ini tetap terbuka.

**7. Buat akun usaha (tenant) pertama**

1. Buka `http://localhost:8000/daftar` dan isi formulir pendaftaran. CAPTCHA dilewati otomatis di lingkungan lokal.
2. Email tidak benar-benar dikirim (`MAIL_MAILER=log`). Tautan verifikasi email ada di berkas log `Aplikasi/Web/storage/logs/laravel.log`; salin tautan itu ke peramban.
3. Masuk di `http://localhost:8000/masuk`. Anda akan diarahkan ke panduan awal, lalu ke back-office `http://localhost:8000/kelola`.

### B. Aplikasi kasir (opsional)

**1. Pasang dependensi Dart** (dari akar repo, sekali saja)

```bash
cd /path/ke/Payou
dart pub get
```

**2. Siapkan data di back-office** (`/kelola`): pastikan ada produk (menu Produk) dan metode pembayaran, lalu buat perangkat di menu **Perangkat** untuk mendapatkan **kode aktivasi**. Atur PIN kasir di menu **PIN**.

**3. Jalankan aplikasi**, arahkan ke server lokal lewat `ALAMAT_SERVER`:

```bash
cd Aplikasi/Kasir

# Emulator Android (alamat bawaan emulator ke komputer Anda, tidak perlu ALAMAT_SERVER):
flutter run -t lib/UtamaDev.dart

# Windows desktop / simulator iOS di komputer yang sama:
flutter run -t lib/UtamaDev.dart --dart-define=ALAMAT_SERVER=http://127.0.0.1:8000

# HP fisik di Wi-Fi yang sama (ganti dengan IP komputer Anda, dan jalankan server dengan
# `php artisan serve --host=0.0.0.0` agar bisa diakses dari jaringan):
flutter run -t lib/UtamaDev.dart --dart-define=ALAMAT_SERVER=http://192.168.1.10:8000
```

**4. Di aplikasi:** masukkan kode aktivasi → pilih kasir & masukkan PIN → buka shift → mulai berjualan di layar Jual.

### C. Platform Pengelola (opsional, untuk tim internal)

```bash
cd Aplikasi/Web
php artisan pengelola:buat-super-admin
```

Isi nama, email, dan kata sandi (minimal 12 karakter, huruf & angka), lalu buka `http://pengelola.localhost:8000`. Peramban modern mengarahkan `*.localhost` ke komputer Anda sendiri, jadi tidak perlu mengubah berkas hosts. Saat masuk pertama kali, Anda diminta mengaktifkan verifikasi dua langkah (siapkan aplikasi authenticator seperti Google Authenticator).

### Masalah yang sering muncul

| Gejala | Penyebab & solusi |
|---|---|
| `SQLSTATE[HY000] [1045] Access denied` | Sandi di `.env` (`DB_PASSWORD`) tidak sama dengan sandi user MySQL. |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL belum berjalan. Nyalakan layanan MySQL. |
| Halaman tampil tanpa gaya / kosong | Vite belum jalan. Pastikan `composer dev` masih berjalan, atau jalankan `npm run dev`. |
| Tautan verifikasi email tidak ada | Lihat bagian paling bawah `storage/logs/laravel.log`. |
| Laporan/dasbor belum terisi setelah berjualan | Pekerja antrean belum jalan. `composer dev` sudah menjalankannya; bila menjalankan manual, pakai `php artisan queue:listen`. |
| Aplikasi kasir tidak bisa terhubung | Periksa `ALAMAT_SERVER`, dan untuk HP fisik jalankan server dengan `--host=0.0.0.0`. |

### Perintah yang berguna

```bash
php artisan migrate:status                 # cek migrasi yang sudah jalan
php artisan pengelola:buat-super-admin     # buat akun Platform Pengelola
php artisan persediaan:bangun-ulang-saldo  # bangun ulang cache saldo stok dari ledger
php artisan laporan:bangun-ulang-ringkasan # bangun ulang ringkasan penjualan harian
php artisan schedule:work                  # jalankan tugas terjadwal secara lokal
```

---

## Pengujian & pengecekan kualitas

Semua pengecekan di bawah juga dijalankan CI (`.github/workflows/CekKepatuhan.yml`) dan harus hijau sebelum merge.

| Cakupan | Perintah (dari folder terkait) |
|---|---|
| Konvensi penamaan & pola terlarang | `python3 Alat/CekKonvensi.py --berubah` (atau `--semua`) dari akar |
| `Dokumen/` sinkron dengan `PRD.md` | `python3 Alat/PecahPrd.py --cek` dari akar |
| PHP: format & analisis statis | `composer analisis` (Pint + PHPStan) di `Aplikasi/Web` |
| PHP: test (Unit, Fitur di MySQL, Arsitektur) | `composer tes:cepat` di `Aplikasi/Web` |
| Frontend web | `npm run periksa` (tsc, ESLint, Prettier, Vitest) di `Aplikasi/Web` |
| Flutter & paket Dart | `melos run periksa` dari akar (format, analyze, test) |

Hal yang dijaga test:

- **Invariant keuangan & stok**: Σ debit = Σ kredit, `SaldoStok` = Σ `MutasiStok`, nilai persediaan = saldo akun persediaan.
- **Test vector kalkulasi** di `Spesifikasi/VektorUjiKalkulasi/` dijalankan oleh PHP dan Dart.
- **Isolasi tenant, izin, batas outlet, dan idempotensi** sinkron POS.
- **Test arsitektur**: batas antar-domain dan larangan pola tertentu.
- **UI**: semua tabel lewat `TabelData`, tanpa `<select>` bawaan, dan tanpa isian tanggal bawaan peramban.

---

## Konvensi wajib

Ringkasan dari [`CLAUDE.md`](CLAUDE.md); detailnya ada di PRD §13.7 dan `.claude/rules/`.

- **Bahasa Indonesia + PascalCase** untuk tabel, kolom, folder, file, class, dan function. Function diawali kata kerja (`HitungTotal`, `SimpanPenjualan`). Variabel lokal camelCase. PK `Id`, FK `Id{Tabel}`, waktu `DibuatPada`/`DiubahPada`/`DihapusPada`.
- **URL** huruf kecil kebab-case Indonesia (`/api/pos/v1/sinkron/kirim`). Key JSON API = nama kolom (PascalCase). Permission bergaya `penjualan.diskon.manual`.
- Istilah mengikuti kamus PRD §13.7.1 (misal **Pemasok**, bukan Supplier).
- **Uang & kuantitas tidak pernah float/double**: PHP `Uang`/`Kuantitas` (brick/math), Dart `decimal`, DB `DECIMAL`.
- **Dokumen terposting tidak diedit/dihapus**; koreksi lewat dokumen pembalik (void, retur, penyesuaian).
- **Tarif pajak tidak pernah di-hard-code**; selalu dari `TarifPajak` bertanggal berlaku.
- **Mutasi dari POS idempoten** (`UuidKlien` + `Idempotency-Key`).
- **Migrasi yang sudah di-merge tidak diubah**; buat migrasi baru.
- **UI**: token desain (tanpa hex lepas, gradien, efek kaca, atau emoji), `TabelData` untuk semua tabel, komponen `Komponen/Tanggal` & `PilihanCari`, microcopy Indonesia, responsif 360/768/1280px. Aplikasi POS berada di bingkai **Ruang Kerja Kasir**.
- **Dilarang** melemahkan/men-skip test, lint, atau CI; `git push --force`; `--no-verify`; serta membaca atau menulis rahasia.

---

## Alur kerja pengembangan

1. Setiap tugas terikat ke **ID flow** (`P-xx` Platform Pengelola, `F-xx` tenant) dan aturan bisnisnya (`BR-xx`). Mulai dengan `/mulai-flow <ID>` untuk membaca flow terkait di `Dokumen/Flow/`.
2. Kerjakan sekecil mungkin sesuai cakupan, ikuti urutan dependensi flow (PRD §7).
3. Setiap perubahan perilaku disertai test; flow keuangan/stok wajib invariant test.
4. Sebelum menyatakan selesai, jalankan `/cek-dod` (semua pengecekan di atas).
5. Perubahan PRD dilakukan di `PRD.md` lalu `python3 Alat/PecahPrd.py` untuk memperbarui `Dokumen/`. Keputusan dicatat di riwayat versi & tabel keputusan (D-xx).
6. PR memakai template `.github/pull_request_template.md` dan menyebut Flow, BR, dan D-xx.

---

## Status pengembangan

**Fase 1 inti selesai** (PRD §22). Yang sudah dibangun:

| Area | Flow |
|---|---|
| Platform Pengelola | P-01 s.d. P-09 & P-11 (dasar) |
| Registrasi, autentikasi, organisasi | F-00, F-01 (panduan awal & template sektor), F-02 (outlet, peran, perangkat, PIN) |
| Katalog, harga & pajak | F-03 (termasuk impor/ekspor) |
| Persediaan | F-05a (stok awal, ledger & HPP, jurnal inti) |
| Kasir | F-06 (shift & kas), F-07 (penjualan: mesin kalkulasi PHP & Dart, sinkron, layar Jual & Bayar), F-08 fase 1 (pembayaran), F-09 fase 1 (void & retur), F-11 (tutup shift & rekonsiliasi) |
| Keuangan & laporan | F-13a (bagan akun, pemetaan, kas & bank, buku besar, neraca saldo, laba rugi), F-14a (dashboard, laporan penjualan/pajak/stok) |
| Fondasi UI | `TabelData` (TanStack), pemilih tanggal & pilihan ber-cari, bingkai Ruang Kerja Kasir |
| Pembelian & persediaan lanjutan | F-04 fase 1 (pemasok, PO, penerimaan, faktur, hutang, retur), F-05b (transfer, opname, penyesuaian) |

Berikutnya: audit tampilan responsif, utang teknis PRD §25, lalu flow fase 2 (mode meja & KDS, CRM/promo, piutang, karyawan). Daftar utang teknis & pertanyaan terbuka ada di PRD §25.

---

## Dokumentasi lanjutan

- [`PRD.md`](PRD.md): dokumen lengkap (baca per bagian lewat `Dokumen/Indeks.md`)
- [`Dokumen/Flow/`](Dokumen/Flow): spesifikasi per flow bisnis
- [`Dokumen/Keputusan.md`](Dokumen/Keputusan.md): keputusan produk (D-xx)
- [`CLAUDE.md`](CLAUDE.md) & [`.claude/rules/`](.claude/rules): aturan kerja per stack
- `Aplikasi/Kasir/README.md`: catatan aplikasi kasir
