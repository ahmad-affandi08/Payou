import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/Persediaan/LayarStok.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-05f bagian 2 di ruang kerja: menu Stok hanya untuk staf berizin `persediaan.terbuang.catat`; catat bahan terbuang
/// dari panel tugas (offline) → masuk outbox `BahanTerbuang.Catat` dan tampil di daftar hari ini dengan status kirim
/// berteks, di 360/800/1280 dp.
void main() {
  Future<LingkunganUji> Masuk(WidgetTester tester, Size ukuran, {String nama = 'Budi Santoso', int pin = 1}) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.shift.BukaShift(kasir: await u.Staf(nama), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      // Sinkron & lainnya: offline (catatan tetap di outbox).
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await PilihKasir(tester, nama);
    await tester.pump();
    await KetikPin(tester, KasusPin(pin)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);
    return u;
  }

  Future<void> Ketuk(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder);
    await Tunggu(tester, const Duration(milliseconds: 60));
    await tester.ensureVisible(finder);
    await tester.pump();
    await tester.tap(finder);
    await Tunggu(tester);
  }

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('catat bahan terbuang dari menu Stok, offline (${ukuran.width.toInt()} dp)', (tester) async {
      final u = await Masuk(tester, ukuran);

      await Ketuk(tester, find.text('Stok').last);
      // Di layar sempit bagian Bahan terbuang ada di bawah bagian Gudang: gulir dulu.
      final kosong = find.text('Belum ada bahan terbuang yang dicatat hari ini');
      await tester.scrollUntilVisible(
        kosong,
        200,
        scrollable: find.descendant(of: find.byType(LayarStok), matching: find.byType(Scrollable)).first,
      );
      expect(kosong, findsOneWidget);
      await Ketuk(tester, find.text('Catat bahan terbuang').last);

      // Cari lalu pilih roti; bahan baku & produk berpelacakan batch tidak tercampur.
      await tester.enterText(find.byKey(const ValueKey('CariProdukTerbuang')), 'roti');
      await Tunggu(tester);
      expect(find.text('Susu UHT 1 Liter'), findsNothing);
      await Ketuk(tester, find.text('Roti Tawar Gandum'));

      // Simpan tanpa jumlah → galat berteks di kolom jumlah.
      await Ketuk(tester, find.text('Catat terbuang'));
      expect(find.text('Isi jumlah yang terbuang.'), findsOneWidget);

      await tester.enterText(find.byKey(const ValueKey('JumlahTerbuang')), '1,5');
      await Ketuk(tester, find.text('Catat terbuang'));
      expect(find.text('Satuan Pcs harus bilangan bulat.'), findsOneWidget);

      await tester.enterText(find.byKey(const ValueKey('JumlahTerbuang')), '4');
      await Ketuk(tester, find.text('Catat terbuang'));
      expect(find.text('Pilih alasan terbuang.'), findsOneWidget);

      await Ketuk(tester, find.text('Kedaluwarsa / basi'));
      await Ketuk(tester, find.text('Catat terbuang'));
      await Tunggu(tester, const Duration(milliseconds: 400));

      // Panel tertutup; daftar hari ini menampilkan catatan dengan status berteks (offline = belum terkirim).
      await tester.scrollUntilVisible(
        find.text('Belum terkirim'),
        200,
        scrollable: find.descendant(of: find.byType(LayarStok), matching: find.byType(Scrollable)).first,
      );
      expect(find.text('Roti Tawar Gandum'), findsOneWidget);
      expect(find.text('4 Pcs'), findsOneWidget);
      expect(find.textContaining('Kedaluwarsa / basi | Budi Santoso'), findsOneWidget);
      expect(find.text('Belum terkirim'), findsOneWidget);

      final outbox = await tester.runAsync(() => u.db.select(u.db.outbox).get());
      final item = outbox!.singleWhere((o) => o.Jenis == 'BahanTerbuang.Catat');
      expect(jsonDecode(item.Data), containsPair('Jumlah', '4.0000'));
      expect(jsonDecode(item.Data), containsPair('Alasan', 'Kedaluwarsa'));
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  testWidgets('kasir tanpa izin tidak melihat menu Stok', (tester) async {
    final u = await Masuk(tester, const Size(1280, 900), nama: 'Rina Wulandari', pin: 0);
    expect(find.text('Stok'), findsNothing);
    expect(find.text('Riwayat'), findsWidgets);
    await Lepas(tester, u);
  });
}
