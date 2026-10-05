---
title: FAQ
description: Short answers to common questions about Culprit Finder.
---

## Will my visitors notice anything?

No. Plugins are switched off only for your own browser while you're logged in. Visitors and other administrators always get every plugin, and pages filtered for you are marked as not cacheable.

## Does it deactivate my plugins?

No. Your real list of active plugins never changes. Culprit Finder only decides which plugins load for your browser during a session. It even blocks other plugins from changing that list while you troubleshoot.

## How many questions will it ask?

About 7 for 30 plugins, because each answer halves the list. Two plugins that only fail together take a few more.

## A step shows "There has been a critical error". What now?

Answer **Yes**: the plugins on in that step are causing an error. If the page with the buttons won't load, open your bookmarked [control panel](/docs/using/safety-links/). WordPress may email you about the error; that's expected during troubleshooting.

## My whole site is down, including wp-admin. Can I still use it?

Yes, through WordPress recovery mode. See [When the whole site is down](/docs/using/whole-site-down/).

## How do I get out if everything is broken?

Press Exit, open your emergency exit link (it works logged out), wait 60 minutes, deactivate the plugin, clear your cookies, or delete `wp-content/mu-plugins/culprit-finder-loader.php` with FTP. See [Your two safety links](/docs/using/safety-links/).

## Does any data leave my site?

No. No external requests, no tracking. Results stay on your site. See [Privacy and data](/docs/help/privacy-and-data/).

## What can't it test?

Your theme, must-use plugins, drop-ins and server settings. See [What it can't test](/docs/help/what-it-cant-test/).

## Can I manage plugins during troubleshooting?

No. The Plugins screen is paused for you while a session runs, so the shortened list you see can't be saved by accident. Exit first.

## Can other administrators use the site normally?

Yes. Only the browser that started the session is affected. Another administrator who opens Culprit Finder sees that a session is running elsewhere and can end it.

## Does it work on multisite?

Not yet. It refuses to start on multisite networks.

## Is it really free?

Yes. Culprit Finder is free and open source, licensed under the GPL.
