---
title: Privacy and data
description: What Culprit Finder stores on your site, and what it never sends anywhere.
---

**Culprit Finder makes no external requests and has no tracking or telemetry.** Everything stays in your own WordPress database.

## What it stores

| Data | Where | When it's removed |
|---|---|---|
| The running session: which plugins were active, your answers, the plugins you kept on, and secure hashes of the session token and emergency exit key | One database option | When the session ends |
| Your last 10 results, with the plugin names and versions involved and your WordPress, PHP and theme versions | One database option | Delete, Clear all, or uninstalling |
| A note if the helper file couldn't be installed | One database option | When it installs, or uninstalling |
| A browser cookie holding the session token | Your browser only | When the session ends or expires |

The session token and emergency exit key are only ever stored as hashes, never in plain text.

## The support report

The report contains no site address, user names, email addresses or IP addresses, and doesn't list all your plugins, only the ones involved and the ones you kept on. You decide whether and where to share it.

## Visitors

Visitors' requests are never changed or recorded by Culprit Finder.
