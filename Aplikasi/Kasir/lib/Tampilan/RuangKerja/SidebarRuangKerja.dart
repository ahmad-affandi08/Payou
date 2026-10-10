import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

import 'ItemNavigasi.dart';
import 'TemaNavigasiRuangKerja.dart';

/// Susunan item sidebar menurut keadaan dan lebar layar.
enum SusunanSidebar {
  /// Tertutup: ikon saja, nama menu muncul sebagai tooltip.
  IkonSaja,

  /// Terbuka di layar sedang (600-1023dp): label kecil di bawah ikon, tidak memakan ruang kerja.
  LabelDiBawah,

  /// Terbuka di layar lebar (≥ 1024dp): ikon dan label sebaris.
  Penuh,
}

/// Sidebar bingkai Ruang Kerja (PRD §17.2.7, D-16, D-66): menggantikan `NavigationRail` bawaan Flutter.
///
/// Penanda item aktif adalah pil apricot yang menutup **seluruh baris** (ikon dan label) dengan teks merek gelap di
/// dalamnya. `NavigationRail` yang diperluas hanya menggambar pil seukuran ikon, sehingga label aktif berwarna gelap
/// jatuh di atas latar rel yang juga gelap dan tidak terbaca.
///
/// Menu utama berada di atas dan bisa digulir; Sinkron dan Pengaturan tertambat di bawah supaya tidak pernah
/// terpotong. Lebarnya beranimasi (dimatikan bila pengguna meminta kurangi gerak).
class SidebarRuangKerja extends StatelessWidget {
  const SidebarRuangKerja({
    super.key,
    required this.item,
    required this.indeks,
    required this.diciutkan,
    required this.lebarPenuh,
    required this.saatPilih,
    required this.saatUbahLebar,
  });

  /// Item yang tampil (sudah disaring izin & modul) dan indeks item aktif di dalamnya.
  final List<ItemNavigasi> item;
  final int indeks;

  /// Tertutup (ikon saja). Bawaan aplikasi: tertutup sampai kasir melebarkannya (D-66).
  final bool diciutkan;

  /// Layar cukup lebar untuk susunan [SusunanSidebar.Penuh] saat terbuka.
  final bool lebarPenuh;

  final ValueChanged<TujuanRuangKerja> saatPilih;
  final VoidCallback saatUbahLebar;

  static const double lebarIkonSaja = 72;
  static const double lebarLabelDiBawah = 88;
  static const double lebarPenuhPiksel = 232;

  /// Lama animasi lebar (dimatikan bila pengguna meminta kurangi gerak) dan opasitas garis pemisah dasar.
  static const Duration lamaAnimasi = Duration(milliseconds: 200);
  static const double opasitasGaris = 0.14;

  /// Tujuan yang tertambat di dasar sidebar.
  static const Set<TujuanRuangKerja> _tujuanDasar = {TujuanRuangKerja.StatusSinkron, TujuanRuangKerja.Pengaturan};

  SusunanSidebar get susunan =>
      diciutkan ? SusunanSidebar.IkonSaja : (lebarPenuh ? SusunanSidebar.Penuh : SusunanSidebar.LabelDiBawah);

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final lebar = switch (susunan) {
      SusunanSidebar.IkonSaja => lebarIkonSaja,
      SusunanSidebar.LabelDiBawah => lebarLabelDiBawah,
      SusunanSidebar.Penuh => lebarPenuhPiksel,
    };
    final durasi = MediaQuery.disableAnimationsOf(context) ? Duration.zero : lamaAnimasi;
    final utama = [
      for (var i = 0; i < item.length; i++)
        if (!_tujuanDasar.contains(item[i].tujuan)) i,
    ];
    final dasar = [
      for (var i = 0; i < item.length; i++)
        if (_tujuanDasar.contains(item[i].tujuan)) i,
    ];

    return AnimatedContainer(
      duration: durasi,
      curve: Curves.easeOutCubic,
      width: lebar,
      child: Material(
        color: warna.brandGelap,
        child: FocusTraversalGroup(
          child: Column(
            children: [
              const SizedBox(height: TokenJarak.jarak12),
              _BarisSidebar(
                kunci: const ValueKey('SidebarLebar'),
                ikon: diciutkan ? Icons.menu : Icons.menu_open,
                label: diciutkan ? 'Lebarkan menu' : 'Ciutkan menu',
                aktif: false,
                susunan: susunan,
                tanpaLabelBawah: true,
                saatKetuk: saatUbahLebar,
              ),
              const SizedBox(height: TokenJarak.jarak8),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak8),
                  children: [for (final i in utama) _BuatBaris(i)],
                ),
              ),
              if (dasar.isNotEmpty) ...[
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak4),
                  child: Divider(height: 1, color: warna.permukaan.withValues(alpha: opasitasGaris)),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak8),
                  child: Column(children: [for (final i in dasar) _BuatBaris(i)]),
                ),
              ],
              const SizedBox(height: TokenJarak.jarak12),
            ],
          ),
        ),
      ),
    );
  }

  Widget _BuatBaris(int i) {
    final aktif = i == indeks;
    return _BarisSidebar(
      kunci: ValueKey('ItemSidebar.${item[i].tujuan.name}'),
      ikon: aktif ? item[i].ikonAktif : item[i].ikon,
      label: item[i].label,
      aktif: aktif,
      susunan: susunan,
      saatKetuk: () => saatPilih(item[i].tujuan),
    );
  }
}

/// Satu baris sidebar: ikon + label, pil apricot penuh bila aktif.
class _BarisSidebar extends StatelessWidget {
  const _BarisSidebar({
    required this.kunci,
    required this.ikon,
    required this.label,
    required this.aktif,
    required this.susunan,
    required this.saatKetuk,
    this.tanpaLabelBawah = false,
  });

  /// Kunci item (dipakai test); dipasang di `Material` penanda, bukan di widget baris.
  final Key kunci;
  final IconData ikon;
  final String label;
  final bool aktif;
  final SusunanSidebar susunan;
  final VoidCallback saatKetuk;

  /// Tombol lebar/ciut: pada susunan [SusunanSidebar.LabelDiBawah] tampil sebagai ikon saja (labelnya panjang).
  final bool tanpaLabelBawah;

  /// Sudut baris lebih membulat daripada kontrol biasa (`radiusKontrol` 6): baris sidebar adalah pil besar, bukan
  /// tombol, dan pil aktifnya memegang identitas bingkai kasir (D-66).
  static const double _radius = 14;

  /// Opasitas lapisan interaksi (putih di atas latar merek gelap).
  static const double _opasitasHover = 0.08;
  static const double _opasitasFokus = 0.14;
  static const double _opasitasSplash = 0.12;
  static const double _opasitasTekan = 0.06;
  static const double _tinggiBaris = 48;
  static const double _tinggiLabelDiBawah = 60;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final warnaIsi = aktif ? warna.brandGelap : warna.permukaan.withValues(alpha: TemaNavigasiRuangKerja.opasitasPasif);
    final tebal = aktif ? FontWeight.w700 : FontWeight.w500;
    final ikonWidget = Icon(ikon, color: warnaIsi, size: TokenJarak.ikonBesar);

    final Widget isi;
    final double tinggi;
    switch (tanpaLabelBawah && susunan == SusunanSidebar.LabelDiBawah ? SusunanSidebar.IkonSaja : susunan) {
      case SusunanSidebar.IkonSaja:
        tinggi = _tinggiBaris;
        isi = Center(child: ikonWidget);
      case SusunanSidebar.LabelDiBawah:
        tinggi = _tinggiLabelDiBawah;
        isi = Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            ikonWidget,
            const SizedBox(height: 2),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: Text(
                label,
                maxLines: 1,
                softWrap: false,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: (teks.labelSmall ?? const TextStyle()).copyWith(color: warnaIsi, fontWeight: tebal),
              ),
            ),
          ],
        );
      case SusunanSidebar.Penuh:
        tinggi = _tinggiBaris;
        // Lebar minimum baris = 12 + 24 + 12 = 48: pas di lebar item saat sidebar baru mulai melebar (72 - 16 - 8).
        isi = Row(
          children: [
            const SizedBox(width: TokenJarak.jarak12),
            ikonWidget,
            const SizedBox(width: TokenJarak.jarak12),
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                softWrap: false,
                overflow: TextOverflow.clip,
                style: (teks.labelLarge ?? const TextStyle()).copyWith(color: warnaIsi, fontWeight: tebal),
              ),
            ),
          ],
        );
    }

    final baris = Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      // Satu simpul semantik per baris: label dibaca sekali, bukan lagi oleh `Text` di dalamnya.
      child: Semantics(
        button: true,
        selected: aktif,
        label: label,
        onTap: saatKetuk,
        excludeSemantics: true,
        child: Material(
          key: kunci,
          color: aktif ? warna.aksen : Colors.transparent,
          shape: const RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(_radius))),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: saatKetuk,
            hoverColor: warna.permukaan.withValues(alpha: _opasitasHover),
            focusColor: warna.permukaan.withValues(alpha: _opasitasFokus),
            splashColor: warna.permukaan.withValues(alpha: _opasitasSplash),
            highlightColor: warna.permukaan.withValues(alpha: _opasitasTekan),
            child: SizedBox(height: tinggi, width: double.infinity, child: isi),
          ),
        ),
      ),
    );

    // Tertutup: hanya ikon yang terlihat, nama menu muncul saat ditahan atau disorot. Tombol lebar/ciut selalu
    // bertooltip karena di susunan LabelDiBawah ia hanya berupa ikon.
    return susunan == SusunanSidebar.IkonSaja || tanpaLabelBawah ? Tooltip(message: label, child: baris) : baris;
  }
}
