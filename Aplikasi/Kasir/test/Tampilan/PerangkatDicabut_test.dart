import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/GerbangKasir.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// BR-02.3: perangkat yang dicabut dari back-office tidak boleh bisa dipakai lagi.
///
/// Dua celah yang ditutup di sini (laporan pemilik produk: "perangkat sudah dicabut, tapi masih bisa login"):
/// 1. PIN diverifikasi lokal supaya kasir bisa masuk tanpa internet (BR-06.3), jadi masuk tidak pernah menyentuh
///    server — perangkat dicabut baru ketahuan saat aplikasi dibuka ulang.
/// 2. Pemanggil selain sinkron & data awal (katalog, promo, data meja, konfigurasi) sengaja menelan `GalatApi`
///    agar galat server tidak mengganggu kasir, termasuk 403 `PerangkatDicabut`.
void main() {
  /// Jawaban server untuk perangkat yang sudah dicabut (`AutentikasiPerangkat`, 403).
  Future<http.Response> Dicabut(http.Request _) async => JsonUji({
    'Galat': {'Kode': 'PerangkatDicabut', 'Pesan': 'Perangkat ini sudah dicabut dari back-office.'},
  }, 403);

  testWidgets('dicabut lalu online: masuk kasir diblokir di papan PIN, bukan masuk dulu baru dikeluarkan', (
    tester,
  ) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    // Perangkat dicabut saat aplikasi sedang terbuka dan sedang offline, jadi pemeriksaan awal tidak menemukannya.
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u);
    expect(find.text('Siapa yang bertugas?'), findsOneWidget);

    u.server.penangan = Dicabut;
    u.server.permintaan.clear();
    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);

    // Keputusan pemilik produk (v2.64): masuk diperiksa ke server dulu. Endpointnya `konfigurasi-aplikasi` karena
    // bertoken, murah, dan di luar penjaga langganan — yang diuji keabsahan perangkat, bukan status langganan.
    // Tidak ada permintaan data awal/katalog: itu baru terjadi kalau sesi sempat terbuka.
    expect(u.server.permintaan.map((p) => p.url.path).toSet(), {'/api/pos/v1/konfigurasi-aplikasi'});
    expect(find.byType(RuangKerja), findsNothing);
    expect(find.text('Aktifkan perangkat kasir'), findsOneWidget);
    expect(find.textContaining('sudah dicabut dari back-office'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('offline tetap boleh masuk: pemblokiran hanya saat server menjawab (BR-06.3)', (tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u);

    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);

    expect(find.byType(RuangKerja), findsOneWidget, reason: 'Kasir harus tetap bisa bekerja tanpa internet.');
    expect(find.text('Offline'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('dicabut saat shift berjalan: perbarui katalog menutup sesi, bukan hanya gagal diam-diam', (
    tester,
  ) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u);
    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);

    // Perangkat dicabut di tengah shift; kasir menekan tombol perbarui katalog.
    u.server.penangan = Dicabut;
    await tester.tap(find.widgetWithText(OutlinedButton, 'Perbarui katalog'));
    await Tunggu(tester);

    expect(find.text('Aktifkan perangkat kasir'), findsOneWidget);
    expect(find.byType(RuangKerja), findsNothing);
    await Lepas(tester, u);
  });

  // Dua keadaan yang tidak punya pewaktu apa pun: ruang kerja belum terbuka, jadi pewaktu sinkron 30 detik
  // miliknya tidak berjalan dan tidak ada yang akan menemukan pencabutan. Ini yang ditutup pemeriksaan berkala.
  testWidgets('dicabut saat belum buka shift: kembali ke aktivasi sendiri, tanpa disentuh kasir', (tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u);
    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    expect(find.textContaining('Buka shift'), findsWidgets);

    u.server.penangan = Dicabut;
    await tester.pump(GerbangKasir.selangPeriksaPerangkat);
    await Tunggu(tester);

    expect(find.text('Aktifkan perangkat kasir'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('dicabut saat berdiri di layar pilih kasir: kembali ke aktivasi sendiri', (tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u);
    expect(find.text('Siapa yang bertugas?'), findsOneWidget);

    u.server.penangan = Dicabut;
    await tester.pump(GerbangKasir.selangPeriksaPerangkat);
    await Tunggu(tester);

    expect(find.text('Aktifkan perangkat kasir'), findsOneWidget);
    await Lepas(tester, u);
  });
}
