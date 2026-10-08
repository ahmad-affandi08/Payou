import 'package:flutter/material.dart';

import '../Token/TokenJarak.dart';
import '../Token/TokenWarna.dart';

/// Hasil sebuah aksi yang harus terlihat jelas oleh kasir (padanan `DialogHasil` web, D-51): ikon besar berwarna,
/// judul, alasan dalam bahasa sehari-hari, dan satu tombol. Dipakai untuk aksi yang gagal (ditolak aturan atau server,
/// atau tak terkirim) dan aksi penting yang berhasil, supaya kasir tidak perlu menebak apakah ketukannya berhasil dan
/// tidak bergantung pada teks kecil di dasar formulir yang bisa tertutup papan ketik atau di luar layar.
class DialogHasil extends StatelessWidget {
  const DialogHasil({
    super.key,
    required this.berhasil,
    required this.judul,
    required this.pesan,
    this.labelTombol = 'Mengerti',
  });

  final bool berhasil;
  final String judul;
  final String pesan;
  final String labelTombol;

  /// Tampilkan dialog dan tunggu sampai kasir menutupnya.
  static Future<void> Tampilkan(
    BuildContext context, {
    required bool berhasil,
    required String judul,
    required String pesan,
    String labelTombol = 'Mengerti',
  }) => showDialog<void>(
    context: context,
    builder: (_) => DialogHasil(berhasil: berhasil, judul: judul, pesan: pesan, labelTombol: labelTombol),
  );

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final nada = berhasil ? warna.sukses : warna.bahaya;
    return AlertDialog(
      key: ValueKey(berhasil ? 'DialogHasilBerhasil' : 'DialogHasilGagal'),
      insetPadding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak24),
      contentPadding: const EdgeInsets.fromLTRB(
        TokenJarak.jarak24,
        TokenJarak.jarak24,
        TokenJarak.jarak24,
        TokenJarak.jarak8,
      ),
      content: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 64,
                  height: 64,
                  decoration: BoxDecoration(shape: BoxShape.circle, color: nada.withValues(alpha: 0.12)),
                  child: Icon(
                    berhasil ? Icons.check_circle_outline : Icons.error_outline,
                    size: 40,
                    color: nada,
                    semanticLabel: berhasil ? 'Berhasil' : 'Gagal',
                  ),
                ),
              ),
              const SizedBox(height: TokenJarak.jarak16),
              Semantics(
                header: true,
                liveRegion: true,
                child: Text(judul, style: teks.titleLarge, textAlign: TextAlign.center),
              ),
              const SizedBox(height: TokenJarak.jarak8),
              Text(pesan, style: teks.bodyMedium, textAlign: TextAlign.center),
            ],
          ),
        ),
      ),
      actionsAlignment: MainAxisAlignment.center,
      actionsPadding: const EdgeInsets.fromLTRB(
        TokenJarak.jarak24,
        TokenJarak.jarak8,
        TokenJarak.jarak24,
        TokenJarak.jarak24,
      ),
      actions: [
        SizedBox(
          width: double.infinity,
          height: TokenJarak.targetSentuh,
          child: FilledButton(autofocus: true, onPressed: () => Navigator.of(context).pop(), child: Text(labelTombol)),
        ),
      ],
    );
  }
}

/// Umpan hasil aksi yang seragam di seluruh aplikasi (D-51 untuk POS). Aturan pakai:
/// - validasi isian (jumlah kosong, kategori belum dipilih) tetap teks di dekat isiannya, bukan dialog;
/// - aksi yang ditolak aturan, server, atau perangkat → [Gagal] (dialog, harus diakui) dengan alasan dan langkah berikutnya;
/// - aksi yang berhasil → [Berhasil] (notifikasi melayang berikon, tidak menghalangi ketukan berikutnya);
/// - hasil penting yang harus dibaca (transaksi dibatalkan, shift ditutup) → [BerhasilPenting] (dialog).
abstract final class UmpanAksi {
  /// Notifikasi melayang berikon centang; hilang sendiri. Diabaikan bila tidak ada `Scaffold` di atas [context].
  static void Berhasil(BuildContext context, String pesan) {
    final pemberitahu = ScaffoldMessenger.maybeOf(context);
    if (pemberitahu == null) {
      return;
    }
    final warna = TokenWarna.AmbilDari(context);
    pemberitahu
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          key: const ValueKey('UmpanBerhasil'),
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 4),
          content: Row(
            children: [
              Icon(Icons.check_circle, color: warna.sukses, semanticLabel: 'Berhasil'),
              const SizedBox(width: TokenJarak.jarak12),
              Expanded(child: Text(pesan)),
            ],
          ),
        ),
      );
  }

  /// Dialog "belum berhasil" dengan [pesan] alasan dan langkah berikutnya; selesai saat kasir menutupnya.
  static Future<void> Gagal(BuildContext context, {String judul = 'Belum berhasil', required String pesan}) =>
      DialogHasil.Tampilkan(context, berhasil: false, judul: judul, pesan: pesan);

  /// Dialog hasil berhasil untuk aksi yang akibatnya perlu dibaca (void, retur, tutup shift).
  static Future<void> BerhasilPenting(
    BuildContext context, {
    required String judul,
    required String pesan,
    String labelTombol = 'Oke',
  }) => DialogHasil.Tampilkan(context, berhasil: true, judul: judul, pesan: pesan, labelTombol: labelTombol);
}
