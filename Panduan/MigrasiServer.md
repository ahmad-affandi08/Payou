# Migrasi dari Hosting Bersama ke Server Sendiri (tanpa henti layanan)

Sasaran pemilik produk (3 Okt 2026): saat Payoung pindah dari hosting bersama (hPanel) ke server sendiri, **toko tidak
boleh berhenti berjualan**. Panduan ini menjelaskan apa yang harus disiapkan sejak sekarang dan langkah pindahnya.

## Arti "tanpa henti" di Payoung

| Pengguna | Target | Kenapa bisa |
|---|---|---|
| Kasir (aplikasi POS) | **Nol henti**: tetap bisa jual, bayar, cetak struk | Aplikasi offline-first; penjualan masuk outbox SQLite. `KlienPos` menganggap jawaban 5xx/timeout sebagai gangguan jaringan dan mengirim ulang otomatis; idempotensi `Uuid` + `Idempotency-Key` menjamin kiriman ulang tidak menggandakan data |
| Aplikasi Pemilik, back-office, toko online, konsol | **Nol henti untuk baca**, jeda tulis **≤ 2 menit** (banner "sedang pemeliharaan, coba lagi sebentar") | Jeda tulis hanya dipakai bila replikasi DB tidak tersedia (lihat bawah) |
| Fitur yang wajib online (voucher berkode, QRIS dinamis, pesanan online, persetujuan jarak jauh) | Gagal sebentar selama jeda tulis, lalu normal | Aplikasi kasir sudah menampilkan pesan "coba lagi"; webhook gerbang pembayaran dikirim ulang oleh penyedia |

**Batasan jujur:** nol henti penuh di sisi web butuh **replikasi basis data** (server baru menyalin perubahan dari
server lama secara terus-menerus). Hosting bersama umumnya **tidak memberi hak replikasi/binlog**. Cek sekarang:

```sql
SHOW VARIABLES LIKE 'log_bin';      -- ON = binlog aktif
SHOW GRANTS;                        -- cari REPLICATION SLAVE / REPLICATION CLIENT
```

- Bila **ada**: pakai Jalur A (replikasi) → web pun nol henti.
- Bila **tidak ada** (paling mungkin): pakai Jalur B (salin + jeda tulis singkat) → kasir tetap nol henti, web jeda
  tulis ≤ 2 menit. Ini batas terbaik yang bisa dicapai dari hosting bersama tanpa mengubah arsitektur.

## Yang harus disiapkan SEJAK SEKARANG (supaya nanti mudah)

1. **Domain lewat Cloudflare (atau DNS dengan TTL rendah).** Pindahkan DNS `payoung.id` ke Cloudflare sekarang. Saat
   migrasi, perpindahan cukup mengganti alamat asal di Cloudflare (berlaku detik), bukan menunggu propagasi DNS
   berjam-jam. Bila tidak memakai Cloudflare, turunkan TTL A record ke 60 detik **minimal 2 hari** sebelum pindah.
   Setel `PROKSI_TEPERCAYA` ke rentang IP Cloudflare.
2. **Berkas unggahan siap dipindah ke penyimpanan objek.** Semua berkas memakai disk yang bisa diatur lewat `.env`
   (`katalog.DiskGambar`, `kasir.DiskBuktiKas`, `karyawan.DiskSwafoto`, `pemenuhan.DiskBukti`, `tenant.DiskLogo`,
   `akuntansi.DiskLampiran`, `tagihan.DiskBukti`, `situs.Disk`). Di server sendiri, arahkan ke S3-kompatibel (mis.
   Cloudflare R2, IDCloudHost Object Storage, MinIO) supaya berkas tidak terikat ke satu mesin. Jangan menulis jalur
   berkas absolut di basis data (sudah dipenuhi: yang disimpan hanya jalur relatif).
3. **Basis data sama mesinnya di kedua sisi.** Hosting = MariaDB 11.8 (A2/K17). Untuk server baru pilih salah satu
   dan pertahankan; pindah MariaDB → MySQL 8 sekaligus migrasi server menambah risiko. Saran: server baru tetap
   MariaDB 11.8 saat pindah, ganti mesin (bila perlu) belakangan sebagai proyek terpisah.
4. **Backup harian + uji restore** (A10/K18). Uji restore inilah latihan pertama migrasi: ukur berapa lama
   `mysqldump` + impor untuk ukuran data saat ini, catat di bawah.
5. **`APP_KEY` dan kunci enkripsi disimpan aman di luar server.** Data terenkripsi (NPWP/NIK, resep, prospek,
   kredensial gerbang) hanya bisa dibaca di server baru dengan `APP_KEY` yang **sama persis**. Kehilangan kunci =
   data terenkripsi tidak bisa dibuka. Simpan di pengelola sandi pemilik, bukan di repo.
6. **Migrasi skema selalu expand → contract** (aturan emas #15). Jangan pernah menjalankan migrasi skema di malam yang
   sama dengan pindah server.

## Jalur B — salin + jeda tulis singkat (hosting bersama, tanpa replikasi)

### H-7 s.d. H-1: siapkan server baru di samping server lama

1. Pasang server baru: PHP 8.3+ (ekstensi sama dengan `Panduan/PasangDiHosting.md`), Nginx/Caddy, MariaDB 11.8,
   Redis (opsional, untuk antrean/cache), Supervisor untuk `queue:work`, cron untuk `schedule:run`.
2. Deploy kode **commit yang sama** dengan produksi. `.env` disalin dari hosting (`APP_KEY` sama), ganti hanya
   `DB_*`, disk berkas, dan `QUEUE_CONNECTION`/`CACHE_STORE` bila memakai Redis.
3. Gladi bersih: salin backup terbaru ke server baru, jalankan `php artisan migrate --pretend` (harus kosong), buka
   aplikasi lewat berkas `hosts` lokal, coba masuk, sinkron dari satu perangkat uji, jalankan
   `php artisan tenant:periksa-silang`. Catat lama `mysqldump` + impor → jadi perkiraan jeda tulis.
4. Salin berkas unggahan ke penyimpanan objek (`rclone sync`), ulangi sinkron berkas setiap hari sampai hari H.
5. Matikan cron jadwal di server baru dulu (jangan ada dua penjadwal mengirim WA/email ganda).

### Hari H (pilih jam paling sepi, mis. Senin 02.00 WIB; hindari tanggal tutup bulan & sekitar jadwal 05.30–07.15)

| Menit | Langkah |
|---|---|
| 0 | Umumkan lewat **Pengumuman platform** (konsol → Pengumuman, sasaran semua) sehari sebelumnya: "pemeliharaan singkat, kasir tetap bisa jual". |
| 0 | `rclone sync` berkas terakhir. |
| 1 | Server lama: `php artisan down --retry=60 --secret=<token-acak>`. Semua permintaan dapat **503 + Retry-After**; aplikasi kasir menyimpan ke outbox dan mencoba lagi. Hentikan `queue:work` & cron lama. |
| 1–3 | `mysqldump --single-transaction --routines --triggers --hex-blob` → impor ke server baru. |
| 3 | Server baru: `php artisan migrate --force` (seharusnya tidak ada yang dijalankan), `php artisan optimize`, nyalakan `queue:work` & cron. Cek cepat: jumlah baris `Penjualan`, `Jurnal`, `MutasiStok` sama dengan sumber; `php artisan persediaan:bangun-ulang-saldo --periksa` (keluar 0 = saldo stok cocok dengan buku mutasi). |
| 4 | Alihkan Cloudflare/DNS ke server baru. |
| 4–10 | Pantau: log galat, antrean, outbox perangkat uji terkirim (`Duplikat` untuk kiriman ulang adalah normal). |
| +1 hari | Server lama tetap dalam mode `down` (jangan dihapus) 7 hari sebagai cadangan kembali. |

**Rencana mundur:** bila ada masalah dalam 30 menit pertama dan **belum ada tulisan penting** di server baru,
kembalikan DNS ke server lama dan `php artisan up`. Setelah server baru menerima transaksi, jangan mundur ke server
lama (data akan bercabang); perbaiki maju.

**Yang terjadi di kasir selama jeda:** penjualan tetap tersimpan lokal dan tercetak; bilah status menunjukkan
"tertunda sinkron"; setelah server baru hidup outbox terkirim otomatis (pemicu sinkron K-6). Tidak ada tindakan kasir.

## Jalur A — replikasi (bila hosting memberi binlog, atau dari server sendiri ke server berikutnya)

1. Server baru dijadikan replika (`CHANGE MASTER TO ...` / `mariadb-dump --master-data` lalu `START SLAVE`) sampai
   tertinggal 0 detik.
2. Deploy aplikasi di server baru dalam keadaan **baca-saja ke replika** untuk uji.
3. Hari H: server lama `php artisan down` **beberapa detik** → tunggu replika tertinggal 0 → promosikan replika
   (`STOP SLAVE; RESET SLAVE ALL`) → alihkan Cloudflare → server baru `up`. Jeda tulis < 30 detik, masih diserap
   outbox kasir dan pengulangan klien.
4. Untuk perpindahan berikutnya (server sendiri → cluster), Jalur A ini dipakai, sehingga nol henti penuh.

## Ceklis kesiapan (isi saat gladi)

| Butir | Hasil |
|---|---|
| `log_bin` / hak replikasi di hosting | belum dicek |
| Ukuran basis data | — |
| Lama `mysqldump` + impor | — |
| DNS lewat Cloudflare / TTL 60 detik | belum |
| Disk berkas di penyimpanan objek | belum |
| `APP_KEY` tersimpan di pengelola sandi pemilik | belum dipastikan |
| Gladi restore lengkap berhasil | belum |
