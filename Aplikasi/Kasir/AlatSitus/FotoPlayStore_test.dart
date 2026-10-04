import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../test/Pendukung/LingkunganUji.dart';
import '../test/Pendukung/MuatFont.dart';
import '../test/Pendukung/PasangAplikasi.dart';
import 'DataDemoKafe.dart';

/// Penghasil tangkapan layar Play Store aplikasi Kasir (bukan test regresi; di luar `test/` agar tidak ikut CI).
/// Layar asli dengan data contoh satu pagi di kafe (delapan transaksi tunai & QRIS, satu kas keluar), dipotret
/// langsung dari layer dengan rasio piksel tinggi supaya teks tetap tajam:
///
/// ```
/// cd Aplikasi/Kasir && flutter test AlatSitus/FotoPlayStore_test.dart
/// ```
///
/// Hasil mentah di `AlatSitus/Hasil/PlayStore/`, lalu disusun oleh `Spesifikasi/Merek/PlayStore/BuatAsetPlayStore.py`.
void main() {
  Future<void> SimpanFoto(WidgetTester tester, String nama, double skala) async {
    debugPrint('foto $nama');
    await tester.pump();
    var objek = tester.renderObject(find.byType(MaterialApp));
    while (!objek.isRepaintBoundary) {
      objek = objek.parent!;
    }
    final layer = objek.debugLayer! as OffsetLayer;
    final batas = objek.paintBounds;
    await tester.runAsync(() async {
      final gambar = await layer.toImage(batas, pixelRatio: skala);
      final data = await gambar.toByteData(format: ui.ImageByteFormat.png);
      final berkas = File('AlatSitus/Hasil/PlayStore/$nama.png');
      await berkas.parent.create(recursive: true);
      await berkas.writeAsBytes(data!.buffer.asUint8List());
    });
  }

  Future<void> UbahUkuran(WidgetTester tester, Size ukuran) async {
    tester.view.physicalSize = ukuran;
    await tester.binding.setSurfaceSize(ukuran);
    await Tunggu(tester, const Duration(milliseconds: 400));
  }

  testWidgets('tangkapan layar Play Store aplikasi Kasir', (tester) async {
    await MuatFontMerek();
    final u = LingkunganUji.Buat()..jam = DateTime.utc(2026, 9, 24, 7);
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.repositori.SimpanPengaturan(KunciPengaturan.namaOutlet, 'Kopi Senja Solo Baru');
      await u.repositori.SimpanPengaturan(KunciPengaturan.kodePerangkat, 'POS-001');
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      final jalur = p.url.path;
      if (jalur.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (jalur.endsWith('/katalog')) {
        return JsonUji(KatalogKafe());
      }
      if (jalur.endsWith('/produk-habis')) {
        return JsonUji({'Produk': <String>[]});
      }
      if (jalur.endsWith('/sinkron/kirim')) {
        final item = ((jsonDecode(p.body) as Map<String, Object?>)['Item']! as List<Object?>)
            .cast<Map<String, Object?>>();
        return JsonUji({
          'Hasil': [
            for (final i in item) {'Uuid': i['Uuid'], 'Jenis': i['Jenis'], 'Status': 'Diterima'},
          ],
          'WaktuServer': u.jam.toUtc().toIso8601String(),
          'PerangkatDicabut': false,
          'PerluTinjauan': <String>[],
        });
      }
      return http.Response(jsonEncode({}), 200, headers: {'content-type': 'application/json'});
    };
    await PasangAplikasi(tester, u, ukuran: const Size(1280, 800));
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);

    Future<void> Ketuk(Finder f) async {
      await tester.ensureVisible(f.first);
      await tester.pump();
      await tester.tap(f.first);
      await Tunggu(tester);
    }

    Finder Ubin(String nama) => find.byWidgetPredicate((w) => w is UbinProduk && w.nama == nama);

    Future<void> Jual(List<String> menu, {bool qris = false}) async {
      for (final nama in menu) {
        await Ketuk(Ubin(nama));
      }
      await tester.sendKeyEvent(LogicalKeyboardKey.f8);
      await Tunggu(tester);
      if (qris) {
        await Ketuk(find.widgetWithText(ChoiceChip, 'QRIS'));
        await Ketuk(find.widgetWithText(FilledButton, 'Selesaikan pembayaran'));
        await Ketuk(find.byType(CheckboxListTile));
        await Ketuk(find.widgetWithText(FilledButton, 'Selesaikan pembayaran'));
      } else {
        await Ketuk(find.widgetWithText(FilledButton, 'Uang pas'));
      }
      await Ketuk(find.widgetWithText(FilledButton, 'Transaksi baru'));
    }

    final pagi = <(int, int, List<String>, bool)>[
      (7, 12, ['Es Kopi Susu Aren', 'Croissant Cokelat'], false),
      (7, 41, ['Americano Panas', 'Roti Bakar Srikaya'], true),
      (8, 5, ['Es Kopi Susu Aren', 'Es Kopi Susu Aren', 'Pisang Goreng Keju'], false),
      (8, 33, ['Matcha Latte', 'Croissant Cokelat'], true),
      (9, 14, ['Cappuccino', 'Es Kopi Susu Aren'], false),
      (9, 48, ['Nasi Goreng Kampung', 'Es Teh Leci', 'Mie Goreng Jawa', 'Es Teh Leci'], true),
      (10, 20, ['Es Kopi Susu Aren', 'Kopi Tubruk Gayo'], false),
      (10, 52, ['Cokelat Panas', 'Croissant Cokelat', 'Es Kopi Susu Aren'], true),
    ];
    for (final (jam, menit, menu, qris) in pagi) {
      debugPrint('jual $jam.$menit');
      u.jam = DateTime.utc(2026, 9, 24, jam, menit);
      await Jual(menu, qris: qris);
    }
    u.jam = DateTime.utc(2026, 9, 24, 10, 58);
    await Ketuk(find.text('Kas'));
    await Ketuk(find.widgetWithText(OutlinedButton, 'Kas keluar'));
    await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '35000');
    await Ketuk(find.text('Simpan kas keluar'));
    await Ketuk(find.text('Jual'));
    u.jam = DateTime.utc(2026, 9, 24, 11, 6);
    await Tunggu(tester, const Duration(seconds: 2));

    // Tablet 10 inci (1280 × 800 dp, 2× → 2560 × 1600 px).
    const skalaTablet = 2.0;
    for (final nama in ['Es Kopi Susu Aren', 'Es Kopi Susu Aren', 'Croissant Cokelat', 'Matcha Latte']) {
      await Ketuk(Ubin(nama));
    }
    // Katalog digulir kembali ke atas supaya baris pertama tidak terpotong.
    await tester.drag(
      find.descendant(of: find.byType(GridView), matching: find.byType(Scrollable)).first,
      const Offset(0, 2000),
    );
    await Tunggu(tester);
    await SimpanFoto(tester, 'Tablet1Jual', skalaTablet);
    await tester.sendKeyEvent(LogicalKeyboardKey.f8);
    await Tunggu(tester);
    await SimpanFoto(tester, 'Tablet2Bayar', skalaTablet);
    await Ketuk(find.widgetWithText(FilledButton, 'Uang pas'));
    await SimpanFoto(tester, 'Tablet3Berhasil', skalaTablet);
    await Ketuk(find.widgetWithText(FilledButton, 'Transaksi baru'));
    for (final (label, nama) in [('Shift', 'Tablet4Shift'), ('Riwayat', 'Tablet5Riwayat'), ('Kas', 'Tablet6Kas')]) {
      await Ketuk(find.text(label));
      await Tunggu(tester, const Duration(milliseconds: 400));
      await SimpanFoto(tester, nama, skalaTablet);
    }

    // HP (400 × 800 dp, 2,7× → 1080 × 2160 px).
    await UbahUkuran(tester, const Size(400, 800));
    await Ketuk(find.text('Jual').last);
    await tester.drag(
      find.descendant(of: find.byType(GridView), matching: find.byType(Scrollable)).first,
      const Offset(0, 2000),
    );
    await Tunggu(tester);
    for (final nama in ['Es Kopi Susu Aren', 'Croissant Cokelat']) {
      await Ketuk(Ubin(nama));
    }
    await tester.drag(
      find.descendant(of: find.byType(GridView), matching: find.byType(Scrollable)).first,
      const Offset(0, 2000),
    );
    await Tunggu(tester);
    await SimpanFoto(tester, 'Hp1Jual', 2.7);
    await Ketuk(find.text('Shift').last);
    await SimpanFoto(tester, 'Hp2Shift', 2.7);
    await Ketuk(find.text('Riwayat').last);
    await SimpanFoto(tester, 'Hp3Riwayat', 2.7);
    await Lepas(tester, u);
  }, timeout: const Timeout(Duration(minutes: 20)));
}
