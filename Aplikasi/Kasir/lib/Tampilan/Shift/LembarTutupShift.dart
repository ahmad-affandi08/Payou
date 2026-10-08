import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../../Domain/Shift/LayananShift.dart';
import '../../Domain/Shift/LayananTutupShift.dart';
import '../Komponen/MasukanUang.dart';
import '../LembarMutasiKas.dart';
import 'KartuLaporanShift.dart';
import '../Komponen/PilihanAlasan.dart';

/// Formulir tutup shift (Rincian F-11) di dalam `PanelTugas` ruang kerja. Kasir menghitung kas laci (total atau per
/// pecahan) dan opsional total non-tunai per metode (slip EDC/QRIS). Tutup buta (`TutupShiftButa`): kas seharusnya
/// baru tampil setelah hitungan disimpan dan hitungan tidak bisa diubah lagi. |Selisih| di atas toleransi wajib alasan
/// + PIN supervisor ber-izin `shift.selisih.setujui` (kasir yang sendiri ber-izin menyetujui dirinya). Setelah
/// tersimpan shift tertutup dan bingkai berganti ke Laporan Z.
class LembarTutupShift extends ConsumerStatefulWidget {
  const LembarTutupShift({super.key, required this.shift, required this.penutup});

  static const String judul = 'Tutup shift';

  final BarisShift shift;
  final StafLokal penutup;

  @override
  ConsumerState<LembarTutupShift> createState() => _LembarTutupShiftState();
}

class _LembarTutupShiftState extends ConsumerState<LembarTutupShift> {
  final _kasAktual = TextEditingController();
  final _alasan = TextEditingController();
  final Map<String, TextEditingController> _nonTunai = {};
  final Map<int, int> _pecahan = {for (final n in daftarPecahanRupiah) n: 0};
  bool _hitungPecahan = false;
  bool? _buta;
  String? _galatAwal;

  /// Hasil hitungan yang sudah disimpan (tahap tinjau). Null = masih tahap hitung.
  PratinjauTutupShift? _pratinjau;

  /// Pratinjau berjalan saat tidak buta (selisih tampil sambil mengetik).
  PratinjauTutupShift? _pratinjauLangsung;
  bool _sibuk = false;
  String? _galat;

  LayananTutupShift get _layanan => ref.read(penyediaLayananTutupShift);

  @override
  void initState() {
    super.initState();
    unawaited(_Muat());
  }

  Future<void> _Muat() async {
    try {
      await _layanan.PeriksaBolehTutup(widget.shift, widget.penutup);
    } on GalatKasir catch (galat) {
      _galatAwal = galat.pesan;
    }
    final buta = await _layanan.CekTutupButa();
    if (mounted) {
      setState(() => _buta = buta);
    }
  }

  @override
  void dispose() {
    _kasAktual.dispose();
    _alasan.dispose();
    for (final p in _nonTunai.values) {
      p.dispose();
    }
    super.dispose();
  }

  TextEditingController _PengendaliNonTunai(String uuid) => _nonTunai.putIfAbsent(uuid, TextEditingController.new);

  Future<void> _PerbaruiLangsung() async {
    final kas = MasukanUang.AmbilNilai(_kasAktual);
    if (_buta != false || kas == null) {
      setState(() => _pratinjauLangsung = null);
      return;
    }
    final hasil = await _layanan.Pratinjau(widget.shift.Uuid, kas);
    if (mounted) {
      setState(() => _pratinjauLangsung = hasil);
    }
  }

  void _UbahPecahan(int nominal, int jumlahBaru) {
    setState(() {
      _pecahan[nominal] = jumlahBaru;
      MasukanUang.Isi(_kasAktual, HitungPecahan.HitungTotal(_pecahan));
    });
    unawaited(_PerbaruiLangsung());
  }

  Future<void> _SimpanHitungan() async {
    final kas = MasukanUang.AmbilNilai(_kasAktual);
    if (kas == null) {
      setState(() => _galat = 'Isi kas aktual hasil hitungan laci.');
      return;
    }
    final hasil = await _layanan.Pratinjau(widget.shift.Uuid, kas);
    if (mounted) {
      setState(() {
        _galat = null;
        _pratinjau = hasil;
      });
    }
  }

  Future<void> _Tutup() async {
    final kas = MasukanUang.AmbilNilai(_kasAktual);
    if (kas == null) {
      setState(() => _galat = 'Isi kas aktual hasil hitungan laci.');
      return;
    }
    final pratinjau = await _layanan.Pratinjau(widget.shift.Uuid, kas);
    StafLokal? penyetuju;
    if (pratinjau.butuhPersetujuan) {
      if (_alasan.text.trim().length < LayananTutupShift.panjangAlasanMinimal) {
        setState(() => _galat = 'Tulis alasan selisih minimal 5 huruf.');
        return;
      }
      if (widget.penutup.PunyaIzin(IzinKasir.shiftSelisihSetujui)) {
        penyetuju = widget.penutup;
      } else {
        if (!mounted) {
          return;
        }
        penyetuju = await showDialog<StafLokal>(
          context: context,
          builder: (_) => DialogPinSupervisor(
            izin: IzinKasir.shiftSelisihSetujui,
            pesan:
                'Selisih kas ${KartuLaporanShift.FormatSelisih(pratinjau.selisih)} melebihi toleransi '
                '${pratinjau.toleransi.FormatRupiah()}. Pilih supervisor yang menyetujui.',
            rincian: [(label: 'Selisih kas', nilai: KartuLaporanShift.FormatSelisih(pratinjau.selisih))],
          ),
        );
        if (penyetuju == null) {
          return;
        }
      }
    }

    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      final nonTunai = <String, Uang>{
        for (final e in _nonTunai.entries)
          if (MasukanUang.AmbilNilai(e.value) != null) e.key: MasukanUang.AmbilNilai(e.value)!,
      };
      await _layanan.TutupShift(
        shift: widget.shift,
        penutup: widget.penutup,
        kasAktual: kas,
        pecahan: _hitungPecahan ? _pecahan : null,
        nonTunai: nonTunai,
        alasan: _alasan.text,
        penyetuju: penyetuju,
      );
      if (mounted) {
        UmpanAksi.Berhasil(
          Navigator.of(context, rootNavigator: true).context,
          'Shift ditutup. Laporan tutup shift tersimpan dan dikirim otomatis ke server.',
        );
      }
      await ref.read(penyediaSesi.notifier).Sinkronkan();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
        unawaited(UmpanAksi.Gagal(context, judul: 'Shift belum ditutup', pesan: galat.pesan));
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final buta = _buta;
    if (buta == null) {
      return const Padding(padding: EdgeInsets.all(TokenJarak.jarak24), child: LinearProgressIndicator());
    }
    if (_galatAwal != null) {
      return Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak24),
        child: Text(_galatAwal!, style: teks.bodyLarge?.copyWith(color: warna.bahaya)),
      );
    }

    final pratinjau = _pratinjau ?? _pratinjauLangsung;
    final tahapTinjau = _pratinjau != null;
    final hitunganTerkunci = buta && tahapTinjau;
    final metode = (ref.watch(penyediaKonteksPenjualan).value?.metodePembayaran ?? const <BarisMetodePembayaran>[])
        .where((m) => m.Jenis != LayananTutupShift.jenisTunai)
        .toList();
    final laporan = ref.watch(penyediaLaporanShift(widget.shift.Uuid)).value;
    final tampilkanSistem = !buta || tahapTinjau;

    return Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            buta && !tahapTinjau
                ? 'Hitung uang di laci. Kas seharusnya ditampilkan setelah hitungan disimpan.'
                : 'Hitung uang di laci lalu tutup shift.',
            style: teks.bodyMedium,
          ),
          const SizedBox(height: TokenJarak.jarak12),
          if (hitunganTerkunci)
            _BarisRingkas(label: 'Kas aktual (hitungan)', isi: TeksUang(_pratinjau!.kasAktual))
          else ...[
            MasukanUang(
              pengendali: _kasAktual,
              label: 'Kas aktual di laci',
              autofocus: true,
              saatBerubah: (_) => unawaited(_PerbaruiLangsung()),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _hitungPecahan,
              onChanged: (nilai) => setState(() => _hitungPecahan = nilai),
              title: const Text('Hitung per pecahan'),
              subtitle: const Text('Opsional. Jumlahnya otomatis mengisi kas aktual.'),
            ),
            if (_hitungPecahan)
              HitungPecahan(nominal: daftarPecahanRupiah, jumlah: _pecahan, saatBerubah: _UbahPecahan),
          ],
          if (metode.isNotEmpty) ...[
            const SizedBox(height: TokenJarak.jarak12),
            Text('Non-tunai (opsional, cocokkan dengan slip)', style: teks.titleSmall),
            for (final m in metode)
              Padding(
                padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    MasukanUang(pengendali: _PengendaliNonTunai(m.Uuid), label: m.Nama),
                    if (tampilkanSistem && laporan != null)
                      Padding(
                        padding: const EdgeInsets.only(top: TokenJarak.jarak4),
                        child: Text(
                          'Menurut sistem: ${laporan.AmbilJumlahMetode(m.Uuid).FormatRupiah()}',
                          style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                        ),
                      ),
                  ],
                ),
              ),
          ],
          const SizedBox(height: TokenJarak.jarak16),
          if (pratinjau != null && tampilkanSistem)
            KotakPanel(
              anak: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _BarisRingkas(label: 'Kas seharusnya', isi: TeksUang(pratinjau.kasSeharusnya)),
                  _BarisRingkas(label: 'Kas aktual', isi: TeksUang(pratinjau.kasAktual)),
                  _BarisRingkas(
                    label: 'Selisih',
                    isi: Text(
                      KartuLaporanShift.FormatSelisih(pratinjau.selisih),
                      textAlign: TextAlign.right,
                      style: teks.titleSmall?.copyWith(
                        color: pratinjau.selisih.BernilaiNol() ? warna.teksUtama : warna.bahaya,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ),
                  if (pratinjau.butuhPersetujuan)
                    Padding(
                      padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                      child: Text(
                        'Selisih melebihi toleransi ${pratinjau.toleransi.FormatRupiah()}: tulis alasan dan minta '
                        'persetujuan supervisor.',
                        style: teks.bodySmall?.copyWith(color: warna.bahaya),
                      ),
                    ),
                ],
              ),
            ),
          if (pratinjau != null && tampilkanSistem && pratinjau.butuhPersetujuan) ...[
            const SizedBox(height: TokenJarak.jarak12),
            PilihanAlasan(pengendali: _alasan, pilihan: PilihanAlasan.selisihShift),
            TextField(
              controller: _alasan,
              maxLength: 255,
              decoration: const InputDecoration(labelText: 'Alasan selisih', border: OutlineInputBorder()),
            ),
          ],
          if (_galat != null)
            Padding(
              padding: const EdgeInsets.only(top: TokenJarak.jarak8),
              child: Text(_galat!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
            ),
          const SizedBox(height: TokenJarak.jarak12),
          SizedBox(
            height: 56,
            child: buta && !tahapTinjau
                ? FilledButton(onPressed: _SimpanHitungan, child: const Text('Simpan hitungan'))
                : FilledButton(
                    onPressed: _sibuk ? null : _Tutup,
                    child: Text(_sibuk ? 'Menutup shift…' : 'Tutup shift sekarang'),
                  ),
          ),
          const SizedBox(height: TokenJarak.jarak8),
          Text(
            'Shift tetap bisa ditutup tanpa internet. Laporan tutup shift (Z) tampil setelah shift ditutup.',
            style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
          ),
        ],
      ),
    );
  }
}

class _BarisRingkas extends StatelessWidget {
  const _BarisRingkas({required this.label, required this.isi});

  final String label;
  final Widget isi;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak4),
    child: Row(
      children: [
        Expanded(child: Text(label)),
        Flexible(child: isi),
      ],
    ),
  );
}
