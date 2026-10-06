#!/usr/bin/env python3
"""Membuat turunan aset merek Payoung dari file di `Spesifikasi/Merek/Sumber/`.

Jalankan ulang setiap kali logo sumber diganti (sumber dibuat oleh `BuatSumberLogo.py`):

    pip install pillow
    python3 Spesifikasi/Merek/BuatSumberLogo.py
    python3 Spesifikasi/Merek/BuatTurunanAset.py

Hasil (ditimpa):
- Web: `Aplikasi/Web/public/favicon.ico`, `public/apple-touch-icon.png`, `public/ikon-pwa-{192,512}.png`, `resources/js/Aset/Merek/*` (WebP, kecuali `IkonMerekPutih.png`)
- Flutter: ikon Android (legacy + adaptive), iOS AppIcon, Windows `app_icon.ico` untuk Kasir & Pemilik,
  serta logo warna/putih dalam aplikasi di `Paket/SistemDesain/assets/merek/`.

Warna latar ikon aplikasi = `Permukaan` (putih), sama dengan token UI.
"""

from pathlib import Path

from PIL import Image, ImageDraw

AKAR = Path(__file__).resolve().parents[2]
SUMBER = AKAR / "Spesifikasi/Merek/Sumber"
PUTIH = (255, 255, 255, 255)


def Muat(nama: str) -> Image.Image:
    """Buka gambar sumber, buang derau alfa tipis, lalu potong ke batas konten."""
    gambar = Image.open(SUMBER / nama).convert("RGBA")
    alfa = gambar.getchannel("A").point(lambda v: 0 if v < 10 else v)
    gambar.putalpha(alfa)
    return gambar.crop(alfa.getbbox())


def UbahTinggi(gambar: Image.Image, tinggi: int) -> Image.Image:
    lebar = round(gambar.width * tinggi / gambar.height)
    return gambar.resize((lebar, tinggi), Image.LANCZOS)


def JadikanPutih(gambar: Image.Image) -> Image.Image:
    """Pertahankan alfa dan siluet persis, lalu ubah seluruh piksel tampak menjadi putih."""
    putih = Image.new("RGBA", gambar.size, (255, 255, 255, 0))
    putih.putalpha(gambar.getchannel("A"))
    return putih


def TaruhDiTengah(tanda: Image.Image, sisi: int, porsi: float, latar=(0, 0, 0, 0), sudut: float = 0.0) -> Image.Image:
    """Tanda merek di tengah kanvas persegi; `porsi` = tinggi tanda terhadap sisi kanvas."""
    kanvas = Image.new("RGBA", (sisi, sisi), (0, 0, 0, 0))
    if latar[3]:
        topeng = Image.new("L", (sisi, sisi), 0)
        ImageDraw.Draw(topeng).rounded_rectangle((0, 0, sisi - 1, sisi - 1), radius=round(sisi * sudut), fill=255)
        kanvas.paste(Image.new("RGBA", (sisi, sisi), latar), (0, 0), topeng)
    kecil = UbahTinggi(tanda, max(1, round(sisi * porsi)))
    if kecil.width > sisi * porsi:
        kecil = kecil.resize((round(sisi * porsi), round(kecil.height * sisi * porsi / kecil.width)), Image.LANCZOS)
    x =(sisi - kecil.width) // 2
    y = (sisi - kecil.height) // 2
    kanvas.alpha_composite(kecil, (x, y))
    return kanvas


def Simpan(gambar: Image.Image, path: Path, **opsi) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    gambar.save(path, optimize=True, **opsi)
    print(f"  {path.relative_to(AKAR)}")


def BuatWeb(tanda: Image.Image, logo: Image.Image, tanda_putih: Image.Image, logo_putih: Image.Image) -> None:
    web = AKAR / "Aplikasi/Web"
    ico = TaruhDiTengah(tanda, 256, 0.92)
    ico.save(web / "public/favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])
    print("  Aplikasi/Web/public/favicon.ico")
    Simpan(TaruhDiTengah(tanda, 180, 0.64, PUTIH).convert("RGB"), web / "public/apple-touch-icon.png")
    # F-18 bagian 4 (D-37): ikon PWA halaman absensi (porsi kecil supaya aman sebagai ikon "maskable").
    Simpan(TaruhDiTengah(tanda, 192, 0.56, PUTIH).convert("RGB"), web / "public/ikon-pwa-192.png")
    Simpan(TaruhDiTengah(tanda, 512, 0.56, PUTIH).convert("RGB"), web / "public/ikon-pwa-512.png")
    Simpan(UbahTinggi(logo, 168), web / "resources/js/Aset/Merek/LogoHorizontal.webp", format="WEBP", quality=92, method=6)
    Simpan(UbahTinggi(tanda, 96), web / "resources/js/Aset/Merek/IkonMerek.webp", format="WEBP", quality=92, method=6)
    Simpan(UbahTinggi(logo_putih, 168), web / "resources/js/Aset/Merek/LogoHorizontalPutih.webp", format="WEBP", quality=92, method=6)
    Simpan(UbahTinggi(tanda_putih, 96), web / "resources/js/Aset/Merek/IkonMerekPutih.png")


def BuatAndroid(tanda: Image.Image, res: Path) -> None:
    kepadatan = {"mdpi": 1, "hdpi": 1.5, "xhdpi": 2, "xxhdpi": 3, "xxxhdpi": 4}
    for nama, skala in kepadatan.items():
        Simpan(TaruhDiTengah(tanda, round(48 * skala), 0.62, PUTIH, 0.22), res / f"mipmap-{nama}/ic_launcher.png")
        # Adaptive icon: kanvas 108dp, zona aman lingkaran 66dp -> tinggi tanda 46% kanvas.
        Simpan(TaruhDiTengah(tanda, round(108 * skala), 0.46), res / f"mipmap-{nama}/ic_launcher_foreground.png")
    anydpi = res / "mipmap-anydpi-v26/ic_launcher.xml"
    anydpi.parent.mkdir(parents=True, exist_ok=True)
    anydpi.write_text(
        '<?xml version="1.0" encoding="utf-8"?>\n'
        '<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">\n'
        '    <background android:drawable="@color/ic_launcher_background"/>\n'
        '    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>\n'
        '    <monochrome android:drawable="@mipmap/ic_launcher_foreground"/>\n'
        '</adaptive-icon>\n',
        encoding="utf-8",
    )
    warna = res / "values/ic_launcher_background.xml"
    warna.write_text(
        '<?xml version="1.0" encoding="utf-8"?>\n'
        '<resources>\n    <color name="ic_launcher_background">#FFFFFF</color>\n</resources>\n',
        encoding="utf-8",
    )
    print(f"  {anydpi.relative_to(AKAR)}\n  {warna.relative_to(AKAR)}")


def BuatIos(tanda: Image.Image, set_ikon: Path) -> None:
    for path in sorted(set_ikon.glob("Icon-App-*.png")):
        ukuran_pt, skala = path.stem.removeprefix("Icon-App-").split("@")
        sisi = round(float(ukuran_pt.split("x")[0]) * int(skala.removesuffix("x")))
        # iOS menolak ikon beralfa; sudut dibulatkan sistem, jadi latar penuh.
        Simpan(TaruhDiTengah(tanda, sisi, 0.62, PUTIH).convert("RGB"), path)


def BuatWindows(tanda: Image.Image, path: Path) -> None:
    ikon = TaruhDiTengah(tanda, 256, 0.9)
    ikon.save(path, sizes=[(16, 16), (24, 24), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)])
    print(f"  {path.relative_to(AKAR)}")


def BuatSistemDesain(tanda: Image.Image, logo: Image.Image, tanda_putih: Image.Image, logo_putih: Image.Image) -> None:
    aset = AKAR / "Paket/SistemDesain/assets/merek"
    Simpan(UbahTinggi(logo, 216), aset / "LogoHorizontal.png")
    Simpan(UbahTinggi(tanda, 192), aset / "IkonMerek.png")
    Simpan(UbahTinggi(logo_putih, 216), aset / "LogoHorizontalPutih.png")
    Simpan(UbahTinggi(tanda_putih, 192), aset / "IkonMerekPutih.png")


def main() -> None:
    # Varian putih punya sumber sendiri (rusuk dilubangi, kilau tetap Apricot), dibuat oleh BuatSumberLogo.py.
    tanda = Muat("IkonMerek.png")
    logo = Muat("LogoHorizontal.png")
    tanda_putih = Muat("IkonMerekPutih.png")
    logo_putih = Muat("LogoHorizontalPutih.png")
    print("Web:")
    BuatWeb(tanda, logo, tanda_putih, logo_putih)
    for aplikasi in ("Kasir", "Pemilik"):
        print(f"{aplikasi}:")
        dasar = AKAR / "Aplikasi" / aplikasi
        BuatAndroid(tanda, dasar / "android/app/src/main/res")
        BuatIos(tanda, dasar / "ios/Runner/Assets.xcassets/AppIcon.appiconset")
        windows = dasar / "windows/runner/resources/app_icon.ico"
        if windows.parent.exists():
            BuatWindows(tanda, windows)
    print("SistemDesain:")
    BuatSistemDesain(tanda, logo, tanda_putih, logo_putih)


if __name__ == "__main__":
    main()
