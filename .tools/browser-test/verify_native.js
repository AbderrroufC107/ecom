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
  page.on('dialog', async (d) => { console.log('DIALOG:', d.message().slice(0, 40)); await d.accept(); });

  await page.setCookie({ name: 'PHPSESSID', value: sessId, domain: 'localhost', path: '/' });
  await page.goto('http://localhost/ecom/admin/employees.php', { waitUntil: 'networkidle0', timeout: 30000 });
  await new Promise((r) => setTimeout(r, 2000));

  // 1. Edit button -> modal
  const editTarget = await page.evaluate(() => {
    const b = Array.from(document.querySelectorAll('.emp-btn.b-edit'))[0];
    if (!b) return null;
    const r = b.getBoundingClientRect();
    return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
  });
  await page.mouse.click(editTarget.x, editTarget.y);
  await new Promise((r) => setTimeout(r, 600));
  const modal = await page.evaluate(() => {
    const m = document.getElementById('editEmployeeModal');
    return { display: getComputedStyle(m).display, name: document.getElementById('editFullName').value, weight: document.getElementById('editAssignmentWeight').value };
  });
  console.log('EDIT MODAL:', JSON.stringify(modal));

  // close modal
  await page.evaluate(() => { const b = document.querySelector('#editEmployeeModal .js-dismiss-employee-modal'); if (b) b.click(); });
  await new Promise((r) => setTimeout(r, 500));

  // 2. Toggle disable on row 2
  const statusBefore = await page.evaluate(() => Array.from(document.querySelectorAll('.emp-tbl tbody tr')).map((tr) => tr.querySelector('.emp-statusbox .emp-badge').textContent.trim()));
  console.log('STATUS BEFORE:', JSON.stringify(statusBefore));

  const offTarget = await page.evaluate(() => {
    const rows = document.querySelectorAll('.emp-tbl tbody tr');
    const b = rows[1] ? rows[1].querySelector('.emp-btn.b-off') : null;
    if (!b) return null;
    const r = b.getBoundingClientRect();
    return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
  });
  if (offTarget) {
    await page.mouse.click(offTarget.x, offTarget.y);
    await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 15000 }).catch(() => {});
    await new Promise((r) => setTimeout(r, 1500));
    const statusAfter = await page.evaluate(() => Array.from(document.querySelectorAll('.emp-tbl tbody tr')).map((tr) => tr.querySelector('.emp-statusbox .emp-badge').textContent.trim()));
    console.log('STATUS AFTER DISABLE:', JSON.stringify(statusAfter));

    // restore
    const onTarget = await page.evaluate(() => {
      const rows = document.querySelectorAll('.emp-tbl tbody tr');
      const b = rows[1] ? rows[1].querySelector('.emp-btn.b-on') : null;
      if (!b) return null;
      const r = b.getBoundingClientRect();
      return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
    });
    if (onTarget) {
      await page.mouse.click(onTarget.x, onTarget.y);
      await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 15000 }).catch(() => {});
      await new Promise((r) => setTimeout(r, 1000));
      const statusRestored = await page.evaluate(() => Array.from(document.querySelectorAll('.emp-tbl tbody tr')).map((tr) => tr.querySelector('.emp-statusbox .emp-badge').textContent.trim()));
      console.log('STATUS RESTORED:', JSON.stringify(statusRestored));
    } else {
      console.log('ERROR: no enable button found to restore');
    }
  }

  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
