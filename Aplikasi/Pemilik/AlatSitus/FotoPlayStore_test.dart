import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:pemilik/Data/PenyimpanSesi.dart';

import '../test/Pendukung/PasangPemilik.dart';
import 'DataDemoPemilik.dart';
import 'FotoSitus_test.dart' show MuatFont;

/// Penghasil tangkapan layar Play Store aplikasi Pemilik (bukan test regresi; di luar `test/` agar tidak ikut CI).
/// Layar asli dengan data contoh dua outlet kafe, dipotret dari layer pada 2,7× (400 × 800 dp → 1080 × 2160 px):
///
/// ```
/// cd Aplikasi/Pemilik && flutter test AlatSitus/FotoPlayStore_test.dart
/// ```
void main() {
  Future<void> SimpanFoto(WidgetTester tester, String nama) async {
    await tester.pumpAndSettle();
    var objek = tester.renderObject(find.byType(MaterialApp));
    while (!objek.isRepaintBoundary) {
      objek = objek.parent!;
    }
    final layer = objek.debugLayer! as OffsetLayer;
    final batas = objek.paintBounds;
    await tester.runAsync(() async {
      final gambar = await layer.toImage(batas, pixelRatio: 2.7);
      final data = await gambar.toByteData(format: ui.ImageByteFormat.png);
      final berkas = File('AlatSitus/Hasil/PlayStore/$nama.png');
      await berkas.parent.create(recursive: true);
      await berkas.writeAsBytes(data!.buffer.asUint8List());
    });
  }

  Map<String, Object?> Permintaan(String uuid, String judul, String nilai, String pemohon, String catatan, int menit) =>
      {
        'Uuid': uuid,
        'Status': 'Menunggu',
        'LabelStatus': 'Menunggu keputusan',
        'Judul': judul,
        'Rincian': [
          {'Label': 'Catatan', 'Nilai': catatan},
        ],
        'Nilai': nilai,
        'NamaOutlet': 'Solo Baru',
        'NamaPerangkat': 'Kasir Depan',
        'NamaPemohon': pemohon,
        'DibuatPada': DateTime.utc(2026, 9, 26, 7, menit).toIso8601String(),
        'KedaluwarsaPada': DateTime.utc(2026, 9, 26, 7, menit + 10).toIso8601String(),
      };

  testWidgets('tangkapan layar Play Store aplikasi Pemilik', (tester) async {
    await MuatFont();
    final server = ServerTiruan();
    final sesi = PenyimpanSesiMemori();
    server.penangan = (http.Request p) async {
      final jalur = p.url.path.replaceFirst('/api/pemilik/v1/', '');
      return switch (jalur) {
        'masuk' => JsonUji({
          'Token': '5|contoh',
          'Pengguna': {'Uuid': 'U1', 'Nama': 'Bu Sari', 'Email': 'sari@contoh.id'},
          'Tenant': [
            {'Uuid': 'T1', 'Nama': 'Kopi Senja', 'Pemilik': true},
          ],
        }),
        'profil' => JsonUji({
          'Pengguna': {'Nama': 'Bu Sari'},
          'Tenant': [
            {'Uuid': 'T1', 'Nama': 'Kopi Senja', 'Pemilik': true},
          ],
        }),
        'dasbor' => JsonUji(DasborDemo()),
        'laporan/penjualan' => JsonUji({
          'Kelompok': p.url.queryParameters['kelompok'] ?? 'Produk',
          'Baris': [
            {'Nama': 'Es Kopi Susu Aren', 'Jumlah': '96', 'Omzet': '1728000.00'},
            {'Nama': 'Matcha Latte', 'Jumlah': '41', 'Omzet': '1066000.00'},
            {'Nama': 'Croissant Cokelat', 'Jumlah': '38', 'Omzet': '950000.00'},
            {'Nama': 'Nasi Goreng Kampung', 'Jumlah': '27', 'Omzet': '864000.00'},
            {'Nama': 'Cappuccino', 'Jumlah': '29', 'Omzet': '696000.00'},
            {'Nama': 'Mie Goreng Jawa', 'Jumlah': '19', 'Omzet': '570000.00'},
            {'Nama': 'Es Teh Leci', 'Jumlah': '33', 'Omzet': '528000.00'},
            {'Nama': 'Roti Bakar Srikaya', 'Jumlah': '22', 'Omzet': '440000.00'},
          ],
          'Total': {'Jumlah': '305', 'Omzet': '6842000.00'},
        }),
        'persetujuan' => JsonUji({
          'Persetujuan': [
            Permintaan('P1', 'Kas keluar di atas batas', '350000.00', 'Rina Wulandari', 'Galon 12 + es balok 4', 58),
            Permintaan('P2', 'Diskon manual 20%', '48000.00', 'Dimas Pratama', 'Pelanggan tetap, ulang tahun', 52),
          ],
        }),
        'shift' => JsonUji({
          'Shift': [
            for (final (uuid, outlet, kasir, buka, tutup, status, selisih) in [
              ('S1', 'Solo Baru', 'Rina Wulandari', '2026-09-26T07:00:00Z', null, 'Terbuka', null),
              ('S2', 'Kartasura', 'Dimas Pratama', '2026-09-26T08:00:00Z', null, 'Terbuka', null),
              ('S3', 'Solo Baru', 'Ayu Lestari', '2026-09-25T15:00:00Z', '2026-09-25T23:04:00Z', 'Ditutup', '0.00'),
              (
                'S4',
                'Kartasura',
                'Bagus Saputra',
                '2026-09-25T15:00:00Z',
                '2026-09-25T23:11:00Z',
                'Ditutup',
                '-20000.00',
              ),
            ])
              {
                'Uuid': uuid,
                'Outlet': outlet,
                'Kasir': kasir,
                'DibukaPada': buka,
                'DitutupPada': tutup,
                'Status': status,
                'Selisih': selisih,
              },
          ],
        }),
        'perangkat' => JsonUji({
          'Perangkat': [
            for (final (kode, nama, outlet, tertunda) in [
              ('SLB-K01', 'Kasir Depan', 'Solo Baru', 0),
              ('SLB-K02', 'Kasir Drive-thru', 'Solo Baru', 2),
              ('KTS-K01', 'Kasir Utama', 'Kartasura', 0),
            ])
              {
                'Uuid': kode,
                'Kode': kode,
                'Nama': nama,
                'Jenis': 'Kasir',
                'Outlet': outlet,
                'Status': 'Aktif',
                'TerakhirAktifPada': '2026-09-26T07:58:00Z',
                'JumlahOutboxTertunda': tertunda,
                'VersiAplikasi': '1.0.0',
              },
          ],
        }),
        'karyawan' => JsonUji({
          'Tanggal': '2026-09-26',
          'Periode': '2026-09',
          'Ringkasan': {'Hadir': 5, 'SedangBekerja': 4, 'Terlambat': 1, 'BelumMasuk': 1},
          'Kehadiran': [
            for (final (nama, outlet, jadwal, masuk, telat) in [
              ('Rina Wulandari', 'Solo Baru', '07:00–15:00', '06:52', 0),
              ('Ayu Lestari', 'Solo Baru', '07:00–15:00', '06:58', 0),
              ('Dimas Pratama', 'Kartasura', '08:00–16:00', '08:14', 14),
              ('Bagus Saputra', 'Kartasura', '08:00–16:00', '07:55', 0),
            ])
              {
                'NamaKaryawan': nama,
                'NamaOutlet': outlet,
                'Jadwal': jadwal,
                'JamMasuk': masuk,
                'JamKeluar': null,
                'TerlambatMenit': telat,
                'Sumber': 'Web',
              },
          ],
          'BelumMasuk': [
            {'NamaKaryawan': 'Sinta Maharani', 'NamaOutlet': 'Solo Baru', 'Jadwal': '15:00–23:00'},
          ],
          'Komisi': [
            {'NamaKaryawan': 'Rina Wulandari', 'Komisi': '485000.00'},
            {'NamaKaryawan': 'Dimas Pratama', 'Komisi': '412500.00'},
          ],
          'Target': [
            {
              'LabelCakupan': 'Outlet',
              'NamaSasaran': 'Solo Baru',
              'Nilai': '150000000.00',
              'Realisasi': '118400000.00',
              'Persen': '78.9',
              'Proyeksi': '141000000.00',
            },
          ],
        }),
        'insight' => JsonUji({
          'Insight': {
            'Dari': '2026-09-14',
            'Sampai': '2026-09-20',
            'Bersih': '52640000.00',
            'BersihSebelumnya': '47310000.00',
            'PersenPerubahan': '11.3',
            'JumlahTransaksi': 1384,
            'JumlahTransaksiSebelumnya': 1262,
            'RataTransaksi': '38035.00',
            'HariTeramai': {'Tanggal': '2026-09-20', 'Bersih': '9870000.00'},
            'Terlaris': [
              {'NamaProduk': 'Es Kopi Susu Aren', 'Qty': '612.0000', 'Bersih': '11016000.00'},
              {'NamaProduk': 'Matcha Latte', 'Qty': '254.0000', 'Bersih': '6604000.00'},
            ],
            'Naik': [
              {'NamaProduk': 'Matcha Latte', 'Selisih': '1820000.00'},
            ],
            'Turun': [
              {'NamaProduk': 'Cokelat Panas', 'Selisih': '-410000.00'},
            ],
            'Restock': [
              {
                'NamaProduk': 'Susu Segar 1 L',
                'NamaGudang': 'Solo Baru',
                'HariHabis': 2,
                'SaranBeli': '48.0000',
                'SimbolSatuan': 'L',
              },
            ],
            'Lebaran': null,
          },
        }),
        'pengumuman' => JsonUji({'Pengumuman': <Object?>[]}),
        'notifikasi' => JsonUji({'Notifikasi': <Object?>[], 'BelumDibaca': 0}),
        _ => JsonUji({}, 404),
      };
    };
    await PasangPemilik(tester, server: server, sesi: sesi, ukuran: const Size(400, 800));
    await tester.enterText(find.widgetWithText(TextField, 'Email'), 'sari@contoh.id');
    await tester.enterText(find.widgetWithText(TextField, 'Kata sandi'), 'rahasia123');
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();
    await SimpanFoto(tester, 'PemilikHp1Beranda');

    Future<void> Buka(String label, String nama) async {
      await tester.tap(find.text(label).last);
      await tester.pumpAndSettle();
      await SimpanFoto(tester, nama);
    }

    await Buka('Laporan', 'PemilikHp2Laporan');
    await Buka('Persetujuan', 'PemilikHp3Persetujuan');
    await Buka('Shift', 'PemilikHp4Shift');
    await Buka('Beranda', 'PemilikHp1Beranda');
    Future<void> BukaKartu(String judul, String nama) async {
      await tester.tap(find.text(judul).first);
      await tester.pumpAndSettle();
      await SimpanFoto(tester, nama);
      await tester.tap(find.byType(BackButton).first);
      await tester.pumpAndSettle();
    }

    await BukaKartu('Insight minggu lalu', 'PemilikHp6Insight');
    await BukaKartu('Karyawan hari ini', 'PemilikHp5Karyawan');
  });
}
