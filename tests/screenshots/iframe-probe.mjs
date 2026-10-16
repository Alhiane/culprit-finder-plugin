// E2E helper (tests/e2e/scenarios/requests.sh): open the probe's frame host page in a real browser with the
// cookies of a curl jar (or none, for "-") and print what the same-site iframe reports.
// Usage: node iframe-probe.mjs <base-url> <curl-jar|->
import { readFileSync } from 'node:fs';
import { launch } from './browser.mjs';

const [base, jar] = process.argv.slice(2);
const cookies = [];
if (jar && jar !== '-') {
  for (const raw of readFileSync(jar, 'utf8').split('\n')) {
    const httpOnly = raw.startsWith('#HttpOnly_');
    const line = httpOnly ? raw.slice('#HttpOnly_'.length) : raw;
    if (!line || line.startsWith('#')) continue;
    const [domain, , path, secure, , name, value] = line.split('\t');
    if (!name) continue;
    cookies.push({ name, value: value || '', domain, path, secure: secure === 'TRUE', httpOnly, sameSite: 'Lax' });
  }
}

const browser = await launch();
try {
  const context = await browser.newContext();
  if (cookies.length) await context.addCookies(cookies);
  const page = await context.newPage();
  await page.goto(`${base}/?cff_probe_frame=1`);
  const frame = await (await page.waitForSelector('#cff-frame')).contentFrame();
  await frame.waitForSelector('#cff-probe');
  process.stdout.write((await frame.textContent('#cff-probe')).trim() + '\n');
} finally {
  await browser.close();
}
