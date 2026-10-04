import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../test/Pendukung/LingkunganUji.dart';
import '../test/Pendukung/MuatFont.dart';
import '../test/Pendukung/PasangAplikasi.dart';
import 'DataDemoKafe.dart';

/// Penghasil foto produk untuk situs pemasaran `payou.id` (bukan test regresi; sengaja di luar `test/` agar tidak
/// ikut CI). Layar Jual asli aplikasi Kasir dirender dengan katalog contoh kafe, lalu disimpan sebagai PNG:
///
/// ```
/// cd Aplikasi/Kasir && flutter test AlatSitus/FotoSitus_test.dart --update-goldens
/// ```
///
/// Hasil di `AlatSitus/Hasil/` diubah ke WebP dan disalin ke `Aplikasi/Web/public/situs/produk/`.
void main() {
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
