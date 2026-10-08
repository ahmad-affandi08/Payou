<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Integrasi\Enum\StatusPendaftaranMerchant;
use App\Domain\Integrasi\Merchant\PenyimpanBerkasKyc;
use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;

/**
 * Penyapu foto KYC sementara yang melewati `config('merchant.JamBerkasKedaluwarsa')` (24 jam): berkas dihapus dari
 * disk dan kolom path dikosongkan. Pendaftaran yang belum sempat terunggah semuanya dikembalikan ke `Draf` dengan
 * pesan jelas supaya tenant mengunggah ulang. Konteks tenant harus sudah diatur pemanggil.
 */
final class BersihkanBerkasPendaftaranMerchant
{
    public function __construct(private readonly PenyimpanBerkasKyc $berkas) {}

    /** @return int jumlah pendaftaran yang berkasnya dibersihkan */
    public function Jalankan(): int
    {
        $batas = now()->subHours((int) config('merchant.JamBerkasKedaluwarsa'));
        $jumlah = 0;

        $kedaluwarsa = PendaftaranMerchantPembayaran::query()
            ->where('BerkasDiunggahPada', '<', $batas)
            ->get();

        foreach ($kedaluwarsa as $pendaftaran) {
            foreach ([$pendaftaran->PathKtp, $pendaftaran->PathSwafoto, $pendaftaran->PathBuktiUsaha] as $path) {
                $this->berkas->Hapus($path);
            }

            $pendaftaran->PathKtp = null;
            $pendaftaran->PathSwafoto = null;
            $pendaftaran->PathBuktiUsaha = null;
            $pendaftaran->BerkasDiunggahPada = null;

            if (in_array($pendaftaran->Status, [StatusPendaftaranMerchant::Draf, StatusPendaftaranMerchant::Dikirim], true) && ! $pendaftaran->CekBerkasTerunggah() && ! $pendaftaran->CekSudahTerdaftar()) {
                $pendaftaran->Status = StatusPendaftaranMerchant::Draf;
                $pendaftaran->PesanGalat = 'Foto yang tersimpan sudah lebih dari 24 jam dan dihapus demi keamanan. Unggah fotonya lagi.';
            }

            $pendaftaran->save();
            $jumlah++;
        }

        return $jumlah;
    }
}
