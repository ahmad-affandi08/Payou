#!/usr/bin/env python3
"""Mewarnai ulang ilustrasi raster berpalet lama (indigo/navy + kuning) ke palet Payoung (D-61).

Dipakai sekali saat penggantian merek untuk ikon navigasi web dan ilustrasi keadaan kosong, supaya bentuk &
pencahayaan aset tetap sama. Skrip ini idempoten untuk aset yang SUDAH berpalet baru (tidak ada piksel biru-ungu
yang tersisa, jadi tidak ada yang berubah).

    python3 Spesifikasi/Merek/WarnaiUlangRaster.py

Aturan:
- Piksel biru-ungu (hue 195-285 derajat, jenuh): dipetakan per terang-gelapnya ke tangga Slate Teal.
- Piksel kuning-jingga (hue 30-60 derajat, jenuh): digeser ke Apricot dengan terang yang sama.
- Piksel netral (putih, abu, bayangan) dan warna lain (hijau, merah) tidak diubah.
"""

import colorsys
from pathlib import Path

from PIL import Image

AKAR = Path(__file__).resolve().parents[2]
BERKAS = [
    *sorted((AKAR / "Aplikasi/Web/resources/js/Aset/IkonNavigasi").glob("*.webp")),
    *sorted((AKAR / "Aplikasi/Web/resources/js/Aset/KeadaanKosong").glob("*.webp")),
    *sorted((AKAR / "Spesifikasi/Merek/KeadaanKosong").glob("*.png")),
]

# Tangga terang (luminans sumber) -> warna Slate Teal.
TANGGA = [
    (0.000, (0x12, 0x20, 0x22)),
    (0.030, (0x22, 0x38, 0x3A)),
    (0.110, (0x3B, 0x5B, 0x5D)),
    (0.240, (0x4F, 0x85, 0x88)),
    (0.480, (0x86, 0xB6, 0xB6)),
    (0.780, (0xC9, 0xE4, 0xE1)),
    (0.920, (0xE6, 0xF1, 0xEF)),
    (1.000, (0xFF, 0xFF, 0xFF)),
]
APRICOT_HUE = 27 / 360


def Luminans(r: float, g: float, b: float) -> float:
    def lin(c: float) -> float:
        return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4

    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)


def DariTangga(y: float) -> tuple[float, float, float]:
    for (y0, c0), (y1, c1) in zip(TANGGA, TANGGA[1:]):
        if y <= y1:
            t = 0 if y1 == y0 else (y - y0) / (y1 - y0)
            return tuple((c0[i] + (c1[i] - c0[i]) * t) / 255 for i in range(3))  # type: ignore[return-value]
    return (1.0, 1.0, 1.0)


def Warnai(gambar: Image.Image) -> tuple[Image.Image, int]:
    gambar = gambar.convert("RGBA")
    piksel = gambar.load()
    berubah = 0
    for y in range(gambar.height):
        for x in range(gambar.width):
            r8, g8, b8, a = piksel[x, y]
            if a == 0:
                continue
            r, g, b = r8 / 255, g8 / 255, b8 / 255
            h, s, v = colorsys.rgb_to_hsv(r, g, b)
            if s < 0.025:
                continue
            derajat = h * 360
            if 195 <= derajat <= 285:
                nr, ng, nb = DariTangga(Luminans(r, g, b))
            elif 30 <= derajat <= 60 and s > 0.35:
                nr, ng, nb = colorsys.hsv_to_rgb(APRICOT_HUE, min(1.0, s * 0.72), v * 0.98)
            else:
                continue
            piksel[x, y] = (round(nr * 255), round(ng * 255), round(nb * 255), a)
            berubah += 1
    return gambar, berubah


def main() -> None:
    for path in BERKAS:
        asli = Image.open(path)
        baru, berubah = Warnai(asli)
        if berubah == 0:
            print(f"  (tetap) {path.relative_to(AKAR)}")
            continue
        if path.suffix == ".webp":
            baru.save(path, format="WEBP", quality=94, method=6)
        else:
            baru.save(path, optimize=True)
        print(f"  {path.relative_to(AKAR)}: {berubah} piksel")


if __name__ == "__main__":
    main()
