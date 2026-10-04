import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Satu batang [GrafikBatang]: [rasio] 0–1 terhadap batang tertinggi, [label] di bawah sumbu, [keterangan] untuk
/// pembaca layar & tooltip (misal "10.00–11.00: Rp 845.000").
@immutable
class BatangGrafik {
  const BatangGrafik({required this.label, required this.rasio, required this.keterangan});

  final String label;
  final num rasio;
  final String keterangan;
}

/// Grafik batang kecil tanpa pustaka (D-40), misal penjualan per jam selama shift. Satu warna merek; batang kosong
/// tetap digambar setipis garis supaya jam sepi terbaca sebagai "nol", bukan data hilang. Setiap batang punya
/// tooltip & label semantik berisi angka sebenarnya.
class GrafikBatang extends StatelessWidget {
  const GrafikBatang({super.key, required this.batang, this.tinggi = 120});

  final List<BatangGrafik> batang;
  final double tinggi;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return SizedBox(
      height: tinggi + TokenJarak.jarak24,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (final b in batang)
            Expanded(
              child: Tooltip(
                message: b.keterangan,
                child: Semantics(
                  label: b.keterangan,
                  excludeSemantics: true,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 2),
                        child: Container(
                          height: (tinggi * b.rasio.clamp(0, 1)).clamp(2, tinggi).toDouble(),
                          decoration: BoxDecoration(
                            color: b.rasio > 0 ? warna.brand : warna.garis,
                            borderRadius: const BorderRadius.vertical(top: Radius.circular(3)),
                          ),
                        ),
                      ),
                      const SizedBox(height: TokenJarak.jarak4),
                      SizedBox(
                        height: 18,
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(b.label, style: teks.bodySmall?.copyWith(color: warna.teksSekunder)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
