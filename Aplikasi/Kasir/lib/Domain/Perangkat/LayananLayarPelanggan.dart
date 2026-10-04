import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:inti/Inti.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/RepositoriKasir.dart';
import '../Penjualan/Keranjang.dart';
import '../Penjualan/LayananPenjualan.dart';

/// Mode layar pelanggan perangkat (PRD §17.2.5a `PortLayar`, v2.01).
abstract final class ModeLayarPelanggan {
  static const String mati = 'Mati';

  /// Android: layar kedua POS all-in-one atau monitor HDMI (*presentation display*).
  static const String layarKedua = 'LayarKedua';

  /// Windows: layar VFD 2×20 (*pole display*) lewat COM port.
  static const String vfd = 'Vfd';
}

/// Pengaturan layar pelanggan per perangkat (tidak ikut data awal) + nama toko untuk layar siaga.
class PengaturanLayarPelanggan {
  const PengaturanLayarPelanggan({this.mode = ModeLayarPelanggan.mati, this.portVfd, this.namaToko = ''});

  final String mode;

  /// Nama COM port VFD (mis. `COM3`), hanya untuk mode [ModeLayarPelanggan.vfd].
  final String? portVfd;
  final String namaToko;

  bool get aktif => mode != ModeLayarPelanggan.mati;

  PengaturanLayarPelanggan Salin({String? mode, String? portVfd, String? namaToko}) => PengaturanLayarPelanggan(
    mode: mode ?? this.mode,
    portVfd: portVfd ?? this.portVfd,
    namaToko: namaToko ?? this.namaToko,
  );

  static Future<PengaturanLayarPelanggan> Muat(RepositoriKasir repositori) async {
    final teks = await repositori.AmbilPengaturan(KunciPengaturan.layarPelanggan);
    final namaToko = await repositori.AmbilPengaturan(KunciPengaturan.namaUsaha) ?? '';
    final json = teks == null ? null : jsonDecode(teks);
    if (json is! Map<String, Object?>) {
      return PengaturanLayarPelanggan(namaToko: namaToko);
    }
    return PengaturanLayarPelanggan(
      mode: json['Mode'] is String ? json['Mode']! as String : ModeLayarPelanggan.mati,
      portVfd: json['PortVfd'] is String ? json['PortVfd']! as String : null,
      namaToko: namaToko,
    );
  }

  Future<void> Simpan(RepositoriKasir repositori) =>
      repositori.SimpanPengaturan(KunciPengaturan.layarPelanggan, jsonEncode({'Mode': mode, 'PortVfd': portVfd}));
}

/// Menyusun isi layar pelanggan dari keranjang & hasil bayar (PRD §17.2.5a, POS-15 customer display). Pelanggan melihat
/// item yang dipindai, harga setelah diskon baris, ringkasan (diskon, biaya layanan, pajak), total, lalu kembalian dan
/// ucapan terima kasih setelah bayar. Murni (tanpa efek samping) agar mudah diuji.
abstract final class PenyusunLayarPelanggan {
  static IsiLayarPelanggan Siaga(String namaToko) => IsiLayarPelanggan.Siaga(namaToko);

  static IsiLayarPelanggan DariKeranjang(
    String namaToko,
    Keranjang keranjang,
    HitunganKeranjang? hitungan, {
    bool bayar = false,
    String? dataQr,
  }) {
    if (keranjang.CekKosong) {
      return Siaga(namaToko);
    }
    final hasil = hitungan?.hasil;
    return IsiLayarPelanggan(
      keadaan: bayar ? KeadaanLayarPelanggan.Bayar : KeadaanLayarPelanggan.Keranjang,
      namaToko: namaToko,
      baris: [
        for (var i = 0; i < keranjang.baris.length; i++)
          BarisLayarPelanggan(
            nama: keranjang.baris[i].nama,
            rincian: _Rincian(keranjang.baris[i]),
            nilai:
                (hasil == null || i >= hasil.baris.length
                        ? keranjang.baris[i].hargaSatuan
                        : hasil.baris[i].bruto.Kurangi(hasil.baris[i].diskon))
                    .FormatRupiah(),
          ),
      ],
      ringkasan: [
        if (hasil != null) ...[
          if (!hasil.diskonPesanan.BernilaiNol())
            BarisLayarPelanggan(nama: 'Diskon', nilai: '-${hasil.diskonPesanan.FormatRupiah()}'),
          if (!hasil.biayaLayanan.BernilaiNol())
            BarisLayarPelanggan(nama: 'Biaya layanan', nilai: hasil.biayaLayanan.FormatRupiah()),
          if (!hasil.totalPajakEksklusif.BernilaiNol())
            BarisLayarPelanggan(nama: 'Pajak', nilai: hasil.totalPajakEksklusif.FormatRupiah()),
        ],
      ],
      total: hasil?.totalAkhir.FormatRupiah(),
      pesan: bayar && dataQr != null
          ? 'Pindai QRIS untuk membayar'
          : bayar
          ? 'Silakan lakukan pembayaran'
          : null,
      dataQr: bayar ? dataQr : null,
    );
  }

  static IsiLayarPelanggan Selesai(String namaToko, PenjualanTersimpan hasil) => IsiLayarPelanggan(
    keadaan: KeadaanLayarPelanggan.Selesai,
    namaToko: namaToko,
    ringkasan: [
      BarisLayarPelanggan(nama: 'Total', nilai: hasil.totalAkhir.FormatRupiah()),
      BarisLayarPelanggan(nama: 'Dibayar', nilai: hasil.totalDibayar.FormatRupiah()),
    ],
    labelTotal: 'Kembalian',
    total: hasil.kembalian.FormatRupiah(),
    pesan: 'Terima kasih',
  );

  static String? _Rincian(ItemKeranjang b) {
    final bagian = ['${_FormatJumlah(b.jumlah)} × ${b.hargaSatuan.FormatRupiah()}', for (final p in b.pilihan) p.nama];
    return bagian.join(' | ');
  }

  static String _FormatJumlah(Kuantitas jumlah) {
    var teks = jumlah.KeDesimal().toString();
    if (teks.contains('.')) {
      teks = teks.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
    }
    return teks.replaceAll('.', ',');
  }
}
