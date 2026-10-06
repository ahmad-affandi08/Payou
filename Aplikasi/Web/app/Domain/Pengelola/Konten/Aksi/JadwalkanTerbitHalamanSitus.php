<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\HalamanSitus;
use Illuminate\Support\Carbon;

/**
 * D-63: menjadwalkan (atau membatalkan jadwal) penerbitan draf halaman. Yang diterbitkan nanti adalah draf pada saat
 * jadwal tiba, oleh `situs:terbitkan-terjadwal`. Waktu harus di masa depan.
 */
final class JadwalkanTerbitHalamanSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, HalamanSitus $halaman, ?Carbon $waktu): HalamanSitus
    {
        if ($waktu !== null && $waktu->lessThanOrEqualTo(now()->addMinute())) {
            throw new PelanggaranAturanBisnis('JadwalTerlaluDekat', 'Pilih waktu terbit minimal beberapa menit dari sekarang.', 'JadwalTerbitPada');
        }

        $lama = $halaman->JadwalTerbitPada?->toIso8601ZuluString();
        $halaman->forceFill([
            'JadwalTerbitPada' => $waktu,
            'IdPenggunaPengelolaPenjadwal' => $waktu === null ? null : $pelaku->Id,
        ])->save();

        $this->audit->Catat($waktu === null ? 'situs.halaman.batal-jadwal' : 'situs.halaman.jadwalkan', $halaman,
            nilaiLama: ['JadwalTerbitPada' => $lama], nilaiBaru: ['JadwalTerbitPada' => $waktu?->toIso8601ZuluString()], idPelaku: $pelaku->Id);

        return $halaman;
    }
}
