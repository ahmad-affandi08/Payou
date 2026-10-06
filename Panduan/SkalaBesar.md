# Menjaga Payoung tetap cepat di skala besar (target 100.000 pengguna)

Hasil audit kinerja 6 Oktober 2026 (PRD v5.01, D-81). Bagian 1 sudah dikerjakan; bagian 2-4 adalah daftar kerja berikutnya
berurut dampak. Angka kueri adalah perkiraan dari membaca kode kecuali tertulis "terukur".

## 1. Sudah dikerjakan (v5.01)

- 18 indeks baru (migrasi `2026_11_22_000170`), termasuk lima indeks penyapu terjadwal.
- Empat tugas per menit sampai per 10 menit (webhook, pesanan online hangus, rekonsiliasi QRIS, kampanye terjadwal) hanya
  mengunjungi tenant yang punya pekerjaan. Terukur pada 1 juta baris & 20.000 tenant: 10 ms per putaran, dulu satu kueri
  per tenant.
- `withoutOverlapping(menit)` di semua jadwal; log harian dengan level `warning` (di `.env` server, bukan hanya contoh).
- Polling kasir `pesanan-online/ringkas` tiga `COUNT` jadi satu kueri; layar antrian kios di-cache 3 detik per outlet;
  ringkasan Kotak Tindakan di Beranda di-cache 60 detik.

Setelah `git pull` di server: `php artisan migrate` (menambah indeks pada tabel yang sudah berisi data; di MySQL 8 dikerjakan
online, tetapi jalankan di luar jam ramai), lalu `php artisan optimize:clear`. Pastikan `.env` server berisi
`LOG_STACK=daily` dan `LOG_LEVEL=warning`.

## 2. Syarat operasional (bukan perubahan kode)

1. **Redis untuk cache, sesi, dan antrean** (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`). Hari ini
   ketiganya di MySQL yang sama dengan data transaksi, jadi setiap request menulis sesi dan membaca cache di sana. Butuh
   VPS (PRD §14.2), bukan hosting bersama. Ini perubahan yang paling besar dampaknya.
2. **Beberapa worker antrean paralel.** Hari ini satu `queue:work --max-time=50` per menit dan semua jenis tugas berbagi
   satu jalur; pisahkan antrean `webhook`, `wa`, `impor` dan beri proses sendiri (Supervisor di VPS).
3. **Naikkan jatah koneksi basis data** sebelum menambah pekerja PHP: tiap request, worker, dan penjadwal membuka koneksi.
4. Pantau `/operasional` (umur job tertua, detak penjadwal) dan aktifkan log kueri lambat (`DB::whenQueryingForLongerThan`)
   sebelum beban naik.
5. **Ukur, jangan menebak:** jalankan `UjiBeban.yml` di staging dengan jumlah tenant dan perangkat yang meniru target
   (`Panduan/UjiBeban.md`), lalu isi ambang p95 dengan angka terukur.

## 3. Perubahan kode berikutnya (menunggu pengukuran / persetujuan)

Urut dampak. Butir 1-3 menyentuh jalur uang dan stok, jadi butuh invariant test dan jangan digabung dengan perbaikan lain.

1. **`TerimaPenjualanPos` (sinkron/kirim):** satu transaksi 35-60 kueri per penjualan; lock nomor jurnal per tenant
   (`PenomorDokumen::Naikkan`) memegang semua kasir satu tenant sampai commit; `PenjualanDetail::create` per baris dan
   `IsiHppBaris` yang menyimpan per baris. Usul: nomor jurnal di akhir transaksi atau urutan per outlet/perangkat, insert massal.
2. **Katalog POS tanpa paginasi:** sinkron lengkap memuat seluruh produk, satuan, dan barcode ke memori
   (`ProdukUntukPos`). Usul: halaman berbasis kursor `Id`, `chunkById`, ETag; tambah kontrak API versi baru bila perlu.
3. **Neraca, Neraca Saldo, Buku Besar** menjumlahkan seluruh riwayat `JurnalDetail`. Usul: tabel saldo akun per periode.
4. **Cache pendek per tenant** untuk `konfigurasi-aplikasi` (14-18 kueri), `data-awal` (sekitar 40 kueri), flag fitur, dan
   `FiturPaket` di properti bersama Inertia (sekitar 10 kueri per navigasi), dengan kunci per tenant dan pembatalan saat
   langganan/add-on/flag berubah. Paling bermanfaat setelah Redis.
5. **Daftar bertabel (`PenerapKueriTabel`):** `COUNT(*)` penuh + `OFFSET` + `LIKE '%x%'` di setiap halaman. Usul: pencarian
   awalan atau FULLTEXT, total yang di-cache, dan keyset untuk tabel besar.
6. **Tugas harian yang menyapu semua tenant** (draf PO, tutup harian, penyusutan, ringkasan, pengingat): ubah menjadi satu
   job per tenant (atau per 500 tenant) di antrean, sebar jamnya, dan catat durasi per tugas.
7. **Retensi tabel log:** `LogAudit`, `PesanKeluar`, `TagihanQris` final, notifikasi tidak pernah dipangkas. Jangan memangkas
   `Penjualan`, `Jurnal`, `MutasiStok` (pajak & akuntansi): arsip atau partisi bulanan.
8. **Email transaksional** (verifikasi, atur ulang kata sandi, undangan) masih dikirim di dalam request; antrekan setelah
   Redis dan worker pasti berjalan, karena tertundanya email masuk akal dirasakan pengguna.
9. **`whereDate()` pada kolom tanggal** (mematikan indeks) di `TerimaReturTanpaStrukPos`, `ShiftBelumDitutup`,
   `PembayaranBelumDicairkan`, dan beberapa lainnya; ganti ke rentang `TanggalBisnis`.
10. Frontend: pisahkan vendor lewat `manualChunks`, kompres brotli di server, header cache `immutable` untuk `/build/assets`.
