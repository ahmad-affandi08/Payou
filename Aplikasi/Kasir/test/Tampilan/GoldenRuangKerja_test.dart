import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';

import 'package:kasir/Data/RepositoriKasir.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/MuatFont.dart';
import '../Pendukung/PasangAplikasi.dart';

/// Golden tiap layar ruang kerja selain Jual (§17.2.7).
///
/// Ada karena masalahnya selalu tata letak, bukan logika: sampai v2.68 semua layar ini rata kiri dengan ±350dp
/// menganga di kanan, dan tidak satu pun test menangkapnya — test yang ada hanya memeriksa teks & perilaku.
void main() {
  testWidgets('golden layar ruang kerja selain Jual di 1280 dp', (tester) async {
    await MuatFontMerek();
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.repositori.SimpanPengaturan(KunciPengaturan.namaOutlet, 'Kopi Senja Solo Baru');
      await u.repositori.SimpanPengaturan(KunciPengaturan.kodePerangkat, 'POS-001');
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u, ukuran: const Size(1280, 900));
    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);

    for (final label in ['Riwayat', 'Kas', 'Shift', 'Sinkron', 'Pengaturan']) {
      await tester.tap(find.text(label));
      await Tunggu(tester);
      await expectLater(find.byType(MaterialApp), matchesGoldenFile('Golden/Layar$label.png'));
    }

    await Lepas(tester, u);
  });
}
