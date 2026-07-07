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
  page.on('pageerror', (err) => console.log('PAGE ERROR:', err.message));

  await page.setCookie({ name: 'PHPSESSID', value: sessId, domain: 'localhost', path: '/' });
  await page.goto('http://localhost/ecom/admin/employees.php', { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise((r) => setTimeout(r, 2500));

  const found = await page.evaluate(() => {
    const root = document.getElementById('other-tables-react-root');
    if (!root) return { ok: false, reason: 'no react root' };
    const btns = Array.from(root.querySelectorAll('a, button')).filter((b) => b.textContent.trim() === 'تعديل');
    if (!btns.length) return { ok: false, reason: 'no edit button found', allTexts: Array.from(root.querySelectorAll('a, button')).map(b=>b.textContent.trim()).filter(Boolean) };
    const b = btns[0];
    const rect = b.getBoundingClientRect();
    b.setAttribute('data-test-edit-target', '1');
    return { ok: true, rect: { x: rect.x, y: rect.y, w: rect.width, h: rect.height }, visible: rect.width > 0 && rect.height > 0 };
  });
  console.log('Find edit button result:', JSON.stringify(found));

  if (found.ok && found.visible) {
    const cx = found.rect.x + found.rect.w / 2;
    const cy = found.rect.y + found.rect.h / 2;
    await page.mouse.click(cx, cy);
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
    console.log('Edit modal state after click:', JSON.stringify(modalState, null, 2));
    await page.screenshot({ path: 'employees_edit_modal.png', fullPage: true });
  }

  await browser.close();
})().catch((err) => {
  console.error('ERROR', err);
  process.exit(1);
});
