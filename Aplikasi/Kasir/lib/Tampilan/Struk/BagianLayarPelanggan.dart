import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Perangkat/LayananLayarPelanggan.dart';

/// Pengaturan layar pelanggan di layar Pengaturan (PRD §17.2.5a, POS-15, v2.01): tidak dipakai, layar kedua
/// (Android: POS dua layar / HDMI), atau layar VFD lewat COM port (Windows). Tombol "Tampilkan contoh" menguji layar.
class BagianLayarPelanggan extends ConsumerStatefulWidget {
  const BagianLayarPelanggan({super.key});

  static String AmbilLabel(String mode) => switch (mode) {
    ModeLayarPelanggan.layarKedua => 'Layar kedua / HDMI',
    ModeLayarPelanggan.vfd => 'Layar VFD (COM port)',
    _ => 'Tidak dipakai',
  };

  @override
  ConsumerState<BagianLayarPelanggan> createState() => _BagianLayarPelangganState();
}

class _BagianLayarPelangganState extends ConsumerState<BagianLayarPelanggan> {
  final _port = TextEditingController();
  String? _pesan;
  var _galat = false;

  @override
  void initState() {
    super.initState();
    _port.text = ref.read(penyediaLayarPelanggan).portVfd ?? '';
  }

  @override
  void dispose() {
    _port.dispose();
    super.dispose();
  }

  Future<void> _Simpan(PengaturanLayarPelanggan baru) async {
    final galat = await ref.read(penyediaLayarPelanggan.notifier).Simpan(baru);
    if (mounted) {
      setState(() {
        _pesan = galat ?? (baru.aktif ? 'Layar pelanggan disimpan.' : 'Layar pelanggan dimatikan.');
        _galat = galat != null;
      });
    }
  }

  Future<void> _Contoh() async {
    final layar = ref.read(penyediaLayarPelanggan);
    final galat = await ref
        .read(penyediaLayarPelanggan.notifier)
        .Tampilkan(PenyusunLayarPelanggan.Siaga(layar.namaToko.isEmpty ? 'Payoung' : layar.namaToko));
    if (mounted) {
      setState(() {
        _pesan = galat ?? 'Contoh dikirim. Pastikan layar pelanggan menampilkan "Selamat datang".';
        _galat = galat != null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final layar = ref.watch(penyediaLayarPelanggan);
    final mode = ref.watch(penyediaModeLayarPelanggan);
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            for (final m in mode)
              ChoiceChip(
                label: Text(BagianLayarPelanggan.AmbilLabel(m)),
                selected: layar.mode == m,
                showCheckmark: false,
                onSelected: (_) => unawaited(_Simpan(layar.Salin(mode: m))),
              ),
          ],
        ),
        if (mode.length == 1)
          Padding(
            padding: const EdgeInsets.only(top: TokenJarak.jarak8),
            child: Text(
              'Perangkat ini belum mendukung layar pelanggan. Layar kedua tersedia di Android, layar VFD di Windows.',
              style: teks.bodySmall,
            ),
          ),
        if (layar.mode == ModeLayarPelanggan.vfd) ...[
          const SizedBox(height: TokenJarak.jarak12),
          Wrap(
            spacing: TokenJarak.jarak8,
            runSpacing: TokenJarak.jarak8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              SizedBox(
                width: 160,
                child: TextField(
                  controller: _port,
                  decoration: const InputDecoration(labelText: 'COM port', hintText: 'COM3'),
                ),
              ),
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: OutlinedButton(
                  onPressed: () => unawaited(_Simpan(layar.Salin(portVfd: _port.text.trim().toUpperCase()))),
                  child: const Text('Simpan port'),
                ),
              ),
            ],
          ),
        ],
        if (layar.aktif) ...[
          const SizedBox(height: TokenJarak.jarak12),
          SizedBox(
            height: TokenJarak.targetSentuh,
            child: OutlinedButton.icon(
              onPressed: () => unawaited(_Contoh()),
              icon: const Icon(Icons.connected_tv_outlined),
              label: const Text('Tampilkan contoh'),
            ),
          ),
        ],
        if (_pesan != null)
          Padding(
            padding: const EdgeInsets.only(top: TokenJarak.jarak8),
            child: Text(_pesan!, style: teks.bodyMedium?.copyWith(color: _galat ? warna.bahaya : warna.teksSekunder)),
          ),
      ],
    );
  }
}
