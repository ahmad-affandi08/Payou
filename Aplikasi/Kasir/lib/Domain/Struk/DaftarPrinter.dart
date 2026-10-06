import '../Dapur/LayananTiketDapur.dart';
import 'ProfilPrinter.dart';

/// Satu printer fisik di perangkat ini beserta kegunaannya (D-67, gaya Majoo): mencetak struk kasir, tiket pesanan
/// untuk stasiun dapur/bar tertentu, atau keduanya. Penyimpanan tetap memakai dua kunci lama (`ProfilPrinter` untuk
/// struk, `PrinterDapur` per stasiun) sehingga data perangkat yang sudah ada tidak perlu dimigrasi; kelas ini hanya
/// menyatukan keduanya menjadi satu daftar untuk layar Pengaturan.
class PrinterPerangkat {
  const PrinterPerangkat({required this.profil, this.struk = false, this.stasiun = const {}});

  final ProfilPrinter profil;

  /// Mencetak struk kasir (paling banyak satu printer per perangkat).
  final bool struk;

  /// Uuid stasiun dapur/bar yang tiketnya dicetak di printer ini.
  final Set<String> stasiun;

  /// Dua profil dianggap printer yang sama bila sambungannya sama.
  String get kunci => '${profil.jenis.name}|${profil.alamat}|${profil.port}';

  PrinterPerangkat copyWith({ProfilPrinter? profil, bool? struk, Set<String>? stasiun}) => PrinterPerangkat(
    profil: profil ?? this.profil,
    struk: struk ?? this.struk,
    stasiun: stasiun ?? this.stasiun,
  );
}

/// Hasil menyusun daftar kembali ke bentuk simpan: profil printer struk (null = tidak ada) dan printer per stasiun.
class HasilSusunPrinter {
  const HasilSusunPrinter({required this.struk, required this.dapur});

  final ProfilPrinter? struk;
  final Map<String, PrinterDapur> dapur;
}

abstract final class DaftarPrinter {
  /// Gabungkan printer struk dan printer per stasiun menjadi satu daftar. Stasiun yang memakai printer struk, atau
  /// printer sendiri dengan sambungan sama, masuk ke printer yang sama (satu kartu, bukan dua).
  static List<PrinterPerangkat> Gabung(ProfilPrinter? struk, Map<String, PrinterDapur> dapur) {
    final hasil = <PrinterPerangkat>[];
    if (struk != null) {
      hasil.add(PrinterPerangkat(profil: struk, struk: true));
    }
    for (final e in dapur.entries) {
      final printer = e.value;
      final indeks = printer.samaDenganStruk
          ? hasil.indexWhere((p) => p.struk)
          : hasil.indexWhere((p) => p.kunci == _Kunci(printer.profil!));
      if (indeks >= 0) {
        hasil[indeks] = hasil[indeks].copyWith(stasiun: {...hasil[indeks].stasiun, e.key});
      } else if (!printer.samaDenganStruk) {
        hasil.add(PrinterPerangkat(profil: printer.profil!, stasiun: {e.key}));
      }
    }
    return hasil;
  }

  static String _Kunci(ProfilPrinter p) => '${p.jenis.name}|${p.alamat}|${p.port}';

  /// Kebalikan [Gabung]. Printer pertama yang berkegunaan struk menjadi printer struk (yang lain dianggap printer
  /// biasa); tiap stasiun dipetakan ke printer terakhir yang memintanya.
  static HasilSusunPrinter Susun(List<PrinterPerangkat> daftar) {
    final penentuStruk = daftar.where((p) => p.struk).firstOrNull;
    final dapur = <String, PrinterDapur>{};
    for (final p in daftar) {
      for (final uuid in p.stasiun) {
        dapur[uuid] = identical(p, penentuStruk) ? const PrinterDapur.Struk() : PrinterDapur.Sendiri(p.profil);
      }
    }
    return HasilSusunPrinter(struk: penentuStruk?.profil, dapur: dapur);
  }
}
