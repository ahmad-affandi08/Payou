<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="robots" content="noindex, nofollow">
    @viteReactRefresh
    @vite(['resources/js/Pengelola.tsx'])
    @inertiaHead
    <noscript><style>.layar-muat{display:none}</style></noscript>
</head>
<body>
    {{-- D-58: layar muat awal statis; gayanya di Gaya/Muat.css, dicabut Pustaka/LayarMuat saat React terpasang. --}}
    <div id="layar-muat" class="layar-muat">
        <div class="layar-muat__inti">
            <div class="tanda-muat" role="status" aria-label="Memuat" style="--u: 72px">
                <span class="tanda-muat__isi"><img src="{{ Vite::asset('resources/js/Aset/Merek/IkonMerek.webp') }}" alt=""></span>
            </div>
            <p class="layar-muat__kata"><span>Menyiapkan konsol</span><span>Mengambil data terbaru…</span></p>
        </div>
    </div>
    @inertia
</body>
</html>
