import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Katalog/KatalogLokal.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Komponen/FormatAngka.dart';
import 'DialogPesananMeja.dart';

/// Pesanan dari QR meja yang menunggu konfirmasi (F-17 self-order, v2.02), tampil di atas denah meja: meja, nama
/// pemesan, umur pesanan, item + pilihan + catatan, subtotal; "Terima & kirim ke dapur" atau "Tolak" (dengan alasan
/// yang ditampilkan ke tamu). Terima butuh koneksi (klaim di server agar dua perangkat tidak menerima pesanan sama).
class BagianPesanSendiri extends ConsumerStatefulWidget {
  const BagianPesanSendiri({super.key, required this.kasir, required this.saatPesan});

  final StafLokal kasir;

  /// Pesan hasil (berhasil/galat) untuk ditampilkan layar Meja.
  final void Function(String pesan) saatPesan;

  @override
  ConsumerState<BagianPesanSendiri> createState() => _BagianPesanSendiriState();
}

class _BagianPesanSendiriState extends ConsumerState<BagianPesanSendiri> {
  String? _diproses;

  String _Judul(PesananSendiriPos p) => [if (p.namaMeja != null) 'Meja ${p.namaMeja}', ?p.namaPemesan].join(' | ');

  Future<void> _Terima(PesananSendiriPos p) async {
    final k = ref.read(penyediaKonteksPenjualan).value;
    if (k == null) {
      widget.saatPesan('Data outlet belum siap. Tunggu sebentar lalu coba lagi.');
      return;
    }
    setState(() => _diproses = p.uuid);
    try {
      final pesanan = await ref
          .read(penyediaLayananPesanSendiri)
          .Terima(p, kasir: widget.kasir, katalog: ref.read(penyediaKatalog).value ?? KatalogLokal.kosong, k: k);
      ref.read(penyediaPesanSendiri.notifier).Hapus(p.uuid);
      unawaited(ref.read(penyediaSesi.notifier).Sinkronkan());
      widget.saatPesan('Pesanan ${p.nomor} diterima dan dikirim ke dapur (${pesanan.AmbilJudul()}).');
    } on GalatKasir catch (galat) {
      widget.saatPesan(galat.pesan);
    } on GalatApi catch (galat) {
      if (galat.kode == 'SudahDiproses' || galat.kode == 'Kedaluwarsa') {
        ref.read(penyediaPesanSendiri.notifier).Hapus(p.uuid);
      }
      widget.saatPesan(galat.pesan);
    } on GalatJaringan {
      widget.saatPesan('Menerima pesanan QR butuh internet. Periksa koneksi lalu coba lagi.');
    } finally {
      if (mounted) {
        setState(() => _diproses = null);
      }
    }
  }

  Future<void> _Tolak(PesananSendiriPos p) async {
    final alasan = await TanyaAlasan(
      context,
      judul: 'Tolak pesanan ${p.nomor}?',
      pesan: 'Alasan ditampilkan ke tamu di HP-nya, misalnya "Menu habis" atau "Salah meja".',
      labelTombol: 'Tolak pesanan',
      wajib: true,
      pilihan: const ['Menu habis', 'Salah meja', 'Dapur sudah tutup'],
    );
    if (alasan == null || !mounted) {
      return;
    }
    setState(() => _diproses = p.uuid);
    try {
      await ref.read(penyediaLayananPesanSendiri).Tolak(p, kasir: widget.kasir, alasan: alasan);
      ref.read(penyediaPesanSendiri.notifier).Hapus(p.uuid);
      widget.saatPesan('Pesanan ${p.nomor} ditolak.');
    } on GalatKasir catch (galat) {
      widget.saatPesan(galat.pesan);
    } on GalatApi catch (galat) {
      if (galat.kode == 'SudahDiproses' || galat.kode == 'Kedaluwarsa') {
        ref.read(penyediaPesanSendiri.notifier).Hapus(p.uuid);
      }
      widget.saatPesan(galat.pesan);
    } on GalatJaringan {
      widget.saatPesan('Menolak pesanan QR butuh internet. Periksa koneksi lalu coba lagi.');
    } finally {
      if (mounted) {
        setState(() => _diproses = null);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final daftar = ref.watch(penyediaPesanSendiri);
    if (daftar.isEmpty) {
      return const SizedBox.shrink();
    }
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final sekarang = ref.watch(penyediaJam)().toUtc();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: TokenJarak.jarak16),
        Row(
          children: [
            Icon(Icons.qr_code_2, color: warna.peringatan),
            const SizedBox(width: TokenJarak.jarak8),
            Expanded(child: Text('Pesanan QR menunggu konfirmasi (${daftar.length})', style: teks.titleMedium)),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak8),
        for (final p in daftar)
          Container(
            margin: const EdgeInsets.only(bottom: TokenJarak.jarak8),
            padding: const EdgeInsets.all(TokenJarak.jarak12),
            decoration: BoxDecoration(
              color: warna.permukaan,
              border: Border.all(color: warna.peringatan, width: TokenJarak.tebalGaris),
              borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(_Judul(p), style: teks.titleSmall)),
                    Text(
                      '${sekarang.difference(p.dibuatPada).inMinutes.clamp(0, 999)} menit lalu',
                      style: teks.bodySmall,
                    ),
                  ],
                ),
                Text(p.nomor, style: teks.bodySmall?.copyWith(color: warna.teksSekunder)),
                const SizedBox(height: TokenJarak.jarak8),
                for (final b in p.baris)
                  Padding(
                    padding: const EdgeInsets.only(bottom: TokenJarak.jarak4),
                    child: Text(
                      [
                        '${FormatAngka.FormatDesimal(Decimal.parse(b.jumlah))} × ${b.namaProduk}',
                        if (b.pilihan.isNotEmpty) '(${b.pilihan.map((x) => x['Nama']).join(', ')})',
                        if (b.catatan != null && b.catatan!.isNotEmpty) '| ${b.catatan}',
                      ].join(' '),
                      style: teks.bodyMedium,
                    ),
                  ),
                if (p.catatan != null && p.catatan!.isNotEmpty) Text('Catatan: ${p.catatan}', style: teks.bodySmall),
                const SizedBox(height: TokenJarak.jarak4),
                Text('Subtotal ${Uang.Dari(p.subtotal).FormatRupiah()}', style: teks.bodyMedium),
                const SizedBox(height: TokenJarak.jarak8),
                Wrap(
                  spacing: TokenJarak.jarak8,
                  runSpacing: TokenJarak.jarak8,
                  children: [
                    SizedBox(
                      height: TokenJarak.targetSentuh,
                      child: FilledButton.icon(
                        onPressed: _diproses != null ? null : () => unawaited(_Terima(p)),
                        icon: const Icon(Icons.check),
                        label: const Text('Terima & kirim ke dapur'),
                      ),
                    ),
                    SizedBox(
                      height: TokenJarak.targetSentuh,
                      child: OutlinedButton(
                        onPressed: _diproses != null ? null : () => unawaited(_Tolak(p)),
                        child: const Text('Tolak'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
      ],
    );
  }
}
