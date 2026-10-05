import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:pemilik/Aplikasi/Lingkungan.dart';
import 'package:pemilik/Aplikasi/Penyedia.dart';
import 'package:pemilik/Data/PenyediaMasukGoogle.dart';
import 'package:pemilik/Data/PenyimpanSesi.dart';

import '../Pendukung/PasangPemilik.dart';

/// D-57: Masuk dengan Google di Aplikasi Owner (menggantikan 2FA): tombol hanya bila server mengaktifkannya,
/// token ID dikirim ke `masuk/google`, batal = diam, galat server tampil.
class _GoogleTiruan implements PenyediaMasukGoogle {
  _GoogleTiruan(this.hasil);

  final String? hasil;
  final List<String> clientIdDipakai = [];

  @override
  Future<String?> AmbilTokenId({required String clientIdServer}) async {
    clientIdDipakai.add(clientIdServer);
    return hasil;
  }
}

void main() {
  late ServerTiruan server;
  late PenyimpanSesiMemori sesi;
  var googleAktif = true;
  var statusMasuk = 200;

  setUp(() {
    server = ServerTiruan();
    sesi = PenyimpanSesiMemori();
    googleAktif = true;
    statusMasuk = 200;
    server.penangan = (http.Request p) async {
      final jalur = p.url.path.replaceFirst('/api/pemilik/v1/', '');
      return switch (jalur) {
        'masuk/google/konfigurasi' => JsonUji({
          'Aktif': googleAktif,
          'ClientId': googleAktif ? 'klien-web.apps.googleusercontent.com' : null,
        }),
        'masuk/google' when statusMasuk != 200 => JsonUji({
          'Galat': {'Kode': 'AkunGoogleBelumTerdaftar', 'Pesan': 'Akun Google ini belum terdaftar di PAYOU.'},
        }, statusMasuk),
        'masuk/google' => JsonUji({
          'Token': '9|rahasia',
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
        'dasbor' => JsonUji(DasborUji()),
        _ => JsonUji({}, 404),
      };
    };
  });

  testWidgets('tombol Masuk dengan Google tampil bila diaktifkan dan masuk tanpa 2FA', (tester) async {
    final google = _GoogleTiruan('jwt-google');
    await PasangPemilik(
      tester,
      server: server,
      sesi: sesi,
      lingkungan: Lingkungan.Dev,
      tambahan: [penyediaMasukGoogle.overrideWithValue(google)],
    );

    expect(find.byKey(const ValueKey('MasukGoogle')), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('MasukGoogle')));
    await tester.pumpAndSettle();

    expect(google.clientIdDipakai, ['klien-web.apps.googleusercontent.com']);
    final kirim = server.permintaan.singleWhere((p) => p.url.path.endsWith('/masuk/google') && p.method == 'POST');
    final isi = jsonDecode(kirim.body) as Map<String, Object?>;
    expect(isi['IdToken'], 'jwt-google');
    expect(isi['NamaPerangkat'], 'Aplikasi Owner');
    expect(await sesi.Baca(PenyimpanSesi.kunciToken), '9|rahasia');
    expect(find.byKey(const ValueKey('MasukGoogle')), findsNothing);
  });

  testWidgets('tombol tidak tampil bila Google belum diaktifkan di server', (tester) async {
    googleAktif = false;
    await PasangPemilik(
      tester,
      server: server,
      sesi: sesi,
      lingkungan: Lingkungan.Dev,
      tambahan: [penyediaMasukGoogle.overrideWithValue(_GoogleTiruan('x'))],
    );

    expect(find.byKey(const ValueKey('MasukGoogle')), findsNothing);
    expect(find.widgetWithText(FilledButton, 'Masuk'), findsOneWidget);
  });

  testWidgets('dialog Google dibatalkan: tidak ada permintaan masuk dan tidak ada galat', (tester) async {
    await PasangPemilik(
      tester,
      server: server,
      sesi: sesi,
      lingkungan: Lingkungan.Dev,
      tambahan: [penyediaMasukGoogle.overrideWithValue(_GoogleTiruan(null))],
    );

    await tester.tap(find.byKey(const ValueKey('MasukGoogle')));
    await tester.pumpAndSettle();

    expect(server.permintaan.where((p) => p.url.path.endsWith('/masuk/google') && p.method == 'POST'), isEmpty);
    expect(find.byKey(const ValueKey('MasukGoogle')), findsOneWidget);
  });

  testWidgets('akun Google belum terdaftar: pesan server tampil di layar masuk', (tester) async {
    statusMasuk = 404;
    await PasangPemilik(
      tester,
      server: server,
      sesi: sesi,
      lingkungan: Lingkungan.Dev,
      tambahan: [penyediaMasukGoogle.overrideWithValue(_GoogleTiruan('jwt'))],
    );

    await tester.tap(find.byKey(const ValueKey('MasukGoogle')));
    await tester.pumpAndSettle();

    expect(find.text('Akun Google ini belum terdaftar di PAYOU.'), findsOneWidget);
    expect(await sesi.Baca(PenyimpanSesi.kunciToken), isNull);
  });
}
