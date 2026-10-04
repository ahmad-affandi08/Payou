import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Pelanggan/LayananSesi.dart';
import '../../Domain/Penjualan/Keranjang.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Komponen/FormatWaktu.dart';

/// F-16d bagian 2: pelanggan terpilih memakai sesi paketnya (misal creambath ke-3 dari paket 10x). Paket aktif dibaca
/// online; pemakaian dicatat ke outbox `Sesi.Pakai` sehingga tetap terkirim bila koneksi putus setelahnya.
class PanelPakaiSesi extends ConsumerStatefulWidget {
  const PanelPakaiSesi({
    super.key,
    required this.pelanggan,
    required this.kasir,
    required this.saatSelesai,
    required this.saatKembali,
  });

  final PelangganTerpilih pelanggan;
  final StafLokal kasir;
  final VoidCallback saatSelesai;
  final VoidCallback saatKembali;

  @override
  ConsumerState<PanelPakaiSesi> createState() => _PanelPakaiSesiState();
}

class _PanelPakaiSesiState extends ConsumerState<PanelPakaiSesi> {
  SaldoSesiPos? _saldo;
  bool _memuat = true;
  String? _pesanMuat;
  PaketSesiPelangganPos? _paket;
  String? _uuidLayanan;
  int _jumlah = 1;
  bool _sibuk = false;
  String? _galat;
  String? _selesai;

  @override
  void initState() {
    super.initState();
    unawaited(_Muat());
  }

  Future<void> _Muat() async {
    try {
      final saldo = await ref.read(penyediaLayananSesi).AmbilSaldo(widget.pelanggan.uuid);
      if (mounted) {
        setState(() {
          _saldo = saldo;
          _paket = saldo.paket.length == 1 ? saldo.paket.single : null;
        });
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _pesanMuat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _memuat = false);
      }
    }
  }

  Future<void> _Simpan() async {
    final paket = _paket;
    final layanan = _uuidLayanan;
    final katalog = ref.read(penyediaKatalog).value;
    if (paket == null || layanan == null || katalog == null) {
      setState(() => _galat = 'Pilih paket dan layanan yang dikerjakan.');
      return;
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await ref
          .read(penyediaLayananSesi)
          .Pakai(paket: paket, uuidProduk: layanan, jumlah: _jumlah, kasir: widget.kasir, katalog: katalog);
      unawaited(ref.read(penyediaSesi.notifier).Sinkronkan());
      final nama = LayananSesi.AmbilLayanan(paket, katalog).firstWhere((l) => l.uuid == layanan).nama;
      if (mounted) {
        setState(
          () => _selesai =
              '$_jumlah sesi $nama tercatat untuk ${widget.pelanggan.nama}. '
              'Sisa paket ${paket.namaPaket}: ${paket.sisaSesi - _jumlah} sesi.',
        );
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final katalog = ref.watch(penyediaKatalog).value;
    final selesai = _selesai;
    final saldo = _saldo;
    final paket = _paket;
    final layanan = paket == null || katalog == null
        ? const <LayananTukarSesi>[]
        : LayananSesi.AmbilLayanan(paket, katalog);

    Widget BuatTombol(String label, VoidCallback? saatTekan, {bool utama = false}) => SizedBox(
      height: TokenJarak.targetSentuh,
      child: utama
          ? FilledButton(onPressed: saatTekan, child: Text(label))
          : OutlinedButton(onPressed: saatTekan, child: Text(label)),
    );

    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Pakai sesi ${widget.pelanggan.nama}', style: teks.titleMedium),
            const SizedBox(height: TokenJarak.jarak4),
            Text(
              'Sisa sesi dicek online. Setelah dicatat, pemakaian tetap terkirim walau koneksi putus.',
              style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
            ),
            const SizedBox(height: TokenJarak.jarak12),
            if (selesai != null) ...[
              Text(selesai, style: teks.bodyLarge),
              const SizedBox(height: TokenJarak.jarak16),
              BuatTombol('Selesai', widget.saatSelesai, utama: true),
            ] else if (_memuat)
              const LinearProgressIndicator()
            else if (saldo == null) ...[
              Text(_pesanMuat ?? 'Paket sesi belum bisa dicek.', style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
              const SizedBox(height: TokenJarak.jarak16),
              BuatTombol('Kembali', widget.saatKembali),
            ] else if (!saldo.berlaku || saldo.paket.isEmpty) ...[
              Text(
                saldo.berlaku
                    ? '${widget.pelanggan.nama} belum punya paket sesi aktif.'
                    : 'Paket usaha ini belum termasuk paket sesi.',
                style: teks.bodyMedium,
              ),
              const SizedBox(height: TokenJarak.jarak16),
              BuatTombol('Kembali', widget.saatKembali),
            ] else ...[
              Text('Paket', style: teks.labelLarge),
              const SizedBox(height: TokenJarak.jarak8),
              for (final p in saldo.paket)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  selected: paket?.uuid == p.uuid,
                  leading: Icon(paket?.uuid == p.uuid ? Icons.radio_button_checked : Icons.radio_button_unchecked),
                  title: Text(p.namaPaket),
                  subtitle: Text(
                    'Sisa ${p.sisaSesi} dari ${p.jumlahSesi} sesi'
                    '${p.berlakuSampai == null ? '' : ' | berlaku sampai ${FormatWaktu.FormatTanggal(DateTime.parse(p.berlakuSampai!))}'}',
                  ),
                  onTap: _sibuk
                      ? null
                      : () => setState(() {
                          _paket = p;
                          _uuidLayanan = null;
                          _jumlah = 1;
                        }),
                ),
              if (paket != null) ...[
                const SizedBox(height: TokenJarak.jarak12),
                Text('Layanan yang dikerjakan', style: teks.labelLarge),
                const SizedBox(height: TokenJarak.jarak8),
                if (layanan.isEmpty)
                  Text(
                    'Tidak ada layanan untuk paket ini di katalog.',
                    style: teks.bodySmall?.copyWith(color: warna.bahaya),
                  ),
                Wrap(
                  spacing: TokenJarak.jarak8,
                  runSpacing: TokenJarak.jarak8,
                  children: [
                    for (final l in layanan)
                      ChoiceChip(
                        label: Text(l.nama),
                        selected: _uuidLayanan == l.uuid,
                        onSelected: _sibuk ? null : (_) => setState(() => _uuidLayanan = l.uuid),
                      ),
                  ],
                ),
                const SizedBox(height: TokenJarak.jarak12),
                Row(
                  children: [
                    Expanded(child: Text('Jumlah sesi', style: teks.bodyMedium)),
                    IconButton(
                      tooltip: 'Kurangi',
                      onPressed: _sibuk || _jumlah <= 1 ? null : () => setState(() => _jumlah--),
                      icon: const Icon(Icons.remove),
                    ),
                    Text('$_jumlah', style: teks.titleMedium),
                    IconButton(
                      tooltip: 'Tambah',
                      onPressed: _sibuk || _jumlah >= paket.sisaSesi ? null : () => setState(() => _jumlah++),
                      icon: const Icon(Icons.add),
                    ),
                  ],
                ),
              ],
              if (_galat != null) ...[
                const SizedBox(height: TokenJarak.jarak8),
                Text(_galat!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
              ],
              const SizedBox(height: TokenJarak.jarak16),
              Row(
                children: [
                  Expanded(child: BuatTombol('Kembali', _sibuk ? null : widget.saatKembali)),
                  const SizedBox(width: TokenJarak.jarak8),
                  Expanded(
                    flex: 2,
                    child: BuatTombol(
                      'Catat pemakaian',
                      _sibuk || paket == null || _uuidLayanan == null ? null : () => unawaited(_Simpan()),
                      utama: true,
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
