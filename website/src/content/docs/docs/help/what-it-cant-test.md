---
title: What it can't test
description: The parts of a WordPress site Culprit Finder can't switch off, and what to check instead.
---

Culprit Finder switches regular plugins off for your browser. Some things load no matter which plugins are active, so it can't test them:

- **Your theme.** If the problem is in the theme, it stays with every plugin off. Test by switching to a default theme on a staging copy.
- **Must-use plugins** in `wp-content/mu-plugins/`. They always load. Your host may have added some.
- **Drop-ins** such as `object-cache.php`, `advanced-cache.php` or `db.php` in `wp-content/`. They replace parts of WordPress itself.
- **Server settings**: the PHP version, memory limits, server rules and firewalls.
- **Plugins you kept on.** You chose to keep them on, so they're never suspected.
- **Multisite networks** aren't supported yet.

If the problem stays with every tested plugin off, the result is "It's not one of the plugins tested". That's useful too: it tells you to look at the theme, must-use plugins, drop-ins or the server instead.

## Problems that come and go

The search assumes the problem shows up reliably when the responsible plugins are on. If it only happens sometimes, or only for some visitors, your answers may contradict each other. Culprit Finder then says the answers didn't add up instead of naming the wrong plugin.
