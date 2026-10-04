<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-18 bagian 5 (D-44): jadwal kerja terintegrasi penuh dengan absensi.
 *
 * - `AturanKehadiran` (satu per tenant): wajib berjadwal, batas masuk paling awal, toleransi terlambat/pulang cepat,
 *   ambang lembur, pengingat shift ke karyawan, peringatan terlambat/tidak masuk ke pengelola. Tanpa baris = bawaan
 *   (tidak ada penegakan, notifikasi mati) sehingga tenant lama tidak berubah perilakunya.
 * - `JadwalKerja.PengingatShiftPada`/`PeringatanKehadiranPada`: penanda sekali kirim per jadwal.
 * - `Karyawan`: tarif lembur per jam, potongan terlambat per menit, potongan tidak masuk per hari (null = tidak dihitung).
 * - `Absensi.DiluarJadwal`: absen POS (offline) yang masuk saat aturan wajib-jadwal aktif tetapi tidak sesuai jadwal.
 * - `RekapGajiBaris`: lembur, potongan terlambat, dan potongan tidak masuk beserta dasar hitungnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('AturanKehadiran', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->unique('UniqAturanKehadiranIdTenant')->constrained('Tenant', 'Id', 'FkAturanKehadiranIdTenant')->restrictOnDelete();
            $tabel->boolean('WajibJadwal')->default(false);
            $tabel->unsignedSmallInteger('MasukPalingAwalMenit')->default(60);
            $tabel->unsignedSmallInteger('ToleransiTerlambatMenit')->default(5);
            $tabel->unsignedSmallInteger('ToleransiPulangCepatMenit')->default(5);
            $tabel->unsignedSmallInteger('LemburSetelahMenit')->default(30);
            $tabel->boolean('PengingatShiftAktif')->default(false);
            $tabel->unsignedSmallInteger('PengingatShiftMenitSebelum')->default(30);
            $tabel->boolean('PeringatanPengelolaAktif')->default(false);
            $tabel->unsignedSmallInteger('PeringatanPengelolaSetelahMenit')->default(15);
            $tabel->WaktuStandar();
        });

        Schema::table('JadwalKerja', function (Blueprint $tabel): void {
            $tabel->timestamp('PengingatShiftPada')->nullable();
            $tabel->timestamp('PeringatanKehadiranPada')->nullable();
        });

        Schema::table('Karyawan', function (Blueprint $tabel): void {
            $tabel->decimal('TarifLemburPerJam', 18, 2)->nullable();
            $tabel->decimal('PotonganTerlambatPerMenit', 18, 2)->nullable();
            $tabel->decimal('PotonganTidakMasukPerHari', 18, 2)->nullable();
        });

        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->boolean('DiluarJadwal')->default(false);
        });

        Schema::table('RekapGajiBaris', function (Blueprint $tabel): void {
            $tabel->unsignedInteger('LemburMenit')->default(0);
            $tabel->decimal('Lembur', 18, 2)->default(0);
            $tabel->unsignedInteger('TerlambatMenit')->default(0);
            $tabel->decimal('PotonganTerlambat', 18, 2)->default(0);
            $tabel->unsignedInteger('HariTidakMasuk')->default(0);
            $tabel->decimal('PotonganTidakMasuk', 18, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('RekapGajiBaris', function (Blueprint $tabel): void {
            $tabel->dropColumn(['LemburMenit', 'Lembur', 'TerlambatMenit', 'PotonganTerlambat', 'HariTidakMasuk', 'PotonganTidakMasuk']);
        });

        Schema::table('Absensi', function (Blueprint $tabel): void {
            $tabel->dropColumn('DiluarJadwal');
        });

        Schema::table('Karyawan', function (Blueprint $tabel): void {
            $tabel->dropColumn(['TarifLemburPerJam', 'PotonganTerlambatPerMenit', 'PotonganTidakMasukPerHari']);
        });

        Schema::table('JadwalKerja', function (Blueprint $tabel): void {
            $tabel->dropColumn(['PengingatShiftPada', 'PeringatanKehadiranPada']);
        });

        Schema::dropIfExists('AturanKehadiran');
    }
};
