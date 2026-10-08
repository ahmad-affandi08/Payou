/// Galat dari server dengan format seragam `{"Galat": {"Kode", "Pesan", "Detail"}}` (PRD §16.2), atau galat
/// validasi Laravel (`errors`) yang dipetakan ke kode `DataTidakValid`.
class GalatApi implements Exception {
  const GalatApi({
    required this.kode,
    required this.pesan,
    required this.statusHttp,
    this.bidang,
    this.detail = const <String, Object?>{},
  });

  final String kode;
  final String pesan;
  final int statusHttp;
  final String? bidang;
  final Map<String, Object?> detail;

  /// Perangkat sudah tidak berhak (token dicabut/tidak berlaku): aplikasi wajib menghapus data sensitif lokal.
  bool CekPerangkatDitolak() => kode == 'PerangkatDicabut' || kode == 'TokenPerangkatTidakValid';

  /// Balasan 4xx yang bukan penolakan isi permintaan oleh aplikasi server: pembatasan laju/waktu habis (408, 425, 429),
  /// badan terlalu besar (413), atau balasan proxy/WAF tanpa badan `Galat` (kode cadangan `GalatServer`). Aman dicoba
  /// lagi nanti; jangan diperlakukan sebagai "item ini salah".
  bool CekGalatPerantara() => const {408, 413, 425, 429}.contains(statusHttp) || kode == 'GalatServer';

  @override
  String toString() => 'GalatApi($statusHttp $kode: $pesan)';
}

/// Server tidak bisa dihubungi (offline, DNS, waktu habis) atau membalas galat 5xx: aman untuk dicoba lagi.
class GalatJaringan implements Exception {
  const GalatJaringan(this.pesan);

  final String pesan;

  @override
  String toString() => 'GalatJaringan($pesan)';
}
