import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../Aplikasi/Penyedia.dart';
import 'BingkaiPemilik.dart';
import 'LayarMasuk.dart';
import 'LayarPilihTenant.dart';

/// Menentukan layar menurut sesi: masuk (+ 2FA) → pilih usaha → bingkai Owner.
class GerbangPemilik extends ConsumerWidget {
  const GerbangPemilik({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => switch (ref.watch(penyediaSesi).tahap) {
    TahapSesi.Memuat => const Scaffold(body: Center(child: TandaMuat(ukuran: 72))),
    TahapSesi.Keluar || TahapSesi.DuaFaktor => const LayarMasuk(),
    TahapSesi.PilihTenant => const LayarPilihTenant(),
    TahapSesi.Masuk => const BingkaiPemilik(),
  };
}
