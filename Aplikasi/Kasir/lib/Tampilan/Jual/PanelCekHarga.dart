import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Katalog/KatalogLokal.dart';
import '../../Domain/Penjualan/LayananPenjualan.dart';
import '../../Domain/Persediaan/LayananInfoBatch.dart';
import '../Komponen/FormatAngka.dart';

/// Cek harga (K-10, §9.3): pelanggan bertanya "ini berapa?" tanpa membeli. Kasir memindai barcode atau mengetik
/// nama/SKU; panel menampilkan harga per satuan menurut kanal & tier pelanggan keranjang saat ini, harga bertingkat
/// (mulai jumlah tertentu), dan alasan bila produk tidak bisa dijual. Keranjang tidak berubah kecuali kasir mengetuk
/// "Tambah ke keranjang". Pemindaian saat panel terbuka diarahkan ke sini lewat [PanelCekHargaState.Tampilkan].
/// K-19: produk ber-batch menampilkan batch bersisa di toko urut FEFO (yang terjual lebih dulu di atas) beserta sisa
/// hari kedaluwarsanya (butuh internet).
class PanelCekHarga extends ConsumerStatefulWidget {
  const PanelCekHarga({super.key, required this.saatTambah});

  final void Function(ProdukJual produk) saatTambah;

  @override
  ConsumerState<PanelCekHarga> createState() => PanelCekHargaState();
}

class PanelCekHargaState extends ConsumerState<PanelCekHarga> {
  final TextEditingController _cari = TextEditingController();
  ProdukJual? _produk;
  String? _pesan;

  @override
  void dispose() {
    _cari.dispose();
    super.dispose();
  }

  /// Tampilkan produk untuk kode pindaian/ketikan (barcode, SKU); satu hasil cari nama juga langsung tampil.
  void Tampilkan(String kode) {
    final katalog = ref.read(penyediaKatalog).value;
    if (katalog == null) {
      return;
    }
    final hasil = katalog.CariKode(kode)?.produk;
    final daftar = hasil == null ? katalog.AmbilTampil(kata: kode) : const <ProdukJual>[];
    setState(() {
      _cari.text = kode;
      _produk = hasil ?? (daftar.length == 1 ? daftar.single : null);
      _pesan = _produk != null || daftar.length > 1 ? null : 'Kode $kode tidak ditemukan di katalog.';
    });
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final katalog = ref.watch(penyediaKatalog).value;
    final kata = _cari.text.trim();
    final daftar = _produk == null && kata.isNotEmpty && katalog != null
        ? katalog.AmbilTampil(kata: kata).take(20).toList()
        : const <ProdukJual>[];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          key: const ValueKey('CekHargaCari'),
          controller: _cari,
          autofocus: true,
          textInputAction: TextInputAction.search,
          decoration: const InputDecoration(
            labelText: 'Pindai atau ketik barcode, SKU, atau nama',
            prefixIcon: Icon(Icons.qr_code_scanner),
          ),
          onChanged: (_) => setState(() {
            _produk = null;
            _pesan = null;
          }),
          onSubmitted: Tampilkan,
        ),
        const SizedBox(height: TokenJarak.jarak16),
        if (_pesan case final pesan?)
          Text(pesan, style: teks.bodyMedium?.copyWith(color: warna.bahaya))
        else if (_produk case final produk?)
          _BangunRincian(context, produk)
        else
          for (final p in daftar)
            BarisProduk(
              key: ValueKey('cek-${p.uuid}'),
              nama: p.nama,
              sku: p.sku,
              harga: _Harga(p, p.AmbilSatuanBawaan(), Kuantitas.DariBulat(1)),
              saatDiketuk: () => setState(() => _produk = p),
            ),
        if (_produk == null && _pesan == null && kata.isEmpty)
          Text(
            'Harga tampil tanpa menambah ke keranjang.',
            style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
          ),
      ],
    );
  }

  Uang? _Harga(ProdukJual p, SatuanJual? satuan, Kuantitas jumlah) {
    final katalog = ref.read(penyediaKatalog).value;
    final k = ref.read(penyediaKonteksPenjualan).value;
    if (katalog == null || k == null || satuan == null) {
      return null;
    }
    final keranjang = ref.read(penyediaKeranjang);
    return ref
        .read(penyediaLayananPenjualan)
        .TentukanHarga(
          katalog,
          k,
          p.uuid,
          satuan.uuid,
          jumlah,
          kanal: LayananPenjualan.AmbilKanal(keranjang),
          tierPelanggan: keranjang.pelanggan?.kodeTier,
        );
  }

  Widget _BangunRincian(BuildContext context, ProdukJual produk) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final katalog = ref.read(penyediaKatalog).value;
    final alasan = produk.AmbilAlasanTidakBisaDijual();
    final habis = ref.watch(penyediaProdukHabis).contains(produk.uuid);
    final tingkat = katalog?.AmbilKatalogHarga(produk.uuid).harga ?? const <BarisProdukHarga>[];
    return Column(
      key: const ValueKey('CekHargaRincian'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(produk.nama, style: teks.titleMedium),
        if (produk.sku case final sku?) TeksKode(sku, gaya: teks.bodySmall?.copyWith(color: warna.teksSekunder)),
        const SizedBox(height: TokenJarak.jarak12),
        for (final s in produk.satuan) ...[
          Row(
            children: [
              Expanded(child: Text('Per ${s.nama}', style: teks.bodyMedium)),
              switch (_Harga(produk, s, Kuantitas.DariBulat(1))) {
                final harga? => TeksUang(harga, gaya: teks.titleSmall),
                null => Text('Harga belum diatur', style: teks.bodySmall?.copyWith(color: warna.peringatan)),
              },
            ],
          ),
          // Harga bertingkat: jumlah minimum > 1 untuk satuan ini, urut naik, tanpa duplikat.
          for (final minimum in {
            for (final t in tingkat)
              if (t.uuidProdukSatuan == s.uuid && t.jumlahMinimum.Bandingkan(Kuantitas.DariBulat(1)) > 0)
                t.jumlahMinimum,
          }.toList()..sort())
            if (_Harga(produk, s, minimum) case final harga?)
              Padding(
                padding: const EdgeInsets.only(left: TokenJarak.jarak12, top: TokenJarak.jarak4),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Mulai ${FormatAngka.FormatJumlah(minimum)} ${s.nama}',
                        style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                      ),
                    ),
                    TeksUang(harga, gaya: teks.bodySmall),
                  ],
                ),
              ),
          const SizedBox(height: TokenJarak.jarak8),
        ],
        if (produk.berBatch) ...[
          const SizedBox(height: TokenJarak.jarak8),
          _BagianBatch(key: ValueKey('batch-${produk.uuid}'), uuidProduk: produk.uuid),
          const SizedBox(height: TokenJarak.jarak8),
        ],
        if (alasan != null || habis)
          Row(
            children: [
              Icon(Icons.block, size: TokenJarak.ikonKecil, color: warna.bahaya),
              const SizedBox(width: TokenJarak.jarak4),
              Expanded(
                child: Text(
                  alasan?.pesan ?? 'Ditandai habis di outlet ini.',
                  style: teks.bodySmall?.copyWith(color: warna.bahaya),
                ),
              ),
            ],
          ),
        const SizedBox(height: TokenJarak.jarak16),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            OutlinedButton(
              onPressed: () => setState(() {
                _produk = null;
                _cari.clear();
              }),
              child: const Text('Cek produk lain'),
            ),
            if (alasan == null && !habis)
              FilledButton(onPressed: () => widget.saatTambah(produk), child: const Text('Tambah ke keranjang')),
          ],
        ),
      ],
    );
  }
}

/// K-19: daftar batch bersisa di toko (urut FEFO) dengan status kedaluwarsa.
class _BagianBatch extends ConsumerStatefulWidget {
  const _BagianBatch({super.key, required this.uuidProduk});

  final String uuidProduk;

  @override
  ConsumerState<_BagianBatch> createState() => _BagianBatchState();
}

class _BagianBatchState extends ConsumerState<_BagianBatch> {
  late Future<BatchProdukPos> _info = ref.read(penyediaLayananInfoBatch).Ambil(widget.uuidProduk);

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final kecil = teks.bodySmall?.copyWith(color: warna.teksSekunder);
    return Column(
      key: const ValueKey('CekHargaBatch'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Batch & kedaluwarsa di toko', style: teks.titleSmall),
        const SizedBox(height: TokenJarak.jarak4),
        FutureBuilder<BatchProdukPos>(
          future: _info,
          builder: (context, hasil) {
            if (hasil.connectionState != ConnectionState.done) {
              return const LinearProgressIndicator();
            }
            final info = hasil.data;
            if (info == null) {
              return Row(
                children: [
                  Expanded(child: Text('Info batch butuh internet.', style: kecil)),
                  TextButton(
                    onPressed: () => setState(
                      () => _info = ref.read(penyediaLayananInfoBatch).Ambil(widget.uuidProduk, paksa: true),
                    ),
                    child: const Text('Coba lagi'),
                  ),
                ],
              );
            }
            if (info.batch.isEmpty) {
              return Text('Tidak ada stok ber-batch di toko.', style: kecil);
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (i, b) in info.batch.indexed)
                  Padding(
                    padding: const EdgeInsets.only(bottom: TokenJarak.jarak4),
                    child: Row(
                      children: [
                        Icon(
                          (b.sisaHari ?? 1) < 0 ? Icons.error_outline : Icons.inventory_2_outlined,
                          size: TokenJarak.ikonKecil,
                          color: switch (b.sisaHari) {
                            final h? when h < 0 => warna.bahaya,
                            final h? when h <= info.hariSegera => warna.peringatan,
                            _ => warna.teksSekunder,
                          },
                        ),
                        const SizedBox(width: TokenJarak.jarak8),
                        Expanded(
                          child: Text(
                            '${b.nomorBatch} | ${FormatAngka.FormatJumlah(Kuantitas.Dari(b.jumlahSisa))} '
                            '${info.simbolSatuan}${i == 0 ? ' | dijual lebih dulu' : ''}',
                            style: teks.bodySmall,
                          ),
                        ),
                        Text(
                          LayananInfoBatch.LabelSisaHari(b.sisaHari),
                          style: teks.bodySmall?.copyWith(
                            color: (b.sisaHari ?? 1) < 0 ? warna.bahaya : warna.teksSekunder,
                          ),
                        ),
                      ],
                    ),
                  ),
                if (info.jumlahBatch > info.batch.length)
                  Text('+${info.jumlahBatch - info.batch.length} batch lain', style: kecil),
              ],
            );
          },
        ),
      ],
    );
  }
}
