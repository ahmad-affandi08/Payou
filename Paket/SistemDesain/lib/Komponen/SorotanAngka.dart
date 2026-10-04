import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';
import 'BilahStatus.dart';

/// Angka utama sebuah layar (D-40): satu angka besar yang menjawab pertanyaan pertama kasir di layar itu ("berapa
/// penjualan shift ini?", "berapa uang di laci?"), dengan label di atas dan keterangan di bawah. Satu per layar;
/// angka pendukung tetap memakai [KartuAngka].
///
/// [nada] hanya menegaskan nilai; label & keterangan selalu tampil (§17.6.11).
class SorotanAngka extends StatelessWidget {
  const SorotanAngka({
    super.key,
    required this.label,
    required this.nilai,
    this.ikon,
    this.keterangan,
    this.nada = NadaStatus.Netral,
    this.ekor,
  });

  final String label;

  /// Biasanya `TeksUang` atau `Text`; gaya teksnya (ukuran tampilan) diwarisi dari sorotan.
  final Widget nilai;
  final IconData? ikon;

  /// Satu atau dua baris kecil di bawah angka (misal "87 transaksi | rata-rata Rp 48.700").
  final String? keterangan;
  final NadaStatus nada;

  /// Isi tambahan di bawah keterangan (misal deret lencana atau tombol); null = tidak ada.
  final Widget? ekor;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final warnaNilai = nada == NadaStatus.Netral ? warna.teksUtama : BilahStatus.AmbilWarnaNada(warna, nada);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: warna.permukaan,
        border: Border.all(color: warna.garis, width: TokenJarak.tebalGaris),
        borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
      ),
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                if (ikon != null) ...[
                  Icon(ikon, size: TokenJarak.ikonSedang, color: warna.brand),
                  const SizedBox(width: TokenJarak.jarak8),
                ],
                Expanded(
                  child: Text(label, style: teks.labelLarge?.copyWith(color: warna.teksSekunder)),
                ),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak8),
            DefaultTextStyle.merge(
              maxLines: 1,
              style: teks.displaySmall?.copyWith(color: warnaNilai),
              child: FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: nilai),
            ),
            if (keterangan case final String isi) ...[
              const SizedBox(height: TokenJarak.jarak4),
              Text(isi, style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
            ],
            if (ekor case final Widget isi) ...[const SizedBox(height: TokenJarak.jarak16), isi],
          ],
        ),
      ),
    );
  }
}
