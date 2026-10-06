import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Tema navigasi bingkai Ruang Kerja (PRD §17.2.7, D-16): rel kiri dan bilah bawah memakai warna merek yang sama
/// dengan bilah atas, sehingga bingkai kasir terbaca sebagai satu kerangka — bukan tiga potongan terpisah.
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

  /// [labelDalamPenanda]: label berada di dalam pil (rel diperluas) sehingga memakai warna gelap; selain itu
  /// (label di bawah ikon) label tetap putih di atas latar merek.
  static TextStyle _GayaLabel(
    TokenWarna warna,
    TextTheme teks, {
    required bool aktif,
    bool labelDalamPenanda = false,
  }) =>
      (teks.labelMedium ?? const TextStyle()).copyWith(
        color: aktif
            ? (labelDalamPenanda ? warna.brandGelap : warna.permukaan)
            : warna.permukaan.withValues(alpha: opasitasPasif),
        fontWeight: aktif ? FontWeight.w700 : FontWeight.w500,
      );

  static IconThemeData _GayaIkon(TokenWarna warna, {required bool aktif}) => IconThemeData(
    color: aktif ? warna.brandGelap : warna.permukaan.withValues(alpha: opasitasPasif),
    size: TokenJarak.ikonBesar,
  );

  /// Tema rel navigasi kiri (tablet & desktop). [diperluas]: label di dalam pil penanda.
  static NavigationRailThemeData BuatTemaRel(TokenWarna warna, TextTheme teks, {bool diperluas = false}) =>
      NavigationRailThemeData(
        backgroundColor: warna.brandGelap,
        elevation: 0,
        indicatorColor: warna.aksen,
        indicatorShape: _bentukPenanda,
        useIndicator: true,
        selectedIconTheme: _GayaIkon(warna, aktif: true),
        unselectedIconTheme: _GayaIkon(warna, aktif: false),
        selectedLabelTextStyle: _GayaLabel(warna, teks, aktif: true, labelDalamPenanda: diperluas),
        unselectedLabelTextStyle: _GayaLabel(warna, teks, aktif: false),
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
