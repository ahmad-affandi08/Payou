import 'package:sistem_desain/SistemDesain.dart';

import '../../Domain/Katalog/KatalogLokal.dart';
import '../../Domain/Penjualan/AturanApotek.dart';

/// Apotek bagian 2 (§9.5): lencana golongan obat untuk ubin produk, baris katalog daftar, dan baris keranjang. Null =
/// bukan obat (tanpa lencana). Teks selalu tampil ("K", "K | OWA", "P", "N", "Bebas", "B. terbatas"); warna mengikuti
/// penandaan kemasan obat Indonesia (hijau/biru/merah) hanya sebagai penegas.
abstract final class LencanaObat {
  static LencanaTeks? Buat(ProdukJual? produk) {
    final obat = produk == null ? null : InfoObat.Dari(produk);
    if (obat == null) {
      return null;
    }
    return LencanaTeks(
      teks: obat.Kode,
      label: obat.Label,
      nada: switch (obat.Nada) {
        NadaObat.Sukses => NadaStatus.Sukses,
        NadaObat.Info => NadaStatus.Info,
        NadaObat.Bahaya => NadaStatus.Bahaya,
      },
    );
  }
}
