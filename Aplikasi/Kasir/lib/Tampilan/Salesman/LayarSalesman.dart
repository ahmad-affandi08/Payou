import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Aplikasi/PenyediaSalesman.dart';
import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Salesman/LayananSalesman.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Komponen/FormatWaktu.dart';
import 'BagianRiwayatSalesman.dart';
import 'DetailPelangganSalesman.dart';
import 'DialogSelesaiKunjungan.dart';

/// Bagian layar Salesman.
enum TabSalesman { Pelanggan, Riwayat }

/// Ruang kerja Salesman (Modul Salesman bagian 2, §9.7, SLS-11): daftar pelanggan offline (cari nama/HP, tier, sisa
/// piutang & jatuh tempo, kunjungan terakhir), rincian pelanggan dengan piutang online, mulai/selesai kunjungan dengan
/// lokasi sekali, ambil pesanan (panel tugas lewat [saatAmbilPesanan]), dan riwayat 7 hari dengan status kirim.
///
/// Data pelanggan & stok kantor diunduh otomatis saat layar pertama kali aktif bila cache kosong atau sudah lebih dari
/// [selangSegarkan], dan bisa diperbarui manual. Semua catatan tetap bisa dibuat offline.
class LayarSalesman extends ConsumerStatefulWidget {
  const LayarSalesman({super.key, required this.staf, required this.saatAmbilPesanan, this.aktif = true});

  static const Duration selangSegarkan = Duration(minutes: 30);

  /// Lebar area kerja minimum untuk daftar + rincian berdampingan.
  static const double lebarDuaKolom = 880;

  final StafLokal staf;

  /// Buka panel ambil pesanan untuk Uuid pelanggan.
  final ValueChanged<String> saatAmbilPesanan;

  /// Layar sedang tampil di area kerja (unduh otomatis hanya saat aktif).
  final bool aktif;

  @override
  ConsumerState<LayarSalesman> createState() => _LayarSalesmanState();
}

class _LayarSalesmanState extends ConsumerState<LayarSalesman> {
  final _cari = TextEditingController();
  TabSalesman _tab = TabSalesman.Pelanggan;
  String? _dipilih;
  bool _memperbarui = false;
  bool _sudahOtomatis = false;
  String? _pesanPembaruan;

  /// Uuid kunjungan yang lokasinya sedang dicari.
  String? _mencariLokasi;
  bool _sibukKunjungan = false;

  bool get _boleh => LayananSalesman.CekBoleh(widget.staf);

  @override
  void initState() {
    super.initState();
    if (widget.aktif) {
      unawaited(Future<void>.microtask(_PerbaruiOtomatis));
    }
  }

  @override
  void didUpdateWidget(LayarSalesman lama) {
    super.didUpdateWidget(lama);
    if (widget.aktif && !lama.aktif) {
      unawaited(Future<void>.microtask(_PerbaruiOtomatis));
    }
  }

  @override
  void dispose() {
    _cari.dispose();
    super.dispose();
  }

  Future<void> _PerbaruiOtomatis() async {
    if (_sudahOtomatis || !mounted || !_boleh) {
      return;
    }
    _sudahOtomatis = true;
    final terakhir = await ref.read(penyediaPelangganSalesmanDiperbaruiPada.future);
    final sekarang = ref.read(penyediaJam)();
    if (terakhir == null || sekarang.difference(terakhir) > LayarSalesman.selangSegarkan) {
      await _Perbarui(diam: true);
    }
  }

  /// Unduh pelanggan & stok kantor. [diam] = tanpa pesan bila offline (unduhan otomatis).
  Future<void> _Perbarui({bool diam = false}) async {
    if (_memperbarui || !mounted) {
      return;
    }
    setState(() {
      _memperbarui = true;
      _pesanPembaruan = null;
    });
    final layanan = ref.read(penyediaLayananSalesman);
    String? pesan;
    try {
      final jumlah = await layanan.PerbaruiPelanggan(widget.staf);
      try {
        await layanan.PerbaruiStok(widget.staf);
      } on GalatKasir {
        // Stok hanya petunjuk: gagal stok tidak membatalkan pembaruan pelanggan.
      }
      ref.read(penyediaKoneksi.notifier).Tandai(StatusKoneksi.Online);
      pesan = diam ? null : '$jumlah pelanggan diperbarui.';
    } on GalatKasir catch (galat) {
      if (galat.kode == 'PerluOnline') {
        ref.read(penyediaKoneksi.notifier).Tandai(StatusKoneksi.Offline);
        pesan = diam ? null : 'Offline. Data pelanggan terakhir tetap dipakai.';
      } else {
        pesan = galat.pesan;
      }
    }
    if (mounted) {
      setState(() {
        _memperbarui = false;
        _pesanPembaruan = pesan;
      });
    }
  }

  Future<void> _MulaiKunjungan(PelangganSalesman pelanggan) async {
    if (_sibukKunjungan) {
      return;
    }
    setState(() => _sibukKunjungan = true);
    final layanan = ref.read(penyediaLayananSalesman);
    try {
      final uuid = await layanan.MulaiKunjungan(pelanggan, widget.staf);
      if (!mounted) {
        return;
      }
      setState(() {
        _sibukKunjungan = false;
        _mencariLokasi = uuid;
      });
      await layanan.CatatLokasi(uuid);
      if (mounted) {
        setState(() => _mencariLokasi = null);
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _sibukKunjungan = false);
        ScaffoldMessenger.maybeOf(context)?.showSnackBar(SnackBar(content: Text(galat.pesan)));
      }
    }
  }

  Future<void> _SelesaikanKunjungan(BarisKunjunganSalesLokal kunjungan) async {
    final pilihan = await showDialog<HasilDialogKunjungan>(
      context: context,
      builder: (_) => DialogSelesaiKunjungan(kunjungan: kunjungan),
    );
    if (pilihan == null || !mounted) {
      return;
    }
    final sesi = ref.read(penyediaSesi.notifier);
    try {
      await ref
          .read(penyediaLayananSalesman)
          .SelesaikanKunjungan(kunjungan, hasil: pilihan.hasil, catatan: pilihan.catatan, staf: widget.staf);
      if (mounted) {
        ScaffoldMessenger.maybeOf(context)?.showSnackBar(
          SnackBar(content: Text('Kunjungan ke ${kunjungan.NamaPelanggan} selesai. Dikirim otomatis saat online.')),
        );
      }
      await sesi.Sinkronkan();
    } on GalatKasir catch (galat) {
      if (mounted) {
        ScaffoldMessenger.maybeOf(context)?.showSnackBar(SnackBar(content: Text(galat.pesan)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final sempit = MediaQuery.sizeOf(context).width < 600;
    final tepi = sempit ? TokenJarak.jarak16 : TokenJarak.jarak24;

    if (!_boleh) {
      return Padding(
        padding: EdgeInsets.all(tepi),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Semantics(header: true, child: Text('Salesman', style: teks.headlineSmall)),
            const SizedBox(height: TokenJarak.jarak16),
            Text(
              '${widget.staf.nama} tidak punya izin salesman. Minta pemilik atau admin memberi peran Salesman di '
              'back-office, lalu sinkronkan perangkat.',
              style: teks.bodyMedium,
            ),
          ],
        ),
      );
    }

    final berjalan = ref.watch(penyediaKunjunganBerjalan(widget.staf.uuid)).value;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: EdgeInsets.fromLTRB(tepi, tepi, tepi, TokenJarak.jarak12),
          child: Wrap(
            spacing: TokenJarak.jarak16,
            runSpacing: TokenJarak.jarak8,
            crossAxisAlignment: WrapCrossAlignment.center,
            alignment: WrapAlignment.spaceBetween,
            children: [
              Semantics(header: true, child: Text('Salesman', style: teks.headlineSmall)),
              SegmentedButton<TabSalesman>(
                showSelectedIcon: false,
                segments: const [
                  ButtonSegment(
                    value: TabSalesman.Pelanggan,
                    label: Text('Pelanggan'),
                    icon: Icon(Icons.storefront_outlined),
                  ),
                  ButtonSegment(value: TabSalesman.Riwayat, label: Text('Riwayat'), icon: Icon(Icons.history)),
                ],
                selected: {_tab},
                onSelectionChanged: (pilih) => setState(() => _tab = pilih.first),
              ),
            ],
          ),
        ),
        if (berjalan != null)
          Padding(
            padding: EdgeInsets.fromLTRB(tepi, 0, tepi, TokenJarak.jarak12),
            child: _BannerKunjungan(
              kunjungan: berjalan,
              mencariLokasi: _mencariLokasi == berjalan.Uuid,
              saatAmbilPesanan: () => widget.saatAmbilPesanan(berjalan.UuidPelanggan),
              saatSelesai: () => _SelesaikanKunjungan(berjalan),
            ),
          ),
        Divider(height: TokenJarak.tebalGaris, thickness: TokenJarak.tebalGaris, color: warna.garis),
        Expanded(
          child: switch (_tab) {
            TabSalesman.Pelanggan => _BangunPelanggan(berjalan, tepi),
            TabSalesman.Riwayat => BagianRiwayatSalesman(staf: widget.staf, tepi: tepi),
          },
        ),
      ],
    );
  }

  Widget _BangunPelanggan(BarisKunjunganSalesLokal? berjalan, double tepi) => LayoutBuilder(
    builder: (context, batas) {
      final daftar = ref.watch(penyediaPelangganSalesman);
      final semua = daftar.value ?? const <PelangganSalesman>[];
      final dipilih = semua.where((p) => p.uuid == _dipilih).firstOrNull;
      final duaKolom = batas.maxWidth >= LayarSalesman.lebarDuaKolom;

      Widget Detail(PelangganSalesman p) => DetailPelangganSalesman(
        key: ValueKey('Detail-${p.uuid}'),
        pelanggan: p,
        staf: widget.staf,
        kunjunganBerjalan: berjalan,
        mencariLokasi: berjalan != null && _mencariLokasi == berjalan.Uuid,
        sibuk: _sibukKunjungan,
        tepi: tepi,
        saatKembali: duaKolom ? null : () => setState(() => _dipilih = null),
        saatMulaiKunjungan: () => _MulaiKunjungan(p),
        saatSelesaiKunjungan: berjalan == null ? null : () => _SelesaikanKunjungan(berjalan),
        saatAmbilPesanan: () => widget.saatAmbilPesanan(p.uuid),
      );

      final daftarWidget = _DaftarPelanggan(
        daftar: daftar,
        cari: _cari,
        dipilih: _dipilih,
        tepi: tepi,
        memperbarui: _memperbarui,
        pesanPembaruan: _pesanPembaruan,
        uuidBerkunjung: berjalan?.UuidPelanggan,
        saatCari: () => setState(() {}),
        saatPilih: (p) => setState(() => _dipilih = p.uuid),
        saatPerbarui: _Perbarui,
      );

      if (!duaKolom) {
        return dipilih == null ? daftarWidget : Detail(dipilih);
      }
      final warna = TokenWarna.AmbilDari(context);
      return Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(width: 400, child: daftarWidget),
          VerticalDivider(width: TokenJarak.tebalGaris, thickness: TokenJarak.tebalGaris, color: warna.garis),
          Expanded(
            child: dipilih == null
                ? Center(
                    child: Padding(
                      padding: EdgeInsets.all(tepi),
                      child: Text(
                        'Pilih pelanggan untuk melihat piutang, mulai kunjungan, atau ambil pesanan.',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: warna.teksSekunder),
                      ),
                    ),
                  )
                : Detail(dipilih),
          ),
        ],
      );
    },
  );
}

/// Kunjungan yang sedang berjalan selalu terlihat di atas layar Salesman.
class _BannerKunjungan extends StatelessWidget {
  const _BannerKunjungan({
    required this.kunjungan,
    required this.mencariLokasi,
    required this.saatAmbilPesanan,
    required this.saatSelesai,
  });

  final BarisKunjunganSalesLokal kunjungan;
  final bool mencariLokasi;
  final VoidCallback saatAmbilPesanan;
  final VoidCallback saatSelesai;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Semantics(
      container: true,
      label: 'Kunjungan berjalan',
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: warna.permukaan,
          border: Border.all(color: warna.info, width: TokenJarak.tebalGaris),
          borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
        ),
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak12),
          child: Wrap(
            spacing: TokenJarak.jarak12,
            runSpacing: TokenJarak.jarak8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.directions_walk, size: TokenJarak.ikonSedang, color: warna.info),
                  const SizedBox(width: TokenJarak.jarak8),
                  Flexible(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Berkunjung ke ${kunjungan.NamaPelanggan}',
                          style: teks.titleSmall,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        Text(
                          'Sejak ${FormatWaktu.FormatJam(kunjungan.MasukPada)} | '
                          '${TeksLokasiKunjungan(kunjungan, mencari: mencariLokasi)}',
                          style: teks.bodySmall,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              Wrap(
                spacing: TokenJarak.jarak8,
                runSpacing: TokenJarak.jarak8,
                children: [
                  SizedBox(
                    height: TokenJarak.targetSentuh,
                    child: FilledButton.icon(
                      onPressed: saatAmbilPesanan,
                      icon: const Icon(Icons.add_shopping_cart),
                      label: const Text('Ambil pesanan'),
                    ),
                  ),
                  SizedBox(
                    height: TokenJarak.targetSentuh,
                    child: OutlinedButton.icon(
                      onPressed: saatSelesai,
                      icon: const Icon(Icons.check_circle_outline),
                      label: const Text('Selesai kunjungan'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Keterangan lokasi kunjungan berjalan (selalu berteks, tidak hanya warna).
String TeksLokasiKunjungan(BarisKunjunganSalesLokal k, {required bool mencari}) {
  if (k.Latitude != null && k.Longitude != null) {
    return k.AkurasiMeter == null ? 'Lokasi tercatat' : 'Lokasi tercatat (±${k.AkurasiMeter} m)';
  }
  return mencari ? 'Mencari lokasi…' : 'Lokasi tidak tersedia';
}

/// Daftar pelanggan offline dengan kotak cari & pembaruan data.
class _DaftarPelanggan extends StatelessWidget {
  const _DaftarPelanggan({
    required this.daftar,
    required this.cari,
    required this.dipilih,
    required this.tepi,
    required this.memperbarui,
    required this.pesanPembaruan,
    required this.uuidBerkunjung,
    required this.saatCari,
    required this.saatPilih,
    required this.saatPerbarui,
  });

  final AsyncValue<List<PelangganSalesman>> daftar;
  final TextEditingController cari;
  final String? dipilih;
  final double tepi;
  final bool memperbarui;
  final String? pesanPembaruan;
  final String? uuidBerkunjung;
  final VoidCallback saatCari;
  final ValueChanged<PelangganSalesman> saatPilih;
  final Future<void> Function() saatPerbarui;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final semua = daftar.value ?? const <PelangganSalesman>[];
    final hasil = LayananSalesman.SaringPelanggan(semua, cari.text);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: EdgeInsets.fromLTRB(tepi, TokenJarak.jarak16, tepi, TokenJarak.jarak8),
          child: TextField(
            key: const ValueKey('CariPelangganSalesman'),
            controller: cari,
            onChanged: (_) => saatCari(),
            decoration: const InputDecoration(
              labelText: 'Cari pelanggan',
              hintText: 'Nama toko atau nomor HP',
              prefixIcon: Icon(Icons.search),
              border: OutlineInputBorder(),
            ),
          ),
        ),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: tepi),
          child: Row(
            children: [
              Expanded(child: _InfoPembaruan(jumlah: semua.length)),
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: TextButton.icon(
                  onPressed: memperbarui ? null : saatPerbarui,
                  icon: memperbarui
                      ? const SizedBox.square(
                          dimension: TokenJarak.ikonKecil,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.sync),
                  label: Text(memperbarui ? 'Memperbarui…' : 'Perbarui'),
                ),
              ),
            ],
          ),
        ),
        if (pesanPembaruan != null)
          Padding(
            padding: EdgeInsets.symmetric(horizontal: tepi),
            child: Text(pesanPembaruan!, style: teks.bodySmall?.copyWith(color: warna.teksSekunder)),
          ),
        const SizedBox(height: TokenJarak.jarak8),
        Expanded(
          child: switch (daftar) {
            AsyncValue(isLoading: true, value: null) => const Align(
              alignment: Alignment.topCenter,
              child: LinearProgressIndicator(),
            ),
            AsyncValue(hasError: true, value: null) => Padding(
              padding: EdgeInsets.all(tepi),
              child: Text('Daftar pelanggan tidak bisa dibaca. Coba lagi.', style: TextStyle(color: warna.bahaya)),
            ),
            _ when semua.isEmpty => Padding(
              padding: EdgeInsets.all(tepi),
              child: KeadaanKosong(
                ikon: Icons.people_outline,
                ilustrasi: memperbarui ? IlustrasiKosong.Sinkron : IlustrasiKosong.Pelanggan,
                judul: memperbarui ? 'Mengunduh data pelanggan…' : 'Belum ada data pelanggan di perangkat ini',
                keterangan: memperbarui ? null : 'Sambungkan ke internet lalu ketuk Perbarui.',
              ),
            ),
            _ when hasil.isEmpty => Padding(
              padding: EdgeInsets.all(tepi),
              child: KeadaanKosong(
                ikon: Icons.search_off,
                ilustrasi: IlustrasiKosong.Cari,
                ringkas: true,
                judul: 'Tidak ada pelanggan yang cocok',
                keterangan: 'Tidak ada hasil untuk "${cari.text.trim()}".',
              ),
            ),
            _ => ListView.separated(
              padding: EdgeInsets.only(bottom: tepi),
              itemCount: hasil.length,
              separatorBuilder: (_, _) => Divider(height: TokenJarak.tebalGaris, color: warna.garis),
              itemBuilder: (_, i) => _BarisPelanggan(
                pelanggan: hasil[i],
                dipilih: hasil[i].uuid == dipilih,
                berkunjung: hasil[i].uuid == uuidBerkunjung,
                tepi: tepi,
                saatDiketuk: () => saatPilih(hasil[i]),
              ),
            ),
          },
        ),
      ],
    );
  }
}

class _InfoPembaruan extends ConsumerWidget {
  const _InfoPembaruan({required this.jumlah});

  final int jumlah;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final waktu = ref.watch(penyediaPelangganSalesmanDiperbaruiPada).value;
    return Text(
      waktu == null
          ? 'Belum pernah diperbarui'
          : '$jumlah pelanggan | terakhir diperbarui ${FormatWaktu.FormatTanggalJam(waktu)}',
      style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
    );
  }
}

class _BarisPelanggan extends StatelessWidget {
  const _BarisPelanggan({
    required this.pelanggan,
    required this.dipilih,
    required this.berkunjung,
    required this.tepi,
    required this.saatDiketuk,
  });

  final PelangganSalesman pelanggan;
  final bool dipilih;
  final bool berkunjung;
  final double tepi;
  final VoidCallback saatDiketuk;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final p = pelanggan;
    final kunjungan = p.terakhirDikunjungiPada;
    return Material(
      color: dipilih ? warna.latar : warna.permukaan,
      child: InkWell(
        onTap: saatDiketuk,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: TokenJarak.targetSentuh),
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: tepi, vertical: TokenJarak.jarak12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(p.nama, maxLines: 2, overflow: TextOverflow.ellipsis, style: teks.titleSmall),
                    ),
                    if (p.namaTier != null) ...[
                      const SizedBox(width: TokenJarak.jarak8),
                      Text(p.namaTier!, style: teks.labelMedium?.copyWith(color: warna.teksSekunder)),
                    ],
                  ],
                ),
                const SizedBox(height: TokenJarak.jarak4),
                Text(
                  p.sisaPiutang.BernilaiNol() ? 'Tanpa piutang' : 'Sisa piutang ${p.sisaPiutang.FormatRupiah()}',
                  style: teks.bodySmall,
                ),
                if (p.adaLewatJatuhTempo)
                  Row(
                    children: [
                      Icon(Icons.warning_amber_outlined, size: TokenJarak.ikonKecil, color: warna.bahaya),
                      const SizedBox(width: TokenJarak.jarak4),
                      Expanded(
                        child: Text(
                          'Lewat jatuh tempo ${p.hariLewatJatuhTempo} hari | '
                          '${p.jumlahPiutangJatuhTempo.FormatRupiah()}',
                          style: teks.bodySmall?.copyWith(color: warna.bahaya),
                        ),
                      ),
                    ],
                  ),
                Text(
                  berkunjung
                      ? 'Sedang dikunjungi'
                      : kunjungan == null
                      ? 'Belum pernah dikunjungi'
                      : 'Terakhir dikunjungi ${FormatWaktu.FormatTanggal(kunjungan)}',
                  style: teks.bodySmall?.copyWith(color: berkunjung ? warna.info : warna.teksSekunder),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
