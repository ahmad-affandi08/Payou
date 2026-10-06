import 'dart:ffi';
import 'dart:isolate';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:ffi/ffi.dart';
import 'package:win32/win32.dart';

import '../../Domain/Struk/PemindaiPrinter.dart';

/// Printer USB di Windows (PRD §17.2.5, v1.96): printer thermal USB dipasang dengan driver pabrik (atau "Generic / Text
/// Only") sehingga muncul di Pengaturan Windows › Printer. Aplikasi mengirim byte ESC/POS mentah lewat spooler
/// (`WritePrinter` dengan tipe data `RAW`), jadi logo, QR, potong kertas, dan buka laci tetap dari ESC/POS. Printer
/// bawaan POS all-in-one Windows yang memakai driver juga lewat jalur ini.
abstract final class SpoolerWindows {
  /// Printer yang terpasang di Windows (lokal & sambungan jaringan), urut nama.
  static List<PrinterDitemukan> DaftarPrinter() => using((arena) {
    const bendera = PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS;
    final dibutuhkan = arena<Uint32>();
    final jumlah = arena<Uint32>();
    EnumPrinters(bendera, null, 4, null, 0, dibutuhkan, jumlah);
    if (dibutuhkan.value == 0) {
      return const <PrinterDitemukan>[];
    }
    final penyangga = arena<Uint8>(dibutuhkan.value);
    if (!EnumPrinters(bendera, null, 4, penyangga, dibutuhkan.value, dibutuhkan, jumlah).value) {
      return const <PrinterDitemukan>[];
    }
    final info = penyangga.cast<PRINTER_INFO_4>();
    final hasil = <PrinterDitemukan>[
      for (var i = 0; i < jumlah.value; i++)
        if (info[i].pPrinterName.address != 0)
          PrinterDitemukan(
            jenis: JenisTransport.Usb,
            alamat: info[i].pPrinterName.toDartString(),
            nama: info[i].pPrinterName.toDartString(),
          ),
    ];
    return hasil..sort((a, b) => a.nama.toLowerCase().compareTo(b.nama.toLowerCase()));
  });
}

/// Mengirim byte ESC/POS ke printer Windows [namaPrinter] sebagai satu dokumen RAW. Spooler bisa lambat saat printer
/// mati, jadi berjalan di isolate lain agar layar kasir tidak macet.
class TransportSpoolerWindows implements TransportPrinter {
  const TransportSpoolerWindows(this.namaPrinter);

  final String namaPrinter;

  @override
  Future<void> Kirim(List<int> data) async {
    final nama = namaPrinter;
    final salinan = List<int>.of(data);
    final galat = await Isolate.run(() => _Tulis(nama, salinan));
    if (galat != null) {
      throw GalatPrinter(galat);
    }
  }

  /// null = berhasil; selain itu pesan untuk kasir.
  static String? _Tulis(String nama, List<int> data) => using((arena) {
    final pegangan = arena<Pointer>();
    if (!OpenPrinter(arena.pcwstr(nama), pegangan, null).value) {
      return 'Printer "$nama" tidak ditemukan di Windows. Pastikan driver printer terpasang, lalu pilih ulang printer.';
    }
    final printer = PRINTER_HANDLE(pegangan.value);
    try {
      final dokumen = arena<DOC_INFO_1>()
        ..ref.pDocName = arena.pwstr('Struk Payoung')
        ..ref.pOutputFile = PWSTR(nullptr)
        ..ref.pDatatype = arena.pwstr('RAW');
      if (StartDocPrinter(printer, 1, dokumen) == 0) {
        return 'Printer "$nama" menolak pekerjaan cetak. Cek antrean printer di Windows, lalu coba lagi.';
      }
      try {
        StartPagePrinter(printer);
        final byte = arena<Uint8>(data.length);
        byte.asTypedList(data.length).setAll(0, data);
        final tertulis = arena<Uint32>();
        final berhasil = WritePrinter(printer, byte, data.length, tertulis);
        EndPagePrinter(printer);
        if (!berhasil || tertulis.value != data.length) {
          return 'Struk gagal terkirim ke printer "$nama". Pastikan printer menyala dan kabel USB tersambung.';
        }
      } finally {
        EndDocPrinter(printer);
      }
      return null;
    } finally {
      ClosePrinter(printer);
    }
  });
}
