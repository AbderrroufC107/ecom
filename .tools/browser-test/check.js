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

  await page.setCookie({
    name: 'PHPSESSID',
    value: sessId,
    domain: 'localhost',
    path: '/',
  });

  await page.goto('http://localhost/ecom/admin/employees.php', { waitUntil: 'networkidle0', timeout: 30000 });

  // give React time to mount and scrape the legacy table
  await new Promise((r) => setTimeout(r, 2500));

  await page.screenshot({ path: 'employees_screenshot.png', fullPage: true });

  // Inspect the actions column area for the second employee row for overlap issues
  const diag = await page.evaluate(() => {
    const root = document.getElementById('other-tables-react-root');
    if (!root) return { error: 'no react root found', bodyClass: document.body.className };
    const rows = Array.from(root.querySelectorAll('table tbody tr, [role="row"]'));
    const info = rows.slice(0, 5).map((row) => {
      const rect = row.getBoundingClientRect();
      const cells = Array.from(row.querySelectorAll('td, [role="cell"]')).map((c) => {
        const r = c.getBoundingClientRect();
        return { text: c.textContent.trim().slice(0, 60), top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height };
      });
      return { rowTop: rect.top, rowBottom: rect.bottom, rowHeight: rect.height, cells };
    });
    return { rowCount: rows.length, info };
  });

  console.log(JSON.stringify(diag, null, 2));

  await browser.close();
})().catch((err) => {
  console.error('ERROR', err);
  process.exit(1);
});
