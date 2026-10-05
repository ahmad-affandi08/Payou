import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Domain/Penjualan/Keranjang.dart';
import 'package:kasir/Domain/Penjualan/LayananPenjualan.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// K-24: Riwayat bisa digeser ke tanggal sebelumnya (data lokal perangkat, offline; tidak ke masa depan) dan
/// "Ringkasan outlet" menampilkan ringkasan akhir hari semua perangkat dari server (360/1280 dp).
void main() {
  for (final ukuran in const [Size(1280, 900), Size(360, 740)]) {
    testWidgets('riwayat kemarin & ringkasan outlet (${ukuran.width.toInt()} dp)', (tester) async {
      final u = LingkunganUji.Buat();
      final hariIni = u.jam;
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.SiapkanKatalog();
        final rina = await u.Staf('Rina Wulandari');
        await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
        final katalog = await u.MuatKatalog();
        final k = await u.MuatKonteks();
        u.jam = hariIni.subtract(const Duration(days: 1));
        await u.penjualan.Bayar(
          keranjang: u.penjualan.TambahBaris(
            Keranjang.kosong,
            u.penjualan.BuatBaris(katalog, k, katalog.CariProduk(UuidUji.croissant)!),
            katalog,
            k,
          ),
          pembayaran: [
            PembayaranMasukan(
              metode: k.metodePembayaran.firstWhere((m) => m.Jenis == 'Tunai'),
              jumlah: Uang.DariBulat(50000),
            ),
          ],
          kasir: rina,
          k: k,
        );
        u.jam = hariIni;
      });
      String? tanggalDiminta;
      u.server.penangan = (p) async {
        if (p.url.path.endsWith('/data-awal')) {
          return JsonUji(DataAwalUji());
        }
        if (p.url.path.endsWith('/katalog')) {
          return JsonUji(KatalogUji());
        }
        if (p.url.path.endsWith('/ringkasan-harian')) {
          tanggalDiminta = p.url.queryParameters['tanggal'];
          return JsonUji({
            'Tanggal': tanggalDiminta,
            'JumlahTransaksi': 7,
            'JumlahVoid': 1,
            'JumlahRetur': 0,
            'Kotor': '1250000.00',
            'Diskon': '25000.00',
            'Retur': '0.00',
            'Bersih': '1225000.00',
            'Pajak': '111363.64',
            'PerMetodeBayar': [
              {'Jenis': 'Tunai', 'Nama': 'Tunai', 'Jumlah': '725000.00'},
              {'Jenis': 'QrisStatis', 'Nama': 'QRIS', 'Jumlah': '500000.00'},
            ],
            'PerKasir': [
              {'Nama': 'Rina Wulandari', 'JumlahTransaksi': 5, 'Bersih': '900000.00'},
              {'Nama': 'Budi Santoso', 'JumlahTransaksi': 2, 'Bersih': '325000.00'},
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

      await tester.tap(find.text('Riwayat').last);
      await Tunggu(tester);
      expect(find.text('24 Sep 2026 (hari ini)'), findsOneWidget);
      expect(find.text('Belum ada transaksi hari ini di perangkat ini.'), findsOneWidget);
      expect(tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.chevron_right)).onPressed, isNull);

      await tester.tap(find.byTooltip('Hari sebelumnya'));
      await Tunggu(tester, const Duration(milliseconds: 600));
      expect(find.text('23 Sep 2026'), findsOneWidget);
      expect(find.textContaining('1 transaksi |'), findsOneWidget);

      await tester.tap(find.text('Ringkasan outlet'));
      await Tunggu(tester, const Duration(milliseconds: 600));
      expect(tanggalDiminta, '2026-09-23');
      final ringkasan = find.byKey(const ValueKey('RingkasanOutlet'));
      expect(find.descendant(of: ringkasan, matching: find.text('Rp 1.225.000')), findsOneWidget);
      expect(find.descendant(of: ringkasan, matching: find.text('Budi Santoso | 2 transaksi')), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Tutup'));
      await Tunggu(tester);
      await Lepas(tester, u);
    });
  }
}
