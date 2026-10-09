import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/Shift/LayananShift.dart';
import 'LembarBukaLaci.dart';
import 'Komponen/FormatWaktu.dart';
import 'LembarMutasiKas.dart';
import 'RuangKerja/BagianKerja.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Area kerja "Kas" (F-06 langkah 4, D-40): uang yang seharusnya ada di laci sebagai angka utama beserta asal-usulnya,
/// tombol kas masuk/keluar/setoran & buka laci tanpa transaksi, dan linimasa mutasi kas di panel konteks. Formulir kas dibuka sebagai panel/lembar oleh bingkai ruang kerja lewat [saatCatat].
class LayarKas extends ConsumerWidget {
  const LayarKas({super.key, required this.shift, required this.saatCatat});

  final BarisShift shift;
  final ValueChanged<String> saatCatat;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final mutasi = ref.watch(penyediaMutasiShift(shift.Uuid)).value ?? const <BarisMutasiKas>[];
    final kas = LayananShift.HitungKasNonPenjualan(shift, mutasi);
    final tunaiPenjualan = ref.watch(penyediaTunaiShift(shift.Uuid)).value ?? Uang.Nol();
    final refundTunai = ref.watch(penyediaRefundTunaiShift(shift.Uuid)).value ?? Uang.Nol();
    final adaPenjualan = !tunaiPenjualan.BernilaiNol() || !refundTunai.BernilaiNol();

    final perkiraan = kas.Tambah(tunaiPenjualan).Kurangi(refundTunai);
    Uang Jumlahkan(String jenis) =>
        mutasi.where((m) => m.Jenis == jenis).fold(Uang.Nol(), (total, m) => total.Tambah(Uang.Dari(m.Jumlah)));

    final rincian = [
      BarisInfo(label: 'Kas awal', nilai: TeksUang(Uang.Dari(shift.KasAwal))),
      if (adaPenjualan) BarisInfo(label: 'Penjualan tunai bersih', nilai: TeksUang(tunaiPenjualan)),
      BarisInfo(label: '+ Kas masuk', nilai: TeksUang(Jumlahkan(JenisMutasi.masuk))),
      BarisInfo(label: '− Kas keluar', nilai: TeksUang(Uang.Nol().Kurangi(Jumlahkan(JenisMutasi.keluar)))),
      BarisInfo(label: '− Setoran', nilai: TeksUang(Uang.Nol().Kurangi(Jumlahkan(JenisMutasi.setoran)))),
      if (!refundTunai.BernilaiNol())
        BarisInfo(label: 'Uang kembali ke pelanggan (batal & retur)', nilai: TeksUang(Uang.Nol().Kurangi(refundTunai))),
    ];

    return IsiAreaKerja(
      judul: 'Kas',
      aksi: [
        for (final jenis in const [JenisMutasi.masuk, JenisMutasi.keluar, JenisMutasi.setoran])
          OutlinedButton(onPressed: () => saatCatat(jenis), child: Text(LembarMutasiKas.AmbilJudul(jenis))),
        // Cetak struk bagian 4 (§19.2): buka laci tanpa transaksi, selalu dicatat.
        OutlinedButton.icon(
          onPressed: () => saatCatat(LembarBukaLaci.kunciPanel),
          icon: const Icon(Icons.point_of_sale),
          label: const Text(LembarBukaLaci.judul),
        ),
      ],
      anak: [
        SorotanAngka(
          label: adaPenjualan ? 'Perkiraan kas di laci' : 'Kas di laci (tanpa penjualan)',
          ikon: Icons.point_of_sale,
          nilai: TeksUang(adaPenjualan ? perkiraan : kas, rataKanan: false),
          keterangan: 'Jumlah uang tunai yang seharusnya ada di laci sekarang. Cocokkan saat menghitung laci.',
        ),
        const SizedBox(height: TokenJarak.jarak12),
        BagianKerja(
          judul: 'Asal uang di laci',
          ikon: Icons.calculate_outlined,
          anak: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ...rincian,
              if (adaPenjualan) ...[
                const SizedBox(height: TokenJarak.jarak4),
                Text(
                  'Penjualan tunai bersih = uang tunai diterima dikurangi kembalian'
                  '${refundTunai.BernilaiNol() ? '' : ', termasuk transaksi yang kemudian di-void'}.',
                  style: teks.bodySmall,
                ),
              ],
            ],
          ),
        ),
      ],
      panelSamping: [
        JudulPanelSamping('Kas masuk, keluar & setoran${mutasi.isEmpty ? '' : ' (${mutasi.length})'}'),
        if (mutasi.isEmpty)
          const KeadaanKosong(
            ringkas: true,
            ilustrasi: IlustrasiKosong.Kas,
            ikon: Icons.swap_vert,
            judul: 'Belum ada kas masuk atau keluar',
            keterangan:
                'Catat uang yang masuk atau keluar laci di luar penjualan, misalnya beli es batu atau setoran ke '
                'pemilik. Semuanya tampil di sini.',
          )
        else
          for (final m in mutasi.reversed) _BarisMutasi(mutasi: m),
      ],
    );
  }
}

/// Satu mutasi kas di panel konteks: arah, kategori, jam & catatan, nominal bertanda.
class _BarisMutasi extends StatelessWidget {
  const _BarisMutasi({required this.mutasi});

  final BarisMutasiKas mutasi;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final m = mutasi;
    final masuk = m.Jenis == JenisMutasi.masuk;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak8),
      decoration: BoxDecoration(
        border: Border(
          bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
        ),
      ),
      child: Row(
        children: [
          Icon(
            masuk ? Icons.south_west : Icons.north_east,
            size: TokenJarak.ikonSedang,
            color: masuk ? warna.sukses : warna.teksSekunder,
          ),
          const SizedBox(width: TokenJarak.jarak12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(m.NamaKategori ?? LembarMutasiKas.AmbilJudul(m.Jenis), style: teks.labelLarge),
                Text(
                  [
                    FormatWaktu.FormatJam(m.DicatatPada),
                    LembarMutasiKas.AmbilJudul(m.Jenis),
                    if (m.Catatan != null) m.Catatan!,
                    if (m.DisetujuiOleh != null) 'disetujui supervisor',
                  ].join(' | '),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: teks.bodySmall,
                ),
              ],
            ),
          ),
          const SizedBox(width: TokenJarak.jarak8),
          TeksUang(
            masuk ? Uang.Dari(m.Jumlah) : Uang.Nol().Kurangi(Uang.Dari(m.Jumlah)),
            gaya: teks.labelLarge?.copyWith(color: masuk ? warna.sukses : warna.teksUtama),
          ),
        ],
      ),
    );
  }
}
