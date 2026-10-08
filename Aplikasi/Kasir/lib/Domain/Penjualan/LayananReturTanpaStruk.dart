import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPenjualan.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';
import 'Keranjang.dart';
import 'KonteksPenjualan.dart';
import 'LayananPenjualan.dart';
import 'LayananReturPenjualan.dart';

/// Satu barang yang dikembalikan tanpa struk.
class BarisReturTanpaStruk {
  const BarisReturTanpaStruk({
    required this.produk,
    required this.satuan,
    required this.jumlah,
    this.kondisi = KondisiRetur.layakJual,
  });

  final ProdukJual produk;
  final SatuanJual satuan;
  final Kuantitas jumlah;
  final String kondisi;

  BarisReturTanpaStruk Salin({Kuantitas? jumlah, String? kondisi}) => BarisReturTanpaStruk(
    produk: produk,
    satuan: satuan,
    jumlah: jumlah ?? this.jumlah,
    kondisi: kondisi ?? this.kondisi,
  );
}

/// Nilai retur tanpa struk: per baris (`TotalBaris` mesin kalkulasi) dan total (= `Ringkasan.TotalRefund`).
class HitunganReturTanpaStruk {
  const HitunganReturTanpaStruk({required this.nilaiBaris, required this.total, required this.peringatan});

  final List<Uang> nilaiBaris;
  final Uang total;
  final List<String> peringatan;
}

/// K28 (F-09, PRD v4.01): retur tanpa struk di aplikasi POS, offline. Aturan sama dengan server
/// (`TerimaReturTanpaStrukPos`) agar kasir langsung tahu bila ditolak:
/// - hanya produk berstok biasa (bukan batch/seri, resep, paket, jasa, konsinyasi);
/// - nilai = harga berlaku kanal Bawa pulang (tanpa tier & promo) × jumlah, pajak tarif berlaku, tanpa biaya layanan &
///   pembulatan ([LayananPenjualan.Hitung] `polos`);
/// - wajib PIN penyetuju ber-izin `penjualan.retur.tanpa-struk`; kasir ber-izin `penjualan.buat`/`penjualan.retur`;
/// - refund hanya tukar barang (barang pengganti minimal senilai retur) atau deposit pelanggan terdaftar;
/// - Σ retur tanpa struk perangkat hari ini di atas `BatasReturTanpaStrukHarian` hanya diperingatkan (server menjadikannya
///   tinjauan);
/// - retur + baris + refund + outbox `ReturPenjualan.TanpaStruk` dalam satu transaksi SQLite; penjualan asal kosong.
class LayananReturTanpaStruk {
  LayananReturTanpaStruk({
    required this.repositori,
    required this.repositoriPenjualan,
    required this.layananPenjualan,
    PembuatUlid? ulid,
    DateTime Function()? jam,
  }) : _ulid = ulid ?? PembuatUlid(),
       _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'ReturPenjualan.TanpaStruk';
  static const String jenisProdukStok = 'Stok';

  final RepositoriKasir repositori;
  final RepositoriPenjualan repositoriPenjualan;
  final LayananPenjualan layananPenjualan;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Alasan [produk] tidak bisa diretur tanpa struk (null = bisa).
  static String? AmbilAlasanTidakBisa(ProdukJual produk) {
    if (produk.bernomorSeri || produk.berBatch) {
      return '"${produk.nama}" ber-batch atau bernomor seri sehingga hanya bisa diretur dengan struk.';
    }
    if (produk.hargaTerbuka || produk.kelompokPilihan.any((k) => k.minimal >= 1)) {
      return '"${produk.nama}" butuh harga atau pilihan khusus saat dijual sehingga hanya bisa diretur dengan struk.';
    }
    if (produk.jenis != jenisProdukStok) {
      return '"${produk.nama}" bukan barang berstok biasa sehingga tidak bisa diretur tanpa struk.';
    }
    return null;
  }

  /// Satuan baris baru: satuan jual bawaan produk (Uuid `ProdukSatuan`, sama dengan yang dikirim ke server).
  static SatuanJual? AmbilSatuan(ProdukJual produk) => produk.AmbilSatuanBawaan();

  HitunganReturTanpaStruk Hitung(List<BarisReturTanpaStruk> baris, KatalogLokal katalog, KonteksPenjualan k) {
    if (baris.isEmpty) {
      return HitunganReturTanpaStruk(nilaiBaris: const [], total: Uang.Nol(), peringatan: const []);
    }
    final keranjang = Keranjang.kosong.Salin(
      baris: [
        for (final b in baris) layananPenjualan.BuatBaris(katalog, k, b.produk, satuan: b.satuan, jumlah: b.jumlah),
      ],
    );
    final hitungan = layananPenjualan.Hitung(keranjang, k, polos: true);
    return HitunganReturTanpaStruk(
      nilaiBaris: [for (final h in hitungan.hasil.baris) h.totalBaris],
      total: hitungan.hasil.totalAkhir,
      peringatan: hitungan.peringatan,
    );
  }

  Future<Uang> AmbilBatasHarian() async => Uang.Dari(
    await repositori.AmbilPengaturan(KunciPengaturan.batasReturTanpaStrukHarian) ??
        DataAwal.batasReturTanpaStrukHarianBawaan,
  );

  /// Σ retur tanpa struk perangkat ini pada tanggal bisnis hari ini.
  Future<Uang> HitungHariIni(KonteksPenjualan k) =>
      repositoriPenjualan.HitungReturTanpaStrukHari(k.HitungTanggalBisnis(_jam().toUtc()));

  static void Validasi(List<BarisReturTanpaStruk> baris) {
    if (baris.isEmpty) {
      throw const GalatKasir('BarisKosong', 'Tambahkan minimal satu barang yang dikembalikan.');
    }
    for (final b in baris) {
      final alasan = AmbilAlasanTidakBisa(b.produk);
      if (alasan != null) {
        throw GalatKasir('ReturTanpaStrukButuhStruk', alasan);
      }
      if (b.jumlah.BernilaiNol() || b.jumlah.BernilaiNegatif()) {
        throw GalatKasir('JumlahTidakValid', 'Jumlah "${b.produk.nama}" harus lebih dari 0.');
      }
      final d = b.jumlah.KeDesimal();
      if (!b.satuan.bolehDesimal && d != d.truncate()) {
        throw GalatKasir('JumlahTidakValid', 'Jumlah "${b.produk.nama}" harus bilangan bulat.');
      }
      if (b.kondisi != KondisiRetur.layakJual && b.kondisi != KondisiRetur.rusak) {
        throw GalatKasir('KondisiTidakValid', 'Pilih kondisi barang "${b.produk.nama}": layak jual atau rusak.');
      }
    }
  }

  /// Simpan retur tanpa struk. [metode] = metode `Tukar` atau `Deposit`; [uuidPelanggan] wajib untuk deposit.
  /// [penyetuju] = staf yang lolos PIN ber-izin `penjualan.retur.tanpa-struk`.
  Future<ReturTersimpan> Simpan({
    required List<BarisReturTanpaStruk> baris,
    required String alasan,
    required StafLokal kasir,
    required StafLokal penyetuju,
    required BarisMetodePembayaran metode,
    required KatalogLokal katalog,
    required KonteksPenjualan k,
    String? uuidPelanggan,
    String? uuidRetur,
  }) async {
    final shift = await repositori.AmbilShiftAktif();
    if (shift == null) {
      throw const GalatKasir('ShiftTidakDitemukan', 'Belum ada shift terbuka. Buka shift dulu sebelum retur.');
    }
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat) && !kasir.PunyaIzin(IzinKasir.penjualanRetur)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin melayani retur.');
    }
    if (!penyetuju.PunyaIzin(IzinKasir.penjualanReturTanpaStruk)) {
      throw GalatKasir('PenyetujuTidakBerwenang', '${penyetuju.nama} tidak punya izin menyetujui retur tanpa struk.');
    }
    final kodeOutlet = k.kodeOutlet ?? '';
    final kodePerangkat = k.kodePerangkat ?? '';
    if (kodeOutlet.isEmpty || kodePerangkat.isEmpty) {
      throw const GalatKasir(
        'DataAwalBelumLengkap',
        'Kode outlet atau perangkat belum ada di perangkat ini. Sambungkan ke internet agar data terbaru terunduh.',
      );
    }
    Validasi(baris);
    final alasanRapi = alasan.trim();
    if (alasanRapi.runes.length < LayananReturPenjualan.panjangAlasanMinimal) {
      throw const GalatKasir('AlasanDiperlukan', 'Tulis alasan retur minimal 5 huruf.');
    }
    if (metode.Jenis != JenisMetodeBayar.tukar && metode.Jenis != JenisMetodeBayar.deposit) {
      throw const GalatKasir(
        'MetodeBayarBelumDidukung',
        'Retur tanpa struk hanya bisa ditukar barang atau masuk deposit pelanggan, tidak dikembalikan uang.',
      );
    }
    if (metode.Jenis == JenisMetodeBayar.deposit && uuidPelanggan == null) {
      throw const GalatKasir('DepositTanpaPelanggan', 'Pilih pelanggan penerima deposit.');
    }

    final hitungan = Hitung(baris, katalog, k);
    final total = hitungan.total;
    final sekarang = _jam().toUtc();
    final tanggalBisnis = k.HitungTanggalBisnis(sekarang);
    final yymmdd = '${tanggalBisnis.substring(2, 4)}${tanggalBisnis.substring(5, 7)}${tanggalBisnis.substring(8, 10)}';
    final uuid = uuidRetur ?? _ulid.Buat();
    final uuidBaris = [for (final _ in baris) _ulid.Buat()];
    final uuidRefund = _ulid.Buat();
    final metodeRefund = metode.Jenis == JenisMetodeBayar.deposit ? MetodeRefundRetur.deposit : MetodeRefundRetur.tukar;

    final dokumen = await repositoriPenjualan.SimpanRetur(
      kodePerangkat: kodePerangkat,
      tanggal: yymmdd,
      sekarang: sekarang,
      susun: (urut) {
        final nomor = 'RJ/$kodeOutlet/$yymmdd/$kodePerangkat-${urut.toString().padLeft(4, '0')}';
        return DokumenRetur(
          retur: ReturPenjualanCompanion.insert(
            Uuid: uuid,
            Nomor: nomor,
            // Tanpa struk: tidak ada penjualan asal (kosong).
            UuidPenjualanAsal: '',
            NomorPenjualanAsal: '',
            UuidShift: shift.Uuid,
            UuidPengguna: kasir.uuid,
            NamaKasir: kasir.nama,
            UuidPenyetuju: penyetuju.uuid,
            Alasan: alasanRapi,
            DibuatPada: sekarang,
            TanggalBisnis: tanggalBisnis,
            MetodeRefund: metodeRefund,
            TotalRefund: total.KeString(),
            RefundTunai: Uang.Nol().KeString(),
          ),
          detail: [
            for (var i = 0; i < baris.length; i++)
              ReturPenjualanDetailCompanion.insert(
                Uuid: uuidBaris[i],
                UuidReturPenjualan: uuid,
                UuidPenjualanDetail: '',
                NamaProduk: baris[i].produk.nama,
                SimbolSatuan: baris[i].satuan.nama,
                Jumlah: baris[i].jumlah.KeString(),
                Kondisi: baris[i].kondisi,
                NilaiBaris: hitungan.nilaiBaris[i].KeString(),
              ),
          ],
          pembayaran: [
            ReturPenjualanPembayaranCompanion.insert(
              Uuid: uuidRefund,
              UuidReturPenjualan: uuid,
              UuidMetodePembayaran: metode.Uuid,
              Jenis: metode.Jenis,
              NamaMetode: metode.Nama,
              Jumlah: total.KeString(),
            ),
          ],
          outbox: ItemOutbox(
            jenis: jenisOutbox,
            uuid: uuid,
            data: BuatDataOutbox(
              uuidShift: shift.Uuid,
              uuidPengguna: kasir.uuid,
              uuidPenyetuju: penyetuju.uuid,
              uuidPelanggan: uuidPelanggan,
              nomor: nomor,
              alasan: alasanRapi,
              dibuatPada: sekarang,
              baris: [
                for (var i = 0; i < baris.length; i++)
                  (
                    uuid: uuidBaris[i],
                    uuidProduk: baris[i].produk.uuid,
                    uuidProdukSatuan: baris[i].satuan.uuid,
                    jumlah: baris[i].jumlah,
                    kondisi: baris[i].kondisi,
                  ),
              ],
              refund: [(uuid: uuidRefund, uuidMetodePembayaran: metode.Uuid, jumlah: total)],
              totalRefund: total,
            ),
          ),
        );
      },
    );

    return ReturTersimpan(
      uuid: uuid,
      nomor: dokumen.retur.Nomor.value,
      totalRefund: total,
      refundTunai: Uang.Nol(),
      refundTransfer: Uang.Nol(),
    );
  }

  /// Data item outbox `ReturPenjualan.TanpaStruk` persis kontrak server (uang string 2 desimal, jumlah 4 desimal, waktu
  /// ISO UTC).
  static Map<String, Object?> BuatDataOutbox({
    required String uuidShift,
    required String uuidPengguna,
    required String uuidPenyetuju,
    required String? uuidPelanggan,
    required String nomor,
    required String alasan,
    required DateTime dibuatPada,
    required List<({String uuid, String uuidProduk, String uuidProdukSatuan, Kuantitas jumlah, String kondisi})> baris,
    required List<({String uuid, String uuidMetodePembayaran, Uang jumlah})> refund,
    required Uang totalRefund,
  }) => {
    'UuidShift': uuidShift,
    'UuidPengguna': uuidPengguna,
    'UuidPenyetuju': uuidPenyetuju,
    'UuidPelanggan': ?uuidPelanggan,
    'Nomor': nomor,
    'Alasan': alasan,
    'DibuatPada': dibuatPada.toUtc().toIso8601String(),
    'Baris': [
      for (final b in baris)
        {
          'Uuid': b.uuid,
          'UuidProduk': b.uuidProduk,
          'UuidProdukSatuan': b.uuidProdukSatuan,
          'Jumlah': b.jumlah.KeString(),
          'Kondisi': b.kondisi,
        },
    ],
    'Refund': [
      for (final r in refund)
        {'Uuid': r.uuid, 'UuidMetodePembayaran': r.uuidMetodePembayaran, 'Jumlah': r.jumlah.KeString()},
    ],
    'Ringkasan': {'TotalRefund': totalRefund.KeString()},
  };
}
