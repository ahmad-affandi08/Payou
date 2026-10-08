import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPenjualan.dart';
import '../GalatKasir.dart';
import '../Sesi/StafLokal.dart';
import 'KonteksPenjualan.dart';

/// Pengembalian uang saat void: mengikuti pembayaran asal (PRD "Rincian F-09 fase 1").
class RefundVoid {
  const RefundVoid({required this.tunai, required this.nonTunai, required this.metodeNonTunai});

  /// Tunai bersih (diterima − kembalian) yang dikembalikan dari laci shift.
  final Uang tunai;

  /// Non-tunai yang dikembalikan manual (BR-09.2).
  final Uang nonTunai;

  /// Nama metode non-tunai asal (untuk petunjuk kasir).
  final List<String> metodeNonTunai;
}

/// Void transaksi di aplikasi POS (Rincian F-09 fase 1 & keputusan implementasi v1.47), berlaku offline. Aturan sama
/// dengan server agar kasir langsung tahu bila ditolak:
/// - hanya penjualan `Lunas` dari shift yang masih terbuka di perangkat ini (`VoidTidakDiizinkan`);
/// - pelaku ber-izin `penjualan.buat` atau `penjualan.void`; penyetuju ber-izin `penjualan.void` (kasir yang sendiri
///   ber-izin menyetujui dirinya);
/// - alasan minimal 5 karakter;
/// - status lokal `Void` + `VoidPenjualan` + outbox `Penjualan.Void` dalam satu transaksi SQLite. Outbox FIFO mengirim
///   void setelah `Penjualan.Buat`-nya.
class LayananVoidPenjualan {
  LayananVoidPenjualan({
    required this.repositori,
    required this.repositoriPenjualan,
    PembuatUlid? ulid,
    DateTime Function()? jam,
  }) : _ulid = ulid ?? PembuatUlid(),
       _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'Penjualan.Void';
  static const int panjangAlasanMinimal = 5;

  final RepositoriKasir repositori;
  final RepositoriPenjualan repositoriPenjualan;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Apakah [penjualan] bisa di-void di perangkat ini sekarang (tanpa memeriksa pelaku).
  static bool CekBisaDivoid(BarisPenjualan penjualan, BarisShift? shiftAktif) =>
      shiftAktif != null &&
      shiftAktif.Status == StatusShiftLokal.terbuka &&
      penjualan.UuidShift == shiftAktif.Uuid &&
      penjualan.Status == StatusPenjualanLokal.lunas;

  /// Pembayaran yang dikembalikan lewat dokumennya sendiri, bukan uang yang harus direfund ke pembeli (sama dengan
  /// `BUKAN_REFUND` di server): piutang dibatalkan, saldo deposit kembali, uang muka tetap melekat di pesanan, nilai
  /// tukar barang bisa dipakai lagi, poin & voucher bukan uang yang diterima.
  static const Set<String> bukanRefund = {'Tempo', 'Deposit', 'UangMuka', 'Tukar', 'Poin', 'Voucher'};

  /// Refund tunai bersih & non-tunai dari pembayaran asal (tunai negatif → 0, sama dengan server).
  static RefundVoid HitungRefund(BarisPenjualan penjualan, List<BarisPenjualanPembayaran> pembayaran) {
    var tunai = Uang.Nol();
    var nonTunai = Uang.Nol();
    final metode = <String>[];
    for (final b in pembayaran) {
      if (bukanRefund.contains(b.Jenis)) {
        continue;
      }
      if (b.Jenis == JenisMetodeBayar.tunai) {
        tunai = tunai.Tambah(Uang.Dari(b.Jumlah)).Kurangi(Uang.Dari(penjualan.Kembalian));
      } else {
        nonTunai = nonTunai.Tambah(Uang.Dari(b.Jumlah));
        if (!metode.contains(b.NamaMetode)) {
          metode.add(b.NamaMetode);
        }
      }
    }
    return RefundVoid(tunai: tunai.BernilaiNegatif() ? Uang.Nol() : tunai, nonTunai: nonTunai, metodeNonTunai: metode);
  }

  /// Penyetuju efektif: penyetuju yang lolos PIN, atau kasir sendiri bila ia ber-izin `penjualan.void`.
  static StafLokal? AmbilPenyetujuEfektif(StafLokal kasir, StafLokal? penyetuju) =>
      penyetuju ?? (kasir.PunyaIzin(IzinKasir.penjualanVoid) ? kasir : null);

  /// Pemeriksaan sebelum lembar void dibuka / sebelum PIN diminta.
  Future<({BarisPenjualan penjualan, RefundVoid refund})> Periksa(String uuidPenjualan, StafLokal kasir) async {
    final penjualan = await repositoriPenjualan.CariPenjualan(uuidPenjualan);
    if (penjualan == null) {
      throw const GalatKasir('PenjualanTidakDitemukan', 'Transaksi ini tidak ditemukan di perangkat ini.');
    }
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat) && !kasir.PunyaIzin(IzinKasir.penjualanVoid)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin melayani void.');
    }
    if (penjualan.Status == StatusPenjualanLokal.divoid) {
      throw GalatKasir('SudahDivoid', 'Transaksi ${penjualan.Nomor} sudah di-void.');
    }
    if (penjualan.Status != StatusPenjualanLokal.lunas) {
      throw GalatKasir('VoidTidakDiizinkan', 'Transaksi ${penjualan.Nomor} sudah diretur sehingga tidak bisa di-void.');
    }
    final shift = await repositori.AmbilShiftAktif();
    if (!CekBisaDivoid(penjualan, shift)) {
      throw GalatKasir(
        'VoidTidakDiizinkan',
        'Transaksi ${penjualan.Nomor} dari shift yang sudah ditutup. Gunakan retur untuk mengembalikan barang.',
      );
    }
    return (
      penjualan: penjualan,
      refund: HitungRefund(penjualan, await repositoriPenjualan.AmbilPembayaran(uuidPenjualan)),
    );
  }

  /// Void [uuidPenjualan]. [penyetuju] = staf yang lolos PIN; null → kasir sendiri bila ber-izin `penjualan.void`.
  Future<RefundVoid> Void({
    required String uuidPenjualan,
    required StafLokal kasir,
    required String alasan,
    StafLokal? penyetuju,
  }) async {
    final periksa = await Periksa(uuidPenjualan, kasir);
    final alasanRapi = alasan.trim();
    if (alasanRapi.runes.length < panjangAlasanMinimal) {
      throw const GalatKasir('AlasanDiperlukan', 'Tulis alasan void minimal 5 huruf.');
    }
    final efektif = AmbilPenyetujuEfektif(kasir, penyetuju);
    if (efektif == null) {
      throw const GalatKasir('PersetujuanDiperlukan', 'Void wajib disetujui supervisor dengan PIN.');
    }
    if (!efektif.PunyaIzin(IzinKasir.penjualanVoid)) {
      throw GalatKasir('PenyetujuTidakBerwenang', '${efektif.nama} tidak punya izin menyetujui void.');
    }

    final sekarang = _jam().toUtc();
    final uuid = _ulid.Buat();
    final p = periksa.penjualan;
    final refund = periksa.refund;
    try {
      await repositoriPenjualan.SimpanVoid(
        VoidPenjualanCompanion.insert(
          Uuid: uuid,
          UuidPenjualan: p.Uuid,
          UuidShift: p.UuidShift,
          UuidPengguna: kasir.uuid,
          NamaPengguna: kasir.nama,
          UuidPenyetuju: efektif.uuid,
          NamaPenyetuju: efektif.nama,
          Alasan: alasanRapi,
          DivoidPada: sekarang,
          Nominal: p.TotalAkhir,
          RefundTunai: refund.tunai.KeString(),
          RefundNonTunai: refund.nonTunai.KeString(),
        ),
        ItemOutbox(
          jenis: jenisOutbox,
          uuid: uuid,
          data: BuatDataOutbox(
            uuidPenjualan: p.Uuid,
            uuidPengguna: kasir.uuid,
            uuidPenyetuju: efektif.uuid,
            alasan: alasanRapi,
            divoidPada: sekarang,
          ),
        ),
        sekarang,
      );
    } on StateError {
      throw GalatKasir('VoidTidakDiizinkan', 'Transaksi ${p.Nomor} sudah berubah status. Muat ulang riwayat.');
    }
    return refund;
  }

  /// Data item outbox `Penjualan.Void` persis kontrak Rincian F-09 fase 1.
  static Map<String, Object?> BuatDataOutbox({
    required String uuidPenjualan,
    required String uuidPengguna,
    required String uuidPenyetuju,
    required String alasan,
    required DateTime divoidPada,
  }) => {
    'UuidPenjualan': uuidPenjualan,
    'UuidPengguna': uuidPengguna,
    'UuidPenyetuju': uuidPenyetuju,
    'Alasan': alasan,
    'DivoidPada': divoidPada.toUtc().toIso8601String(),
  };
}
