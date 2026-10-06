import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pemilik/Aplikasi/Lingkungan.dart';
import 'package:pemilik/Data/PenyimpanSesi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/PasangPemilik.dart';

void main() {
  Future<void> Pasang(WidgetTester tester, Lingkungan lingkungan) => PasangPemilik(
    tester,
    server: ServerTiruan()..penangan = (_) async => JsonUji({}),
    sesi: PenyimpanSesiMemori(),
    lingkungan: lingkungan,
  );

  testWidgets('menampilkan penanda lingkungan selain produksi', (tester) async {
    await Pasang(tester, Lingkungan.Staging);
    expect(find.text('Masuk ke Payoung Owner'), findsOneWidget);
    expect(find.byType(Banner), findsOneWidget);
  });

  testWidgets('tanpa penanda lingkungan di produksi', (tester) async {
    await Pasang(tester, Lingkungan.Produksi);
    expect(find.byType(Banner), findsNothing);
  });

  testWidgets('hanya memakai tema terang walau sistem operasi dalam mode gelap (D-14)', (tester) async {
    tester.platformDispatcher.platformBrightnessTestValue = Brightness.dark;
    addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);
    await Pasang(tester, Lingkungan.Produksi);

    final aplikasi = tester.widget<MaterialApp>(find.byType(MaterialApp));
    expect(aplikasi.darkTheme, isNull);
    expect(aplikasi.highContrastDarkTheme, isNull);
    expect(aplikasi.themeMode, ThemeMode.light);

    final tema = Theme.of(tester.element(find.text('Masuk ke Payoung Owner')));
    expect(tema.brightness, Brightness.light);
    expect(tema.extension<TokenWarna>(), TokenWarna.bawaan);
  });
}
