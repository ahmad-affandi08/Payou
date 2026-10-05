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

import '../Domain/Pelanggan/LayananSesi_test.dart' show KatalogSalonUji, creambath;
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-16d bagian 2 di aplikasi kasir (Ruang Kerja, 360/800/1280 dp): pelanggan terpilih memakai sesi paketnya dari panel
/// pelanggan (paket dibaca online, pemakaian ke outbox `Sesi.Pakai`).
void main() {
  const ani = PelangganTerpilih(uuid: '01K5PELANGGAN0000000000001', nama: 'Ani Rahmawati', noHpSamar: '0812****7890');

  Future<LingkunganUji> MasukJual(WidgetTester tester, Size ukuran) async {
    final u = LingkunganUji.Buat();
    final dataAwal = DataAwalUji();
    await tester.runAsync(() async {
      await u.SiapkanAktif(dataAwal: dataAwal);
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(dataAwal);
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogSalonUji());
      }
      if (p.url.path.endsWith('/pelanggan/${ani.uuid}/sesi')) {
        return JsonUji({
          'Pelanggan': {'Uuid': ani.uuid},
          'Berlaku': true,
          'Paket': [
            {
              'Uuid': '01K5SALDOSESI0000000000001',
              'NamaPaket': 'Paket Creambath Rambut Panjang 10x Sesi',
              'JumlahSesi': 10,
              'SisaSesi': 7,
              'BerlakuSampai': '2026-12-31',
              'NomorPenjualan': 'INV/SLB/260926/POS-001-0008',
              'SemuaProdukJasa': false,
              'ProdukBerlaku': [
                {'Uuid': creambath, 'Nama': 'Creambath Rambut Panjang Aroma Ginseng'},
              ],
            },
          ],
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

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('pakai sesi dari panel pelanggan: paket online, outbox Sesi.Pakai (${ukuran.width.toInt()} dp)', (
      tester,
    ) async {
      final u = await MasukJual(tester, ukuran);
      final wadah = ProviderScope.containerOf(tester.element(find.byType(RuangKerja)));
      wadah.read(penyediaKeranjang.notifier).Ganti(wadah.read(penyediaKeranjang).Salin(pelanggan: () => ani));
      await Tunggu(tester);

      await tester.sendKeyEvent(LogicalKeyboardKey.f2);
      await Tunggu(tester);
      await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Pakai sesi'));
      await Tunggu(tester, const Duration(milliseconds: 300));
      expect(find.text('Pakai sesi Ani Rahmawati'), findsOneWidget);
      expect(find.textContaining('Sisa 7 dari 10 sesi'), findsOneWidget);

      await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Creambath Rambut Panjang Aroma Ginseng'));
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Catat pemakaian'));
      expect(find.textContaining('1 sesi Creambath Rambut Panjang Aroma Ginseng tercatat'), findsOneWidget);

      final outbox = (await tester.runAsync(() => u.db.select(u.db.outbox).get()))!
          .where((o) => o.Jenis == 'Sesi.Pakai')
          .map((o) => jsonDecode(o.Data) as Map<String, Object?>)
          .toList();
      expect(outbox.single['UuidSaldoSesi'], '01K5SALDOSESI0000000000001');
      expect(outbox.single['Jumlah'], 1);
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
