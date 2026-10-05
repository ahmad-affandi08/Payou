import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Latar bergaya Ruang Kerja Kasir (bilah atas, rel navigasi, katalog, keranjang) yang masih kosong, dipakai di
/// belakang modal Buka shift: kasir merasa sudah masuk ke halaman penjualan, hanya belum boleh berjualan sampai shift
/// dibuka. Murni hiasan, tidak bisa disentuh dan tidak dibaca pembaca layar.
class LatarRuangKerja extends StatelessWidget {
  const LatarRuangKerja({super.key, required this.namaKasir});

  final String namaKasir;

  static const List<IconData> _ikonRel = [
    Icons.point_of_sale,
    Icons.history,
    Icons.inventory_2_outlined,
    Icons.payments_outlined,
    Icons.schedule,
    Icons.sync,
    Icons.settings_outlined,
  ];

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final lebar = MediaQuery.sizeOf(context).width;
    final lega = lebar >= 720;
    return ExcludeSemantics(
      child: IgnorePointer(
        child: Column(
          children: [
            _Bilah(warna: warna, namaKasir: namaKasir),
            Expanded(
              child: Row(
                children: [
                  if (lega) _Rel(warna: warna),
                  Expanded(child: _Katalog(warna: warna)),
                  if (lebar >= 960) _Keranjang(warna: warna),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Bilah extends StatelessWidget {
  const _Bilah({required this.warna, required this.namaKasir});

  final TokenWarna warna;
  final String namaKasir;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    return Material(
      color: warna.brandGelap,
      child: SafeArea(
        bottom: false,
        child: SizedBox(
          height: 56,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16),
            child: Row(
              children: [
                const LogoMerek.lengkapPutih(tinggi: 28),
                const Spacer(),
                Icon(Icons.person_outline, size: TokenJarak.ikonSedang, color: warna.permukaan),
                const SizedBox(width: TokenJarak.jarak8),
                Text(namaKasir, style: teks.labelLarge?.copyWith(color: warna.permukaan)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Rel extends StatelessWidget {
  const _Rel({required this.warna});

  final TokenWarna warna;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 84,
      decoration: BoxDecoration(
        color: warna.permukaan,
        border: Border(right: BorderSide(color: warna.garis)),
      ),
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak12),
      child: Column(
        children: [
          for (var i = 0; i < LatarRuangKerja._ikonRel.length; i++)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak8),
              child: Container(
                width: 52,
                height: 40,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
                  color: i == 0 ? warna.brand.withValues(alpha: 0.12) : null,
                ),
                child: Icon(LatarRuangKerja._ikonRel[i], color: i == 0 ? warna.brand : warna.teksSekunder),
              ),
            ),
        ],
      ),
    );
  }
}

class _Katalog extends StatelessWidget {
  const _Katalog({required this.warna});

  final TokenWarna warna;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: warna.latar,
      padding: const EdgeInsets.all(TokenJarak.jarak16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            height: 44,
            decoration: BoxDecoration(
              color: warna.permukaan,
              borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
              border: Border.all(color: warna.garis),
            ),
            padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak12),
            child: Row(
              children: [
                Icon(Icons.search, color: warna.teksSekunder),
                const SizedBox(width: TokenJarak.jarak8),
                Expanded(
                  child: Text(
                    'Cari produk atau pindai barcode',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: warna.teksSekunder),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: TokenJarak.jarak12),
          Expanded(
            child: LayoutBuilder(
              builder: (context, ruang) {
                final kolom = (ruang.maxWidth / 150).floor().clamp(2, 6);
                return GridView.count(
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisCount: kolom,
                  mainAxisSpacing: TokenJarak.jarak12,
                  crossAxisSpacing: TokenJarak.jarak12,
                  childAspectRatio: 1.25,
                  children: [
                    for (var i = 0; i < kolom * 4; i++)
                      Container(
                        decoration: BoxDecoration(
                          color: warna.permukaan,
                          borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
                          border: Border.all(color: warna.garis),
                        ),
                        padding: const EdgeInsets.all(TokenJarak.jarak12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            _Garis(warna: warna, lebar: 88),
                            const SizedBox(height: TokenJarak.jarak8),
                            _Garis(warna: warna, lebar: 52),
                          ],
                        ),
                      ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _Keranjang extends StatelessWidget {
  const _Keranjang({required this.warna});

  final TokenWarna warna;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    return Container(
      width: 340,
      decoration: BoxDecoration(
        color: warna.permukaan,
        border: Border(left: BorderSide(color: warna.garis)),
      ),
      padding: const EdgeInsets.all(TokenJarak.jarak16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Keranjang', style: teks.titleMedium),
          const Spacer(),
          Center(child: Icon(Icons.shopping_basket_outlined, size: 48, color: warna.garis)),
          const SizedBox(height: TokenJarak.jarak8),
          Center(
            child: Text('Belum ada barang', style: TextStyle(color: warna.teksSekunder)),
          ),
          const Spacer(),
          Container(
            height: 52,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: warna.garis,
              borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
            ),
            child: Text(
              'Bayar',
              style: TextStyle(color: warna.teksSekunder, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _Garis extends StatelessWidget {
  const _Garis({required this.warna, required this.lebar});

  final TokenWarna warna;
  final double lebar;

  @override
  Widget build(BuildContext context) => Container(
    width: lebar,
    height: 10,
    decoration: BoxDecoration(color: warna.garis, borderRadius: BorderRadius.circular(5)),
  );
}
