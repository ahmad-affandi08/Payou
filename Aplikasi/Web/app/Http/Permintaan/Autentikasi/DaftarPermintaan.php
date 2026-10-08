<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Layanan\PemeriksaAlamatEmail;
use App\Domain\Tenant\Data\DataPendaftaran;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class DaftarPermintaan extends FormRequest
{
    /** Nomor WhatsApp Indonesia: 08… atau +628…, 10–14 digit. */
    public const POLA_NO_HP = '/^(\+62|62|0)8\d{8,12}$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:150'],
            // BR-00.5: tolak email asal-asalan (typo penyedia, domain contoh/sekali pakai, domain tanpa server surat).
            'Email' => ['required', 'string', 'email:rfc', 'max:191', static function (string $atribut, mixed $nilai, Closure $gagal): void {
                $alasan = app(PemeriksaAlamatEmail::class)->AmbilAlasanTolak(mb_strtolower(trim((string) $nilai)));

                if ($alasan !== null) {
                    $gagal($alasan);
                }
            }],
            'NoHp' => ['required', 'string', 'regex:'.self::POLA_NO_HP],
            'KataSandi' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:100'],
            'KonfirmasiKataSandi' => ['required', 'same:KataSandi'],
            'NamaUsaha' => ['required', 'string', 'min:3', 'max:150'],
            'Paket' => ['nullable', 'string', 'max:30'],
            'Setuju' => ['accepted'],
            'TokenCaptcha' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'NoHp.regex' => 'Nomor WhatsApp diawali 08 atau +628, misal 081234567890.',
            'KonfirmasiKataSandi.same' => 'Konfirmasi kata sandi tidak sama.',
            'Setuju.accepted' => 'Centang persetujuan Syarat & Ketentuan dan Kebijakan Privasi.',
        ];
    }

    public function AmbilData(): DataPendaftaran
    {
        return new DataPendaftaran(
            pemilik: new DataPemilikBaru(
                nama: trim($this->string('Nama')->toString()),
                email: mb_strtolower(trim($this->string('Email')->toString())),
                noHp: self::NormalkanNoHp($this->string('NoHp')->toString()),
                kataSandi: $this->string('KataSandi')->toString(),
            ),
            namaUsaha: trim($this->string('NamaUsaha')->toString()),
            kodePaket: $this->filled('Paket') ? mb_strtoupper($this->string('Paket')->toString()) : null,
            ip: $this->ip(),
        );
    }

    /** Disimpan dalam bentuk 08… agar keunikan BR-00.1 tidak lolos lewat format berbeda. */
    public static function NormalkanNoHp(string $noHp): string
    {
        $angka = preg_replace('/\D/', '', $noHp) ?? '';

        return str_starts_with($angka, '62') ? '0'.substr($angka, 2) : $angka;
    }
}
