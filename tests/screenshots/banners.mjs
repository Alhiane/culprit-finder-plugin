import { existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { launch } from './browser.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '../..');
const template = pathToFileURL(resolve(here, 'banner.html')).href;
// The og-image belongs to the website repository; write it there when it's checked out next to this one.
const websiteDir = process.env.WEBSITE_DIR || resolve(root, '../culprit-finder-website');
const ogImage = existsSync(resolve(websiteDir, 'astro.config.mjs')) ? resolve(websiteDir, 'public/og-image.png') : resolve(root, 'build/og-image.png');
const outputs = [
  { w: 1544, h: 500, pad: 40, file: '.wordpress-org/banner-1544x500.png' },
  { w: 772, h: 250, pad: 20, file: '.wordpress-org/banner-772x250.png' },
  { w: 1200, h: 630, pad: 60, file: ogImage },
];

const browser = await launch();
for (const o of outputs) {
  const page = await browser.newPage({ viewport: { width: o.w, height: o.h }, deviceScaleFactor: 1 });
  await page.goto(`${template}?w=${o.w}&h=${o.h}&pad=${o.pad}`);
  await page.waitForLoadState('networkidle');
  const out = resolve(root, o.file);
  mkdirSync(dirname(out), { recursive: true });
  await page.screenshot({ path: out });
  await page.close();
  console.log(`banner: ${o.file}`);
}
await browser.close();
