<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Merchant;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Penyimpanan sementara foto KYC (KTP, swafoto, foto tempat usaha) sebelum diunggah ke DOKU. Berkas diperiksa dari
 * isinya (JPEG/PNG sungguhan, bukan hanya ekstensi), dienkripsi, dan disimpan di disk privat dengan nama acak per
 * tenant. Tidak pernah dilayani ke peramban dan tidak pernah ditulis ke log. Dihapus begitu terunggah ke DOKU, saat
 * gagal permanen, saat batal, atau oleh penyapu setelah `config('merchant.JamBerkasKedaluwarsa')`.
 */
final class PenyimpanBerkasKyc
{
    public function Simpan(int $idTenant, UploadedFile $berkas, string $label, string $kolom = 'Foto'): string
    {
        $isi = (string) file_get_contents($berkas->getRealPath());

        if ($isi === '' || $this->DeteksiJenis($isi) === null) {
            throw new PelanggaranAturanBisnis('FotoTidakValid', "{$label} harus berupa foto JPG atau PNG.", $kolom);
        }

        if (strlen($isi) > (int) config('merchant.UkuranMaksimalFotoKb') * 1024) {
            throw new PelanggaranAturanBisnis('FotoTerlaluBesar', "{$label} terlalu besar. Maksimal ".((int) config('merchant.UkuranMaksimalFotoKb') / 1024).' MB.', $kolom);
        }

        $path = "merchant/kyc/{$idTenant}/".Str::ulid().'.enc';

        if (! $this->AmbilDisk()->put($path, Crypt::encryptString($isi))) {
            throw new RuntimeException('Foto gagal disimpan sementara.');
        }

        return $path;
    }

    /**
     * @return array{Isi: string, Nama: string, Jenis: string}|null null bila berkas hilang atau rusak
     */
    public function Baca(string $path, string $namaDasar): ?array
    {
        $disk = $this->AmbilDisk();

        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $isi = Crypt::decryptString((string) $disk->get($path));
        } catch (DecryptException) {
            return null;
        }

        $jenis = $this->DeteksiJenis($isi);

        return $jenis === null ? null : ['Isi' => $isi, 'Nama' => $namaDasar.'.'.($jenis === 'image/png' ? 'png' : 'jpg'), 'Jenis' => $jenis];
    }

    public function Hapus(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->AmbilDisk()->delete($path);
        }
    }

    private function DeteksiJenis(string $isi): ?string
    {
        return match (true) {
            str_starts_with($isi, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($isi, "\x89PNG\r\n\x1A\n") => 'image/png',
            default => null,
        };
    }

    private function AmbilDisk(): FilesystemAdapter
    {
        $disk = Storage::disk((string) config('merchant.DiskBerkas'));

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Disk berkas merchant tidak dikenal.');
        }

        return $disk;
    }
}
