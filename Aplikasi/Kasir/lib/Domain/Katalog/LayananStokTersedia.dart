import 'dart:async';
import 'dart:convert';

import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPenjualan.dart';
import '../Penjualan/Keranjang.dart';
import 'KatalogLokal.dart';

/// Salinan sisa stok lokasi Toko dari server (`GET /api/pos/v1/stok-tersedia`) yang disimpan di perangkat.
///
/// - [tersedia]: Uuid produk → sisa stok **satuan dasar**, hanya produk berstok yang tidak boleh minus. Produk yang
///   tidak ada di sini tanpa batas.
/// - [diambilPada]: jam **perangkat** (UTC) ketika permintaan ke server dimulai. Penjualan lokal yang dibuat sesudahnya
///   belum mungkin tercakup salinan ini.
/// - [belumTercakup]: Uuid penjualan yang masih di outbox ketika permintaan dimulai. Penjualan ini belum dilihat
///   server saat menghitung salinan, walau sekarang sudah terkirim.
class SalinanStokTersedia {
  const SalinanStokTersedia({
    required this.diambilPada,
    required this.tersedia,
    this.waktuServer,
    this.belumTercakup = const {},
  });

  final DateTime diambilPada;
  final DateTime? waktuServer;
  final Map<String, Kuantitas> tersedia;
  final Set<String> belumTercakup;

  /// Bentuk simpan satu blob di pengaturan lokal (`KunciPengaturan.stokTersedia`).
  String KeJson() => jsonEncode({
    'DiambilPada': diambilPada.toUtc().toIso8601String(),
    'WaktuServer': waktuServer?.toUtc().toIso8601String(),
    'Produk': {for (final e in tersedia.entries) e.key: e.value.KeString()},
    'BelumTercakup': belumTercakup.toList()..sort(),
  });

  /// Null bila blob rusak atau dari versi lain: salinan itu dibuang, bukan membuat aplikasi gagal mulai.
  static SalinanStokTersedia? DariJson(String teks) {
    try {
      final json = jsonDecode(teks);
      if (json is! Map<String, Object?>) {
        return null;
      }
      final diambil = json['DiambilPada'] is String ? DateTime.tryParse(json['DiambilPada']! as String) : null;
      final produk = json['Produk'];
      if (diambil == null || produk is! Map<String, Object?>) {
        return null;
      }
      final peta = <String, Kuantitas>{};
      for (final e in produk.entries) {
        final jumlah = e.value is String ? LayananStokTersedia.UraiKuantitas(e.value! as String) : null;
        if (jumlah != null) {
          peta[e.key] = jumlah;
        }
      }
      final waktuServer = json['WaktuServer'];
      final belum = json['BelumTercakup'];
      return SalinanStokTersedia(
        diambilPada: diambil.toUtc(),
        waktuServer: waktuServer is String ? DateTime.tryParse(waktuServer)?.toUtc() : null,
        tersedia: peta,
        belumTercakup: belum is List<Object?> ? {...belum.whereType<String>()} : const {},
      );
    } on FormatException {
      return null;
    }
  }
}

/// BR-05.2 (F-07 + F-05): kasir offline-first tidak punya angka stok, jadi penjualan produk berstok yang tidak boleh
/// minus tetap lolos dan stok server jadi minus. Layanan ini menyimpan **salinan sisa stok Toko** dari server dan
/// menghitung sisa yang boleh dijual sekarang ([HitungTersediaEfektif]) supaya keranjang menolak jumlah di atasnya.
/// Server sengaja tetap menerima penjualan yang sudah terjadi; ini hanya pencegah di sisi kasir.
///
/// **Sisa efektif** = stok salinan − penjualan lokal yang belum tercakup salinan − jumlah produk itu di keranjang
/// (semua dalam satuan dasar, konversi satuan jual dihormati).
///
/// **Penjualan lokal yang belum tercakup** (batasan yang dipilih, dihitung ulang tiap penjualan/outbox berubah):
/// 1. dibuat pada/setelah [SalinanStokTersedia.diambilPada] (jam perangkat saat permintaan dimulai), atau
/// 2. masih di outbox sekarang (belum terkirim atau ditolak server: ditolak tetap dihitung, sengaja konservatif), atau
/// 3. masih di outbox ketika permintaan dimulai ([SalinanStokTersedia.belumTercakup]); tanpa ini penjualan yang
///    dibuat sebelum permintaan lalu terkirim sesudahnya lolos dari kedua syarat di atas padahal salinan belum
///    memuatnya.
/// Penjualan `Void` tidak dihitung. Kesalahan sengaja ke arah aman (menolak lebih dulu): penjualan yang terkirim
/// bersamaan dengan permintaan terhitung dua kali sampai salinan berikutnya, dan void atas penjualan yang sudah
/// tercakup tidak menambah sisa sampai salinan berikutnya.
///
/// **Di luar cakupan** (dikoreksi oleh salinan berikutnya, ±20 detik saat online): penjualan di perangkat lain pada
/// outlet yang sama, retur, penyesuaian, bahan terbuang, penerimaan barang, serta komponen racikan/resep/paket.
/// Item pesanan meja yang sudah tersimpan belum mengurangi stok sampai dibayar, jadi tidak dihitung.
///
/// Tanpa salinan sama sekali (belum pernah online, server lama) atau produk di luar daftar = tanpa batas.
class LayananStokTersedia {
  LayananStokTersedia({
    required this.klien,
    required this.repositori,
    required this.repositoriPenjualan,
    DateTime Function()? jam,
  }) : _jam = jam ?? DateTime.now;

  /// Salinan yang isinya tidak berubah ditulis ulang paling cepat sekian lama sekali, agar `DiambilPada` tersimpan
  /// tidak terlalu usang untuk pembukaan aplikasi berikutnya (offline).
  static const Duration selangSimpanUlang = Duration(minutes: 10);

  final KlienPos klien;
  final RepositoriKasir repositori;
  final RepositoriPenjualan repositoriPenjualan;
  final DateTime Function() _jam;

  /// Dipanggil setiap salinan atau hitungan penjualan lokal berubah (pemicu tampilan "Habis" ikut berubah).
  void Function()? saatBerubah;

  SalinanStokTersedia? _salinan;
  Map<String, Kuantitas> _terjualLokal = const {};
  SalinanStokTersedia? _tersimpan;
  DateTime? _disimpanPada;
  bool _sedangMenyegarkan = false;
  int _urutanHitung = 0;

  /// Naik tiap [Kosongkan]: pemuatan salinan tersimpan yang dimulai sebelumnya dan selesai sesudahnya diabaikan.
  int _generasi = 0;
  StreamSubscription<void>? _langganan;

  SalinanStokTersedia? get salinan => _salinan;

  /// Ada salinan stok (jika tidak, semua produk tanpa batas).
  bool get CekAdaSalinan => _salinan != null;

  /// Muat salinan tersimpan lalu pantau perubahan penjualan lokal. Dipanggil sekali saat layanan dibuat.
  Future<void> Mulai() async {
    try {
      await Muat();
    } on Object {
      // Salinan tersimpan tak terbaca (basis data sedang ditutup, dll.): tanpa batas sampai Segarkan berhasil.
    }
    _langganan ??= repositoriPenjualan.PantauPerubahanPenjualan().listen(
      (_) => unawaited(HitungUlangTerjual()),
      onError: (Object _) {},
    );
  }

  void Berhenti() {
    unawaited(_langganan?.cancel());
    _langganan = null;
    saatBerubah = null;
  }

  /// Muat salinan terakhir dari pengaturan lokal, supaya batas stok tetap berlaku setelah aplikasi dibuka ulang
  /// dalam keadaan offline. Salinan yang sudah diambil dari server lebih dulu tidak ditimpa.
  Future<void> Muat() async {
    final generasi = _generasi;
    final teks = await repositori.AmbilPengaturan(KunciPengaturan.stokTersedia);
    if (generasi != _generasi || _salinan != null || teks == null || teks.isEmpty) {
      return;
    }
    final salinan = SalinanStokTersedia.DariJson(teks);
    if (salinan == null) {
      return;
    }
    _salinan = salinan;
    _tersimpan = salinan;
    _disimpanPada = _jam();
    await HitungUlangTerjual();
  }

  /// Hapus salinan (aktivasi ulang ke outlet lain): sampai salinan baru diambil, semua produk tanpa batas.
  Future<void> Kosongkan() async {
    _generasi++;
    _urutanHitung++;
    _salinan = null;
    _terjualLokal = const {};
    _tersimpan = null;
    _disimpanPada = null;
    await repositori.HapusPengaturan(KunciPengaturan.stokTersedia);
    saatBerubah?.call();
  }

  /// Ambil salinan baru dari server. Hanya dipanggil saat online. Offline, galat server, atau jawaban yang bukan daftar
  /// stok = salinan terakhir tetap dipakai dan tidak ada yang dilempar. True bila salinan diperbarui.
  Future<bool> Segarkan() async {
    if (_sedangMenyegarkan) {
      return false;
    }
    _sedangMenyegarkan = true;
    try {
      final mulai = _jam().toUtc();
      final tertunda = await repositoriPenjualan.AmbilUuidPenjualanTertunda();
      final StokTersediaPos hasil;
      try {
        hasil = await klien.AmbilStokTersedia();
      } on GalatApi catch (galat) {
        // Rute tidak ada lagi (server dikembalikan ke versi lama): jangan menahan penjualan dengan salinan usang.
        if (galat.statusHttp == 404 && _salinan != null) {
          await Kosongkan();
        }
        return false;
      } on GalatJaringan {
        return false;
      }
      if (!hasil.lengkap) {
        return false;
      }
      final peta = <String, Kuantitas>{};
      for (final e in hasil.tersedia.entries) {
        final jumlah = UraiKuantitas(e.value);
        // Angka yang tidak terbaca dilewati (produk itu tanpa batas), bukan dianggap nol.
        if (jumlah != null) {
          peta[e.key] = jumlah;
        }
      }
      final baru = SalinanStokTersedia(
        diambilPada: mulai,
        waktuServer: hasil.waktuServer,
        tersedia: Map.unmodifiable(peta),
        belumTercakup: Set.of(tertunda),
      );
      _salinan = baru;
      await _Simpan(baru);
      await HitungUlangTerjual();
      saatBerubah?.call();
      return true;
    } on Object {
      // Fitur bantu yang tidak boleh mengganggu kasir (basis data sedang ditutup, dan sebagainya): salinan terakhir
      // tetap dipakai dan permintaan berikutnya mencoba lagi.
      return false;
    } finally {
      _sedangMenyegarkan = false;
    }
  }

  /// Tulis ke pengaturan hanya bila isinya berubah (Uuid produk, jumlah, penjualan belum tercakup) atau salinan
  /// tersimpan sudah lebih tua dari [selangSimpanUlang].
  Future<void> _Simpan(SalinanStokTersedia salinan) async {
    final sekarang = _jam();
    final lama = _tersimpan;
    final berubah =
        lama == null ||
        !_SamaIsi(lama.tersedia, salinan.tersedia) ||
        lama.belumTercakup.length != salinan.belumTercakup.length ||
        !lama.belumTercakup.containsAll(salinan.belumTercakup);
    final usang = _disimpanPada == null || sekarang.difference(_disimpanPada!) >= selangSimpanUlang;
    if (!berubah && !usang) {
      return;
    }
    await repositori.SimpanPengaturan(KunciPengaturan.stokTersedia, salinan.KeJson());
    _tersimpan = salinan;
    _disimpanPada = sekarang;
  }

  static bool _SamaIsi(Map<String, Kuantitas> a, Map<String, Kuantitas> b) =>
      a.length == b.length && a.entries.every((e) => b[e.key] == e.value);

  /// Hitung ulang jumlah (satuan dasar) penjualan lokal yang belum tercakup salinan, per produk di dalam salinan.
  Future<void> HitungUlangTerjual() async {
    try {
      await _HitungTerjual();
    } on Object {
      // Basis data sedang ditutup atau galat baca: hitungan lama tetap dipakai, pemicu berikutnya mencoba lagi.
    }
  }

  Future<void> _HitungTerjual() async {
    final salinan = _salinan;
    final nomor = ++_urutanHitung;
    final peta = <String, Kuantitas>{};
    if (salinan != null) {
      final baris = await repositoriPenjualan.AmbilDetailBelumTercakup(
        sejak: salinan.diambilPada,
        uuidTertundaSaatAmbil: salinan.belumTercakup,
      );
      for (final b in baris) {
        if (!salinan.tersedia.containsKey(b.uuidProduk)) {
          continue;
        }
        final jumlah = UraiKuantitas(b.jumlah);
        if (jumlah == null) {
          continue;
        }
        peta[b.uuidProduk] = (peta[b.uuidProduk] ?? Kuantitas.Nol()).Tambah(KeDasar(jumlah, b.konversiKeDasar));
      }
    }
    // Perhitungan yang lebih baru (atau Kosongkan) sudah berjalan: hasil ini usang.
    if (nomor != _urutanHitung) {
      return;
    }
    _terjualLokal = peta;
    saatBerubah?.call();
  }

  // Perhitungan sisa ------------------------------------------------------------------------------------------------

  /// Sisa stok menurut salinan dikurangi penjualan lokal yang belum tercakup, tanpa memperhitungkan keranjang.
  /// Null = produk tanpa batas (tidak ada salinan, atau produk di luar daftar). Bisa nol atau negatif.
  Kuantitas? HitungTersediaSalinan(String uuidProduk) {
    final dasar = _salinan?.tersedia[uuidProduk];
    if (dasar == null) {
      return null;
    }
    return dasar.Kurangi(_terjualLokal[uuidProduk] ?? Kuantitas.Nol());
  }

  /// Jumlah [uuidProduk] di [keranjang] dalam satuan dasar; [kecualiBaris] dilewati (baris yang sedang diubah).
  Kuantitas HitungDiKeranjang(String uuidProduk, Keranjang keranjang, KatalogLokal katalog, {String? kecualiBaris}) {
    var total = Kuantitas.Nol();
    for (final b in keranjang.baris) {
      if (b.uuidProduk == uuidProduk && b.uuid != kecualiBaris) {
        total = total.Tambah(HitungJumlahDasar(b.jumlah, b.uuidProduk, b.uuidProdukSatuan, katalog));
      }
    }
    return total;
  }

  /// Sisa yang boleh dijual sekarang: [HitungTersediaSalinan] dikurangi isi [keranjang]. Null = tanpa batas. Bisa nol
  /// atau negatif (keranjang sudah melebihi salinan yang baru turun).
  Kuantitas? HitungTersediaEfektif(
    String uuidProduk,
    Keranjang keranjang,
    KatalogLokal katalog, {
    String? kecualiBaris,
  }) {
    final salinan = HitungTersediaSalinan(uuidProduk);
    if (salinan == null) {
      return null;
    }
    return salinan.Kurangi(HitungDiKeranjang(uuidProduk, keranjang, katalog, kecualiBaris: kecualiBaris));
  }

  /// Produk berbatas dan sisa efektifnya nol atau kurang: tampil "Habis" dan tidak bisa ditambah.
  bool CekHabis(String uuidProduk, Keranjang keranjang, KatalogLokal katalog) {
    final sisa = HitungTersediaEfektif(uuidProduk, keranjang, katalog);
    return sisa != null && sisa.Bandingkan(Kuantitas.Nol()) <= 0;
  }

  /// Jumlah dalam satuan jual `ProdukSatuan` [uuidProdukSatuan] → satuan dasar. Satuan tidak dikenal katalog atau
  /// konversi tak terbaca dianggap 1 (satuan dasar).
  static Kuantitas HitungJumlahDasar(
    Kuantitas jumlah,
    String uuidProduk,
    String? uuidProdukSatuan,
    KatalogLokal katalog,
  ) {
    if (uuidProdukSatuan == null) {
      return jumlah;
    }
    final satuan = katalog.CariProduk(uuidProduk)?.satuan.where((s) => s.uuid == uuidProdukSatuan).firstOrNull;
    return KeDasar(jumlah, satuan?.konversiKeDasar);
  }

  /// [jumlah] × [konversi] (teks desimal; null atau tak terbaca = 1), dibulatkan ke 4 desimal.
  static Kuantitas KeDasar(Kuantitas jumlah, String? konversi) {
    final faktor = konversi == null ? null : Decimal.tryParse(konversi.trim());
    return faktor == null ? jumlah : jumlah.Kali(faktor);
  }

  /// Teks desimal → [Kuantitas]; null bila bukan angka atau lebih dari 4 desimal.
  static Kuantitas? UraiKuantitas(String teks) {
    final nilai = Decimal.tryParse(teks.trim());
    if (nilai == null || nilai.scale > Kuantitas.skala) {
      return null;
    }
    return Kuantitas.DariDesimal(nilai);
  }

  /// `12.0000` → `12`, `2.5000` → `2,5` (tampilan Indonesia, tanpa nol di belakang koma).
  static String FormatJumlah(Kuantitas jumlah) {
    var teks = jumlah.KeDesimal().toString();
    if (teks.contains('.')) {
      teks = teks.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
    }
    return teks.replaceAll('.', ',');
  }
}
