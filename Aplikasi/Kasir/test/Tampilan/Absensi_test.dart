import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:kasir/Tampilan/LayarAbsensi.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-18 EMP-03 di aplikasi kasir: dari layar pilih kasir → Absen → pilih nama → PIN → swafoto (bila kamera ada) →
/// absen masuk lalu keluar tercatat di outbox, tanpa membuka sesi kasir.
void main() {
  Future<LingkunganUji> Pasang(
    WidgetTester tester,
    Size ukuran,
    KameraSwafotoTiruan kamera, {
    List<Map<String, Object?>>? karyawan,
  }) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() => u.SiapkanAktif());
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji(karyawan: karyawan));
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran, kamera: kamera);
    await Tunggu(tester, const Duration(milliseconds: 600));
    return u;
  }

  Finder DiAbsensi(Finder f) => find.descendant(of: find.byType(LayarAbsensi), matching: f);

  Future<void> Absen(WidgetTester tester, String nama, int kasusPin) async {
    await tester.tap(find.widgetWithText(OutlinedButton, 'Absen masuk/keluar'));
    await Tunggu(tester);
    expect(find.byType(LayarAbsensi), findsOneWidget);
    await tester.tap(DiAbsensi(find.widgetWithText(OutlinedButton, nama)));
    await Tunggu(tester);
    await KetikPin(tester, KasusPin(kasusPin)['Pin']! as String);
    await Tunggu(tester, const Duration(milliseconds: 600));
  }

  for (final (nama, ukuran) in [
    ('360', const Size(360, 780)),
    ('800', const Size(800, 1280)),
    ('1280', const Size(1280, 900)),
  ]) {
    testWidgets('absen masuk lalu keluar dengan swafoto di $nama dp', (tester) async {
      final kamera = KameraSwafotoTiruan(foto: Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xE0, 9]));
      final u = await Pasang(tester, ukuran, kamera);

      await Absen(tester, 'Rina Wulandari', 0);
      expect(find.textContaining('Rina Wulandari absen masuk pukul'), findsOneWidget);
      expect(kamera.dipanggil, 1);
      await tester.tap(find.widgetWithText(FilledButton, 'Selesai'));
      await Tunggu(tester);
      expect(find.textContaining('belum keluar'), findsOneWidget);

      await tester.tap(DiAbsensi(find.widgetWithText(OutlinedButton, 'Rina Wulandari')));
      await Tunggu(tester);
      expect(find.text('Absen keluar Rina Wulandari'), findsOneWidget);
      await KetikPin(tester, KasusPin(0)['Pin']! as String);
      await Tunggu(tester, const Duration(milliseconds: 600));
      expect(find.textContaining('Rina Wulandari absen keluar pukul'), findsOneWidget);

      final outbox = await tester.runAsync(() => u.db.select(u.db.outbox).get());
      expect(outbox!.map((o) => o.Jenis), ['Absensi.Masuk', 'Absensi.Keluar']);
      expect(
        (jsonDecode(outbox.first.Data) as Map<String, Object?>)['Swafoto'],
        base64Encode([0xFF, 0xD8, 0xFF, 0xE0, 9]),
      );
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  testWidgets('swafoto dibatalkan → tidak tercatat; PIN salah → pesan galat', (tester) async {
    final u = await Pasang(tester, const Size(1280, 900), KameraSwafotoTiruan());
    await Absen(tester, 'Rina Wulandari', 0);
    expect(find.text('Swafoto wajib untuk absen. Coba lagi dan ambil foto wajah.'), findsOneWidget);
    expect(await tester.runAsync(() => u.db.select(u.db.outbox).get()), isEmpty);
    await Lepas(tester, u);
  });

  for (final (pilih, absen) in [('Absen masuk', true), ('Nanti', false)]) {
    testWidgets('audit #31: toko berkaryawan ditawari absen masuk setelah PIN kasir ($pilih)', (tester) async {
      final kamera = KameraSwafotoTiruan(foto: Uint8List.fromList([0xFF, 0xD8, 0xFF, 0xE0, 9]));
      final u = await Pasang(
        tester,
        const Size(1280, 900),
        kamera,
        karyawan: [
          {'Uuid': '01K5KRY0000000000000000001', 'Nama': 'Rina Wulandari', 'Jabatan': 'Kasir'},
        ],
      );
      await PilihKasir(tester, 'Rina Wulandari');
      await tester.pump();
      await KetikPin(tester, KasusPin(0)['Pin']! as String);
      await Tunggu(tester);
      expect(find.text('Absen masuk sekarang?'), findsOneWidget);
      await tester.tap(find.text(pilih));
      await Tunggu(tester, const Duration(milliseconds: 600));
      expect(find.text('Absen masuk sekarang?'), findsNothing);

      final outbox = await tester.runAsync(() => u.db.select(u.db.outbox).get());
      expect(outbox!.where((o) => o.Jenis == 'Absensi.Masuk'), hasLength(absen ? 1 : 0));
      expect(kamera.dipanggil, absen ? 1 : 0, reason: 'PIN tidak diminta lagi; hanya swafoto.');
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
