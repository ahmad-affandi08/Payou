import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Pelanggan/LayananPelanggan.dart';
import '../../Domain/Penjualan/Keranjang.dart';

/// Tukar poin pelanggan sebagai potongan pesanan sebelum pajak (F-16b, J-16.4). Saldo & aturan tukar diambil online
/// setiap kali panel dibuka (§18.4: penukaran wajib online). Poin dibatasi saldo dan sisa belanja setelah diskon lain.
class PanelTukarPoin extends ConsumerStatefulWidget {
  const PanelTukarPoin({super.key, required this.pelanggan, required this.saatSelesai, required this.saatKembali});

  final PelangganTerpilih pelanggan;
  final VoidCallback saatSelesai;
  final VoidCallback saatKembali;

  @override
  ConsumerState<PanelTukarPoin> createState() => _PanelTukarPoinState();
}

class _PanelTukarPoinState extends ConsumerState<PanelTukarPoin> {
  final _poin = TextEditingController();
  SaldoPoinPos? _saldo;
  String? _galat;
  bool _memuat = true;

  @override
  void initState() {
    super.initState();
    final lama = ref.read(penyediaKeranjang).tukarPoin;
    if (lama != null) {
      _poin.text = '${lama.poin}';
    }
    unawaited(_Muat());
  }

  @override
  void dispose() {
    _poin.dispose();
    super.dispose();
  }

  Future<void> _Muat() async {
    setState(() {
      _memuat = true;
      _galat = null;
    });
    try {
      final saldo = await ref.read(penyediaLayananPelanggan).AmbilSaldoPoin(widget.pelanggan.uuid);
      if (mounted) {
        setState(() => _saldo = saldo);
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _memuat = false);
      }
    }
  }

  /// Sisa belanja yang bisa dipotong poin: subtotal − diskon pesanan lain (tanpa tukar poin).
  Uang? _HitungSisaTagihan() {
    final k = ref.read(penyediaKonteksPenjualan).value;
    if (k == null) {
      return null;
    }
    final keranjang = ref.read(penyediaKeranjangEfektif).Salin(tukarPoin: () => null);
    final hasil = ref.read(penyediaLayananPenjualan).Hitung(keranjang, k).hasil;
    return hasil.subtotal.Kurangi(hasil.diskonPesanan);
  }

  void _Pakai(SaldoPoinPos saldo, int maksimal) {
    final poin = int.tryParse(_poin.text.trim()) ?? 0;
    if (poin < saldo.minimalTukarPoin) {
      setState(() => _galat = 'Minimal tukar ${saldo.minimalTukarPoin} poin.');
      return;
    }
    if (poin > maksimal) {
      setState(() => _galat = 'Maksimal $maksimal poin untuk belanja ini.');
      return;
    }
    final nilai = LayananPelanggan.HitungNilaiTukar(poin, Uang.Dari(saldo.nilaiTukarPoin));
    _Terapkan(TukarPoin(poin: poin, nilai: nilai), saldo.saldoPoin);
  }

  void _Terapkan(TukarPoin? tukar, int? saldo) {
    final keranjang = ref.read(penyediaKeranjang);
    final pelanggan = keranjang.pelanggan;
    ref
        .read(penyediaKeranjang.notifier)
        .Ganti(
          keranjang.Salin(
            tukarPoin: () => tukar,
            pelanggan: pelanggan == null || saldo == null
                ? null
                : () => PelangganTerpilih(
                    uuid: pelanggan.uuid,
                    nama: pelanggan.nama,
                    noHpSamar: pelanggan.noHpSamar,
                    kodeTier: pelanggan.kodeTier,
                    namaTier: pelanggan.namaTier,
                    saldoPoin: saldo,
                  ),
          ),
        );
    widget.saatSelesai();
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final saldo = _saldo;
    final adaTukar = ref.watch(penyediaKeranjang.select((k) => k.tukarPoin != null));

    Widget Tombol(String label, VoidCallback? aksi, {bool utama = false}) => SizedBox(
      height: TokenJarak.targetSentuh,
      child: utama
          ? FilledButton(
              onPressed: aksi,
              child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
            )
          : OutlinedButton(
              onPressed: aksi,
              child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
            ),
    );

    final isi = <Widget>[
      Text(
        'Tukar poin | ${widget.pelanggan.nama}',
        style: teks.titleMedium,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      const SizedBox(height: TokenJarak.jarak8),
    ];

    if (_memuat) {
      isi.add(const LinearProgressIndicator());
    } else if (saldo == null) {
      isi.addAll([
        Row(
          children: [
            Icon(Icons.wifi_off, size: TokenJarak.ikonKecil, color: warna.peringatan),
            const SizedBox(width: TokenJarak.jarak8),
            Expanded(child: Text(_galat ?? 'Saldo poin tidak bisa dimuat.', style: teks.bodyMedium)),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak16),
        Row(
          children: [
            Expanded(child: Tombol('Kembali', widget.saatKembali)),
            const SizedBox(width: TokenJarak.jarak8),
            Expanded(child: Tombol('Coba lagi', () => unawaited(_Muat()), utama: true)),
          ],
        ),
      ]);
    } else if (!saldo.berlaku) {
      isi.addAll([
        Text('Poin loyalti tidak aktif untuk usaha ini.', style: teks.bodyMedium),
        const SizedBox(height: TokenJarak.jarak16),
        Tombol('Kembali', widget.saatKembali),
      ]);
    } else {
      final nilaiPerPoin = Uang.Dari(saldo.nilaiTukarPoin);
      final sisa = _HitungSisaTagihan() ?? Uang.Nol();
      final maksimal = LayananPelanggan.HitungMaksimalPoin(
        saldo: saldo.saldoPoin,
        sisaTagihan: sisa,
        nilaiPerPoin: nilaiPerPoin,
      );
      final poin = int.tryParse(_poin.text.trim()) ?? 0;
      final bisa = maksimal >= saldo.minimalTukarPoin;
      isi.addAll([
        Text(
          'Saldo ${saldo.saldoPoin} poin | 1 poin = ${nilaiPerPoin.FormatRupiah()} | minimal ${saldo.minimalTukarPoin} poin',
          style: teks.bodyMedium,
        ),
        const SizedBox(height: TokenJarak.jarak4),
        Text(
          bisa
              ? 'Bisa ditukar sampai $maksimal poin untuk belanja ini.'
              : 'Poin belum cukup untuk ditukar pada belanja ini.',
          style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
        ),
        const SizedBox(height: TokenJarak.jarak16),
        TextField(
          controller: _poin,
          autofocus: true,
          enabled: bisa,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(8)],
          decoration: InputDecoration(
            labelText: 'Poin yang ditukar',
            border: const OutlineInputBorder(),
            suffixIcon: bisa
                ? TextButton(onPressed: () => setState(() => _poin.text = '$maksimal'), child: const Text('Maks'))
                : null,
          ),
          onChanged: (_) => setState(() => _galat = null),
          onSubmitted: (_) => _Pakai(saldo, maksimal),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Row(
          children: [
            Expanded(child: Text('Potongan', style: teks.bodyMedium)),
            TeksUang(Uang.Nol().Kurangi(LayananPelanggan.HitungNilaiTukar(poin, nilaiPerPoin)), gaya: teks.titleMedium),
          ],
        ),
        if (_galat != null) ...[
          const SizedBox(height: TokenJarak.jarak8),
          Text(_galat!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
        ],
        const SizedBox(height: TokenJarak.jarak16),
        Row(
          children: [
            Expanded(
              child: adaTukar
                  ? Tombol('Lepas tukar poin', () => _Terapkan(null, saldo.saldoPoin))
                  : Tombol('Kembali', widget.saatKembali),
            ),
            const SizedBox(width: TokenJarak.jarak8),
            Expanded(
              flex: 2,
              child: Tombol(
                bisa ? 'Pakai $poin poin' : 'Pakai poin',
                bisa ? () => _Pakai(saldo, maksimal) : null,
                utama: true,
              ),
            ),
          ],
        ),
      ]);
    }

    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: isi),
      ),
    );
  }
}
