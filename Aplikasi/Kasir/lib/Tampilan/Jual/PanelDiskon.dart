import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Penjualan/Keranjang.dart';
import '../../Domain/Penjualan/KonteksPenjualan.dart';
import '../../Domain/Penjualan/LayananPenjualan.dart';
import '../../Domain/Penjualan/LayananVoucher.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Komponen/FormatAngka.dart';
import '../Komponen/MasukanUang.dart';
import '../LembarMutasiKas.dart';

/// Hasil pemeriksaan persetujuan diskon.
typedef HasilPersetujuanDiskon = ({bool boleh, PenyetujuDiskon? penyetuju});

/// BR-07.3: periksa diskon terhadap batas memakai persen efektif (diskon hasil mesin [nilaiDiskon] ÷ [dasar]); kasir
/// tanpa izin diskon manual atau di atas batas manual → PIN penyetuju ber-izin `penjualan.diskon.setujui`; di atas
/// batas penyetuju → hanya Pemilik. Satu dialog saja (tidak bertumpuk).
Future<HasilPersetujuanDiskon> PastikanDiskonDisetujui(
  BuildContext context, {
  required Uang dasar,
  required Uang nilaiDiskon,
  required StafLokal kasir,
  required KonteksPenjualan k,
  PenyetujuDiskon? penyetuju,
}) async {
  var status = LayananPenjualan.PeriksaDiskon(
    dasar: dasar,
    diskon: nilaiDiskon,
    kasir: kasir,
    k: k,
    penyetuju: penyetuju,
  );
  if (status == StatusDiskon.Boleh) {
    return (boleh: true, penyetuju: penyetuju);
  }
  final hanyaPemilik = status == StatusDiskon.MelebihiBatas;
  final batasManual = FormatAngka.FormatPersen(k.batasDiskonManual.toString());
  final batasPenyetuju = FormatAngka.FormatPersen(k.batasDiskonPenyetuju.toString());
  final berizinManual = kasir.PunyaIzin(IzinKasir.penjualanDiskonManual);
  final staf = await MintaPenyetujuSementara(
    context,
    DialogPinSupervisor(
      izin: IzinKasir.penjualanDiskonSetujui,
      hanyaPemilik: hanyaPemilik,
      pesan: hanyaPemilik
          ? 'Diskon ini di atas $batasPenyetuju. Hanya Pemilik yang bisa menyetujuinya.'
          : berizinManual
          ? 'Diskon ini di atas $batasManual. Pilih penyetuju diskon.'
          : 'Diskon manual perlu persetujuan. Pilih penyetuju diskon.',
    ),
  );
  if (staf == null) {
    return (boleh: false, penyetuju: penyetuju);
  }
  final baru = PenyetujuDiskon(uuid: staf.uuid, nama: staf.nama, pemilik: staf.pemilik);
  status = LayananPenjualan.PeriksaDiskon(dasar: dasar, diskon: nilaiDiskon, kasir: kasir, k: k, penyetuju: baru);
  if (status != StatusDiskon.Boleh) {
    throw GalatKasir(
      'DiskonMelebihiBatas',
      'Diskon melebihi batas yang bisa disetujui ${staf.nama} ($batasPenyetuju).',
    );
  }
  return (boleh: true, penyetuju: baru);
}

/// Isian diskon manual: persen atau nominal Rupiah.
class IsianDiskon extends StatefulWidget {
  const IsianDiskon({super.key, required this.awal, required this.saatBerubah, this.galat});

  final DiskonManual? awal;

  /// Null = isian kosong/tidak valid (tanpa diskon).
  final ValueChanged<DiskonManual?> saatBerubah;
  final String? galat;

  @override
  State<IsianDiskon> createState() => _IsianDiskonState();
}

class _IsianDiskonState extends State<IsianDiskon> {
  late bool _persen = widget.awal?.jumlah == null;
  late final TextEditingController _nilai = TextEditingController(
    text: widget.awal == null
        ? ''
        : widget.awal!.persen != null
        ? FormatAngka.FormatDesimal(widget.awal!.persen!)
        : MasukanUang.FormatTeks(widget.awal!.jumlah!),
  );

  @override
  void dispose() {
    _nilai.dispose();
    super.dispose();
  }

  void _Kabarkan() {
    if (!_persen) {
      final jumlah = MasukanUang.AmbilNilai(_nilai);
      widget.saatBerubah(jumlah == null || jumlah.BernilaiNol() ? null : DiskonManual.DariJumlah(jumlah));
      return;
    }
    final desimal = FormatAngka.UraiDesimal(_nilai.text);
    widget.saatBerubah(desimal == null || desimal <= Decimal.zero ? null : DiskonManual.DariPersen(desimal));
  }

  /// Nominal berformat ribuan (`15.000`), persen berkoma desimal (`12,5`): teks diubah saat berganti mode.
  void _GantiMode(bool persen) {
    if (persen == _persen) {
      return;
    }
    if (persen) {
      final jumlah = MasukanUang.AmbilNilai(_nilai);
      _nilai.text = jumlah == null ? '' : FormatAngka.FormatDesimal(jumlah.KeDesimal());
    } else {
      final desimal = FormatAngka.UraiDesimal(_nilai.text);
      MasukanUang.Isi(_nilai, desimal == null ? null : Uang.DariDesimal(desimal.truncate()));
    }
    setState(() => _persen = persen);
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      SegmentedButton<bool>(
        segments: const [
          ButtonSegment(value: true, label: Text('Persen')),
          ButtonSegment(value: false, label: Text('Nominal')),
        ],
        selected: {_persen},
        onSelectionChanged: (pilihan) {
          _GantiMode(pilihan.first);
          _Kabarkan();
        },
      ),
      const SizedBox(height: TokenJarak.jarak12),
      TextField(
        controller: _nilai,
        keyboardType: TextInputType.numberWithOptions(decimal: _persen),
        inputFormatters: _persen
            ? [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')), LengthLimitingTextInputFormatter(13)]
            : [MasukanUang.pemformat],
        textAlign: TextAlign.right,
        style: const TextStyle(fontFeatures: [FontFeature.tabularFigures()]),
        onChanged: (_) => _Kabarkan(),
        decoration: InputDecoration(
          labelText: _persen ? 'Diskon (%)' : 'Diskon (Rp)',
          prefixText: _persen ? null : 'Rp ',
          suffixText: _persen ? '%' : null,
          errorText: widget.galat,
          border: const OutlineInputBorder(),
        ),
      ),
    ],
  );
}

/// Panel diskon pesanan (BR-07.3): dasar = subtotal setelah diskon baris.
class PanelDiskonPesanan extends ConsumerStatefulWidget {
  const PanelDiskonPesanan({super.key, required this.kasir, required this.saatSelesai});

  final StafLokal kasir;
  final VoidCallback saatSelesai;

  @override
  ConsumerState<PanelDiskonPesanan> createState() => _PanelDiskonPesananState();
}

class _PanelDiskonPesananState extends ConsumerState<PanelDiskonPesanan> {
  DiskonManual? _diskon;
  String? _galat;

  @override
  void initState() {
    super.initState();
    _diskon = ref.read(penyediaKeranjang).diskonPesanan;
  }

  Future<void> _Simpan() async {
    final keranjang = ref.read(penyediaKeranjangEfektif);
    final k = await ref.read(penyediaKonteksPenjualan.future);
    final diskon = _diskon;
    if (diskon == null) {
      setState(() => _galat = 'Isi besar diskon.');
      return;
    }
    final layanan = ref.read(penyediaLayananPenjualan);
    try {
      final subtotal = layanan.Hitung(keranjang.Salin(diskonPesanan: () => null), k).hasil.subtotal;
      LayananPenjualan.ValidasiBentukDiskon(subtotal, diskon);
      final nilai = layanan.HitungDiskonPesanan(keranjang, diskon, k);
      if (!mounted) {
        return;
      }
      final hasil = await PastikanDiskonDisetujui(
        context,
        dasar: nilai.dasar,
        nilaiDiskon: nilai.diskon,
        kasir: widget.kasir,
        k: k,
        penyetuju: keranjang.penyetuju,
      );
      if (!hasil.boleh) {
        return;
      }
      ref
          .read(penyediaKeranjang.notifier)
          .Ganti(ref.read(penyediaKeranjang).Salin(diskonPesanan: () => diskon, penyetuju: () => hasil.penyetuju));
      widget.saatSelesai();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    }
  }

  void _Hapus() {
    ref.read(penyediaKeranjang.notifier).Ganti(ref.read(penyediaKeranjang).Salin(diskonPesanan: () => null));
    widget.saatSelesai();
  }

  @override
  Widget build(BuildContext context) {
    final adaDiskon = ref.watch(penyediaKeranjang).diskonPesanan != null;
    return Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const BagianVoucher(),
          const SizedBox(height: TokenJarak.jarak16),
          const Divider(height: 1),
          const SizedBox(height: TokenJarak.jarak16),
          Text(
            'Diskon dari subtotal. Di atas batas butuh persetujuan PIN.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: TokenJarak.jarak12),
          IsianDiskon(
            awal: _diskon,
            galat: _galat,
            saatBerubah: (d) => setState(() {
              _diskon = d;
              _galat = null;
            }),
          ),
          const SizedBox(height: TokenJarak.jarak16),
          SizedBox(
            height: 56,
            child: FilledButton(onPressed: _Simpan, child: const Text('Terapkan diskon')),
          ),
          if (adaDiskon) ...[
            const SizedBox(height: TokenJarak.jarak8),
            SizedBox(
              height: TokenJarak.targetSentuh,
              child: TextButton(onPressed: _Hapus, child: const Text('Hapus diskon pesanan')),
            ),
          ],
        ],
      ),
    );
  }
}

/// F-16c bagian 2: kode voucher di keranjang. Wajib online: kode diperiksa & dipesan server untuk transaksi ini, lalu
/// promonya dihitung mesin promo seperti promo otomatis. Voucher bisa dihapus (dilepas) selama belum dibayar.
class BagianVoucher extends ConsumerStatefulWidget {
  const BagianVoucher({super.key});

  @override
  ConsumerState<BagianVoucher> createState() => _BagianVoucherState();
}

class _BagianVoucherState extends ConsumerState<BagianVoucher> {
  final TextEditingController _kode = TextEditingController();
  String? _galat;
  bool _memproses = false;

  @override
  void dispose() {
    _kode.dispose();
    super.dispose();
  }

  Future<void> _Pakai() async {
    setState(() {
      _memproses = true;
      _galat = null;
    });
    try {
      final voucher = await ref.read(penyediaLayananVoucher).Pesan(_kode.text, ref.read(penyediaKeranjang));
      ref.read(penyediaKeranjang.notifier).Ganti(ref.read(penyediaKeranjang).Salin(voucher: () => voucher));
      _kode.clear();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _memproses = false);
      }
    }
  }

  Future<void> _Hapus(VoucherKeranjang voucher) async {
    ref.read(penyediaKeranjang.notifier).Ganti(ref.read(penyediaKeranjang).Salin(voucher: () => null));
    await ref.read(penyediaLayananVoucher).Lepas(voucher);
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final keranjang = ref.watch(penyediaKeranjangEfektif);
    final voucher = keranjang.voucher;
    final k = ref.watch(penyediaKonteksPenjualan).value;
    Uang? potongan;
    if (voucher != null && k != null && !keranjang.CekKosong) {
      try {
        potongan = ref
            .read(penyediaLayananPenjualan)
            .Hitung(keranjang, k)
            .promoTerpakai
            .where((p) => p.uuid == voucher.uuidPromo)
            .firstOrNull
            ?.HitungTotal();
      } on GalatKasir {
        potongan = null;
      }
    }

    if (voucher != null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.confirmation_number_outlined, color: warna.brand),
              const SizedBox(width: TokenJarak.jarak8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    TeksKode(voucher.kode, gaya: teks.titleSmall),
                    Text(
                      potongan == null
                          ? '${voucher.namaPromo} | belum memenuhi syarat promo'
                          : '${voucher.namaPromo} | −${potongan.FormatRupiah()}',
                      style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: TokenJarak.jarak8),
          SizedBox(
            height: TokenJarak.targetSentuh,
            child: TextButton(onPressed: () => unawaited(_Hapus(voucher)), child: const Text('Hapus voucher')),
          ),
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Punya kode voucher? Perlu koneksi internet.', style: teks.bodySmall),
        const SizedBox(height: TokenJarak.jarak12),
        TextField(
          controller: _kode,
          textCapitalization: TextCapitalization.characters,
          inputFormatters: [LengthLimitingTextInputFormatter(LayananVoucher.panjangKodeMaksimal)],
          style: const TextStyle(fontFamily: fontMono, package: paketFont),
          onSubmitted: (_) => unawaited(_Pakai()),
          decoration: InputDecoration(labelText: 'Kode voucher', errorText: _galat, border: const OutlineInputBorder()),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        SizedBox(
          height: 56,
          child: OutlinedButton(
            onPressed: _memproses ? null : () => unawaited(_Pakai()),
            child: Text(_memproses ? 'Memeriksa voucher…' : 'Pakai voucher'),
          ),
        ),
      ],
    );
  }
}
