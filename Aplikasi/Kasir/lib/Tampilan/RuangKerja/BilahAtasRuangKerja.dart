import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Sesi/StafLokal.dart';
import 'JamRuangKerja.dart';

/// Bilah atas ruang kerja (PRD §17.2.7): logo Payoung di tengah, outlet | perangkat di kiri, lalu identitas kasir,
/// jam, dan tombol Kunci di kanan.
///
/// Latarnya memakai warna merek gelap, bukan permukaan putih: bingkai ruang kerja adalah satu-satunya bagian layar
/// yang tidak berganti sepanjang shift, jadi ia yang memberi aplikasi ini identitas. Yang dipakai `brandGelap`
/// (#22383a), bukan `brand` (#3b5b5d), karena teks putih di atasnya berkontras ±10:1 dibanding ±4,9:1 — nama outlet
/// dan jam harus terbaca dari jarak berdiri kasir, bukan hanya dari dekat.
class BilahAtasRuangKerja extends ConsumerWidget {
  const BilahAtasRuangKerja({super.key, required this.kasir, required this.saatGantiKasir, required this.saatKunci});

  static const double tinggi = 56;

  /// Di bawah lebar ini (HP) bilahnya diringkas: ikon merek saja, kasir tanpa nama (nama ada di petunjuknya dan di
  /// layar Shift), dan tanpa jam — jam sistem sudah berada persis di atasnya. Tanpa peringkasan ini tidak ada ruang
  /// untuk menaruh logo di tengah tanpa memotong nama outlet.
  static const double lebarLega = 600;

  /// Batas lebar tombol kasir di layar lega; lebih dari ini nama dipendekkan dengan elipsis.
  static const double lebarMaksKasir = 240;

  final StafLokal kasir;
  final VoidCallback saatGantiKasir;
  final VoidCallback saatKunci;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final identitas = ref.watch(penyediaIdentitas).value;
    final lega = MediaQuery.sizeOf(context).width >= lebarLega;
    final lokasi = identitas == null
        ? ''
        : [identitas.outlet, identitas.perangkat].where((b) => b.isNotEmpty).join(' | ');
    final gayaBilah = teks.labelLarge?.copyWith(color: warna.permukaan);
    final gayaTombol = TextButton.styleFrom(foregroundColor: warna.permukaan);

    // Ikon status bar dibuat terang karena bilah ini gelap; `SafeArea` di dalam `Material` supaya warna merek ikut
    // mengisi area status bar, bukan menyisakan garis putih di puncak layar.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: warna.brandGelap),
      child: Material(
        color: warna.brandGelap,
        child: SafeArea(
          bottom: false,
          child: SizedBox(
            height: tinggi,
            child: Row(
              children: [
                // Kiri dan kanan sama-sama `Expanded` dengan flex sama, jadi logo di tengah tetap berada di tengah
                // layar walau nama outlet panjang dan lebar tombol di kanan berubah-ubah.
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(left: TokenJarak.jarak16),
                    child: Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Text(
                        lokasi,
                        style: gayaBilah,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        softWrap: false,
                      ),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak12),
                  child: lega ? const LogoMerek.lengkapPutih(tinggi: 28) : const LogoMerek.ikonPutih(tinggi: 28),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(right: TokenJarak.jarak8),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Flexible(
                          child: Tooltip(
                            message: 'Ganti kasir',
                            child: lega
                                ? ConstrainedBox(
                                    constraints: const BoxConstraints(maxWidth: lebarMaksKasir),
                                    child: TextButton.icon(
                                      onPressed: saatGantiKasir,
                                      style: gayaTombol,
                                      icon: const Icon(Icons.person_outline, size: TokenJarak.ikonSedang),
                                      label: Text(
                                        kasir.nama,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        softWrap: false,
                                      ),
                                    ),
                                  )
                                : IconButton(
                                    onPressed: saatGantiKasir,
                                    color: warna.permukaan,
                                    icon: const Icon(Icons.person_outline),
                                  ),
                          ),
                        ),
                        if (lega) ...[
                          const SizedBox(width: TokenJarak.jarak8),
                          JamRuangKerja(gaya: gayaBilah),
                          const SizedBox(width: TokenJarak.jarak4),
                          TextButton.icon(
                            onPressed: saatKunci,
                            style: gayaTombol,
                            icon: const Icon(Icons.lock_outline, size: TokenJarak.ikonSedang),
                            label: const Text('Kunci'),
                          ),
                        ] else
                          IconButton(
                            tooltip: 'Kunci',
                            onPressed: saatKunci,
                            color: warna.permukaan,
                            icon: const Icon(Icons.lock_outline),
                          ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
