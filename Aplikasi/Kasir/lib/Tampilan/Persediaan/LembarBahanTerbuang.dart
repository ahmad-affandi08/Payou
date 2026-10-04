import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Katalog/KatalogLokal.dart';
import '../../Domain/Persediaan/LayananBahanTerbuang.dart';
import '../../Domain/Sesi/StafLokal.dart';

/// Formulir catat bahan/menu terbuang (F-05f bagian 2) di panel tugas ruang kerja: cari produk (nama, SKU, atau
/// barcode), pilih satuan, isi jumlah, pilih alasan, catatan opsional. Tersimpan offline lalu dikirim lewat outbox;
/// [saatTersimpan] dipanggil setelah tersimpan.
class LembarBahanTerbuang extends ConsumerStatefulWidget {
  const LembarBahanTerbuang({super.key, required this.pencatat, required this.saatTersimpan});

  static const String judul = 'Catat bahan terbuang';

  final StafLokal pencatat;
  final VoidCallback saatTersimpan;

  @override
  ConsumerState<LembarBahanTerbuang> createState() => _LembarBahanTerbuangState();
}

class _LembarBahanTerbuangState extends ConsumerState<LembarBahanTerbuang> {
  final _cari = TextEditingController();
  final _jumlah = TextEditingController();
  final _catatan = TextEditingController();
  ProdukJual? _produk;
  SatuanJual? _satuan;
  AlasanBahanTerbuang? _alasan;
  bool _sibuk = false;
  String? _galatJumlah;
  String? _galat;

  @override
  void dispose() {
    _cari.dispose();
    _jumlah.dispose();
    _catatan.dispose();
    super.dispose();
  }

  void _Pilih(ProdukJual produk) => setState(() {
    _produk = produk;
    _satuan = LayananBahanTerbuang.AmbilSatuanBawaan(produk);
    _galat = null;
  });

  void _GantiProduk() => setState(() {
    _produk = null;
    _satuan = null;
    _galatJumlah = null;
  });

  Future<void> _Simpan() async {
    final produk = _produk;
    final satuan = _satuan;
    if (produk == null || satuan == null) {
      setState(() => _galat = 'Pilih produk yang terbuang.');
      return;
    }
    final alasan = _alasan;
    try {
      final jumlah = LayananBahanTerbuang.BacaJumlah(_jumlah.text, satuan);
      if (alasan == null) {
        setState(() {
          _galatJumlah = null;
          _galat = 'Pilih alasan terbuang.';
        });
        return;
      }
      setState(() {
        _sibuk = true;
        _galatJumlah = null;
        _galat = null;
      });
      final konteks = await ref.read(penyediaKonteksPenjualan.future);
      await ref
          .read(penyediaLayananBahanTerbuang)
          .Catat(
            produk: produk,
            satuan: satuan,
            jumlah: jumlah,
            alasan: alasan,
            pencatat: widget.pencatat,
            tanggalBisnis: konteks.HitungTanggalBisnis(ref.read(penyediaJam)()),
            catatan: _catatan.text,
          );
      final sesi = ref.read(penyediaSesi.notifier);
      if (mounted) {
        ScaffoldMessenger.maybeOf(context)
            ?.showSnackBar(SnackBar(content: Text('${produk.nama} dicatat terbuang. Dikirim otomatis saat online.')));
        widget.saatTersimpan();
      }
      await sesi.Sinkronkan();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() {
          if (galat.kode.startsWith('Jumlah')) {
            _galatJumlah = galat.pesan;
          } else {
            _galat = galat.pesan;
          }
        });
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
    final produk = _produk;
    final pilihanSatuan = produk == null ? const <SatuanJual>[] : LayananBahanTerbuang.AmbilPilihanSatuan(produk);
    return Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (produk == null)
            _PilihProduk(pengendali: _cari, saatPilih: _Pilih)
          else ...[
            Text('Produk', style: teks.labelMedium?.copyWith(color: warna.teksSekunder)),
            Row(
              children: [
                Expanded(
                  child: Text(produk.nama, maxLines: 2, overflow: TextOverflow.ellipsis, style: teks.titleMedium),
                ),
                SizedBox(
                  height: TokenJarak.targetSentuh,
                  child: TextButton(onPressed: _sibuk ? null : _GantiProduk, child: const Text('Ganti')),
                ),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: TextField(
                    key: const ValueKey('JumlahTerbuang'),
                    controller: _jumlah,
                    autofocus: true,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
                    decoration: InputDecoration(
                      labelText: 'Jumlah',
                      errorText: _galatJumlah,
                      errorMaxLines: 2,
                      border: const OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: TokenJarak.jarak12),
                Expanded(
                  child: pilihanSatuan.length == 1
                      ? InputDecorator(
                          decoration: const InputDecoration(labelText: 'Satuan', border: OutlineInputBorder()),
                          child: Text(_satuan?.nama ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
                        )
                      : DropdownButtonFormField<String>(
                          isExpanded: true,
                          initialValue: _satuan?.uuid,
                          decoration: const InputDecoration(labelText: 'Satuan', border: OutlineInputBorder()),
                          items: [
                            for (final s in pilihanSatuan)
                              DropdownMenuItem(
                                value: s.uuid,
                                child: Text(s.nama, maxLines: 1, overflow: TextOverflow.ellipsis),
                              ),
                          ],
                          onChanged: (uuid) =>
                              setState(() => _satuan = pilihanSatuan.where((s) => s.uuid == uuid).firstOrNull),
                        ),
                ),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak16),
            Text('Alasan', style: teks.labelMedium?.copyWith(color: warna.teksSekunder)),
            const SizedBox(height: TokenJarak.jarak8),
            Wrap(
              spacing: TokenJarak.jarak8,
              runSpacing: TokenJarak.jarak8,
              children: [
                for (final a in AlasanBahanTerbuang.values)
                  ChoiceChip(
                    label: Text(a.label),
                    selected: _alasan == a,
                    onSelected: _sibuk ? null : (_) => setState(() => _alasan = a),
                  ),
              ],
            ),
            const SizedBox(height: TokenJarak.jarak16),
            TextField(
              controller: _catatan,
              maxLength: LayananBahanTerbuang.panjangCatatanMaksimal,
              decoration: const InputDecoration(labelText: 'Catatan (opsional)', border: OutlineInputBorder()),
            ),
          ],
          if (_galat != null) ...[
            const SizedBox(height: TokenJarak.jarak8),
            Text(_galat!, style: TextStyle(color: warna.bahaya)),
          ],
          if (produk != null) ...[
            const SizedBox(height: TokenJarak.jarak8),
            SizedBox(
              height: 56,
              child: FilledButton(
                onPressed: _sibuk ? null : _Simpan,
                child: Text(_sibuk ? 'Menyimpan…' : 'Catat terbuang'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Kolom cari + daftar produk yang bisa dicatat terbuang (bahan baku ikut, walau tidak tampil di layar Jual).
class _PilihProduk extends ConsumerStatefulWidget {
  const _PilihProduk({required this.pengendali, required this.saatPilih});

  final TextEditingController pengendali;
  final ValueChanged<ProdukJual> saatPilih;

  @override
  ConsumerState<_PilihProduk> createState() => _PilihProdukState();
}

class _PilihProdukState extends ConsumerState<_PilihProduk> {
  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final katalog = ref.watch(penyediaKatalog);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          key: const ValueKey('CariProdukTerbuang'),
          controller: widget.pengendali,
          autofocus: true,
          onChanged: (_) => setState(() {}),
          onSubmitted: (kata) {
            final hasil = LayananBahanTerbuang.CariProduk(katalog.value ?? KatalogLokal.kosong, kata);
            if (hasil.length == 1) {
              widget.saatPilih(hasil.single);
            }
          },
          decoration: const InputDecoration(
            labelText: 'Cari produk atau bahan',
            hintText: 'Nama, SKU, atau pindai barcode',
            prefixIcon: Icon(Icons.search),
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        katalog.when(
          loading: () => const LinearProgressIndicator(),
          error: (_, _) => Text('Katalog tidak bisa dibaca. Coba lagi.', style: TextStyle(color: warna.bahaya)),
          data: (k) {
            final hasil = LayananBahanTerbuang.CariProduk(k, widget.pengendali.text);
            if (hasil.isEmpty) {
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak12),
                child: Text(
                  widget.pengendali.text.trim().isEmpty
                      ? 'Belum ada produk berstok atau beresep di katalog perangkat ini.'
                      : 'Produk tidak ditemukan, atau tidak punya stok/resep untuk dicatat terbuang.',
                  style: teks.bodyMedium,
                ),
              );
            }
            return Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final p in hasil)
                  ListTile(
                    minTileHeight: TokenJarak.targetSentuh,
                    contentPadding: EdgeInsets.zero,
                    title: Text(p.nama, maxLines: 2, overflow: TextOverflow.ellipsis),
                    subtitle: Text(
                      [
                        if (p.sku != null) p.sku!,
                        if (p.jenis == 'BahanBaku') 'Bahan baku',
                        if (p.jenis == 'Resep' || p.jenis == 'Paket') 'Dikurangi dari bahannya',
                      ].join(' | '),
                      style: teks.bodySmall,
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => widget.saatPilih(p),
                  ),
              ],
            );
          },
        ),
      ],
    );
  }
}
