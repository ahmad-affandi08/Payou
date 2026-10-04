import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:pemilik/Data/PenyimpanSesi.dart';

import '../test/Pendukung/PasangPemilik.dart';

/// Penghasil foto produk untuk situs pemasaran `payou.id` (bukan test regresi; di luar `test/` agar tidak ikut CI):
/// Beranda aplikasi Pemilik asli di ukuran HP dengan data contoh dua outlet kafe.
///
/// ```
/// cd Aplikasi/Pemilik && flutter test AlatSitus/FotoSitus_test.dart --update-goldens
/// ```
Future<void> MuatFont() async {
  const berkas = {
    'AtkinsonHyperlegibleNext': ['AtkinsonHyperlegibleNextVariable.ttf', 'AtkinsonHyperlegibleNextVariableMiring.ttf'],
    'AtkinsonHyperlegibleMono': ['AtkinsonHyperlegibleMonoVariable.ttf'],
  };
  for (final keluarga in berkas.entries) {
    final pemuat = FontLoader('packages/sistem_desain/${keluarga.key}');
    for (final nama in keluarga.value) {
      pemuat.addFont(rootBundle.load('packages/sistem_desain/assets/fonts/$nama').then((b) => b.buffer.asByteData()));
    }
    await pemuat.load();
  }
  final ikon = FontLoader('MaterialIcons')
    ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf').then((b) => b.buffer.asByteData()));
  await ikon.load();
}

void main() {
  testWidgets('foto Beranda aplikasi Pemilik untuk situs (390 × 844)', (tester) async {
    await MuatFont();
    final server = ServerTiruan();
    final sesi = PenyimpanSesiMemori();
    server.penangan = (http.Request p) async {
      final jalur = p.url.path.replaceFirst('/api/pemilik/v1/', '');
      return switch (jalur) {
        'masuk' => JsonUji({
          'Token': '5|contoh',
          'Pengguna': {'Uuid': 'U1', 'Nama': 'Bu Sari', 'Email': 'sari@contoh.id'},
          'Tenant': [
            {'Uuid': 'T1', 'Nama': 'Kopi Senja', 'Pemilik': true},
          ],
        }),
        'profil' => JsonUji({
          'Pengguna': {'Nama': 'Bu Sari'},
          'Tenant': [
            {'Uuid': 'T1', 'Nama': 'Kopi Senja', 'Pemilik': true},
          ],
        }),
        'dasbor' => JsonUji({
          ...DasborUji(omzet: '8475000.00'),
          'Ringkasan': {
            'Omzet': '8475000.00',
            'LabaKotor': '3390000.00',
            'Transaksi': 214,
            'RataRata': '39603.00',
            'OmzetKemarin': '7430000.00',
            'OmzetMingguLalu': '7910000.00',
          },
          'PerOutlet': [
            {'Uuid': 'O1', 'Nama': 'Solo Baru', 'Omzet': '5120000.00', 'Transaksi': 131},
            {'Uuid': 'O2', 'Nama': 'Kartasura', 'Omzet': '3355000.00', 'Transaksi': 83},
          ],
          'PerJam': [
            for (final (jam, omzet) in [
              (8, 310000),
              (9, 520000),
              (10, 640000),
              (11, 820000),
              (12, 1210000),
              (13, 980000),
              (14, 610000),
              (15, 720000),
              (16, 890000),
              (17, 760000),
              (18, 620000),
              (19, 395000),
            ])
              {'Jam': jam, 'Omzet': '$omzet.00'},
          ],
          'ProdukTeratas': [
            {'Nama': 'Es Kopi Susu Aren', 'Jumlah': '96.0000', 'Omzet': '1728000.00'},
            {'Nama': 'Matcha Latte', 'Jumlah': '41.0000', 'Omzet': '1066000.00'},
            {'Nama': 'Croissant Cokelat', 'Jumlah': '38.0000', 'Omzet': '950000.00'},
          ],
          'PerluTindakan': [
            {'Jenis': 'StokMenipis', 'Judul': 'Susu segar hampir habis', 'Keterangan': 'Sisa 4 liter di Solo Baru'},
          ],
        }),
        _ => JsonUji({}, 404),
      };
    };
    await PasangPemilik(tester, server: server, sesi: sesi, ukuran: const Size(390, 844));
    await tester.enterText(find.widgetWithText(TextField, 'Email'), 'sari@contoh.id');
    await tester.enterText(find.widgetWithText(TextField, 'Kata sandi'), 'rahasia123');
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('Hasil/PemilikBeranda.png'));
  });
}
