<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\MasukGoogle\IdentitasGoogle;
use App\Domain\Integrasi\MasukGoogle\KlienOauthGoogle;
use App\Domain\Integrasi\MasukGoogle\KonfigurasiGoogle;
use App\Domain\Integrasi\MasukGoogle\PemverifikasiTokenGoogle;
use App\Domain\Integrasi\MasukGoogle\TokenGoogleTidakSah;
use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Domain\Organisasi\Aksi\MasukDenganGoogle;
use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Data\DataPendaftaran;
use App\Domain\Tenant\Kueri\PaketTersedia;
use App\Domain\Tenant\Kueri\StatusPendaftaran;
use App\Domain\Tenant\Model\Paket;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\IdentifikasiTenantSesi;
use App\Http\Perantara\SesiAutentikasiTenant;
use App\Http\Permintaan\Autentikasi\DaftarPermintaan;
use App\Http\Permintaan\Autentikasi\LengkapiGooglePermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D-57 Masuk dengan Google untuk dashboard tenant. Alur OAuth 2.0 kode otorisasi di server: `/masuk/google` mengalihkan
 * ke Google dengan `state` + `nonce` acak yang disimpan di sesi; `/masuk/google/panggilan-balik` menukar kode, memverifikasi
 * token ID, lalu menurut tujuan alur: **masuk** (akun ada → masuk tanpa 2FA; belum ada → lanjut mendaftar), **daftar**
 * (akun ada → masuk), atau **tautkan** (dari Keamanan akun). Masuk lewat Google menggantikan 2FA dan menandai sesi
 * (`MASUK_GOOGLE`) agar kewajiban 2FA paket Bisnis terpenuhi. Pendaftaran Google tidak memakai CAPTCHA (identitas dibuktikan
 * Google) tetapi tetap dibatasi laju dan wajib menyetujui S&K/Kebijakan Privasi.
 */
final class MasukGoogleKontroler extends Kontroler
{
    public const TUJUAN_MASUK = 'masuk';

    public const TUJUAN_DAFTAR = 'daftar';

    public const TUJUAN_TAUTKAN = 'tautkan';

    /** Batas percobaan panggilan balik gagal per IP (menahan penebakan state/kode). */
    private const BATAS_GAGAL_PER_IP = 20;

    public function Mulai(Request $permintaan, KonfigurasiGoogle $konfigurasi, KlienOauthGoogle $klien): RedirectResponse
    {
        $tujuan = $permintaan->string('tujuan')->toString();
        $tujuan = in_array($tujuan, [self::TUJUAN_DAFTAR, self::TUJUAN_TAUTKAN], true) ? $tujuan : self::TUJUAN_MASUK;

        if ($tujuan === self::TUJUAN_TAUTKAN) {
            abort_unless($permintaan->user('web') instanceof Pengguna, 403);
        }

        if (! $konfigurasi->CekAktif()) {
            return $this->KeHalamanAwal($tujuan)->with('Kilat', 'Masuk dengan Google belum diaktifkan. Gunakan email dan kata sandi.');
        }

        $state = Str::random(40);
        $nonce = Str::random(40);
        $paket = $permintaan->string('paket')->toString();
        $permintaan->session()->put(SesiAutentikasiTenant::GOOGLE_ALUR, [
            'State' => $state,
            'Nonce' => $nonce,
            'Tujuan' => $tujuan,
            'Paket' => mb_substr($paket, 0, 30),
            'Sampai' => now()->addMinutes(SesiAutentikasiTenant::MENIT_ALUR_GOOGLE)->getTimestamp(),
        ]);

        return redirect()->away($klien->BuatUrlOtorisasi(route('masuk.google.panggilan-balik'), $state, $nonce));
    }

    public function PanggilanBalik(
        Request $permintaan,
        KlienOauthGoogle $klien,
        PemverifikasiTokenGoogle $pemverifikasi,
        MasukDenganGoogle $masuk,
        KeanggotaanPengguna $keanggotaan,
    ): RedirectResponse {
        $kunciIp = 'masuk-google-ip:'.$permintaan->ip();
        $alur = $permintaan->session()->pull(SesiAutentikasiTenant::GOOGLE_ALUR);

        if (RateLimiter::tooManyAttempts($kunciIp, self::BATAS_GAGAL_PER_IP)) {
            return redirect()->route('masuk')->with('Kilat', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
        }

        $tujuan = is_array($alur) && is_string($alur['Tujuan'] ?? null) ? $alur['Tujuan'] : self::TUJUAN_MASUK;
        $awal = $this->KeHalamanAwal($tujuan);

        if (! is_array($alur)
            || ! is_int($alur['Sampai'] ?? null) || $alur['Sampai'] < now()->getTimestamp()
            || ! hash_equals((string) ($alur['State'] ?? ''), $permintaan->string('state')->toString())) {
            RateLimiter::hit($kunciIp, 300);

            return $awal->with('Kilat', 'Sesi Masuk dengan Google sudah berakhir. Coba lagi.');
        }

        if ($permintaan->filled('error') || ! $permintaan->filled('code')) {
            // Pengguna menekan "Batal" di layar Google.
            return $awal->with('Kilat', 'Masuk dengan Google dibatalkan.');
        }

        try {
            $identitas = $pemverifikasi->Verifikasi(
                $klien->TukarKode($permintaan->string('code')->toString(), route('masuk.google.panggilan-balik')),
                (string) $alur['Nonce'],
            );
        } catch (TokenGoogleTidakSah $galat) {
            RateLimiter::hit($kunciIp, 300);
            Log::warning('Masuk dengan Google ditolak.', ['Alasan' => $galat->getMessage()]);

            return $awal->with('Kilat', 'Akun Google tidak bisa diverifikasi. Pastikan email akun Google Anda sudah terverifikasi, lalu coba lagi.');
        }

        if ($tujuan === self::TUJUAN_TAUTKAN) {
            return $this->Tautkan($permintaan, $masuk, $identitas);
        }

        try {
            $pengguna = $masuk->Jalankan($identitas);
        } catch (PelanggaranAturanBisnis $galat) {
            return redirect()->route('masuk')->with('Kilat', $galat->getMessage());
        }

        if ($pengguna instanceof Pengguna) {
            return $this->SelesaikanMasuk($permintaan, $pengguna, $keanggotaan, $tujuan === self::TUJUAN_DAFTAR);
        }

        // Belum ada akun. Edisi Lisensi tidak membuka pendaftaran (D-35): pemilik akun harus mengundang lebih dulu.
        if (EdisiAplikasi::CekLisensi()) {
            return redirect()->route('masuk')->with('Kilat', 'Akun Google ini belum terdaftar di sistem. Minta admin usaha Anda mengundang email ini lebih dulu.');
        }

        $permintaan->session()->put(SesiAutentikasiTenant::GOOGLE_PENDAFTARAN, [
            'Sub' => $identitas->sub,
            'Email' => $identitas->email,
            'Nama' => $identitas->nama,
            'Paket' => is_string($alur['Paket'] ?? null) ? $alur['Paket'] : '',
            'Sampai' => now()->addMinutes(SesiAutentikasiTenant::MENIT_ALUR_GOOGLE + 10)->getTimestamp(),
        ]);

        return redirect()->route('daftar.google');
    }

    public function TampilkanLengkapi(Request $permintaan, StatusPendaftaran $status, PaketTersedia $paket): Response|RedirectResponse
    {
        $tertunda = $this->AmbilPendaftaranTertunda($permintaan);

        if ($tertunda === null) {
            return redirect()->route('daftar')->with('Kilat', 'Sesi Daftar dengan Google sudah berakhir. Mulai lagi.');
        }

        $daftarPaket = array_values(array_map(
            fn (Paket $baris) => ['Kode' => $baris->Kode, 'Nama' => $baris->Nama, 'MasaTrialHari' => $baris->MasaTrialHari],
            array_filter($paket->AmbilUntukPendaftaran(), fn (Paket $baris) => ! $baris->HargaNegosiasi),
        ));
        $diminta = mb_strtoupper($tertunda['Paket']);

        return Inertia::render('Autentikasi/LengkapiGoogle', [
            'Dibuka' => $status->AmbilAlasanDitutup() === [],
            'Akun' => ['Nama' => $tertunda['Nama'], 'Email' => $tertunda['Email']],
            'Paket' => $daftarPaket,
            'PaketTerpilih' => in_array($diminta, array_column($daftarPaket, 'Kode'), true) ? $diminta : (string) config('tenant.KodePaketTrialBawaan'),
        ]);
    }

    public function Lengkapi(
        LengkapiGooglePermintaan $permintaan,
        StatusPendaftaran $status,
        DaftarkanTenant $daftarkan,
        MasukDenganGoogle $masuk,
        KeanggotaanPengguna $keanggotaan,
    ): RedirectResponse {
        $tertunda = $this->AmbilPendaftaranTertunda($permintaan);

        if ($tertunda === null) {
            return redirect()->route('daftar')->with('Kilat', 'Sesi Daftar dengan Google sudah berakhir. Mulai lagi.');
        }

        $alasanDitutup = $status->AmbilAlasanDitutup();

        if ($alasanDitutup !== []) {
            Log::warning('Pendaftaran Google ditolak karena prasyarat belum lengkap.', ['Alasan' => $alasanDitutup]);

            throw new PelanggaranAturanBisnis('BR-P06.2', 'Pendaftaran belum dibuka. Silakan coba lagi nanti.');
        }

        // Kata sandi acak buatan sistem: pemilik masuk lewat Google dan bisa mengaturnya sendiri kemudian.
        $hasil = $daftarkan->Jalankan(new DataPendaftaran(
            pemilik: new DataPemilikBaru(
                nama: trim($permintaan->string('Nama')->toString()),
                email: $tertunda['Email'],
                noHp: DaftarPermintaan::NormalkanNoHp($permintaan->string('NoHp')->toString()),
                kataSandi: Str::random(48),
                googleSub: $tertunda['Sub'],
            ),
            namaUsaha: trim($permintaan->string('NamaUsaha')->toString()),
            kodePaket: $permintaan->filled('Paket') ? mb_strtoupper($permintaan->string('Paket')->toString()) : null,
            ip: $permintaan->ip(),
        ), wajibPanduanAwal: true);

        $permintaan->session()->forget(SesiAutentikasiTenant::GOOGLE_PENDAFTARAN);
        (new PencatatMitraPendaftaran)->Catat($permintaan, $hasil['Tenant']->Id);

        Auth::guard('web')->login($hasil['Pengguna']);
        $permintaan->session()->regenerate();
        $permintaan->session()->put([IdentifikasiTenantSesi::KUNCI_SESI => $hasil['Tenant']->Id, SesiAutentikasiTenant::MASUK_GOOGLE => true]);
        app(PencatatAudit::class)->CatatSesi('sesi.masuk', $hasil['Tenant']->Id, $hasil['Pengguna']->Id, $permintaan->ip(), $permintaan->userAgent(), ['Metode' => 'Google']);

        return redirect()->route('kelola.panduan-awal')->with('Kilat', "Selamat datang di {$hasil['Tenant']->Nama}! Akun Google Anda sudah tertaut.");
    }

    private function Tautkan(Request $permintaan, MasukDenganGoogle $masuk, IdentitasGoogle $identitas): RedirectResponse
    {
        $pengguna = $permintaan->user('web');

        if (! $pengguna instanceof Pengguna) {
            return redirect()->route('masuk')->with('Kilat', 'Masuk dulu untuk menautkan akun Google.');
        }

        try {
            $masuk->TautkanKeAkun($pengguna, $identitas);
        } catch (PelanggaranAturanBisnis $galat) {
            return redirect()->route('kelola.keamanan')->with('Kilat', $galat->getMessage());
        }

        return redirect()->route('kelola.keamanan')->with('Kilat', 'Akun Google berhasil ditautkan.');
    }

    private function SelesaikanMasuk(Request $permintaan, Pengguna $pengguna, KeanggotaanPengguna $keanggotaan, bool $dariDaftar): RedirectResponse
    {
        Auth::guard('web')->login($pengguna);
        $permintaan->session()->regenerate();
        $permintaan->session()->put(SesiAutentikasiTenant::MASUK_GOOGLE, true);
        $daftarTenant = $keanggotaan->AmbilIdTenant($pengguna->Id);
        $pesan = $dariDaftar ? 'Akun ini sudah terdaftar, jadi Anda langsung masuk.' : null;

        if (count($daftarTenant) === 1) {
            $permintaan->session()->put(IdentifikasiTenantSesi::KUNCI_SESI, $daftarTenant[0]);
            app(PencatatAudit::class)->CatatSesi('sesi.masuk', $daftarTenant[0], $pengguna->Id, $permintaan->ip(), $permintaan->userAgent(), ['Metode' => 'Google']);
            $tujuan = redirect()->intended(route('kelola.beranda'));

            return $pesan === null ? $tujuan : $tujuan->with('Kilat', $pesan);
        }

        return redirect()->route('pilih-tenant');
    }

    /** @return array{Sub: string, Email: string, Nama: string, Paket: string}|null */
    private function AmbilPendaftaranTertunda(Request $permintaan): ?array
    {
        $data = $permintaan->session()->get(SesiAutentikasiTenant::GOOGLE_PENDAFTARAN);

        if (! is_array($data) || ! is_string($data['Sub'] ?? null) || ! is_string($data['Email'] ?? null)
            || ! is_int($data['Sampai'] ?? null) || $data['Sampai'] < now()->getTimestamp()) {
            $permintaan->session()->forget(SesiAutentikasiTenant::GOOGLE_PENDAFTARAN);

            return null;
        }

        return [
            'Sub' => $data['Sub'],
            'Email' => $data['Email'],
            'Nama' => is_string($data['Nama'] ?? null) ? $data['Nama'] : '',
            'Paket' => is_string($data['Paket'] ?? null) ? $data['Paket'] : '',
        ];
    }

    private function KeHalamanAwal(string $tujuan): RedirectResponse
    {
        return match ($tujuan) {
            self::TUJUAN_TAUTKAN => redirect()->route('kelola.keamanan'),
            self::TUJUAN_DAFTAR => EdisiAplikasi::CekLisensi() ? redirect()->route('masuk') : redirect()->route('daftar'),
            default => redirect()->route('masuk'),
        };
    }
}
