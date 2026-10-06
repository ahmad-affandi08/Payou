import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Domain/Dapur/LayananTiketDapur.dart';
import 'package:kasir/Domain/Struk/DaftarPrinter.dart';
import 'package:kasir/Domain/Struk/ProfilPrinter.dart';

void main() {
  const struk = ProfilPrinter(alamat: '192.168.1.50');
  const dapur = ProfilPrinter(jenis: JenisTransport.BluetoothKlasik, alamat: 'AA:BB', nama: 'Dapur');

  test('Gabung: stasiun yang memakai printer struk satu kartu dengan struk; printer sendiri kartu terpisah', () {
    final daftar = DaftarPrinter.Gabung(struk, {
      'bar': const PrinterDapur.Struk(),
      'dapur': PrinterDapur.Sendiri(dapur),
      'kue': PrinterDapur.Sendiri(dapur),
    });

    expect(daftar, hasLength(2));
    expect((daftar[0].struk, daftar[0].stasiun), (true, {'bar'}));
    expect((daftar[1].struk, daftar[1].stasiun, daftar[1].profil.alamat), (false, {'dapur', 'kue'}, 'AA:BB'));
  });

  test('Gabung: tanpa printer struk, stasiun "pakai printer struk" diabaikan', () {
    final daftar = DaftarPrinter.Gabung(null, {'bar': const PrinterDapur.Struk()});
    expect(daftar, isEmpty);
  });

  test('Susun adalah kebalikan Gabung', () {
    final asal = {'bar': const PrinterDapur.Struk(), 'dapur': PrinterDapur.Sendiri(dapur)};
    final hasil = DaftarPrinter.Susun(DaftarPrinter.Gabung(struk, asal));

    expect(hasil.struk?.alamat, '192.168.1.50');
    expect(hasil.dapur['bar']?.samaDenganStruk, isTrue);
    expect(hasil.dapur['dapur']?.profil?.alamat, 'AA:BB');
  });

  test('Susun: hanya printer struk pertama yang jadi struk; tanpa struk = tidak ada profil struk', () {
    final tanpa = DaftarPrinter.Susun([const PrinterPerangkat(profil: dapur, stasiun: {'dapur'})]);
    expect(tanpa.struk, isNull);
    expect(tanpa.dapur['dapur']?.samaDenganStruk, isFalse);

    final dua = DaftarPrinter.Susun([
      const PrinterPerangkat(profil: struk, struk: true, stasiun: {'bar'}),
      const PrinterPerangkat(profil: dapur, struk: true),
    ]);
    expect(dua.struk?.alamat, '192.168.1.50');
    expect(dua.dapur['bar']?.samaDenganStruk, isTrue);
  });
}
