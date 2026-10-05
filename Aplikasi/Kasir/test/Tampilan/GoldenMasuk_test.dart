import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import 'package:kasir/Data/RepositoriKasir.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/MuatFont.dart';
import '../Pendukung/PasangAplikasi.dart';

/// Golden alur masuk kasir (D-59): pilih kasir → PIN → halaman penjualan dengan modal Buka shift. Ada karena layar
/// pertama kasir dulu hanya diperiksa teksnya, bukan tampilannya.
void main() {
  for (final (nama, ukuran) in [('Lebar', const Size(1280, 800)), ('Ponsel', const Size(390, 844))]) {
    testWidgets('golden masuk kasir $nama', (tester) async {
      await MuatFontMerek();
      final u = LingkunganUji.Buat();
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.repositori.SimpanPengaturan(KunciPengaturan.namaOutlet, 'Kopi Senja Solo Baru');
        await u.repositori.SimpanPengaturan(KunciPengaturan.kodePerangkat, 'POS-001');
      });
      u.server.penangan = (_) async => throw http.ClientException('offline');
      await PasangAplikasi(tester, u, ukuran: ukuran);
      await Tunggu(tester);
      await expectLater(find.byType(MaterialApp), matchesGoldenFile('Golden/Masuk${nama}1Pilih.png'));

      await tester.tap(find.text('Rina Wulandari'));
      await tester.pump();
      await Tunggu(tester);
      await expectLater(find.byType(MaterialApp), matchesGoldenFile('Golden/Masuk${nama}2Pin.png'));

      await KetikPin(tester, KasusPin(0)['Pin']! as String);
      await Tunggu(tester);
      expect(find.text('Buka shift | Rina Wulandari'), findsOneWidget);
      await expectLater(find.byType(MaterialApp), matchesGoldenFile('Golden/Masuk${nama}3BukaShift.png'));

      await Lepas(tester, u);
    });
  }
}
