import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/KlienPemilik.dart';
import 'FormatTampilan.dart';
import 'KeadaanData.dart';

/// OWN-11: insight minggu lalu — omzet bersih dibanding minggu sebelumnya, hari teramai, produk terlaris, produk naik &
/// turun, saran restock (bila berizin persediaan), dan pengingat Lebaran. Isinya sama dengan pesan WhatsApp Senin.
class LayarInsight extends ConsumerWidget {
  const LayarInsight({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.refresh(penyediaInsight.future),
      child: KeadaanData(
        nilai: ref.watch(penyediaInsight),
        saatCobaLagi: () => ref.invalidate(penyediaInsight),
        isi: (data) => data == null
            ? ListView(
                padding: const EdgeInsets.all(TokenJarak.jarak16),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  Text(
                    'Belum ada penjualan dua minggu terakhir untuk dibandingkan.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              )
            : _IsiInsight(data: data),
      ),
    );
  }

  /// Persen perubahan dari server ("12.5") ke tampilan "+12,5%"; null bila minggu sebelumnya nol.
  static String? Persen(String? nilai) {
    if (nilai == null) return null;
    final teks = nilai.replaceAll('.', ',');
    return nilai.startsWith('-') ? '$teks%' : '+$teks%';
  }

  /// Ringkasan satu baris untuk kartu Beranda.
  static String Ringkasan(InsightMingguanPemilik d) {
    final persen = Persen(d.persenPerubahan);
    return '${FormatTampilan.Rupiah(d.bersih)}${persen == null ? '' : ' ($persen)'} | ${d.jumlahTransaksi} transaksi';
  }
}

class _IsiInsight extends StatelessWidget {
  const _IsiInsight({required this.data});

  final InsightMingguanPemilik data;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final d = data;
    final persen = LayarInsight.Persen(d.persenPerubahan);
    final naik = d.persenPerubahan != null && !d.persenPerubahan!.startsWith('-');

    Widget Judul(String t) => Padding(
      padding: const EdgeInsets.only(top: TokenJarak.jarak24, bottom: TokenJarak.jarak8),
      child: Text(t, style: teks.titleMedium),
    );

    String Tanggal(String iso) => FormatTampilan.Tanggal(DateTime.parse(iso));

    return ListView(
      padding: const EdgeInsets.all(TokenJarak.jarak16),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Text('${Tanggal(d.dari)} – ${Tanggal(d.sampai)}', style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
        const SizedBox(height: TokenJarak.jarak8),
        Text('Penjualan bersih', style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
        Text(FormatTampilan.Rupiah(d.bersih), style: teks.displaySmall),
        Row(
          children: [
            if (persen != null)
              Icon(naik ? Icons.trending_up : Icons.trending_down, size: 18, color: naik ? warna.sukses : warna.bahaya),
            const SizedBox(width: TokenJarak.jarak4),
            Expanded(
              child: Text(
                persen == null
                    ? 'Minggu sebelumnya ${FormatTampilan.Rupiah(d.bersihSebelumnya)}'
                    : '$persen dari minggu sebelumnya (${FormatTampilan.Rupiah(d.bersihSebelumnya)})',
                style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
              ),
            ),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Text(
          [
            '${d.jumlahTransaksi} transaksi, rata-rata ${FormatTampilan.Rupiah(d.rataTransaksi)}',
            if (d.hariTeramai != null)
              'teramai ${Tanggal(d.hariTeramai!.tanggal)} (${FormatTampilan.Rupiah(d.hariTeramai!.bersih)})',
          ].join(' | '),
          style: teks.bodyMedium,
        ),
        if (d.lebaran != null) ...[
          const SizedBox(height: TokenJarak.jarak12),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.event_outlined, color: warna.peringatan),
            title: Text('Lebaran ${d.lebaran!.sisaHari} hari lagi'),
            subtitle: const Text('Saran restock sudah memperhitungkan lonjakan musim Lebaran.'),
          ),
        ],
        if (d.terlaris.isNotEmpty) ...[
          Judul('Terlaris'),
          for (final p in d.terlaris)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(p.nama),
              subtitle: Text('${FormatTampilan.Jumlah(p.qty)} terjual'),
              trailing: Text(FormatTampilan.Rupiah(p.bersih), style: teks.titleSmall),
            ),
        ],
        if (d.naik.isNotEmpty) ...[
          Judul('Naik paling banyak'),
          for (final p in d.naik)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.arrow_upward, color: warna.sukses),
              title: Text(p.nama),
              trailing: Text('+${FormatTampilan.Rupiah(p.selisih)}', style: teks.titleSmall),
            ),
        ],
        if (d.turun.isNotEmpty) ...[
          Judul('Turun paling banyak'),
          for (final p in d.turun)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.arrow_downward, color: warna.bahaya),
              title: Text(p.nama),
              trailing: Text(FormatTampilan.Rupiah(p.selisih), style: teks.titleSmall),
            ),
        ],
        if (d.restock.isNotEmpty) ...[
          Judul('Segera restock'),
          for (final r in d.restock)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.inventory_2_outlined, color: warna.peringatan),
              title: Text(r.nama),
              subtitle: Text(
                '${r.hariHabis <= 0 ? 'habis' : 'habis ±${r.hariHabis} hari lagi'} | ${r.gudang} | '
                'saran beli ${FormatTampilan.Jumlah(r.saranBeli)} ${r.satuan}',
              ),
            ),
        ],
      ],
    );
  }
}
