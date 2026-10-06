/// D-35 edisi Lisensi: alamat server toko yang memasang Payoung di server & domainnya sendiri, diketik di aplikasi
/// Kasir (aktivasi) atau Pemilik (masuk). `kasir.toko.id` → `https://kasir.toko.id/`.
///
/// Null bila kosong atau tidak sah (skema selain http/https, tanpa host, berisi query/fragmen/info pengguna).
Uri? NormalkanAlamatServer(String teks) {
  final bersih = teks.trim();
  if (bersih.isEmpty) {
    return null;
  }
  final alamat = Uri.tryParse(bersih.contains('://') ? bersih : 'https://$bersih');
  if (alamat == null ||
      !CekSkemaWeb(alamat) ||
      alamat.host.isEmpty ||
      alamat.hasQuery ||
      alamat.hasFragment ||
      alamat.userInfo.isNotEmpty) {
    return null;
  }
  final jalur = alamat.path.isEmpty ? '/' : (alamat.path.endsWith('/') ? alamat.path : '${alamat.path}/');
  return alamat.replace(path: jalur);
}

/// Hanya http/https yang dianggap alamat server.
bool CekSkemaWeb(Uri alamat) => alamat.scheme == 'https' || alamat.scheme == 'http';
