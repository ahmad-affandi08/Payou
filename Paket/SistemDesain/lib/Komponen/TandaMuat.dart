import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../Token/TokenWarna.dart';

/// Tanda muat Payoung (D-58): logo payung di lingkaran putih, dikelilingi cincin warna token tanpa jarak yang berputar
/// pelan sementara logo "bernapas". Sama dengan `TandaMuat` di web. Untuk layar penuh dan panel besar; di dalam
/// tombol atau baris kecil tetap pakai `CircularProgressIndicator`. Bila pengguna mematikan animasi di perangkat,
/// cincin diam.
class TandaMuat extends StatefulWidget {
  const TandaMuat({super.key, this.ukuran = 64, this.label = 'Memuat'});

  final double ukuran;
  final String label;

  @override
  State<TandaMuat> createState() => _TandaMuatState();
}

class _TandaMuatState extends State<TandaMuat> with SingleTickerProviderStateMixin {
  late final AnimationController _pengendali = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2400),
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) {
      _pengendali.stop();
    } else if (!_pengendali.isAnimating) {
      _pengendali.repeat();
    }
  }

  @override
  void dispose() {
    _pengendali.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final tebal = widget.ukuran * 0.1;
    return Semantics(
      label: widget.label,
      liveRegion: true,
      child: ExcludeSemantics(
        child: AnimatedBuilder(
          animation: _pengendali,
          builder: (context, anak) {
            final napas = 1 + 0.05 * math.sin(_pengendali.value * 2 * math.pi * (2400 / 1800));
            return Transform.scale(scale: napas, child: anak);
          },
          child: SizedBox(
            width: widget.ukuran,
            height: widget.ukuran,
            child: Stack(
              children: [
                Positioned.fill(
                  child: RotationTransition(
                    turns: _pengendali,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: SweepGradient(
                          colors: [warna.brand, warna.info, warna.sukses, warna.aksen, warna.brandGelap, warna.brand],
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned.fill(
                  child: Padding(
                    padding: EdgeInsets.all(tebal),
                    child: DecoratedBox(
                      decoration: BoxDecoration(shape: BoxShape.circle, color: warna.permukaan),
                      child: Center(
                        child: Image.asset(
                          'assets/merek/IkonMerek.png',
                          package: 'sistem_desain',
                          height: widget.ukuran * 0.56,
                          fit: BoxFit.contain,
                        ),
                      ),
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
