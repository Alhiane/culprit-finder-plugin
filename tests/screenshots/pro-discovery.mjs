import { execFileSync } from 'node:child_process';
import { launch } from './browser.mjs';

// Pro discovery placements (ADR-0025) for review: full pages plus a close-up of each placement.
// Run through `CAPTURE=pro-discovery.mjs OUT=docs/screenshots bash tests/screenshots/run.sh`.
const BASE = process.env.BASE || 'http://localhost:8888';
const OUT = process.argv[2];
const TOOLS = `${BASE}/wp-admin/admin.php?page=culprit-finder`;
const FAKE_KEY = '4f1c9a7e2b6d0c8f3a5e7b9d1f2c4a6e8b0d2f4a6c8e0b2d4f6a8c0e2b4d6f8a';
const cli = (...args) => execFileSync('docker', ['exec', '-w', '/var/www/html', process.env.WPENV_CLI_CONTAINER, 'wp', ...args]).toString().trim();
const written = [];

const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1 });
const settle = async () => {
  await page.mouse.move(1279, 799);
  await page.evaluate(() => { document.activeElement && document.activeElement.blur(); return document.fonts && document.fonts.ready; });
  await page.evaluate((key) => document.querySelectorAll('code').forEach((el) => { el.textContent = el.textContent.replace(/[0-9a-f]{64}/, key); }), FAKE_KEY);
};
const full = async (name, { tall = true } = {}) => {
  await settle();
  if (tall) {
    const height = await page.evaluate(() => Math.max(800, document.documentElement.scrollHeight));
    await page.setViewportSize({ width: 1280, height });
    await page.waitForTimeout(300);
  }
  await page.screenshot({ path: `${OUT}/${name}.png`, animations: 'disabled', caret: 'hide' });
  await page.setViewportSize({ width: 1280, height: 800 });
  written.push(`${name}.png`);
};
const closeup = async (name, placement, pad = 12, selector = '') => {
  await settle();
  const box = await page.locator(selector || `[data-culprit-finder-pro-tip="${placement}"]`).first().boundingBox();
  const height = await page.evaluate(() => Math.max(800, document.documentElement.scrollHeight));
  await page.setViewportSize({ width: 1280, height });
  await page.screenshot({ path: `${OUT}/${name}.png`, clip: { x: Math.max(0, box.x - pad), y: Math.max(0, box.y - pad), width: box.width + 2 * pad, height: box.height + 2 * pad }, animations: 'disabled', caret: 'hide' });
  await page.setViewportSize({ width: 1280, height: 800 });
  written.push(`${name}.png`);
};

await page.goto(`${BASE}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'password');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

await page.goto(`${TOOLS}&tab=pro`);
await full('1-pro-tab');

const id = JSON.parse(cli('option', 'get', 'culprit_finder_results', '--format=json'))[0].id;
await page.goto(`${TOOLS}&tab=results&result=${id}`);
await full('2-result-detail');
await closeup('2-result-card-closeup', 'result');

await page.goto(TOOLS);
await full('3-setup');
await closeup('3-setup-line-closeup', 'setup');

await page.goto(`${TOOLS}&tab=help`);
await full('4-help');
await closeup('4-help-entry-closeup', 'help');

await page.goto(`${BASE}/wp-admin/plugins.php`);
await full('5-plugins-row', { tall: false });
await closeup('5-plugins-row-closeup', 'plugins_row', 4, 'tr[data-slug="culprit-finder"]');

await page.goto(`${BASE}/wp-admin/index.php`);
await full('6-dashboard-no-tips', { tall: false });

await page.goto(TOOLS);
await Promise.all([page.waitForNavigation(), page.click('[data-culprit-finder-pro-tip="setup"] .cf-tip__hide')]);
await full('7-after-hide-pro-tips-setup');
await page.goto(`${TOOLS}&tab=results&result=${id}`);
await full('7-after-hide-pro-tips-result');
cli('user', 'meta', 'delete', 'admin', 'culprit_finder_hide_pro_tips');

await browser.close();
console.log(`screenshots: ${written.length} written to ${OUT}\n${written.join('\n')}`);
