import 'package:flutter/material.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Komponen/FormatWaktu.dart';

/// P-10 PGL-19: satu baris tenang di atas area kerja berisi pengumuman berprioritas tertinggi dari pengelola platform
/// (Penting, lalu Pemeliharaan, Yang baru, Info). Ketuk "Lihat" untuk membaca semuanya. Info & Yang baru bisa ditutup
/// selama aplikasi berjalan; Penting & Pemeliharaan tetap tampil sampai masa tampilnya habis di server.
class BannerPengumuman extends StatefulWidget {
  const BannerPengumuman({required this.pengumuman, super.key});

  final List<PengumumanAplikasi> pengumuman;

  @override
  State<BannerPengumuman> createState() => _KeadaanBannerPengumuman();
}

class _KeadaanBannerPengumuman extends State<BannerPengumuman> {
  final Set<String> _ditutup = {};

  List<PengumumanAplikasi> get _tampil => [
    for (final p in widget.pengumuman)
      if (!(p.bolehDitutup && _ditutup.contains(p.uuid))) p,
  ];

  static String TeksJadwal(PengumumanAplikasi p) => p.pemeliharaanMulai != null && p.pemeliharaanSelesai != null
      ? 'Jadwal: ${FormatWaktu.FormatTanggalJam(p.pemeliharaanMulai!)} – ${FormatWaktu.FormatTanggalJam(p.pemeliharaanSelesai!)}'
      : '';

  @override
  Widget build(BuildContext context) {
    final tampil = _tampil;
    if (tampil.isEmpty) {
      return const SizedBox.shrink();
    }
    final utama = tampil.first;
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final nada = AmbilWarna(warna, utama.jenis);
    final jadwal = TeksJadwal(utama);

    return Semantics(
      container: true,
      liveRegion: true,
      label: 'Pengumuman ${utama.labelJenis}: ${utama.judul}',
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: warna.permukaan,
          border: Border(
            left: BorderSide(color: nada, width: 4),
            bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak12, vertical: TokenJarak.jarak4),
        child: Row(
          children: [
            Icon(AmbilIkon(utama.jenis), color: nada, size: 20),
            const SizedBox(width: TokenJarak.jarak8),
            Expanded(
              child: Text(
                [utama.judul, if (jadwal.isNotEmpty) jadwal].join(' | '),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: teks.bodyMedium?.copyWith(color: warna.teksUtama),
              ),
            ),
            TextButton(
              onPressed: () => _BukaSemua(context, tampil),
              child: Text(tampil.length > 1 ? 'Lihat (${tampil.length})' : 'Lihat'),
            ),
            if (utama.bolehDitutup)
              IconButton(
                tooltip: 'Tutup pengumuman',
                icon: const Icon(Icons.close),
                onPressed: () => setState(() => _ditutup.add(utama.uuid)),
              ),
          ],
        ),
      ),
    );
  }

  static Color AmbilWarna(TokenWarna warna, JenisPengumuman jenis) => switch (jenis) {
    JenisPengumuman.Penting => warna.bahaya,
    JenisPengumuman.Pemeliharaan => warna.peringatan,
    JenisPengumuman.YangBaru => warna.sukses,
    JenisPengumuman.Info => warna.info,
  };

  static IconData AmbilIkon(JenisPengumuman jenis) => switch (jenis) {
    JenisPengumuman.Penting => Icons.priority_high,
    JenisPengumuman.Pemeliharaan => Icons.build_outlined,
    JenisPengumuman.YangBaru => Icons.auto_awesome_outlined,
    JenisPengumuman.Info => Icons.info_outline,
  };

  Future<void> _BukaSemua(BuildContext context, List<PengumumanAplikasi> daftar) => showDialog<void>(
    context: context,
    builder: (konteks) {
      final teks = Theme.of(konteks).textTheme;
      final warna = TokenWarna.AmbilDari(konteks);
      return AlertDialog(
        title: const Text('Pengumuman Payoung'),
        content: SizedBox(
          width: 520,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final p in daftar) ...[
                  Row(
                    children: [
                      Icon(AmbilIkon(p.jenis), color: AmbilWarna(warna, p.jenis), size: 20),
                      const SizedBox(width: TokenJarak.jarak8),
                      Expanded(child: Text('${p.labelJenis}: ${p.judul}', style: teks.titleSmall)),
                    ],
                  ),
                  const SizedBox(height: TokenJarak.jarak4),
                  Text(p.isi, style: teks.bodyMedium),
                  if (TeksJadwal(p).isNotEmpty) ...[
                    const SizedBox(height: TokenJarak.jarak4),
                    Text(TeksJadwal(p), style: teks.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
                  ],
                  if (p.tautan != null) ...[
                    const SizedBox(height: TokenJarak.jarak4),
                    SelectableText(p.tautan!, style: teks.bodySmall?.copyWith(color: warna.teksSekunder)),
                  ],
                  const SizedBox(height: TokenJarak.jarak16),
                ],
              ],
            ),
          ),
        ),
        actions: [TextButton(onPressed: () => Navigator.of(konteks).pop(), child: const Text('Tutup'))],
      );
    },
  );
}
