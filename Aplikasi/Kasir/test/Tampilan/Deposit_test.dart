import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Aplikasi/Penyedia.dart';
import 'package:kasir/Domain/Penjualan/Keranjang.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-16d bagian 1 di aplikasi kasir (Ruang Kerja, 360/800/1280 dp): isi deposit dari panel pelanggan (offline,
/// outbox `Deposit.Isi`, bukti isi) lalu bayar dengan deposit di panel Bayar (saldo dibaca online, tidak melebihi saldo).
void main() {
  const ani = PelangganTerpilih(uuid: '01K5PELANGGAN0000000000001', nama: 'Ani Rahmawati', noHpSamar: '0812****7890');

  Future<LingkunganUji> MasukJual(WidgetTester tester, Size ukuran, {required String saldo}) async {
    final u = LingkunganUji.Buat();
    final dataAwal = DataAwalUji(deposit: true);
    await tester.runAsync(() async {
      await u.SiapkanAktif(dataAwal: dataAwal);
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(dataAwal);
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      if (p.url.path.endsWith('/pelanggan/${ani.uuid}/deposit')) {
        return JsonUji({
          'Pelanggan': {'Uuid': ani.uuid, 'SaldoDeposit': saldo},
          'Berlaku': true,
        });
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

  Finder TombolUtamaBayar() => find.ancestor(
    of: find.textContaining(RegExp(r'^(Selesaikan pembayaran|Tambah pembayaran)')),
    matching: find.byType(FilledButton),
  );

  ProviderContainer Wadah(WidgetTester tester) => ProviderScope.containerOf(tester.element(find.byType(RuangKerja)));

  void PasangAni(WidgetTester tester) {
    final wadah = Wadah(tester);
    wadah.read(penyediaKeranjang.notifier).Ganti(wadah.read(penyediaKeranjang).Salin(pelanggan: () => ani));
  }

  Future<List<Map<String, Object?>>> Outbox(WidgetTester tester, LingkunganUji u, String jenis) async =>
      (await tester.runAsync(() => u.db.select(u.db.outbox).get()))!
          .where((o) => o.Jenis == jenis)
          .map((o) => jsonDecode(o.Data) as Map<String, Object?>)
          .toList();

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets(
      'isi deposit tunai dari panel pelanggan: saldo tampil, outbox Deposit.Isi (${ukuran.width.toInt()} dp)',
      (tester) async {
        final u = await MasukJual(tester, ukuran, saldo: '20000.00');
        await Ketuk(tester, find.byWidgetPredicate((w) => w is UbinProduk && w.nama.startsWith('Americano')));
        PasangAni(tester);
        await Tunggu(tester);

        // Panel pelanggan lewat F2 (di 360 dp keranjang ada di lembar bawah).
        await tester.sendKeyEvent(LogicalKeyboardKey.f2);
        await Tunggu(tester);
        await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Isi deposit'));
        await Tunggu(tester, const Duration(milliseconds: 300));
        expect(find.text('Isi deposit Ani Rahmawati'), findsOneWidget);
        expect(find.text('Rp 20.000'), findsWidgets);

        await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
        await Ketuk(tester, find.widgetWithText(ActionChip, 'Rp 100.000'));
        await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan isi deposit'));

        expect(find.text('Deposit Ani Rahmawati tersimpan'), findsOneWidget);
        expect(find.textContaining('DEP/SLB/'), findsOneWidget);
        expect(find.text('Rp 120.000'), findsWidgets, reason: 'Saldo sesudah = 20.000 + 100.000.');
        final item = (await Outbox(tester, u, 'Deposit.Isi')).single;
        expect(item['UuidPelanggan'], ani.uuid);
        expect(item['Jumlah'], '100000.00');
        expect(Wadah(tester).read(penyediaKeranjang).CekKosong, isFalse, reason: 'Keranjang tidak ikut dikosongkan.');
        expect(tester.takeException(), isNull);
        await Lepas(tester, u);
      },
    );
  }

  testWidgets('bayar dengan deposit: chip hanya dengan pelanggan, saldo online, melebihi saldo ditolak (1280 dp)', (
    tester,
  ) async {
    final u = await MasukJual(tester, const Size(1280, 900), saldo: '20000.00');
    await Ketuk(tester, find.byWidgetPredicate((w) => w is UbinProduk && w.nama.startsWith('Americano')));
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').first);
    expect(find.widgetWithText(ChoiceChip, 'Deposit pelanggan'), findsNothing, reason: 'Tanpa pelanggan.');

    PasangAni(tester);
    await Tunggu(tester);
    await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Deposit pelanggan'));
    await Tunggu(tester, const Duration(milliseconds: 300));
    expect(find.text('Saldo deposit'), findsOneWidget);
    expect(find.text('Rp 20.000'), findsWidgets);

    // Total belanja lebih dari saldo: jumlah bawaan = saldo, sisanya dibayar tunai.
    await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '25000');
    await tester.pump();
    await Ketuk(tester, TombolUtamaBayar());
    expect(find.textContaining('Saldo deposit tinggal Rp 20.000'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '10000');
    await tester.pump();
    await Ketuk(tester, TombolUtamaBayar());
    await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
    await Ketuk(tester, find.text('Uang pas'));
    expect(find.text('Pembayaran berhasil'), findsOneWidget);

    final jual = (await Outbox(tester, u, 'Penjualan.Buat')).single;
    final pembayaran = (jual['Pembayaran']! as List<Object?>).cast<Map<String, Object?>>();
    expect(pembayaran.first, containsPair('UuidMetodePembayaran', '01K5MTD0000000000000000009'));
    expect(pembayaran.first, containsPair('Jumlah', '10000.00'));
    expect(jual['UuidPelanggan'], ani.uuid);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });
}
