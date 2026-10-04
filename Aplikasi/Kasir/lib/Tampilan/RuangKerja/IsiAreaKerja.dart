import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Kerangka isi area kerja layar non-Jual: kepala (judul + tombol aksi dalam **satu baris**) lalu isi yang bisa
/// digulir, dengan jarak kisi 8dp. Layar fitur di dalam ruang kerja memakai ini alih-alih `Scaffold`/`AppBar` sendiri
/// (bingkai sudah menyediakan bilah atas & navigasi).
///
/// Padat (v4.11, permintaan pemilik produk): isi memakai lebar area kerja sampai [lebarMaksimum] (bukan kolom sempit
/// ±720dp di tengah yang meninggalkan ruang kosong di kiri-kanan), tepi 16dp (12dp di HP), dan tombol aksi layar
/// berada di kanan judul alih-alih bertumpuk di bawahnya. Angka ringkas memakai `DeretKartuAngka`.
///
/// [kolomGanda] untuk layar yang isinya banyak bagian berdiri sendiri (mis. Pengaturan): di layar lebar bagian-bagian
/// itu dibagi dua kolom supaya tidak jadi gulungan panjang di sebelah ruang kosong.
///
/// D-40 "dua kolom kerja": [panelSamping] adalah panel konteks di kanan (seperti panel keranjang di layar Jual):
/// permukaan putih setinggi area kerja selebar [lebarPanelSamping], bisa digulir sendiri. Di area kerja yang lebih
/// sempit dari [lebarMinimumPanelSamping] isinya turun ke bawah isi utama.
class IsiAreaKerja extends StatelessWidget {
  const IsiAreaKerja({
    super.key,
    required this.judul,
    required this.anak,
    this.aksi = const [],
    this.lebarMaksimum = 1200,
    this.kolomGanda = false,
    this.panelSamping = const [],
  });

  /// D-40: lebar panel konteks kanan.
  static const double lebarPanelSamping = 380;

  /// D-40: lebar area kerja minimum agar panel konteks tampil di samping (bukan di bawah) isi utama.
  static const double lebarMinimumPanelSamping = 960;

  /// Batas lebar isi saat [kolomGanda] aktif dan layarnya memang cukup lebar.
  static const double lebarKolomGanda = 1200;

  /// Lebar area kerja minimum untuk dua kolom: di bawah ini satu kolom tetap lebih terbaca.
  static const double lebarMinimumKolomGanda = 840;

  final String judul;
  final List<Widget> anak;

  /// Tombol aksi layar (misal "Kas masuk", "Tutup shift"), tampil sebaris di kanan judul.
  final List<Widget> aksi;
  final double lebarMaksimum;
  final bool kolomGanda;

  /// D-40: isi panel konteks kanan; kosong = layar satu kolom seperti sebelumnya.
  final List<Widget> panelSamping;

  @override
  Widget build(BuildContext context) {
    final lebarLayar = MediaQuery.sizeOf(context).width;
    final sempit = lebarLayar < 600;
    final tepi = sempit ? TokenJarak.jarak12 : TokenJarak.jarak16;

    return LayoutBuilder(
      builder: (context, batas) {
        final duaKolom = kolomGanda && batas.maxWidth >= lebarMinimumKolomGanda;
        final lebarIsi = duaKolom ? lebarKolomGanda : lebarMaksimum;
        final judulTeks = Semantics(header: true, child: Text(judul, style: Theme.of(context).textTheme.titleLarge));
        final tombol = Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          // Rata kiri: bila tombol turun ke baris berikutnya, tombol yang terbungkus tidak menggantung di kanan.
          alignment: WrapAlignment.start,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: aksi,
        );

        final kepala = aksi.isEmpty
            ? judulTeks
            // Judul kiri, tombol kanan selama muat sebaris; bila tidak muat (HP, judul panjang, banyak tombol)
            // tombol turun ke baris berikutnya alih-alih meluap.
            : Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: TokenJarak.jarak16,
                runSpacing: TokenJarak.jarak8,
                children: [judulTeks, tombol],
              );

        if (panelSamping.isNotEmpty) {
          final berdampingan = batas.maxWidth >= lebarMinimumPanelSamping;
          // Isi halaman berpanel pendek: dibangun utuh (bukan lazy) supaya bagian di bawah lipatan tetap ada untuk
          // pembaca layar & pencarian, dan gulir kedua kolom saling lepas.
          final utama = SingleChildScrollView(
            padding: EdgeInsets.all(tepi),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                kepala,
                const SizedBox(height: TokenJarak.jarak16),
                ...anak,
                if (!berdampingan) ...[
                  const SizedBox(height: TokenJarak.jarak16),
                  KotakPanel(
                    anak: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: panelSamping),
                  ),
                ],
              ],
            ),
          );
          if (!berdampingan) {
            return utama;
          }
          final warna = TokenWarna.AmbilDari(context);
          return Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(child: utama),
              DecoratedBox(
                decoration: BoxDecoration(
                  color: warna.permukaan,
                  border: Border(
                    left: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
                  ),
                ),
                child: SizedBox(
                  width: lebarPanelSamping,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(TokenJarak.jarak16),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: panelSamping),
                  ),
                ),
              ),
            ],
          );
        }

        return Align(
          alignment: Alignment.topLeft,
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: lebarIsi),
            child: ListView(
              padding: EdgeInsets.all(tepi),
              children: [
                kepala,
                const SizedBox(height: TokenJarak.jarak12),
                if (duaKolom) _DuaKolom(anak: anak) else ...anak,
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Bagi bagian-bagian isi menjadi dua kolom secara berselang-seling, lalu rata atas. Berselang-seling (bukan
/// separuh-separuh) supaya urutan bacanya kiri-kanan seperti membaca biasa, dan tinggi kedua kolom tidak jomplang
/// ketika satu bagian jauh lebih panjang.
class _DuaKolom extends StatelessWidget {
  const _DuaKolom({required this.anak});

  final List<Widget> anak;

  @override
  Widget build(BuildContext context) {
    final kiri = <Widget>[];
    final kanan = <Widget>[];
    for (final (indeks, bagian) in anak.indexed) {
      (indeks.isEven ? kiri : kanan).add(bagian);
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: kiri),
        ),
        const SizedBox(width: TokenJarak.jarak16),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: kanan),
        ),
      ],
    );
  }
}
