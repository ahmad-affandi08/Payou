import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import 'BilahSaringan.dart';
import 'FormatTampilan.dart';
import 'KeadaanData.dart';

/// Shift kasir pada tanggal terpilih: siapa, kapan, dan selisih kas saat tutup shift (OWN-03/05).
class LayarShift extends ConsumerWidget {
  const LayarShift({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final shift = ref.watch(penyediaShift);
    final outlet = ref.watch(penyediaDasbor).value?.outlet ?? const [];
    return ListView(
      padding: const EdgeInsets.all(TokenJarak.jarak16),
      children: [
        BilahSaringan(outlet: outlet),
        const SizedBox(height: TokenJarak.jarak12),
        KeadaanData(
          nilai: shift,
          saatCobaLagi: () => ref.invalidate(penyediaShift),
          isi: (daftar) => daftar.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(TokenJarak.jarak24),
                  child: Text('Belum ada shift pada tanggal ini.', style: teks.bodyMedium),
                )
              : Column(
                  children: [
                    for (final s in daftar)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('${s.kasir} | ${s.outlet}'),
                        subtitle: Text(
                          [
                            if (s.dibukaPada != null) 'Buka ${FormatTampilan.TanggalJam(s.dibukaPada!)}',
                            if (s.ditutupPada != null) 'tutup ${FormatTampilan.TanggalJam(s.ditutupPada!)}',
                            if (s.ditutupPada == null) 'masih berjalan',
                          ].join(', '),
                        ),
                        trailing: s.selisih == null
                            ? Text(s.status, style: teks.bodySmall)
                            : Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text('Selisih', style: teks.bodySmall),
                                  Text(
                                    FormatTampilan.Rupiah(s.selisih!),
                                    style: teks.titleSmall?.copyWith(
                                      color: (double.tryParse(s.selisih!) ?? 0) == 0 ? warna.sukses : warna.bahaya,
                                    ),
                                  ),
                                ],
                              ),
                      ),
                  ],
                ),
        ),
      ],
    );
  }
}
