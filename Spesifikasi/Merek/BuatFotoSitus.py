#!/usr/bin/env python3
"""Mengubah tangkapan layar asli aplikasi Payoung menjadi WebP untuk situs pemasaran (`payoung.id`).

Sumbernya keluaran test widget yang merender layar **asli** aplikasi dengan data contoh
(`Aplikasi/Kasir/AlatSitus/FotoPlayStore_test.dart` & `Aplikasi/Pemilik/AlatSitus/FotoPlayStore_test.dart`,
hasilnya di `AlatSitus/Hasil/PlayStore/`, tidak masuk Git). Tidak ada gambar karangan atau mockup pesaing di sini.

Pakai:  python3 Spesifikasi/Merek/BuatFotoSitus.py

Tablet disimpan 1600 x 1000 px, ponsel 540 x 1080 px; WebP kualitas 80 agar tiap berkas tetap di bawah ~150 KB.
Berkas hasil di `Aplikasi/Web/public/situs/produk/` dirujuk `Komponen/Situs/SpesimenSitus.tsx`.
"""
from pathlib import Path

from PIL import Image

AKAR = Path(__file__).resolve().parents[2]
KASIR = AKAR / 'Aplikasi/Kasir/AlatSitus/Hasil/PlayStore'
PEMILIK = AKAR / 'Aplikasi/Pemilik/AlatSitus/Hasil/PlayStore'
TUJUAN = AKAR / 'Aplikasi/Web/public/situs/produk'

TABLET = (1600, 1000)
PONSEL = (540, 1080)

DAFTAR = {
    'kasir-jual': (KASIR / 'Tablet1Jual.png', TABLET),
    'kasir-bayar': (KASIR / 'Tablet2Bayar.png', TABLET),
    'kasir-berhasil': (KASIR / 'Tablet3Berhasil.png', TABLET),
    'kasir-shift': (KASIR / 'Tablet4Shift.png', TABLET),
    'kasir-riwayat': (KASIR / 'Tablet5Riwayat.png', TABLET),
    'kasir-kas': (KASIR / 'Tablet6Kas.png', TABLET),
    'kasir-hp-jual': (KASIR / 'Hp1Jual.png', PONSEL),
    'pemilik-beranda': (PEMILIK / 'PemilikHp1Beranda.png', PONSEL),
    'pemilik-laporan': (PEMILIK / 'PemilikHp2Laporan.png', PONSEL),
    'pemilik-persetujuan': (PEMILIK / 'PemilikHp3Persetujuan.png', PONSEL),
    'pemilik-shift': (PEMILIK / 'PemilikHp4Shift.png', PONSEL),
    'pemilik-karyawan': (PEMILIK / 'PemilikHp5Karyawan.png', PONSEL),
    'pemilik-insight': (PEMILIK / 'PemilikHp6Insight.png', PONSEL),
}


def main() -> None:
    TUJUAN.mkdir(parents=True, exist_ok=True)
    for nama, (sumber, ukuran) in DAFTAR.items():
        if not sumber.exists():
            print(f'LEWATI {nama}: {sumber} belum dirender (jalankan test AlatSitus di Aplikasi/Kasir & Aplikasi/Pemilik).')
            continue
        gambar = Image.open(sumber).convert('RGB').resize(ukuran, Image.LANCZOS)
        berkas = TUJUAN / f'{nama}.webp'
        gambar.save(berkas, 'WEBP', quality=80, method=6)
        print(f'{berkas.name}: {ukuran[0]}x{ukuran[1]}, {berkas.stat().st_size // 1024} KB')


if __name__ == '__main__':
    main()
