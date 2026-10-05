import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// X4 persetujuan jarak jauh di kasir: dari dialog PIN penyetuju (kas keluar di atas batas) kasir memilih "Minta
/// persetujuan jarak jauh"; perangkat menunggu keputusan dari Aplikasi Owner; disetujui = kas keluar tersimpan dengan
/// penyetuju itu (bisa pemilik yang tidak terdaftar di perangkat); ditolak = alasan tampil dan kasir bisa memakai PIN.
void main() {
  const uuidPemilik = '01K5STAF00000000000PEMILIK';

  Map<String, Object?> Permintaan(String status, {Map<String, Object?>? penyetuju, String? alasan}) => {
    'Uuid': '01K5PERSETUJUAN00000000001',
    'Status': status,
    'LabelStatus': status,
    'Judul': 'Kas keluar di atas batas',
    'Rincian': <Object?>[],
    'Nilai': '350000.00',
    'NamaOutlet': 'Kopi Senja Solo Baru',
    'NamaPerangkat': 'Kasir Depan',
    'NamaPemohon': 'Rina Wulandari',
    'AlasanTolak': alasan,
    'NamaPemutus': penyetuju == null && alasan == null ? null : 'Pak Harto',
    'Penyetuju': penyetuju,
  };

  Future<LingkunganUji> SiapkanKasKeluar(WidgetTester tester, {required bool fitur, required String keputusan}) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() => u.SiapkanAktif(dataAwal: DataAwalUji(persetujuanJarakJauh: fitur)));
    var dicek = 0;
    u.server.penangan = (p) async {
      final jalur = p.url.path;
      if (jalur.endsWith('/persetujuan/jarak-jauh') && p.method == 'POST') {
        return JsonUji({'Persetujuan': Permintaan('Menunggu')}, 201);
      }
      if (jalur.contains('/persetujuan/jarak-jauh/')) {
        if (jalur.endsWith('/batal')) {
          return JsonUji({'Persetujuan': Permintaan('Dibatalkan')});
        }
        dicek++;
        if (dicek < 2) {
          return JsonUji({'Persetujuan': Permintaan('Menunggu')});
        }
        return JsonUji({
          'Persetujuan': keputusan == 'Disetujui'
              ? Permintaan(
                  'Disetujui',
                  penyetuju: {
                    'Uuid': uuidPemilik,
                    'Nama': 'Pak Harto',
                    'Pemilik': true,
                    'Izin': ['kas.keluar.setujui'],
                  },
                )
              : Permintaan('Ditolak', alasan: 'Galon masih ada stok di gudang'),
        });
      }
      if (p.method == 'GET') {
        return JsonUji(DataAwalUji(persetujuanJarakJauh: fitur));
      }
      if (jalur.endsWith('/sinkron/kirim')) {
        throw http.ClientException('offline');
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u);
    await PilihKasir(tester, 'Rina Wulandari');
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await tester.enterText(find.byType(TextField), '500000');
    await tester.tap(find.text('Buka shift'));
    await Tunggu(tester, const Duration(seconds: 1));

    await tester.tap(find.text('Kas'));
    await Tunggu(tester);
    await tester.tap(find.widgetWithText(OutlinedButton, 'Kas keluar'));
    await Tunggu(tester);
    // Satu-satunya kategori kas keluar terpilih otomatis (audit kemudahan pakai #8).
    expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Beli es batu & galon')).selected, isTrue);
    await tester.enterText(find.widgetWithText(TextField, 'Jumlah'), '350000');
    await tester.tap(find.text('Simpan kas keluar'));
    await Tunggu(tester);
    expect(find.text('Persetujuan supervisor'), findsOneWidget);
    return u;
  }

  testWidgets('disetujui dari Aplikasi Owner → kas keluar tersimpan dengan penyetuju pemilik', (tester) async {
    final u = await SiapkanKasKeluar(tester, fitur: true, keputusan: 'Disetujui');

    await tester.tap(find.text('Minta persetujuan jarak jauh'));
    await Tunggu(tester);
    expect(find.text('Menunggu persetujuan'), findsOneWidget);
    final ajukan = u.server.permintaan.firstWhere((p) => p.url.path.endsWith('/persetujuan/jarak-jauh'));
    final isi = jsonDecode(ajukan.body) as Map<String, Object?>;
    expect(isi['Izin'], 'kas.keluar.setujui');
    expect(isi['UuidPengguna'], '01K5STAF000000000000000001');
    expect(isi['Judul'], 'Kas keluar di atas batas');
    expect(isi['Nilai'], '350000.00');
    expect((isi['Rincian']! as List<Object?>).first, containsPair('Label', 'Keterangan'));

    // Pantau tiap 3 detik sampai disetujui.
    await Tunggu(tester, const Duration(seconds: 8));
    expect(find.text('Menunggu persetujuan'), findsNothing);
    expect(find.textContaining('disetujui supervisor'), findsOneWidget);

    final outbox = await tester.runAsync(() => u.db.select(u.db.outbox).get());
    final kas = outbox!.singleWhere((o) => o.Jenis == 'MutasiKas.Catat');
    expect(jsonDecode(kas.Data), containsPair('UuidPenyetuju', uuidPemilik));
    await Lepas(tester, u);
  });

  testWidgets('ditolak → alasan tampil dan kasir tetap bisa memakai PIN penyetuju', (tester) async {
    final u = await SiapkanKasKeluar(tester, fitur: true, keputusan: 'Ditolak');

    await tester.tap(find.text('Minta persetujuan jarak jauh'));
    await Tunggu(tester, const Duration(seconds: 8));
    expect(find.text('Ditolak Pak Harto: Galon masih ada stok di gudang'), findsOneWidget);
    expect(find.text('PIN Budi Santoso'), findsOneWidget);

    await PilihPenyetuju(tester, 'Budi Santoso');
    await tester.pump();
    await KetikPin(tester, KasusPin(1)['Pin']! as String);
    await Tunggu(tester, const Duration(seconds: 1));
    expect(find.textContaining('disetujui supervisor'), findsOneWidget);
    await Lepas(tester, u);
  });

  testWidgets('fitur paket tidak aktif → tombol jarak jauh tidak ditawarkan', (tester) async {
    final u = await SiapkanKasKeluar(tester, fitur: false, keputusan: 'Disetujui');
    expect(find.text('Minta persetujuan jarak jauh'), findsNothing);
    await tester.tap(find.text('Batal'));
    await Tunggu(tester);
    await Lepas(tester, u);
  });
}
