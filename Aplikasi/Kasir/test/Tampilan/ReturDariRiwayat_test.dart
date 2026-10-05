import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/Penjualan/LembarRetur.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';
import '../Pendukung/StrukUji.dart';

/// Audit kemudahan pakai #10: retur/tukar dibuka langsung dari baris Riwayat (nomor struk terisi & langsung dicari),
/// Riwayat bisa dicari dengan sebagian nomor atau nominal, dan struk terakhir bisa dicetak ulang dari layar Jual.
void main() {
  Future<({LingkunganUji u, List<Uri> cari})> Masuk(WidgetTester tester, Size ukuran) async {
    final u = LingkunganUji.Buat();
    final dataAwal = DataAwalUji(tukar: true);
    final cari = <Uri>[];
    await tester.runAsync(() async {
      await u.SiapkanAktif(dataAwal: dataAwal);
      await u.SiapkanKatalog();
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/penjualan/cari')) {
        cari.add(p.url);
        return JsonUji(StrukUji());
      }
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(dataAwal);
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);
    return (u: u, cari: cari);
  }

  Future<void> Ketuk(WidgetTester tester, Finder f) async {
    await tester.ensureVisible(f);
    await Tunggu(tester, const Duration(milliseconds: 60));
    await tester.tap(f);
    await Tunggu(tester);
  }

  Future<String> Jual(WidgetTester tester, LingkunganUji u, String produk) async {
    await Ketuk(tester, find.byWidgetPredicate((w) => w is UbinProduk && w.nama == produk));
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').last);
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Uang pas'));
    expect(find.text('Pembayaran berhasil'), findsOneWidget);
    final penjualan = await tester.runAsync(() => u.db.select(u.db.penjualan).get());
    final terbaru = penjualan!.reduce((a, b) => a.DibuatPada.isAfter(b.DibuatPada) ? a : b);
    await Ketuk(tester, find.byTooltip('Transaksi baru'));
    return terbaru.Nomor;
  }

  for (final ukuran in const [Size(1280, 900), Size(360, 740)]) {
    testWidgets(
      'Riwayat: cari nomor/nominal lalu Retur / tukar membuka lembar retur yang sudah mencari (${ukuran.width.toInt()} dp)',
      (tester) async {
        final (:u, :cari) = await Masuk(tester, ukuran);
        final nomorAmericano = await Jual(tester, u, 'Americano Panas');
        final nomorCroissant = await Jual(tester, u, 'Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo');

        await tester.tap(find.text('Riwayat').last);
        await Tunggu(tester);
        expect(find.text(nomorAmericano), findsOneWidget);
        expect(find.text(nomorCroissant), findsOneWidget);

        // Sebagian nomor struk (empat angka terakhir) menyaring baris.
        final akhiran = nomorCroissant.substring(nomorCroissant.length - 4);
        await tester.enterText(find.widgetWithText(TextField, 'Cari nomor struk atau nominal'), akhiran);
        await Tunggu(tester);
        expect(find.text(nomorCroissant), findsOneWidget);
        expect(find.text(nomorAmericano), findsNothing);

        // Nominal tanpa titik ribuan: Americano Rp 16.500 (15.000 + PBJT 10%).
        await tester.enterText(find.widgetWithText(TextField, 'Cari nomor struk atau nominal'), '16500');
        await Tunggu(tester);
        expect(find.text(nomorAmericano), findsOneWidget);
        expect(find.text(nomorCroissant), findsNothing);

        await tester.enterText(find.widgetWithText(TextField, 'Cari nomor struk atau nominal'), 'ZZZ');
        await Tunggu(tester);
        expect(find.textContaining('Tidak ada transaksi yang cocok'), findsOneWidget);
        await Ketuk(tester, find.byTooltip('Hapus pencarian'));
        expect(find.text(nomorCroissant), findsOneWidget);

        await Ketuk(tester, find.text(nomorAmericano));
        await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Retur / tukar'));
        expect(find.byType(LembarRetur), findsOneWidget);
        expect(cari, hasLength(1), reason: 'Struk langsung dicari tanpa mengetik nomor.');
        expect(cari.single.queryParameters['nomor'], nomorAmericano);
        expect(find.byKey(const ValueKey('JumlahRetur-${UuidStruk.kopiLiter}')), findsOneWidget);
        expect(tester.takeException(), isNull);
        await Lepas(tester, u);
      },
    );
  }

  testWidgets('Jual: cetak ulang struk terakhir tanpa membuka Riwayat', (tester) async {
    final (:u, cari: _) = await Masuk(tester, const Size(1280, 900));
    await Ketuk(tester, find.byKey(const ValueKey('TombolCetakUlangTerakhir')));
    expect(find.text('Belum ada transaksi di perangkat ini.'), findsOneWidget);

    await Jual(tester, u, 'Americano Panas');
    await Ketuk(tester, find.byKey(const ValueKey('TombolCetakUlangTerakhir')));
    expect(
      find.textContaining('Printer belum diatur'),
      findsWidgets,
      reason: 'Tanpa printer: pesan jelas, bukan diam.',
    );
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });
}
