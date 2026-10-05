import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:sistem_desain/SistemDesain.dart';

/// Keadaan wajib §17.6.6 untuk data dari server: memuat, galat/offline (dengan "Coba lagi"), atau isi.
class KeadaanData<T> extends StatelessWidget {
  const KeadaanData({super.key, required this.nilai, required this.saatCobaLagi, required this.isi});

  final AsyncValue<T> nilai;
  final VoidCallback saatCobaLagi;
  final Widget Function(T data) isi;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return nilai.when(
      skipLoadingOnRefresh: true,
      loading: () => const Center(child: TandaMuat(ukuran: 56)),
      error: (galat, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(galat is GalatJaringan ? Icons.wifi_off : Icons.error_outline, color: warna.bahaya),
              const SizedBox(height: TokenJarak.jarak8),
              Text(
                galat is GalatJaringan
                    ? 'Tidak tersambung ke server. Periksa internet lalu coba lagi.'
                    : galat is GalatApi
                    ? galat.pesan
                    : 'Data tidak bisa dimuat.',
                style: teks.bodyMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: TokenJarak.jarak12),
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: OutlinedButton(onPressed: saatCobaLagi, child: const Text('Coba lagi')),
              ),
            ],
          ),
        ),
      ),
      data: isi,
    );
  }
}
