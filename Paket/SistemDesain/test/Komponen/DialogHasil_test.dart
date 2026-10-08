import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Umpan hasil aksi POS (padanan DialogHasil web, D-51): gagal = dialog yang harus diakui, berhasil = notifikasi
/// melayang yang tidak menghalangi ketukan berikutnya.
void main() {
  Future<void> Pasang(WidgetTester tester, Widget Function(BuildContext) tombol) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(360, 740);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        theme: BuatTema(),
        home: Scaffold(
          body: Builder(builder: (context) => Center(child: tombol(context))),
        ),
      ),
    );
  }

  testWidgets('Gagal: dialog berikon galat memuat judul & alasan, tertutup dengan tombol Mengerti', (tester) async {
    await Pasang(
      tester,
      (context) => FilledButton(
        onPressed: () => UmpanAksi.Gagal(context, judul: 'Retur belum tersimpan', pesan: 'Shift belum terbuka.'),
        child: const Text('Uji'),
      ),
    );

    await tester.tap(find.text('Uji'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('DialogHasilGagal')), findsOneWidget);
    expect(find.text('Retur belum tersimpan'), findsOneWidget);
    expect(find.text('Shift belum terbuka.'), findsOneWidget);
    expect(find.byIcon(Icons.error_outline), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, 'Mengerti'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('DialogHasilGagal')), findsNothing);
  });

  testWidgets('BerhasilPenting: dialog berikon centang dengan tombol sendiri', (tester) async {
    await Pasang(
      tester,
      (context) => FilledButton(
        onPressed: () => UmpanAksi.BerhasilPenting(context, judul: 'Shift ditutup', pesan: 'Selisih kas Rp 0.'),
        child: const Text('Uji'),
      ),
    );

    await tester.tap(find.text('Uji'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('DialogHasilBerhasil')), findsOneWidget);
    expect(find.byIcon(Icons.check_circle_outline), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Oke'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('DialogHasilBerhasil')), findsNothing);
  });

  testWidgets('Berhasil: notifikasi melayang, tidak memblokir ketukan, dan hilang sendiri', (tester) async {
    var ketukan = 0;
    await Pasang(
      tester,
      (context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          FilledButton(
            onPressed: () => UmpanAksi.Berhasil(context, 'Kas keluar Rp 25.000 tercatat.'),
            child: const Text('Simpan'),
          ),
          FilledButton(onPressed: () => ketukan++, child: const Text('Lainnya')),
        ],
      ),
    );

    await tester.tap(find.text('Simpan'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.byKey(const ValueKey('UmpanBerhasil')), findsOneWidget);
    expect(find.text('Kas keluar Rp 25.000 tercatat.'), findsOneWidget);

    await tester.tap(find.text('Lainnya'));
    expect(ketukan, 1, reason: 'Notifikasi tidak menghalangi aksi berikutnya.');

    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('UmpanBerhasil')), findsNothing);
  });
}
