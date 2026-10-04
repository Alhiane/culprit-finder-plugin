import { chromium } from 'playwright-core';

// Use the installed Chrome when available (no download); otherwise Playwright's Chromium (npx playwright install chromium).
export async function launch() {
  try {
    return await chromium.launch({ channel: 'chrome' });
  } catch {
    return await chromium.launch();
  }
}
