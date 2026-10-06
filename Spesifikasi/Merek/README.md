# Merek Payoung (D-61)

Nama: **Payoung** (payung; sebelumnya PAYOU). Tagline resmi: **Smart Choice Your Business Partner**.
Tanda merek: **payung** dengan kubah bergerigi empat, rusuk, gagang melengkung, dan kilau bintang Apricot.

## Sumber utama

Dibuat deterministik oleh `BuatSumberLogo.py` (butuh Chromium dan Pillow):

| File | Isi |
|---|---|
| `Sumber/LembarMerek.png` | Lembar merek: logo utama, horizontal, ikon, monokrom, palet |
| `Sumber/LogoHorizontal.png` | Logo horizontal berwarna, latar transparan |
| `Sumber/IkonMerek.png` | Tanda payung + bintang, latar transparan |
| `Sumber/LogoMonokrom.png` | Logo satu warna (dokumen hitam-putih, struk, faks) |
| `Sumber/LogoHorizontalPutih.png` | Logo horizontal untuk permukaan gelap (kubah putih, "oung" Apricot) |
| `Sumber/IkonMerekPutih.png` | Tanda payung putih untuk sidebar yang diciutkan (rusuk dilubangi, kilau Apricot) |

## Palet merek → token UI

Muted Teal & Apricot:

| Merek | Hex | Token UI (§17.6.3) |
|---|---|---|
| Utama Slate Teal | `#3B5B5D` | `Brand` (teks putih 7,4:1) |
| Slate Teal gelap | `#22383A` | `BrandGelap` (latar sidebar/header merek; teks putih 12:1) |
| Teal Tinta | `#1F3335` | `TeksUtama` |
| Aksen Apricot | `#F4A261` | `Aksen` (grafis; teks di atasnya wajib `TeksUtama`, putih dilarang) |
| Pendukung Sage | `#E8ECE9` | `Garis` |
| Dasar Cream | `#F7F9F6` | `Latar` |

Nilai token hanya diubah di `Aplikasi/Web/resources/js/Gaya/Aplikasi.css` dan
`Paket/SistemDesain/lib/Token/TokenWarna.dart` (dijaga sama oleh `SumberWarna_test.dart`).

## Turunan

Jalankan setelah mengganti file di `Sumber/` (atau setelah `BuatSumberLogo.py`):

```bash
pip install pillow
python3 Spesifikasi/Merek/BuatTurunanAset.py
```

Menghasilkan:

- **Web**: `Aplikasi/Web/public/favicon.ico` (16/32/48), `public/apple-touch-icon.png` (180),
  `resources/js/Aset/Merek/{LogoHorizontal,IkonMerek,LogoHorizontalPutih}.webp` + `IkonMerekPutih.png`
  (dipakai `Komponen/Merek/LogoMerek.tsx`; pilih `varian="putih"` untuk permukaan gelap).
- **Android** (Kasir & Pemilik): `mipmap-*/ic_launcher.png` (lama) dan adaptive icon
  (`ic_launcher_foreground.png`, latar putih, mendukung ikon bertema Android 13).
- **iOS**: seluruh `AppIcon.appiconset` (tanpa alfa, sesuai syarat App Store).
- **Windows** (Kasir): `windows/runner/resources/app_icon.ico`.
- **Flutter dalam aplikasi**: `Paket/SistemDesain/assets/merek/` (dipakai widget `LogoMerek`;
  gunakan `LogoMerek.lengkapPutih()` atau `LogoMerek.ikonPutih()` untuk permukaan gelap).

Nama tampilan: **Payoung POS** (Aplikasi POS) dan **Payoung Owner** (Aplikasi Owner).
