import 'dart:typed_data';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

/// Mengirim PDF ke dialog cetak sistem; false = kasir membatalkan.
typedef CetakPdfSistem = Future<bool> Function(Uint8List pdf, String nama, PdfPageFormat format);

/// Struk sebagai PDF gulungan (lebar 58/80 mm, tinggi mengikuti isi) dari tata letak yang sama dengan printer thermal
/// (PRD v1.97), sehingga isi & pemenggalan barisnya sama persis. Font Courier bawaan PDF (teks sudah ASCII).
abstract final class PenyusunPdfStruk {
  static const double _margin = 2 * PdfPageFormat.mm;

  static PdfPageFormat AmbilFormat(LebarKertas lebar) =>
      PdfPageFormat((lebar == LebarKertas.Mm58 ? 58 : 80) * PdfPageFormat.mm, double.infinity, marginAll: _margin);

  static Future<Uint8List> Susun(DokumenStruk dokumen, LebarKertas lebar) {
    final format = AmbilFormat(lebar);
    // Courier: lebar huruf 0,6 em. Ukuran font dipilih agar satu baris = jumlah kolom printer.
    final ukuran = format.availableWidth / (lebar.kolom * 0.6);
    final biasa = pw.Font.courier();
    final tebal = pw.Font.courierBold();
    final pdf = pw.Document(title: 'Struk', creator: 'Payoung');
    pdf.addPage(
      pw.Page(
        pageFormat: format,
        build: (_) => pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.start,
          children: [
            for (final baris in TataLetakStruk.Susun(dokumen, lebar))
              switch (baris) {
                BarisCetakTeks(:final teks, tebal: final t, :final besar) => pw.Text(
                  teks,
                  style: pw.TextStyle(
                    font: t || besar ? tebal : biasa,
                    fontSize: besar ? ukuran * 2 : ukuran,
                    lineSpacing: 0,
                  ),
                ),
                BarisCetakQr(:final data) => pw.Center(
                  child: pw.Padding(
                    padding: const pw.EdgeInsets.symmetric(vertical: 4),
                    child: pw.BarcodeWidget(
                      barcode: pw.Barcode.qrCode(),
                      data: data,
                      width: format.availableWidth * 0.55,
                      height: format.availableWidth * 0.55,
                    ),
                  ),
                ),
                BarisCetakGambar(:final gambar) => pw.Center(
                  child: pw.Image(
                    pw.RawImage(bytes: _KeRgba(gambar), width: gambar.lebar, height: gambar.tinggi),
                    width: format.availableWidth * gambar.lebar / lebar.titik,
                  ),
                ),
              },
          ],
        ),
      ),
    );
    return pdf.save();
  }

  static Uint8List _KeRgba(GambarMonokrom gambar) {
    final hasil = Uint8List(gambar.lebar * gambar.tinggi * 4);
    for (var i = 0; i < gambar.lebar * gambar.tinggi; i++) {
      final nilai = gambar.titik[i] == 1 ? 0 : 255;
      hasil
        ..[i * 4] = nilai
        ..[i * 4 + 1] = nilai
        ..[i * 4 + 2] = nilai
        ..[i * 4 + 3] = 255;
    }
    return hasil;
  }
}

/// Printer sistem (PRD §17.2.5 `CetakSistem`, v1.97): struk dibuka di dialog cetak sistem operasi (Android Print,
/// AirPrint di iOS/iPadOS, driver printer Windows) sehingga bisa dicetak ke printer apa saja atau disimpan sebagai PDF.
/// Dipakai sebagai printer tetap atau cadangan saat printer thermal bermasalah. Tidak bisa membuka laci kas.
class TransportCetakSistem implements TransportDokumen {
  const TransportCetakSistem({CetakPdfSistem? cetak}) : _cetak = cetak ?? _CetakLewatPrinting;

  final CetakPdfSistem _cetak;

  static Future<bool> _CetakLewatPrinting(Uint8List pdf, String nama, PdfPageFormat format) =>
      Printing.layoutPdf(onLayout: (_) async => pdf, name: nama, format: format);

  @override
  Future<void> CetakDokumen(DokumenStruk dokumen, LebarKertas lebar) async {
    final pdf = await PenyusunPdfStruk.Susun(dokumen, lebar);
    final bool dicetak;
    try {
      dicetak = await _cetak(pdf, 'Struk Payoung', PenyusunPdfStruk.AmbilFormat(lebar));
    } on Exception {
      throw const GalatPrinter(
        'Printer sistem tidak tersedia di perangkat ini. Pakai printer thermal atau simpan PDF.',
      );
    }
    if (!dicetak) {
      throw const GalatPrinter('Cetak lewat printer sistem dibatalkan.');
    }
  }

  @override
  Future<void> Kirim(List<int> data) =>
      Future.error(const GalatPrinter('Printer sistem hanya menerima struk utuh, bukan perintah printer thermal.'));
}
