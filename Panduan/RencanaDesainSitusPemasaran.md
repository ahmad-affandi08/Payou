# Rencana Desain Ulang Situs Pemasaran `payoung.id`

Status: **diterapkan** (D-25; disempurnakan D-39 v4.47 "bersih & meyakinkan" dengan tangkapan layar asli aplikasi)
Cakupan: D-20, D-21 (situs pemasaran `payoung.id`). Tidak menyentuh `dashboard.payoung.id` maupun `consol.payoung.id`.
Referensi desain yang disepakati: **squareup.com**.

---

## 1. Mengapa squareup.com

Dipilih bukan karena "bagus", tapi karena disiplinnya bisa ditiru tanpa merombak sistem desain kita.

| Hal | Square | Payoung sekarang |
|---|---|---|
| Warna merek | Dijatah ekstrem: hanya border 1px & focus ring, bukan isian | `bg-brand-lembut` di setiap kartu |
| Skala judul | Judul editorial besar, jurang lebar ke body 16px | 48px → 18px, lompatan sempit |
| Gambar | Foto full-bleed + tangkapan layar produk nyata | Tidak ada gambar sama sekali |
| Bentuk bagian | Berbeda-beda per bagian | 4 jenis blok, semuanya grid kartu berbingkai |
| Gradien / bayangan dekoratif | Tidak ada | Tidak ada (sudah benar) |

Struktur kontennya juga identik: Square punya halaman per industri (restaurants / retail / beauty / services)
yang memetakan satu-ke-satu ke `/solusi/*` kita, ditambah halaman fitur dan harga.

Referensi kedua, khusus irama tipografi dan cara menaruh tangkapan layar di dalam alir teks: **basecamp.com**.
Ditolak sebagai referensi utama: **nory.ai** — masih memakai grid kartu ikon 3 kolom (penyakit kita) plus drop shadow.

---

## 2. Diagnosis kondisi sekarang

1. **Semua bagian berbentuk sama.** `BagianKeunggulan`, `BagianSektor`, `BagianTestimoni`, `BagianKontak`
   semuanya = judul rata tengah → grid kartu `rounded-panel border border-garis p-6`.
   Tidak ada hierarki: 6 kartu fitur sebobot 3 kartu sektor.
2. **`/fitur` adalah 3 blok `Keunggulan` berturut-turut** (`KontenSitusBawaan.php:111-145`) = 18 kartu identik,
   judul bagiannya kata benda kategori ("Penjualan", "Stok & pembelian").
3. **Kotak ikon ungu di setiap kartu** (`size-12 rounded-panel bg-brand-lembut text-brand`,
   `BagianKartu.tsx:33-37`) — ciri AI slop paling khas, dan melanggar semangat aturan 90/10 §17.6.3.
4. **Selang-seling latar tidak bekerja.** `--color-latar: #f7f9f6` vs `--color-permukaan: #ffffff` beda ~2%,
   jadi batas bagian yang dijanjikan `HitungLatar()` (`RenderBagian.tsx:20-38`) praktis tak terlihat.
5. **`KepalaBagian` default `rata="tengah"`** (`KepalaBagian.tsx:14`) → dinding teks rata tengah dari atas ke bawah.
6. **Hero tanpa jangkar visual.** `Gambar` opsional, tanpa bawaan → beranda dibuka dengan teks rata tengah
   di ruang kosong.
7. **Copy generik.** "Fitur lengkap tanpa ribet", "Semua yang dibutuhkan kasir dan pemilik usaha",
   isi kartu berupa daftar koma yang membaca seperti dump spesifikasi.

---

## 3. Keputusan yang dibutuhkan: usul **D-25**

Tiga permintaan pemilik produk di sesi ini bertabrakan dengan teks PRD yang ada. Semuanya **harus disetujui
eksplisit** sebelum implementasi, karena membalik keputusan yang terikat D-15.

### D-25.a Kuning aksen menjadi token UI

- **Menimpa** §17.6.3: *"Kuning aksen tidak menjadi token UI (tetap hanya di logo) agar tidak tertukar dengan `Peringatan`."*
- **Usul**: `Aksen` = `#f4a261` menjadi token resmi, dengan pagar pemakaian:
  - Hanya di `payoung.id`. **Dilarang** di back-office, konsol pengelola, POS, KDS, Aplikasi Pemilik.
  - **Tidak pernah menandai status apa pun.** Status tetap `Sukses`/`Peringatan`/`Bahaya`/`Info` + teks/ikon.
    Ini yang menjaga aturan "makna warna sama di semua klien".
  - Peran tunggal: aksen grafis pemasaran (garis bawah judul, penanda harga tersorot, blok kutipan).
- **Pagar kontras (wajib, bukan saran)**:

  | Pasangan | Rasio | Putusan |
  |---|---|---|
  | Putih di atas `#f4a261` | **2,1:1** | **Dilarang** |
  | Navy `#1f3335` di atas `#f4a261` | **6,4:1** | Boleh (AAA) |
  | `#f4a261` sebagai teks di atas `Permukaan` putih | **2,1:1** | **Dilarang** |
  | `#f4a261` sebagai teks di atas `BrandGelap` `#22383a` | **5,1:1** | Boleh (AA) |

  Artinya kuning adalah **warna latar dan grafis**, bukan warna teks — kecuali di atas permukaan gelap.

### D-25.b Skala judul hero situs pemasaran

- **Menimpa** §17.5: *"Maksimal 6 token di atas, tidak membuat ukuran baru di luar token."*
- **Usul**: dua token baru **khusus situs pemasaran**: `sorotan-besar` 64px/70px (desktop) dan
  `sorotan-besar-hp` 40px/46px. Tidak dipakai di klien lain.
- Tetap mematuhi §17.5 yang lain: tanpa letter-spacing negatif, tanpa teks bergradien, sentence case.

### D-25.c Animasi pemasaran

- **Menimpa** §17.6.4: *"Animasi singkat (100–200 ms) dan fungsional."*
- **Usul**: di `payoung.id` diizinkan **animasi masuk saat gulir** 200–400 ms (fade + geser ≤ 16px) dan
  transisi angka harga. Syarat mutlak:
  - `prefers-reduced-motion: reduce` mematikan seluruhnya — pola ini **sudah ada** di
    `UtilitasKomponen.css:186`, jadi tinggal diperluas.
  - Animasi **tidak boleh** menunda keterbacaan: isi dirender penuh di HTML, animasi hanya lapisan CSS.
    Halaman tanpa JavaScript tetap lengkap.
  - Tanpa parallax, tanpa animasi berulang tanpa henti, tanpa elemen yang bergerak saat dibaca.
- Anggaran kinerja §17.6.2 tetap: JS < 150 KB.

### D-25.d Pemakaian palet penuh di situs pemasaran

- **Menimpa** aturan 90/10 §17.6.3 **hanya untuk `payoung.id`**.
- **Usul**: blok berlatar penuh boleh memakai `BrandGelap`, `TeksUtama` (Navy), dan `Aksen` sebagai
  latar bagian. Warna semantik (`Sukses`/`Peringatan`/`Bahaya`/`Info`) **tetap hanya untuk status** dan
  tidak dipakai sebagai warna dekoratif — ini tidak dilonggarkan.
- Checklist §17.6.11 "layar tetap bisa dipahami jika semua warna dihapus" **tetap berlaku dan tetap diuji**.

> **Catatan penting**: sesuai CLAUDE.md dan D-17, saya boleh mencatat keputusan, tetapi tiga poin di atas
> membalik keputusan pemilik produk yang terikat D-15. Implementasi tidak dimulai sebelum Anda setujui.

---

## 4. Perubahan token (konkret)

### 4.1 Warna — menyentuh tiga file sekaligus

`SumberWarna_test.dart:83` menuntut himpunan nama `--color-*` berhex literal di `Aplikasi.css`
**sama persis** dengan peta token Flutter. Menambah kuning di web saja **akan mematahkan test itu**.
Jalur yang benar (menambah kasus, bukan melemahkan penjaga — sesuai `.claude/rules/Pengujian.md`):

| File | Perubahan |
|---|---|
| `Aplikasi/Web/resources/js/Gaya/Aplikasi.css` | `--color-aksen: #f4a261;` + turunan `--color-aksen-lembut` (color-mix, tidak ikut terbaca test) |
| `Paket/SistemDesain/lib/Token/TokenWarna.dart` | field `aksen` dengan nilai sama |
| `Paket/SistemDesain/test/Token/SumberWarna_test.dart` | tambah `'aksen': t.aksen.toARGB32()` ke peta |

Ditambah test baru: `aksen` **tidak** muncul di `Aplikasi/Kasir`, `Aplikasi/Pemilik`, back-office,
dan konsol pengelola (pagar D-25.a).

### 4.2 Tipografi

Tambah di `@theme` `Aplikasi.css`: `--text-sorotan-besar: 64px` / line-height 70px,
`--text-sorotan-besar-hp: 40px` / 46px. Dipakai **hanya** oleh `BagianHero` di `Halaman/Situs/`.

### 4.3 Animasi

Utilitas baru di `UtilitasKomponen.css`: `muncul-saat-gulir` berbasis `animation-timeline: view()`
dengan fallback `@supports not` yang merender tanpa animasi, dan blok `prefers-reduced-motion`
diperluas untuk mematikannya.

---

## 5. Rencana per blok

Semua di `Aplikasi/Web/resources/js/Komponen/Situs/Bagian/`.

### `BagianHero.tsx` — dirombak
- Judul memakai `sorotan-besar`, **rata kiri** (bukan tengah) saat ada gambar.
- Latar penuh `BrandGelap` sebagai pilihan (`Latar` prop baru: `Terang` | `Merek` | `Navy`),
  meniru blok full-bleed Square. Teks putih, aksen kuning untuk satu frasa kunci.
- Slot tangkapan layar produk wajib di beranda, dengan dimensi eksplisit agar tanpa pergeseran tata letak.
- Tombol: satu isian (`Brand`) + satu **border 1px** (cara Square), bukan dua tombol sebobot.

### `KepalaBagian.tsx` — default berubah
- Default `rata` menjadi **`kiri`**. Rata tengah menjadi pilihan sadar, bukan bawaan.
- Judul boleh membawa garis bawah `Aksen` setebal 3px sebagai penanda bagian (pengganti kotak ikon).

### `BagianKeunggulan.tsx` (di `BagianKartu.tsx`) — kotak ikon dihapus
- **Buang** `size-12 rounded-panel bg-brand-lembut`. Ikon menjadi 20px monokrom `TeksSekunder`,
  sebaris dengan judul — bukan blok warna di atasnya.
- Tiga tata letak, dipilih dari data, bukan selalu grid: `Grid` (sekarang), `Daftar`
  (dua kolom teks mengalir, tanpa bingkai), `Sorot` (1 item besar + sisanya ringkas).
- Kartu tanpa bingkai bila latar bagian sudah membedakan; bingkai hanya saat kartu benar-benar bisa diklik.

### `BagianSektor.tsx` — dibedakan dari Keunggulan
- Menjadi kartu bergambar besar (aspect-video) dengan judul di bawah, tanpa ikon.
  Ini yang membuatnya beda bentuk dari blok fitur.

### `BagianHarga.tsx` — penyorotan diperbaiki
- Lencana "Paling populer" memakai `Aksen` + teks Navy (6,4:1), bukan `Brand` + putih.
- Paket tersorot: latar `Permukaan` dengan border 2px `Brand`; paket lain tanpa border, dipisah garis 1px.
- Transisi angka saat Bulanan ↔ Tahunan (D-25.c).

### `RenderBagian.tsx` — irama diperbaiki
- `HitungLatar()` diganti: bukan selang-seling `latar`/`permukaan` yang tak terlihat, tapi urutan
  bernilai kontras nyata — `Permukaan` → `Latar` → `Navy`/`Merek` sebagai jeda gelap tiap 3–4 bagian.
- Penjaga baru: **dua blok sejenis tidak boleh berdampingan** tanpa pembeda bentuk.

### `TombolSitus.tsx`
- Varian baru `garis-merek`: border 1px `Brand`, teks `Brand`, latar transparan — pola tombol Square.

---

## 6. Rencana copy (`KontenSitusBawaan.php`)

Aturan menulis: judul harus **mengatakan sesuatu yang bisa salah**, bukan klaim kosong.
Isi kartu = satu manfaat konkret, bukan daftar koma fitur.

| Halaman | Perubahan utama |
|---|---|
| `beranda` | Hero diberi angka/klaim spesifik. Buang "Semua yang dibutuhkan kasir dan pemilik usaha". |
| `fitur` | **Restrukturisasi**: dari 3 grid berturut-turut menjadi Hero → `Sorot` penjualan → `GambarTeks` stok → `Daftar` pelanggan/karyawan → `GambarTeks` pembukuan → CTA. Tidak ada dua grid berdampingan. |
| `harga` | Judul "Pilih paket yang pas" → menyebut pembeda nyata. Catatan kaki PPN dipertegas. |
| `solusi/*` (3 halaman) | Hero per sektor memakai latar berbeda; isi kartu diubah dari daftar fitur menjadi masalah → jalan keluar. |
| `kontak`, `tentang` | Struktur tetap, copy dirapikan. |

Yang **tidak** berubah: tidak mengarang testimoni, angka, atau logo mitra (§ D-21 sudah melarang, dan
`BagianStatistik` memang menunggu isi pengelola).

---

## 7. Aset visual

Belum ada satu pun tangkapan layar produk di repo — ini penyebab besar hero terasa kosong.
Rencana: render tangkapan layar asli dari back-office dan Ruang Kerja Kasir yang sudah jalan
memakai Chromium + Playwright yang sudah tersedia di lingkungan, simpan sebagai aset situs
lewat pustaka gambar D-21. Jujur (UI nyata), bukan mockup karangan.

Perlu konfirmasi: apakah Anda ingin saya yang merender, atau Anda unggah sendiri dari konsol.

---

## 8. Urutan kerja

| Tahap | Isi | Bisa jalan tanpa D-25? |
|---|---|---|
| 0 | Persetujuan D-25 + catat di `PRD.md` (§17.5, §17.6.3, §17.6.4, riwayat versi) lalu `python3 Alat/PecahPrd.py` | — |
| 1 | Token: warna `aksen` (3 file), tipografi, utilitas animasi | Tidak |
| 2 | `KepalaBagian` rata kiri + buang kotak ikon + `RenderBagian` irama baru | **Ya** |
| 3 | `BagianHero`, `BagianSektor`, `BagianHarga`, `TombolSitus` | Sebagian |
| 4 | Copy `KontenSitusBawaan.php` + restrukturisasi `/fitur` | **Ya** |
| 5 | Tangkapan layar produk | Ya |
| 6 | Animasi masuk saat gulir | Tidak |

Tahap 2 dan 4 sendirian sudah menghilangkan sebagian besar kesan AI slop, dan **tidak butuh D-25 sama sekali**.
Kalau Anda ingin hasil cepat tanpa mengubah keputusan, kita bisa mulai dari situ.

---

## 9. Definition of Done

- `python3 Alat/CekKonvensi.py --berubah` hijau.
- `python3 Alat/PecahPrd.py --cek` hijau (PRD dan `Dokumen/` sinkron).
- `cd Aplikasi/Web && composer tes:cepat && composer analisis` hijau.
- `cd Aplikasi/Web && npm run periksa` hijau.
- `melos run periksa` hijau — khususnya `SumberWarna_test.dart` **setelah** token `aksen` ditambah ke Flutter.
- Test baru: pagar `aksen` tidak dipakai di luar situs pemasaran.
- Diuji di **360 / 768 / 1280px** tanpa gulir horizontal (D-16, §17.4.4).
- Kontras diuji: tidak ada teks putih di atas `Aksen`.
- `prefers-reduced-motion: reduce` mematikan seluruh animasi masuk.
- Halaman tetap lengkap dan terbaca tanpa JavaScript.
- Tinjauan subagent `penjaga-konvensi` lewat `/cek-dod`.

---

## 10. Risiko

| Risiko | Mitigasi |
|---|---|
| Kuning tertukar dengan `Peringatan` (alasan asli §17.6.3 melarangnya) | Kuning dilarang menandai status; status selalu berteks/ikon; pagar diuji otomatis |
| Menambah token mematahkan `SumberWarna_test` | Token ditambah ke Flutter juga, bukan test yang dilemahkan |
| Animasi memperlambat / mengganggu | Isi dirender penuh di HTML; reduced-motion dihormati; anggaran JS < 150 KB |
| Palet penuh merusak aksesibilitas yang sudah lolos audit D-16c | Setiap pasangan warna baru dihitung rasionya sebelum dipakai |
| Cakupan melebar ke back-office | D-25 mengikat pemakaian hanya ke `payoung.id`, diuji penjaga |
