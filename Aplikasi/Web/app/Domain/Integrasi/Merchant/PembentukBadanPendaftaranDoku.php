<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Merchant;

use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;

/**
 * Membentuk badan JSON Business Registration (`POST /business`) DOKU Partner API untuk usaha perseorangan.
 *
 * Satu-satunya tempat bentuk badan ditentukan, sengaja dipisah supaya mudah disesuaikan.
 *
 * BELUM TERVERIFIKASI: bentuk persis perlu dicocokkan lewat Postman collection "DOKU Partner API"/UAT, terutama
 *  - `BentukRekening()`: dokumentasi ambigu untuk PERSONAL (field `account_name`, `account_number`, `bank_id`,
 *    `bank_name`, `country`, `currency`, `swift_code`; tabel PERSONAL menulis `business.bank_account` bertipe string).
 *    Tebakan terbaik: objek `business.bank_account` berisi field-field itu;
 *  - `addresses[].area` (kode wilayah) belum punya sumber data, dikirim sama dengan alamat;
 *  - nilai `website_readiness`/`mobile_app_readiness` (dikirim `false`), `transaction_frequency` (`UKE`) dan
 *    `transaction_quantity` (`UP_TO_100`) mengikuti contoh dokumentasi;
 *  - `bank_id` memakai kode bank referensi Payoung (`ReferensiBank.Kode`), padahal DOKU mungkin memakai pengenal sendiri.
 */
final class PembentukBadanPendaftaranDoku
{
    /**
     * @param  array{Ktp: string, Swafoto: string, BuktiUsaha: string}  $idFile  id berkas hasil Upload File
     * @param  array{Kode: string, Nama: string}  $bank
     * @return array<string, mixed>
     */
    public static function Bentuk(PendaftaranMerchantPembayaran $pendaftaran, array $idFile, string $urlCallback, array $bank): array
    {
        $nomorHp = self::AmbilNomorTanpaKodeNegara((string) $pendaftaran->NomorHp);
        $nama = (string) $pendaftaran->NamaUsaha;
        $alamat = (string) $pendaftaran->AlamatUsaha;

        return ['business' => [
            'type' => 'PERSONAL',
            'legal_entity' => 'PERSEORANGAN',
            'name' => $nama,
            'description' => "Usaha {$nama}",
            'email' => (string) $pendaftaran->Email,
            'phone_calling_code' => '62',
            'phone_number' => $nomorHp,
            'callback_url' => $urlCallback,
            'website_readiness' => false,
            'mobile_app_readiness' => false,
            'addresses' => [[
                'area' => $alamat,
                'country' => 'ID',
                'name' => $alamat,
                'primary' => true,
            ]],
            'contacts' => [[
                'name' => (string) $pendaftaran->NamaPemilik,
                'email' => (string) $pendaftaran->Email,
                'nationality' => 'ID',
                'phone_calling_code' => '62',
                'phone_number' => $nomorHp,
                'position' => 'OWNER',
                'primary' => true,
                'owner_liveness' => ['id' => $idFile['Swafoto']],
                'documents' => [[
                    'code' => 'KTP',
                    'category' => 'DOCUMENT',
                    'id' => $idFile['Ktp'],
                    'forms' => [['code' => 'NO_KTP', 'value' => (string) $pendaftaran->Nik]],
                ]],
            ]],
            'brands' => [[
                'name' => $nama,
                'description' => "Usaha {$nama}",
                'category' => $pendaftaran->KategoriUsaha ?? (string) config('merchant.KategoriUsahaBawaan'),
                'transaction_frequency' => 'UKE',
                'transaction_quantity' => 'UP_TO_100',
                'social_media' => [],
                'photo_proofs' => [['id' => $idFile['BuktiUsaha']]],
            ]],
            'bank_account' => self::BentukRekening($pendaftaran, $bank),
        ]];
    }

    /**
     * @param  array{Kode: string, Nama: string}  $bank
     * @return array<string, string>
     */
    public static function BentukRekening(PendaftaranMerchantPembayaran $pendaftaran, array $bank): array
    {
        return [
            'account_name' => (string) $pendaftaran->NamaPemilikRekening,
            'account_number' => (string) $pendaftaran->NomorRekening,
            'bank_id' => $bank['Kode'],
            'bank_name' => $bank['Nama'],
            'country' => 'ID',
            'currency' => 'IDR',
        ];
    }

    /** `628123456789` atau `08123456789` menjadi `8123456789` (kode negara dikirim terpisah). */
    public static function AmbilNomorTanpaKodeNegara(string $nomor): string
    {
        $angka = (string) preg_replace('/\D+/', '', $nomor);

        return match (true) {
            str_starts_with($angka, '62') => substr($angka, 2),
            str_starts_with($angka, '0') => substr($angka, 1),
            default => $angka,
        };
    }
}
