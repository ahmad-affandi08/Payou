import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// K-20 (§9.8) di aplikasi kasir (360/800/1280 dp): Riwayat › Reservasi › "Kalender & booking" menampilkan jam kerja
/// staf, reservasinya, dan rentang kosong; "Buat booking" memilih layanan, staf, jam kosong dari server, lalu mengirim
/// `POST /reservasi`. Hari sebelumnya tidak bisa dipilih; ganti tanggal memuat kalender tanggal itu.
void main() {
  const staf = '01K5KARYAWAN00000000000001';
  const layanan = '01K5PRODUKCREAMBATH0000001';
  Map<String, Object?> Reservasi(String uuid, String nama, String mulai, String selesai) => {
    'Uuid': uuid,
    'Nomor': 'RS/2026/09/0001',
    'MulaiPada': mulai,
    'SelesaiPada': selesai,
    'NamaPelanggan': nama,
    'NoHp': '0813-5555-0077',
    'Pelanggan': null,
    'UuidProduk': layanan,
    'NamaLayanan': 'Creambath Ginseng',
    'UuidStaf': staf,
    'NamaStaf': 'Maya',
    'Status': 'Dikonfirmasi',
    'LabelStatus': 'Dikonfirmasi',
    'Catatan': null,
  };

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('kalender staf + buat booking jam kosong (${ukuran.width.toInt()} dp)', (tester) async {
      final u = LingkunganUji.Buat();
      await tester.runAsync(() async {
        await u.SiapkanAktif();
        await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
      });
      final diminta = <http.Request>[];
      u.server.penangan = (p) async {
        if (p.url.path.endsWith('/data-awal')) {
          return JsonUji(DataAwalUji());
        }
        if (p.url.path.endsWith('/katalog')) {
          return JsonUji(KatalogUji());
        }
        diminta.add(p);
        if (p.url.path.endsWith('/reservasi') && p.method == 'GET') {
          return JsonUji({'Reservasi': <Object?>[]});
        }
        if (p.url.path.endsWith('/reservasi/kalender')) {
          return JsonUji({
            'Tanggal': p.url.queryParameters['tanggal'],
            'Layanan': [
              {'Uuid': layanan, 'Nama': 'Creambath Ginseng', 'DurasiMenit': 60, 'Harga': '85000.00'},
            ],
            'Staf': [
              {'Uuid': staf, 'Nama': 'Maya', 'JamMulai': '09:00', 'JamSelesai': '12:00'},
            ],
            'Reservasi': [Reservasi('R1', 'Ratna Sari', '2026-09-24T03:00:00Z', '2026-09-24T04:00:00Z')],
          });
        }
        if (p.url.path.endsWith('/reservasi/slot')) {
          return JsonUji({
            'Slot': [
              {
                'Jam': '09:00',
                'Staf': [
                  {'Uuid': staf, 'Nama': 'Maya'},
                ],
              },
              {
                'Jam': '11:00',
                'Staf': [
                  {'Uuid': staf, 'Nama': 'Maya'},
                ],
              },
            ],
          });
        }
        if (p.url.path.endsWith('/reservasi') && p.method == 'POST') {
          return JsonUji({
            'Reservasi': Reservasi('R2', 'Dina Lestari', '2026-09-24T04:00:00Z', '2026-09-24T05:00:00Z'),
          }, 201);
        }
        throw http.ClientException('offline');
      };
      await PasangAplikasi(tester, u, ukuran: ukuran);
      await Tunggu(tester, const Duration(milliseconds: 600));
      await PilihKasir(tester, 'Rina Wulandari');
      await tester.pump();
      await KetikPin(tester, KasusPin(0)['Pin']! as String);
      await Tunggu(tester);

      Future<void> Ketuk(Finder finder) async {
        await tester.ensureVisible(finder);
        await tester.pump();
        await tester.tap(finder);
        await Tunggu(tester);
      }

      await tester.tap(find.text('Riwayat').last);
      await Tunggu(tester);
      await Ketuk(find.widgetWithText(OutlinedButton, 'Reservasi hari ini'));
      await Ketuk(find.text('Kalender & booking'));
      expect(find.text('Kamis, 24 Sep 2026 (hari ini)'), findsOneWidget);
      expect(find.text('Maya | 09:00–12:00'), findsOneWidget);
      expect(find.text('10.00–11.00 | Ratna Sari | Creambath Ginseng | Dikonfirmasi'), findsOneWidget);
      expect(find.text('Kosong: 09:00–10:00, 11:00–12:00'), findsOneWidget);
      expect(
        tester
            .widget<IconButton>(
              find.descendant(
                of: find.byKey(const ValueKey('KalenderBooking')),
                matching: find.widgetWithIcon(IconButton, Icons.chevron_left),
              ),
            )
            .onPressed,
        isNull,
        reason: 'Tidak bisa booking hari yang sudah lewat.',
      );
      expect(diminta.last.url.queryParameters['tanggal'], '2026-09-24');

      await Ketuk(find.widgetWithText(FilledButton, 'Buat booking'));
      expect(diminta.last.url.queryParameters, {'UuidLayanan': layanan, 'Tanggal': '2026-09-24'});
      await Ketuk(find.byKey(const ValueKey('slot-11:00')));
      await tester.enterText(find.byKey(const ValueKey('BookingNama')), 'Dina Lestari');
      await tester.enterText(find.byKey(const ValueKey('BookingNoHp')), '0812 9999 1234');
      await Ketuk(find.widgetWithText(FilledButton, 'Simpan booking'));

      final kirim = diminta.lastWhere((p) => p.method == 'POST' && p.url.path.endsWith('/reservasi'));
      expect(jsonDecode(kirim.body), {
        'UuidPengguna': '01K5STAF000000000000000001',
        'UuidLayanan': layanan,
        'Tanggal': '2026-09-24',
        'Jam': '11:00',
        'UuidStaf': null,
        'NamaPelanggan': 'Dina Lestari',
        'NoHp': '0812 9999 1234',
        'Catatan': null,
      });
      expect(find.textContaining('Booking RS/2026/09/0001 dicatat: Dina Lestari, 11.00 dengan Maya.'), findsOneWidget);
      expect(find.text('Maya | 09:00–12:00'), findsOneWidget, reason: 'Kembali ke kalender setelah tersimpan.');

      await Ketuk(
        find.descendant(
          of: find.byKey(const ValueKey('KalenderBooking')),
          matching: find.widgetWithIcon(IconButton, Icons.chevron_right),
        ),
      );
      expect(find.text('Jumat, 25 Sep 2026'), findsOneWidget);
      expect(diminta.last.url.queryParameters['tanggal'], '2026-09-25');
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }
}
