import 'dart:convert';

import 'package:flutter/material.dart';
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

/// F-12 bagian 2 di aplikasi kasir: keranjang ber-pelanggan dijadikan pre-order dengan DP dari panel Bayar (offline),
/// lalu pre-order dicari online di Riwayat › Ambil pre-order, dimuat ke keranjang, dan DP menjadi pembayaran pertama.
void main() {
  const pelanggan = PelangganTerpilih(uuid: '01K5PELANGGAN0000000000001', nama: 'Ibu Ratna', noHpSamar: '0813****0077');

  Map<String, Object?> HasilCari() => {
    'Pesanan': [
      {
        'Uuid': '01K5PREORDER00000000000001',
        'Nomor': 'SO/SLB/260925/POS-001-0001',
        'Status': 'Siap',
        'TanggalAmbil': '2026-09-28',
        'Catatan': null,
        'TotalPesanan': '28000.00',
        'UangMuka': '10000.00',
        'SisaUangMuka': '10000.00',
        'Pelanggan': {'Uuid': pelanggan.uuid, 'Nama': 'Ibu Ratna', 'NoHp': '0813****0077'},
        'Baris': [
          {
            'Uuid': '01K5PREORDERBARIS000000001',
            'UuidProduk': UuidUji.americano,
            'UuidProdukSatuan': null,
            'NamaProduk': 'Americano Panas',
            'Jumlah': '2.0000',
            'HargaSatuan': '14000.00',
            'HargaPilihan': '0.00',
            'Pilihan': <Object?>[],
            'Catatan': null,
          },
        ],
      },
    ],
    'MetodeUangMuka': {'Uuid': '01K5METODEUANGMUKA00000001', 'Nama': 'Uang muka (DP)'},
  };

  Future<LingkunganUji> MasukJual(WidgetTester tester, Size ukuran) async {
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
      if (p.url.path.endsWith('/pesanan-penjualan')) {
        return JsonUji(HasilCari());
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

  ProviderContainer Wadah(WidgetTester tester) => ProviderScope.containerOf(tester.element(find.byType(RuangKerja)));

  Future<List<Map<String, Object?>>> Outbox(WidgetTester tester, LingkunganUji u, String jenis) async =>
      (await tester.runAsync(() => u.db.select(u.db.outbox).get()))!
          .where((o) => o.Jenis == jenis)
          .map((o) => jsonDecode(o.Data) as Map<String, Object?>)
          .toList();

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('buat pre-order dari panel Bayar: DP tunai tersimpan offline (${ukuran.width.toInt()} dp)', (
      tester,
    ) async {
      final u = await MasukJual(tester, ukuran);
      await Ketuk(tester, find.byWidgetPredicate((w) => w is UbinProduk && w.nama.startsWith('Americano')));
      final wadah = Wadah(tester);
      wadah.read(penyediaKeranjang.notifier).Ganti(wadah.read(penyediaKeranjang).Salin(pelanggan: () => pelanggan));
      await Tunggu(tester);

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').first);
      await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Jadikan pre-order (bayar DP)'));
      expect(find.textContaining('Pesanan atas nama Ibu Ratna'), findsOneWidget);
      await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
      await tester.enterText(find.widgetWithText(TextField, 'Uang muka'), '10000');
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan pre-order'));

      expect(find.text('Pre-order tersimpan'), findsWidgets);
      expect(find.textContaining('SO/SLB/'), findsOneWidget);
      final item = (await Outbox(tester, u, 'PesananPenjualan.Buat')).single;
      expect(item['UuidPelanggan'], pelanggan.uuid);
      expect((item['Pembayaran']! as List<Object?>).single, containsPair('Jumlah', '10000.00'));
      expect(Wadah(tester).read(penyediaKeranjang).CekKosong, isTrue);
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  for (final ukuran in const [Size(1280, 900), Size(800, 1000)]) {
    testWidgets(
      'ambil pre-order dari Riwayat: keranjang terisi, DP dipakai, sisa dibayar tunai (${ukuran.width.toInt()} dp)',
      (tester) async {
        final u = await MasukJual(tester, ukuran);
        await tester.tap(find.text('Riwayat').last);
        await Tunggu(tester);
        await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Ambil pre-order'));
        await tester.enterText(find.widgetWithText(TextField, 'Cari pre-order'), 'ratna');
        await Ketuk(tester, find.widgetWithText(FilledButton, 'Cari'));
        expect(find.text('SO/SLB/260925/POS-001-0001'), findsOneWidget);
        await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Ambil 0001'));

        expect(Wadah(tester).read(penyediaKeranjang).praPesan?.nomor, 'SO/SLB/260925/POS-001-0001');
        await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').first);
        expect(find.text('Mengambil pre-order SO/SLB/260925/POS-001-0001'), findsOneWidget);
        expect(find.text('Uang muka (DP)'), findsOneWidget);
        expect(find.widgetWithText(OutlinedButton, 'Jadikan pre-order (bayar DP)'), findsNothing);

        await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
        await Ketuk(tester, find.widgetWithText(FilledButton, 'Uang pas'));
        expect(find.text('Pembayaran berhasil'), findsOneWidget);

        final jual = (await Outbox(tester, u, 'Penjualan.Buat')).single;
        expect(jual['UuidPesananPenjualan'], '01K5PREORDER00000000000001');
        final bayar = (jual['Pembayaran']! as List<Object?>).cast<Map<String, Object?>>();
        expect(bayar.first['UuidMetodePembayaran'], '01K5METODEUANGMUKA00000001');
        expect(bayar.first['Jumlah'], '10000.00');
        expect(tester.takeException(), isNull);
        await Lepas(tester, u);
      },
    );
  }
}
