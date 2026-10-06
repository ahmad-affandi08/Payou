import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sistem_desain/SistemDesain.dart';

Widget _Bungkus(Widget anak) => MaterialApp(
  theme: BuatTema(),
  home: Scaffold(body: Center(child: anak)),
);

void main() {
  testWidgets('bentuk penuh menampilkan ilustrasi merek (D-68)', (tester) async {
    await tester.pumpWidget(
      _Bungkus(const KeadaanKosong(ikon: Icons.inbox, judul: 'Belum ada data', ilustrasi: IlustrasiKosong.Laporan)),
    );
    expect(find.byKey(const ValueKey('IlustrasiKosong')), findsOneWidget);
    expect(find.text('Belum ada data'), findsOneWidget);
  });

  testWidgets('bentuk ringkas tetap memakai ikon, tanpa ilustrasi', (tester) async {
    await tester.pumpWidget(_Bungkus(const KeadaanKosong(ikon: Icons.inbox, judul: 'Kosong', ringkas: true)));
    expect(find.byKey(const ValueKey('IlustrasiKosong')), findsNothing);
    expect(find.byIcon(Icons.inbox), findsOneWidget);
  });
}
