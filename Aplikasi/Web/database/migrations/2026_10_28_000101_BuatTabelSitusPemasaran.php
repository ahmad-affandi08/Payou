<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-21 v2.10: situs pemasaran `payoung.id` yang isinya diatur dari konsol (data platform, tanpa IdTenant).
 *
 * `PengaturanSitus`: satu baris (`Kunci` = `Umum`), `Nilai` JSON: identitas, SEO bawaan, kontak, media sosial,
 * pengumuman, menu atas & kaki, tautan unduh, ID analitik.
 * `HalamanSitus`: halaman berblok (`Bagian` JSON). `BagianDraf` diedit, `BagianTerbit` yang tampil publik; terbitkan =
 * salin draf. `Slug` unik (`beranda` = halaman `/`).
 * `GambarSitus`: pustaka gambar unggahan konsol (disk publik), dipakai blok halaman & SEO.
 * Izin pengelola baru `situs.lihat` & `situs.kelola` langsung diberikan ke peran bawaan Super Admin & Konten & Legal
 * yang sudah ada (peran baru mengikuti `PeranPengelolaBawaan`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PengaturanSitus', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->string('Kunci', 50);
            $tabel->json('Nilai');
            $tabel->unsignedBigInteger('IdPenggunaPengelolaPengubah')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('Kunci', 'UniqPengaturanSitusKunci');
        });

        Schema::create('HalamanSitus', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->char('Uuid', 26);
            $tabel->string('Slug', 100);
            $tabel->string('Judul', 150);
            $tabel->string('JudulSeo', 70)->nullable();
            $tabel->string('DeskripsiSeo', 170)->nullable();
            $tabel->char('UuidGambarOg', 26)->nullable();
            $tabel->boolean('TampilDiSitemap')->default(true);
            $tabel->json('BagianDraf');
            $tabel->json('BagianTerbit')->nullable();
            $tabel->string('JudulTerbit', 150)->nullable();
            $tabel->string('JudulSeoTerbit', 70)->nullable();
            $tabel->string('DeskripsiSeoTerbit', 170)->nullable();
            $tabel->char('UuidGambarOgTerbit', 26)->nullable();
            $tabel->boolean('Aktif')->default(true);
            $tabel->timestamp('DiterbitkanPada')->nullable();
            $tabel->unsignedBigInteger('IdPenggunaPengelolaPenerbit')->nullable();
            $tabel->unsignedBigInteger('IdPenggunaPengelolaPengubah')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('Uuid', 'UniqHalamanSitusUuid');
            $tabel->unique('Slug', 'UniqHalamanSitusSlug');
        });

        Schema::create('GambarSitus', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->char('Uuid', 26);
            $tabel->string('Path', 255);
            $tabel->string('NamaBerkas', 150);
            $tabel->string('TipeMime', 50);
            $tabel->unsignedInteger('Ukuran');
            $tabel->unsignedInteger('Lebar')->nullable();
            $tabel->unsignedInteger('Tinggi')->nullable();
            $tabel->string('TeksAlternatif', 150)->nullable();
            $tabel->unsignedBigInteger('IdPenggunaPengelolaPengunggah')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique('Uuid', 'UniqGambarSitusUuid');
        });

        foreach (DB::table('PeranPengelola')->whereIn('Kode', ['SuperAdmin', 'KontenLegal'])->pluck('Id') as $idPeran) {
            foreach (['situs.lihat', 'situs.kelola'] as $kunci) {
                DB::table('PeranPengelolaIzin')->insertOrIgnore(['IdPeranPengelola' => $idPeran, 'KunciIzin' => $kunci]);
            }
        }
    }

    public function down(): void
    {
        DB::table('PeranPengelolaIzin')->whereIn('KunciIzin', ['situs.lihat', 'situs.kelola'])->delete();
        Schema::dropIfExists('GambarSitus');
        Schema::dropIfExists('HalamanSitus');
        Schema::dropIfExists('PengaturanSitus');
    }
};
