import 'dart:async';

import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/RepositoriKasir.dart';
import '../GalatKasir.dart';
import 'StafLokal.dart';

/// Persetujuan jarak jauh (X4, §19.2): bila penyetuju tidak di tempat, kasir meminta persetujuan yang diputuskan lewat
/// Aplikasi Owner. Butuh online di kedua sisi dan fitur paket `persetujuan.jarak-jauh` (dibawa data awal dan konfigurasi aplikasi). Perangkat
/// memantau status tiap [jeda]; setelah disetujui, penyetujunya dipakai persis seperti penyetuju yang memasukkan PIN
/// (izinnya diperiksa lagi saat dokumen disinkronkan).
class LayananPersetujuanJarakJauh {
  LayananPersetujuanJarakJauh({
    required this.klien,
    required this.repositori,
    PembuatUlid? ulid,
    this.jeda = const Duration(seconds: 3),
  }) : _ulid = ulid ?? PembuatUlid();

  final KlienPos klien;
  final RepositoriKasir repositori;
  final PembuatUlid _ulid;
  final Duration jeda;

  /// Judul bawaan per izin yang dimintakan.
  static String AmbilJudulBawaan(String? izin) => switch (izin) {
    IzinKasir.kasKeluarSetujui => 'Kas keluar di atas batas',
    IzinKasir.penjualanDiskonSetujui => 'Diskon di atas batas',
    IzinKasir.shiftSelisihSetujui => 'Selisih kas tutup shift',
    IzinKasir.penjualanVoid => 'Pembatalan transaksi (void)',
    IzinKasir.penjualanRetur => 'Retur penjualan',
    IzinKasir.penjualanReturTanpaStruk => 'Retur tanpa struk',
    IzinKasir.penjualanTempoSetujui => 'Penjualan tempo',
    _ => 'Persetujuan pemilik',
  };

  Future<bool> CekTersedia() async => await repositori.AmbilPengaturan(KunciPengaturan.persetujuanJarakJauh) == '1';

  /// Simpan keadaan fitur dari server (konfigurasi aplikasi) ke pengaturan lokal. Null (server lama) = tidak mengubah.
  Future<void> SimpanKeadaan(bool? aktif) async {
    if (aktif != null) {
      await repositori.SimpanPengaturan(KunciPengaturan.persetujuanJarakJauh, aktif ? '1' : '0');
    }
  }

  /// Tanya server apakah fitur paket aktif sekarang, supaya perubahan paket di konsol langsung terlihat tanpa menunggu
  /// "Perbarui data kasir". Offline atau galat apa pun = pakai keadaan lokal terakhir (tidak melempar).
  Future<bool> SegarkanTersedia({Duration batas = const Duration(seconds: 4)}) async {
    try {
      final konfigurasi = await klien.AmbilKonfigurasiAplikasi().timeout(batas);
      await SimpanKeadaan(konfigurasi.persetujuanJarakJauh);
    } on Object {
      // Offline, kedaluwarsa, atau galat server: keadaan lokal terakhir tetap berlaku.
    }
    return CekTersedia();
  }

  /// Kirim permintaan. [izin] null (atau [hanyaPemilik]) = hanya pemilik yang bisa memutuskan.
  Future<PermintaanPersetujuanPos> Ajukan({
    required StafLokal pemohon,
    required String? izin,
    required String judul,
    required List<({String label, String nilai})> rincian,
    bool hanyaPemilik = false,
    Uang? nilai,
  }) async {
    try {
      return await klien.AjukanPersetujuanJarakJauh(
        uuid: _ulid.Buat(),
        izin: hanyaPemilik ? null : izin,
        uuidPengguna: pemohon.uuid,
        judul: judul,
        rincian: [for (final r in rincian) (label: _Potong(r.label, 40), nilai: _Potong(r.nilai, 160))]
            .take(12)
            .toList(),
        nilai: nilai?.KeString(),
      );
    } on GalatJaringan {
      throw const GalatKasir(
        'PerluOnline',
        'Persetujuan jarak jauh perlu online. Periksa koneksi, atau minta PIN penyetuju di tempat.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  /// Pantau status sampai final (Disetujui/Ditolak/Dibatalkan/Kedaluwarsa). Gangguan jaringan sesaat diabaikan (dicoba
  /// lagi pada putaran berikutnya); galat lain menghentikan pantauan.
  Stream<PermintaanPersetujuanPos> Pantau(String uuid) async* {
    while (true) {
      await Future<void>.delayed(jeda);
      final PermintaanPersetujuanPos hasil;
      try {
        hasil = await klien.AmbilPersetujuanJarakJauh(uuid);
      } on GalatJaringan {
        continue;
      } on GalatApi catch (galat) {
        throw GalatKasir(galat.kode, galat.pesan);
      }
      yield hasil;
      if (hasil.selesai) {
        return;
      }
    }
  }

  /// Berhenti menunggu (kasir menutup dialog). Gagal jaringan diabaikan: permintaan kedaluwarsa sendiri.
  Future<void> Batalkan(String uuid) async {
    try {
      await klien.BatalkanPersetujuanJarakJauh(uuid);
    } on GalatJaringan {
      // Permintaan kedaluwarsa sendiri setelah masa berlakunya.
    } on GalatApi {
      // Sudah final atau tidak dikenal: tidak ada yang perlu dibatalkan.
    }
  }

  /// Penyetuju dari server sebagai staf; dipakai sebagai penyetuju dokumen seperti hasil PIN.
  static StafLokal KeStaf(PenyetujuJarakJauh p) =>
      StafLokal(uuid: p.uuid, nama: p.nama, pemilik: p.pemilik, izin: p.izin);

  static String _Potong(String teks, int maks) => teks.length <= maks ? teks : '${teks.substring(0, maks - 1)}…';
}
