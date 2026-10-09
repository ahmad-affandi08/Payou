"""Ilustrasi keadaan kosong khusus aplikasi Kasir (D-68 lanjutan): Keranjang, Meja, Dapur, Sinkron, Kas, Cari,
Kalender, Cucian, Servis. Gaya dan palet sama dengan set yang sudah ada (blob sage pucat, garis Teal Tinta, isi Slate
Teal, aksen Apricot). Menulis SVG ke `KeadaanKosong/Sumber/`; PNG diekspor dengan `RenderIlustrasi.cjs` (Chromium).

    python3 BuatIlustrasiKasir.py
"""

from pathlib import Path

KELUAR = Path(__file__).parent / 'KeadaanKosong' / 'Sumber'

GARIS = '#1F3335'
APRICOT = '#F4A261'
TERANG = '#D9EBE7'
BLOB = '#DCEBE8'

DEFS = f"""
<defs>
  <linearGradient id="a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#528688"/><stop offset="1" stop-color="#365759"/></linearGradient>
  <linearGradient id="b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3C6264"/><stop offset="1" stop-color="#26403F"/></linearGradient>
  <filter id="k" x="-20%" y="-200%" width="140%" height="500%"><feGaussianBlur stdDeviation="14"/></filter>
</defs>"""

BLOB_PATH = (
    'M170 430 C165 330 260 235 380 232 C450 230 480 250 520 248 C600 244 650 215 735 240 '
    'C830 268 880 360 850 470 C825 575 745 690 600 750 C470 802 300 790 215 690 C175 640 172 520 170 430 Z'
)

DASAR = f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="1024" height="1024">{DEFS}
<ellipse cx="512" cy="835" rx="300" ry="22" fill="{GARIS}" opacity=".10" filter="url(#k)"/>
<path d="{BLOB_PATH}" fill="{BLOB}"/>
<circle cx="200" cy="320" r="12" fill="{APRICOT}"/><circle cx="850" cy="430" r="9" fill="#4F8184"/>
{{isi}}
</svg>"""

G = f'stroke="{GARIS}" stroke-width="16" stroke-linejoin="round" stroke-linecap="round"'


def Lencana(x: int, y: int, tanda: str = 'plus') -> str:
    """Lingkaran apricot di pojok objek (aksi 'tambah' atau tanda centang)."""
    isi = (
        f'<path d="M{x-20} {y} H{x+20} M{x} {y-20} V{y+20}" stroke="#fff" stroke-width="12" stroke-linecap="round"/>'
        if tanda == 'plus'
        else f'<path d="M{x-20} {y+2} L{x-5} {y+17} L{x+22} {y-14}" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>'
    )
    return f'<circle cx="{x}" cy="{y}" r="44" fill="{APRICOT}" {G}/>{isi}'


def Jam(x: int, y: int, r: int = 96) -> str:
    return (
        f'<circle cx="{x}" cy="{y}" r="{r}" fill="#fff" {G}/>'
        f'<path d="M{x} {y-r*0.58} V{y} L{x+r*0.42} {y+r*0.34}" fill="none" stroke="#3C6264" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>'
    )


ILUSTRASI: dict[str, str] = {}

ILUSTRASI['Keranjang'] = f"""
<path d="M350 455 C350 330 430 300 512 300 C594 300 674 330 674 455" fill="none" {G.replace('16', '22')}/>
<path d="M240 470 H784 L722 722 Q716 748 690 748 H334 Q308 748 302 722 Z" fill="url(#b)" {G}/>
<path d="M290 540 H734 M304 610 H720 M318 680 H706" stroke="#7FA9A9" stroke-width="12" stroke-linecap="round" opacity=".6"/>
<path d="M418 470 V740 M512 470 V745 M606 470 V740" stroke="#7FA9A9" stroke-width="12" stroke-linecap="round" opacity=".35"/>
<rect x="214" y="430" width="596" height="56" rx="24" fill="url(#a)" {G}/>
{Lencana(780, 330)}
"""

ILUSTRASI['Meja'] = f"""
<rect x="205" y="395" width="26" height="190" rx="13" fill="url(#a)" {G}/>
<rect x="205" y="560" width="120" height="30" rx="14" fill="url(#a)" {G}/>
<rect x="215" y="590" width="24" height="130" rx="10" fill="url(#b)" {G}/>
<rect x="793" y="395" width="26" height="190" rx="13" fill="url(#a)" {G}/>
<rect x="699" y="560" width="120" height="30" rx="14" fill="url(#a)" {G}/>
<rect x="785" y="590" width="24" height="130" rx="10" fill="url(#b)" {G}/>
<rect x="352" y="540" width="38" height="190" rx="12" fill="url(#b)" {G}/>
<rect x="634" y="540" width="38" height="190" rx="12" fill="url(#b)" {G}/>
<rect x="290" y="468" width="444" height="76" rx="28" fill="url(#a)" {G}/>
<path d="M455 468 L470 392 H554 L569 468 Z" fill="{TERANG}" {G}/>
<path d="M556 408 Q600 408 598 438 Q596 462 566 462" fill="none" stroke="{GARIS}" stroke-width="12" stroke-linecap="round"/>
<circle cx="512" cy="360" r="14" fill="{APRICOT}"/>
"""

ILUSTRASI['Dapur'] = f"""
<rect x="215" y="285" width="594" height="30" rx="15" fill="url(#b)" {G}/>
<path d="M330 315 V345 M470 315 V345 M560 315 V370 M700 315 V370" stroke="{GARIS}" stroke-width="12" stroke-linecap="round"/>
<path d="M300 345 H500 V690 L470 665 L440 695 L410 665 L380 695 L350 665 L300 690 Z" fill="#fff" {G}/>
<path d="M340 410 H460 M340 460 H430 M340 510 H460 M340 560 H410" stroke="#7FA9A9" stroke-width="14" stroke-linecap="round"/>
<path d="M545 370 H745 V640 L715 615 L685 645 L655 615 L625 645 L595 615 L545 640 Z" fill="{TERANG}" {G}/>
<path d="M585 430 H705 M585 480 H675 M585 530 H705" stroke="#4F8184" stroke-width="14" stroke-linecap="round"/>
{Lencana(740, 690, 'cek')}
"""

ILUSTRASI['Sinkron'] = f"""
<path d="M300 650 C205 650 170 545 245 500 C235 395 345 335 435 385 C480 295 640 305 665 415 C780 405 845 530 765 610 C740 640 705 650 680 650 Z" fill="url(#a)" {G}/>
<path d="M395 520 L475 600 L640 440" fill="none" stroke="#fff" stroke-width="34" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M330 735 H690" stroke="{GARIS}" stroke-width="14" stroke-linecap="round" opacity=".25"/>
"""

ILUSTRASI['Kas'] = f"""
<g transform="rotate(-9 440 420)"><rect x="285" y="300" width="330" height="170" rx="22" fill="{TERANG}" {G}/>
<circle cx="450" cy="385" r="46" fill="#fff" {G}/><path d="M308 330 V440 M592 330 V440" stroke="#7FA9A9" stroke-width="12" stroke-linecap="round"/></g>
<g transform="rotate(7 560 450)"><rect x="410" y="330" width="330" height="170" rx="22" fill="#fff" {G}/>
<circle cx="575" cy="415" r="46" fill="{TERANG}" {G}/><path d="M432 360 V470 M718 360 V470" stroke="#7FA9A9" stroke-width="12" stroke-linecap="round"/></g>
<path d="M232 540 L262 490 H762 L792 540 Z" fill="url(#a)" {G}/>
<rect x="215" y="540" width="594" height="205" rx="34" fill="url(#b)" {G}/>
<rect x="300" y="600" width="424" height="86" rx="18" fill="{TERANG}" {G}/>
<path d="M440 643 H584" stroke="#4F8184" stroke-width="16" stroke-linecap="round"/>
<circle cx="770" cy="470" r="42" fill="{APRICOT}" {G}/><circle cx="770" cy="470" r="20" fill="none" stroke="#fff" stroke-width="10"/>
"""

ILUSTRASI['Cari'] = f"""
<rect x="260" y="270" width="380" height="470" rx="30" fill="#fff" {G}/>
<path d="M320 350 H520 M320 410 H500 M320 470 H450" stroke="{BLOB}" stroke-width="18" stroke-linecap="round"/>
<path d="M320 560 H400 M320 620 H380" stroke="{BLOB}" stroke-width="18" stroke-linecap="round"/>
<path d="M690 650 L790 750" stroke="{GARIS}" stroke-width="44" stroke-linecap="round"/>
<path d="M690 650 L790 750" stroke="#4F8184" stroke-width="20" stroke-linecap="round"/>
<circle cx="620" cy="580" r="112" fill="#fff" fill-opacity=".92" {G.replace('16', '22')}/>
<path d="M575 580 H665" stroke="{APRICOT}" stroke-width="20" stroke-linecap="round"/>
"""

ILUSTRASI['Kalender'] = f"""
<rect x="235" y="290" width="554" height="450" rx="38" fill="#fff" {G}/>
<path d="M235 328 Q235 290 273 290 H751 Q789 290 789 328 V405 H235 Z" fill="url(#a)" {G}/>
<rect x="335" y="250" width="30" height="80" rx="15" fill="url(#b)" {G}/><rect x="659" y="250" width="30" height="80" rx="15" fill="url(#b)" {G}/>
<g fill="{BLOB}"><rect x="290" y="450" width="76" height="62" rx="12"/><rect x="390" y="450" width="76" height="62" rx="12"/><rect x="490" y="450" width="76" height="62" rx="12"/>
<rect x="290" y="545" width="76" height="62" rx="12"/><rect x="390" y="545" width="76" height="62" rx="12"/><rect x="290" y="640" width="76" height="62" rx="12"/></g>
{Jam(700, 650, 100)}
"""

ILUSTRASI['Cucian'] = f"""
<rect x="270" y="270" width="484" height="480" rx="44" fill="url(#b)" {G}/>
<path d="M270 380 H754" stroke="{GARIS}" stroke-width="14"/>
<circle cx="350" cy="325" r="20" fill="{APRICOT}" stroke="{GARIS}" stroke-width="10"/><circle cx="410" cy="325" r="20" fill="{TERANG}" stroke="{GARIS}" stroke-width="10"/>
<rect x="560" y="305" width="150" height="40" rx="14" fill="{TERANG}" stroke="{GARIS}" stroke-width="10"/>
<circle cx="512" cy="570" r="148" fill="{TERANG}" {G}/>
<circle cx="512" cy="570" r="104" fill="url(#a)" stroke="{GARIS}" stroke-width="12"/>
<path d="M425 575 Q470 530 512 575 T599 575" fill="none" stroke="#fff" stroke-width="16" stroke-linecap="round"/>
<circle cx="560" cy="520" r="14" fill="#fff" opacity=".7"/><circle cx="468" cy="620" r="10" fill="#fff" opacity=".7"/>
"""

ILUSTRASI['Servis'] = f"""
<path d="M228 640 L258 520 Q272 468 326 468 H500 Q548 468 580 506 L626 556 L752 566 Q800 572 800 618 V652 H228 Z" fill="url(#a)" {G}/>
<path d="M300 520 H384 V470 H330 Q312 470 306 490 Z M432 470 V520 H548 L506 478 Q496 470 484 470 Z" fill="{TERANG}" stroke="{GARIS}" stroke-width="12" stroke-linejoin="round"/>
<circle cx="350" cy="660" r="66" fill="url(#b)" {G}/><circle cx="350" cy="660" r="26" fill="{TERANG}" stroke="{GARIS}" stroke-width="10"/>
<circle cx="692" cy="660" r="66" fill="url(#b)" {G}/><circle cx="692" cy="660" r="26" fill="{TERANG}" stroke="{GARIS}" stroke-width="10"/>
<circle cx="772" cy="370" r="86" fill="{APRICOT}" {G}/>
<g transform="rotate(40 772 370)"><rect x="754" y="352" width="36" height="120" rx="16" fill="#fff" stroke="{GARIS}" stroke-width="10"/>
<circle cx="772" cy="322" r="46" fill="#fff" stroke="{GARIS}" stroke-width="10"/><rect x="756" y="262" width="32" height="56" rx="6" fill="{APRICOT}"/></g>
"""

if __name__ == '__main__':
    KELUAR.mkdir(parents=True, exist_ok=True)
    for nama, isi in ILUSTRASI.items():
        (KELUAR / f'{nama}.svg').write_text(DASAR.replace('{isi}', isi), encoding='utf-8')
    print(f'{len(ILUSTRASI)} ilustrasi ditulis ke {KELUAR}')
