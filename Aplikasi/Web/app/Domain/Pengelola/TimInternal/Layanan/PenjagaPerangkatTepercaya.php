<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Layanan;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PerangkatTepercayaPengelola;
use Illuminate\Support\Str;

/**
 * Perangkat tepercaya konsol (P-01 BR-P01.2, D-42). Nilai cookie = "{Uuid}.{token}"; hanya hash token yang disimpan,
 * sehingga isi basis data saja tidak cukup untuk melewati 2FA. Kepercayaan terikat ke satu akun: cookie milik
 * anggota lain di browser yang sama tidak berlaku.
 */
final class PenjagaPerangkatTepercaya
{
    /** Batas perangkat aktif per akun; perangkat tertua dicabut saat batas terlampaui. */
    public const MAKS_PERANGKAT_AKTIF = 10;

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * Mencatat browser ini sebagai tepercaya dan mengembalikan nilai cookie-nya.
     */
    public function Terbitkan(PenggunaPengelola $pengguna, ?string $agenPengguna, ?string $alamatIp): string
    {
        $token = Str::random(64);
        $perangkat = PerangkatTepercayaPengelola::query()->create([
            'IdPenggunaPengelola' => $pengguna->Id,
            'HashToken' => PerangkatTepercayaPengelola::BuatHashToken($token),
            'Keterangan' => self::RingkasPeramban($agenPengguna),
            'AlamatIp' => $alamatIp,
            'TerakhirDipakaiPada' => now(),
            'BerlakuSampai' => now()->addDays(self::HariBerlaku()),
        ]);

        $kelebihan = PerangkatTepercayaPengelola::query()
            ->where('IdPenggunaPengelola', $pengguna->Id)
            ->whereNull('DicabutPada')
            ->orderByDesc('Id')
            ->skip(self::MAKS_PERANGKAT_AKTIF)
            ->take(PHP_INT_MAX)
            ->pluck('Id');

        if ($kelebihan->isNotEmpty()) {
            PerangkatTepercayaPengelola::query()->whereIn('Id', $kelebihan)->update(['DicabutPada' => now()]);
        }

        $this->audit->Catat(
            'keamanan.perangkat-tepercaya.tambah',
            $perangkat,
            nilaiBaru: ['Keterangan' => $perangkat->Keterangan, 'BerlakuSampai' => $perangkat->BerlakuSampai->toIso8601String()],
            idPelaku: $pengguna->Id,
        );

        return $perangkat->Uuid.'.'.$token;
    }

    /**
     * Perangkat milik pengguna ini yang cocok dengan nilai cookie dan masih berlaku, atau null.
     * Bila cocok, waktu & alamat pemakaian terakhir diperbarui.
     */
    public function Cocokkan(PenggunaPengelola $pengguna, ?string $nilaiCookie, ?string $alamatIp): ?PerangkatTepercayaPengelola
    {
        $perangkat = self::Cari($nilaiCookie);

        if ($perangkat === null || $perangkat->IdPenggunaPengelola !== $pengguna->Id || ! $perangkat->CekMasihBerlaku()) {
            return null;
        }

        $perangkat->forceFill(['TerakhirDipakaiPada' => now(), 'AlamatIp' => $alamatIp])->save();

        return $perangkat;
    }

    /**
     * Uuid perangkat dari nilai cookie (untuk menandai "perangkat ini" di daftar), tanpa memeriksa token.
     */
    public static function AmbilUuid(?string $nilaiCookie): ?string
    {
        if ($nilaiCookie === null || ! str_contains($nilaiCookie, '.')) {
            return null;
        }

        return explode('.', $nilaiCookie, 2)[0];
    }

    public function Cabut(PerangkatTepercayaPengelola $perangkat, int $idPelaku): void
    {
        if ($perangkat->DicabutPada !== null) {
            return;
        }

        $perangkat->forceFill(['DicabutPada' => now()])->save();
        $this->audit->Catat(
            'keamanan.perangkat-tepercaya.cabut',
            $perangkat,
            nilaiLama: ['Keterangan' => $perangkat->Keterangan],
            idPelaku: $idPelaku,
        );
    }

    /**
     * Mencabut semua perangkat tepercaya akun ini (ganti kata sandi, 2FA diaktifkan ulang, nonaktif, atau manual).
     */
    public function CabutSemua(PenggunaPengelola $pengguna, string $alasan, ?int $idPelaku = null): int
    {
        $jumlah = PerangkatTepercayaPengelola::query()
            ->where('IdPenggunaPengelola', $pengguna->Id)
            ->whereNull('DicabutPada')
            ->update(['DicabutPada' => now()]);

        if ($jumlah > 0) {
            $this->audit->Catat(
                'keamanan.perangkat-tepercaya.cabut-semua',
                $pengguna,
                nilaiBaru: ['Jumlah' => $jumlah],
                alasan: $alasan,
                idPelaku: $idPelaku ?? $pengguna->Id,
            );
        }

        return $jumlah;
    }

    public static function HariBerlaku(): int
    {
        return (int) config('pengelola.HariPerangkatTepercaya');
    }

    /** "Chrome di Windows", "Safari di iPhone", dst. Cukup untuk dikenali pemilik akun, bukan sidik jari peramban. */
    public static function RingkasPeramban(?string $agenPengguna): string
    {
        $agen = (string) $agenPengguna;
        $peramban = match (true) {
            str_contains($agen, 'Edg/') => 'Edge',
            str_contains($agen, 'OPR/') || str_contains($agen, 'Opera') => 'Opera',
            str_contains($agen, 'Firefox/') => 'Firefox',
            str_contains($agen, 'Chrome/') || str_contains($agen, 'CriOS/') => 'Chrome',
            str_contains($agen, 'Safari/') => 'Safari',
            default => 'Peramban',
        };
        $sistem = match (true) {
            str_contains($agen, 'iPhone') => 'iPhone',
            str_contains($agen, 'iPad') => 'iPad',
            str_contains($agen, 'Android') => 'Android',
            str_contains($agen, 'Windows') => 'Windows',
            str_contains($agen, 'Mac OS X') || str_contains($agen, 'Macintosh') => 'macOS',
            str_contains($agen, 'Linux') => 'Linux',
            default => null,
        };

        return Str::limit($sistem === null ? $peramban : $peramban.' di '.$sistem, 120, '');
    }

    private static function Cari(?string $nilaiCookie): ?PerangkatTepercayaPengelola
    {
        $uuid = self::AmbilUuid($nilaiCookie);

        if ($uuid === null || $nilaiCookie === null) {
            return null;
        }

        $token = explode('.', $nilaiCookie, 2)[1];
        $perangkat = PerangkatTepercayaPengelola::query()->where('Uuid', $uuid)->first();

        if ($perangkat === null || ! hash_equals($perangkat->HashToken, PerangkatTepercayaPengelola::BuatHashToken($token))) {
            return null;
        }

        return $perangkat;
    }
}
