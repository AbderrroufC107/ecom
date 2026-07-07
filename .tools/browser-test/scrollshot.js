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
  await page.setCacheEnabled(false);
  await page.setCookie({ name: 'PHPSESSID', value: sessId, domain: 'localhost', path: '/' });
  await page.goto('http://localhost/ecom/admin/employees.php', { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise((r) => setTimeout(r, 2500));

  // Scroll the datatable horizontally to reveal the left-side (stats/date) columns
  await page.evaluate(() => {
    const root = document.getElementById('other-tables-react-root');
    const vp = root.querySelector('.mantine-ScrollArea-viewport');
    if (vp) vp.scrollLeft = -400; // RTL negative scroll
  });
  await new Promise((r) => setTimeout(r, 600));

  const tableEl = await page.$('#other-tables-react-root');
  if (tableEl) await tableEl.screenshot({ path: 'shot_scrolled.png' });
  console.log('done');
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
