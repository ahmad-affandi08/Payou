import 'dart:async';
import 'dart:typed_data';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:universal_ble/universal_ble.dart';

import '../../Domain/Struk/PemindaiPrinter.dart';

/// Karakteristik GATT yang bisa ditulis.
class KarakteristikBle {
  const KarakteristikBle({
    required this.layanan,
    required this.uuid,
    required this.tulis,
    required this.tulisTanpaRespons,
  });

  final String layanan;
  final String uuid;
  final bool tulis;
  final bool tulisTanpaRespons;
}

/// Operasi BLE yang dipakai printer (dibungkus agar bisa diuji tanpa radio).
abstract interface class KlienBle {
  Future<String?> Siapkan();
  Future<List<PrinterDitemukan>> Pindai(Duration lama);
  Future<void> Sambungkan(String idPerangkat);
  Future<List<KarakteristikBle>> AmbilKarakteristik(String idPerangkat);
  Future<int> MintaMtu(String idPerangkat, int mtu);
  Future<void> Tulis(String idPerangkat, KarakteristikBle karakteristik, Uint8List data, {required bool tanpaRespons});
  Future<void> Putuskan(String idPerangkat);
}

/// Printer thermal BLE (iOS/iPadOS, Android, Windows; PRD v1.80). Data dikirim bertahap ke karakteristik tulis layanan
/// printer yang dikenal (18F0, E7810A71, 49535343-FE7D, FF00, FFE0, FFF0), atau karakteristik tulis pertama. Tulis dengan
/// respons diutamakan (printer tidak kewalahan); bila hanya tanpa respons, tiap potongan diberi jeda.
class TransportBle implements TransportPrinter {
  TransportBle(this.idPerangkat, this.klien, {this.jedaTanpaRespons = const Duration(milliseconds: 20)});

  final String idPerangkat;
  final KlienBle klien;
  final Duration jedaTanpaRespons;

  /// Potongan paling besar walau MTU lebih besar: buffer printer murah kecil.
  static const int potonganMaksimal = 180;

  /// Layanan printer thermal BLE yang umum (16-bit atau 128-bit, huruf kecil).
  static const List<String> layananPrinter = [
    '18f0',
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    'ff00',
    'ffe0',
    'fff0',
  ];

  @override
  Future<void> Kirim(List<int> data) async {
    try {
      await klien.Sambungkan(idPerangkat);
    } on GalatPrinter {
      rethrow;
    } on Object {
      throw const GalatPrinter(
        'Printer Bluetooth LE tidak tersambung. Pastikan printer menyala, dekat, dan tidak tersambung ke perangkat lain.',
      );
    }
    try {
      final karakteristik = PilihKarakteristik(await klien.AmbilKarakteristik(idPerangkat));
      if (karakteristik == null) {
        throw const GalatPrinter('Perangkat ini bukan printer Bluetooth LE yang dikenal. Pilih printer lain.');
      }
      var mtu = 23;
      try {
        mtu = await klien.MintaMtu(idPerangkat, 185);
      } on Object {
        // MTU diatur sistem (iOS) atau tidak didukung: pakai bawaan 23.
      }
      final ukuran = HitungUkuranPotongan(mtu);
      final tanpaRespons = !karakteristik.tulis;
      final bait = Uint8List.fromList(data);
      for (var mulai = 0; mulai < bait.length; mulai += ukuran) {
        final akhir = mulai + ukuran > bait.length ? bait.length : mulai + ukuran;
        await klien.Tulis(
          idPerangkat,
          karakteristik,
          Uint8List.sublistView(bait, mulai, akhir),
          tanpaRespons: tanpaRespons,
        );
        if (tanpaRespons) {
          await Future<void>.delayed(jedaTanpaRespons);
        }
      }
    } on GalatPrinter {
      rethrow;
    } on Object {
      throw const GalatPrinter('Struk gagal terkirim ke printer Bluetooth LE. Dekatkan printer, lalu coba cetak lagi.');
    } finally {
      try {
        await klien.Putuskan(idPerangkat);
      } on Object {
        // Sambungan sudah putus.
      }
    }
  }

  /// MTU − 3 byte kepala ATT, antara 20 dan [potonganMaksimal].
  static int HitungUkuranPotongan(int mtu) => (mtu - 3).clamp(20, potonganMaksimal);

  /// Karakteristik tulis di layanan printer yang dikenal (urut [layananPrinter]), selain itu yang pertama bisa ditulis.
  static KarakteristikBle? PilihKarakteristik(List<KarakteristikBle> daftar) {
    final bisaTulis = daftar.where((k) => k.tulis || k.tulisTanpaRespons).toList();
    for (final dikenal in layananPrinter) {
      final cocok = bisaTulis.where((k) => SamakanUuid(k.layanan) == SamakanUuid(dikenal)).firstOrNull;
      if (cocok != null) {
        return cocok;
      }
    }
    return bisaTulis.firstOrNull;
  }

  /// UUID 16-bit (`18f0`) dan bentuk lengkapnya (`000018f0-0000-1000-8000-00805f9b34fb`) dianggap sama.
  static String SamakanUuid(String uuid) {
    final kecil = uuid.toLowerCase().trim();
    if (RegExp(r'^[0-9a-f]{4}$').hasMatch(kecil)) {
      return '0000$kecil-0000-1000-8000-00805f9b34fb';
    }
    if (RegExp(r'^[0-9a-f]{8}$').hasMatch(kecil)) {
      return '$kecil-0000-1000-8000-00805f9b34fb';
    }
    return kecil;
  }
}

/// [KlienBle] di atas paket `universal_ble` (Android, iOS, macOS, Windows).
class KlienUniversalBle implements KlienBle {
  const KlienUniversalBle();

  @override
  Future<String?> Siapkan() async {
    try {
      await UniversalBle.requestPermissions();
    } on Object {
      return 'Izin Bluetooth ditolak. Izinkan Payoung POS memakai Bluetooth di pengaturan perangkat, lalu coba lagi.';
    }
    return switch (await UniversalBle.getBluetoothAvailabilityState()) {
      AvailabilityState.poweredOn => null,
      AvailabilityState.poweredOff => 'Bluetooth mati. Nyalakan Bluetooth, lalu coba lagi.',
      AvailabilityState.unauthorized =>
        'Izin Bluetooth ditolak. Izinkan Payoung POS memakai Bluetooth di pengaturan perangkat, lalu coba lagi.',
      AvailabilityState.unsupported => 'Perangkat ini tidak mendukung Bluetooth LE. Pakai printer LAN/Wi-Fi.',
      _ => 'Bluetooth belum siap. Tunggu sebentar, lalu coba lagi.',
    };
  }

  @override
  Future<List<PrinterDitemukan>> Pindai(Duration lama) async {
    final ditemukan = <String, PrinterDitemukan>{};
    final langganan = UniversalBle.scanStream.listen((perangkat) {
      final nama = perangkat.name?.trim();
      if (nama != null && nama.isNotEmpty) {
        ditemukan[perangkat.deviceId] = PrinterDitemukan(
          jenis: JenisTransport.Ble,
          alamat: perangkat.deviceId,
          nama: nama,
        );
      }
    });
    try {
      await UniversalBle.startScan();
      await Future<void>.delayed(lama);
    } finally {
      await UniversalBle.stopScan();
      await langganan.cancel();
    }
    return ditemukan.values.toList()..sort((a, b) => a.nama.compareTo(b.nama));
  }

  @override
  Future<void> Sambungkan(String idPerangkat) =>
      UniversalBle.connect(idPerangkat, timeout: const Duration(seconds: 10));

  @override
  Future<List<KarakteristikBle>> AmbilKarakteristik(String idPerangkat) async => [
    for (final layanan in await UniversalBle.discoverServices(idPerangkat))
      for (final k in layanan.characteristics)
        KarakteristikBle(
          layanan: layanan.uuid,
          uuid: k.uuid,
          tulis: k.properties.contains(CharacteristicProperty.write),
          tulisTanpaRespons: k.properties.contains(CharacteristicProperty.writeWithoutResponse),
        ),
  ];

  @override
  Future<int> MintaMtu(String idPerangkat, int mtu) => UniversalBle.requestMtu(idPerangkat, mtu);

  @override
  Future<void> Tulis(
    String idPerangkat,
    KarakteristikBle karakteristik,
    Uint8List data, {
    required bool tanpaRespons,
  }) => UniversalBle.write(idPerangkat, karakteristik.layanan, karakteristik.uuid, data, withoutResponse: tanpaRespons);

  @override
  Future<void> Putuskan(String idPerangkat) => UniversalBle.disconnect(idPerangkat);
}
