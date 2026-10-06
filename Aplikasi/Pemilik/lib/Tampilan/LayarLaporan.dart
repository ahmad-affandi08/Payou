import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import 'BilahSaringan.dart';
import 'FormatTampilan.dart';
import 'KeadaanData.dart';

/// Laporan ringkas OWN-05: penjualan per produk/kategori/kasir/jam/kanal pada tanggal & outlet terpilih.
class LayarLaporan extends ConsumerWidget {
  const LayarLaporan({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final laporan = ref.watch(penyediaLaporan);
    final kelompok = ref.watch(penyediaKelompokLaporan);
    final outlet = ref.watch(penyediaDasbor).value?.outlet ?? const [];
    return ListView(
      padding: const EdgeInsets.all(TokenJarak.jarak16),
      children: [
        BilahSaringan(outlet: outlet),
        const SizedBox(height: TokenJarak.jarak12),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            for (final (nilai, label) in PengaturKelompokLaporan.pilihan)
              ChoiceChip(
                label: Text(label),
                selected: kelompok == nilai,
                showCheckmark: false,
                onSelected: (_) => ref.read(penyediaKelompokLaporan.notifier).Atur(nilai),
              ),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak12),
        KeadaanData(
          nilai: laporan,
          saatCobaLagi: () => ref.invalidate(penyediaLaporan),
          isi: (l) => l.baris.isEmpty
              ? const KotakPanel(
                  anak: KeadaanKosong(
                    ilustrasi: IlustrasiKosong.Laporan,
                    ikon: Icons.bar_chart_outlined,
                    judul: 'Belum ada penjualan pada tanggal ini.',
                    keterangan: 'Ringkasan penjualan per produk, kategori, kasir, jam, dan kanal muncul di sini.',
                  ),
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    KotakPanel(
                      rapat: true,
                      anak: Column(
                        children: [
                          for (final (i, b) in l.baris.indexed) ...[
                            if (i > 0) Divider(height: 1, color: warna.garis),
                            ListTile(
                              title: Text(kelompok == 'Jam' ? 'Jam ${b.nama}' : b.nama),
                              subtitle: b.jumlah == null ? null : Text('Jumlah ${b.jumlah}'),
                              trailing: Text(FormatTampilan.Rupiah(b.omzet), style: teks.titleSmall),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: TokenJarak.jarak12),
                    DecoratedBox(
                      decoration: BoxDecoration(
                        color: warna.brandGelap,
                        borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
                      ),
                      child: ListTile(
                        title: Text('Total', style: teks.titleSmall?.copyWith(color: warna.permukaan)),
                        trailing: Text(
                          FormatTampilan.Rupiah(l.totalOmzet),
                          style: teks.titleMedium?.copyWith(color: warna.permukaan, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                  ],
                ),
        ),
      ],
    );
  }
}
