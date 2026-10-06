/// Nomor dokumen untuk dicetak (D-71): kode perangkat dibuang supaya pendek dan mudah dibacakan, misalnya
/// `INV/SLB/260924/POS-001-0042` menjadi `INV/SLB/260924/0042`. Nomor lengkap tetap yang tersimpan & disinkronkan
/// (dipakai untuk keunikan offline); ini hanya tampilan di struk. Nomor yang tidak berpola dikembalikan apa adanya.
String PendekkanNomorStruk(String nomor) {
  final cocok = RegExp(r'^(.+/)[^/]*-(\d+)$').firstMatch(nomor);
  return cocok == null ? nomor : '${cocok.group(1)}${cocok.group(2)}';
}
