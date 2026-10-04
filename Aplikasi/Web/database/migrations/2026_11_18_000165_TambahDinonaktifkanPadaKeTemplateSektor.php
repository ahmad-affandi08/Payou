<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-47 (P-03): template sektor yang sudah terbit tidak bisa dihapus (BR-P03.2), tetapi bisa dinonaktifkan supaya tidak
 * ditawarkan ke tenant baru. Tenant yang sudah memakainya tidak terpengaruh. Null = aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('TemplateSektor', function (Blueprint $tabel): void {
            $tabel->timestamp('DinonaktifkanPada')->nullable()->after('Keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('TemplateSektor', function (Blueprint $tabel): void {
            $tabel->dropColumn('DinonaktifkanPada');
        });
    }
};
