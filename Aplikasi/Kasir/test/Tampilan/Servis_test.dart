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

/// Bengkel bagian 2 di aplikasi kasir (360/800/1280 dp): Riwayat › Servis siap tagih (online; offline = pesan jelas +
/// muat ulang), rincian jasa dengan mekanik & sparepart, "Tagih ke keranjang" memuat baris + pelanggan, Bayar menampilkan
/// perintah kerja & nomor polisi, dan `Penjualan.Buat` membawa `UuidPerintahKerja` serta mekanik sebagai staf baris.
void main() {
  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('servis siap tagih dari Riwayat → keranjang → bayar (${ukuran.width.toInt()} dp)', (tester) async {
      final u = LingkunganUji.Buat();
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
      });
      var online = false;
      u.server.penangan = (p) async {
        if (p.url.path.endsWith('/data-awal')) {
          return JsonUji(DataAwalUji());
        }
        if (p.url.path.endsWith('/katalog')) {
          return JsonUji(KatalogBengkelApotekUji());
        }
        if (online && p.url.path.endsWith('/perintah-kerja')) {
          return JsonUji({
            'PerintahKerja': [PerintahKerjaUji()],
          });
        }
        if (online && p.url.path.endsWith('/perintah-kerja/01K6PK000000000000000000A1')) {
          return JsonUji({'PerintahKerja': PerintahKerjaUji()});
        }
        throw http.ClientException('offline');
      };
      await PasangAplikasi(tester, u, ukuran: ukuran);
      await Tunggu(tester, const Duration(milliseconds: 600));
      await tester.tap(find.text('Rina Wulandari'));
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
      await Ketuk(find.widgetWithText(OutlinedButton, 'Servis siap tagih'));
      expect(
        find.text('Melihat perintah kerja perlu koneksi internet. Coba lagi saat perangkat online.'),
        findsOneWidget,
        reason: 'Offline: pesan jelas, bukan layar kosong.',
      );

      online = true;
      await Ketuk(find.widgetWithText(OutlinedButton, 'Muat ulang'));
      expect(find.text('AD 1234 XY'), findsOneWidget);
      expect(find.text('Bambang Sutrisno | Selesai | 2 baris'), findsOneWidget);

      await Ketuk(find.text('AD 1234 XY'));
      expect(find.text('Jasa'), findsOneWidget);
      expect(find.text('Sparepart'), findsOneWidget);
      expect(find.text('1 × Rp 50.000 | Mekanik: Joko Prasetyo'), findsOneWidget);
      expect(find.text('1 × Rp 55.000 | diskon Rp 5.000'), findsOneWidget);
      expect(find.text('Keluhan: Rem belakang bunyi, tarikan berat'), findsOneWidget);
      await Ketuk(find.widgetWithText(FilledButton, 'Tagih ke keranjang'));

      final wadah = ProviderScope.containerOf(tester.element(find.byType(RuangKerja)));
      final keranjang = wadah.read(penyediaKeranjang);
      expect(keranjang.perintahKerja?.nomor, 'WO/SLB/2610/0007');
      expect(keranjang.pelanggan?.nama, 'Bambang Sutrisno');
      expect(keranjang.baris.first.staf, ['01K6KRY00000000000000MKN01']);
      expect(find.textContaining('Servis AD 1234 XY'), findsWidgets, reason: 'Judul keranjang = kendaraannya.');

      await Ketuk(find.widgetWithText(FilledButton, 'Bayar').first);
      expect(find.text('Menagih perintah kerja WO/SLB/2610/0007 | AD 1234 XY'), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Jadikan pre-order (bayar DP)'), findsNothing);
      await Ketuk(find.widgetWithText(ChoiceChip, 'Tunai'));
      await Ketuk(find.widgetWithText(FilledButton, 'Uang pas'));
      expect(find.text('Pembayaran berhasil'), findsOneWidget);

      final jual = (await tester.runAsync(() => u.db.select(u.db.outbox).get()))!
          .where((o) => o.Jenis == 'Penjualan.Buat')
          .map((o) => jsonDecode(o.Data) as Map<String, Object?>)
          .single;
      expect(jual['UuidPerintahKerja'], '01K6PK000000000000000000A1');
      expect(jual['UuidPelanggan'], '01K5PELANGGAN0000000000009');
      final baris = (jual['Baris']! as List<Object?>).cast<Map<String, Object?>>();
      expect(baris.first['Staf'], ['01K6KRY00000000000000MKN01']);
      expect(baris.last['HargaSatuan'], '55000.00');
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
