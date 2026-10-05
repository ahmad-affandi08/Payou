import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// K-18: foto bukti kas masuk/keluar (opsional) diambil dari kamera belakang, bisa dihapus/diambil ulang, lalu ikut
/// item outbox `MutasiKas.Catat` sebagai `Bukti` (JPEG base64). Setoran tidak menawarkan foto. Diuji 360/800/1280 dp.
void main() {
  const jpeg =
      '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDACgcHiMeGSgjISMtKygwPGRBPDc3PHtYXUlkkYCZlo+AjIqgtObDoKrarYqMyP/L2u71////m8H////6/+b9//j/2wBDASstLTw1PHZBQXb4pYyl+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj/wAARCAACAAIDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwBaKKK5TpP/2Q==';

  for (final ukuran in const [Size(360, 740), Size(800, 1280), Size(1280, 900)]) {
    testWidgets('lebar ${ukuran.width.toInt()} dp: kas keluar dengan foto bukti → outbox berisi Bukti', (tester) async {
      final u = LingkunganUji.Buat();
      final kamera = KameraBuktiTiruan(foto: base64Decode(jpeg));
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
      });
      u.server.penangan = (_) async => throw http.ClientException('offline');
      await PasangAplikasi(tester, u, ukuran: ukuran, kameraBukti: kamera);
      await PilihKasir(tester, 'Rina Wulandari');
      await tester.pump();
      await KetikPin(tester, KasusPin(0)['Pin']! as String);
      expect(find.byType(RuangKerja), findsOneWidget);

      await tester.tap(find.text('Kas').last);
      await Tunggu(tester);
      await tester.tap(find.widgetWithText(OutlinedButton, 'Setoran'));
      await Tunggu(tester);
      expect(find.text('Foto bukti / nota (opsional)'), findsNothing, reason: 'Setoran tanpa foto bukti.');
      await tester.tap(find.byTooltip('Tutup'));
      await Tunggu(tester);

      await tester.tap(find.widgetWithText(OutlinedButton, 'Kas keluar'));
      await Tunggu(tester);
      // Satu-satunya kategori kas keluar terpilih otomatis (audit kemudahan pakai #8).
      expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Beli es batu & galon')).selected, isTrue);
      await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '45000');
      await tester.ensureVisible(find.text('Foto bukti / nota (opsional)'));
      await tester.tap(find.text('Foto bukti / nota (opsional)'));
      await Tunggu(tester);
      expect(find.text('Foto bukti terlampir'), findsOneWidget);
      await tester.tap(find.widgetWithText(TextButton, 'Hapus'));
      await Tunggu(tester);
      expect(find.text('Foto bukti terlampir'), findsNothing);
      await tester.tap(find.text('Foto bukti / nota (opsional)'));
      await Tunggu(tester);
      expect(kamera.dipanggil, 2);
      expect(tester.takeException(), isNull);

      await tester.ensureVisible(find.text('Simpan kas keluar'));
      await tester.tap(find.text('Simpan kas keluar'));
      await Tunggu(tester, const Duration(seconds: 1));
      final outbox = (await tester.runAsync(() => u.db.select(u.db.outbox).get()))!;
      final data = jsonDecode(outbox.singleWhere((o) => o.Jenis == 'MutasiKas.Catat').Data) as Map<String, Object?>;
      expect(data['Bukti'], jpeg);
      expect(data['Jumlah'], '45000.00');
      await Lepas(tester, u);
    });
  }
}
