import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPreOrder.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';
import 'Keranjang.dart';
import 'KonteksPenjualan.dart';
import 'LayananPenjualan.dart';

/// Pre-order yang tersimpan di perangkat. Data cetak (pelanggan, kasir, metode DP, baris) untuk bukti uang muka
/// (cetak struk bagian 4a) hanya ada di memori setelah pre-order dibuat.
class PreOrderTersimpan {
  const PreOrderTersimpan({
    required this.uuid,
    required this.nomor,
    required this.uangMuka,
    required this.totalPesanan,
    required this.tanggalAmbil,
    this.namaPelanggan = '',
    this.namaKasir = '',
    this.namaMetode = '',
    this.uangMukaTunai = false,
    this.dibuatPada,
    this.catatan,
    this.baris = const [],
  });

  final String uuid;
  final String nomor;
  final Uang uangMuka;
  final Uang totalPesanan;

  /// `YYYY-MM-DD`.
  final String tanggalAmbil;
  final String namaPelanggan;
  final String namaKasir;
  final String namaMetode;

  /// Uang muka dibayar tunai (laci dibuka pada cetak otomatis pertama).
  final bool uangMukaTunai;
  final DateTime? dibuatPada;
  final String? catatan;
  final List<BarisBuktiPreOrder> baris;
}

/// Satu baris barang di bukti uang muka pre-order: nilai = total baris setelah diskon & pajak.
class BarisBuktiPreOrder {
  const BarisBuktiPreOrder({required this.nama, required this.jumlah, this.satuan, required this.nilai});

  final String nama;
  final Kuantitas jumlah;
  final String? satuan;
  final Uang nilai;
}

/// Pre-order + uang muka di aplikasi kasir (F-12 bagian 2, SLS-02):
/// - **Buat** (offline): keranjang ber-pelanggan menjadi pre-order `SO/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`
///   dengan satu pembayaran DP (tunai/QRIS/EDC/transfer/e-wallet, tanpa kembalian, > 0 dan ≤ total) dan tanggal ambil ≥
///   tanggal bisnis. Baris lokal (kas shift) + outbox `PesananPenjualan.Buat` dalam satu transaksi SQLite; stok & pendapatan
///   baru terjadi saat diambil. Voucher, tukar poin, dan pesanan meja belum didukung di pre-order.
/// - **Cari** (online): pre-order yang siap diambil di outlet ini; offline → `PerluOnline`.
/// - **Muat ke keranjang**: baris dengan harga saat dipesan + pelanggan + konteks DP (dipakai lewat metode Uang Muka).
class LayananPreOrder {
  LayananPreOrder({
    required this.klien,
    required this.repositori,
    required this.repositoriPreOrder,
    required this.penjualan,
    PembuatUlid? ulid,
    DateTime Function()? jam,
  }) : _ulid = ulid ?? PembuatUlid(),
       _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'PesananPenjualan.Buat';
  static const int panjangCatatanMaksimal = 500;

  final KlienPos klien;
  final RepositoriKasir repositori;
  final RepositoriPreOrder repositoriPreOrder;
  final LayananPenjualan penjualan;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  Future<PreOrderTersimpan> Buat({
    required Keranjang keranjang,
    required KonteksPenjualan k,
    required StafLokal kasir,
    required String tanggalAmbil,
    required Uang uangMuka,
    required BarisMetodePembayaran metode,
    String? referensi,
    String? catatan,
  }) async {
    final shift = await repositori.AmbilShiftAktif();
    if (shift == null) {
      throw const GalatKasir(
        'ShiftTidakDitemukan',
        'Belum ada shift terbuka. Buka shift dulu sebelum menerima pesanan.',
      );
    }
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin berjualan.');
    }
    final pelanggan = keranjang.pelanggan;
    if (pelanggan == null) {
      throw const GalatKasir('PelangganWajib', 'Pilih pelanggan dulu. Pre-order wajib atas nama pelanggan.');
    }
    if (keranjang.CekKosong) {
      throw const GalatKasir('KeranjangKosong', 'Keranjang masih kosong. Tambahkan barang pesanan dulu.');
    }
    if (keranjang.pesananMeja != null || keranjang.voucher != null || keranjang.tukarPoin != null) {
      throw const GalatKasir(
        'PreOrderBelumDidukung',
        'Pre-order belum bisa dari pesanan meja, dengan voucher, atau tukar poin. Hapus dulu, lalu coba lagi.',
      );
    }
    // Server mencatat TotalPesanan apa adanya, tetapi saat diambil penjualan dihitung ulang dari harga baris saat
    // dipesan tanpa diskon manual dan ongkir. Total yang sudah dipotong atau ditambah di sini akan berbeda dari yang
    // ditagih, jadi ditolak sampai server ikut menerima rincian itu.
    if (keranjang.diskonPesanan != null ||
        keranjang.baris.any((b) => b.diskon != null) ||
        keranjang.biayaKirim.Bandingkan(Uang.Nol()) > 0) {
      throw const GalatKasir(
        'PreOrderBelumDidukung',
        'Pre-order belum bisa dengan diskon manual atau ongkir. Hapus dulu diskon/ongkirnya, lalu coba lagi.',
      );
    }
    if (!JenisMetodeBayar.bolehUangMuka.contains(metode.Jenis)) {
      throw GalatKasir('MetodeBayarBelumDidukung', 'Uang muka tidak bisa dibayar dengan ${metode.Nama}.');
    }
    final kodeOutlet = k.kodeOutlet ?? '';
    final kodePerangkat = k.kodePerangkat ?? '';
    if (kodeOutlet.isEmpty || kodePerangkat.isEmpty) {
      throw const GalatKasir(
        'DataAwalBelumLengkap',
        'Kode outlet atau perangkat belum ada di perangkat ini. Sambungkan ke internet agar data terbaru terunduh.',
      );
    }
    final hitungan = penjualan.Hitung(keranjang, k);
    final total = hitungan.hasil.totalAkhir;
    if (uangMuka.Bandingkan(Uang.Nol()) <= 0) {
      throw const GalatKasir('UangMukaWajib', 'Isi uang muka lebih dari Rp 0.');
    }
    if (uangMuka.Bandingkan(total) > 0) {
      throw GalatKasir(
        'UangMukaMelebihiTotal',
        'Uang muka tidak boleh melebihi total pesanan ${total.FormatRupiah()}.',
      );
    }
    if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(tanggalAmbil) || tanggalAmbil.compareTo(hitungan.tanggalBisnis) < 0) {
      throw const GalatKasir('TanggalAmbilTidakValid', 'Tanggal ambil tidak boleh sebelum hari ini.');
    }
    final rapiCatatan = catatan?.trim();
    if ((rapiCatatan?.runes.length ?? 0) > panjangCatatanMaksimal) {
      throw const GalatKasir('CatatanTerlaluPanjang', 'Catatan paling panjang 500 karakter.');
    }

    final sekarang = _jam().toUtc();
    final t = hitungan.tanggalBisnis;
    final yymmdd = '${t.substring(2, 4)}${t.substring(5, 7)}${t.substring(8, 10)}';
    final uuid = _ulid.Buat();
    final uuidBayar = _ulid.Buat();
    final rapiReferensi = referensi?.trim();
    late String nomor;
    await repositoriPreOrder.Simpan(
      kodePerangkat: kodePerangkat,
      tanggal: yymmdd,
      sekarang: sekarang,
      susun: (urut) {
        nomor = 'SO/$kodeOutlet/$yymmdd/$kodePerangkat-${urut.toString().padLeft(4, '0')}';
        return (
          pesanan: PesananPenjualanLokalCompanion.insert(
            Uuid: uuid,
            UuidShift: shift.Uuid,
            Nomor: nomor,
            NamaPelanggan: pelanggan.nama,
            TanggalAmbil: tanggalAmbil,
            TotalPesanan: total.KeString(),
            UangMuka: uangMuka.KeString(),
            UuidMetodePembayaran: metode.Uuid,
            JenisMetode: metode.Jenis,
            NamaMetode: metode.Nama,
            DibuatPada: sekarang,
          ),
          outbox: ItemOutbox(
            jenis: jenisOutbox,
            uuid: uuid,
            data: {
              'UuidShift': shift.Uuid,
              'UuidPengguna': kasir.uuid,
              'UuidPelanggan': pelanggan.uuid,
              'Nomor': nomor,
              'DipesanPada': sekarang.toIso8601String(),
              'TanggalAmbil': tanggalAmbil,
              'Catatan': rapiCatatan == null || rapiCatatan.isEmpty ? null : rapiCatatan,
              'TotalPesanan': total.KeString(),
              'Baris': [
                for (final b in keranjang.baris)
                  {
                    'Uuid': b.uuid,
                    'UuidProduk': b.uuidProduk,
                    'UuidProdukSatuan': b.uuidProdukSatuan,
                    'Jumlah': b.jumlah.KeString(),
                    'HargaSatuan': b.hargaSatuan.KeString(),
                    'HargaPilihan': b.AmbilHargaPilihan().KeString(),
                    'Pilihan': [for (final p in b.pilihan) p.KeJson()],
                    'Catatan': b.catatan,
                  },
              ],
              'Pembayaran': [
                {
                  'Uuid': uuidBayar,
                  'UuidMetodePembayaran': metode.Uuid,
                  'Jumlah': uangMuka.KeString(),
                  'Referensi': rapiReferensi == null || rapiReferensi.isEmpty ? null : rapiReferensi,
                },
              ],
            },
          ),
        );
      },
    );
    return PreOrderTersimpan(
      uuid: uuid,
      nomor: nomor,
      uangMuka: uangMuka,
      totalPesanan: total,
      tanggalAmbil: tanggalAmbil,
      namaPelanggan: pelanggan.nama,
      namaKasir: kasir.nama,
      namaMetode: metode.Nama,
      uangMukaTunai: metode.Jenis == JenisMetodeBayar.tunai,
      dibuatPada: sekarang,
      catatan: rapiCatatan == null || rapiCatatan.isEmpty ? null : rapiCatatan,
      baris: [
        for (var i = 0; i < keranjang.baris.length; i++)
          BarisBuktiPreOrder(
            nama: keranjang.baris[i].nama,
            jumlah: keranjang.baris[i].jumlah,
            satuan: keranjang.baris[i].namaSatuan,
            nilai: hitungan.hasil.baris[i].totalBaris,
          ),
      ],
    );
  }

  Future<HasilCariPesananPenjualan> Cari(String kata) async {
    try {
      return await klien.CariPesananPenjualan(kata);
    } on GalatJaringan {
      throw const GalatKasir(
        'PerluOnline',
        'Mengambil pre-order perlu koneksi internet. Coba lagi saat perangkat online.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  /// Keranjang pengambilan: harga saat dipesan, pelanggan pre-order, dan DP yang tersisa.
  Keranjang MuatKeKeranjang(
    PesananPenjualanPos pesanan,
    HasilCariPesananPenjualan hasil,
    KatalogLokal katalog,
    KonteksPenjualan k,
  ) {
    final uuidMetode = hasil.uuidMetodeUangMuka;
    if (uuidMetode == null) {
      throw const GalatKasir('DataAwalBelumLengkap', 'Metode uang muka belum tersedia di server. Coba lagi nanti.');
    }
    final pelanggan = pesanan.pelanggan;
    final baris = <ItemKeranjang>[];
    for (final b in pesanan.baris) {
      final produk = katalog.CariProduk(b.uuidProduk);
      if (produk == null) {
        throw GalatKasir(
          'ProdukTidakDikenal',
          '"${b.namaProduk}" belum ada di katalog perangkat ini. Perbarui katalog, lalu coba lagi.',
        );
      }
      final satuan = produk.satuan.where((s) => s.uuid == b.uuidProdukSatuan).firstOrNull ?? produk.AmbilSatuanBawaan();
      final pilihan = [
        for (final p in b.pilihan)
          PilihanTerpilih(uuid: '${p['UuidPilihan']}', nama: '${p['Nama']}', harga: Uang.Dari('${p['Harga']}')),
      ];
      final item = penjualan.BuatBaris(katalog, k, produk, satuan: satuan, pilihan: pilihan, catatan: b.catatan);
      baris.add(item.Salin(jumlah: Kuantitas.Dari(b.jumlah), hargaSatuan: Uang.Dari(b.hargaSatuan)));
    }
    return Keranjang(
      baris: baris,
      catatan: pesanan.catatan,
      pelanggan: pelanggan == null
          ? null
          : PelangganTerpilih(
              uuid: '${pelanggan['Uuid']}',
              nama: '${pelanggan['Nama']}',
              noHpSamar: '${pelanggan['NoHp'] ?? ''}',
              kodeTier: pelanggan['KodeTier'] as String?,
              namaTier: pelanggan['NamaTier'] as String?,
            ),
      praPesan: PraPesananKeranjang(
        uuid: pesanan.uuid,
        nomor: pesanan.nomor,
        sisaUangMuka: Uang.Dari(pesanan.sisaUangMuka),
        uuidMetode: uuidMetode,
        namaMetode: hasil.namaMetodeUangMuka ?? 'Uang muka (DP)',
      ),
    );
  }

  /// Metode sistem Uang Muka sebagai baris metode pembayaran (tidak tersimpan di katalog perangkat).
  static BarisMetodePembayaran MetodeUangMuka(PraPesananKeranjang praPesan) => BarisMetodePembayaran(
    Uuid: praPesan.uuidMetode,
    Jenis: JenisMetodeBayar.uangMuka,
    Nama: praPesan.namaMetode,
    AdaGambarQris: false,
    Urutan: 0,
  );
}
