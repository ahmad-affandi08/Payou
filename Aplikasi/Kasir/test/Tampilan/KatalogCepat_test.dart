import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// K-16 (§17.2.7): kategori "Terlaris" dari penjualan perangkat ini, kategori terakhir diingat per perangkat, dan
/// daftar pintasan keyboard lewat `?`.
void main() {
  Future<LingkunganUji> Masuk(WidgetTester tester, Size ukuran, {String? kategoriTersimpan}) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      if (kategoriTersimpan != null) {
        await u.repositori.SimpanPengaturan(KunciPengaturan.kategoriTerakhirJual, kategoriTersimpan);
      }
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);
    return u;
  }

  Future<void> Ketuk(WidgetTester tester, Finder f) async {
    await tester.ensureVisible(f);
    await tester.pump();
    await tester.tap(f);
    await Tunggu(tester);
  }

  Finder Ubin(String nama) => find.byWidgetPredicate((w) => w is UbinProduk && w.nama == nama);
  Finder Chip(String label) => find.widgetWithText(ChoiceChip, label);

  for (final ukuran in const [Size(1280, 900), Size(360, 740)]) {
    testWidgets(
      'Terlaris muncul setelah ada penjualan, menampilkan produk terjual, pilihan diingat (${ukuran.width.toInt()} dp)',
      (tester) async {
        final u = await Masuk(tester, ukuran);
        expect(Chip('Terlaris'), findsNothing, reason: 'Belum ada penjualan di perangkat ini.');

        await Ketuk(tester, Ubin('Americano Panas'));
        if (ukuran.width < 600) {
          await Ketuk(tester, find.textContaining('Keranjang |').first);
        }
        await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').last);
        await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
        await Ketuk(tester, find.widgetWithText(FilledButton, 'Uang pas'));
        await Ketuk(tester, find.widgetWithText(FilledButton, 'Transaksi baru'));

        expect(Chip('Terlaris'), findsOneWidget);
        await Ketuk(tester, Chip('Terlaris'));
        expect(find.byType(UbinProduk), findsOneWidget);
        expect(Ubin('Americano Panas'), findsOneWidget);
        final tersimpan = await tester.runAsync(
          () => u.repositori.AmbilPengaturan(KunciPengaturan.kategoriTerakhirJual),
        );
        expect(tersimpan, 'Terlaris');
        expect(tester.takeException(), isNull);
        await Lepas(tester, u);
      },
    );
  }

  testWidgets('kategori terakhir dipulihkan saat layar Jual dibuka lagi; kategori yang hilang kembali ke Semua', (
    tester,
  ) async {
    final u = await Masuk(tester, const Size(1280, 900), kategoriTersimpan: UuidUji.kategoriMakanan);
    expect(tester.widget<ChoiceChip>(Chip('Makanan')).selected, isTrue);
    expect(Ubin('Americano Panas'), findsNothing);
    await Lepas(tester, u);

    final lain = await Masuk(tester, const Size(1280, 900), kategoriTersimpan: '01K5KATEGORIHILANG000000001');
    expect(tester.widget<ChoiceChip>(Chip('Semua')).selected, isTrue);
    await Lepas(tester, lain);
  });

  testWidgets('`?` di luar kolom isian membuka daftar pintasan; di kolom cari tetap jadi teks', (tester) async {
    final u = await Masuk(tester, const Size(1280, 900));
    await tester.sendKeyDownEvent(LogicalKeyboardKey.shiftLeft);
    await tester.sendKeyEvent(LogicalKeyboardKey.slash, character: '?');
    await tester.sendKeyUpEvent(LogicalKeyboardKey.shiftLeft);
    await Tunggu(tester);
    expect(find.text('Pintasan keyboard'), findsOneWidget);
    expect(find.text('Bayar tunai uang pas'), findsOneWidget);
    await Ketuk(tester, find.widgetWithText(TextButton, 'Tutup'));
    expect(find.text('Pintasan keyboard'), findsNothing);

    await tester.sendKeyEvent(LogicalKeyboardKey.f1);
    await tester.pump();
    await tester.sendKeyDownEvent(LogicalKeyboardKey.shiftLeft);
    await tester.sendKeyEvent(LogicalKeyboardKey.slash, character: '?');
    await tester.sendKeyUpEvent(LogicalKeyboardKey.shiftLeft);
    await Tunggu(tester);
    expect(find.text('Pintasan keyboard'), findsNothing);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });
}
