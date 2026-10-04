import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:pemilik/Data/PenyimpanSesi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/PasangPemilik.dart';

/// OWN-02 (D-56): Beranda Aplikasi Owner yang modern: kartu omzet bermerek, ubin angka, dan bagian berpanel
/// (perlu tindakan dengan lencana jumlah, per outlet berbatang, per jam, produk terlaris berperingkat).
void main() {
  late ServerTiruan server;
  late PenyimpanSesiMemori sesi;

  setUp(() {
    server = ServerTiruan();
    sesi = PenyimpanSesiMemori()
      ..isi[PenyimpanSesi.kunciToken] = '5|rahasia'
      ..isi[PenyimpanSesi.kunciTenant] = 'T1';
  });

  Future<http.Response> Penangan(http.Request p) async {
    final jalur = p.url.path.replaceFirst('/api/pemilik/v1/', '');
    return switch (jalur) {
      'profil' => JsonUji({
        'Pengguna': {'Nama': 'Bu Sari'},
        'Tenant': [
          {'Uuid': 'T1', 'Nama': 'Kopi Senja', 'Pemilik': true},
        ],
      }),
      'dasbor' => JsonUji(DasborUji()),
      _ => JsonUji({}, 404),
    };
  }

  testWidgets('kartu omzet, ubin angka, dan bagian berpanel tampil', (tester) async {
    server.penangan = Penangan;
    await PasangPemilik(tester, server: server, sesi: sesi);

    expect(find.text('Omzet'), findsOneWidget);
    expect(find.text('Rp 1.250.000'), findsOneWidget);
    expect(find.byType(DeretKartuAngka), findsOneWidget);
    expect(find.widgetWithText(KartuAngka, 'Transaksi'), findsOneWidget);
    expect(find.widgetWithText(KartuAngka, 'Laba kotor'), findsOneWidget);

    expect(find.text('Perlu tindakan'), findsOneWidget);
    expect(find.byType(LencanaTeks), findsOneWidget, reason: 'Lencana jumlah hal perlu tindakan.');

    await tester.scrollUntilVisible(find.text('Per jam'), 200);
    expect(find.byType(GrafikBatang), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Produk terlaris'), 200);
    expect(find.text('Es Kopi Susu Aren'), findsOneWidget);
    expect(find.text('25 terjual'), findsOneWidget);
    expect(find.text('1'), findsOneWidget, reason: 'Nomor peringkat produk teratas.');
    expect(tester.takeException(), isNull);
  });

  testWidgets('tanpa hal yang perlu tindakan: keadaan kosong menjelaskan apa yang akan muncul', (tester) async {
    server.penangan = (p) async {
      final jalur = p.url.path.replaceFirst('/api/pemilik/v1/', '');
      if (jalur == 'dasbor') {
        return JsonUji({...DasborUji(), 'PerluTindakan': <Object?>[]});
      }
      return Penangan(p);
    };
    await PasangPemilik(tester, server: server, sesi: sesi);

    expect(find.text('Tidak ada yang perlu ditindaklanjuti.'), findsOneWidget);
    expect(find.byType(KeadaanKosong), findsOneWidget);
    expect(find.byType(LencanaTeks), findsNothing);
  });
}
