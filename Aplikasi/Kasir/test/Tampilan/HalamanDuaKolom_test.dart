import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:inti/Inti.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// D-40 "dua kolom kerja": layar non-Jual berisi angka utama + bagian kerja, dengan panel konteks di kanan pada area
/// kerja lebar (≥ 960dp) dan di bawah isi pada layar sempit. Riwayat = master-detail di layar lebar.
void main() {
  Future<LingkunganUji> MasukDenganDuaPenjualan(WidgetTester tester, Size ukuran) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.repositori.SimpanPengaturan(KunciPengaturan.kodePerangkat, 'POS-001');
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);

    Future<void> Ketuk(Finder f) async {
      await tester.ensureVisible(f);
      await tester.pump();
      await tester.tap(f);
      await Tunggu(tester);
    }

    for (final nama in ['Americano', 'Americano']) {
      await Ketuk(find.byWidgetPredicate((w) => w is UbinProduk && w.nama.startsWith(nama)));
      await tester.sendKeyEvent(LogicalKeyboardKey.f8);
      await Tunggu(tester);
      await Ketuk(find.widgetWithText(FilledButton, 'Uang pas'));
      await Ketuk(find.widgetWithText(FilledButton, 'Transaksi baru'));
    }
    return u;
  }

  testWidgets('1280 dp: Riwayat master-detail, Shift berangka utama & daftar periksa, Kas & Sinkron berpanel', (
    tester,
  ) async {
    final u = await MasukDenganDuaPenjualan(tester, const Size(1280, 900));

    await tester.tap(find.text('Riwayat'));
    await Tunggu(tester);
    const nomor1 = 'INV/SLB/260924/POS-001-0001';
    const nomor2 = 'INV/SLB/260924/POS-001-0002';
    expect(find.text('Rincian transaksi'), findsOneWidget);
    expect(find.text('No. $nomor1'), findsOneWidget, reason: 'Transaksi teratas terpilih otomatis.');
    expect(find.widgetWithText(OutlinedButton, 'Cetak ulang struk'), findsOneWidget);
    expect(find.text('Menunggu dikirim'), findsOneWidget);
    await tester.tap(find.text(nomor2));
    await Tunggu(tester);
    expect(find.text('No. $nomor2'), findsOneWidget);
    expect(find.text('No. $nomor1'), findsNothing);
    expect(find.byType(ExpansionTile), findsNothing, reason: 'Layar lebar memilih baris, bukan membuka di tempat.');

    await tester.tap(find.text('Shift'));
    await Tunggu(tester);
    expect(find.text('Penjualan shift ini'), findsOneWidget);
    expect(find.text('Rp 33.000'), findsWidgets);
    expect(find.textContaining('2 transaksi | rata-rata Rp 16.500'), findsOneWidget);
    expect(find.text('Per metode bayar'), findsOneWidget);
    expect(find.text('Produk terlaris'), findsOneWidget);
    expect(find.text('Sebelum tutup shift'), findsOneWidget);
    expect(find.text('Tidak ada pesanan tertahan'), findsOneWidget);
    expect(find.byType(GrafikBatang), findsOneWidget);

    await tester.tap(find.text('Kas'));
    await Tunggu(tester);
    expect(find.text('Perkiraan kas di laci'), findsOneWidget);
    expect(find.text('Asal uang di laci'), findsOneWidget);
    expect(find.text('Belum ada kas masuk atau keluar'), findsOneWidget);

    await tester.tap(find.text('Sinkron'));
    await Tunggu(tester);
    expect(find.text('Antrean kirim'), findsOneWidget);
    expect(find.text('Penjualan'), findsOneWidget);
    expect(find.text('Cara kerja sinkron'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });

  testWidgets('360 dp: panel konteks turun ke bawah isi; Riwayat tetap dibuka di tempat', (tester) async {
    final u = await MasukDenganDuaPenjualan(tester, const Size(360, 740));
    await tester.tap(find.text('Riwayat').last);
    await Tunggu(tester);
    expect(find.text('Rincian transaksi'), findsNothing);
    expect(find.byType(ExpansionTile), findsNWidgets(2));

    await tester.tap(find.text('Shift').last);
    await Tunggu(tester);
    expect(find.text('Penjualan shift ini'), findsOneWidget);
    await tester.ensureVisible(find.text('Sebelum tutup shift'));
    await Tunggu(tester);
    expect(find.text('Sebelum tutup shift'), findsOneWidget, reason: 'Panel konteks turun ke bawah isi.');
    expect(tester.takeException(), isNull);
    await Lepas(tester, u);
  });
}
