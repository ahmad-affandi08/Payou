import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// F-16c di layar Jual: promo diunduh bersama katalog lalu diterapkan otomatis; potongan tampil di baris keranjang.
void main() {
  Future<LingkunganUji> MasukJual(
    WidgetTester tester,
    Size ukuran, {
    Map<String, Object?>? katalog,
    Map<String, Object?>? hasilCari,
    Map<String, Object?>? saldoPoin,
    Map<String, Object?>? promo,
  }) async {
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
        return JsonUji(katalog ?? KatalogUji());
      }
      if (promo != null && p.url.path.endsWith('/promo')) {
        return JsonUji(promo);
      }
      if (saldoPoin != null && p.url.path.endsWith('/poin')) {
        return JsonUji(saldoPoin);
      }
      if (hasilCari != null && p.url.path.endsWith('/pelanggan')) {
        return JsonUji(hasilCari);
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
    await tester.pump();
    await tester.tap(finder);
    await Tunggu(tester);
  }

  testWidgets('F-16c: bundel 2 americano Rp 25.000 otomatis → rincian promo & total di keranjang (1280 dp)', (
    tester,
  ) async {
    final u = await MasukJual(
      tester,
      const Size(1280, 900),
      promo: {
        'ModeResolusi': 'Terbaik',
        'Promo': [
          {
            'Uuid': '01K5PROMO00000000000000001',
            'Kode': 'KOPI2',
            'Nama': '2 kopi Rp 25.000',
            'Prioritas': 10,
            'Eksklusif': false,
            'Definisi': {
              'Kondisi': {
                'Jenis': 'Kategori',
                'Uuid': [UuidUji.kategoriKopi],
                'JumlahMinimal': '2',
              },
              'Aksi': {'Jenis': 'BundelHargaTetap', 'Harga': '25000'},
            },
          },
        ],
      },
    );
    final americano = find.byWidgetPredicate((w) => w is UbinProduk && w.nama.startsWith('Americano'));
    await Ketuk(tester, americano);
    expect(find.textContaining('Promo 2 kopi'), findsNothing);
    await Ketuk(tester, americano);
    expect(find.textContaining('Promo 2 kopi Rp 25.000 −Rp 5.000'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });
}
