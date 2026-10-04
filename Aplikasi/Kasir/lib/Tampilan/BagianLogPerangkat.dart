import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/Diagnostik/LogLokal.dart';
import 'Komponen/FormatWaktu.dart';

/// K-21: ringkasan log perangkat di Pengaturan: jumlah galat yang belum terkirim, lihat 50 entri terakhir, dan kirim
/// laporan sekarang (biasanya terkirim otomatis setelah sinkron). Isi log sudah disaring dari data pribadi.
class BagianLogPerangkat extends ConsumerStatefulWidget {
  const BagianLogPerangkat({super.key});

  @override
  ConsumerState<BagianLogPerangkat> createState() => _BagianLogPerangkatState();
}

class _BagianLogPerangkatState extends ConsumerState<BagianLogPerangkat> {
  int? _belumTerkirim;
  String? _pesan;
  bool _sibuk = false;

  @override
  void initState() {
    super.initState();
    unawaited(_Hitung());
  }

  Future<void> _Hitung() async {
    final jumlah = (await ref.read(penyediaLogLokal)?.AmbilBelumTerkirim())?.length;
    if (mounted) {
      setState(() => _belumTerkirim = jumlah);
    }
  }

  Future<void> _Kirim(LogLokal log) async {
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    final terkirim = await log.KirimTertunda(ref.read(penyediaKlienPos));
    await _Hitung();
    if (mounted) {
      setState(() {
        _sibuk = false;
        _pesan = terkirim > 0
            ? '$terkirim laporan galat terkirim ke tim dukungan.'
            : (_belumTerkirim ?? 0) > 0
            ? 'Belum terkirim. Periksa koneksi internet lalu coba lagi.'
            : 'Tidak ada galat yang perlu dikirim.';
      });
    }
  }

  Future<void> _Lihat(LogLokal log) async {
    final entri = await log.AmbilTerbaru(batas: 50);
    if (!mounted) {
      return;
    }
    await showDialog<void>(
      context: context,
      builder: (konteks) {
        final teks = Theme.of(konteks).textTheme;
        final warna = TokenWarna.AmbilDari(konteks);
        return AlertDialog(
          title: const Text('Log perangkat'),
          content: SizedBox(
            width: 560,
            child: entri.isEmpty
                ? const Text('Belum ada catatan.')
                : ListView.separated(
                    shrinkWrap: true,
                    itemCount: entri.length,
                    separatorBuilder: (_, _) => const Divider(height: TokenJarak.jarak16),
                    itemBuilder: (_, i) {
                      final e = entri[i];
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${FormatWaktu.FormatTanggalJam(e.waktu)} | ${e.tingkat.nilai} | ${e.sumber}',
                            style: teks.labelMedium?.copyWith(
                              color: e.tingkat == TingkatLog.galat ? warna.bahaya : warna.teksSekunder,
                            ),
                          ),
                          Text(e.pesan, style: teks.bodySmall),
                        ],
                      );
                    },
                  ),
          ),
          actions: [TextButton(onPressed: () => Navigator.of(konteks).pop(), child: const Text('Tutup'))],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final log = ref.watch(penyediaLogLokal);
    final teks = Theme.of(context).textTheme;
    if (log == null) {
      return Text('Log tidak tersedia di perangkat ini.', style: teks.bodySmall);
    }
    final belum = _belumTerkirim;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          belum == null
              ? 'Menghitung…'
              : belum == 0
              ? 'Semua galat sudah terkirim.'
              : '$belum galat belum terkirim.',
          style: teks.bodyMedium,
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            OutlinedButton(onPressed: () => unawaited(_Lihat(log)), child: const Text('Lihat log')),
            FilledButton.tonal(
              onPressed: _sibuk ? null : () => unawaited(_Kirim(log)),
              child: Text(_sibuk ? 'Mengirim…' : 'Kirim laporan sekarang'),
            ),
          ],
        ),
        if (_pesan case final pesan?) ...[
          const SizedBox(height: TokenJarak.jarak8),
          Text(pesan, style: teks.bodySmall),
        ],
      ],
    );
  }
}
