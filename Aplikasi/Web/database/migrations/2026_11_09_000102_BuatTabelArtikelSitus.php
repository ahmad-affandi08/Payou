<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Situs pemasaran bagian B2 (§13.9): artikel blog `payoung.id/blog` (data platform, bukan tenant). Isi memakai format teks
 * ringan situs (tanpa HTML); sampul dari pustaka `GambarSitus` (lewat Uuid, sama seperti gambar halaman).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ArtikelSitus', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->string('Slug', 120)->unique('UqArtikelSitusSlug');
            $tabel->string('Judul', 150);
            $tabel->string('Ringkasan', 300)->nullable();
            $tabel->mediumText('Isi');
            $tabel->string('Kategori', 60)->nullable();
            $tabel->string('NamaPenulis', 80)->nullable();
            $tabel->string('UuidGambarSampul', 26)->nullable();
            $tabel->string('JudulSeo', 70)->nullable();
            $tabel->string('DeskripsiSeo', 170)->nullable();
            $tabel->string('Status', 20);
            $tabel->timestamp('DiterbitkanPada')->nullable();
            $tabel->foreignId('IdPenggunaPengelolaPengubah')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkArtikelSitusIdPengubah')->nullOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['Status', 'DiterbitkanPada'], 'IdxArtikelSitusStatusDiterbitkanPada');
            $tabel->index(['Kategori'], 'IdxArtikelSitusKategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ArtikelSitus');
    }
};
