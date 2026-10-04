<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Data\DataAbsensiWeb;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Enum\StatusWajahKaryawan;
use App\Domain\Karyawan\Layanan\PencocokWajah;
use App\Domain\Karyawan\Layanan\PenegakJadwalAbsen;
use App\Domain\Karyawan\Layanan\PengukurJarak;
use App\Domain\Karyawan\Layanan\PenyimpanSwafoto;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\WajahKaryawan;
use App\Domain\Organisasi\Kueri\LokasiAbsensiOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F-18 bagian 4 (D-37): absen masuk/keluar dari HP pribadi lewat tautan absen. Semua syarat dinilai di server dan
 * yang tidak lolos **ditolak** (tidak ada baris tercatat):
 *
 * 1. Karyawan aktif dengan wajah **Disetujui** pengelola.
 * 2. Lokasi: akurasi GPS tidak lebih buruk dari max(radius outlet, `BatasAkurasiMinimalMeter`), dan jarak ke outlet
 *    ≤ radius. Masuk: outlet terdekat yang radiusnya memuat posisi karyawan, di antara outlet utama karyawan & outlet
 *    jadwalnya pada tanggal bisnis hari ini atau kemarin (shift malam lewat tengah malam); tanpa outlet utama = semua
 *    outlet. Keluar: outlet absensi masuknya.
 * 3. Outlet yang mewajibkan QR: kode 6 digit dari layar QR outlet (berganti tiap 30 detik) harus berlaku; bukti bahwa
 *    karyawan melihat layar di outlet, bukan hanya mengirim koordinat.
 * 4. Jadwal (D-44): bila aturan tenant `WajibJadwal` aktif, masuk hanya sah dalam jendela jadwal di outlet yang sama
 *    (`PenegakJadwalAbsen`); ditolak dengan `TidakAdaJadwal`/`TerlaluAwal`/`ShiftSudahBerakhir`/`JadwalDiOutletLain`.
 * 5. Wajah: kemiripan sidik wajah saat ini dengan sidik terdaftar ≥ `AmbangKemiripanWajah`. Swafoto disimpan sebagai
 *    bukti. Wajah tidak cocok dicatat di log audit (tanpa sidiknya) sebagai jejak percobaan.
 *
 * Idempoten per Uuid: masuk dengan Uuid yang sudah tercatat atau keluar untuk absensi yang sudah ditutup mengembalikan
 * absensi itu tanpa mengubah apa pun.
 */
final class CatatAbsensiWeb
{
    public function __construct(
        private readonly LokasiAbsensiOutlet $lokasi,
        private readonly PengukurJarak $pengukur,
        private readonly PencocokWajah $pencocok,
        private readonly PenyimpanSwafoto $swafoto,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PencatatAudit $audit,
        private readonly PenegakJadwalAbsen $jadwal,
    ) {}

    public function Masuk(Karyawan $karyawan, DataAbsensiWeb $data): Absensi
    {
        $lama = Absensi::query()->where('Uuid', $data->uuid)->first();

        if ($lama !== null) {
            return $lama->IdKaryawan === $karyawan->Id
                ? $lama
                : throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Muat ulang halaman lalu absen lagi.', 'Uuid');
        }

        $this->PastikanBolehAbsen($karyawan);
        $this->PastikanBelumMasuk($karyawan);

        $sekarang = CarbonImmutable::now();
        [$outlet, $jarak] = $this->PilihOutlet($karyawan, $data, $this->AmbilIdOutletBoleh($karyawan, $sekarang));
        // D-44: jadwal diperiksa sebelum wajah/swafoto diproses, supaya absen yang pasti ditolak tidak membuang kerja.
        $langgar = $this->jadwal->Periksa($karyawan->Id, $outlet['Id'], $sekarang);

        if ($langgar !== null) {
            $this->audit->Catat('absensi.web.ditolak-jadwal', $karyawan, null, ['Kode' => $langgar['Kode'], 'IdOutlet' => $outlet['Id']], $karyawan->IdTenant);

            throw new PelanggaranAturanBisnis($langgar['Kode'], $langgar['Pesan'], 'Jadwal');
        }

        $qr = $this->PeriksaQr($karyawan, $outlet, $data, $sekarang);
        $kemiripan = $this->CocokkanWajah($karyawan, $data);
        $path = $this->swafoto->Simpan($karyawan->IdTenant, $data->swafoto);

        try {
            return DB::transaction(function () use ($karyawan, $data, $outlet, $jarak, $kemiripan, $path, $sekarang, $qr): Absensi {
                // Dua kiriman bersamaan (dua tab, ketukan ganda dengan Uuid berbeda) diserialkan lewat kunci baris
                // karyawan, lalu syarat "belum masuk" diperiksa ulang di dalam kunci.
                Karyawan::query()->whereKey($karyawan->Id)->lockForUpdate()->first();
                $lama = Absensi::query()->where('Uuid', $data->uuid)->first();

                if ($lama !== null && $lama->IdKaryawan === $karyawan->Id) {
                    $this->swafoto->Hapus($path);

                    return $lama;
                }

                $this->PastikanBelumMasuk($karyawan);

                return Absensi::query()->create([
                    'Uuid' => $data->uuid,
                    'IdKaryawan' => $karyawan->Id,
                    'IdOutlet' => $outlet['Id'],
                    'IdPerangkat' => null,
                    'TanggalBisnis' => $this->tanggalBisnis->Hitung($outlet['Id'], $sekarang)->toDateString(),
                    'MasukPada' => $sekarang->utc(),
                    'PathSwafotoMasuk' => $path,
                    'Sumber' => Absensi::SUMBER_WEB,
                    'LintangMasuk' => $data->lintang,
                    'BujurMasuk' => $data->bujur,
                    'AkurasiMasukMeter' => $data->akurasiMeter,
                    'JarakMasukMeter' => $jarak,
                    'KemiripanWajahMasuk' => (string) $kemiripan,
                    'QrMasukTerverifikasi' => $qr,
                ]);
            }, 3);
        } catch (Throwable $galat) {
            $this->swafoto->Hapus($path);

            throw $galat;
        }
    }

    public function Keluar(Karyawan $karyawan, DataAbsensiWeb $data): Absensi
    {
        $absensi = Absensi::query()->where('Uuid', $data->uuid)->where('IdKaryawan', $karyawan->Id)->first();

        if ($absensi === null) {
            throw new PelanggaranAturanBisnis('BelumAbsenMasuk', 'Absensi masuk tidak ditemukan. Muat ulang halaman.', 'Absensi');
        }

        if ($absensi->KeluarPada !== null) {
            return $absensi;
        }

        $this->PastikanBolehAbsen($karyawan);
        [$outlet, $jarak] = $this->PilihOutlet($karyawan, $data, [$absensi->IdOutlet], true);
        $qr = $this->PeriksaQr($karyawan, $outlet, $data, CarbonImmutable::now());
        $kemiripan = $this->CocokkanWajah($karyawan, $data);
        $path = $this->swafoto->Simpan($karyawan->IdTenant, $data->swafoto);

        try {
            return DB::transaction(function () use ($absensi, $data, $jarak, $kemiripan, $path, $qr): Absensi {
                $terkini = Absensi::query()->lockForUpdate()->findOrFail($absensi->Id);

                if ($terkini->KeluarPada !== null) {
                    $this->swafoto->Hapus($path);

                    return $terkini;
                }

                $terkini->forceFill([
                    'KeluarPada' => now()->utc(),
                    'PathSwafotoKeluar' => $path,
                    'LintangKeluar' => $data->lintang,
                    'BujurKeluar' => $data->bujur,
                    'AkurasiKeluarMeter' => $data->akurasiMeter,
                    'JarakKeluarMeter' => $jarak,
                    'KemiripanWajahKeluar' => (string) $kemiripan,
                    'QrKeluarTerverifikasi' => $qr,
                ])->save();

                return $terkini;
            }, 3);
        } catch (Throwable $galat) {
            $this->swafoto->Hapus($path);

            throw $galat;
        }
    }

    private function PastikanBolehAbsen(Karyawan $karyawan): void
    {
        if ($karyawan->Status !== StatusKaryawan::Aktif) {
            throw new PelanggaranAturanBisnis('KaryawanNonaktif', "{$karyawan->Nama} sudah nonaktif sebagai karyawan.", 'Karyawan');
        }
    }

    private function PastikanBelumMasuk(Karyawan $karyawan): void
    {
        if (Absensi::query()->where('IdKaryawan', $karyawan->Id)->whereNull('KeluarPada')->exists()) {
            throw new PelanggaranAturanBisnis('SudahAbsenMasuk', 'Anda sudah absen masuk. Absen keluar dulu sebelum masuk lagi.', 'Absensi');
        }
    }

    /**
     * Outlet utama karyawan + outlet jadwalnya pada tanggal bisnis hari ini dan kemarin (tanggal bisnis outlet utama,
     * bukan tanggal UTC; kemarin untuk shift malam yang melewati tengah malam). Tanpa outlet utama = semua outlet.
     *
     * @return list<int>|null
     */
    private function AmbilIdOutletBoleh(Karyawan $karyawan, CarbonImmutable $sekarang): ?array
    {
        if ($karyawan->IdOutlet === null) {
            return null;
        }

        $hariIni = $this->tanggalBisnis->Hitung($karyawan->IdOutlet, $sekarang);
        $jadwal = JadwalKerja::query()
            ->where('IdKaryawan', $karyawan->Id)
            ->whereBetween('Tanggal', [$hariIni->subDay()->toDateString(), $hariIni->toDateString()])
            ->pluck('IdOutlet')
            ->all();

        return array_values(array_unique([$karyawan->IdOutlet, ...array_map('intval', $jadwal)]));
    }

    /**
     * Pilih outlet terdekat yang radiusnya memuat posisi karyawan (outlet bisa berdekatan dengan radius berbeda, jadi
     * outlet terdekat belum tentu yang memuatnya). Bila tidak ada, tolak dengan jarak ke outlet terdekat.
     *
     * @param  list<int>|null  $idOutlet
     * @return array{0: array{Id: int, Nama: string, Lintang: string, Bujur: string, RadiusMeter: int, WajibQr: bool}, 1: int}
     */
    private function PilihOutlet(Karyawan $karyawan, DataAbsensiWeb $data, ?array $idOutlet, bool $keluar = false): array
    {
        $daftar = $this->lokasi->Ambil($idOutlet);

        if ($daftar === []) {
            throw new PelanggaranAturanBisnis('OutletTanpaLokasi', $keluar
                ? 'Lokasi outlet tempat Anda absen masuk sudah dihapus. Minta pengelola mencatat jam keluar Anda di koreksi absensi.'
                : 'Lokasi outlet Anda belum diatur. Minta pengelola mengisi titik lokasi outlet.', 'Lokasi');
        }

        $terdekat = null;
        $jarakTerdekat = PHP_INT_MAX;
        $terpilih = null;
        $jarakTerpilih = PHP_INT_MAX;

        foreach ($daftar as $outlet) {
            $jarak = $this->pengukur->HitungMeter($outlet['Lintang'], $outlet['Bujur'], $data->lintang, $data->bujur);

            if ($jarak < $jarakTerdekat) {
                [$terdekat, $jarakTerdekat] = [$outlet, $jarak];
            }

            if ($jarak <= $outlet['RadiusMeter'] && $jarak < $jarakTerpilih) {
                [$terpilih, $jarakTerpilih] = [$outlet, $jarak];
            }
        }

        $acuan = $terpilih ?? $terdekat;

        if ($acuan === null) {
            throw new PelanggaranAturanBisnis('OutletTanpaLokasi', 'Lokasi outlet Anda belum diatur. Minta pengelola mengisi titik lokasi outlet.', 'Lokasi');
        }

        $batasAkurasi = max($acuan['RadiusMeter'], (int) config('karyawan.BatasAkurasiMinimalMeter'));

        if ($data->akurasiMeter > $batasAkurasi) {
            throw new PelanggaranAturanBisnis('AkurasiLokasiRendah', "Sinyal lokasi lemah (±{$data->akurasiMeter} m). Nyalakan GPS, dekati pintu atau jendela, lalu coba lagi.", 'Lokasi');
        }

        if ($terpilih === null) {
            $this->audit->Catat('absensi.web.di-luar-radius', $karyawan, null, ['Outlet' => $acuan['Nama'], 'JarakMeter' => $jarakTerdekat, 'RadiusMeter' => $acuan['RadiusMeter']]);

            throw new PelanggaranAturanBisnis('DiLuarRadius', "Anda berada ±{$jarakTerdekat} m dari {$acuan['Nama']}. Absen hanya bisa dalam radius {$acuan['RadiusMeter']} m dari outlet.", 'Lokasi');
        }

        return [$terpilih, $jarakTerpilih];
    }

    /**
     * Outlet yang mewajibkan QR: kode layar harus berlaku (true dicatat). Outlet lain: kode tidak diminta (null).
     *
     * @param  array{Id: int, Nama: string, WajibQr: bool}  $outlet
     */
    private function PeriksaQr(Karyawan $karyawan, array $outlet, DataAbsensiWeb $data, CarbonImmutable $sekarang): ?bool
    {
        if (! $outlet['WajibQr']) {
            return null;
        }

        if ($data->kodeQr === null) {
            throw new PelanggaranAturanBisnis('KodeQrWajib', "Absen di {$outlet['Nama']} wajib memindai QR di layar outlet. Pindai QR atau ketik 6 angkanya.", 'KodeQr');
        }

        if (! $this->lokasi->CekKodeQr($outlet['Id'], $data->kodeQr, $sekarang)) {
            $this->audit->Catat('absensi.web.qr-salah', $karyawan, null, ['Outlet' => $outlet['Nama']]);

            throw new PelanggaranAturanBisnis('KodeQrSalah', 'Kode QR salah atau sudah berganti. Pindai lagi QR yang sedang tampil di layar outlet.', 'KodeQr');
        }

        return true;
    }

    private function CocokkanWajah(Karyawan $karyawan, DataAbsensiWeb $data): BigDecimal
    {
        $wajah = WajahKaryawan::query()->where('IdKaryawan', $karyawan->Id)->where('Status', StatusWajahKaryawan::Disetujui->value)->first();

        if ($wajah === null) {
            throw new PelanggaranAturanBisnis('WajahBelumDisetujui', 'Wajah Anda belum terdaftar atau belum disetujui pengelola.', 'Wajah');
        }

        $kemiripan = $this->pencocok->HitungKemiripan($this->pencocok->Validasi($data->sidikWajah), $wajah->SidikWajah);

        if (! $this->pencocok->CekCocok($kemiripan)) {
            $this->audit->Catat('absensi.web.wajah-tidak-cocok', $karyawan, null, ['Kemiripan' => (string) $kemiripan]);

            throw new PelanggaranAturanBisnis('WajahTidakCocok', 'Wajah tidak cocok dengan wajah terdaftar. Hadapkan wajah lurus ke kamera di tempat terang, lalu coba lagi.', 'Wajah');
        }

        return $kemiripan;
    }
}
