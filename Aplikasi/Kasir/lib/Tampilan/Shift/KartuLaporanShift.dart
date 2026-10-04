import 'package:flutter/material.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Domain/Shift/LayananTutupShift.dart';
import '../Komponen/FormatWaktu.dart';

/// Isi laporan shift X (berjalan) / Z (setelah tutup) (Rincian F-11): penjualan, per metode bayar, void & retur, kas
/// laci, dan (Z) hasil hitung kas. [tampilkanKasSeharusnya] = false menyembunyikan kas seharusnya (tutup buta).
class KartuLaporanShift extends StatelessWidget {
  const KartuLaporanShift({super.key, required this.laporan, this.tampilkanKasSeharusnya = true});

  final LaporanShift laporan;
  final bool tampilkanKasSeharusnya;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final l = laporan;
    final s = l.shift;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          '${s.NamaKasir} | dibuka ${FormatWaktu.FormatTanggalJam(s.DibukaPada)}'
          '${s.DitutupPada == null ? '' : ' | ditutup ${FormatWaktu.FormatTanggalJam(s.DitutupPada!)}'}',
          style: teks.bodyMedium,
        ),
        const SizedBox(height: TokenJarak.jarak12),
        _Bagian(
          judul: 'Penjualan',
          baris: [
            _BarisLaporan('Jumlah transaksi', teksKanan: '${l.jumlahTransaksi}'),
            _BarisLaporan('Penjualan kotor', nilai: l.penjualanKotor),
            _BarisLaporan('Diskon', nilai: l.totalDiskon),
            _BarisLaporan('Penjualan bersih', nilai: l.penjualanBersih, tebal: true),
            _BarisLaporan('Pajak', nilai: l.totalPajak),
            if (!l.biayaLayanan.BernilaiNol()) _BarisLaporan('Biaya layanan', nilai: l.biayaLayanan),
            if (!l.pembulatan.BernilaiNol()) _BarisLaporan('Pembulatan', nilai: l.pembulatan),
            _BarisLaporan('Total dibayar pelanggan', nilai: l.totalAkhir, tebal: true),
            _BarisLaporan('Void', teksKanan: '${l.jumlahVoid} transaksi | ${l.nominalVoid.FormatRupiah()}'),
            _BarisLaporan('Retur', teksKanan: '${l.jumlahRetur} dokumen | ${l.nominalRetur.FormatRupiah()}'),
            if (l.jumlahUangMuka > 0)
              _BarisLaporan(
                'Uang muka pre-order',
                teksKanan: '${l.jumlahUangMuka} pesanan | ${(l.nominalUangMuka ?? Uang.Nol()).FormatRupiah()}',
              ),
            if (l.jumlahIsiDeposit > 0)
              _BarisLaporan(
                'Isi deposit pelanggan',
                teksKanan: '${l.jumlahIsiDeposit} kali | ${(l.nominalIsiDeposit ?? Uang.Nol()).FormatRupiah()}',
              ),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak12),
        _Bagian(
          judul: 'Per metode bayar',
          baris: l.perMetode.isEmpty
              ? const [_BarisLaporan('Belum ada pembayaran')]
              : [for (final m in l.perMetode) _BarisLaporan(m.nama, nilai: m.jumlah)],
        ),
        const SizedBox(height: TokenJarak.jarak12),
        _Bagian(
          judul: 'Kas laci',
          baris: [
            _BarisLaporan('Kas awal', nilai: l.kasAwal),
            _BarisLaporan('Penjualan tunai bersih', nilai: l.tunaiMasukBersih),
            _BarisLaporan('Kas masuk', nilai: l.kasMasuk),
            _BarisLaporan('Kas keluar', nilai: Uang.Nol().Kurangi(l.kasKeluar)),
            _BarisLaporan('Setoran', nilai: Uang.Nol().Kurangi(l.setoran)),
            _BarisLaporan('Refund tunai (void & retur)', nilai: Uang.Nol().Kurangi(l.refundTunai)),
            if (tampilkanKasSeharusnya)
              _BarisLaporan(
                'Kas seharusnya',
                nilai: s.KasSeharusnya == null ? l.kasSeharusnya : Uang.Dari(s.KasSeharusnya!),
                tebal: true,
              )
            else
              const _BarisLaporan('Kas seharusnya', teksKanan: 'Ditampilkan setelah hitungan disimpan'),
            if (s.KasAktual != null)
              _BarisLaporan('Kas aktual (dihitung)', nilai: Uang.Dari(s.KasAktual!), tebal: true),
            if (s.Selisih != null)
              _BarisLaporan('Selisih', teksKanan: FormatSelisih(Uang.Dari(s.Selisih!)), tebal: true),
            if (s.AlasanSelisih != null) _BarisLaporan('Alasan selisih', teksKanan: s.AlasanSelisih),
          ],
        ),
      ],
    );
  }

  /// "+Rp 28.000 (lebih)", "−Rp 7.000 (kurang)", atau "Rp 0 (pas)": arti selisih selalu tertulis, tidak hanya warna.
  static String FormatSelisih(Uang selisih) {
    if (selisih.BernilaiNol()) {
      return 'Rp 0 (pas)';
    }
    return selisih.BernilaiNegatif() ? '${selisih.FormatRupiah()} (kurang)' : '+${selisih.FormatRupiah()} (lebih)';
  }
}

class _Bagian extends StatelessWidget {
  const _Bagian({required this.judul, required this.baris});

  final String judul;
  final List<Widget> baris;

  @override
  Widget build(BuildContext context) => KotakPanel(
    anak: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(header: true, child: Text(judul, style: Theme.of(context).textTheme.titleMedium)),
        const SizedBox(height: TokenJarak.jarak8),
        ...baris,
      ],
    ),
  );
}

class _BarisLaporan extends StatelessWidget {
  const _BarisLaporan(this.label, {this.nilai, this.teksKanan, this.tebal = false});

  final String label;
  final Uang? nilai;
  final String? teksKanan;
  final bool tebal;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final gaya = tebal ? teks.titleSmall : teks.bodyMedium;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label, style: gaya)),
          const SizedBox(width: TokenJarak.jarak12),
          if (nilai != null)
            TeksUang(nilai!, gaya: gaya)
          else if (teksKanan != null)
            Flexible(
              child: Text(
                teksKanan!,
                textAlign: TextAlign.right,
                style: gaya?.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
              ),
            ),
        ],
      ),
    );
  }
}
