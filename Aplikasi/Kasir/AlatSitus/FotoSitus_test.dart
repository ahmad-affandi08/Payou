import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../test/Pendukung/KatalogUji.dart';
import '../test/Pendukung/LingkunganUji.dart';
import '../test/Pendukung/MuatFont.dart';
import '../test/Pendukung/PasangAplikasi.dart';

/// Penghasil foto produk untuk situs pemasaran `payou.id` (bukan test regresi; sengaja di luar `test/` agar tidak
/// ikut CI). Layar Jual asli aplikasi Kasir dirender dengan katalog contoh kafe, lalu disimpan sebagai PNG:
///
/// ```
/// cd Aplikasi/Kasir && flutter test AlatSitus/FotoSitus_test.dart --update-goldens
/// ```
///
/// Hasil di `AlatSitus/Hasil/` diubah ke WebP dan disalin ke `Aplikasi/Web/public/situs/produk/`.
void main() {
  const kategoriKopi = '01K5KAT0000000000000K0P101';
  const kategoriNonKopi = '01K5KAT00000000000N0NK0P11';
  const kategoriMakanan = '01K5KAT000000000000MAKAN01';

  Map<String, Object?> KatalogKafe() {
    final katalog = KatalogUji();
    final menu = [
      ('Es Kopi Susu Aren', kategoriKopi, '18000.00'),
      ('Americano Panas', kategoriKopi, '15000.00'),
      ('Kopi Tubruk Gayo', kategoriKopi, '12000.00'),
      ('Cappuccino', kategoriKopi, '24000.00'),
      ('Matcha Latte', kategoriNonKopi, '26000.00'),
      ('Es Teh Leci', kategoriNonKopi, '16000.00'),
      ('Cokelat Panas', kategoriNonKopi, '22000.00'),
      ('Croissant Cokelat', kategoriMakanan, '25000.00'),
      ('Roti Bakar Srikaya', kategoriMakanan, '20000.00'),
      ('Pisang Goreng Keju', kategoriMakanan, '18000.00'),
      ('Nasi Goreng Kampung', kategoriMakanan, '32000.00'),
      ('Mie Goreng Jawa', kategoriMakanan, '30000.00'),
    ];
    String Id(String awalan, int i) => '$awalan${i.toString().padLeft(26 - awalan.length, '0')}';
    katalog['Kategori'] = [
      {'Uuid': kategoriKopi, 'UuidInduk': null, 'Nama': 'Kopi', 'Urutan': 1},
      {'Uuid': kategoriNonKopi, 'UuidInduk': null, 'Nama': 'Non-kopi', 'Urutan': 2},
      {'Uuid': kategoriMakanan, 'UuidInduk': null, 'Nama': 'Makanan', 'Urutan': 3},
    ];
    katalog['Produk'] = [
      for (final (i, m) in menu.indexed)
        ProdukUji(Id('01K5PRDSITUS', i), m.$1, jenis: 'Resep', sku: 'MN-${i + 1}', kategori: m.$2),
    ];
    katalog['ProdukSatuan'] = [
      for (final (i, _) in menu.indexed)
        SatuanProdukUji(Id('01K5PSSITUS', i), Id('01K5PRDSITUS', i), UuidUji.satuanPcs),
    ];
    katalog['ProdukHarga'] = [
      for (final (i, m) in menu.indexed)
        HargaUji(Id('01K5HRGSITUS', i), Id('01K5PRDSITUS', i), Id('01K5PSSITUS', i), m.$3),
    ];
    katalog['ProdukBarcode'] = <Object?>[];
    katalog['ProdukKelompokPilihan'] = <Object?>[];

    return katalog;
  }

  testWidgets('foto layar Jual untuk situs (1280 × 800)', (tester) async {
    await MuatFontMerek();
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.repositori.SimpanPengaturan(KunciPengaturan.namaOutlet, 'Kopi Senja Solo Baru');
      await u.repositori.SimpanPengaturan(KunciPengaturan.kodePerangkat, 'POS-001');
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    var online = true;
    u.server.penangan = (p) async {
      final jalur = p.url.path;
      if (!online) {
        throw http.ClientException('offline');
      }
      if (jalur.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (jalur.endsWith('/katalog')) {
        return JsonUji(KatalogKafe());
      }
      if (jalur.endsWith('/produk-habis')) {
        return JsonUji({'Produk': <String>[]});
      }
      return http.Response(jsonEncode({}), 200, headers: {'content-type': 'application/json'});
    };
    await PasangAplikasi(tester, u, ukuran: const Size(1280, 800));
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    online = false;

    Finder Ubin(String nama) => find.byWidgetPredicate((w) => w is UbinProduk && w.nama == nama);
    for (final nama in ['Es Kopi Susu Aren', 'Es Kopi Susu Aren', 'Croissant Cokelat', 'Matcha Latte']) {
      await tester.tap(Ubin(nama));
      await Tunggu(tester);
    }
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('Hasil/KasirJual.png'));
    await Lepas(tester, u);
  });
}
