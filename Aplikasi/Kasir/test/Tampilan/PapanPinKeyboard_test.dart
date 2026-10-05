import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// Audit kemudahan pakai #9: papan PIN menerima keyboard fisik (kasir Windows): angka baris atas & numpad mengisi,
/// Backspace menghapus satu angka.
void main() {
  const angkaAtas = {
    '0': LogicalKeyboardKey.digit0,
    '1': LogicalKeyboardKey.digit1,
    '2': LogicalKeyboardKey.digit2,
    '3': LogicalKeyboardKey.digit3,
    '4': LogicalKeyboardKey.digit4,
    '5': LogicalKeyboardKey.digit5,
    '6': LogicalKeyboardKey.digit6,
    '7': LogicalKeyboardKey.digit7,
    '8': LogicalKeyboardKey.digit8,
    '9': LogicalKeyboardKey.digit9,
  };
  const numpad = {
    '0': LogicalKeyboardKey.numpad0,
    '1': LogicalKeyboardKey.numpad1,
    '2': LogicalKeyboardKey.numpad2,
    '3': LogicalKeyboardKey.numpad3,
    '4': LogicalKeyboardKey.numpad4,
    '5': LogicalKeyboardKey.numpad5,
    '6': LogicalKeyboardKey.numpad6,
    '7': LogicalKeyboardKey.numpad7,
    '8': LogicalKeyboardKey.numpad8,
    '9': LogicalKeyboardKey.numpad9,
  };

  for (final (nama, peta) in [('angka baris atas', angkaAtas), ('numpad', numpad)]) {
    testWidgets('masuk kasir dengan PIN dari keyboard ($nama), Backspace menghapus satu angka', (tester) async {
      final u = LingkunganUji.Buat();
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
      });
      u.server.penangan = (_) async => throw http.ClientException('offline');
      await PasangAplikasi(tester, u, ukuran: const Size(1280, 900));
      await PilihKasir(tester, 'Rina Wulandari');
      await Tunggu(tester);

      final pin = KasusPin(0)['Pin']! as String;
      await tester.sendKeyEvent(peta['9']!);
      await tester.pump();
      expect(find.bySemanticsLabel('PIN terisi 1 dari 6 angka'), findsOneWidget);
      await tester.sendKeyEvent(LogicalKeyboardKey.backspace);
      await tester.pump();
      expect(find.bySemanticsLabel('PIN terisi 0 dari 6 angka'), findsOneWidget);

      for (final angka in pin.split('')) {
        await tester.sendKeyEvent(peta[angka]!);
        await tester.pump();
      }
      await Tunggu(tester);
      expect(find.byType(RuangKerja), findsOneWidget);
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
