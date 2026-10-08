import { execFileSync } from 'node:child_process';
import { launch } from './browser.mjs';

// Setup screen review shots (ADR-0026): tips hidden, with the Pro discovery line, narrow, and with the
// cff-addon section numbered 2. Also checks that Start stays disabled until the saved-links box is ticked.
// Run through `CAPTURE=setup-review.mjs OUT=docs/screenshots bash tests/screenshots/run.sh`.
const BASE = process.env.BASE || 'http://localhost:8888';
const OUT = process.argv[2];
const TOOLS = `${BASE}/wp-admin/admin.php?page=culprit-finder`;
const FAKE_KEY = '4f1c9a7e2b6d0c8f3a5e7b9d1f2c4a6e8b0d2f4a6c8e0b2d4f6a8c0e2b4d6f8a';
const cli = (...args) => execFileSync('docker', ['exec', '-w', '/var/www/html', process.env.WPENV_CLI_CONTAINER, 'wp', ...args]).toString().trim();
const written = [];

const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1 });
const open = async (width = 1280) => {
  await page.setViewportSize({ width, height: 800 });
  await page.goto(TOOLS);
  await page.fill('#culprit-finder-problem-url', `${BASE}/checkout/`);
  await page.evaluate((key) => document.querySelectorAll('code').forEach((el) => { el.textContent = el.textContent.replace(/[0-9a-f]{64}/, key); }), FAKE_KEY);
};
const shot = async (name, width = 1280) => {
  await page.mouse.move(width - 1, 799);
  await page.evaluate(() => { document.activeElement && document.activeElement.blur(); return document.fonts && document.fonts.ready; });
  const height = await page.evaluate(() => Math.max(800, document.documentElement.scrollHeight));
  await page.setViewportSize({ width, height });
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${OUT}/${name}.png`, animations: 'disabled', caret: 'hide' });
  await page.setViewportSize({ width, height: 800 });
  written.push(`${name}.png`);
};
const fail = (msg) => { console.error(`FAIL: ${msg}`); process.exitCode = 1; };

await page.goto(`${BASE}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'password');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

cli('user', 'meta', 'update', 'admin', 'culprit_finder_hide_pro_tips', '1');
await open();
const start = page.locator('.cf-start button[type=submit]');
if (!(await start.isDisabled())) fail('Start is enabled before the box is ticked');
await page.check('input[name="culprit_finder_saved"]');
if (await start.isDisabled()) fail('Start is still disabled after ticking the box');
await page.check('input[value="cff-showcase/acme-shop.php"]');
await shot('setup-free');
await open(800);
await page.check('input[name="culprit_finder_saved"]');
await shot('setup-narrow', 800);

cli('user', 'meta', 'delete', 'admin', 'culprit_finder_hide_pro_tips');
await open();
await shot('setup-pro-discovery');
const box = await page.locator('.cf-ready').boundingBox();
const height = await page.evaluate(() => Math.max(800, document.documentElement.scrollHeight));
await page.setViewportSize({ width: 1280, height });
await page.screenshot({ path: `${OUT}/setup-sidebar-closeup.png`, clip: { x: box.x - 12, y: box.y - 12, width: box.width + 24, height: box.height + 24 }, animations: 'disabled', caret: 'hide' });
written.push('setup-sidebar-closeup.png');

cli('user', 'meta', 'update', 'admin', 'culprit_finder_hide_pro_tips', '1');
cli('plugin', 'activate', 'cff-fixtures/cff-addon.php');
await open();
const numbers = await page.locator('.cf-section__num').allTextContents();
if (numbers.join(',') !== '1,2,3') fail(`section numbers with the add-on: ${numbers.join(',')}`);
if (!(await page.locator('.cf-section--addon .cf-section__num').textContent()).includes('2')) fail('add-on section is not number 2');
await page.check('input[name="culprit_finder_saved"]');
await shot('setup-addon');
cli('plugin', 'deactivate', 'cff-fixtures/cff-addon.php');
cli('user', 'meta', 'delete', 'admin', 'culprit_finder_hide_pro_tips');

await browser.close();
console.log(`setup review: ${written.join(', ')} written to ${OUT}`);
