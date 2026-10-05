import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import 'Komponen/AvatarKasir.dart';
import 'Komponen/BingkaiMasuk.dart';
import 'Komponen/PapanPin.dart';
import 'LayarAbsensi.dart';

/// F-06 langkah 1: pilih nama kasir lalu masukkan PIN (bisa tanpa internet).
class LayarPilihKasir extends ConsumerStatefulWidget {
  const LayarPilihKasir({super.key});

  @override
  ConsumerState<LayarPilihKasir> createState() => _LayarPilihKasirState();
}

class _LayarPilihKasirState extends ConsumerState<LayarPilihKasir> {
  StafLokal? _dipilih;
  bool _sibuk = false;
  String? _galat;

  Future<void> _Masuk(String pin) async {
    final staf = _dipilih;
    if (staf == null) {
      return;
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await ref.read(penyediaSesi.notifier).Masuk(staf, pin, sebelumMasuk: _TawarkanAbsenMasuk);
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  /// Audit kemudahan pakai #31: toko yang memakai data karyawan ditawari absen masuk sekali per hari tepat setelah
  /// PIN kasir benar (PIN tidak diminta dua kali). "Nanti" = langsung bekerja; absen tetap bisa dari tombol Absen.
  Future<void> _TawarkanAbsenMasuk(StafLokal kasir) async {
    final layanan = ref.read(penyediaLayananAbsensi);
    final pakaiKaryawan = (await ref.read(penyediaKaryawanPos.future)).isNotEmpty;
    if (!pakaiKaryawan || !await layanan.CekPerluTawaranMasuk(kasir) || !mounted) {
      return;
    }
    final absen = await showDialog<bool>(
      context: context,
      builder: (konteks) => AlertDialog(
        title: const Text('Absen masuk sekarang?'),
        content: Text('${kasir.nama} belum absen masuk hari ini.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(konteks).pop(false), child: const Text('Nanti')),
          FilledButton(onPressed: () => Navigator.of(konteks).pop(true), child: const Text('Absen masuk')),
        ],
      ),
    );
    if (absen != true) {
      return;
    }
    try {
      await layanan.Catat(kasir, await layanan.AmbilSwafoto());
    } on GalatKasir catch (galat) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(galat.pesan)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final terpilih = _dipilih;

    if (terpilih != null) {
      return BingkaiMasuk(
        judul: 'PIN ${terpilih.nama}',
        keterangan: 'Masukkan PIN untuk mulai bertugas.',
        lebarIsi: 380,
        aksi: [
          TextButton(
            onPressed: _sibuk ? null : () => setState(() => _dipilih = null),
            child: const Text('Ganti kasir'),
          ),
        ],
        isi: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AvatarKasir(nama: terpilih.nama, ukuran: 72),
            const SizedBox(height: TokenJarak.jarak16),
            PapanPin(saatSelesai: _Masuk, sibuk: _sibuk, pesanGalat: _galat),
          ],
        ),
      );
    }

    final staf = ref.watch(penyediaStaf);
    return BingkaiMasuk(
      judul: 'Siapa yang bertugas?',
      keterangan: 'Pilih nama Anda, lalu masukkan PIN. Shift dibuka setelah Anda masuk.',
      lebarIsi: 560,
      aksi: [
        TextButton(
          onPressed: () => ref.read(penyediaSesi.notifier).SegarkanData(),
          child: const Text('Perbarui data kasir'),
        ),
        // F-18: absen masuk/keluar staf tanpa membuka sesi kasir.
        OutlinedButton.icon(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LayarAbsensi())),
          icon: const Icon(Icons.badge_outlined),
          label: const Text('Absen masuk/keluar'),
        ),
      ],
      isi: staf.when(
        loading: () => const Center(
          child: Padding(padding: EdgeInsets.all(TokenJarak.jarak24), child: CircularProgressIndicator()),
        ),
        error: (galat, _) =>
            Text('Data kasir tidak bisa dimuat: $galat', style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
        data: (daftar) => daftar.isEmpty
            ? Text(
                'Belum ada kasir untuk outlet ini. Tambahkan pengguna di back-office, lalu ketuk "Perbarui data kasir".',
                style: teks.bodyLarge,
              )
            : LayoutBuilder(
                builder: (konteks, ruang) {
                  // Dua kolom sama lebar bila muat (≥ 420dp), selain itu satu kolom penuh.
                  final lebarKartu = ruang.maxWidth >= 420 ? (ruang.maxWidth - TokenJarak.jarak12) / 2 : ruang.maxWidth;
                  return Wrap(
                    spacing: TokenJarak.jarak12,
                    runSpacing: TokenJarak.jarak12,
                    children: [
                      for (final s in daftar)
                        SizedBox(
                          width: lebarKartu,
                          child: Material(
                            color: warna.permukaan,
                            shape: RoundedRectangleBorder(
                              side: BorderSide(color: warna.garis),
                              borderRadius: BorderRadius.circular(TokenJarak.radiusPanel + 4),
                            ),
                            child: InkWell(
                              customBorder: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(TokenJarak.radiusPanel + 4),
                              ),
                              onTap: () => setState(() {
                                _dipilih = s;
                                _galat = null;
                              }),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: TokenJarak.jarak12,
                                  vertical: TokenJarak.jarak16,
                                ),
                                child: Row(
                                  children: [
                                    AvatarKasir(nama: s.nama),
                                    const SizedBox(width: TokenJarak.jarak12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            s.nama,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: teks.titleMedium,
                                          ),
                                          Text(s.pemilik ? 'Pemilik' : 'Kasir', style: teks.bodySmall),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                    ],
                  );
                },
              ),
      ),
    );
  }
}
