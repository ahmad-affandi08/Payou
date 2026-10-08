import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// Audit kesegaran data (P1): data awal (PIN & izin staf, batas diskon/retur/tempo, metode bayar, tarif pajak) tidak
/// boleh menunggu kasir menekan "Perbarui data kasir" atau mengunci layar. Selama kasir di layar Jual dengan keranjang
/// kosong dan perangkat online, data awal diunduh ulang tiap 10 menit.
void main() {
  testWidgets('layar Jual: data awal diunduh ulang berkala (sekali per 10 menit) saat online dan keranjang kosong', (
    tester,
  ) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      final jalur = p.url.path;
      if (jalur.endsWith('/konfigurasi-aplikasi')) {
        return JsonUji(<String, Object?>{});
      }
      if (jalur.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (jalur.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      if (jalur.endsWith('/produk-habis')) {
        return JsonUji({'Produk': <Object?>[]});
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: const Size(1280, 900));
    await Tunggu(tester, const Duration(milliseconds: 600));
    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester, const Duration(milliseconds: 600));
    expect(find.byType(RuangKerja), findsOneWidget);

    int JumlahDataAwal() => u.server.permintaan.where((p) => p.url.path.endsWith('/data-awal')).length;
    final awal = JumlahDataAwal();

    await tester.pump(const Duration(seconds: 61));
    await Tunggu(tester);
    expect(JumlahDataAwal(), awal + 1, reason: 'Tarikan berkala pertama terjadi pada putaran 60 detik.');

    await tester.pump(const Duration(seconds: 61));
    await Tunggu(tester);
    expect(JumlahDataAwal(), awal + 1, reason: 'Jeda 10 menit: putaran berikutnya tidak menarik lagi.');
    await Lepas(tester, u);
  });
}
