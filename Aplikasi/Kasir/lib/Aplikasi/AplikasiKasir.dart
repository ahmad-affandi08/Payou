import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Tampilan/GerbangKasir.dart';
import 'Lingkungan.dart';
import 'Penyedia.dart';

/// Akar widget Aplikasi POS (PRD §17.2). Harus berada di dalam `ProviderScope` (lihat `Persiapan.dart`). Ukuran
/// tampilan perangkat (Normal/Besar, §17.2.7) diterapkan ke seluruh aplikasi lewat skala teks `MediaQuery`, dikalikan
/// dengan skala teks aksesibilitas sistem.
class AplikasiKasir extends ConsumerWidget {
  const AplikasiKasir({super.key, required this.lingkungan});

  final Lingkungan lingkungan;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final skala = ref.watch(penyediaPengaturanPerangkat.select((p) => p.ukuran.skalaTeks));
    return MaterialApp(
      title: 'Payoung POS',
      debugShowCheckedModeBanner: false,
      // Hanya tema terang, tanpa darkTheme (D-14).
      theme: BuatTema(),
      themeMode: ThemeMode.light,
      builder: (context, anak) {
        final media = MediaQuery.of(context);
        final berskala = MediaQuery(
          data: media.copyWith(textScaler: _SkalaGabungan(media.textScaler, skala)),
          child: anak!,
        );
        return lingkungan.tampilkanPenanda
            ? Banner(
                message: lingkungan.label,
                location: BannerLocation.topEnd,
                color: TokenWarna.AmbilDari(context).peringatan,
                child: berskala,
              )
            : berskala;
      },
      home: const GerbangKasir(),
    );
  }
}

/// Skala teks sistem (aksesibilitas) dikali skala ukuran tampilan perangkat.
TextScaler _SkalaGabungan(TextScaler sistem, num skala) =>
    skala == 1 ? sistem : TextScaler.linear(sistem.scale(100) / 100 * skala);
