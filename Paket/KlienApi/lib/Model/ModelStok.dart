import 'UraiJson.dart';

/// Isi `GET /api/pos/v1/stok-tersedia` (F-07 + F-05, BR-05.2): sisa stok **lokasi Toko** outlet perangkat dalam
/// **satuan dasar**, hanya untuk produk berstok yang tidak boleh minus. Produk yang tidak ada di [tersedia] tidak
/// dibatasi (boleh minus, jasa, paket, resep), jadi kasir tidak membatasinya.
///
/// Jumlah berupa string desimal apa adanya (bisa nol atau negatif; tidak pernah melewati tipe pecahan biner).
class StokTersediaPos {
  const StokTersediaPos({required this.waktuServer, required this.tersedia, this.lengkap = true});

  /// Waktu server saat daftar dihitung (UTC); null bila server tidak mengirimnya atau formatnya tak terbaca.
  final DateTime? waktuServer;

  /// Uuid produk → sisa stok satuan dasar (string desimal).
  final Map<String, String> tersedia;

  /// False bila jawaban server tidak berbentuk daftar stok (kunci `Produk` bukan larik), misalnya jawaban kosong
  /// atau dari server lama. Jawaban begini tidak boleh dianggap "semua produk tanpa batas": pemanggil mengabaikannya
  /// dan tetap memakai salinan terakhir.
  final bool lengkap;

  static StokTersediaPos DariJson(Map<String, Object?> json) {
    final produk = json['Produk'];
    final peta = <String, String>{};
    for (final baris in UraiJson.AmbilDaftarPeta(produk)) {
      final uuid = UraiJson.AmbilTeks(baris['UuidProduk']);
      final nilai = baris['Tersedia'];
      final teks = switch (nilai) {
        final String s => s.trim(),
        final num n => n.toString(),
        _ => '',
      };
      // Baris tanpa Uuid atau tanpa jumlah dilewati: menganggapnya nol akan menandai produk "Habis" tanpa dasar.
      if (uuid.isEmpty || teks.isEmpty) {
        continue;
      }
      peta[uuid] = UraiJson.AmbilDesimal(teks);
    }
    final waktu = UraiJson.AmbilTeksAtauNull(json['WaktuServer']);
    return StokTersediaPos(
      waktuServer: waktu == null ? null : DateTime.tryParse(waktu)?.toUtc(),
      tersedia: Map.unmodifiable(peta),
      lengkap: produk is List<Object?>,
    );
  }
}
