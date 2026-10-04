import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Satu baris perbandingan (D-40): label di kiri, nilai di kanan, dan batang tipis di bawahnya sepanjang [rasio]
/// (0–1) dari baris terbesar. Dipakai untuk rincian per metode bayar & produk terlaris. Angka selalu tertulis;
/// batang hanya membantu membandingkan sekilas.
class BatangProporsi extends StatelessWidget {
  const BatangProporsi({super.key, required this.label, required this.nilai, required this.rasio, this.keterangan});

  final String label;

  /// Biasanya `TeksUang`; rata kanan.
  final Widget nilai;

  /// Panjang batang relatif, dipotong ke 0–1.
  final num rasio;

  /// Teks kecil di samping label (misal "32×"); null = tidak ada.
  final String? keterangan;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text.rich(
                  TextSpan(
                    children: [
                      TextSpan(text: label),
                      if (keterangan case final String isi)
                        TextSpan(
                          text: '  $isi',
                          style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                        ),
                    ],
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: teks.bodyMedium,
                ),
              ),
              const SizedBox(width: TokenJarak.jarak12),
              DefaultTextStyle.merge(style: teks.labelLarge, child: nilai),
            ],
          ),
          const SizedBox(height: TokenJarak.jarak4),
          ClipRRect(
            borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
            child: SizedBox(
              height: 6,
              child: Stack(
                children: [
                  Positioned.fill(child: ColoredBox(color: warna.latar)),
                  FractionallySizedBox(
                    widthFactor: rasio.clamp(0, 1).toDouble(),
                    heightFactor: 1,
                    child: ColoredBox(color: warna.brand),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
