import 'KatalogUji.dart';

/// Respons `GET /api/pos/v1/gudang/*` uji (POS-25) memakai produk katalog uji "Kopi Senja".
abstract final class UuidGudangUji {
  static const String po = '01K5P0000000000000000P0001';
  static const String transfer = '01K5TF000000000000000TF001';
  static const String opname = '01K5S0000000000000000S0001';
}

/// PO PO/SLB/2609/0007: roti tawar 2 lusin (sisa 2), susu UHT 24 pcs ber-batch (sisa 24), croissant 10 pcs (sisa 4).
Map<String, Object?> PesananGudangUji({String sisaRoti = '2.0000'}) => {
  'Uuid': UuidGudangUji.po,
  'Nomor': 'PO/SLB/2609/0007',
  'Tanggal': '2026-09-20',
  'PerkiraanTiba': '2026-09-24',
  'Status': 'DiterimaSebagian',
  'LabelStatus': 'Diterima sebagian',
  'NamaPemasok': 'PT Sumber Pangan Nusantara',
  'NamaGudang': 'Toko Solo Baru',
  'Baris': [
    {
      'Urutan': 1,
      'UuidProduk': UuidUji.roti,
      'NamaProduk': 'Roti Tawar Gandum',
      'Sku': 'RTG-01',
      'SimbolSatuan': 'lsn',
      'Konversi': '12.0000',
      'Pelacakan': 'Tidak',
      'Jumlah': '2.0000',
      'JumlahDiterima': '0.0000',
      'Sisa': sisaRoti,
    },
    {
      'Urutan': 2,
      'UuidProduk': UuidUji.susuUht,
      'NamaProduk': 'Susu UHT 1 Liter',
      'Sku': 'UHT-1L',
      'SimbolSatuan': 'pcs',
      'Konversi': '1.0000',
      'Pelacakan': 'Batch',
      'Jumlah': '24.0000',
      'JumlahDiterima': '0.0000',
      'Sisa': '24.0000',
    },
    {
      'Urutan': 3,
      'UuidProduk': UuidUji.croissant,
      'NamaProduk': 'Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo',
      'Sku': 'CRS-01',
      'SimbolSatuan': 'pcs',
      'Konversi': '1.0000',
      'Pelacakan': 'Tidak',
      'Jumlah': '10.0000',
      'JumlahDiterima': '6.0000',
      'Sisa': '4.0000',
    },
  ],
};

/// Transfer TF/GDG-SLB/2609/0003 dari gudang pusat: croissant 30 pcs (sisa [sisa]).
Map<String, Object?> TransferGudangUji({String sisa = '30.0000', String status = 'Dikirim'}) => {
  'Uuid': UuidGudangUji.transfer,
  'Nomor': 'TF/GDG-SLB/2609/0003',
  'Tanggal': '2026-09-24',
  'Status': status,
  'LabelStatus': status == 'Diterima' ? 'Diterima' : 'Dikirim',
  'NamaAsal': 'Pusat | Gudang Utama',
  'NamaTujuan': 'Kopi Senja Solo Baru | Toko',
  'Catatan': null,
  'Baris': [
    {
      'Urutan': 1,
      'UuidProduk': UuidUji.croissant,
      'NamaProduk': 'Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo',
      'Sku': 'CRS-01',
      'SimbolSatuan': 'pcs',
      'BolehDesimal': false,
      'NomorBatch': null,
      'TanggalKedaluwarsa': null,
      'NomorSeri': null,
      'JumlahDikirim': '30.0000',
      'JumlahDiterima': '0.0000',
      'Sisa': sisa,
    },
  ],
};

/// Opname SO/SLB/2609/001 hitung buta: roti (belum dihitung), croissant (tersimpan 5).
Map<String, Object?> OpnameGudangUji({int dihitung = 1, String? fisikRoti}) => {
  'Uuid': UuidGudangUji.opname,
  'Nomor': 'SO/SLB/2609/001',
  'NamaLokasi': 'Kopi Senja Solo Baru | Toko',
  'NamaKategori': null,
  'HitungButa': true,
  'SnapshotPada': '2026-09-24T00:30:00Z',
  'JumlahBaris': 2,
  'JumlahDihitung': dihitung,
  'Baris': [
    {
      'Urutan': 1,
      'UuidProduk': UuidUji.roti,
      'NamaProduk': 'Roti Tawar Gandum',
      'Sku': 'RTG-01',
      'SimbolSatuan': 'pcs',
      'BolehDesimal': false,
      'Pelacakan': 'Tidak',
      'NomorBatch': null,
      'TanggalKedaluwarsa': null,
      'NomorSeri': null,
      'JumlahSistem': null,
      'JumlahFisik': fisikRoti,
    },
    {
      'Urutan': 2,
      'UuidProduk': UuidUji.croissant,
      'NamaProduk': 'Croissant Mentega Prancis Isi Cokelat Lumer Ukuran Jumbo',
      'Sku': 'CRS-01',
      'SimbolSatuan': 'pcs',
      'BolehDesimal': false,
      'Pelacakan': 'Tidak',
      'NomorBatch': null,
      'TanggalKedaluwarsa': null,
      'NomorSeri': null,
      'JumlahSistem': null,
      'JumlahFisik': '5.0000',
    },
  ],
};
