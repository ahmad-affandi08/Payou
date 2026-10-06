import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:kasir/Data/CacheGambarProduk.dart';
import 'package:klien_api/KlienApi.dart';

void main() {
  test('gambar diunduh bertoken lalu dapat dibaca dari cache saat offline', () async {
    final folder = await Directory.systemTemp.createTemp('payoung-gambar-produk-');
    addTearDown(() => folder.delete(recursive: true));
    var jumlahUnduh = 0;
    final klien = KlienPos(
      alamatDasar: Uri.parse('https://kasir.contoh.id/'),
      versiAplikasi: '1.0.0',
      ambilToken: () => 'TokenPerangkat',
      klien: MockClient((p) async {
        jumlahUnduh++;
        expect(p.headers['Authorization'], 'Bearer TokenPerangkat');
        return http.Response.bytes([1, 2, 3, 4], 200, headers: {'content-type': 'image/webp'});
      }),
    );
    const url = 'http://server-internal/api/pos/v1/katalog/gambar/PRODUK1?ukuran=kecil&versi=VERSI1';

    final cache = CacheGambarProduk(klien: klien, folderAplikasi: folder);
    expect(await cache.Ambil(url), [1, 2, 3, 4]);
    expect(await cache.Ambil(url), [1, 2, 3, 4]);
    expect(jumlahUnduh, 1, reason: 'Pemanggilan kedua memakai cache memori.');

    final offline = CacheGambarProduk(
      klien: KlienPos(
        alamatDasar: Uri.parse('https://kasir.contoh.id/'),
        versiAplikasi: '1.0.0',
        ambilToken: () => 'TokenPerangkat',
        klien: MockClient((_) async => throw http.ClientException('offline')),
      ),
      folderAplikasi: folder,
    );
    expect(await offline.Ambil(url), [1, 2, 3, 4], reason: 'Cache berkas tersedia setelah aplikasi dibuka ulang.');
  });
}
