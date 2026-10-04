import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

void main() {
  testWidgets('aktivasi → daftar kasir dari data awal', (tester) async {
    final u = LingkunganUji.Buat();
    u.server.penangan = (p) async => p.url.path.endsWith('aktivasi')
        ? JsonUji({
            'TokenPerangkat': '12|rahasia',
            'KunciPinOffline': vektorPin['KunciPerangkat'],
            'Perangkat': {'Uuid': 'P1', 'Kode': 'POS-001', 'Nama': 'Kasir Depan'},
            'Outlet': {'Uuid': 'O1', 'Nama': 'Kopi Senja Solo Baru'},
            'Tenant': {'Nama': 'Kopi Senja'},
          }, 201)
        : JsonUji(DataAwalUji());
    await PasangAplikasi(tester, u);

    await tester.enterText(find.byType(TextField), 'AB12CD34');
    await tester.tap(find.text('Aktifkan perangkat'));
    await Tunggu(tester, const Duration(seconds: 1));

    expect(find.text('Siapa yang bertugas?'), findsOneWidget);
    expect(find.text('Rina Wulandari'), findsOneWidget);
    expect(find.text('Kopi Senja Solo Baru | POS-001'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('BR-06.3 offline: pilih kasir, PIN, buka shift dengan pecahan → ruang kerja & outbox tertunda', (
    tester,
  ) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    u.server.penangan = (_) async => throw http.ClientException('offline');
    await PasangAplikasi(tester, u);

    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, '111111');
    expect(find.textContaining('PIN salah. Sisa 4 percobaan'), findsOneWidget);

    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    expect(find.text('Buka shift | Rina Wulandari'), findsOneWidget);

    await tester.tap(find.text('Hitung per pecahan'));
    await tester.pump();
    await tester.tap(find.byTooltip('Tambah Rp 100.000'));
    await tester.tap(find.byTooltip('Tambah Rp 100.000'));
    await tester.tap(find.byTooltip('Tambah Rp 50.000'));
    await tester.pump();
    expect(find.widgetWithText(TextField, '250.000'), findsOneWidget);

    // Daftar pecahan yang terbuka membuat formulir lebih tinggi dari layar: gulir dulu seperti kasir sungguhan.
    await tester.ensureVisible(find.text('Buka shift'));
    await tester.pump();
    await tester.tap(find.text('Buka shift'));
    await Tunggu(tester, const Duration(seconds: 1));

    // Shift terbuka → Ruang Kerja Kasir dengan beranda Jual; kasir di bilah atas; status tertunda & offline terlihat.
    expect(find.byType(RuangKerja), findsOneWidget);
    expect(find.text('Katalog belum ada di perangkat ini.'), findsOneWidget);
    expect(find.text('Rina Wulandari'), findsOneWidget);
    expect(find.text('1 belum terkirim'), findsOneWidget);
    expect(find.text('Offline'), findsOneWidget);

    await tester.tap(find.text('Kas'));
    await Tunggu(tester);
    expect(find.text('Rp 250.000'), findsWidgets);
    await tester.tap(find.text('Shift'));
    await Tunggu(tester);
    expect(find.text('Dibuka oleh'), findsOneWidget);
    expect(find.text('Rina Wulandari'), findsWidgets);
    await Lepas(tester, u);
  });

  testWidgets('BR-06.4 kas keluar di atas batas meminta PIN supervisor lalu tercatat', (tester) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(u.SiapkanAktif);
    u.server.penangan = (p) async {
      if (p.method == 'GET') {
        return JsonUji(DataAwalUji());
      }
      final item = (jsonDecode(p.body) as Map<String, Object?>)['Item']! as List<Object?>;
      return JsonUji({
        'Hasil': [
          for (final i in item.cast<Map<String, Object?>>())
            {'Uuid': i['Uuid'], 'Jenis': i['Jenis'], 'Status': 'Diterima', 'Galat': null},
        ],
      });
    };
    await PasangAplikasi(tester, u);
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await tester.enterText(find.byType(TextField), '500000');
    await tester.tap(find.text('Buka shift'));
    await Tunggu(tester, const Duration(seconds: 1));

    await tester.tap(find.text('Kas'));
    await Tunggu(tester);
    await tester.tap(find.widgetWithText(OutlinedButton, 'Kas keluar'));
    await Tunggu(tester);
    // Formulir dibuka sebagai panel di atas area kerja (bukan halaman baru).
    expect(find.text('Kas keluar'), findsNWidgets(2));
    // Satu-satunya kategori kas keluar terpilih otomatis (audit kemudahan pakai #8).
    expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Beli es batu & galon')).selected, isTrue);
    await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '350000');
    await tester.tap(find.text('Simpan kas keluar'));
    await Tunggu(tester);

    expect(find.text('Persetujuan supervisor'), findsOneWidget);
    await PilihPenyetuju(tester, 'Budi Santoso');
    await tester.pump();
    await KetikPin(tester, KasusPin(1)['Pin']! as String);
    await Tunggu(tester, const Duration(seconds: 1));

    expect(find.text('Kas keluar'), findsOneWidget, reason: 'Panel tertutup setelah tersimpan.');
    expect(find.text('Beli es batu & galon'), findsOneWidget);
    expect(find.textContaining('disetujui supervisor'), findsOneWidget);
    expect(find.text('Rp 150.000'), findsOneWidget);
    expect(find.text('Tersinkron'), findsOneWidget);
    await Lepas(tester, u);
  });
}
