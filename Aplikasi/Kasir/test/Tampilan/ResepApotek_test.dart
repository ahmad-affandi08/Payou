import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/Jual/DialogResep.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// Apotek bagian 2 di layar Jual & Bayar (360/800/1280 dp): lencana golongan obat berteks di ubin & keranjang; obat
/// keras/psikotropika di keranjang menahan metode bayar sampai resep dokter diisi (validasi isian) dan apoteker lolos
/// PIN (kasir Rina tidak berizin `apotek.obat-keras.jual`); muatan `Penjualan.Buat` membawa `Resep`, `UuidApoteker`,
/// dan `Baris[].DenganResep`. Tanpa apoteker di perangkat = diblokir dengan pesan jelas.
void main() {
  Future<LingkunganUji> MasukJual(WidgetTester tester, Size ukuran, {bool tanpaApoteker = false}) async {
    final u = LingkunganUji.Buat();
    final dataAwal = DataAwalApotekUji(tanpaApoteker: tanpaApoteker);
    await tester.runAsync(() async {
      await u.SiapkanAktif(dataAwal: dataAwal);
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(dataAwal);
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogBengkelApotekUji());
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

  Future<void> TambahProduk(WidgetTester tester, String kata, String nama) async {
    final cari = find.byType(TextField).first;
    await tester.tap(cari);
    await tester.enterText(cari, kata);
    await Tunggu(tester);
    await tester.tap(find.text(nama).first);
    await Tunggu(tester);
  }

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('resep dokter + PIN apoteker sebelum bayar obat keras & psikotropika (${ukuran.width.toInt()} dp)', (
      tester,
    ) async {
      final u = await MasukJual(tester, ukuran);
      await TambahProduk(tester, 'Amox', 'Amoxicillin Trihydrate 500 mg Kapsul Strip 10');
      expect(find.text('K'), findsWidgets, reason: 'Lencana obat keras berteks di ubin & keranjang.');
      await TambahProduk(tester, 'Diaz', 'Diazepam 2 mg Tablet Strip 10');
      await TambahProduk(tester, 'Para', 'Paracetamol 500 mg Tablet Strip 10');
      expect(find.text('Bebas'), findsWidgets);

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').first);
      expect(find.text('Penyerahan obat'), findsOneWidget);
      expect(find.byKey(const ValueKey('GerbangApotek')), findsOneWidget);
      expect(find.widgetWithText(ChoiceChip, 'Tunai'), findsNothing, reason: 'Belum bisa menerima uang.');
      expect(
        find.text('Wajib resep dokter: Amoxicillin Trihydrate 500 mg Kapsul Strip 10, Diazepam 2 mg Tablet Strip 10.'),
        findsOneWidget,
      );

      // Resep: isian kosong ditolak, alamat wajib karena ada psikotropika.
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Isi resep dokter'));
      expect(find.byType(DialogResep), findsOneWidget);
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan resep'));
      expect(find.text('Isi nomor resep.'), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('NomorResep')), 'RX/2026/09/0012');
      await tester.enterText(find.byKey(const ValueKey('NamaDokter')), 'Andi Wijaya');
      await tester.enterText(find.byKey(const ValueKey('NamaPasien')), 'Siti Rahmawati');
      await tester.enterText(find.byKey(const ValueKey('UmurPasien')), '42 tahun');
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan resep'));
      expect(find.text('Psikotropika/narkotika wajib mencatat alamat pasien.'), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('AlamatPasien')), 'Jl. Slamet Riyadi No. 45, Surakarta');
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan resep'));
      expect(find.byType(DialogResep), findsNothing);
      expect(find.text('Resep: no. RX/2026/09/0012, dr. Andi Wijaya'), findsOneWidget);
      expect(find.byKey(const ValueKey('GerbangApotek')), findsOneWidget, reason: 'Masih butuh apoteker.');

      // Apoteker: PIN apt. Dewi (tanpa persetujuan jarak jauh).
      await Ketuk(tester, find.widgetWithText(FilledButton, 'PIN apoteker'));
      expect(find.text('PIN apoteker'), findsWidgets);
      expect(find.text('Minta persetujuan jarak jauh'), findsNothing);
      await PilihPenyetuju(tester, 'apt. Dewi Anggraini');
      await KetikPin(tester, KasusPin(2)['Pin']! as String);
      await Tunggu(tester);
      expect(find.text('Diserahkan apoteker apt. Dewi Anggraini.'), findsOneWidget);
      expect(find.byKey(const ValueKey('GerbangApotek')), findsNothing);

      await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Uang pas'));
      expect(find.text('Pembayaran berhasil'), findsOneWidget);

      final jual = (await tester.runAsync(() => u.db.select(u.db.outbox).get()))!
          .where((o) => o.Jenis == 'Penjualan.Buat')
          .map((o) => jsonDecode(o.Data) as Map<String, Object?>)
          .single;
      expect(jual['Resep'], {
        'NomorResep': 'RX/2026/09/0012',
        'TanggalResep': '2026-09-24',
        'NamaDokter': 'Andi Wijaya',
        'NamaPasien': 'Siti Rahmawati',
        'UmurPasien': '42 tahun',
        'AlamatPasien': 'Jl. Slamet Riyadi No. 45, Surakarta',
      });
      expect(jual['UuidApoteker'], '01K6STAF0000000000APOTEK01');
      final baris = (jual['Baris']! as List<Object?>).cast<Map<String, Object?>>();
      expect(
        {for (final b in baris) b['UuidProduk']: b['DenganResep']},
        {amoxicillin: true, diazepam: true, paracetamol: null},
      );
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  testWidgets('tanpa apoteker berizin di perangkat: obat keras diblokir dengan pesan jelas (360 dp)', (tester) async {
    final u = await MasukJual(tester, const Size(360, 740), tanpaApoteker: true);
    await TambahProduk(tester, 'Mefenam', 'Asam Mefenamat 500 mg Tablet Strip 10');
    expect(find.text('K | OWA'), findsWidgets);
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').first);
    expect(find.textContaining('Wajib resep dokter'), findsNothing, reason: 'OWA tanpa resep.');
    expect(find.text('Hanya diserahkan apoteker: Asam Mefenamat 500 mg Tablet Strip 10.'), findsOneWidget);
    await Ketuk(tester, find.widgetWithText(FilledButton, 'PIN apoteker'));
    expect(find.textContaining('Belum ada apoteker berizin di perangkat ini.'), findsOneWidget);
    await Ketuk(tester, find.widgetWithText(TextButton, 'Batal'));
    expect(find.byKey(const ValueKey('GerbangApotek')), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Tunai'), findsNothing);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });
}
