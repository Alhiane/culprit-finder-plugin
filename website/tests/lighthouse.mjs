// Lighthouse (mobile) over key pages of the built site; fails below 95 in any category.
// Uses the installed Chrome (CHROME_PATH) and `npx lighthouse`. Writes JSON reports to lighthouse/.
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';

const PORT = 4329;
const BUDGET = 95;
const pages = ['/', '/how-it-works/', '/docs/', '/docs/getting-started/first-run/', '/guides/find-which-plugin-is-breaking-your-site/'];
const chrome = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
mkdirSync('lighthouse', { recursive: true });

// Astro 7 runs `astro preview` as a managed background server: start it, and stop it with `astro preview stop`.
spawn('npx', ['astro', 'preview', '--port', String(PORT), '--host', '127.0.0.1'], { stdio: 'ignore' });
await new Promise((r) => setTimeout(r, 4000));
let bad = 0;
try {
	for (const path of pages) {
		const out = `lighthouse/${path.replace(/\//g, '_') || 'home'}.json`;
		execFileSync('npx', ['--yes', 'lighthouse@13', `http://127.0.0.1:${PORT}${path}`, '--quiet', '--output=json', `--output-path=${out}`,
			'--chrome-flags=--headless=new --no-sandbox', '--only-categories=performance,accessibility,best-practices,seo'],
			{ env: { ...process.env, CHROME_PATH: chrome }, stdio: ['ignore', 'ignore', 'inherit'] });
		const report = JSON.parse(readFileSync(out, 'utf8'));
		const scores = Object.fromEntries(Object.entries(report.categories).map(([k, v]) => [k, Math.round(v.score * 100)]));
		const low = Object.entries(scores).filter(([, s]) => s < BUDGET);
		bad += low.length;
		console.log(`${path.padEnd(52)} ${Object.entries(scores).map(([k, s]) => `${k} ${s}`).join(' · ')}${low.length ? '  <-- below ' + BUDGET : ''}`);
	}
} finally {
	execFileSync('npx', ['astro', 'preview', 'stop'], { stdio: 'ignore' });
}
process.exit(bad ? 1 : 0);
