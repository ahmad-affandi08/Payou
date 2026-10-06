#!/usr/bin/env python3
"""Membuat logo sumber Payoung (D-61) di `Spesifikasi/Merek/Sumber/` dari definisi vektor di file ini.

Tanda merek = payung: kubah bergerigi empat, rusuk, gagang melengkung, dan kilau bintang Apricot.
Palet Muted Teal & Apricot (Slate Teal #3B5B5D, Apricot #F4A261, Sage #E8ECE9, Cream #F7F9F6).

Jalankan, lalu jalankan `BuatTurunanAset.py` untuk menurunkan ikon aplikasi, favicon, dan logo dalam aplikasi:

    python3 Spesifikasi/Merek/BuatSumberLogo.py
    python3 Spesifikasi/Merek/BuatTurunanAset.py

Butuh Chromium (dipakai merender teks logo dengan font Atkinson Hyperlegible Next) dan Pillow.
Sumber yang dihasilkan: IkonMerek, IkonMerekPutih, LogoHorizontal, LogoHorizontalPutih, LogoMonokrom, LembarMerek.
"""

import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from PIL import Image

AKAR = Path(__file__).resolve().parents[2]
SUMBER = AKAR / "Spesifikasi/Merek/Sumber"
FONT = AKAR / "Spesifikasi/Merek/VideoPromosi/Huruf/AtkinsonHyperlegibleNext.woff2"

TEAL = "#3B5B5D"
TEAL_TERANG = "#4F7376"
TINTA = "#1F3335"
APRICOT = "#F4A261"
SAGE = "#E8ECE9"
KREM = "#F7F9F6"
SLOGAN = "Smart Choice Your Business Partner"

# Kubah bergerigi empat (viewBox 200x200), rusuk, pucuk, gagang, dan kilau.
KUBAH = "M16 104 A84 84 0 0 1 184 104 A21 21 0 0 1 142 104 A21 21 0 0 1 100 104 A21 21 0 0 1 58 104 A21 21 0 0 1 16 104 Z"
RUSUK = "M100 20 Q136 50 142 104 M100 20 V104 M100 20 Q64 50 58 104"
GAGANG = "M100 104 V162 a19 19 0 0 1 -38 0"
KILAU = "M168 18 l4.6 12.4 L185 35 l-12.4 4.6 L168 52 l-4.6-12.4 L151 35 l12.4-4.6 Z"


def Payung(varian: str, ukuran: int, id_unik: str = "p") -> str:
    """SVG payung. varian: warna | putih | mono. Pada putih & mono rusuk dilubangi (transparan) agar cocok di latar apa pun."""
    if varian == "warna":
        kubah, gagang, kilau = f"url(#g{id_unik})", TEAL, APRICOT
        rusuk = f'<path d="{RUSUK}" fill="none" stroke="{KREM}" stroke-opacity=".45" stroke-width="3.5" stroke-linecap="round"/>'
        pucuk = f'<path d="M100 20 V9" stroke="{TEAL}" stroke-width="7" stroke-linecap="round"/>'
        lubang = ""
        isi_kubah = f'<path d="{KUBAH}" fill="{kubah}"/>'
    else:
        warna = "#FFFFFF" if varian == "putih" else "#1A1A1A"
        gagang = warna
        kilau = APRICOT if varian == "putih" else warna
        lubang = (
            f'<mask id="m{id_unik}"><rect width="200" height="200" fill="#fff"/>'
            f'<path d="{RUSUK}" fill="none" stroke="#000" stroke-width="3.5" stroke-linecap="round"/></mask>'
        )
        isi_kubah = f'<path d="{KUBAH}" fill="{warna}" mask="url(#m{id_unik})"/>'
        rusuk = ""
        pucuk = f'<path d="M100 20 V9" stroke="{warna}" stroke-width="7" stroke-linecap="round"/>'
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="{ukuran}" height="{ukuran}">'
        f'<defs><linearGradient id="g{id_unik}" x1="0" y1="0" x2="1" y2="1">'
        f'<stop offset="0" stop-color="{TEAL_TERANG}"/><stop offset="1" stop-color="{TEAL}"/></linearGradient>{lubang}</defs>'
        f"{isi_kubah}{rusuk}{pucuk}"
        f'<path d="{GAGANG}" fill="none" stroke="{gagang}" stroke-width="11" stroke-linecap="round" stroke-linejoin="round"/>'
        f'<path d="{KILAU}" fill="{kilau}"/></svg>'
    )


def Nama(varian: str) -> tuple[str, str, str]:
    """(warna 'Pay', warna 'oung', warna slogan)."""
    if varian == "warna":
        return TINTA, TEAL, TINTA
    if varian == "putih":
        return "#FFFFFF", APRICOT, SAGE
    return "#1A1A1A", "#1A1A1A", "#1A1A1A"


def LogoHorizontal(varian: str, skala: float = 1.0) -> str:
    pay, oung, slogan = Nama(varian)
    return (
        f'<div class="logo" style="display:flex;align-items:center;gap:{40 * skala}px">'
        f"{Payung(varian, round(330 * skala), 'h' + varian)}"
        f'<div><div style="font-size:{250 * skala}px;font-weight:700;letter-spacing:-.025em;line-height:1;color:{pay}">'
        f'Pay<span style="color:{oung}">oung</span></div>'
        f'<div style="font-size:{50 * skala}px;font-weight:400;letter-spacing:.01em;margin-top:{18 * skala}px;color:{slogan}">{SLOGAN}</div></div></div>'
    )


def Halaman(isi: str, lebar: int, tinggi: int, latar: str = "transparent") -> str:
    return (
        '<!doctype html><meta charset="utf-8"><style>'
        f'@font-face{{font-family:AHN;src:url(file://{FONT}) format("woff2");font-weight:200 800}}'
        f"*{{box-sizing:border-box}}html,body{{margin:0;background:{latar}}}"
        f'body{{width:{lebar}px;height:{tinggi}px;font-family:AHN,sans-serif;display:grid;place-items:center;color:{TINTA}}}'
        f"</style>{isi}"
    )


def Render(html: str, hasil: Path, lebar: int, tinggi: int, transparan: bool = True) -> None:
    chromium = os.environ.get("CHROMIUM") or shutil.which("chromium") or shutil.which("chromium-browser")
    if not chromium:
        for kandidat in sorted(Path("/opt/pw-browsers").glob("chromium-*/chrome-linux/chrome")):
            chromium = str(kandidat)
    if not chromium:
        sys.exit("Chromium tidak ditemukan. Setel CHROMIUM=/jalur/ke/chrome.")
    with tempfile.TemporaryDirectory() as tmp:
        berkas = Path(tmp) / "halaman.html"
        berkas.write_text(html, encoding="utf-8")
        mentah = Path(tmp) / "mentah.png"
        perintah = [
            chromium, "--headless=new", "--no-sandbox", "--hide-scrollbars", "--force-device-scale-factor=1",
            f"--window-size={lebar},{tinggi + 120}", f"--screenshot={mentah}",
        ]
        if transparan:
            perintah.insert(2, "--default-background-color=00000000")
        subprocess.run([*perintah, f"file://{berkas}"], check=True, capture_output=True)
        Image.open(mentah).crop((0, 0, lebar, tinggi)).save(hasil, optimize=True)
    print(f"  {hasil.relative_to(AKAR)}")


def Lembar() -> str:
    def Kotak(judul: str, isi: str, gelap: bool = False) -> str:
        latar = "#fff" if not gelap else TEAL
        warna = TINTA if not gelap else KREM
        return (
            f'<div style="border-radius:22px;background:{latar};color:{warna};position:relative;display:grid;place-items:center;'
            f'border:1px solid #dde3df"><b style="position:absolute;left:22px;top:16px;font-size:15px;font-weight:600;opacity:.65">{judul}</b>{isi}</div>'
        )

    def Warna(nama: str, makna: str, hex_: str) -> str:
        return (
            f'<div style="background:#fff;border:1px solid #dde3df;border-radius:18px;display:grid;justify-items:center;gap:6px;padding:22px 10px">'
            f'<i style="width:120px;height:120px;border-radius:50%;background:{hex_};border:1px solid rgba(0,0,0,.08)"></i>'
            f'<b style="font-size:24px;font-weight:600">{nama}</b><span style="font-size:15px;opacity:.65">{makna}</span>'
            f'<b style="font-size:26px;font-weight:500">{hex_}</b></div>'
        )

    utama = (
        '<div style="display:grid;place-items:center;padding-block:10px">' + LogoHorizontal("warna", 0.62) + "</div>"
    )
    return (
        '<div style="width:1470px;height:1070px;background:' + KREM + ';padding:30px 34px;display:grid;gap:18px;'
        'grid-template-rows:28px 250px 270px 1fr;align-content:start">'
        '<b style="font-size:24px;font-weight:500;opacity:.7">Primary Logo</b>'
        + utama +
        '<div style="display:grid;grid-template-columns:1.55fr .75fr 1.3fr;gap:18px">'
        + Kotak("Horizontal Logo", LogoHorizontal("warna", 0.34))
        + Kotak("Icon Only", Payung("warna", 190, "ik"))
        + Kotak("Monochrome", LogoHorizontal("mono", 0.34))
        + "</div>"
        '<div><b style="font-size:24px;font-weight:600;opacity:.75">Color Palette</b>'
        '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:14px">'
        + Warna("Slate Teal", "Utama • Tenang • Tepercaya", TEAL)
        + Warna("Apricot", "Aksen • Hangat • Optimis", APRICOT)
        + Warna("Sage", "Pendukung • Seimbang", SAGE)
        + Warna("Cream", "Dasar • Bersih • Ramah", KREM)
        + "</div></div></div>"
    )


def main() -> None:
    SUMBER.mkdir(parents=True, exist_ok=True)
    print("Sumber logo Payoung:")
    Render(Halaman(Payung("warna", 1254, "a"), 1254, 1254), SUMBER / "IkonMerek.png", 1254, 1254)
    Render(Halaman(Payung("putih", 1254, "b"), 1254, 1254), SUMBER / "IkonMerekPutih.png", 1254, 1254)
    for varian, nama in (("warna", "LogoHorizontal"), ("putih", "LogoHorizontalPutih"), ("mono", "LogoMonokrom")):
        Render(Halaman(LogoHorizontal(varian), 1448, 1086), SUMBER / f"{nama}.png", 1448, 1086)
    Render(Halaman(Lembar(), 1470, 1070, KREM), SUMBER / "LembarMerek.png", 1470, 1070, transparan=False)


if __name__ == "__main__":
    main()
