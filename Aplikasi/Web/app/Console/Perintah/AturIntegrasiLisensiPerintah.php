<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Domain\Pengelola\Integrasi\Aksi\SimpanKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Aksi\UbahStatusIntegrasi;
use App\Domain\Pengelola\Integrasi\Aksi\UjiKoneksiIntegrasi;
use App\Domain\Pengelola\Integrasi\Data\DataKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use Illuminate\Console\Command;

/**
 * D-35 edisi Lisensi: mengatur penyedia email, WhatsApp, penyimpanan berkas, atau Masuk dengan Google di server pembeli tanpa konsol.
 * Memakai Aksi P-05 yang sama dengan konsol (kredensial terenkripsi, petunjuk 4 karakter, uji koneksi wajib berhasil
 * sebelum aktif, BR-P05.1/BR-P05.4). Kredensial diminta tersembunyi dan tidak pernah dicetak.
 */
final class AturIntegrasiLisensiPerintah extends Command
{
    /** Jenis yang dipakai dashboard toko; CAPTCHA, push, dan billing milik Payoung SaaS; Masuk dengan Google ikut (D-57). */
    private const JENIS = ['Email' => JenisIntegrasi::Email, 'Whatsapp' => JenisIntegrasi::Whatsapp, 'Penyimpanan' => JenisIntegrasi::Penyimpanan, 'LoginSosial' => JenisIntegrasi::LoginSosial];

    protected $signature = 'lisensi:atur-integrasi {jenis? : Email, Whatsapp, Penyimpanan, atau LoginSosial}';

    protected $description = 'Mengatur penyedia email/WhatsApp/penyimpanan di edisi Lisensi lalu menguji & mengaktifkannya (D-35).';

    public function handle(SimpanKonfigurasiIntegrasi $simpan, UjiKoneksiIntegrasi $uji, UbahStatusIntegrasi $ubahStatus): int
    {
        if (! EdisiAplikasi::CekLisensi()) {
            $this->error('Perintah ini hanya untuk edisi Lisensi. Di edisi SaaS atur integrasi lewat konsol.');

            return self::FAILURE;
        }

        $namaJenis = $this->argument('jenis');
        $namaJenis = is_string($namaJenis) && isset(self::JENIS[$namaJenis])
            ? $namaJenis
            : $this->Pilih('Jenis integrasi', array_keys(self::JENIS), null);
        $jenis = self::JENIS[$namaJenis];

        $daftarPenyedia = array_values(array_filter(PenyediaIntegrasi::cases(), fn (PenyediaIntegrasi $p): bool => $p->AmbilJenis() === $jenis));
        $label = array_map(fn (PenyediaIntegrasi $p): string => $p->value, $daftarPenyedia);
        $penyedia = PenyediaIntegrasi::from($this->Pilih('Penyedia', $label, null));

        $pengaturan = [];

        foreach ($penyedia->AmbilBidangPengaturan() as $bidang) {
            $bawaan = isset($bidang['Bawaan']) ? (string) $bidang['Bawaan'] : null;
            $nilai = isset($bidang['Opsi'])
                ? $this->Pilih($bidang['Label'], $bidang['Opsi'], $bawaan)
                : trim((string) $this->ask($bidang['Label'].($bidang['Wajib'] ? '' : ' (boleh kosong)'), $bawaan));

            if ($nilai === '') {
                if ($bidang['Wajib']) {
                    $this->error("{$bidang['Label']} wajib diisi.");

                    return self::FAILURE;
                }

                continue;
            }

            $pengaturan[$bidang['Kunci']] = $bidang['Jenis'] === 'Angka' && ctype_digit($nilai) ? (int) $nilai : $nilai;
        }

        $kredensial = [];

        foreach ($penyedia->AmbilBidangKredensial() as $bidang) {
            $kredensial[$bidang['Kunci']] = trim((string) $this->secret($bidang['Label'].($bidang['Wajib'] ? '' : ' (boleh kosong)')));
        }

        try {
            $konfigurasi = $simpan->Jalankan(null, new DataKonfigurasiIntegrasi(
                jenis: $jenis,
                lingkungan: LingkunganIntegrasi::AmbilSaatIni(),
                pengaturan: $pengaturan,
                kredensial: array_filter($kredensial, fn (string $nilai): bool => $nilai !== ''),
                // Pengingat rotasi kredensial 90 hari, sama dengan bawaan konsol.
                rotasiSetiapHari: 90,
                alasan: 'Diatur pemasang edisi Lisensi lewat lisensi:atur-integrasi',
                penyedia: $penyedia,
            ));
        } catch (PelanggaranAturanBisnis $galat) {
            $this->error($galat->getMessage());

            return self::FAILURE;
        }

        $hasil = $uji->Jalankan($konfigurasi)['Hasil'];

        if (! $hasil->berhasil) {
            $this->error("Uji koneksi gagal: {$hasil->pesan}");
            $this->line('Konfigurasi tersimpan tetapi belum aktif. Perbaiki isian lalu jalankan perintah ini lagi.');

            return self::FAILURE;
        }

        if (! $konfigurasi->refresh()->Aktif) {
            $ubahStatus->Jalankan(null, $konfigurasi, true, 'Uji koneksi berhasil saat lisensi:atur-integrasi');
        }

        $this->info("{$namaJenis} lewat {$penyedia->AmbilLabel()} tersambung dan aktif. {$hasil->pesan}");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $opsi
     */
    private function Pilih(string $tanya, array $opsi, ?string $bawaan): string
    {
        $jawaban = $this->choice($tanya, $opsi, $bawaan ?? $opsi[0]);

        return is_string($jawaban) ? $jawaban : $opsi[0];
    }
}
