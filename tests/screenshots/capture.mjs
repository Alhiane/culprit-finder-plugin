import { execFileSync } from 'node:child_process';
import { launch } from './browser.mjs';

const BASE = process.env.BASE || 'http://localhost:8888';
const OUT = process.argv[2];
const TOOLS = `${BASE}/wp-admin/admin.php?page=culprit-finder`;
const FAKE_KEY = '4f1c9a7e2b6d0c8f3a5e7b9d1f2c4a6e8b0d2f4a6c8e0b2d4f6a8c0e2b4d6f8a';
const cli = (...args) => execFileSync('docker', ['exec', '-w', '/var/www/html', process.env.WPENV_CLI_CONTAINER, 'wp', ...args]).toString().trim();

const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1 });
// Tall pages: grow the viewport to the page height (a full-page capture would repeat the fixed toolbar).
const shot = async (n, { tall = false, keepPointer = false } = {}) => {
  if (!keepPointer) await page.mouse.move(1279, 799);
  await page.evaluate(() => { document.activeElement && document.activeElement.blur(); return document.fonts && document.fonts.ready; });
  if (tall) {
    const height = await page.evaluate(() => Math.max(800, document.documentElement.scrollHeight));
    await page.setViewportSize({ width: 1280, height });
    await page.evaluate(() => { window.scrollTo(0, 0); return new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))); });
    await page.waitForTimeout(300);
  }
  await page.screenshot({ path: `${OUT}/screenshot-${n}.png`, animations: 'disabled', caret: 'hide' });
  await page.setViewportSize({ width: 1280, height: 800 });
};
const fixKey = () => page.evaluate((key) => {
  document.querySelectorAll('code').forEach((el) => { el.textContent = el.textContent.replace(/[0-9a-f]{64}/, key); });
  document.querySelectorAll('input[name="culprit_finder_recovery"]').forEach((el) => { el.value = key; });
}, FAKE_KEY);

await page.goto(`${BASE}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'password');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

// 1. Setup.
await page.goto(TOOLS);
await page.fill('#culprit-finder-problem-url', `${BASE}/checkout/`);
await page.check('input[value="cff-showcase/acme-shop.php"]');
await page.check('input[name="culprit_finder_saved"]');
await fixKey();
await shot(1, { tall: true });

// 2. A step (right after Start, so the time left is stable).
await Promise.all([page.waitForNavigation(), page.click('.cf-start button[type=submit]')]);
await page.goto(`${TOOLS}&culprit_safe=1`);
await page.click('.cf-details summary');
await shot(2, { tall: true });

// 3. Front end with the toolbar menu open.
await page.goto(`${BASE}/sample-page/`);
await page.hover('#wp-admin-bar-culprit-finder');
await page.waitForTimeout(300);
await shot(3, { keepPointer: true });
cli('culprit-finder', 'exit');

// 4. Result detail (the newest seeded result: Acme Invoices).
const id = JSON.parse(cli('option', 'get', 'culprit_finder_results', '--format=json'))[0].id;
await page.goto(`${TOOLS}&tab=results&result=${id}`);
await shot(4, { tall: true });

// 5. Results list.
await page.goto(`${TOOLS}&tab=results`);
await shot(5);

// 6. Help.
await page.goto(`${TOOLS}&tab=help`);
await shot(6, { tall: true });

await browser.close();
console.log(`screenshots: 6 written to ${OUT}`);
