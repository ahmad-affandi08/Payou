import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Audit kemudahan pakai #27: chip alasan siap pakai di atas isian alasan (void, retur, selisih shift, buka laci,
/// batal pesanan meja). Mengetuk chip mengisi [pengendali]; isian tetap bisa diketik bebas untuk alasan lain. Setiap
/// alasan siap pakai sudah memenuhi panjang minimal server.
class PilihanAlasan extends StatelessWidget {
  const PilihanAlasan({super.key, required this.pengendali, required this.pilihan, this.saatDipilih});

  final TextEditingController pengendali;
  final List<String> pilihan;
  final VoidCallback? saatDipilih;

  static const List<String> voidPenjualan = [
    'Salah input pesanan',
    'Pelanggan batal beli',
    'Salah metode bayar',
    'Transaksi ganda',
  ];
  static const List<String> retur = [
    'Barang rusak atau cacat',
    'Salah kirim barang',
    'Barang kedaluwarsa',
    'Pelanggan berubah pikiran',
  ];
  static const List<String> selisihShift = [
    'Salah hitung kembalian',
    'Lupa catat kas keluar',
    'Uang palsu ditemukan',
    'Penyebab belum diketahui',
  ];
  static const List<String> shiftLama = ['Sisa uji coba', 'Shift lama tidak sempat ditutup', 'Aplikasi dipasang ulang'];
  static const List<String> bukaLaci = ['Tukar uang receh', 'Periksa isi laci', 'Masukkan uang yang tertinggal'];
  static const List<String> batalPesanan = ['Salah input', 'Pelanggan batal', 'Menu habis', 'Terlalu lama menunggu'];

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TextEditingValue>(
      valueListenable: pengendali,
      builder: (context, nilai, _) => Padding(
        padding: const EdgeInsets.only(bottom: TokenJarak.jarak8),
        child: Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak4,
          children: [
            for (final p in pilihan)
              ChoiceChip(
                label: Text(p),
                selected: nilai.text.trim() == p,
                onSelected: (_) {
                  pengendali.value = TextEditingValue(
                    text: p,
                    selection: TextSelection.collapsed(offset: p.length),
                  );
                  saatDipilih?.call();
                },
              ),
          ],
        ),
      ),
    );
  }
}
