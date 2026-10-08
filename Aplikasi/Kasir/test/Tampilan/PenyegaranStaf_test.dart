import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:kasir/Tampilan/GerbangKasir.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// BR-06.3: PIN & izin staf diverifikasi dari data lokal, jadi perubahan di back-office (PIN diganti, staf baru atau
/// dicabut) harus sampai ke perangkat tanpa kasir menekan "Perbarui data". Pemeriksaan berkala menyegarkan daftar staf
/// selama aplikasi menunggu PIN (layar pilih kasir), supaya PIN lama tidak diterima berhari-hari.
void main() {
  testWidgets('layar pilih kasir: pemeriksaan berkala menyegarkan daftar staf dari server', (tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    var serverBerubah = false;
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        // Setelah perubahan di back-office, server menyertakan staf baru.
        return JsonUji(serverBerubah ? DataAwalApotekUji() : DataAwalUji());
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u);
    await Tunggu(tester, const Duration(milliseconds: 600));

    await tester.tap(find.byKey(const ValueKey('PilihKasir')));
    await tester.pumpAndSettle();
    expect(find.text('apt. Dewi Anggraini'), findsNothing);
    await tester.tapAt(const Offset(1, 1));
    await tester.pumpAndSettle();

    serverBerubah = true;
    await tester.pump(GerbangKasir.selangPeriksaPerangkat);
    await Tunggu(tester);

    await tester.tap(find.byKey(const ValueKey('PilihKasir')));
    await tester.pumpAndSettle();
    expect(find.text('apt. Dewi Anggraini'), findsWidgets, reason: 'Staf baru dari back-office tampil tanpa tombol Perbarui.');
    await Lepas(tester, u);
  });
}
