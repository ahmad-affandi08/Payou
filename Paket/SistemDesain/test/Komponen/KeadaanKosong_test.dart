import 'dart:io';

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

  testWidgets('bentuk ringkas tanpa ilustrasi memakai ikon', (tester) async {
    await tester.pumpWidget(_Bungkus(const KeadaanKosong(ikon: Icons.inbox, judul: 'Kosong', ringkas: true)));
    expect(find.byKey(const ValueKey('IlustrasiKosong')), findsNothing);
    expect(find.byIcon(Icons.inbox), findsOneWidget);
  });

  testWidgets('bentuk ringkas dengan ilustrasi menampilkan ilustrasi kecil, bukan ikon', (tester) async {
    await tester.pumpWidget(
      _Bungkus(
        const KeadaanKosong(ikon: Icons.inbox, judul: 'Kosong', ringkas: true, ilustrasi: IlustrasiKosong.Sinkron),
      ),
    );
    final gambar = tester.widget<Image>(find.byKey(const ValueKey('IlustrasiKosong')));
    expect(gambar.width, 96);
    expect(find.byIcon(Icons.inbox), findsNothing);
  });

  test('setiap nilai IlustrasiKosong punya berkas PNG di assets/ilustrasi', () {
    for (final nilai in IlustrasiKosong.values) {
      expect(File('assets/ilustrasi/${nilai.name}.png').existsSync(), isTrue, reason: '${nilai.name}.png hilang');
    }
  });
}
