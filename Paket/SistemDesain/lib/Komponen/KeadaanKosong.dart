import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Keadaan kosong (§17.6.6, D-40): ikon dalam lingkaran netral, satu kalimat yang menjelaskan **apa yang akan
/// muncul di sini**, dan aksi opsional untuk mengisinya. Menggantikan satu baris teks abu-abu yang membuat bagian
/// terlihat rusak atau hampa. Bukan ilustrasi dekoratif: ikonnya sama dengan ikon fitur itu.
class KeadaanKosong extends StatelessWidget {
  const KeadaanKosong({
    super.key,
    required this.ikon,
    required this.judul,
    this.keterangan,
    this.aksi,
    this.ringkas = false,
  });

  final IconData ikon;
  final String judul;
  final String? keterangan;

  /// Tombol untuk mengisi bagian ini (misal "Catat kas masuk"); null = tidak ada.
  final Widget? aksi;

  /// Untuk panel samping & bagian kecil: ikon lebih kecil, jarak lebih rapat.
  final bool ringkas;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final ukuranLingkaran = ringkas ? 40.0 : 56.0;
    return Padding(
      padding: EdgeInsets.symmetric(
        horizontal: TokenJarak.jarak16,
        vertical: ringkas ? TokenJarak.jarak16 : TokenJarak.jarak32,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: ukuranLingkaran,
            height: ukuranLingkaran,
            decoration: BoxDecoration(
              color: warna.latar,
              shape: BoxShape.circle,
              border: Border.all(color: warna.garis),
            ),
            child: Icon(ikon, size: ringkas ? TokenJarak.ikonSedang : TokenJarak.ikonBesar, color: warna.teksSekunder),
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
