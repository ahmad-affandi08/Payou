import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Struk/ProfilPrinter.dart';
import 'EditorProfilPrinter.dart';

/// Atur printer struk perangkat ini di layar Pengaturan (PRD v1.79–v1.80): ringkasan printer tersimpan, cetak uji,
/// hapus, dan isian printer ([EditorProfilPrinter]: LAN/Wi-Fi, Bluetooth, Bluetooth LE, COM) dengan lebar kertas, cetak
/// otomatis, dan buka laci untuk tunai.
class BagianPrinterStruk extends ConsumerStatefulWidget {
  const BagianPrinterStruk({super.key});

  @override
  ConsumerState<BagianPrinterStruk> createState() => _BagianPrinterStrukState();
}

class _BagianPrinterStrukState extends ConsumerState<BagianPrinterStruk> {
  var _mengubah = false;
  var _sibuk = false;
  String? _pesan;
  var _pesanGalat = false;

  void _MulaiUbah() => setState(() {
    _pesan = null;
    _mengubah = true;
  });

  Future<void> _CetakUji(ProfilPrinter profil) async {
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    final galat = await ref.read(penyediaPrinter.notifier).CetakUji(profil);
    if (!mounted) {
      return;
    }
    setState(() {
      _sibuk = false;
      _pesan = galat ?? 'Halaman uji terkirim. Pastikan semua baris lurus dan tidak terpotong.';
      _pesanGalat = galat != null;
    });
  }

  Future<void> _Simpan(ProfilPrinter profil) async {
    await ref.read(penyediaPrinter.notifier).SimpanProfil(profil);
    if (mounted) {
      setState(() {
        _mengubah = false;
        _pesan = 'Printer struk disimpan.';
        _pesanGalat = false;
      });
    }
  }

  Future<void> _Hapus() async {
    await ref.read(penyediaPrinter.notifier).HapusProfil();
    if (mounted) {
      setState(() {
        _mengubah = false;
        _pesan = 'Printer struk dihapus dari perangkat ini.';
        _pesanGalat = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final profil = ref.watch(penyediaPrinter).profil;
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);

    if (_mengubah) {
      return EditorProfilPrinter(
        profilAwal: profil,
        saatSimpan: _Simpan,
        saatBatal: () => setState(() => _mengubah = false),
        saatCetakUji: (p) => ref.read(penyediaPrinter.notifier).CetakUji(p),
      );
    }

    Widget Tombol(String label, IconData ikon, VoidCallback? aksi, {bool utama = false}) => SizedBox(
      height: TokenJarak.targetSentuh,
      child: utama
          ? FilledButton.icon(onPressed: _sibuk ? null : aksi, icon: Icon(ikon), label: Text(label))
          : OutlinedButton.icon(onPressed: _sibuk ? null : aksi, icon: Icon(ikon), label: Text(label)),
    );

    final isi = <Widget>[];
    if (profil == null) {
      isi.add(Tombol('Atur printer', Icons.print_outlined, _MulaiUbah, utama: true));
    } else {
      isi.addAll([
        Text('Printer ${profil.label}', style: teks.bodyLarge),
        Text(
          '${profil.cetakOtomatis ? 'Cetak otomatis setelah bayar' : 'Cetak manual'}'
          '${profil.bukaLaciTunai ? ' | buka laci untuk tunai' : ''}',
          style: teks.bodySmall,
        ),
        const SizedBox(height: TokenJarak.jarak12),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            Tombol('Cetak uji', Icons.print_outlined, () => _CetakUji(profil)),
            Tombol('Ubah', Icons.edit_outlined, _MulaiUbah),
            Tombol('Hapus printer', Icons.delete_outline, _Hapus),
          ],
        ),
      ]);
    }
    if (_pesan != null) {
      isi.add(
        Padding(
          padding: const EdgeInsets.only(top: TokenJarak.jarak8),
          child: Text(
            _pesan!,
            style: teks.bodyMedium?.copyWith(color: _pesanGalat ? warna.bahaya : warna.teksSekunder),
          ),
        ),
      );
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: isi);
  }
}
