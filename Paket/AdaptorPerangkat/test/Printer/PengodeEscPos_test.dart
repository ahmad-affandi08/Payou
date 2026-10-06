import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:test/test.dart';

/// Posisi [pola] di [data], atau -1.
int Cari(List<int> data, List<int> pola) {
  for (var i = 0; i + pola.length <= data.length; i++) {
    var cocok = true;
    for (var j = 0; j < pola.length; j++) {
      if (data[i + j] != pola[j]) {
        cocok = false;
        break;
      }
    }
    if (cocok) {
      return i;
    }
  }
  return -1;
}

void main() {
  group('PengodeEscPos', () {
    test('inisialisasi → laci (bila diminta) → teks tebal → potong kertas', () {
      final byte = PengodeEscPos.Kodekan(
        const DokumenStruk([BarisTeks('TOTAL', tebal: true)], bukaLaci: true),
        LebarKertas.Mm58,
      );
      expect(byte.sublist(0, 2), PerintahEscPos.inisialisasi);
      expect(byte.sublist(2, 7), PerintahEscPos.bukaLaci);
      final teks = Cari(byte, ascii.encode('TOTAL\n'));
      expect(Cari(byte, PerintahEscPos.tebalNyala), lessThan(teks));
      expect(Cari(byte, PerintahEscPos.tebalMati), greaterThan(teks));
      expect(byte.sublist(byte.length - PerintahEscPos.potongKertas.length), PerintahEscPos.potongKertas);
    });

    test('tanpa laci & tanpa potong: tidak ada pulsa laci maupun pemotong', () {
      final byte = PengodeEscPos.Kodekan(const DokumenStruk([BarisKosong()], potong: false), LebarKertas.Mm80);
      expect(Cari(byte, PerintahEscPos.bukaLaci), -1);
      expect(Cari(byte, [0x1D, 0x56]), -1);
    });

    test('QR: GS ( k simpan data dengan panjang pL pH = data + 3 lalu cetak', () {
      final byte = PengodeEscPos.KodekanQr('https://payoung.id/s/AB12', 6);
      final simpan = Cari(byte, [0x1D, 0x28, 0x6B, 28, 0x00, 0x31, 0x50, 0x30]);
      expect(simpan, greaterThan(0));
      expect(utf8.decode(byte.sublist(simpan + 8, simpan + 8 + 25)), 'https://payoung.id/s/AB12');
      expect(byte.sublist(byte.length - 8), [0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30]);
    });

    test('gambar raster GS v 0: bit tertinggi = titik paling kiri, lebar dibulatkan ke byte', () {
      // 10×1: titik 0 dan 9 hitam → byte 1000 0000, 0100 0000.
      final titik = Uint8List(10)
        ..[0] = 1
        ..[9] = 1;
      final byte = PengodeEscPos.KodekanGambar(GambarMonokrom(10, 1, titik), LebarKertas.Mm58);
      expect(byte, [0x1D, 0x76, 0x30, 0x00, 2, 0, 1, 0, 0x80, 0x40]);
    });

    test('halaman uji memuat penggaris selebar kertas', () {
      final teks = TataLetakStruk.KeTeks(
        PrinterStruk.BuatDokumenUji(LebarKertas.Mm58, 'Kopi Senja', null),
        LebarKertas.Mm58,
      );
      expect(teks, contains('12345678901234567890123456789012'));
      expect(teks, contains('[QR https://payoung.id]'));
    });
  });

  group('TransportJaringan', () {
    test('mengirim byte ke printer RAW port 9100 (server soket lokal)', () async {
      final server = await ServerSocket.bind(InternetAddress.loopbackIPv4, 0);
      final diterima = <int>[];
      final selesai = server.first.then((soket) => soket.forEach(diterima.addAll));
      final printer = PrinterStruk(TransportJaringan('127.0.0.1', port: server.port), LebarKertas.Mm58);

      await printer.BukaLaci();
      await selesai;
      await server.close();
      expect(diterima, PengodeEscPos.KodekanBukaLaci());
    });

    test('printer tidak tersambung → GalatPrinter dengan pesan untuk kasir', () async {
      final server = await ServerSocket.bind(InternetAddress.loopbackIPv4, 0);
      final port = server.port;
      await server.close();
      await expectLater(
        TransportJaringan('127.0.0.1', port: port, batasWaktu: const Duration(seconds: 2)).Kirim([0x1B, 0x40]),
        throwsA(isA<GalatPrinter>().having((g) => g.pesan, 'pesan', contains('tidak tersambung'))),
      );
    });
  });
}
