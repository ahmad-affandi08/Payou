import '../Katalog/KatalogLokal.dart';
import 'Keranjang.dart';
import 'KonteksPenjualan.dart';

/// Golongan obat (Apotek §9.5), sama dengan enum `GolonganObat` server. Dasar: PMK 73/2016 (penandaan & pelayanan
/// resep), UU 35/2009 dan PMK 3/2015 (psikotropika & narkotika).
enum GolonganObat {
  Bebas,
  BebasTerbatas,
  Keras,
  Psikotropika,
  Narkotika;

  static GolonganObat? Dari(String? nilai) => GolonganObat.values.where((g) => g.name == nilai).firstOrNull;
}

/// Tanda golongan untuk lencana ubin produk & baris keranjang: teks singkat selalu tampil (warna hanya penegas).
enum NadaObat { Sukses, Info, Bahaya }

/// Golongan & sifat satu produk obat di katalog lokal.
class InfoObat {
  const InfoObat({
    required this.golongan,
    this.obatWajibApotek = false,
    this.prekursor = false,
    bool wajibResep = false,
  }) : _wajibResepServer = wajibResep;

  final GolonganObat golongan;
  final bool obatWajibApotek;
  final bool prekursor;
  final bool _wajibResepServer;

  /// Null = bukan obat (atau katalog dari server lama): tidak pernah memblokir penjualan.
  static InfoObat? Dari(ProdukJual produk) => switch (GolonganObat.Dari(produk.golonganObat)) {
    final golongan? => InfoObat(
      golongan: golongan,
      obatWajibApotek: produk.obatWajibApotek,
      prekursor: produk.prekursor,
      wajibResep: produk.wajibResep,
    ),
    null => null,
  };

  /// OWA hanya bermakna untuk obat keras.
  bool get CekOwa => golongan == GolonganObat.Keras && obatWajibApotek;

  /// Wajib resep dokter: keras bukan OWA, psikotropika, narkotika (sama dengan `GolonganObat::CekWajibResep` server).
  /// Bendera `WajibResep` server ikut dihormati bila lebih ketat.
  bool get CekWajibResep =>
      _wajibResepServer ||
      switch (golongan) {
        GolonganObat.Psikotropika || GolonganObat.Narkotika => true,
        GolonganObat.Keras => !obatWajibApotek,
        _ => false,
      };

  /// Hanya boleh diserahkan apoteker (izin `apotek.obat-keras.jual`): obat keras termasuk OWA, psikotropika,
  /// narkotika.
  bool get CekWajibApoteker =>
      golongan == GolonganObat.Keras || golongan == GolonganObat.Psikotropika || golongan == GolonganObat.Narkotika;

  /// Psikotropika & narkotika: alamat pasien wajib (data pendukung pelaporan SIPNAP).
  bool get CekWajibAlamat => golongan == GolonganObat.Psikotropika || golongan == GolonganObat.Narkotika;

  /// Teks lencana: "Bebas", "B. terbatas", "K", "K | OWA", "P" (psikotropika), "N" (narkotika).
  String get Kode => switch (golongan) {
    GolonganObat.Bebas => 'Bebas',
    GolonganObat.BebasTerbatas => 'B. terbatas',
    GolonganObat.Keras => obatWajibApotek ? 'K | OWA' : 'K',
    GolonganObat.Psikotropika => 'P',
    GolonganObat.Narkotika => 'N',
  };

  /// Arti lengkap untuk pembaca layar & rincian.
  String get Label => [
    switch (golongan) {
      GolonganObat.Bebas => 'Obat bebas',
      GolonganObat.BebasTerbatas => 'Obat bebas terbatas',
      GolonganObat.Keras => obatWajibApotek ? 'Obat keras, obat wajib apotek' : 'Obat keras',
      GolonganObat.Psikotropika => 'Psikotropika',
      GolonganObat.Narkotika => 'Narkotika',
    },
    if (CekWajibResep) 'wajib resep',
    if (prekursor) 'prekursor',
  ].join(', ');

  /// Bebas = hijau, bebas terbatas = biru, keras/psikotropika/narkotika = merah (penandaan kemasan obat Indonesia).
  NadaObat get Nada => switch (golongan) {
    GolonganObat.Bebas => NadaObat.Sukses,
    GolonganObat.BebasTerbatas => NadaObat.Info,
    _ => NadaObat.Bahaya,
  };
}

/// Resep dokter untuk penjualan obat wajib resep (blok `Resep` `Penjualan.Buat`). Data pasien hanya hidup di layar
/// Bayar dan muatan outbox (basis data lokal terenkripsi, K-7); tidak pernah ditulis ke log atau draf keranjang.
class ResepPenjualan {
  const ResepPenjualan({
    required this.nomorResep,
    required this.tanggalResep,
    required this.namaDokter,
    required this.namaPasien,
    this.noSipDokter,
    this.umurPasien,
    this.alamatPasien,
  });

  final String nomorResep;

  /// `YYYY-MM-DD` (tanggal kalender outlet).
  final String tanggalResep;
  final String namaDokter;
  final String? noSipDokter;
  final String namaPasien;
  final String? umurPasien;
  final String? alamatPasien;

  /// Bentuk kontrak `Resep {NomorResep, TanggalResep, NamaDokter, NoSipDokter?, NamaPasien, UmurPasien?,
  /// AlamatPasien?}`; isian opsional yang kosong tidak dikirim.
  Map<String, Object?> KeJson() => {
    'NomorResep': nomorResep,
    'TanggalResep': tanggalResep,
    'NamaDokter': namaDokter,
    'NoSipDokter': ?noSipDokter,
    'NamaPasien': namaPasien,
    'UmurPasien': ?umurPasien,
    'AlamatPasien': ?alamatPasien,
  };

  /// Ringkasan untuk struk & penjualan lokal: tanpa data pasien.
  Map<String, Object?> KeRingkasanStruk() => {
    'NomorResep': nomorResep,
    'TanggalResep': tanggalResep,
    'NamaDokter': namaDokter,
  };
}

/// Syarat penyerahan obat di satu keranjang: baris wajib resep, baris yang hanya boleh diserahkan apoteker, dan apakah
/// alamat pasien wajib (psikotropika/narkotika).
class SyaratApotek {
  const SyaratApotek({this.barisWajibResep = const [], this.barisWajibApoteker = const [], this.wajibAlamat = false});

  static const SyaratApotek kosong = SyaratApotek();

  final List<ItemKeranjang> barisWajibResep;
  final List<ItemKeranjang> barisWajibApoteker;
  final bool wajibAlamat;

  bool get CekWajibResep => barisWajibResep.isNotEmpty;
  bool get CekWajibApoteker => barisWajibApoteker.isNotEmpty;
  bool get CekKosong => !CekWajibResep && !CekWajibApoteker;

  /// Nama produk unik, urut keranjang (untuk pesan kasir).
  static String SebutNama(List<ItemKeranjang> baris) => {for (final b in baris) b.nama}.join(', ');
}

/// Aturan penyerahan obat di kasir (Apotek bagian 2, §9.5). Sama dengan `PencatatResepPenjualan` server, yang tetap
/// menerima pelanggaran sebagai tinjauan; aplikasi kasir memblokirnya lebih dulu. Barang yang bukan obat tidak pernah
/// diblokir.
abstract final class AturanApotek {
  /// Tanggal kalender outlet `YYYY-MM-DD` dari [waktu] (jam dinding [zona] outlet, bukan zona perangkat); batas atas
  /// tanggal resep. Server membandingkan dengan tanggal lokal outlet saat transaksi, bukan tanggal bisnis.
  static String HitungHariIni(DateTime waktu, String zona) {
    final lokal = ZonaWaktuOutlet.KeWaktuOutlet(waktu, zona);
    String Dua(int n) => n.toString().padLeft(2, '0');
    return '${lokal.year.toString().padLeft(4, '0')}-${Dua(lokal.month)}-${Dua(lokal.day)}';
  }

  static const int panjangNomorResep = 50;
  static const int panjangNamaDokter = 100;
  static const int panjangNoSip = 50;
  static const int panjangNamaPasien = 100;
  static const int panjangUmur = 20;
  static const int panjangAlamat = 255;

  static SyaratApotek Periksa(Keranjang keranjang, KatalogLokal? katalog) {
    if (katalog == null) {
      return SyaratApotek.kosong;
    }
    final resep = <ItemKeranjang>[];
    final apoteker = <ItemKeranjang>[];
    var alamat = false;
    for (final b in keranjang.baris) {
      final produk = katalog.CariProduk(b.uuidProduk);
      // Racikan: golongan terkuat komponennya, tidak pernah OWA (sama dengan server).
      final obat = b.racikan != null ? b.racikan!.Obat : (produk == null ? null : InfoObat.Dari(produk));
      if (obat == null) {
        continue;
      }
      if (obat.CekWajibResep) {
        resep.add(b);
        alamat = alamat || obat.CekWajibAlamat;
      }
      if (obat.CekWajibApoteker) {
        apoteker.add(b);
      }
    }
    return SyaratApotek(barisWajibResep: resep, barisWajibApoteker: apoteker, wajibAlamat: alamat);
  }

  /// Rapikan & periksa isian resep. [hariIni] = tanggal kalender outlet `YYYY-MM-DD` (server membandingkan dengan
  /// tanggal lokal outlet saat transaksi). Hasil null + pesan galat bila tidak valid.
  static ({ResepPenjualan? resep, String? galat}) Susun({
    required String nomorResep,
    required String tanggalResep,
    required String namaDokter,
    required String namaPasien,
    required String hariIni,
    required bool wajibAlamat,
    String noSipDokter = '',
    String umurPasien = '',
    String alamatPasien = '',
  }) {
    String? Opsional(String teks) => teks.trim().isEmpty ? null : teks.trim();
    final nomor = nomorResep.trim();
    final dokter = namaDokter.trim();
    final pasien = namaPasien.trim();
    final alamat = Opsional(alamatPasien);
    final galat = switch (true) {
      _ when nomor.isEmpty => 'Isi nomor resep.',
      _ when nomor.length > panjangNomorResep => 'Nomor resep paling panjang $panjangNomorResep karakter.',
      _ when !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(tanggalResep) => 'Pilih tanggal resep.',
      _ when tanggalResep.compareTo(hariIni) > 0 => 'Tanggal resep tidak boleh setelah hari ini.',
      _ when dokter.isEmpty => 'Isi nama dokter penulis resep.',
      _ when dokter.length > panjangNamaDokter => 'Nama dokter paling panjang $panjangNamaDokter karakter.',
      _ when noSipDokter.trim().length > panjangNoSip => 'Nomor SIP dokter paling panjang $panjangNoSip karakter.',
      _ when pasien.isEmpty => 'Isi nama pasien.',
      _ when pasien.length > panjangNamaPasien => 'Nama pasien paling panjang $panjangNamaPasien karakter.',
      _ when umurPasien.trim().length > panjangUmur => 'Umur pasien paling panjang $panjangUmur karakter.',
      _ when wajibAlamat && alamat == null => 'Psikotropika/narkotika wajib mencatat alamat pasien.',
      _ when (alamat?.length ?? 0) > panjangAlamat => 'Alamat pasien paling panjang $panjangAlamat karakter.',
      _ => null,
    };
    if (galat != null) {
      return (resep: null, galat: galat);
    }
    return (
      resep: ResepPenjualan(
        nomorResep: nomor,
        tanggalResep: tanggalResep,
        namaDokter: dokter,
        noSipDokter: Opsional(noSipDokter),
        namaPasien: pasien,
        umurPasien: Opsional(umurPasien),
        alamatPasien: alamat,
      ),
      galat: null,
    );
  }

  /// "Resep: no. 12/RX/X/2026, dr. Andi Wijaya" (struk & riwayat; tanpa data pasien).
  static String SusunBarisStruk(String nomorResep, String namaDokter) {
    final dokter = namaDokter.trim();
    final berGelar = RegExp(r'^(dr|drg)\.?\s', caseSensitive: false).hasMatch(dokter);
    return 'Resep: no. ${nomorResep.trim()}, ${berGelar ? dokter : 'dr. $dokter'}';
  }
}
