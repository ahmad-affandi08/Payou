import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Aplikasi/Penyedia.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-07 mode service bagian 2 di aplikasi kasir (360/800/1280 dp): Riwayat › Reservasi hari ini menampilkan antrian
/// (jam outlet), "Layani" mencatat kedatangan lalu memuat layanan + staf ke keranjang, dan pembayaran membawa
/// `UuidReservasi` di `Penjualan.Buat`.
void main() {
  Map<String, Object?> Baris(String status) => {
    'Uuid': '01K5RESERVASI0000000000001',
    'Nomor': 'RS/2026/09/0001',
    'MulaiPada': '2026-09-26T03:00:00Z',
    'SelesaiPada': '2026-09-26T04:00:00Z',
    'NamaPelanggan': 'Ratna Sari',
    'NoHp': '0813-5555-0077',
    'Pelanggan': null,
    'UuidProduk': UuidUji.americano,
    'NamaLayanan': 'Americano Panas',
    'UuidStaf': '01K5KARYAWAN00000000000001',
    'NamaStaf': 'Maya',
    'Status': status,
    'LabelStatus': status,
    'Catatan': 'Rambut panjang',
  };

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('layani reservasi dari Riwayat lalu bayar (${ukuran.width.toInt()} dp)', (tester) async {
      final u = LingkunganUji.Buat();
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
      });
      u.server.penangan = (p) async {
        if (p.url.path.endsWith('/data-awal')) {
          return JsonUji(DataAwalUji());
        }
        if (p.url.path.endsWith('/katalog')) {
          return JsonUji(KatalogUji());
        }
        if (p.url.path.endsWith('/reservasi')) {
          return JsonUji({
            'Reservasi': [Baris('Dikonfirmasi')],
          });
        }
        if (p.url.path.endsWith('/hadir')) {
          return JsonUji({'Reservasi': Baris('Hadir')});
        }
        throw http.ClientException('offline');
      };
      await PasangAplikasi(tester, u, ukuran: ukuran);
      await Tunggu(tester, const Duration(milliseconds: 600));
      await PilihKasir(tester, 'Rina Wulandari');
      await tester.pump();
      await KetikPin(tester, KasusPin(0)['Pin']! as String);
      await Tunggu(tester);

      Future<void> Ketuk(Finder finder) async {
        await tester.ensureVisible(finder);
        await Tunggu(tester, const Duration(milliseconds: 60));
        await tester.ensureVisible(finder);
        await tester.pump();
        await tester.tap(finder);
        await Tunggu(tester);
      }

      await tester.tap(find.text('Riwayat').last);
      await Tunggu(tester);
      await Ketuk(find.widgetWithText(OutlinedButton, 'Reservasi hari ini'));
      expect(find.text('10.00–11.00'), findsOneWidget, reason: 'Jam menurut zona outlet (WIB).');
      expect(find.text('Americano Panas | oleh Maya'), findsOneWidget);
      await Ketuk(find.widgetWithText(FilledButton, 'Layani Ratna'));

      final wadah = ProviderScope.containerOf(tester.element(find.byType(RuangKerja)));
      final keranjang = wadah.read(penyediaKeranjang);
      expect(keranjang.reservasi?.nomor, 'RS/2026/09/0001');
      expect(keranjang.baris.single.staf, ['01K5KARYAWAN00000000000001']);

      await Ketuk(find.widgetWithText(FilledButton, 'Bayar').first);
      expect(find.text('Melayani reservasi RS/2026/09/0001'), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Jadikan pre-order (bayar DP)'), findsNothing);
      await Ketuk(find.widgetWithText(ChoiceChip, 'Tunai'));
      await Ketuk(find.widgetWithText(FilledButton, 'Uang pas'));
      expect(find.text('Pembayaran berhasil'), findsOneWidget);

      final jual = (await tester.runAsync(() => u.db.select(u.db.outbox).get()))!
          .where((o) => o.Jenis == 'Penjualan.Buat')
          .map((o) => jsonDecode(o.Data) as Map<String, Object?>)
          .single;
      expect(jual['UuidReservasi'], '01K5RESERVASI0000000000001');
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
