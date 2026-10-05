---
title: When the whole site is down
description: Use WordPress recovery mode to start Culprit Finder when even wp-admin shows a critical error.
---

Culprit Finder needs your dashboard to start. If a plugin already crashes **every** page, including wp-admin, you can still use it through WordPress's own **recovery mode**.

## Step by step

1. When a plugin crashes the whole site, WordPress emails the site's admin address a recovery mode link ("Your Site is Experiencing a Technical Issue"). Open it and log in. WordPress pauses the plugin that crashed, for you only, so wp-admin loads again.
2. Open **Culprit Finder**. A notice says you're in recovery mode. Bookmark the two safety links and press **Start troubleshooting**.
3. **Exit recovery mode straight away**, using the link in the notice or **Exit Recovery Mode** in the toolbar.
4. Answer the steps as usual. Culprit Finder now keeps your site usable for you, and your bookmarked control panel always works.

## Why step 3 matters

While recovery mode is on, WordPress keeps the crashed plugin paused in your browser. That plugin then never loads in any step, so your answers wouldn't reflect it and the result would be wrong. Culprit Finder warns you on its page until you exit recovery mode.

## No recovery email?

Some hosts can't send email. Then:

- ask another administrator whether they got the email,
- use your host's tools to disable plugins,
- use WP-CLI: `wp plugin deactivate plugin-name`,
- or rename the plugin's folder in `wp-content/plugins/` with FTP or your host's file manager.

WordPress's email usually names the plugin that crashed. Culprit Finder is most useful when that name isn't the whole story, for example when two plugins only fail together.
