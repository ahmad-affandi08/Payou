<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Data;

use Illuminate\Http\UploadedFile;

/**
 * Isian pendaftaran merchant dari tenant. Foto boleh kosong saat memperbarui draf yang fotonya sudah tersimpan; NIK dan
 * nomor rekening kosong = pertahankan yang tersimpan (nilainya tidak pernah ditampilkan ulang).
 */
final readonly class DataPendaftaranMerchant
{
    public function __construct(
        public string $namaPemilik,
        public ?string $nik,
        public string $email,
        public string $nomorHp,
        public string $namaUsaha,
        public string $alamatUsaha,
        public int $idReferensiBank,
        public string $namaPemilikRekening,
        public ?string $nomorRekening,
        public ?UploadedFile $fotoKtp = null,
        public ?UploadedFile $fotoSwafoto = null,
        public ?UploadedFile $fotoBuktiUsaha = null,
    ) {}
}
