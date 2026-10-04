<?php

declare(strict_types=1);

namespace Tests\Pendukung\Tenant;

use Illuminate\Support\Facades\Route;

/**
 * Penjaga daftar Pengaturan (`Pustaka/DaftarPengaturan.ts`): tautan ada di frontend, jadi rute yang diganti nama
 * tidak terlihat sampai diklik. Butir bertanda `edisi` hanya tampil di edisinya (D-35), jadi diperiksa terhadap rute
 * edisi itu; butir tanpa tanda harus ada di semua edisi.
 */
final class BantuanDaftarPengaturan
{
    /**
     * Tautan butir yang tampil di edisi ini (tanpa tanda `edisi`, atau bertanda edisi ini).
     *
     * @return list<string>
     */
    public static function AmbilTautanEdisi(string $edisi): array
    {
        $isi = (string) file_get_contents(resource_path('js/Pustaka/DaftarPengaturan.ts'));
        preg_match_all("/\\{[^{}]*href: '([^']+)'[^{}]*\\}/", $isi, $cocok, PREG_SET_ORDER);

        $tautan = [];

        foreach ($cocok as [$butir, $href]) {
            $tanda = preg_match("/edisi: '([A-Za-z]+)'/", $butir, $edisiButir) === 1 ? $edisiButir[1] : null;

            if ($tanda === null || $tanda === $edisi) {
                $tautan[] = $href;
            }
        }

        return $tautan;
    }

    /**
     * Tautan yang tampil di edisi ini tetapi tidak punya rute GET terdaftar.
     *
     * @param  list<string>  $tautan
     * @return list<string>
     */
    public static function CariTautanTanpaRute(array $tautan): array
    {
        $jalurTerdaftar = [];

        foreach (Route::getRoutes()->getRoutes() as $rute) {
            if (in_array('GET', $rute->methods(), true)) {
                $jalurTerdaftar[] = $rute->uri();
            }
        }

        return array_values(array_filter(
            $tautan,
            fn (string $href): bool => ! in_array(ltrim($href, '/'), $jalurTerdaftar, true),
        ));
    }
}
