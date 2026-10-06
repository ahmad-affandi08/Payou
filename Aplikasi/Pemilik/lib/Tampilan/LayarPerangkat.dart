import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import 'FormatTampilan.dart';
import 'KeadaanData.dart';

/// Status perangkat POS per outlet (OWN-08): terakhir aktif, data belum terkirim, versi aplikasi. Perangkat yang tidak
/// menghubungi server lebih dari 30 menit ditandai.
class LayarPerangkat extends ConsumerWidget {
  const LayarPerangkat({super.key});

  static const Duration batasDiam = Duration(minutes: 30);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warna = TokenWarna.AmbilDari(context);
    final sekarang = ref.read(penyediaJam)();
    return RefreshIndicator(
      onRefresh: () => ref.refresh(penyediaPerangkat.future),
      child: KeadaanData(
        nilai: ref.watch(penyediaPerangkat),
        saatCobaLagi: () => ref.invalidate(penyediaPerangkat),
        isi: (daftar) => ListView(
          padding: const EdgeInsets.all(TokenJarak.jarak16),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (daftar.isEmpty)
              const KotakPanel(
                anak: KeadaanKosong(
                  ilustrasi: IlustrasiKosong.Outlet,
                  ikon: Icons.point_of_sale_outlined,
                  judul: 'Belum ada perangkat POS.',
                  keterangan: 'Perangkat kasir yang sudah diaktifkan muncul di sini beserta status sambungannya.',
                ),
              )
            else
              KotakPanel(
                rapat: true,
                anak: Column(
                  children: [
                    for (final (i, p) in daftar.indexed) ...[
                      if (i > 0) Divider(height: 1, color: warna.garis),
                      Builder(
                        builder: (context) {
                          final diam =
                              p.status == 'Aktif' &&
                              (p.terakhirAktifPada == null || sekarang.difference(p.terakhirAktifPada!) > batasDiam);
                          return ListTile(
                            leading: CircleAvatar(
                              backgroundColor: diam ? warna.peringatan.withValues(alpha: 0.12) : warna.latar,
                              child: Icon(
                                diam ? Icons.cloud_off_outlined : Icons.point_of_sale_outlined,
                                color: diam ? warna.peringatan : warna.teksSekunder,
                              ),
                            ),
                            title: Text('${p.nama} (${p.kode})'),
                            subtitle: Text(
                              [
                                '${p.jenis} | ${p.outlet}',
                                if (p.status != 'Aktif') p.status,
                                if (p.terakhirAktifPada != null)
                                  'aktif ${FormatTampilan.TanggalJam(p.terakhirAktifPada!)}${diam ? ' (lama tidak tersambung)' : ''}',
                                if (p.outboxTertunda > 0) '${p.outboxTertunda} data belum terkirim',
                                if (p.versiAplikasi != null) 'versi ${p.versiAplikasi}',
                              ].join(' | '),
                            ),
                          );
                        },
                      ),
                    ],
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
