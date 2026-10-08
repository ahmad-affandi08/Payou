import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPenjualan.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';
import 'KonteksPenjualan.dart';
import 'PenghitungNilaiRetur.dart';

/// Kondisi barang retur (sama dengan server `KondisiBarangRetur`).
abstract final class KondisiRetur {
  static const String layakJual = 'LayakJual';
  static const String rusak = 'Rusak';

  static String AmbilLabel(String kondisi) => kondisi == rusak ? 'Rusak' : 'Layak jual';
}

/// Metode refund retur (sama dengan server `MetodeRefund`).
abstract final class MetodeRefundRetur {
  static const String tunai = 'Tunai';
  static const String transfer = 'Transfer';
  static const String campuran = 'Campuran';

  /// F-12: retur penjualan tempo memotong sisa piutang lebih dulu.
  static const String piutang = 'Piutang';

  /// F-16d: refund masuk saldo deposit pelanggan (penjualan berpelanggan).
  static const String deposit = 'Deposit';

  /// K-11: nilai retur dipakai membayar barang pengganti.
  static const String tukar = 'Tukar';
}

/// Satu baris penjualan asal yang dipilih untuk diretur.
class PilihanReturBaris {
  const PilihanReturBaris({required this.baris, required this.jumlah, this.kondisi = KondisiRetur.layakJual});

  final BarisPenjualanCariPos baris;
  final Kuantitas jumlah;
  final String kondisi;
}

/// Hasil simpan retur untuk layar sukses.
class ReturTersimpan {
  ReturTersimpan({
    required this.uuid,
    required this.nomor,
    required this.totalRefund,
    required this.refundTunai,
    required this.refundTransfer,
    this.namaMetodeTransfer,
    Uang? potongPiutang,
  }) : potongPiutang = potongPiutang ?? Uang.Nol();

  final String uuid;
  final String nomor;
  final Uang totalRefund;
  final Uang refundTunai;
  final Uang refundTransfer;
  final String? namaMetodeTransfer;

  /// F-12: bagian retur yang mengurangi piutang pelanggan (tidak ada uang keluar).
  final Uang potongPiutang;
}

/// Retur penjualan di aplikasi POS (Rincian F-09 fase 1 & keputusan implementasi v1.47):
/// - struk asal dicari online (`penjualan/cari`); offline → pesan jelas, retur tidak bisa dibuat;
/// - penjualan `Lunas`/`DireturSebagian`, bukan void, dalam `BatasHariRetur` (keputusan server lewat `BisaDiretur`);
/// - jumlah per baris > 0 dan ≤ `JumlahBisaDiretur` (`JumlahReturMelebihi`), desimal hanya untuk satuan desimal;
/// - nilai per baris dari [PenghitungNilaiRetur] (rumus server), Σ = `Ringkasan.TotalRefund`;
/// - refund fase 1 tunai dari laci shift aktif dan/atau transfer manual, Σ refund = total;
/// - pelaku ber-izin `penjualan.buat`/`penjualan.retur`, penyetuju ber-izin `penjualan.retur` (boleh diri sendiri, K-22);
/// - butuh shift terbuka; nomor `RJ/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}` (sekuens per perangkat per hari);
/// - retur + baris + refund + status penjualan asal lokal + outbox `ReturPenjualan.Buat` dalam satu transaksi SQLite.
class LayananReturPenjualan {
  LayananReturPenjualan({
    required this.klien,
    required this.repositori,
    required this.repositoriPenjualan,
    PembuatUlid? ulid,
    DateTime Function()? jam,
  }) : _ulid = ulid ?? PembuatUlid(),
       _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'ReturPenjualan.Buat';
  static const int panjangAlasanMinimal = 5;
  static const String pesanOffline = 'Retur butuh koneksi internet untuk mencari struk.';

  final KlienPos klien;
  final RepositoriKasir repositori;
  final RepositoriPenjualan repositoriPenjualan;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Batas hari retur dari data awal (bawaan 7).
  Future<int> AmbilBatasHariRetur() async =>
      int.tryParse(await repositori.AmbilPengaturan(KunciPengaturan.batasHariRetur) ?? '') ??
      DataAwal.batasHariReturBawaan;

  /// Daftar penjualan yang bisa dipilih untuk diretur (terbaru, atau yang nomornya memuat [kata]). Offline atau galat
  /// server → daftar kosong: layar tetap bisa dipakai lewat nomor utuh atau pindai QR struk.
  Future<List<KandidatReturPos>> CariKandidat(String kata) async {
    try {
      return await klien.CariKandidatRetur(kata);
    } on GalatJaringan {
      return const [];
    } on GalatApi {
      return const [];
    }
  }

  /// Cari struk asal di server. Offline → `ReturButuhInternet`; tidak ada → `PenjualanTidakDitemukan` (dengan petunjuk
  /// bila struk itu dibuat di perangkat ini dan belum terkirim); retur sebelumnya atas struk ini yang belum terkirim →
  /// `ReturSebelumnyaBelumTerkirim` (jumlah yang bisa diretur dari server belum memperhitungkannya).
  Future<HasilCariPenjualan> Cari(String nomor) async {
    final rapi = nomor.trim();
    if (rapi.isEmpty) {
      throw const GalatKasir('NomorKosong', 'Masukkan atau pindai nomor struk dulu.');
    }
    final HasilCariPenjualan hasil;
    try {
      hasil = await klien.CariPenjualan(rapi);
    } on GalatJaringan {
      throw const GalatKasir('ReturButuhInternet', pesanOffline);
    } on GalatApi catch (galat) {
      if (galat.kode == 'PenjualanTidakDitemukan' || galat.statusHttp == 404) {
        final lokal = await repositoriPenjualan.CariPenjualanNomor(rapi);
        if (lokal != null && await repositoriPenjualan.CekMasihDiOutbox(lokal.Uuid)) {
          throw GalatKasir(
            'PenjualanBelumTerkirim',
            'Struk $rapi belum terkirim ke server. Tunggu sampai terkirim (lihat menu Sinkron), lalu cari lagi.',
          );
        }
        throw GalatKasir('PenjualanTidakDitemukan', 'Struk $rapi tidak ditemukan di outlet ini. Periksa nomornya.');
      }
      throw GalatKasir(galat.kode, galat.pesan);
    }
    if (await repositoriPenjualan.CekReturBelumTerkirim(hasil.penjualan.uuid)) {
      throw GalatKasir(
        'ReturSebelumnyaBelumTerkirim',
        'Retur sebelumnya untuk struk $rapi belum terkirim ke server. Tunggu sampai terkirim, lalu cari lagi.',
      );
    }
    return hasil;
  }

  /// Pesan untuk penjualan yang tidak bisa diretur (null = bisa).
  static String? AmbilPesanTidakBisaDiretur(PenjualanCariPos p) {
    if (p.bisaDiretur) {
      return null;
    }
    return switch (p.alasanTidakBisaDiretur) {
      PenjualanCariPos.alasanVoid => 'Transaksi ${p.nomor} sudah di-void sehingga tidak bisa diretur.',
      PenjualanCariPos.alasanSudahDireturPenuh => 'Semua barang di transaksi ${p.nomor} sudah diretur.',
      PenjualanCariPos.alasanLewatBatasHari =>
        'Batas retur ${p.batasHariRetur} hari untuk transaksi ${p.nomor} sudah lewat '
            '(sampai ${_FormatTanggal(p.batasReturSampai)}).',
      _ => 'Transaksi ${p.nomor} (${p.labelStatus}) tidak bisa diretur.',
    };
  }

  static String _FormatTanggal(String tanggal) => tanggal.length == 10
      ? '${tanggal.substring(8, 10)}/${tanggal.substring(5, 7)}/${tanggal.substring(0, 4)}'
      : tanggal;

  static bool _CekPecahan(String nilai) {
    final d = Decimal.tryParse(nilai) ?? Decimal.zero;
    return d != d.truncate();
  }

  /// Apakah jumlah retur baris ini boleh desimal. Jumlah jual/sisa yang sudah pecahan selalu boleh (agar sisa bisa
  /// diretur habis); selain itu `BolehDesimal` dari server (satuan yang dijual) dipakai bila ada, dan server lama
  /// (null) jatuh ke heuristik satuan produk di katalog lokal.
  static bool CekBolehDesimal(BarisPenjualanCariPos baris, KatalogLokal? katalog) {
    if (_CekPecahan(baris.jumlah) || _CekPecahan(baris.jumlahBisaDiretur)) {
      return true;
    }
    final dariServer = baris.bolehDesimal;
    if (dariServer != null) {
      return dariServer;
    }
    final uuidProduk = baris.uuidProduk;
    final produk = uuidProduk == null ? null : katalog?.CariProduk(uuidProduk);
    return produk?.satuan.any((s) => s.bolehDesimal) ?? false;
  }

  /// Nilai retur per pilihan dan totalnya (= `Ringkasan.TotalRefund`).
  static Uang HitungTotal(List<PilihanReturBaris> pilihan) =>
      PenghitungNilaiRetur.HitungTotal([for (final p in pilihan) (baris: p.baris, jumlah: p.jumlah)]);

  /// Penyetuju efektif: penyetuju yang lolos PIN, atau kasir sendiri bila ber-izin `penjualan.retur` (K-22).
  static StafLokal? AmbilPenyetujuEfektif(StafLokal kasir, StafLokal? penyetuju) =>
      penyetuju ?? (kasir.PunyaIzin(IzinKasir.penjualanRetur) ? kasir : null);

  /// Validasi pilihan baris (tanpa menyentuh basis data), untuk layar sebelum meminta PIN.
  /// F-16d bagian 2: baris paket sesi tidak diretur di kasir; sisa sesinya dikembalikan dari back-office.
  static bool CekPaketSesi(BarisPenjualanCariPos baris, KatalogLokal? katalog) {
    final uuidProduk = baris.uuidProduk;
    return uuidProduk != null && (katalog?.CariProduk(uuidProduk)?.paketSesi ?? false);
  }

  static List<PilihanReturBaris> ValidasiPilihan(
    HasilCariPenjualan hasil,
    List<PilihanReturBaris> pilihan, {
    KatalogLokal? katalog,
  }) {
    final pesan = AmbilPesanTidakBisaDiretur(hasil.penjualan);
    if (pesan != null) {
      throw GalatKasir('ReturTidakDiizinkan', pesan);
    }
    final terisi = pilihan.where((p) => !p.jumlah.BernilaiNol()).toList();
    if (terisi.isEmpty) {
      throw const GalatKasir('BarisKosong', 'Pilih minimal satu barang dan isi jumlah returnya.');
    }
    final uuidBaris = {for (final b in hasil.baris) b.uuid};
    final dipakai = <String>{};
    for (final p in terisi) {
      final nama = p.baris.namaProduk;
      if (!uuidBaris.contains(p.baris.uuid) || !dipakai.add(p.baris.uuid)) {
        throw GalatKasir('BarisTidakDikenal', '"$nama" bukan bagian dari transaksi ${hasil.penjualan.nomor}.');
      }
      if (p.jumlah.BernilaiNegatif()) {
        throw GalatKasir('JumlahTidakValid', 'Jumlah retur "$nama" tidak boleh minus.');
      }
      if (p.baris.racikan) {
        throw GalatKasir('ReturRacikanTidakDidukung', '"$nama" adalah obat racikan dan tidak bisa diretur.');
      }
      if (CekPaketSesi(p.baris, katalog)) {
        throw GalatKasir(
          'ReturPaketSesiTidakDidukung',
          '"$nama" adalah paket sesi dan tidak bisa diretur. Kembalikan sisa sesinya dari back-office.',
        );
      }
      final desimal = p.jumlah.KeDesimal();
      if (desimal != desimal.truncate() && !CekBolehDesimal(p.baris, katalog)) {
        throw GalatKasir('JumlahTidakValid', 'Jumlah retur "$nama" harus bilangan bulat.');
      }
      final sisa = Kuantitas.Dari(p.baris.jumlahBisaDiretur);
      if (p.jumlah.Bandingkan(sisa) > 0) {
        final satuan = p.baris.simbolSatuan.isEmpty ? '' : ' ${p.baris.simbolSatuan}';
        throw GalatKasir(
          'JumlahReturMelebihi',
          'Jumlah retur "$nama" melebihi sisa yang bisa diretur (${_FormatJumlah(sisa)}$satuan).',
        );
      }
      if (p.kondisi != KondisiRetur.layakJual && p.kondisi != KondisiRetur.rusak) {
        throw GalatKasir('KondisiTidakValid', 'Pilih kondisi barang "$nama": layak jual atau rusak.');
      }
    }
    return terisi;
  }

  static String _FormatJumlah(Kuantitas jumlah) {
    var teks = jumlah.KeDesimal().toString();
    if (teks.contains('.')) {
      teks = teks.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
    }
    return teks.replaceAll('.', ',');
  }

  /// F-12: retur penjualan tempo memotong sisa piutang lebih dulu: min(total, sisa piutang); 0 bila bukan tempo.
  static Uang HitungPotongPiutang(HasilCariPenjualan hasil, Uang total) {
    final sisa = hasil.penjualan.sisaPiutang;
    if (sisa == null || !hasil.pembayaran.any((b) => b.jenisMetode == JenisMetodeBayar.tempo)) {
      return Uang.Nol();
    }
    final nilai = Uang.Dari(sisa);
    if (nilai.BernilaiNegatif()) {
      return Uang.Nol();
    }
    return nilai.Bandingkan(total) < 0 ? nilai : total;
  }

  /// Simpan retur. F-12: penjualan tempo memotong piutang dulu ([HitungPotongPiutang]); [refundTunai] = bagian tunai
  /// dari laci shift aktif; sisanya (total − potong piutang − tunai) ditransfer manual lewat [metodeTransfer]. [penyetuju] = staf yang lolos PIN; null → kasir sendiri bila ber-izin `penjualan.retur`.
  Future<ReturTersimpan> Simpan({
    required HasilCariPenjualan hasil,
    required List<PilihanReturBaris> pilihan,
    required String alasan,
    required Uang refundTunai,
    required StafLokal kasir,
    required KonteksPenjualan k,
    BarisMetodePembayaran? metodeTransfer,
    StafLokal? penyetuju,
    KatalogLokal? katalog,
    Uang? refundTukar,
    BarisMetodePembayaran? metodeTukar,
    String? uuidRetur,
  }) async {
    final shift = await repositori.AmbilShiftAktif();
    if (shift == null) {
      throw const GalatKasir('ShiftTidakDitemukan', 'Belum ada shift terbuka. Buka shift dulu sebelum retur.');
    }
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat) && !kasir.PunyaIzin(IzinKasir.penjualanRetur)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin melayani retur.');
    }
    final kodeOutlet = k.kodeOutlet ?? '';
    final kodePerangkat = k.kodePerangkat ?? '';
    if (kodeOutlet.isEmpty || kodePerangkat.isEmpty) {
      throw const GalatKasir(
        'DataAwalBelumLengkap',
        'Kode outlet atau perangkat belum ada di perangkat ini. Sambungkan ke internet agar data terbaru terunduh.',
      );
    }
    final terisi = ValidasiPilihan(hasil, pilihan, katalog: katalog);
    final alasanRapi = alasan.trim();
    if (alasanRapi.runes.length < panjangAlasanMinimal) {
      throw const GalatKasir('AlasanDiperlukan', 'Tulis alasan retur minimal 5 huruf.');
    }

    final total = HitungTotal(terisi);
    final potongPiutang = HitungPotongPiutang(hasil, total);
    final dibayarKembali = total.Kurangi(potongPiutang);
    if (refundTunai.BernilaiNegatif() || refundTunai.Bandingkan(dibayarKembali) > 0) {
      throw GalatKasir('RefundTidakSesuai', 'Refund tunai harus antara Rp 0 dan ${dibayarKembali.FormatRupiah()}.');
    }
    // K-11: bagian refund yang dipakai membayar barang pengganti (bukan uang keluar).
    final tukar = refundTukar ?? Uang.Nol();
    if (tukar.BernilaiNegatif() || refundTunai.Tambah(tukar).Bandingkan(dibayarKembali) > 0) {
      throw GalatKasir(
        'RefundTidakSesuai',
        'Nilai tukar barang melebihi nilai retur ${dibayarKembali.FormatRupiah()}.',
      );
    }
    if (!tukar.BernilaiNol() && metodeTukar == null) {
      throw const GalatKasir(
        'MetodeBayarTidakDikenal',
        'Metode tukar barang belum ada di perangkat ini. Perbarui data kasir.',
      );
    }
    final refundTransfer = dibayarKembali.Kurangi(refundTunai).Kurangi(tukar);
    final bayarTempo = hasil.pembayaran.where((b) => b.jenisMetode == JenisMetodeBayar.tempo).firstOrNull;
    if (!potongPiutang.BernilaiNol() && bayarTempo?.uuidMetodePembayaran == null) {
      throw const GalatKasir('MetodeBayarTidakDikenal', 'Metode Tempo penjualan ini tidak ditemukan. Coba cari ulang.');
    }
    final metodeTunai = k.metodePembayaran.where((m) => m.Jenis == JenisMetodeBayar.tunai).firstOrNull;
    if (!refundTunai.BernilaiNol() && metodeTunai == null) {
      throw const GalatKasir('MetodeBayarTidakDikenal', 'Metode Tunai belum aktif di outlet ini.');
    }
    if (!refundTransfer.BernilaiNol()) {
      if (metodeTransfer == null) {
        throw GalatKasir(
          'MetodeBayarTidakDikenal',
          'Pilih rekening transfer untuk refund ${refundTransfer.FormatRupiah()}.',
        );
      }
      // F-16d: sisa refund boleh masuk saldo deposit bila penjualan asal atas nama pelanggan.
      final keDeposit = metodeTransfer.Jenis == JenisMetodeBayar.deposit && hasil.penjualan.bisaRefundDeposit;
      if (metodeTransfer.Jenis != JenisMetodeBayar.transfer && !keDeposit) {
        throw GalatKasir(
          'MetodeBayarBelumDidukung',
          'Refund lewat ${metodeTransfer.Nama} belum didukung. Pakai tunai, transfer manual, atau deposit pelanggan.',
        );
      }
    }

    final efektif = AmbilPenyetujuEfektif(kasir, penyetuju);
    if (efektif == null) {
      throw const GalatKasir('PersetujuanDiperlukan', 'Retur wajib disetujui supervisor dengan PIN.');
    }
    if (!efektif.PunyaIzin(IzinKasir.penjualanRetur)) {
      throw GalatKasir('PenyetujuTidakBerwenang', '${efektif.nama} tidak punya izin menyetujui retur.');
    }

    final sekarang = _jam().toUtc();
    final tanggalBisnis = k.HitungTanggalBisnis(sekarang);
    final yymmdd = '${tanggalBisnis.substring(2, 4)}${tanggalBisnis.substring(5, 7)}${tanggalBisnis.substring(8, 10)}';
    final uuid = uuidRetur ?? _ulid.Buat();
    final baris = [
      for (final p in terisi) (uuid: _ulid.Buat(), pilihan: p, nilai: PenghitungNilaiRetur.Hitung(p.baris, p.jumlah)),
    ];
    final refund = [
      if (!potongPiutang.BernilaiNol())
        (
          uuid: _ulid.Buat(),
          uuidMetode: bayarTempo!.uuidMetodePembayaran!,
          jenis: JenisMetodeBayar.tempo,
          nama: bayarTempo.namaMetode,
          jumlah: potongPiutang,
        ),
      if (!tukar.BernilaiNol())
        (
          uuid: _ulid.Buat(),
          uuidMetode: metodeTukar!.Uuid,
          jenis: metodeTukar.Jenis,
          nama: metodeTukar.Nama,
          jumlah: tukar,
        ),
      if (!refundTunai.BernilaiNol())
        (
          uuid: _ulid.Buat(),
          uuidMetode: metodeTunai!.Uuid,
          jenis: metodeTunai.Jenis,
          nama: metodeTunai.Nama,
          jumlah: refundTunai,
        ),
      if (!refundTransfer.BernilaiNol())
        (
          uuid: _ulid.Buat(),
          uuidMetode: metodeTransfer!.Uuid,
          jenis: metodeTransfer.Jenis,
          nama: metodeTransfer.Nama,
          jumlah: refundTransfer,
        ),
    ];
    final metodeRefund = refund.length > 1
        ? MetodeRefundRetur.campuran
        : refund.isNotEmpty && refund.first.jenis == JenisMetodeBayar.tempo
        ? MetodeRefundRetur.piutang
        : refund.isNotEmpty && refund.first.jenis == JenisMetodeBayar.transfer
        ? MetodeRefundRetur.transfer
        : refund.isNotEmpty && refund.first.jenis == JenisMetodeBayar.deposit
        ? MetodeRefundRetur.deposit
        : refund.isNotEmpty && refund.first.jenis == JenisMetodeBayar.tukar
        ? MetodeRefundRetur.tukar
        : MetodeRefundRetur.tunai;

    // Status penjualan asal bila ada di perangkat ini: Diretur bila semua sisa baris habis oleh retur ini.
    final jumlahRetur = {for (final p in terisi) p.baris.uuid: p.jumlah};
    final habis = hasil.baris.every(
      (b) => Kuantitas.Dari(b.jumlahBisaDiretur).Kurangi(jumlahRetur[b.uuid] ?? Kuantitas.Nol()).BernilaiNol(),
    );
    final asalLokal = await repositoriPenjualan.CariPenjualan(hasil.penjualan.uuid);

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
            UuidPenjualanAsal: hasil.penjualan.uuid,
            NomorPenjualanAsal: hasil.penjualan.nomor,
            UuidShift: shift.Uuid,
            UuidPengguna: kasir.uuid,
            NamaKasir: kasir.nama,
            UuidPenyetuju: efektif.uuid,
            Alasan: alasanRapi,
            DibuatPada: sekarang,
            TanggalBisnis: tanggalBisnis,
            MetodeRefund: metodeRefund,
            TotalRefund: total.KeString(),
            RefundTunai: refundTunai.KeString(),
          ),
          detail: [
            for (final b in baris)
              ReturPenjualanDetailCompanion.insert(
                Uuid: b.uuid,
                UuidReturPenjualan: uuid,
                UuidPenjualanDetail: b.pilihan.baris.uuid,
                NamaProduk: b.pilihan.baris.namaProduk,
                SimbolSatuan: b.pilihan.baris.simbolSatuan,
                Jumlah: b.pilihan.jumlah.KeString(),
                Kondisi: b.pilihan.kondisi,
                NilaiBaris: b.nilai.KeString(),
              ),
          ],
          pembayaran: [
            for (final r in refund)
              ReturPenjualanPembayaranCompanion.insert(
                Uuid: r.uuid,
                UuidReturPenjualan: uuid,
                UuidMetodePembayaran: r.uuidMetode,
                Jenis: r.jenis,
                NamaMetode: r.nama,
                Jumlah: r.jumlah.KeString(),
              ),
          ],
          statusPenjualanAsal: asalLokal == null
              ? null
              : habis
              ? StatusPenjualanLokal.diretur
              : StatusPenjualanLokal.direturSebagian,
          outbox: ItemOutbox(
            jenis: jenisOutbox,
            uuid: uuid,
            data: BuatDataOutbox(
              uuidPenjualanAsal: hasil.penjualan.uuid,
              uuidShift: shift.Uuid,
              uuidPengguna: kasir.uuid,
              uuidPenyetuju: efektif.uuid,
              nomor: nomor,
              alasan: alasanRapi,
              dibuatPada: sekarang,
              baris: [
                for (final b in baris)
                  (
                    uuid: b.uuid,
                    uuidPenjualanDetail: b.pilihan.baris.uuid,
                    jumlah: b.pilihan.jumlah,
                    kondisi: b.pilihan.kondisi,
                  ),
              ],
              refund: [for (final r in refund) (uuid: r.uuid, uuidMetodePembayaran: r.uuidMetode, jumlah: r.jumlah)],
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
      refundTunai: refundTunai,
      refundTransfer: refundTransfer,
      namaMetodeTransfer: refundTransfer.BernilaiNol() ? null : metodeTransfer?.Nama,
      potongPiutang: potongPiutang,
    );
  }

  /// Data item outbox `ReturPenjualan.Buat` persis kontrak Rincian F-09 fase 1 (uang string 2 desimal, jumlah string
  /// 4 desimal, waktu ISO UTC).
  static Map<String, Object?> BuatDataOutbox({
    required String uuidPenjualanAsal,
    required String uuidShift,
    required String uuidPengguna,
    required String uuidPenyetuju,
    required String nomor,
    required String alasan,
    required DateTime dibuatPada,
    required List<({String uuid, String uuidPenjualanDetail, Kuantitas jumlah, String kondisi})> baris,
    required List<({String uuid, String uuidMetodePembayaran, Uang jumlah})> refund,
    required Uang totalRefund,
  }) => {
    'UuidPenjualanAsal': uuidPenjualanAsal,
    'UuidShift': uuidShift,
    'UuidPengguna': uuidPengguna,
    'UuidPenyetuju': uuidPenyetuju,
    'Nomor': nomor,
    'Alasan': alasan,
    'DibuatPada': dibuatPada.toUtc().toIso8601String(),
    'Baris': [
      for (final b in baris)
        {
          'Uuid': b.uuid,
          'UuidPenjualanDetail': b.uuidPenjualanDetail,
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
