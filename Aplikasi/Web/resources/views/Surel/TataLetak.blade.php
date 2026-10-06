{{--
    Tata letak email HTML Payoung (§17.6, D-26).

    Kenapa gayanya ditulis inline dan warnanya hex literal, bukan token:
    klien email (Outlook, Gmail, Apple Mail) tidak mendukung variabel CSS, `color-mix()`, maupun kelas
    Tailwind. Nilai hex di sini disalin dari token `Gaya/Aplikasi.css` dan dijaga `tests/Arsitektur/SurelTes.php`.

    Struktur email memakai tabel `role="presentation"` karena klien email tidak bisa diandalkan untuk
    flexbox/grid. Tanpa gambar: gambar diblokir bawaan di banyak klien, jadi merek dibawa lewat teks
    berwarna, bukan berkas logo.

    Bagian yang diisi templat anak:
    - `Pratinjau`  : satu baris teks cuplikan di kotak masuk (wajib, kalau kosong klien memakai isi email).
    - `Judul`      : judul di dalam kartu (wajib).
    - `Isi`        : paragraf & komponen (wajib).
    - `CatatanKaki`: catatan tambahan di atas kaki bersama (opsional).
--}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    {{-- D-14: tanpa mode gelap. Tanpa dua meta ini, Gmail & Apple Mail membalik warna sendiri dan kontras rusak. --}}
    <meta name="color-scheme" content="light only" />
    <meta name="supported-color-schemes" content="light only" />
    <title>@yield('Judul')</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, p, a, h1, h2 { font-family: Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        /* Klien yang mendukung <style> memakai ini; sisanya tetap benar karena semua gaya juga inline. */
        body { margin: 0; padding: 0; width: 100% !important; }
        a { color: #3b5b5d; }
        .Kartu { border-radius: 0 0 10px 10px; }
        @media only screen and (max-width: 600px) {
            .Bingkai { width: 100% !important; }
            .Sisi { padding-left: 20px !important; padding-right: 20px !important; }
            .JudulKartu { font-size: 20px !important; line-height: 26px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f7f9f6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
{{-- Cuplikan kotak masuk. Deretan &#8199; mendorong isi email keluar dari cuplikan supaya tidak ikut terbaca. --}}
<div style="display:none; font-size:1px; color:#f7f9f6; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
    @yield('Pratinjau')
    &#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f7f9f6;">
    <tr>
        <td align="center" style="padding:24px 12px 40px 12px;">

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" class="Bingkai" style="width:600px; max-width:600px;">

                {{-- Kepala merek: wordmark di atas latar Slate Teal gelap, digarisi Apricot aksen dari logo. --}}
                <tr>
                    <td class="Sisi" style="background-color:#22383a; border-radius:10px 10px 0 0; padding:22px 32px;">
                        <span style="font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:20px; line-height:26px; font-weight:bold; letter-spacing:3px; color:#ffffff; text-transform:uppercase;">{{ config('app.name') }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f4a261; line-height:4px; font-size:4px; height:4px;">&nbsp;</td>
                </tr>

                {{-- Kartu isi --}}
                <tr>
                    <td class="Kartu" style="background-color:#ffffff; border-left:1px solid #e8ece9; border-right:1px solid #e8ece9; border-bottom:1px solid #e8ece9; border-radius:0 0 10px 10px;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td class="Sisi" style="padding:32px;">
                                    <h1 class="JudulKartu" style="margin:0 0 16px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:24px; line-height:30px; font-weight:bold; color:#1f3335;">@yield('Judul')</h1>
                                    @yield('Isi')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Kaki bersama, di luar kartu --}}
                <tr>
                    <td class="Sisi" style="padding:24px 32px 0 32px; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:20px; color:#4f6567;">
                        @hasSection('CatatanKaki')
                            <p style="margin:0 0 12px 0;">@yield('CatatanKaki')</p>
                        @endif
                        <p style="margin:0;">
                            {{ config('app.name') }} &mdash; kasir, stok, dan pembukuan dalam satu aplikasi.<br />
                            Butuh bantuan? Balas email ini atau hubungi <a href="mailto:{{ config('mail.from.address') }}" style="color:#3b5b5d; text-decoration:underline;">{{ config('mail.from.address') }}</a>.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>
</body>
</html>
