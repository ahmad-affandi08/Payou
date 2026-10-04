import '../test/Pendukung/KatalogUji.dart';

const kategoriKopi = '01K5KAT0000000000000K0P101';
const kategoriNonKopi = '01K5KAT00000000000N0NK0P11';
const kategoriMakanan = '01K5KAT000000000000MAKAN01';

/// Katalog contoh kafe (12 menu, 3 kategori) untuk foto situs & Play Store.
Map<String, Object?> KatalogKafe() {
  final katalog = KatalogUji();
  final menu = [
    ('Es Kopi Susu Aren', kategoriKopi, '18000.00'),
    ('Americano Panas', kategoriKopi, '15000.00'),
    ('Kopi Tubruk Gayo', kategoriKopi, '12000.00'),
    ('Cappuccino', kategoriKopi, '24000.00'),
    ('Matcha Latte', kategoriNonKopi, '26000.00'),
    ('Es Teh Leci', kategoriNonKopi, '16000.00'),
    ('Cokelat Panas', kategoriNonKopi, '22000.00'),
    ('Croissant Cokelat', kategoriMakanan, '25000.00'),
    ('Roti Bakar Srikaya', kategoriMakanan, '20000.00'),
    ('Pisang Goreng Keju', kategoriMakanan, '18000.00'),
    ('Nasi Goreng Kampung', kategoriMakanan, '32000.00'),
    ('Mie Goreng Jawa', kategoriMakanan, '30000.00'),
  ];
  String Id(String awalan, int i) => '$awalan${i.toString().padLeft(26 - awalan.length, '0')}';
  katalog['Kategori'] = [
    {'Uuid': kategoriKopi, 'UuidInduk': null, 'Nama': 'Kopi', 'Urutan': 1},
    {'Uuid': kategoriNonKopi, 'UuidInduk': null, 'Nama': 'Non-kopi', 'Urutan': 2},
    {'Uuid': kategoriMakanan, 'UuidInduk': null, 'Nama': 'Makanan', 'Urutan': 3},
  ];
  katalog['Produk'] = [
    for (final (i, m) in menu.indexed)
      ProdukUji(Id('01K5PRDSITUS', i), m.$1, jenis: 'Resep', sku: 'MN-${i + 1}', kategori: m.$2),
  ];
  katalog['ProdukSatuan'] = [
    for (final (i, _) in menu.indexed) SatuanProdukUji(Id('01K5PSSITUS', i), Id('01K5PRDSITUS', i), UuidUji.satuanPcs),
  ];
  katalog['ProdukHarga'] = [
    for (final (i, m) in menu.indexed)
      HargaUji(Id('01K5HRGSITUS', i), Id('01K5PRDSITUS', i), Id('01K5PSSITUS', i), m.$3),
  ];
  katalog['ProdukBarcode'] = <Object?>[];
  katalog['ProdukKelompokPilihan'] = <Object?>[];

  return katalog;
}
