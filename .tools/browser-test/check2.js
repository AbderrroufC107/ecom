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
  page.on('console', (msg) => console.log('PAGE LOG:', msg.text()));
  page.on('pageerror', (err) => console.log('PAGE ERROR:', err.message));

  await page.setCookie({ name: 'PHPSESSID', value: sessId, domain: 'localhost', path: '/' });
  await page.goto('http://localhost/ecom/admin/employees.php', { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise((r) => setTimeout(r, 2500));

  // Click the "تعديل" (edit) button on the first row and see if the edit modal opens
  const buttons = await page.$$('button');
  let clicked = false;
  for (const b of buttons) {
    const text = await page.evaluate((el) => el.textContent, b);
    if (text.includes('تعديل')) {
      await b.click();
      clicked = true;
      break;
    }
  }
  console.log('Clicked edit button:', clicked);
  await new Promise((r) => setTimeout(r, 800));

  const modalState = await page.evaluate(() => {
    const modal = document.getElementById('editEmployeeModal');
    if (!modal) return { found: false };
    const style = window.getComputedStyle(modal);
    const nameInput = document.getElementById('editFullName');
    return {
      found: true,
      display: style.display,
      hasInClass: modal.classList.contains('in'),
      nameValue: nameInput ? nameInput.value : null,
    };
  });
  console.log('Edit modal state:', JSON.stringify(modalState, null, 2));

  await page.screenshot({ path: 'employees_edit_modal.png', fullPage: true });

  await browser.close();
})().catch((err) => {
  console.error('ERROR', err);
  process.exit(1);
});
