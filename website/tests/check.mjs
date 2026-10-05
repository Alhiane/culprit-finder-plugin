// Website checks over the built site (run after `astro build`):
//   every page returns 200, has exactly one <h1>, logs no console errors, makes no external requests,
//   and has zero axe (WCAG 2.2 AA) violations; zero broken internal links; landing page JS < 30 KB;
//   and no forbidden terms (../tests/release/forbidden-terms.sh).
import { createServer } from 'node:http';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { extname, join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { AxeBuilder } from '@axe-core/playwright';

const site = resolve(fileURLToPath(import.meta.url), '../..');
const dist = join(site, 'dist');
const JS_BUDGET = 30 * 1024;
const failures = [];
const fail = (msg) => failures.push(msg);

const types = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.webp': 'image/webp', '.ico': 'image/x-icon', '.xml': 'application/xml', '.txt': 'text/plain', '.json': 'application/json', '.wasm': 'application/wasm' };
function fileFor(urlPath) {
	const clean = decodeURIComponent(urlPath.split(/[?#]/)[0]);
	const candidate = join(dist, clean);
	if (existsSync(candidate) && statSync(candidate).isFile()) return candidate;
	if (existsSync(join(candidate, 'index.html'))) return join(candidate, 'index.html');
	return null;
}
const server = createServer((req, res) => {
	const file = fileFor(req.url);
	if (!file) {
		res.writeHead(404, { 'content-type': 'text/html' });
		res.end(existsSync(join(dist, '404.html')) ? readFileSync(join(dist, '404.html')) : 'Not found');
		return;
	}
	res.writeHead(200, { 'content-type': types[extname(file)] || 'application/octet-stream' });
	res.end(readFileSync(file));
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const origin = `http://127.0.0.1:${server.address().port}`;

const walk = (dir) => readdirSync(dir).flatMap((f) => {
	const p = join(dir, f);
	return statSync(p).isDirectory() ? walk(p) : [p];
});
const htmlFiles = walk(dist).filter((f) => f.endsWith('.html') && !f.includes('/pagefind/'));
const pages = htmlFiles
	.filter((f) => !f.endsWith('/404.html'))
	.map((f) => f.slice(dist.length).replace(/index\.html$/, ''))
	.sort();

// Internal links and external assets, from the HTML.
for (const file of htmlFiles) {
	const html = readFileSync(file, 'utf8');
	const page = file.slice(dist.length);
	for (const [, attr, url] of html.matchAll(/\s(href|src|srcset)="([^"]+)"/g)) {
		for (const u of attr === 'srcset' ? url.split(',').map((s) => s.trim().split(/\s+/)[0]) : [url]) {
			if (/^(mailto:|tel:|#|data:|javascript:)/.test(u)) continue;
			if (/^https?:\/\//.test(u)) {
				if (u.startsWith('https://getculpritfinder.com')) continue;
				if (attr !== 'href') fail(`${page}: external asset ${u}`);
				continue;
			}
			const target = u.startsWith('/') ? u : new URL(u, `http://x${page}`).pathname;
			if (!fileFor(target)) fail(`${page}: broken link ${u}`);
		}
	}
	for (const [, tag] of html.matchAll(/<link\b([^>]*rel="(?:stylesheet|preload|modulepreload|icon)"[^>]*)>/g)) {
		const href = (tag.match(/href="([^"]+)"/) || [])[1] || '';
		if (/^https?:\/\//.test(href) && !href.startsWith('https://getculpritfinder.com')) fail(`${page}: external <link> ${href}`);
	}
}

const browser = await (async () => {
	try { return await chromium.launch({ channel: 'chrome' }); } catch { return chromium.launch(); }
})();
const context = await browser.newContext();
let homeJs = 0;
for (const path of pages) {
	const p = await context.newPage();
	const errors = [];
	p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
	p.on('pageerror', (e) => errors.push(e.message));
	p.on('request', (r) => {
		const url = new URL(r.url());
		if (url.origin !== origin && !r.url().startsWith('data:')) fail(`${path}: external request ${r.url()}`);
		if (path === '/' && r.resourceType() === 'script' && url.origin === origin) {
			const f = fileFor(url.pathname);
			if (f) homeJs += statSync(f).size;
		}
	});
	const res = await p.goto(origin + path, { waitUntil: 'networkidle' });
	if (!res || res.status() !== 200) fail(`${path}: HTTP ${res && res.status()}`);
	const h1 = await p.locator('h1').count();
	if (h1 !== 1) fail(`${path}: ${h1} <h1> elements`);
	const axe = await new AxeBuilder({ page: p }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
	for (const v of axe.violations) fail(`${path}: axe ${v.id} (${v.nodes.length}): ${v.help}`);
	for (const e of errors) fail(`${path}: console error ${e}`);
	await p.close();
}
await browser.close();
server.close();
// Inline scripts count too.
const homeHtml = readFileSync(join(dist, 'index.html'), 'utf8');
homeJs += [...homeHtml.matchAll(/<script(?![^>]*application\/ld\+json)[^>]*>([\s\S]*?)<\/script>/g)].reduce((n, m) => n + m[1].length, 0);
if (homeJs > JS_BUDGET) fail(`/: landing page JS ${homeJs} bytes > ${JS_BUDGET}`);

try {
	execFileSync('bash', [join(site, '../tests/release/forbidden-terms.sh')], { stdio: 'inherit' });
} catch {
	fail('forbidden terms found');
}

console.log(`website-check: ${pages.length} pages, ${htmlFiles.length} HTML files scanned for links, landing JS ${homeJs} bytes`);
if (failures.length) {
	console.error(`website-check: ${failures.length} problem(s):\n- ` + [...new Set(failures)].join('\n- '));
	process.exit(1);
}
console.log('website-check: OK');
