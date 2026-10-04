<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

/**
 * Kunci sesi Platform Pengelola. Disimpan di cookie sesi pengelola yang terpisah dari tenant (BR-P01.4).
 */
final class SesiPengelola
{
    public const DUA_FAKTOR_TERVERIFIKASI = 'Pengelola.DuaFaktorTerverifikasi';

    public const TERAKHIR_AKTIF = 'Pengelola.TerakhirAktifPada';

    public const RAHASIA_2FA_SEMENTARA = 'Pengelola.Rahasia2faSementara';

    public const KODE_PEMULIHAN_BARU = 'Pengelola.KodePemulihanBaru';

    /** D-42: waktu (timestamp) kode 2FA terakhir benar-benar dimasukkan di sesi ini; login lewat perangkat tepercaya tidak mengisinya. */
    public const KODE_DUA_FAKTOR_PADA = 'Pengelola.KodeDuaFaktorPada';

    /** D-42: halaman yang dibuka lagi setelah konfirmasi 2FA untuk aksi berbahaya. */
    public const KEMBALI_SETELAH_KONFIRMASI = 'Pengelola.KembaliSetelahKonfirmasi';

    public const GUARD = 'pengelola';
}
