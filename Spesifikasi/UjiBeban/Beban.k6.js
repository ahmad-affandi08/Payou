// Uji beban API POS (audit PAY-P1-09). Dijalankan oleh workflow `UjiBeban` (k6), bukan bagian dari `npm run periksa`.
//
// Skenario (semuanya per perangkat/tenant sungguhan yang disiapkan `SiapkanDataBebanTes`):
//   checkout  - satu penjualan lunas per permintaan `POST /sinkron/kirim` pada laju tetap (jam sibuk kasir).
//   outbox    - perangkat yang baru online mengirim 20 penjualan offline sekaligus (backlog setelah koneksi pulih).
//   polling   - aplikasi menarik konfigurasi, katalog delta, dan data awal secara berkala.
//   webhook   - rentetan notifikasi billing bertanda tangan palsu (harus ditolak murah: 401, tanpa menyentuh data).
//
// Env: BASE_URL, BEBAN_DATA (berkas JSON), DURASI (bawaan 60s), TARIF_CHECKOUT (per detik), VU_POLLING, VU_OUTBOX,
//      TARIF_WEBHOOK.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.4/index.js';

const data = JSON.parse(open(__ENV.BEBAN_DATA || './data.json'));
const base = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const durasi = __ENV.DURASI || '60s';

const diterima = new Counter('penjualan_diterima');
const ditolak = new Counter('penjualan_ditolak');
const dibatasi = new Counter('permintaan_dibatasi_429');

export const options = {
  // p(99) ikut dicetak di ringkasan (bawaan k6 hanya sampai p(95)).
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
  scenarios: {
    checkout: {
      executor: 'constant-arrival-rate',
      rate: Number(__ENV.TARIF_CHECKOUT || 10),
      timeUnit: '1s',
      duration: durasi,
      preAllocatedVUs: 20,
      maxVUs: 100,
      exec: 'checkout',
    },
    outbox: {
      executor: 'constant-vus',
      vus: Number(__ENV.VU_OUTBOX || 2),
      duration: durasi,
      exec: 'outbox',
    },
    polling: {
      executor: 'constant-vus',
      vus: Number(__ENV.VU_POLLING || 10),
      duration: durasi,
      exec: 'polling',
    },
    webhook: {
      executor: 'constant-arrival-rate',
      rate: Number(__ENV.TARIF_WEBHOOK || 5),
      timeUnit: '1s',
      duration: durasi,
      preAllocatedVUs: 5,
      maxVUs: 20,
      exec: 'webhook',
    },
  },
  thresholds: {
    'http_req_duration{scenario:checkout}': ['p(95)<1500'],
    'http_req_duration{scenario:outbox}': ['p(95)<5000'],
    'http_req_duration{scenario:polling}': ['p(95)<1000'],
    'http_req_duration{scenario:webhook}': ['p(95)<1000'],
    'http_req_failed{scenario:checkout}': ['rate<0.01'],
    penjualan_ditolak: ['count==0'],
    checks: ['rate>0.99'],
  },
};

const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

// ULID 26 karakter (48 bit waktu + 80 bit acak), huruf besar, seperti `Str::ulid()` di server.
function ulid() {
  let waktu = Date.now();
  let depan = '';
  for (let i = 0; i < 10; i++) {
    depan = CROCKFORD[waktu % 32] + depan;
    waktu = Math.floor(waktu / 32);
  }
  let belakang = '';
  for (let i = 0; i < 16; i++) {
    belakang += CROCKFORD[Math.floor(Math.random() * 32)];
  }
  return depan + belakang;
}

function rupiah(angka) {
  return `${angka}.00`;
}

function offsetJam(zona) {
  return zona === 'Asia/Jayapura' ? 9 : zona === 'Asia/Makassar' ? 8 : 7;
}

function nomorPenjualan(p, urutan) {
  const dibuat = Date.now() - 5 * 60000;
  const lokal = new Date(dibuat + offsetJam(p.ZonaWaktu) * 3600000).toISOString();
  const ymd = lokal.slice(2, 4) + lokal.slice(5, 7) + lokal.slice(8, 10);
  return `INV/${p.KodeOutlet}/${ymd}/${p.KodePerangkat}-${urutan}`;
}

function pad(n, panjang) {
  return String(n).padStart(panjang, '0');
}

// Satu item `Penjualan.Buat` tunai, satu baris, tanpa pajak (cukup untuk menekan jalur stok + jurnal + idempotensi).
function itemPenjualan(p, urutan) {
  const produk = p.Produk[Math.floor(Math.random() * p.Produk.length)];
  const jumlah = 1 + Math.floor(Math.random() * 3);
  const subtotal = Math.round(Number(produk.Harga)) * jumlah;

  return {
    Jenis: 'Penjualan.Buat',
    Uuid: ulid(),
    Data: {
      UuidShift: p.UuidShift,
      UuidPengguna: p.UuidKasir,
      Nomor: nomorPenjualan(p, urutan),
      Kanal: 'BawaPulang',
      DibuatPada: new Date(Date.now() - 5 * 60000).toISOString().replace(/\.\d{3}Z$/, 'Z'),
      HargaTermasukPajak: false,
      PersenBiayaLayanan: '0',
      PembulatanTunai: null,
      Pajak: [],
      Baris: [
        {
          Uuid: ulid(),
          UuidProduk: produk.Uuid,
          UuidProdukSatuan: null,
          Jumlah: String(jumlah),
          HargaSatuan: produk.Harga,
          HargaPilihan: '0.00',
          Pilihan: [],
          HargaTermasukPajak: null,
          KodePajak: null,
          DiskonManual: null,
          Catatan: null,
        },
      ],
      DiskonManualPesanan: null,
      UuidPenyetujuDiskon: null,
      Pembayaran: [{ Uuid: ulid(), UuidMetodePembayaran: p.UuidMetodeTunai, Jumlah: rupiah(subtotal), Referensi: null }],
      Ringkasan: {
        Subtotal: rupiah(subtotal),
        TotalPajak: '0.00',
        Pembulatan: '0.00',
        TotalAkhir: rupiah(subtotal),
        Kembalian: '0.00',
      },
      Catatan: 'Uji beban',
    },
  };
}

function kepala(p) {
  return {
    Authorization: `Bearer ${p.Token}`,
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'Idempotency-Key': ulid(),
  };
}

function kirim(p, item, tag) {
  const res = http.post(`${base}/api/pos/v1/sinkron/kirim`, JSON.stringify({ Item: item }), {
    headers: kepala(p),
    tags: { jenis: tag },
    responseCallback: http.expectedStatuses({ min: 200, max: 299 }, 429),
  });

  if (res.status === 429) {
    dibatasi.add(1);
    return res;
  }

  check(res, { 'sinkron 200': (r) => r.status === 200 });

  if (res.status !== 200) {
    console.error(`sinkron ${tag} -> ${res.status} ${String(res.body).slice(0, 300)}`);
  }

  if (res.status === 200) {
    for (const h of res.json('Hasil') || []) {
      if (h.Status === 'Diterima' || h.Status === 'Duplikat') {
        diterima.add(1);
      } else {
        ditolak.add(1, { kode: h.Galat ? h.Galat.Kode : h.Status });
      }
    }
  }

  return res;
}

export function checkout() {
  const p = data.Perangkat[(__VU + __ITER) % data.Perangkat.length];
  kirim(p, [itemPenjualan(p, `1${pad(__VU, 3)}${pad(__ITER, 6)}`)], 'checkout');
}

export function outbox() {
  const p = data.Perangkat[__VU % data.Perangkat.length];
  const item = [];
  for (let j = 0; j < 20; j++) {
    item.push(itemPenjualan(p, `2${pad(__VU, 3)}${pad(__ITER, 4)}${pad(j, 2)}`));
  }
  kirim(p, item, 'outbox');
  sleep(5);
}

// Kursor katalog per VU (perangkat): sinkron lengkap sekali, lalu delta seperti aplikasi sungguhan.
let kursorKatalog = null;

export function polling() {
  const p = data.Perangkat[__VU % data.Perangkat.length];
  const params = { headers: kepala(p), tags: { jenis: 'polling' }, responseCallback: http.expectedStatuses({ min: 200, max: 299 }, 429) };
  const jalurKatalog = kursorKatalog === null ? '/api/pos/v1/katalog' : `/api/pos/v1/katalog?sejak=${encodeURIComponent(kursorKatalog)}`;

  for (const jalur of ['/api/pos/v1/konfigurasi-aplikasi', jalurKatalog, '/api/pos/v1/data-awal']) {
    const res = http.get(`${base}${jalur}`, params);
    if (res.status === 429) {
      dibatasi.add(1);
    } else {
      check(res, { 'polling 200': (r) => r.status === 200 });

      if (res.status !== 200) {
        console.error(`polling ${jalur.split('?')[0]} -> ${res.status} ${String(res.body).slice(0, 300)}`);
      } else if (jalur.startsWith('/api/pos/v1/katalog')) {
        kursorKatalog = res.json('Kursor') || kursorKatalog;
      }
    }
  }
  sleep(10);
}

export function webhook() {
  // Notifikasi DOKU palsu: tanpa header Client-Id/Signature yang sah, endpoint billing harus menjawab 401.
  const isi = {
    order: { invoice_number: `1-${ulid()}`, amount: 100000 },
    transaction: { status: 'SUCCESS' },
  };
  const res = http.post(`${base}/webhook/billing/doku`, JSON.stringify(isi), {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'Client-Id': 'BRN-uji-beban',
      'Request-Id': ulid(),
      'Request-Timestamp': '2026-01-01T00:00:00Z',
      Signature: 'HMACSHA256=tanda-tangan-palsu',
    },
    tags: { jenis: 'webhook' },
    responseCallback: http.expectedStatuses(401, 429),
  });
  check(res, { 'webhook palsu ditolak': (r) => r.status === 401 || r.status === 429 });
}

export function handleSummary(ringkasan) {
  return {
    'ringkasan-beban.json': JSON.stringify(ringkasan, null, 2),
    stdout: textSummary(ringkasan, { indent: ' ', enableColors: false }),
  };
}
