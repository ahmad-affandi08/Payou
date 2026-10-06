import 'DokumenStruk.dart';
import 'PengodeEscPos.dart';
import 'TransportPrinter.dart';

/// Port printer struk (PRD §17.2.5a `PortPrinter`): cetak dokumen, cetak uji, dan buka laci lewat satu transport.
class PrinterStruk {
  const PrinterStruk(this.transport, this.lebar);

  final TransportPrinter transport;
  final LebarKertas lebar;

  /// Printer sistem ([TransportDokumen]) menerima dokumen utuh; laci tidak ikut dibuka.
  Future<void> Cetak(DokumenStruk dokumen) => switch (transport) {
    final TransportDokumen sistem => sistem.CetakDokumen(dokumen, lebar),
    _ => transport.Kirim(PengodeEscPos.Kodekan(dokumen, lebar)),
  };

  Future<void> BukaLaci() => transport is TransportDokumen
      ? Future.error(
          const GalatPrinter(
            'Printer sistem tidak bisa membuka laci kas. Laci hanya bisa dibuka lewat printer thermal.',
          ),
        )
      : transport.Kirim(PengodeEscPos.KodekanBukaLaci());

  /// Halaman uji: lebar kertas penuh (garis & penggaris kolom), teks tebal/besar, dan QR, agar kasir bisa menilai
  /// apakah lebar kertas dan kualitas cetak sudah benar.
  Future<void> CetakUji({required String namaUsaha, String? keterangan}) =>
      Cetak(BuatDokumenUji(lebar, namaUsaha, keterangan));

  static DokumenStruk BuatDokumenUji(LebarKertas lebar, String namaUsaha, String? keterangan) => DokumenStruk([
    BarisTeks(namaUsaha, rata: RataStruk.Tengah, tebal: true),
    const BarisTeks('CETAK UJI', rata: RataStruk.Tengah, besar: true),
    const BarisGaris(),
    BarisTeks('Kertas ${lebar.label} (${lebar.kolom} kolom)'),
    BarisTeks(List.generate(lebar.kolom, (i) => '${(i + 1) % 10}').join()),
    const BarisDuaKolom('Kiri', 'Kanan'),
    const BarisTeks('Teks tebal', tebal: true),
    if (keterangan != null) BarisTeks(keterangan),
    const BarisGaris(),
    const BarisQr('https://payoung.id'),
    const BarisTeks('Bila semua baris lurus, printer siap dipakai.', rata: RataStruk.Tengah),
  ]);
}
