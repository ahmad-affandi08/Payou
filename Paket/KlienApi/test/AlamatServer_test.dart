import 'package:klien_api/KlienApi.dart';
import 'package:test/test.dart';

/// D-35: alamat server toko sendiri yang diketik di aplikasi Kasir & Pemilik.
void main() {
  test('alamat ketikan dinormalkan menjadi https dengan garis miring akhir', () {
    expect(NormalkanAlamatServer('kasir.tokoabc.id').toString(), 'https://kasir.tokoabc.id/');
    expect(NormalkanAlamatServer(' https://kasir.tokoabc.id/payoung ').toString(), 'https://kasir.tokoabc.id/payoung/');
    expect(NormalkanAlamatServer('http://192.168.1.10:8080').toString(), 'http://192.168.1.10:8080/');
  });

  test('kosong atau tidak sah ditolak', () {
    expect(NormalkanAlamatServer(''), isNull);
    expect(NormalkanAlamatServer('https://kasir.tokoabc.id/?a=1'), isNull);
    expect(NormalkanAlamatServer('mailto:kasir@toko.id'), isNull);
    expect(NormalkanAlamatServer('ftp://kasir.tokoabc.id'), isNull);
  });
}
