import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Perangkat/LayananUjiPerangkat.dart';

/// Wizard Uji Perangkat di layar Pengaturan (PRD §17.2.5a, v1.96): cetak halaman uji, potong kertas, laci kas, dan
/// pemindai barcode, masing-masing dijawab kasir (berhasil, gagal, atau tidak dipakai). Hasil disimpan di perangkat dan
/// dilaporkan ke server untuk dukungan teknis. Laci tidak dibuka dari sini: buka laci manual selalu lewat tombol
/// "Buka laci" di layar Kas agar tercatat (§19.2).
class BagianUjiPerangkat extends ConsumerStatefulWidget {
  const BagianUjiPerangkat({super.key});

  @override
  ConsumerState<BagianUjiPerangkat> createState() => _BagianUjiPerangkatState();
}

class _BagianUjiPerangkatState extends ConsumerState<BagianUjiPerangkat> {
  final _pindai = TextEditingController();
  var _berjalan = false;
  var _sibuk = false;
  var _hasil = const HasilUjiPerangkat();
  String? _pesan;
  var _pesanGalat = false;
  Map<String, Object?>? _terakhir;

  @override
  void initState() {
    super.initState();
    unawaited(_MuatTerakhir());
  }

  @override
  void dispose() {
    _pindai.dispose();
    super.dispose();
  }

  Future<void> _MuatTerakhir() async {
    final terakhir = await ref.read(penyediaLayananUjiPerangkat).AmbilTerakhir();
    if (mounted) {
      setState(() => _terakhir = terakhir);
    }
  }

  void _Mulai() => setState(() {
    _berjalan = true;
    _hasil = const HasilUjiPerangkat();
    _pindai.clear();
    _pesan = null;
  });

  Future<void> _CetakUji() async {
    final profil = ref.read(penyediaPrinter).profil;
    if (profil == null) {
      setState(() {
        _pesan = 'Atur printer dulu di bagian Printer (centang Cetak struk kasir), atau pilih "Tidak ada printer".';
        _pesanGalat = true;
      });
      return;
    }
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
      _pesan = galat ?? 'Halaman uji terkirim. Periksa hasil cetaknya, lalu jawab pertanyaan di bawah.';
      _pesanGalat = galat != null;
      if (galat != null) {
        _hasil = _hasil.Salin(cetak: HasilUji.Gagal);
      }
    });
  }

  Future<void> _Simpan() async {
    setState(() => _sibuk = true);
    final terkirim = await ref.read(penyediaLayananUjiPerangkat).Simpan(_hasil);
    if (!mounted) {
      return;
    }
    setState(() {
      _sibuk = false;
      _berjalan = false;
      _pesan = terkirim
          ? 'Hasil uji disimpan dan dilaporkan ke kantor pusat.'
          : 'Hasil uji disimpan di perangkat dan dikirim otomatis saat online.';
      _pesanGalat = false;
    });
    await _MuatTerakhir();
  }

  static String _Label(Object? hasil) => switch (hasil) {
    'Lolos' => 'berhasil',
    'Gagal' => 'gagal',
    'Dilewati' => 'tidak dipakai',
    _ => 'belum diuji',
  };

  Widget _Langkah(
    String judul,
    String penjelasan,
    HasilUji? nilai,
    void Function(HasilUji) saatPilih, {
    String labelDilewati = 'Tidak dipakai',
    List<Widget> tambahan = const [],
  }) {
    final teks = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: TokenJarak.jarak16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(judul, style: teks.titleSmall),
          const SizedBox(height: TokenJarak.jarak4),
          Text(penjelasan, style: teks.bodySmall),
          ...tambahan,
          const SizedBox(height: TokenJarak.jarak8),
          Wrap(
            spacing: TokenJarak.jarak8,
            runSpacing: TokenJarak.jarak8,
            children: [
              for (final (h, label) in [
                (HasilUji.Lolos, 'Berhasil'),
                (HasilUji.Gagal, 'Gagal'),
                (HasilUji.Dilewati, labelDilewati),
              ])
                ChoiceChip(
                  label: Text(label),
                  selected: nilai == h,
                  showCheckmark: false,
                  onSelected: _sibuk ? null : (_) => saatPilih(h),
                ),
            ],
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final pesan = _pesan == null
        ? null
        : Padding(
            padding: const EdgeInsets.only(bottom: TokenJarak.jarak12),
            child: Text(
              _pesan!,
              style: teks.bodyMedium?.copyWith(color: _pesanGalat ? warna.bahaya : warna.teksSekunder),
            ),
          );

    if (!_berjalan) {
      final uji = _terakhir?['Uji'];
      final ringkas = uji is Map
          ? 'Uji terakhir: cetak ${_Label(uji['Cetak'])}, potong ${_Label(uji['Potong'])}, '
                'laci ${_Label(uji['Laci'])}, pemindai ${_Label(uji['Pemindai'])}.'
          : 'Perangkat ini belum pernah diuji.';
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ?pesan,
          Text(ringkas, style: teks.bodyMedium),
          const SizedBox(height: TokenJarak.jarak8),
          SizedBox(
            height: TokenJarak.targetSentuh,
            child: OutlinedButton.icon(
              onPressed: _Mulai,
              icon: const Icon(Icons.fact_check_outlined),
              label: const Text('Mulai uji perangkat'),
            ),
          ),
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ?pesan,
        _Langkah(
          '1. Cetak struk',
          'Cetak halaman uji. Berhasil bila semua baris jelas, lurus, dan tidak terpotong.',
          _hasil.cetak,
          (h) => setState(() => _hasil = _hasil.Salin(cetak: h)),
          labelDilewati: 'Tidak ada printer',
          tambahan: [
            const SizedBox(height: TokenJarak.jarak8),
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: OutlinedButton.icon(
                onPressed: _sibuk ? null : _CetakUji,
                icon: const Icon(Icons.print_outlined),
                label: const Text('Cetak halaman uji'),
              ),
            ),
          ],
        ),
        _Langkah(
          '2. Potong kertas',
          'Setelah halaman uji keluar, apakah kertas terpotong otomatis?',
          _hasil.potong,
          (h) => setState(() => _hasil = _hasil.Salin(potong: h)),
          labelDilewati: 'Tidak ada pemotong',
        ),
        _Langkah(
          '3. Laci kas',
          'Buka laci lewat tombol "Buka laci" di layar Kas (tercatat) atau saat menerima pembayaran tunai. '
              'Apakah laci terbuka?',
          _hasil.laci,
          (h) => setState(() => _hasil = _hasil.Salin(laci: h)),
          labelDilewati: 'Tidak pakai laci',
        ),
        _Langkah(
          '4. Pemindai barcode',
          'Ketuk kotak di bawah lalu pindai barcode produk apa saja.',
          _hasil.pemindai,
          (h) => setState(() => _hasil = _hasil.Salin(pemindai: h)),
          labelDilewati: 'Tidak pakai pemindai',
          tambahan: [
            const SizedBox(height: TokenJarak.jarak8),
            SizedBox(
              width: 320,
              child: TextField(
                controller: _pindai,
                decoration: const InputDecoration(labelText: 'Hasil pindai', hintText: 'Pindai barcode di sini'),
                onChanged: (nilai) {
                  if (nilai.trim().isNotEmpty && _hasil.pemindai != HasilUji.Lolos) {
                    setState(() => _hasil = _hasil.Salin(pemindai: HasilUji.Lolos));
                  }
                },
              ),
            ),
          ],
        ),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: FilledButton.icon(
                onPressed: _sibuk || !_hasil.lengkap ? null : _Simpan,
                icon: const Icon(Icons.check),
                label: const Text('Simpan hasil uji'),
              ),
            ),
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: OutlinedButton.icon(
                onPressed: _sibuk ? null : () => setState(() => _berjalan = false),
                icon: const Icon(Icons.close),
                label: const Text('Batal'),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
