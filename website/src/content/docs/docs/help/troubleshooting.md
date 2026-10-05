---
title: Troubleshooting
description: Fixes for common Culprit Finder problems, from an unwritable mu-plugins folder to page caching.
---

## "The Culprit Finder loader is missing or out of date"

Culprit Finder couldn't copy its helper file into `wp-content/mu-plugins/`, usually because the folder isn't writable. Start stays disabled until it's there. To fix it:

1. With FTP or your host's file manager, create `wp-content/mu-plugins/` if it doesn't exist.
2. Copy `wp-content/plugins/culprit-finder/mu-loader/culprit-finder-loader.php` into it.
3. Reload the Culprit Finder page.

Or ask your host to make the folder writable for WordPress.

## Page caching

Culprit Finder only switches plugins off for logged-in administrators with its session cookie, and marks those pages as not cacheable. Most caching plugins and hosts never cache pages for logged-in users anyway, so visitors keep getting the normal, full pages.

A server-level cache that ignores login cookies could in theory store a page meant only for you. If you use one, check with your host that logged-in pages aren't cached, or exclude requests with the `wp-culprit-finder` cookie.

## Multisite isn't supported

On a multisite network, Culprit Finder shows a notice and won't start, and it can't be network-activated. Support may come later.

## "Your Site is Experiencing a Technical Issue" emails

When a step makes a page crash, WordPress may email you about it. That's expected while troubleshooting: the crash only happened in your browser, with some plugins off. You can ignore these emails during a session.

## Plugin management is paused

While your session runs, the Plugins screen shows a notice and won't let you activate or deactivate plugins. The list you see there is the shortened list for your browser, and saving it would change your real site. Exit first, then manage plugins as usual.

## The step counter changed

"Step 3 of about 7" is an estimate. If the search needs to look for a second plugin, the total grows a little.

## Still stuck?

See the [FAQ](/docs/help/faq/), or [contact us](/contact/) with your support report.
