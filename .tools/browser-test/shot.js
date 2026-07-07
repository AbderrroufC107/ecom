const puppeteer = require('puppeteer-core');

(async () => {
  const sessId = process.argv[2];
  const browser = await puppeteer.launch({
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    headless: 'new',
    args: ['--window-size=1600,1000'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1600, height: 1000 });

  // hard-disable cache
  await page.setCacheEnabled(false);

  await page.setCookie({ name: 'PHPSESSID', value: sessId, domain: 'localhost', path: '/' });
  await page.goto('http://localhost/ecom/admin/employees.php', { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise((r) => setTimeout(r, 3000));

  // Screenshot just the table region, zoomed to where the overlap complaint is
  await page.screenshot({ path: 'shot_full.png', fullPage: false });

  // Also capture a tight shot of the table
  const tableEl = await page.$('#other-tables-react-root');
  if (tableEl) {
    await tableEl.screenshot({ path: 'shot_table.png' });
  }

  // Diagnose the pinned actions column vs stats column overlap precisely
  const diag = await page.evaluate(() => {
    const root = document.getElementById('other-tables-react-root');
    if (!root) return { error: 'no root' };
    // find the scroll container
    const scroller = root.querySelector('.mantine-datatable-scroll-area, .mantine-ScrollArea-viewport, [class*="scrollArea"]');
    const rows = Array.from(root.querySelectorAll('tbody tr'));
    const result = rows.slice(0, 2).map((row) => {
      return Array.from(row.querySelectorAll('td')).map((td) => {
        const r = td.getBoundingClientRect();
        const cs = window.getComputedStyle(td);
        return {
          text: td.textContent.trim().replace(/\s+/g, ' ').slice(0, 40),
          left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width),
          position: cs.position, zIndex: cs.zIndex, bg: cs.backgroundColor,
        };
      });
    });
    return { scrollLeft: scroller ? scroller.scrollLeft : 'no scroller', scrollWidth: scroller ? scroller.scrollWidth : null, clientWidth: scroller ? scroller.clientWidth : null, rows: result };
  });
  console.log(JSON.stringify(diag, null, 2));

  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
