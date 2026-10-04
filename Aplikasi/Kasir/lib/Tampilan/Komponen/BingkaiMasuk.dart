import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';

/// Bingkai layar sebelum ruang kerja terbuka: aktivasi perangkat (F-02 langkah 5), pilih kasir dan buka shift
/// (F-06 langkah 1 & 2).
///
/// Ketiganya memakai satu bingkai supaya perangkat kasir terasa satu aplikasi sejak layar pertama, bukan tiga
/// formulir lepas: panel merek berisi logo, identitas perangkat, dan tiga janji yang paling sering ditanyakan
/// kasir di lapangan (bisa offline, data aman, sinkron otomatis), lalu kartu isian di sebelahnya.
///
/// Di layar lega (≥ [lebarLega]) panel merek berdiri di kiri; di layar sempit ia menjadi kepala di atas kartu,
/// supaya isian tetap mendapat hampir seluruh tinggi layar.
class BingkaiMasuk extends ConsumerWidget {
  const BingkaiMasuk({
    super.key,
    required this.judul,
    required this.isi,
    this.keterangan,
    this.aksi = const [],
    this.catatan,
    this.lebarIsi = 420,
  });

  /// Batas lebar layar untuk tata letak dua kolom.
  static const double lebarLega = 900;

  /// Lebar panel merek di tata letak dua kolom.
  static const double lebarPanelMerek = 360;

  /// Judul kartu isian; satu-satunya judul di layar ini.
  final String judul;

  /// Kalimat penjelas di bawah judul.
  final String? keterangan;

  /// Isi formulir.
  final Widget isi;

  /// Aksi sekunder di kaki kartu (mis. "Perbarui data kasir", "Ganti kasir").
  final List<Widget> aksi;

  /// Catatan kecil di bawah kartu, mis. penjelasan bahwa layar ini tetap jalan tanpa internet.
  final String? catatan;

  /// Lebar maksimum kartu isian.
  final double lebarIsi;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warna = TokenWarna.AmbilDari(context);
    final lega = MediaQuery.sizeOf(context).width >= lebarLega;
    final identitas = ref.watch(penyediaIdentitas).value;
    final perangkat = identitas == null
        ? ''
        : [identitas.outlet, identitas.perangkat].where((b) => b.isNotEmpty).join(' | ');

    // Ikon status bar dibuat terang: di kedua tata letak, puncak layar adalah panel merek yang gelap.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: warna.brandGelap),
      child: Scaffold(
        body: lega
            ? Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SizedBox(
                    width: lebarPanelMerek,
                    child: _PanelMerek(perangkat: perangkat, tegak: true),
                  ),
                  Expanded(child: _Kartu(context, warna)),
                ],
              )
            : Column(
                children: [
                  _PanelMerek(perangkat: perangkat, tegak: false),
                  Expanded(child: _Kartu(context, warna)),
                ],
              ),
      ),
    );
  }

  Widget _Kartu(BuildContext context, TokenWarna warna) {
    final teks = Theme.of(context).textTheme;
    // Di HP jarak dikecilkan supaya isian selebar mungkin: papan PIN butuh 288dp bersih, dan layar 360dp hanya
    // menyisakan itu bila tepi luar dan tepi kartu sama-sama 16dp.
    final rapat = MediaQuery.sizeOf(context).width < 600;
    final jarakTepi = rapat ? TokenJarak.jarak16 : TokenJarak.jarak24;
    return SafeArea(
      top: false,
      child: Center(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(jarakTepi),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: lebarIsi),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // `Material`, bukan `Container` berwarna: isian bisa memuat `ListTile`/`SwitchListTile`, yang
                // melukis latar & riak sentuhnya di `Material` terdekat.
                Material(
                  color: warna.permukaan,
                  shape: RoundedRectangleBorder(
                    side: BorderSide(color: warna.garis),
                    borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
                  ),
                  child: Padding(
                    padding: EdgeInsets.all(jarakTepi),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(judul, style: teks.headlineSmall),
                        if (keterangan case final String penjelas) ...[
                          const SizedBox(height: TokenJarak.jarak8),
                          Text(penjelas, style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
                        ],
                        const SizedBox(height: TokenJarak.jarak24),
                        isi,
                        if (aksi.isNotEmpty) ...[
                          const SizedBox(height: TokenJarak.jarak16),
                          Divider(height: TokenJarak.tebalGaris, color: warna.garis),
                          const SizedBox(height: TokenJarak.jarak8),
                          Wrap(
                            alignment: WrapAlignment.center,
                            spacing: TokenJarak.jarak8,
                            runSpacing: TokenJarak.jarak4,
                            children: aksi,
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                if (catatan case final String nota) ...[
                  const SizedBox(height: TokenJarak.jarak12),
                  Text(
                    nota,
                    textAlign: TextAlign.center,
                    style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Panel merek: kolom penuh di layar lega, kepala pendek di layar sempit.
class _PanelMerek extends StatelessWidget {
  const _PanelMerek({required this.perangkat, required this.tegak});

  final String perangkat;
  final bool tegak;

  /// Janji yang paling sering ditanyakan kasir sebelum mulai (PRD §17.2.6, BR-06.3): semuanya benar apa adanya.
  static const List<(IconData, String)> _janji = [
    (Icons.wifi_off_outlined, 'Tetap jalan tanpa internet'),
    (Icons.lock_outline, 'Data transaksi tersimpan di perangkat'),
    (Icons.cloud_upload_outlined, 'Terkirim otomatis begitu online'),
  ];

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final gayaPerangkat = teks.labelLarge?.copyWith(color: warna.permukaan.withValues(alpha: 0.85));

    return Material(
      color: warna.brandGelap,
      child: SafeArea(
        bottom: tegak,
        child: Padding(
          padding: EdgeInsets.all(tegak ? TokenJarak.jarak32 : TokenJarak.jarak24),
          child: tegak
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const LogoMerek.lengkapPutih(tinggi: 40),
                    if (perangkat.isNotEmpty) ...[
                      const SizedBox(height: TokenJarak.jarak12),
                      Text(perangkat, style: gayaPerangkat),
                    ],
                    // Janji diletakkan di tengah ruang sisa, bukan menempel di kaki panel: di layar tinggi
                    // (mis. iPad tegak) jaraknya jadi tidak menganga.
                    const Spacer(),
                    for (final (ikon, kalimat) in _PanelMerek._janji)
                      Padding(
                        padding: const EdgeInsets.only(bottom: TokenJarak.jarak16),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(ikon, size: TokenJarak.ikonSedang, color: warna.permukaan),
                            const SizedBox(width: TokenJarak.jarak12),
                            Expanded(
                              child: Text(kalimat, style: teks.bodyMedium?.copyWith(color: warna.permukaan)),
                            ),
                          ],
                        ),
                      ),
                    const Spacer(),
                  ],
                )
              : Row(
                  children: [
                    const LogoMerek.lengkapPutih(tinggi: 32),
                    const SizedBox(width: TokenJarak.jarak16),
                    Expanded(
                      child: Text(
                        perangkat,
                        style: gayaPerangkat,
                        textAlign: TextAlign.end,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}
