import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Tema navigasi bingkai Ruang Kerja (PRD §17.2.7, D-16): sidebar kiri (`SidebarRuangKerja`) dan bilah bawah memakai
/// warna merek yang sama dengan bilah atas, sehingga bingkai kasir terbaca sebagai satu kerangka.
///
/// Dipasang lokal di bingkai ini, bukan di `TemaDasar`: tema dasar dipakai bersama Aplikasi Pemilik, yang
/// navigasinya tetap berada di atas permukaan putih.
abstract final class TemaNavigasiRuangKerja {
  /// Opasitas item non-aktif di atas latar merek. Putih 75% di atas `brandGelap` masih berkontras ±5,9:1
  /// (lolos WCAG AA), tetapi cukup redup sehingga item aktif tetap yang paling menonjol.
  static const double opasitasPasif = 0.75;

  /// Penanda item aktif (D-66): pil berwarna aksen apricot di atas latar merek gelap, dengan ikon & label merek
  /// gelap di dalamnya. Apricot terhadap `brandGelap` berkontras ±7:1, jadi item aktif langsung terbaca tanpa
  /// harus membandingkan tingkat putih.

  /// Bentuk pil penanda item aktif (sudut membulat penuh).
  static const StadiumBorder _bentukPenanda = StadiumBorder();

  /// Label di bawah ikon (bilah bawah HP): aktif putih di atas latar merek, pasif lebih redup.
  static TextStyle _GayaLabel(TokenWarna warna, TextTheme teks, {required bool aktif}) =>
      (teks.labelMedium ?? const TextStyle()).copyWith(
        color: aktif ? warna.permukaan : warna.permukaan.withValues(alpha: opasitasPasif),
        fontWeight: aktif ? FontWeight.w700 : FontWeight.w500,
      );

  static IconThemeData _GayaIkon(TokenWarna warna, {required bool aktif}) => IconThemeData(
    color: aktif ? warna.brandGelap : warna.permukaan.withValues(alpha: opasitasPasif),
    size: TokenJarak.ikonBesar,
  );

  /// Tema bilah navigasi bawah (HP).
  static NavigationBarThemeData BuatTemaBilah(TokenWarna warna, TextTheme teks) => NavigationBarThemeData(
    backgroundColor: warna.brandGelap,
    elevation: 0,
    indicatorColor: warna.aksen,
    indicatorShape: _bentukPenanda,
    iconTheme: WidgetStateProperty.resolveWith(
      (keadaan) => _GayaIkon(warna, aktif: keadaan.contains(WidgetState.selected)),
    ),
    labelTextStyle: WidgetStateProperty.resolveWith(
      (keadaan) => _GayaLabel(warna, teks, aktif: keadaan.contains(WidgetState.selected)),
    ),
  );
}
