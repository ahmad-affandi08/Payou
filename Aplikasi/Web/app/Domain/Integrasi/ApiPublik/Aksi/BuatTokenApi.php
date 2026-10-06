<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\ApiPublik\Enum\CakupanApi;
use App\Domain\Integrasi\ApiPublik\Model\TokenApiTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * X7 bagian 1: Owner membuat token API publik bercakupan. Hanya bila paket tenant punya fitur `api.publik`. Token asli
 * `payoung_{IdTenant}_{40 karakter}` dikembalikan sekali untuk ditampilkan; yang tersimpan hanya hash & prefiks. Paling
 * banyak [BATAS_AKTIF] token aktif per tenant.
 */
final class BuatTokenApi
{
    public const BATAS_AKTIF = 10;

    public const PANJANG_RAHASIA = 40;

    public function __construct(
        private readonly PemeriksaFiturTenant $fitur,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $cakupan
     * @return array{Token: string, Model: TokenApiTenant}
     */
    public function Jalankan(int $idTenant, int $idPengguna, string $nama, array $cakupan, ?CarbonImmutable $kedaluwarsaPada): array
    {
        if (! $this->fitur->CekAktif($idTenant, 'api.publik')) {
            throw new PelanggaranAturanBisnis('FiturTidakTersedia', 'API publik tidak termasuk paket langganan Anda. Naikkan paket atau tambahkan add-on dulu.');
        }

        $cakupan = array_values(array_unique(array_filter($cakupan, fn (string $c): bool => CakupanApi::tryFrom($c) !== null)));

        if ($cakupan === []) {
            throw new PelanggaranAturanBisnis('CakupanKosong', 'Pilih minimal satu akses untuk token ini.');
        }

        return DB::transaction(function () use ($idTenant, $idPengguna, $nama, $cakupan, $kedaluwarsaPada): array {
            $aktif = TokenApiTenant::query()->whereNull('DicabutPada')->lockForUpdate()->count();

            if ($aktif >= self::BATAS_AKTIF) {
                throw new PelanggaranAturanBisnis('BatasTokenApi', 'Token aktif sudah '.self::BATAS_AKTIF.'. Cabut token yang tidak dipakai dulu.');
            }

            $rahasia = Str::random(self::PANJANG_RAHASIA);
            $token = "payoung_{$idTenant}_{$rahasia}";
            $model = TokenApiTenant::query()->create([
                'Nama' => mb_substr(trim($nama), 0, 60),
                'Prefiks' => mb_substr($token, 0, mb_strlen("payoung_{$idTenant}_") + 4),
                'HashToken' => TokenApiTenant::BuatHashToken($rahasia),
                'Cakupan' => $cakupan,
                'DibuatOleh' => $idPengguna,
                'KedaluwarsaPada' => $kedaluwarsaPada,
            ]);
            $this->audit->Catat('integrasi.token-api.buat', $model, null, ['Nama' => $model->Nama, 'Cakupan' => $cakupan, 'Prefiks' => $model->Prefiks]);

            return ['Token' => $token, 'Model' => $model];
        });
    }
}
