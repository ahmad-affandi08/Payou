import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Struk/BagianCetakDokumen.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Penjualan/LayananVoidPenjualan.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Komponen/FormatWaktu.dart';
import '../LembarMutasiKas.dart';
import '../Komponen/PilihanAlasan.dart';

/// Formulir void transaksi (Rincian F-09 fase 1) di dalam `PanelTugas` ruang kerja: ringkasan transaksi, pengembalian
/// yang harus dilakukan (tunai dari laci, non-tunai manual), alasan, lalu PIN penyetuju ber-izin `penjualan.void`
/// (kasir yang sendiri ber-izin menyetujui dirinya). Berlaku offline; dikirim lewat outbox setelah penjualannya.
class LembarVoid extends ConsumerStatefulWidget {
  const LembarVoid({super.key, required this.uuidPenjualan, required this.kasir, required this.saatSelesai});

  static const String judul = 'Batalkan transaksi';

  final String uuidPenjualan;
  final StafLokal kasir;
  final VoidCallback saatSelesai;

  @override
  ConsumerState<LembarVoid> createState() => _LembarVoidState();
}

class _LembarVoidState extends ConsumerState<LembarVoid> {
  final _alasan = TextEditingController();
  ({BarisPenjualan penjualan, RefundVoid refund})? _data;
  String? _galatAwal;
  String? _galat;
  bool _sibuk = false;

  /// Hasil setelah tersimpan (tahap selesai).
  RefundVoid? _selesai;

  LayananVoidPenjualan get _layanan => ref.read(penyediaLayananVoid);

  @override
  void initState() {
    super.initState();
    unawaited(_Muat());
  }

  @override
  void dispose() {
    _alasan.dispose();
    super.dispose();
  }

  Future<void> _Muat() async {
    try {
      final data = await _layanan.Periksa(widget.uuidPenjualan, widget.kasir);
      if (mounted) {
        setState(() => _data = data);
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galatAwal = galat.pesan);
      }
    }
  }

  Future<void> _Simpan() async {
    if (_alasan.text.trim().runes.length < LayananVoidPenjualan.panjangAlasanMinimal) {
      setState(() => _galat = 'Tulis alasan void minimal 5 huruf.');
      return;
    }
    StafLokal? penyetuju;
    if (LayananVoidPenjualan.AmbilPenyetujuEfektif(widget.kasir, null) == null) {
      penyetuju = await showDialog<StafLokal>(
        context: context,
        builder: (_) => DialogPinSupervisor(
          izin: IzinKasir.penjualanVoid,
          pesan: 'Pembatalan (void) ${_data?.penjualan.Nomor ?? ''} wajib disetujui. Pilih supervisor yang menyetujui.',
          judul: 'Batalkan transaksi ${_data?.penjualan.Nomor ?? ''}'.trim(),
          nilai: _data == null ? null : Uang.Dari(_data!.penjualan.TotalAkhir),
          rincian: [(label: 'Alasan', nilai: _alasan.text.trim())],
        ),
      );
      if (penyetuju == null) {
        return;
      }
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      final refund = await _layanan.Void(
        uuidPenjualan: widget.uuidPenjualan,
        kasir: widget.kasir,
        alasan: _alasan.text,
        penyetuju: penyetuju,
      );
      if (mounted) {
        setState(() => _selesai = refund);
      }
      unawaited(ref.read(penyediaSesi.notifier).Sinkronkan());
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
        unawaited(UmpanAksi.Gagal(context, judul: 'Transaksi belum dibatalkan', pesan: galat.pesan));
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
    final data = _data;

    Widget Bingkai(List<Widget> anak) => Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: anak),
    );

    if (_galatAwal != null) {
      return Bingkai([Text(_galatAwal!, style: teks.bodyLarge?.copyWith(color: warna.bahaya))]);
    }
    if (data == null) {
      return Bingkai([const LinearProgressIndicator()]);
    }

    final p = data.penjualan;
    final refund = _selesai ?? data.refund;
    final ringkasRefund = [
      if (!refund.tunai.BernilaiNol())
        _BarisNilai(label: 'Kembalikan tunai dari laci', nilai: refund.tunai, tebal: true),
      if (!refund.nonTunai.BernilaiNol())
        _BarisNilai(
          label: 'Kembalikan manual (${refund.metodeNonTunai.join(', ')})',
          nilai: refund.nonTunai,
          tebal: true,
        ),
      if (refund.tunai.BernilaiNol() && refund.nonTunai.BernilaiNol())
        Text('Tidak ada uang yang perlu dikembalikan.', style: teks.bodyMedium),
    ];

    if (_selesai != null) {
      return Bingkai([
        Row(
          children: [
            Icon(Icons.check_circle_outline, color: warna.sukses, size: TokenJarak.ikonBesar),
            const SizedBox(width: TokenJarak.jarak8),
            Expanded(child: Text('Transaksi ${p.Nomor} sudah di-void.', style: teks.titleMedium)),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak12),
        KotakPanel(
          anak: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: ringkasRefund),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Text(
          'Stok & jurnal dibalik oleh server setelah data terkirim.',
          style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
        ),
        const SizedBox(height: TokenJarak.jarak12),
        // Cetak struk bagian 3b: bukti void (laci terbuka bila ada refund tunai, hanya pada cetak otomatis pertama).
        BagianCetakDokumen(
          kunci: 'Void:${widget.uuidPenjualan}',
          namaDokumen: 'bukti void',
          cetak: (l, ulang, otomatis) => l.CetakVoid(widget.uuidPenjualan, cetakUlang: ulang, bukaLaci: otomatis),
        ),
        const SizedBox(height: TokenJarak.jarak16),
        SizedBox(
          height: 56,
          child: FilledButton(onPressed: widget.saatSelesai, child: const Text('Selesai')),
        ),
      ]);
    }

    return Bingkai([
      KotakPanel(
        anak: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TeksKode(p.Nomor, gaya: teks.titleSmall),
            const SizedBox(height: TokenJarak.jarak4),
            Text('${FormatWaktu.FormatJam(p.DibuatPada)} | ${p.NamaKasir}', style: teks.bodySmall),
            const SizedBox(height: TokenJarak.jarak8),
            _BarisNilai(label: 'Total transaksi', nilai: Uang.Dari(p.TotalAkhir)),
          ],
        ),
      ),
      const SizedBox(height: TokenJarak.jarak12),
      Text('Pengembalian ke pelanggan', style: teks.titleSmall),
      const SizedBox(height: TokenJarak.jarak4),
      ...ringkasRefund,
      const SizedBox(height: TokenJarak.jarak12),
      PilihanAlasan(pengendali: _alasan, pilihan: PilihanAlasan.voidPenjualan),
      TextField(
        controller: _alasan,
        maxLength: 255,
        decoration: const InputDecoration(
          labelText: 'Alasan void',
          hintText: 'Contoh: salah input pesanan',
          border: OutlineInputBorder(),
        ),
      ),
      if (_galat != null)
        Padding(
          padding: const EdgeInsets.only(bottom: TokenJarak.jarak8),
          child: Text(_galat!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
        ),
      SizedBox(
        height: 56,
        child: FilledButton(
          style: FilledButton.styleFrom(backgroundColor: warna.bahaya),
          onPressed: _sibuk ? null : _Simpan,
          child: Text(_sibuk ? 'Menyimpan…' : 'Batalkan transaksi'),
        ),
      ),
      const SizedBox(height: TokenJarak.jarak8),
      Text(
        LayananVoidPenjualan.AmbilPenyetujuEfektif(widget.kasir, null) == null
            ? 'Pembatalan (void) wajib disetujui supervisor dengan PIN. Bisa dilakukan tanpa internet.'
            : 'Anda berwenang menyetujui void ini. Bisa dilakukan tanpa internet.',
        style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
      ),
    ]);
  }
}

class _BarisNilai extends StatelessWidget {
  const _BarisNilai({required this.label, required this.nilai, this.tebal = false});

  final String label;
  final Uang nilai;
  final bool tebal;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final gaya = tebal ? teks.titleSmall : teks.bodyMedium;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label, style: gaya)),
          const SizedBox(width: TokenJarak.jarak12),
          TeksUang(nilai, gaya: gaya),
        ],
      ),
    );
  }
}
