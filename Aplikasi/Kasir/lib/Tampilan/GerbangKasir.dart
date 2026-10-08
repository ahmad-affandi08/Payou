import 'dart:async';

import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/Salesman/LayananSalesman.dart';
import 'Dapur/LayarKds.dart';
import 'LayarAktivasi.dart';
import 'LayarBukaShift.dart';
import 'LayarPilihKasir.dart';
import 'RuangKerja/RuangKerja.dart';
import 'Shift/LayarLaporanZ.dart';

/// Menentukan layar menurut sesi: aktivasi → pilih kasir & PIN → buka shift → Ruang Kerja Kasir (§17.2.7) selama
/// shift terbuka, termasuk layar kunci & ganti kasir; setelah tutup shift → Laporan Z → buka shift (F-11). Perangkat
/// berjenis `Kds` langsung membuka layar dapur setelah aktif (F-10b; tanpa kasir & shift). Perangkat berjenis `Pelayan`
/// (v2.00) masuk dengan PIN lalu langsung ke Ruang Kerja mode Pelayan tanpa shift. Perangkat berjenis `Salesman`, dan
/// pengguna yang izin POS-nya hanya salesman, langsung ke Ruang Kerja mode Salesman tanpa shift (Modul Salesman
/// bagian 2).
class GerbangKasir extends ConsumerStatefulWidget {
  const GerbangKasir({super.key});

  /// BR-02.3: selang pemeriksaan keabsahan perangkat. Tanpa ini pencabutan hanya ketahuan saat ada permintaan lain,
  /// sehingga shift yang terbuka tetapi sepi bisa berjalan lama setelah perangkatnya dicabut.
  static const Duration selangPeriksaPerangkat = Duration(minutes: 1);

  @override
  ConsumerState<GerbangKasir> createState() => _GerbangKasirState();
}

class _GerbangKasirState extends ConsumerState<GerbangKasir> with WidgetsBindingObserver {
  Timer? _pewaktuPerangkat;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _pewaktuPerangkat = Timer.periodic(GerbangKasir.selangPeriksaPerangkat, (_) => unawaited(_Periksa()));
  }

  /// Pemeriksaan berkala. Di layar pilih kasir (dan layar buka shift) pewaktu sinkron 30 detik Ruang Kerja tidak
  /// berjalan, jadi outbox ikut dikirim di sini (§18.3 butir 6, K-6): perangkat yang ditinggal di layar pilih kasir
  /// tetap menyetor transaksi offline-nya.
  Future<void> _Periksa() async {
    final sesi = ref.read(penyediaSesi.notifier);
    await sesi.PeriksaPerangkat();
    // PIN & izin staf berubah di back-office: segarkan sebelum PIN berikutnya diketik (jeda 5 menit).
    await sesi.SegarkanStafBilaPerlu();
    if (mounted && _CekDiLuarRuangKerja()) {
      await sesi.Sinkronkan();
    }
  }

  bool _CekDiLuarRuangKerja() {
    final tahap = ref.read(penyediaSesi).tahap;
    if (tahap == TahapSesi.PilihKasir) {
      return true;
    }
    final jenis = ref.read(penyediaJenisPerangkat).value;
    final kasir = ref.read(penyediaSesi).kasir;
    return tahap == TahapSesi.Masuk &&
        jenis != 'Pelayan' &&
        !(kasir != null && LayananSalesman.CekModeSalesman(kasir, jenis)) &&
        ref.read(penyediaShiftAktif).value == null;
  }

  @override
  void dispose() {
    _pewaktuPerangkat?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState keadaan) {
    // Perangkat kasir sering ditinggal di latar; saat dibuka lagi pencabutan tidak perlu menunggu pewaktu.
    // K-6: outbox yang menunggu jadwal coba ulang langsung dikirim, bukan menunggu jeda berikutnya.
    if (keadaan == AppLifecycleState.resumed) {
      final sesi = ref.read(penyediaSesi.notifier);
      unawaited(
        sesi.PeriksaPerangkat().then((_) => sesi.SegarkanStafBilaPerlu(paksa: true)).then((_) => sesi.SinkronkanSegera()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    // K-6: koneksi pulih (Offline → Online) = kirim outbox sekarang juga.
    ref.listen<StatusKoneksi>(penyediaKoneksi, (sebelum, sesudah) {
      if (sebelum == StatusKoneksi.Offline && sesudah == StatusKoneksi.Online) {
        unawaited(ref.read(penyediaSesi.notifier).SinkronkanSegera());
      }
    });
    final sesi = ref.watch(penyediaSesi);
    final jenis = ref.watch(penyediaJenisPerangkat).value;
    final kds = jenis == 'Kds';
    if (kds && (sesi.tahap == TahapSesi.PilihKasir || sesi.tahap == TahapSesi.Masuk)) {
      return const LayarKds();
    }
    return switch (sesi.tahap) {
      TahapSesi.Memuat => const Scaffold(body: Center(child: TandaMuat(ukuran: 72))),
      TahapSesi.BelumAktif => LayarAktivasi(pesan: sesi.pesan),
      TahapSesi.PilihKasir => const LayarPilihKasir(),
      TahapSesi.Masuk when jenis == 'Pelayan' => RuangKerja(shift: null, kasir: sesi.kasir!, kunci: sesi.kunci),
      TahapSesi.Masuk when LayananSalesman.CekModeSalesman(sesi.kasir!, jenis) => RuangKerja(
        shift: null,
        kasir: sesi.kasir!,
        kunci: sesi.kunci,
        salesman: true,
      ),
      TahapSesi.Masuk =>
        ref
            .watch(penyediaShiftAktif)
            .when(
              loading: () => const Scaffold(body: Center(child: TandaMuat(ukuran: 72))),
              error: (galat, _) => Scaffold(body: Center(child: Text('Data shift tidak bisa dibaca: $galat'))),
              data: (shift) {
                if (shift != null) {
                  return RuangKerja(shift: shift, kasir: sesi.kasir!, kunci: sesi.kunci);
                }
                // F-11: laporan Z shift yang baru ditutup tampil dulu sebelum buka shift berikutnya.
                final laporanZ = ref.watch(penyediaLaporanZTertunda).value;
                return laporanZ == null ? LayarBukaShift(kasir: sesi.kasir!) : LayarLaporanZ(uuidShift: laporanZ);
              },
            ),
    };
  }
}
