import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Satu bagian isi area kerja (D-40): panel bergaris dengan judul (ikon opsional) dan isi. Dipakai layar non-Jual
/// supaya tiap kelompok informasi punya bingkai yang sama, bukan judul lepas di atas ruang kosong.
class BagianKerja extends StatelessWidget {
  const BagianKerja({super.key, required this.judul, required this.anak, this.ikon, this.ekor});

  final String judul;
  final Widget anak;
  final IconData? ikon;

  /// Isi kecil di kanan judul (misal jumlah atau tombol teks); null = tidak ada.
  final Widget? ekor;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return KotakPanel(
      anak: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              if (ikon != null) ...[
                Icon(ikon, size: TokenJarak.ikonSedang, color: warna.teksSekunder),
                const SizedBox(width: TokenJarak.jarak8),
              ],
              Expanded(
                child: Semantics(header: true, child: Text(judul, style: teks.titleSmall)),
              ),
              ?ekor,
            ],
          ),
          const SizedBox(height: TokenJarak.jarak8),
          anak,
        ],
      ),
    );
  }
}

/// Judul kelompok di panel konteks kanan (D-40).
class JudulPanelSamping extends StatelessWidget {
  const JudulPanelSamping(this.teks, {super.key});

  final String teks;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: TokenJarak.jarak8, bottom: TokenJarak.jarak8),
    child: Semantics(header: true, child: Text(teks, style: Theme.of(context).textTheme.titleSmall)),
  );
}

/// Baris "label ........ nilai" di panel konteks (D-40). [nilai] rata kanan; label tidak terpotong.
class BarisInfo extends StatelessWidget {
  const BarisInfo({super.key, required this.label, required this.nilai, this.tebal = false});

  final String label;
  final Widget nilai;
  final bool tebal;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak4 + 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(label, style: tebal ? teks.titleSmall : teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
          ),
          const SizedBox(width: TokenJarak.jarak12),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 240),
            child: DefaultTextStyle.merge(
              textAlign: TextAlign.right,
              style: tebal
                  ? teks.titleMedium
                  : teks.bodyMedium?.copyWith(color: warna.teksUtama, fontWeight: FontWeight.w600),
              child: nilai,
            ),
          ),
        ],
      ),
    );
  }
}

/// Satu butir daftar periksa (D-40), misal "Semua data terkirim" sebelum tutup shift. Ikon + teks selalu bersama:
/// [nada] Sukses = beres, Peringatan = perlu diperhatikan, Netral = langkah yang dikerjakan kasir sendiri.
class ButirPeriksa extends StatelessWidget {
  const ButirPeriksa({super.key, required this.judul, required this.nada, this.keterangan});

  final String judul;
  final String? keterangan;
  final NadaStatus nada;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final ikon = switch (nada) {
      NadaStatus.Sukses => Icons.check_circle,
      NadaStatus.Peringatan || NadaStatus.Bahaya => Icons.error_outline,
      NadaStatus.Netral || NadaStatus.Info => Icons.radio_button_unchecked,
    };
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(ikon, size: TokenJarak.ikonSedang, color: BilahStatus.AmbilWarnaNada(warna, nada)),
          const SizedBox(width: TokenJarak.jarak12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(judul, style: teks.labelLarge),
                if (keterangan case final String isi) Text(isi, style: teks.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
