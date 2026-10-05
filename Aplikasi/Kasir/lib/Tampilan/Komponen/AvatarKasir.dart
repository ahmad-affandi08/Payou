import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Lingkaran berisi inisial nama (maks. dua huruf) untuk memilih kasir tanpa foto. Warnanya diturunkan dari token merek.
class AvatarKasir extends StatelessWidget {
  const AvatarKasir({super.key, required this.nama, this.ukuran = 48});

  final String nama;
  final double ukuran;

  /// Dua huruf pertama dari kata-kata nama; "Rina Wulandari" → "RW", "Budi" → "B".
  static String Inisial(String nama) {
    final kata = nama.trim().split(RegExp(r'\s+')).where((k) => k.isNotEmpty).toList();
    if (kata.isEmpty) {
      return '?';
    }
    final awal = kata.first.substring(0, 1);
    return (kata.length == 1 ? awal : awal + kata.last.substring(0, 1)).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    return ExcludeSemantics(
      child: Container(
        width: ukuran,
        height: ukuran,
        alignment: Alignment.center,
        decoration: BoxDecoration(shape: BoxShape.circle, color: warna.brand.withValues(alpha: 0.12)),
        child: Text(
          Inisial(nama),
          style: TextStyle(
            fontFamily: fontUtama,
            package: paketFont,
            fontSize: ukuran * 0.38,
            fontWeight: FontWeight.w700,
            color: warna.brand,
          ),
        ),
      ),
    );
  }
}
