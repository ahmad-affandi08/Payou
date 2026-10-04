<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-45: wajah karyawan yang didaftarkan langsung Disetujui, tanpa menunggu pengelola. Pendaftaran lama yang masih
 * Menunggu ikut disetujui supaya karyawan tidak tertahan; `DitinjauOleh`/`DitinjauPada` dibiarkan kosong karena tidak
 * ada orang yang meninjaunya. Pendaftaran yang sudah ditolak tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('WajahKaryawan')->where('Status', 'Menunggu')->update(['Status' => 'Disetujui']);
    }

    public function down(): void {}
};
