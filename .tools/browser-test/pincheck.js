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
  const info = await page.evaluate(() => {
    const root = document.getElementById('other-tables-react-root');
    return Array.from(root.querySelectorAll('td[data-pinned], th[data-pinned]')).slice(0, 3).map((el) => ({
      tag: el.tagName,
      pinned: el.getAttribute('data-pinned'),
      text: el.textContent.trim().slice(0, 20),
    }));
  });
  console.log(JSON.stringify(info));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
