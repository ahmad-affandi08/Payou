# Video promosi Payoung (30 detik)

Motion graphic HTML untuk video promosi awal: 1920×1080 (16:9), 30 detik, dengan musik latar dan efek suara.
Memakai logo dari `../Sumber/`, palet merek D-15, dan font Atkinson Hyperlegible (§17.5, disimpan lokal di `Huruf/`, lisensi SIL OFL).

Tagline resmi: **Smart Choice Your Business Partner**. Untuk animasi masuk, tagline yang tertanam di
`LogoHorizontal.png` ditutup lalu digambar kembali sebagai lapisan teks terpisah.

## Alur cerita

| Detik | Adegan | Pesan |
|---|---|---|
| 0–4,9 | Masalah | "Jualan lagi ramai… internet malah mati." Wi-Fi putus, stok selisih, rekap manual |
| 4,9–9,2 | Pengenalan | Bintang merek terbang ke logo, logo Payoung tersapu masuk, tagline muncul, lalu logo mengecil ke sudut |
| 9,2–15,6 | 01 Kasir offline-first | Tablet Payoung POS: luring, keranjang, bayar berhasil, lalu tersinkron tanpa dobel |
| 15,6–21,0 | 02 Stok & akuntansi otomatis | Penjualan → mutasi stok → jurnal seimbang (D = K) → laporan laba rugi |
| 21,0–26,3 | 03 Aplikasi Payoung Owner | Omzet 3 outlet, grafik, stok menipis, persetujuan void dari HP |
| 26,3–30 | Penutup | Logo + tagline, "Daftar gratis sekarang", Android · iPad & iPhone · Windows · Web |

## Suara

Semua suara disintesis di halaman dengan Web Audio (tanpa file audio), jadi bebas lisensi dan selalu sama:

- Musik latar: pad C–G–Am–F, 100 bpm, ketukan mulai di adegan fitur, akor penutup memudar di akhir.
- Adegan masalah: dengung rendah, detak jam, bunyi galat.
- Efek: whoosh untuk transisi, bunyi letup kartu, bip pemindai barang, klik tombol, "ka-ching" pembayaran,
  denting sinkron dan notifikasi, detak penghitung angka, riser + dentum sebelum penutup.

## Memutar

Buka `VideoPromosi30Detik.html` di peramban, lalu klik **Putar dengan suara**.
Spasi = putar/jeda, R = ulang, M = bisu, H = sembunyikan kontrol, panah kiri/kanan = geser 1 detik.

## Merekam ke MP4

Rekaman dibuat bingkai demi bingkai, lalu audio diekspor dari halaman dan dinormalisasi ke −16 LUFS:

```bash
npm i -g playwright            # sekali, jika belum ada
pip install imageio-ffmpeg     # jika ffmpeg belum terpasang; lalu set FFMPEG ke path biner-nya
NODE_PATH=$(npm root -g) node Spesifikasi/Merek/VideoPromosi/RekamVideo.mjs keluaran.mp4 30
```

Hasil MP4 tidak disimpan di repo; buat ulang dengan perintah di atas.
