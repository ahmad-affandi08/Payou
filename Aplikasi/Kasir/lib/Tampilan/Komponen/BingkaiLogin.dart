import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../RuangKerja/JamRuangKerja.dart';

/// Bingkai halaman login kasir (D-60): dua kolom seperti halaman login pada umumnya. Kolom kiri berupa panel merek
/// bergaya iklan (judul besar, tiga keunggulan, identitas perangkat, sapaan & jam); kolom kanan berisi formulir
/// ([isi]) di tengah. Di layar sempit panel kiri menjadi kepala pendek di atas formulir.
class BingkaiLogin extends ConsumerWidget {
  const BingkaiLogin({super.key, required this.isi, this.aksi = const [], this.lebarIsi = 400});

  /// Batas lebar layar untuk tata letak dua kolom.
  static const double lebarLega = 900;

  /// Isi formulir (judul, pilih nama, PIN, keypad).
  final Widget isi;

  /// Aksi sekunder di kaki formulir.
  final List<Widget> aksi;

  final double lebarIsi;

  static const List<String> _hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
  static const List<String> _bulan = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];

  /// Sapaan menurut jam perangkat: pagi 04-10, siang 11-14, sore 15-17, selain itu malam.
  static String Sapaan(DateTime waktu) {
    final jam = waktu.hour;
    if (jam >= 4 && jam < 11) return 'Selamat pagi';
    if (jam >= 11 && jam < 15) return 'Selamat siang';
    if (jam >= 15 && jam < 18) return 'Selamat sore';
    return 'Selamat malam';
  }

  static String Tanggal(DateTime waktu) =>
      '${_hari[waktu.weekday - 1]}, ${waktu.day} ${_bulan[waktu.month - 1]} ${waktu.year}';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warna = TokenWarna.AmbilDari(context);
    final lega = MediaQuery.sizeOf(context).width >= lebarLega;
    final identitas = ref.watch(penyediaIdentitas).value;
    final sekarang = ref.read(penyediaJam)();
    final perangkat = identitas == null
        ? ''
        : [identitas.outlet, identitas.perangkat].where((b) => b.isNotEmpty).join(' | ');

    final formulir = _Formulir(isi: isi, aksi: aksi, lebarIsi: lebarIsi, rapat: !lega);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: warna.brandGelap),
      child: Scaffold(
        backgroundColor: warna.permukaan,
        body: lega
            ? Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    flex: 11,
                    child: _PanelIklan(perangkat: perangkat, sekarang: sekarang),
                  ),
                  Expanded(flex: 9, child: formulir),
                ],
              )
            : Column(
                children: [
                  _KepalaRingkas(perangkat: perangkat),
                  Expanded(child: formulir),
                ],
              ),
      ),
    );
  }
}

class _Formulir extends StatelessWidget {
  const _Formulir({required this.isi, required this.aksi, required this.lebarIsi, required this.rapat});

  final Widget isi;
  final List<Widget> aksi;
  final double lebarIsi;
  final bool rapat;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    return SafeArea(
      top: false,
      child: Center(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(rapat ? TokenJarak.jarak16 : TokenJarak.jarak32),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: lebarIsi),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
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
      ),
    );
  }
}

/// Panel kiri bergaya iklan.
class _PanelIklan extends StatelessWidget {
  const _PanelIklan({required this.perangkat, required this.sekarang});

  final String perangkat;
  final DateTime sekarang;

  static const List<(IconData, String, String)> _keunggulan = [
    (
      Icons.wifi_off_outlined,
      'Tetap jalan tanpa internet',
      'Transaksi tersimpan di perangkat, terkirim otomatis saat online.',
    ),
    (Icons.qr_code_2, 'QRIS dan semua cara bayar', 'Tunai, QRIS, kartu, hingga bayar nanti dalam satu layar.'),
    (
      Icons.insights_outlined,
      'Stok dan laporan otomatis',
      'Setiap penjualan langsung memotong stok dan masuk laporan.',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final putih = warna.permukaan;
    return Material(
      color: warna.brandGelap,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak32 + TokenJarak.jarak16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const LogoMerek.lengkapPutih(tinggi: 44),
              const Spacer(),
              Text('Jualan lancar,\nlaporan rapi.', style: teks.displayMedium?.copyWith(color: putih, height: 1.15)),
              const SizedBox(height: TokenJarak.jarak16),
              Text(
                'Kasir yang tenang dipakai berjam-jam, dari warung sampai toko dengan banyak cabang.',
                style: teks.bodyLarge?.copyWith(color: putih.withValues(alpha: 0.85)),
              ),
              const SizedBox(height: TokenJarak.jarak32),
              for (final (ikon, judul, uraian) in _keunggulan)
                Padding(
                  padding: const EdgeInsets.only(bottom: TokenJarak.jarak16),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      DecoratedBox(
                        decoration: BoxDecoration(
                          color: putih.withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(10),
                          child: Icon(ikon, size: TokenJarak.ikonBesar, color: putih),
                        ),
                      ),
                      const SizedBox(width: TokenJarak.jarak16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(judul, style: teks.titleMedium?.copyWith(color: putih)),
                            const SizedBox(height: 2),
                            Text(uraian, style: teks.bodyMedium?.copyWith(color: putih.withValues(alpha: 0.78))),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              const Spacer(),
              Divider(color: putih.withValues(alpha: 0.2), height: TokenJarak.tebalGaris),
              const SizedBox(height: TokenJarak.jarak16),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(BingkaiLogin.Sapaan(sekarang), style: teks.titleMedium?.copyWith(color: putih)),
                        Text(
                          BingkaiLogin.Tanggal(sekarang),
                          style: teks.bodySmall?.copyWith(color: putih.withValues(alpha: 0.78)),
                        ),
                        if (perangkat.isNotEmpty) ...[
                          const SizedBox(height: TokenJarak.jarak8),
                          Row(
                            children: [
                              Icon(Icons.storefront_outlined, size: TokenJarak.ikonKecil, color: putih),
                              const SizedBox(width: TokenJarak.jarak8),
                              Flexible(
                                child: Text(
                                  perangkat,
                                  overflow: TextOverflow.ellipsis,
                                  style: teks.labelLarge?.copyWith(color: putih),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                  JamRuangKerja(
                    gaya: teks.displaySmall?.copyWith(color: putih, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Kepala pendek di layar sempit: logo di kiri, identitas perangkat di kanan.
class _KepalaRingkas extends StatelessWidget {
  const _KepalaRingkas({required this.perangkat});

  final String perangkat;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    return Material(
      color: warna.brandGelap,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak16),
          child: Row(
            children: [
              const LogoMerek.lengkapPutih(tinggi: 32),
              const SizedBox(width: TokenJarak.jarak16),
              Expanded(
                child: Text(
                  perangkat,
                  textAlign: TextAlign.end,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: teks.labelLarge?.copyWith(color: warna.permukaan.withValues(alpha: 0.85)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
