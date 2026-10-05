import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:inti/Inti.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananShift.dart';
import 'Komponen/AvatarKasir.dart';
import 'Komponen/LatarRuangKerja.dart';
import 'Komponen/FormatWaktu.dart';
import 'Komponen/MasukanUang.dart';
import 'LembarMutasiKas.dart';

/// F-06 langkah 2: layar Buka Shift. Modal awal diketik langsung atau dihitung per pecahan (opsional); keduanya
/// harus sama. Bisa tanpa internet (BR-06.3); data dikirim lewat outbox saat online. K-18: shift terakhir yang ditutup
/// hari ini bisa dibuka ulang (salah tutup) dengan alasan + PIN supervisor.
class LayarBukaShift extends ConsumerStatefulWidget {
  const LayarBukaShift({super.key, required this.kasir});

  final StafLokal kasir;

  @override
  ConsumerState<LayarBukaShift> createState() => _LayarBukaShiftState();
}

class _LayarBukaShiftState extends ConsumerState<LayarBukaShift> {
  final _kasAwal = TextEditingController();
  final Map<int, int> _pecahan = {for (final n in daftarPecahanRupiah) n: 0};
  bool _hitungPecahan = false;
  bool _sibuk = false;
  String? _galat;
  BarisShift? _shiftTerakhir;

  @override
  void initState() {
    super.initState();
    unawaited(_MuatShiftTerakhir());
  }

  Future<void> _MuatShiftTerakhir() async {
    final shift = await ref.read(penyediaLayananShift).AmbilShiftBisaDibukaUlang();
    if (mounted) {
      setState(() => _shiftTerakhir = shift);
    }
  }

  Future<void> _BukaUlang(BarisShift shift) async {
    final alasan = await showDialog<String>(
      context: context,
      builder: (_) => _DialogAlasanBukaUlang(shift: shift),
    );
    if (alasan == null || !mounted) {
      return;
    }
    var penyetuju = widget.kasir.PunyaIzin(IzinKasir.shiftSelisihSetujui) ? widget.kasir : null;
    penyetuju ??= await showDialog<StafLokal>(
      context: context,
      builder: (_) => DialogPinSupervisor(
        izin: IzinKasir.shiftSelisihSetujui,
        judul: 'Setujui buka ulang shift',
        pesan: 'Buka ulang shift butuh persetujuan supervisor. Pilih supervisor yang menyetujui.',
        rincian: [(label: 'Shift', nilai: shift.NamaKasir), (label: 'Alasan', nilai: alasan)],
      ),
    );
    if (penyetuju == null || !mounted) {
      return;
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await ref
          .read(penyediaLayananShift)
          .BukaUlangShift(shift: shift, peminta: widget.kasir, penyetuju: penyetuju, alasan: alasan);
      await ref.read(penyediaSesi.notifier).Sinkronkan();
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

  @override
  void dispose() {
    _kasAwal.dispose();
    super.dispose();
  }

  void _UbahPecahan(int nominal, int jumlahBaru) => setState(() {
    _pecahan[nominal] = jumlahBaru;
    MasukanUang.Isi(_kasAwal, HitungPecahan.HitungTotal(_pecahan));
  });

  Future<void> _Buka() async {
    final kasAwal = MasukanUang.AmbilNilai(_kasAwal) ?? Uang.Nol();
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await ref
          .read(penyediaLayananShift)
          .BukaShift(
            kasir: widget.kasir,
            kasAwal: kasAwal,
            pecahan: _hitungPecahan ? [for (final e in _pecahan.entries) BarisPecahan(e.key, e.value)] : null,
          );
      await ref.read(penyediaSesi.notifier).Sinkronkan();
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

  static const List<int> _nominalCepat = [100000, 200000, 300000, 500000];

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    // Latar = Ruang Kerja yang belum aktif; modal Buka shift menutupnya sampai shift dibuka. Begitu shift tersimpan,
    // gerbang mengganti layar ini dengan Ruang Kerja sungguhan (halaman penjualan).
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: warna.brandGelap),
      child: Scaffold(
        body: Stack(
          children: [
            Positioned.fill(child: LatarRuangKerja(namaKasir: widget.kasir.nama)),
            Positioned.fill(child: ColoredBox(color: warna.teksUtama.withValues(alpha: 0.55))),
            SafeArea(
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(TokenJarak.jarak16),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 480),
                    child: Semantics(
                      scopesRoute: true,
                      explicitChildNodes: true,
                      label: 'Buka shift',
                      child: Material(
                        color: warna.permukaan,
                        borderRadius: BorderRadius.circular(16),
                        clipBehavior: Clip.antiAlias,
                        child: Padding(
                          padding: const EdgeInsets.all(TokenJarak.jarak24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Row(
                                children: [
                                  AvatarKasir(nama: widget.kasir.nama, ukuran: 52),
                                  const SizedBox(width: TokenJarak.jarak12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text('Buka shift | ${widget.kasir.nama}', style: teks.titleLarge),
                                        const SizedBox(height: TokenJarak.jarak4),
                                        Text(
                                          'Hitung uang di laci sebelum mulai berjualan.',
                                          style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: TokenJarak.jarak24),
                              MasukanUang(
                                pengendali: _kasAwal,
                                label: 'Modal awal (kas awal)',
                                autofocus: true,
                                galat: _galat,
                              ),
                              const SizedBox(height: TokenJarak.jarak12),
                              Wrap(
                                spacing: TokenJarak.jarak8,
                                runSpacing: TokenJarak.jarak8,
                                children: [
                                  for (final nominal in _nominalCepat)
                                    ActionChip(
                                      key: ValueKey('ModalCepat$nominal'),
                                      label: Text('Rp ${MasukanUang.FormatTeks(Uang.DariBulat(nominal))}'),
                                      onPressed: _sibuk
                                          ? null
                                          : () => setState(() {
                                              MasukanUang.Isi(_kasAwal, Uang.DariBulat(nominal));
                                              _hitungPecahan = false;
                                            }),
                                    ),
                                ],
                              ),
                              SwitchListTile(
                                contentPadding: EdgeInsets.zero,
                                value: _hitungPecahan,
                                onChanged: (nilai) => setState(() => _hitungPecahan = nilai),
                                title: const Text('Hitung per pecahan'),
                                subtitle: const Text('Opsional. Jumlahnya otomatis mengisi modal awal.'),
                              ),
                              if (_hitungPecahan)
                                HitungPecahan(
                                  nominal: daftarPecahanRupiah,
                                  jumlah: _pecahan,
                                  saatBerubah: _UbahPecahan,
                                ),
                              const SizedBox(height: TokenJarak.jarak16),
                              SizedBox(
                                height: 56,
                                child: FilledButton(
                                  onPressed: _sibuk ? null : _Buka,
                                  child: Text(_sibuk ? 'Membuka shift…' : 'Buka shift'),
                                ),
                              ),
                              if (_shiftTerakhir case final shift?) ...[
                                const SizedBox(height: TokenJarak.jarak12),
                                OutlinedButton.icon(
                                  key: const ValueKey('BukaUlangShift'),
                                  onPressed: _sibuk ? null : () => _BukaUlang(shift),
                                  icon: const Icon(Icons.lock_open_outlined),
                                  label: Text(
                                    'Buka ulang shift ${shift.NamaKasir} '
                                    '(ditutup ${FormatWaktu.FormatJam(shift.DitutupPada!.toLocal())})',
                                  ),
                                ),
                              ],
                              const SizedBox(height: TokenJarak.jarak8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  TextButton(
                                    onPressed: _sibuk ? null : () => ref.read(penyediaSesi.notifier).Keluar(),
                                    child: const Text('Ganti kasir'),
                                  ),
                                ],
                              ),
                              Text(
                                'Shift tetap bisa dibuka tanpa internet dan akan terkirim otomatis saat online.',
                                textAlign: TextAlign.center,
                                style: teks.bodySmall,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Dialog alasan buka ulang shift (K-18), minimal 5 huruf; mengembalikan alasan yang sudah dirapikan.
class _DialogAlasanBukaUlang extends StatefulWidget {
  const _DialogAlasanBukaUlang({required this.shift});

  final BarisShift shift;

  @override
  State<_DialogAlasanBukaUlang> createState() => _DialogAlasanBukaUlangState();
}

class _DialogAlasanBukaUlangState extends State<_DialogAlasanBukaUlang> {
  final _alasan = TextEditingController();
  String? _galat;

  @override
  void dispose() {
    _alasan.dispose();
    super.dispose();
  }

  void _Lanjut() {
    final alasan = _alasan.text.trim();
    if (alasan.length < LayananShift.panjangAlasanBukaUlangMinimal) {
      setState(() => _galat = 'Tulis alasan minimal 5 huruf.');
      return;
    }
    Navigator.of(context).pop(alasan);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Buka ulang shift'),
      content: SizedBox(
        width: 420,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Shift ${widget.shift.NamaKasir} akan dibuka lagi. Hasil tutup sebelumnya dibatalkan dan dicatat; '
              'tutup ulang setelah selesai.',
            ),
            const SizedBox(height: TokenJarak.jarak16),
            TextField(
              key: const ValueKey('AlasanBukaUlang'),
              controller: _alasan,
              autofocus: true,
              maxLength: 255,
              decoration: InputDecoration(
                labelText: 'Alasan',
                hintText: 'Contoh: salah tekan tutup, masih ada pelanggan',
                errorText: _galat,
              ),
              onSubmitted: (_) => _Lanjut(),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
        FilledButton(onPressed: _Lanjut, child: const Text('Lanjut')),
      ],
    );
  }
}
