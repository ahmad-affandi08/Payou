import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';

import '../../Data/RepositoriKasir.dart';

/// Printer struk perangkat ini (PRD §17.2.5, v1.79–v1.80). Disimpan lokal di tabel `Pengaturan` (kunci `ProfilPrinter`,
/// JSON): tidak ikut data awal dan tetap ada saat data kasir diperbarui. [alamat] menurut [jenis]:
/// - `Jaringan`: IP/nama host (+ [port]);
/// - `BluetoothKlasik`: alamat MAC printer yang sudah di-pair (Android) atau nama COM port Bluetooth (Windows);
/// - `Ble`: id perangkat BLE dari pemindaian (iOS, Android, Windows).
class ProfilPrinter {
  const ProfilPrinter({
    required this.alamat,
    this.jenis = JenisTransport.Jaringan,
    this.nama,
    this.port = TransportJaringan.portBawaan,
    this.lebar = LebarKertas.Mm58,
    this.cetakOtomatis = true,
    this.bukaLaciTunai = true,
  });

  /// Alamat profil printer sistem (v1.97): printer dipilih di dialog cetak sistem.
  static const String alamatSistem = 'Sistem';

  /// Printer sistem sebagai cadangan saat printer thermal bermasalah (tanpa laci).
  static ProfilPrinter Sistem(LebarKertas lebar) => ProfilPrinter(
    jenis: JenisTransport.CetakSistem,
    alamat: alamatSistem,
    nama: 'Printer sistem',
    lebar: lebar,
    bukaLaciTunai: false,
  );

  final String alamat;
  final JenisTransport jenis;

  /// Nama printer untuk ditampilkan (Bluetooth); null = pakai [alamat].
  final String? nama;
  final int port;
  final LebarKertas lebar;

  /// Cetak struk otomatis setelah pembayaran tersimpan.
  final bool cetakOtomatis;

  /// Buka laci kas (lewat printer) saat pembayaran memuat tunai.
  final bool bukaLaciTunai;

  String get label => switch (jenis) {
    JenisTransport.Jaringan => 'LAN/Wi-Fi $alamat:$port | ${lebar.label}',
    JenisTransport.CetakSistem => 'Printer sistem (PDF/AirPrint/driver) | ${lebar.label}',
    _ => '${jenis.label} ${nama ?? alamat} | ${lebar.label}',
  };

  /// Alamat IPv4 atau nama host sederhana; port 1–65535.
  static String? ValidasiAlamat(String alamat) {
    final teks = alamat.trim();
    if (teks.isEmpty) {
      return 'Isi alamat IP printer, misal 192.168.1.50.';
    }
    final ipv4 = RegExp(r'^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$').firstMatch(teks);
    if (ipv4 != null) {
      final sah = [1, 2, 3, 4].every((i) => int.parse(ipv4.group(i)!) <= 255);
      return sah ? null : 'Alamat IP tidak valid. Setiap angka 0 sampai 255.';
    }
    return RegExp(r'^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$').hasMatch(teks)
        ? null
        : 'Alamat printer hanya berisi angka, huruf, titik, atau tanda hubung.';
  }

  static String? ValidasiPort(String port) {
    final angka = int.tryParse(port.trim());
    return angka == null || angka < 1 || angka > 65535 ? 'Port berupa angka 1 sampai 65535, biasanya 9100.' : null;
  }

  ProfilPrinter copyWith({String? alamat, int? port, LebarKertas? lebar, bool? cetakOtomatis, bool? bukaLaciTunai}) =>
      ProfilPrinter(
        alamat: alamat ?? this.alamat,
        jenis: jenis,
        nama: nama,
        port: port ?? this.port,
        lebar: lebar ?? this.lebar,
        cetakOtomatis: cetakOtomatis ?? this.cetakOtomatis,
        bukaLaciTunai: bukaLaciTunai ?? this.bukaLaciTunai,
      );

  Map<String, Object?> KeJson() => {
    'Jenis': jenis.name,
    'Alamat': alamat,
    'Nama': nama,
    'Port': port,
    'Lebar': lebar.name,
    'CetakOtomatis': cetakOtomatis,
    'BukaLaciTunai': bukaLaciTunai,
  };

  static ProfilPrinter? DariJson(Object? json) {
    if (json is! Map<String, Object?>) {
      return null;
    }
    final alamat = json['Alamat'];
    final port = json['Port'];
    final jenis = JenisTransport.values.where((j) => j.name == json['Jenis']).firstOrNull;
    if (alamat is! String || alamat.isEmpty || port is! int || jenis == null) {
      return null;
    }
    final nama = json['Nama'];
    return ProfilPrinter(
      alamat: alamat,
      jenis: jenis,
      nama: nama is String && nama.isNotEmpty ? nama : null,
      port: port,
      lebar: LebarKertas.values.where((l) => l.name == json['Lebar']).firstOrNull ?? LebarKertas.Mm58,
      cetakOtomatis: json['CetakOtomatis'] != false,
      bukaLaciTunai: json['BukaLaciTunai'] != false,
    );
  }

  /// null = printer belum diatur (atau data rusak).
  static Future<ProfilPrinter?> Muat(RepositoriKasir repositori) async {
    final teks = await repositori.AmbilPengaturan(KunciPengaturan.profilPrinter);
    if (teks == null || teks.isEmpty) {
      return null;
    }
    try {
      return DariJson(jsonDecode(teks));
    } on FormatException {
      return null;
    }
  }

  Future<void> Simpan(RepositoriKasir repositori) =>
      repositori.SimpanPengaturan(KunciPengaturan.profilPrinter, jsonEncode(KeJson()));

  static Future<void> Hapus(RepositoriKasir repositori) =>
      repositori.SimpanPengaturan(KunciPengaturan.profilPrinter, '');
}
