import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Tanda muat (D-58): logo P di lingkaran putih + cincin berputar; diam bila animasi dimatikan.
void main() {
  final cincin = find.descendant(of: find.byType(TandaMuat), matching: find.byType(RotationTransition));

  testWidgets('TandaMuat: berlabel Memuat, berukuran sesuai, memuat logo, dan animasinya berjalan', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: BuatTema(),
        home: const Scaffold(body: Center(child: TandaMuat(ukuran: 72))),
      ),
    );

    expect(find.bySemanticsLabel('Memuat'), findsOneWidget);
    expect(tester.getSize(find.byType(TandaMuat)), const Size(72, 72));
    expect(find.byType(Image), findsOneWidget);

    final awal = tester.widget<RotationTransition>(cincin).turns.value;
    await tester.pump(const Duration(milliseconds: 600));
    final lalu = tester.widget<RotationTransition>(cincin).turns.value;
    expect(lalu, isNot(awal));
  });

  testWidgets('TandaMuat: animasi dimatikan di perangkat → cincin diam', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: BuatTema(),
        builder: (context, anak) =>
            MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: anak!),
        home: const Scaffold(body: Center(child: TandaMuat())),
      ),
    );

    final awal = tester.widget<RotationTransition>(cincin).turns.value;
    await tester.pump(const Duration(milliseconds: 600));
    expect(tester.widget<RotationTransition>(cincin).turns.value, awal);
  });
}
