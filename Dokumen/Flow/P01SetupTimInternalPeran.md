<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-01 · Setup Tim Internal & Peran

**Tujuan:** Hanya orang yang berwenang yang bisa mengakses Platform Pengelola, dengan hak sesuai tugasnya.
**Aktor:** Pemilik platform, Super Admin.
**Pemicu:** Instalasi pertama sistem, atau penambahan anggota tim.

**Langkah:**
1. Super Admin pertama dibuat lewat perintah server `php artisan pengelola:buat-super-admin` (tidak ada halaman daftar publik untuk pengelola).
2. Sistem membuat peran internal default (§19.3): Super Admin, Keuangan, Dukungan, Teknis, Konten & Legal, Mitra & Penjualan, Analis.
3. Super Admin **menambah anggota langsung** (nama, email, kata sandi awal, peran; D-22) atau mengundang lewat email (berlaku 48 jam). Anggota yang ditambah langsung wajib mengganti kata sandi awal saat pertama masuk, sebelum aktivasi 2FA.
4. Anggota tim membuat kata sandi dan **wajib mengaktifkan 2FA** sebelum bisa membuka menu apa pun.
5. Super Admin menetapkan peran. Satu orang boleh punya lebih dari satu peran.
6. Anggota yang keluar dinonaktifkan (tidak dihapus): sesi langsung diputus, token dicabut, riwayat audit tetap ada.

**Aturan Bisnis:**
- BR-P01.1 Minimal **2 Super Admin aktif** setiap saat: sistem menolak menonaktifkan atau mencabut peran Super Admin bila jumlah Super Admin aktif ≤ 2. Selama Super Admin aktif < 2 (misal setelah instalasi pertama), Platform Pengelola menampilkan peringatan untuk segera mengundang Super Admin kedua.
- BR-P01.2 2FA wajib untuk semua akun pengelola. Sesi berakhir setelah 30 menit tidak aktif. **Perangkat tepercaya (D-42):** saat memasukkan kode 2FA, anggota boleh mencentang "Percayai perangkat ini"; login berikutnya dari browser itu cukup kata sandi selama 90 hari (`PerangkatTepercayaPengelola`, hanya hash token disimpan, maksimal 10 perangkat aktif per akun). Perangkat tepercaya dicabut otomatis saat kata sandi diganti atau akun dinonaktifkan, dan bisa dicabut manual oleh pemiliknya (Keamanan akun) atau Super Admin (Tim internal). Aksi berbahaya (tambah/undang/ubah peran/nonaktifkan anggota, cabut perangkat anggota, ubah integrasi, tangguhkan/aktifkan tenant) **tetap meminta kode 2FA** bila kode terakhir di sesi itu dimasukkan lebih dari 15 menit lalu; login lewat perangkat tepercaya tidak dihitung sebagai memasukkan kode. Pembatasan IP (allowlist) opsional **ditunda** sampai cakupannya diputuskan (§25 no. 13).
- BR-P01.3 Tidak ada akun bersama. Setiap aksi pengelola tercatat di `LogAuditPengelola` (siapa, apa, kapan, tenant terdampak, nilai lama/baru, alasan, IP).
- BR-P01.4 Akun pengelola **terpisah** dari akun tenant (tabel `PenggunaPengelola`, guard `pengelola`). Email yang sama boleh dipakai di keduanya, tetapi sesinya tidak pernah tercampur.

**AC:**
```gherkin
Given anggota tim baru menerima undangan dan membuat kata sandi
When ia mencoba membuka menu Manajemen Tenant sebelum mengaktifkan 2FA
Then ia diarahkan ke halaman aktivasi 2FA dan akses menu ditolak
```
