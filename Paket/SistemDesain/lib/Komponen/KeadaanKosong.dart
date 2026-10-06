import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Pilihan ilustrasi keadaan kosong (D-68): set merek di `assets/ilustrasi/`, nama berkas = nama nilai.
enum IlustrasiKosong { Umum, Penjualan, Laporan, Akuntansi, Pelanggan, Pembelian, Outlet }

/// Keadaan kosong (§17.6.6, D-40, D-68): satu kalimat yang menjelaskan **apa yang akan muncul di sini**, dan aksi
/// opsional untuk mengisinya. Bentuk penuh menampilkan ilustrasi merek (D-68); bentuk `ringkas` (panel samping,
/// bagian kecil) tetap ikon dalam lingkaran netral supaya tidak memakan tempat. Ilustrasi gagal dimuat = ikon.
class KeadaanKosong extends StatelessWidget {
  const KeadaanKosong({
    super.key,
    required this.ikon,
    required this.judul,
    this.keterangan,
    this.aksi,
    this.ringkas = false,
    this.ilustrasi = IlustrasiKosong.Umum,
  });

  final IconData ikon;
  final String judul;
  final String? keterangan;

  /// Tombol untuk mengisi bagian ini (misal "Catat kas masuk"); null = tidak ada.
  final Widget? aksi;

  /// Untuk panel samping & bagian kecil: ikon lebih kecil, jarak lebih rapat.
  final bool ringkas;

  /// Ilustrasi merek untuk bentuk penuh; diabaikan saat [ringkas].
  final IlustrasiKosong ilustrasi;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final ukuranLingkaran = ringkas ? 40.0 : 56.0;
    final lingkaranIkon = Container(
      width: ukuranLingkaran,
      height: ukuranLingkaran,
      decoration: BoxDecoration(
        color: warna.latar,
        shape: BoxShape.circle,
        border: Border.all(color: warna.garis),
      ),
      child: Icon(ikon, size: ringkas ? TokenJarak.ikonSedang : TokenJarak.ikonBesar, color: warna.teksSekunder),
    );
    return Padding(
      padding: EdgeInsets.symmetric(
        horizontal: TokenJarak.jarak16,
        vertical: ringkas ? TokenJarak.jarak16 : TokenJarak.jarak32,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (ringkas)
            lingkaranIkon
          else
            Image.asset(
              'assets/ilustrasi/${ilustrasi.name}.png',
              package: 'sistem_desain',
              key: const ValueKey('IlustrasiKosong'),
              width: 160,
              height: 160,
              excludeFromSemantics: true,
              errorBuilder: (_, _, _) => lingkaranIkon,
            ),
          SizedBox(height: ringkas ? TokenJarak.jarak8 : TokenJarak.jarak12),
          Text(judul, textAlign: TextAlign.center, style: ringkas ? teks.labelLarge : teks.titleSmall),
          if (keterangan case final String isi) ...[
            const SizedBox(height: TokenJarak.jarak4),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Text(
                isi,
                textAlign: TextAlign.center,
                style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
              ),
            ),
          ],
          if (aksi case final Widget tombol) ...[const SizedBox(height: TokenJarak.jarak12), tombol],
        ],
      ),
    );
  }
}
