const puppeteer = require('puppeteer-core');

async function getStatusBadges(page) {
  return page.evaluate(() => {
    const root = document.getElementById('other-tables-react-root');
    const cells = Array.from(root.querySelectorAll('[role="row"], tr')).map((r) => r.textContent.trim().slice(0, 200));
    return cells;
  });
}

(async () => {
  const sessId = process.argv[2];
  const browser = await puppeteer.launch({
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    headless: 'new',
    args: ['--window-size=1600,1000'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1600, height: 1000 });

  page.on('dialog', async (dialog) => {
    console.log('DIALOG:', dialog.message());
    await dialog.accept();
  });

  await page.setCookie({ name: 'PHPSESSID', value: sessId, domain: 'localhost', path: '/' });
  await page.goto('http://localhost/ecom/admin/employees.php', { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise((r) => setTimeout(r, 2000));

  console.log('BEFORE:', JSON.stringify(await getStatusBadges(page)));

  // Click the second employee's "تعطيل" button (row for id=2, the one we've been testing with)
  const target = await page.evaluate(() => {
    const root = document.getElementById('other-tables-react-root');
    const links = Array.from(root.querySelectorAll('a')).filter((a) => a.textContent.trim() === 'تعطيل');
    if (!links.length) return null;
    const rect = links[0].getBoundingClientRect();
    return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 };
  });
  console.log('Toggle target:', JSON.stringify(target));
  if (target) {
    await page.mouse.click(target.x, target.y);
    await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 15000 }).catch((e) => console.log('nav wait error', e.message));
    await new Promise((r) => setTimeout(r, 2000));
    console.log('AFTER TOGGLE:', JSON.stringify(await getStatusBadges(page)));

    // Toggle back to restore original state
    const target2 = await page.evaluate(() => {
      const root = document.getElementById('other-tables-react-root');
      const links = Array.from(root.querySelectorAll('a')).filter((a) => a.textContent.trim() === 'تفعيل');
      if (!links.length) return null;
      const rect = links[0].getBoundingClientRect();
      return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 };
    });
    if (target2) {
      await page.mouse.click(target2.x, target2.y);
      await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 15000 }).catch((e) => console.log('nav wait error', e.message));
      await new Promise((r) => setTimeout(r, 2000));
      console.log('AFTER RESTORE:', JSON.stringify(await getStatusBadges(page)));
    } else {
      console.log('Could not find تفعيل link to restore state!');
    }
  }

  await browser.close();
})().catch((err) => {
  console.error('ERROR', err);
  process.exit(1);
});
