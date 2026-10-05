/** Mencabut layar muat awal dari blade (`#layar-muat`) begitu React terpasang: memudar 300 ms lalu dibuang. */
export function TutupLayarMuat(): void {
    const layar = document.getElementById('layar-muat');
    if (!layar) {
        return;
    }
    layar.setAttribute('data-selesai', '');
    window.setTimeout(() => layar.remove(), 350);
}
