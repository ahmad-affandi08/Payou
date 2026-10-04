import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/KlienPemilik.dart';
import 'FormatTampilan.dart';
import 'KeadaanData.dart';

/// OWN-10: pantau karyawan. Hari ini: siapa hadir (terlambat, sumber absen), siapa sedang bekerja, siapa dijadwalkan
/// tetapi belum masuk. Bulan berjalan: progres target penjualan dan komisi per karyawan.
class LayarKaryawan extends ConsumerWidget {
  const LayarKaryawan({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.refresh(penyediaKaryawan.future),
      child: KeadaanData(
        nilai: ref.watch(penyediaKaryawan),
        saatCobaLagi: () => ref.invalidate(penyediaKaryawan),
        isi: (data) => _IsiKaryawan(data: data),
      ),
    );
  }

  /// Label sumber absen untuk subjudul baris kehadiran.
  static String LabelSumber(String sumber) => switch (sumber) {
    'Web' => 'dari HP',
    'Manual' => 'dicatat pengelola',
    _ => 'di kasir',
  };

  /// Ringkasan satu baris untuk kartu Beranda, misal "5 hadir | 1 terlambat | 2 belum masuk".
  static String Ringkasan(PantauKaryawanPemilik d) => [
    '${d.hadir} hadir',
    if (d.terlambat > 0) '${d.terlambat} terlambat',
    if (d.belumMasuk > 0) '${d.belumMasuk} belum masuk',
  ].join(' | ');
}

class _IsiKaryawan extends StatelessWidget {
  const _IsiKaryawan({required this.data});

  final PantauKaryawanPemilik data;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final d = data;

    Widget Judul(String t) => Padding(
      padding: const EdgeInsets.only(top: TokenJarak.jarak24, bottom: TokenJarak.jarak8),
      child: Text(t, style: teks.titleMedium),
    );

    Widget Angka(String label, int nilai, {Color? warnaNilai}) => Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: teks.bodySmall),
          Text('$nilai', style: teks.titleLarge?.copyWith(color: warnaNilai)),
        ],
      ),
    );

    return ListView(
      padding: const EdgeInsets.all(TokenJarak.jarak16),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Text(
          'Hari ini | ${FormatTampilan.Tanggal(DateTime.parse(d.tanggal))}',
          style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Row(
          children: [
            Angka('Hadir', d.hadir),
            Angka('Sedang bekerja', d.sedangBekerja),
            Angka('Terlambat', d.terlambat, warnaNilai: d.terlambat > 0 ? warna.peringatan : null),
            Angka('Belum masuk', d.belumMasuk, warnaNilai: d.belumMasuk > 0 ? warna.bahaya : null),
          ],
        ),
        if (d.daftarBelumMasuk.isNotEmpty) ...[
          Judul('Belum absen masuk'),
          for (final b in d.daftarBelumMasuk)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.person_off_outlined, color: warna.bahaya),
              title: Text(b.nama),
              subtitle: Text(['Jadwal ${b.jadwal}', ?b.outlet].join(' | ')),
            ),
        ],
        Judul('Kehadiran'),
        if (d.kehadiran.isEmpty)
          Text('Belum ada yang absen hari ini.', style: teks.bodyMedium)
        else
          for (final k in d.kehadiran)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                k.jamKeluar == null ? Icons.badge_outlined : Icons.check_circle_outline,
                color: k.terlambatMenit > 0 ? warna.peringatan : warna.sukses,
              ),
              title: Text(k.nama),
              subtitle: Text(
                [
                  'Masuk ${k.jamMasuk}${k.jamKeluar == null ? ', masih bekerja' : ', keluar ${k.jamKeluar}'}',
                  if (k.terlambatMenit > 0) 'terlambat ${k.terlambatMenit} menit',
                  LayarKaryawan.LabelSumber(k.sumber),
                  ?k.outlet,
                ].join(' | '),
              ),
            ),
        if (d.target.isNotEmpty) ...[
          Judul('Target bulan ini'),
          for (final t in d.target)
            Padding(
              padding: const EdgeInsets.only(bottom: TokenJarak.jarak12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${t.sasaran} (${t.cakupan})', style: teks.bodyLarge),
                  const SizedBox(height: TokenJarak.jarak4),
                  LinearProgressIndicator(
                    value: ((double.tryParse(t.persen) ?? 0) / 100).clamp(0, 1),
                    semanticsLabel: 'Progres target ${t.sasaran}',
                  ),
                  const SizedBox(height: TokenJarak.jarak4),
                  Text(
                    [
                      '${FormatTampilan.Rupiah(t.realisasi)} dari ${FormatTampilan.Rupiah(t.nilai)} '
                          '(${t.persen.replaceAll('.', ',')}%)',
                      if (t.proyeksi != null) 'proyeksi ${FormatTampilan.Rupiah(t.proyeksi!)}',
                    ].join(' | '),
                    style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                  ),
                ],
              ),
            ),
        ],
        if (d.komisi.isNotEmpty) ...[
          Judul('Komisi bulan ini'),
          for (final k in d.komisi)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(k.nama),
              trailing: Text(FormatTampilan.Rupiah(k.komisi), style: teks.titleSmall),
            ),
        ],
      ],
    );
  }
}
