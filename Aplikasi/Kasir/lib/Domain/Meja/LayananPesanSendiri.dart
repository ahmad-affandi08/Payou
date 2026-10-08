import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/PesananMeja.dart';
import '../../Data/RepositoriPesananMeja.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Penjualan/Keranjang.dart';
import '../Penjualan/KonteksPenjualan.dart';
import '../Penjualan/LayananPenjualan.dart';
import '../Sesi/StafLokal.dart';
import 'LayananPesananMeja.dart';

/// Pesanan dari QR meja (F-17 self-order, v2.02) di aplikasi POS: daftar pesanan yang menunggu konfirmasi (online),
/// terima → baris dicatat ke pesanan terbuka meja itu (dibuka bila belum ada) lalu dikirim ke dapur lewat outbox
/// `PesananTerbuka.*` seperti pesanan kasir/pelayan, atau tolak dengan alasan.
///
/// Urutan terima: validasi semua produk ada di katalog perangkat → klaim di server (`terima`, perangkat lain yang
/// lebih dulu → `SudahDiproses`) → buka pesanan (bila perlu) + simpan & kirim baris dalam transaksi lokal. Uuid baris
/// memakai Uuid baris pesanan QR sehingga server tidak menggandakan baris bila outbox terkirim ulang.
class LayananPesanSendiri {
  LayananPesanSendiri({
    required this.klien,
    required this.pesananMeja,
    required this.repositoriMeja,
    required this.penjualan,
    PembuatUlid? ulid,
  }) : _ulid = ulid ?? PembuatUlid();

  final KlienPos klien;
  final LayananPesananMeja pesananMeja;
  final RepositoriPesananMeja repositoriMeja;
  final LayananPenjualan penjualan;
  final PembuatUlid _ulid;

  Future<List<PesananSendiriPos>> AmbilMenunggu() => klien.AmbilPesanSendiri();

  Future<PesananMeja> Terima(
    PesananSendiriPos pesanan, {
    required StafLokal kasir,
    required KatalogLokal katalog,
    required KonteksPenjualan k,
  }) async {
    if (!kasir.CekBolehCatatPesanan()) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin menerima pesanan.');
    }
    final uuidMeja = pesanan.uuidMeja;
    final meja = uuidMeja == null ? null : await repositoriMeja.CariMeja(uuidMeja);
    if (meja == null) {
      throw const GalatKasir(
        'MejaTidakDikenal',
        'Meja pesanan ini belum ada di perangkat. Perbarui data meja (tarik layar Meja) lalu coba lagi.',
      );
    }
    final draf = [for (final b in pesanan.baris) _BuatBaris(b, katalog, k)];
    final ada = await repositoriMeja.CariPesananDiMeja(meja.Uuid);
    // Pesanan baru memakai Uuid pesanan QR itu sendiri: percobaan ulang setelah jawaban server hilang (timeout) tetap
    // idempoten di server, bukan 409 SudahDiproses dengan pesanan yang tak pernah sampai ke dapur.
    final uuidTujuan = ada?.uuid ?? pesanan.uuid;

    await klien.TerimaPesanSendiri(pesanan.uuid, uuidPengguna: kasir.uuid, uuidPesananTerbuka: uuidTujuan);

    if (ada == null) {
      await pesananMeja.Buka(kasir: kasir, k: k, meja: meja, label: pesanan.namaPemesan, uuid: uuidTujuan);
    }
    return pesananMeja.SimpanBaris(uuidPesanan: uuidTujuan, draf: draf, kasir: kasir, kirimDapur: true);
  }

  Future<void> Tolak(PesananSendiriPos pesanan, {required StafLokal kasir, required String alasan}) async {
    if (!kasir.CekBolehCatatPesanan()) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin menolak pesanan.');
    }
    final rapi = alasan.trim();
    if (rapi.runes.length < LayananPesananMeja.panjangAlasanMinimal) {
      throw const GalatKasir('AlasanWajib', 'Tulis alasan penolakan minimal 3 huruf agar tamu tahu.');
    }
    await klien.TolakPesanSendiri(pesanan.uuid, uuidPengguna: kasir.uuid, alasan: rapi);
  }

  ItemKeranjang _BuatBaris(BarisPesananTerbukaPos b, KatalogLokal katalog, KonteksPenjualan k) {
    final produk = b.uuidProduk == null ? null : katalog.CariProduk(b.uuidProduk!);
    if (produk == null) {
      throw GalatKasir(
        'ProdukTidakDikenal',
        '"${b.namaProduk}" belum ada di katalog perangkat ini. Perbarui katalog lalu coba lagi.',
      );
    }
    final satuan = b.uuidProdukSatuan == null
        ? null
        : produk.satuan.where((s) => s.uuid == b.uuidProdukSatuan).firstOrNull;
    final baris = penjualan.BuatBaris(
      katalog,
      k,
      produk,
      satuan: satuan,
      pilihan: [
        for (final p in b.pilihan)
          PilihanTerpilih(
            uuid: p['UuidPilihan'] is String ? p['UuidPilihan']! as String : '',
            nama: p['Nama'] is String ? p['Nama']! as String : '',
            harga: Uang.Dari(p['Harga'] is String ? p['Harga']! as String : '0'),
          ),
      ],
      jumlah: Kuantitas.Dari(b.jumlah),
      catatan: b.catatan,
      kanal: KanalPenjualan.MakanDiTempat,
    );
    return ItemKeranjang(
      uuid: b.uuid,
      uuidProduk: baris.uuidProduk,
      nama: baris.nama,
      uuidProdukSatuan: baris.uuidProdukSatuan,
      namaSatuan: baris.namaSatuan,
      bolehDesimal: baris.bolehDesimal,
      jumlah: baris.jumlah,
      hargaSatuan: baris.hargaSatuan,
      pilihan: baris.pilihan,
      catatan: baris.catatan,
      hargaTermasukPajak: baris.hargaTermasukPajak,
      pajak: baris.pajak,
    );
  }
}
