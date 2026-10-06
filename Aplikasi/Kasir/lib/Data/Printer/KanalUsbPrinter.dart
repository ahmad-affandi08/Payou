import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/services.dart';

import '../../Domain/Struk/PemindaiPrinter.dart';

/// Merek & model perangkat Android (untuk deteksi POS all-in-one dan profil hardware).
class InfoPerangkatAndroid {
  const InfoPerangkatAndroid({required this.produsen, required this.model, required this.versiAndroid});

  final String produsen;
  final String model;
  final String versiAndroid;

  /// Sunmi (V2, T2, D2, dll.): printer bawaan tampil sebagai printer Bluetooth virtual "InnerPrinter".
  bool get sunmi => produsen.toUpperCase().contains('SUNMI');

  /// iMin (D1, D3, D4, Swift, dll.): printer bawaan tersambung lewat USB internal.
  bool get imin => produsen.toUpperCase().contains('IMIN');
}

/// Jembatan ke `KanalUsbPrinter.kt` (Android, PRD v1.96): printer USB lewat USB host dan info perangkat.
class KanalUsbPrinter {
  const KanalUsbPrinter([this._kanal = const MethodChannel(namaKanal)]);

  static const String namaKanal = 'id.payoung.kasir/usb-printer';

  final MethodChannel _kanal;

  Future<InfoPerangkatAndroid> AmbilInfo() async {
    final info = await _Panggil<Map<Object?, Object?>>('InfoPerangkat') ?? const {};
    String Teks(String kunci) => info[kunci] is String ? info[kunci]! as String : '';
    return InfoPerangkatAndroid(produsen: Teks('Produsen'), model: Teks('Model'), versiAndroid: Teks('VersiAndroid'));
  }

  /// Printer USB yang tersambung; alamat = `VID:PID` heksadesimal.
  Future<List<PrinterDitemukan>> Daftar({JenisTransport jenis = JenisTransport.Usb}) async {
    final daftar = await _Panggil<List<Object?>>('Daftar') ?? const [];
    return [
      for (final p in daftar.whereType<Map<Object?, Object?>>())
        if (p['Alamat'] is String)
          PrinterDitemukan(
            jenis: jenis,
            alamat: p['Alamat']! as String,
            nama: p['Nama'] is String ? p['Nama']! as String : p['Alamat']! as String,
          ),
    ];
  }

  Future<void> Kirim(String alamat, List<int> data) =>
      _Panggil<void>('Kirim', {'Alamat': alamat, 'Data': Uint8List.fromList(data)});

  Future<T?> _Panggil<T>(String metode, [Object? argumen]) async {
    try {
      return await _kanal.invokeMethod<T>(metode, argumen);
    } on PlatformException catch (galat) {
      throw GalatPrinter(galat.message ?? 'Printer USB tidak bisa dipakai. Coba lagi.');
    } on MissingPluginException {
      throw const GalatPrinter('Printer USB lewat kabel OTG hanya didukung di Android dan Windows.');
    }
  }
}

/// Transport USB Android: satu sambungan per pekerjaan cetak (dibuka & ditutup oleh Kotlin).
class TransportUsbAndroid implements TransportPrinter {
  const TransportUsbAndroid(this.alamat, [this.kanal = const KanalUsbPrinter()]);

  final String alamat;
  final KanalUsbPrinter kanal;

  @override
  Future<void> Kirim(List<int> data) => kanal.Kirim(alamat, data);
}
