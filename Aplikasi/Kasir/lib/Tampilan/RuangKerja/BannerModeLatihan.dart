import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';

/// K-23 (POS-18): penanda selalu terlihat selama mode latihan aktif, supaya kasir tidak mengira transaksi nyata
/// sedang dicatat. "Matikan" langsung kembali ke mode jual biasa.
class BannerModeLatihan extends ConsumerWidget {
  const BannerModeLatihan({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return Semantics(
      container: true,
      label: 'Mode latihan aktif',
      child: ColoredBox(
        key: const ValueKey('BannerModeLatihan'),
        color: warna.peringatan.withValues(alpha: 0.12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak4),
          child: Row(
            children: [
              Icon(Icons.school_outlined, color: warna.peringatan, size: TokenJarak.ikonKecil),
              const SizedBox(width: TokenJarak.jarak8),
              Expanded(
                child: Text(
                  'MODE LATIHAN | transaksi tidak disimpan & tidak dicetak',
                  style: teks.labelLarge?.copyWith(color: warna.peringatan),
                  maxLines: 2,
                ),
              ),
              TextButton(
                onPressed: () => ref.read(penyediaModeLatihan.notifier).Atur(false),
                child: const Text('Matikan'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
