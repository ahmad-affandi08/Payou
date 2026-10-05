import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';

import '../Pendukung/GudangUji.dart';
import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// POS-25 modul Gudang di ruang kerja: menu Stok › Gudang (izin `persediaan.kelola`), daftar dokumen online, pindai
/// barcode/SKU menambah jumlah baris, konfirmasi lalu posting; di 360/800/1280 dp.
void main() {
  Future<LingkunganUji> Masuk(WidgetTester tester, Size ukuran, {PemindaiQrTiruan? pemindaiQr}) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.shift.BukaShift(kasir: await u.Staf('Budi Santoso'), kasAwal: Uang.DariBulat(500000));
    });
    var transferDiterima = false;
    u.server.penangan = (p) async {
      final jalur = p.url.path;
      if (jalur.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (jalur.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      if (jalur.endsWith('/gudang/transfer')) {
        return JsonUji({
          'Transfer': transferDiterima ? <Object?>[] : [TransferGudangUji()],
        });
      }
      if (jalur.endsWith('/terima')) {
        transferDiterima = true;
        return JsonUji({'Transfer': TransferGudangUji(sisa: '0.0000', status: 'Diterima')});
      }
      if (jalur.endsWith('/gudang/pesanan-pembelian')) {
        return JsonUji({
          'Pesanan': [PesananGudangUji()],
        });
      }
      if (jalur.endsWith('/gudang/penerimaan')) {
        return JsonUji({
          'Penerimaan': {'Uuid': '01K5GR00000000000000000001', 'Nomor': 'GR/SLB/2609/0012'},
          'Pesanan': null,
        }, 201);
      }
      if (jalur.endsWith('/gudang/opname')) {
        return JsonUji({
          'Opname': [OpnameGudangUji()],
        });
      }
      if (jalur.endsWith('/hitung')) {
        return JsonUji({'Opname': OpnameGudangUji(dihitung: 2, fisikRoti: '3.0000')});
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran, pemindaiQr: pemindaiQr);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await PilihKasir(tester, 'Budi Santoso');
    await tester.pump();
    await KetikPin(tester, KasusPin(1)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);
    return u;
  }

  Future<void> Ketuk(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder);
    await Tunggu(tester, const Duration(milliseconds: 60));
    await tester.ensureVisible(finder);
    await tester.pump();
    await tester.tap(finder);
    await Tunggu(tester);
  }

  Future<void> Pindai(WidgetTester tester, String kode) async {
    await tester.enterText(find.byKey(const ValueKey('PindaiGudang')), kode);
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await Tunggu(tester);
  }

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    testWidgets('terima transfer masuk dengan pindai SKU (${ukuran.width.toInt()} dp)', (tester) async {
      final u = await Masuk(tester, ukuran);
      await Ketuk(tester, find.text('Stok').last);
      await Ketuk(tester, find.text('Transfer masuk').last);
      expect(find.text('TF/GDG-SLB/2609/0003'), findsOneWidget);
      await Ketuk(tester, find.text('TF/GDG-SLB/2609/0003'));

      await Pindai(tester, 'CRS-01');
      await Pindai(tester, 'crs-01');
      expect(find.textContaining('Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo: 2 pcs'), findsOneWidget);
      await Pindai(tester, '0000000000');
      expect(find.text('Kode "0000000000" tidak dikenal di katalog perangkat.'), findsOneWidget);

      await Ketuk(tester, find.text('Isi semua sisa'));
      await Ketuk(tester, find.text('Terima transfer'));
      expect(find.text('Terima transfer TF/GDG-SLB/2609/0003?'), findsOneWidget);
      await Ketuk(tester, find.text('Ya, simpan'));
      await Tunggu(tester, const Duration(milliseconds: 400));

      final kirim = u.server.permintaan.where((p) => p.url.path.endsWith('/terima')).single;
      expect(jsonDecode(kirim.body), {
        'UuidPengguna': '01K5STAF000000000000000002',
        'Baris': [
          {'Urutan': 1, 'Jumlah': '30.0000'},
        ],
      });
      expect(kirim.headers['Idempotency-Key'], startsWith('gudang-'));
      // Diterima penuh → kembali ke daftar yang kini kosong.
      expect(find.text('Tidak ada transfer stok yang menuju outlet ini.'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  testWidgets('terima barang dari PO: pindai barcode lusin, batch wajib diisi, lalu posting', (tester) async {
    final u = await Masuk(tester, const Size(1280, 900));
    await Ketuk(tester, find.text('Stok').last);
    await Ketuk(tester, find.text('Terima barang').last);
    await Ketuk(tester, find.text('PO/SLB/2609/0007'));

    await Pindai(tester, UuidUji.barcodeRotiLusin);
    expect(find.textContaining('Roti Tawar Gandum: 1 lsn'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('Jumlah-2')), '24');
    await Tunggu(tester);
    await Ketuk(tester, find.text('Terima barang').last);
    await Ketuk(tester, find.text('Ya, simpan'));
    expect(find.text('Susu UHT 1 Liter: isi nomor batch dari kemasan.'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('Batch-2')), 'UHT-2610B');
    await Tunggu(tester);
    await Ketuk(tester, find.text('Terima barang').last);
    await Ketuk(tester, find.text('Ya, simpan'));
    await Tunggu(tester, const Duration(milliseconds: 400));

    final kirim = u.server.permintaan.where((p) => p.url.path.endsWith('/gudang/penerimaan')).single;
    expect((jsonDecode(kirim.body) as Map<String, Object?>)['Baris'], [
      {'Urutan': 1, 'Jumlah': '1.0000'},
      {'Urutan': 2, 'Jumlah': '24.0000', 'NomorBatch': 'UHT-2610B'},
    ]);
    expect(find.text('Pesanan pembelian siap diterima'), findsNothing);
    await Lepas(tester, u);
  });

  testWidgets('hitung opname: pindai menambah hitungan, produk di luar lembar jadi baris baru', (tester) async {
    final u = await Masuk(tester, const Size(800, 1000));
    await Ketuk(tester, find.text('Stok').last);
    await Ketuk(tester, find.text('Hitung stok opname').last);
    expect(find.textContaining('hitung buta'), findsOneWidget);
    await Ketuk(tester, find.text('SO/SLB/2609/001'));

    for (var i = 0; i < 3; i++) {
      await Pindai(tester, 'RTG-01');
    }
    await Pindai(tester, 'GA-01');
    expect(find.textContaining('Gula Aren Cair tidak ada di lembar hitung'), findsOneWidget);
    await Ketuk(tester, find.text('Simpan hitung'));
    await Ketuk(tester, find.text('Ya, simpan'));
    await Tunggu(tester, const Duration(milliseconds: 400));

    final kirim = u.server.permintaan.where((p) => p.url.path.endsWith('/hitung')).single;
    expect((jsonDecode(kirim.body) as Map<String, Object?>)['Hitung'], [
      {'Urutan': 1, 'JumlahFisik': '3.0000'},
      {'UuidProduk': UuidUji.gulaAren, 'JumlahFisik': '1.0000'},
    ]);
    expect(find.textContaining('tersimpan 3'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('kamera memindai barcode pada pekerjaan gudang', (tester) async {
    final pemindai = PemindaiQrTiruan(hasil: 'CRS-01');
    final u = await Masuk(tester, const Size(360, 740), pemindaiQr: pemindai);
    await Ketuk(tester, find.text('Stok').last);
    await Ketuk(tester, find.text('Transfer masuk').last);
    await Ketuk(tester, find.text('TF/GDG-SLB/2609/0003'));

    await Ketuk(tester, find.byTooltip('Pindai barcode dengan kamera'));

    expect(pemindai.dipanggil, 1);
    expect(find.textContaining('Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo: 1 pcs'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });
}
