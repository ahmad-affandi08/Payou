import '../test/Pendukung/PasangPemilik.dart';

/// Dasbor contoh dua outlet kafe untuk foto situs & Play Store.
Map<String, Object?> DasborDemo() => {
  ...DasborUji(omzet: '8475000.00'),
  'Ringkasan': {
    'Omzet': '8475000.00',
    'LabaKotor': '3390000.00',
    'Transaksi': 214,
    'RataRata': '39603.00',
    'OmzetKemarin': '7430000.00',
    'OmzetMingguLalu': '7910000.00',
  },
  'PerOutlet': [
    {'Uuid': 'O1', 'Nama': 'Solo Baru', 'Omzet': '5120000.00', 'Transaksi': 131},
    {'Uuid': 'O2', 'Nama': 'Kartasura', 'Omzet': '3355000.00', 'Transaksi': 83},
  ],
  'PerJam': [
    for (final (jam, omzet) in [
      (8, 310000),
      (9, 520000),
      (10, 640000),
      (11, 820000),
      (12, 1210000),
      (13, 980000),
      (14, 610000),
      (15, 720000),
      (16, 890000),
      (17, 760000),
      (18, 620000),
      (19, 395000),
    ])
      {'Jam': jam, 'Omzet': '$omzet.00'},
  ],
  'ProdukTeratas': [
    {'Nama': 'Es Kopi Susu Aren', 'Jumlah': '96.0000', 'Omzet': '1728000.00'},
    {'Nama': 'Matcha Latte', 'Jumlah': '41.0000', 'Omzet': '1066000.00'},
    {'Nama': 'Croissant Cokelat', 'Jumlah': '38.0000', 'Omzet': '950000.00'},
  ],
  'PerluTindakan': [
    {'Jenis': 'StokMenipis', 'Judul': 'Susu segar hampir habis', 'Keterangan': 'Sisa 4 liter di Solo Baru'},
  ],
};
