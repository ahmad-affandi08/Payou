// Ekspor SVG ilustrasi ke PNG transparan 1024 px dengan Chromium. Pakai: NODE_PATH=<node_modules> node RenderIlustrasi.cjs <berkas.svg...>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM ?? '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: 1024, height: 1024 } });
  for (const berkas of process.argv.slice(2)) {
    await p.setContent(`<html><body style="margin:0;background:transparent">${fs.readFileSync(berkas, 'utf8')}</body></html>`);
    await p.screenshot({ path: berkas.replace(/\.svg$/, '.png'), omitBackground: true });
    console.log(path.basename(berkas));
  }
  await b.close();
})();
