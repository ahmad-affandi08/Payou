import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/services.dart';

import '../../Domain/Struk/PemindaiPrinter.dart';

/// Jembatan ke `KanalBluetoothKlasik.kt` (Android): printer Bluetooth Classic SPP yang sudah di-pair (PRD v1.80).
class KanalBluetoothKlasik {
  const KanalBluetoothKlasik([this._kanal = const MethodChannel(namaKanal)]);

  static const String namaKanal = 'id.payoung.kasir/bluetooth-klasik';

  final MethodChannel _kanal;

  /// null = siap; selain itu pesan untuk kasir.
  Future<String?> Siapkan() async {
    final status = await _Panggil<Map<Object?, Object?>>('Status') ?? const {};
    if (status['Didukung'] != true) {
      return 'Perangkat ini tidak punya Bluetooth. Pakai printer LAN/Wi-Fi.';
    }
    if (status['Izin'] != true && await _Panggil<bool>('MintaIzin') != true) {
      return 'Izin Perangkat di sekitar ditolak. Buka Pengaturan Android › Aplikasi › Payoung POS › Izin, lalu izinkan.';
    }
    if (status['Aktif'] != true) {
      return 'Bluetooth mati. Nyalakan Bluetooth, lalu coba lagi.';
    }
    return null;
  }

  Future<List<PrinterDitemukan>> DaftarTerpasang() async {
    final daftar = await _Panggil<List<Object?>>('DaftarTerpasang') ?? const [];
    return [
      for (final p in daftar.whereType<Map<Object?, Object?>>())
        if (p['Alamat'] is String)
          PrinterDitemukan(
            jenis: JenisTransport.BluetoothKlasik,
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
      throw GalatPrinter(galat.message ?? 'Printer Bluetooth tidak bisa dipakai. Coba lagi.');
    } on MissingPluginException {
      throw const GalatPrinter('Printer Bluetooth Classic hanya didukung di Android dan Windows.');
    }
  }
}

/// Transport Bluetooth Classic Android: satu sambungan RFCOMM per pekerjaan cetak (dibuka & ditutup oleh Kotlin).
class TransportBluetoothKlasikAndroid implements TransportPrinter {
  const TransportBluetoothKlasikAndroid(this.alamat, [this.kanal = const KanalBluetoothKlasik()]);

  final String alamat;
  final KanalBluetoothKlasik kanal;

  @override
  Future<void> Kirim(List<int> data) => kanal.Kirim(alamat, data);
}
