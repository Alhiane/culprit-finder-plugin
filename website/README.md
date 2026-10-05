# getculpritfinder.com

The Culprit Finder website: landing page, guides and documentation. Astro (static output) with Starlight for `/docs`. No runtime external requests, no cookies, no analytics.

## Develop

```sh
make website-dev    # http://localhost:4321 (runs the sync step first)
make website        # build to website/dist/
make website-test   # astro check + build + page/link/accessibility/privacy checks
cd website && npm run lighthouse   # optional: Lighthouse (mobile) on key pages, budget 95
```

`scripts/sync.mjs` runs before `dev` and `build`. It copies the plugin screenshots from `../.wordpress-org/`, and generates three docs pages from the plugin repo: the Hooks reference (from `../docs/hooks.md` when present; the committed copy is kept otherwise), the WP-CLI command (from `../src/CLI/Command.php` docblocks) and the Changelog (from `../readme.txt`). Edit those sources, not the generated pages.

Site-wide settings (URLs, emails, download link) live in `src/config.ts`.

## Deploy

The site is static: build command `npm run build` (in `website/`), output directory `website/dist`, Node 22.

**Cloudflare Pages**
1. Workers & Pages → Create → Pages → connect the GitHub repository.
2. Root directory: `website`. Build command: `npm run build`. Output: `dist`. Environment variable `NODE_VERSION=22`.
3. Custom domains → add `getculpritfinder.com` and `www.getculpritfinder.com`; add a redirect rule www → apex (301).

**Netlify**
1. Add new site → import the repository.
2. Base directory `website`, build command `npm run build`, publish directory `website/dist`, `NODE_VERSION=22`.
3. Domain management → add `getculpritfinder.com` as the primary domain; Netlify redirects `www` to it automatically.

**GitHub Pages**
1. Add a workflow that runs `npm ci && npm run build` in `website/` and deploys `website/dist` with `actions/upload-pages-artifact` + `actions/deploy-pages`.
2. Settings → Pages → Source: GitHub Actions. Custom domain: `getculpritfinder.com`, enforce HTTPS.
3. DNS: apex A/AAAA records to GitHub Pages, `www` CNAME to `<owner>.github.io` (GitHub redirects www to the apex).

## Domains

- **Main domain:** `https://getculpritfinder.com` (no www). Docs live at `https://getculpritfinder.com/docs/`. Canonical URLs, the sitemap and Open Graph tags all use this domain.
- **www:** `www.getculpritfinder.com` redirects (301) to `https://getculpritfinder.com`, set up in the hosting provider or DNS.
- **getculpritfinder.pro:** a 301 redirect to `https://getculpritfinder.com`, set up at the registrar/DNS level. It is not referenced anywhere in this repository's published content, and nothing here configures it.
