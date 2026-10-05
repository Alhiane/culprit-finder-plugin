---
title: '"There has been a critical error on this website": what it means and how to fix it'
description: What WordPress's critical error message actually means, how to get back into your dashboard, and how to find the plugin or theme behind it.
order: 2
---

You open your site and instead of your pages you see one plain sentence: **"There has been a critical error on this website."** Sometimes it adds "Please check your site admin email inbox for instructions." It looks alarming, but it's one of the more fixable WordPress problems. Your content is almost certainly fine.

## What the message means

WordPress shows this message when PHP, the language WordPress runs on, hits a **fatal error**: code that can't continue. Instead of showing visitors a confusing technical error, WordPress catches it and shows this short message.

The most common causes:

- **A plugin or theme bug**, often right after an update.
- **Two plugins that conflict**, for example both trying to define the same function. Each works alone; together they crash.
- **A PHP version mismatch.** A plugin written for an older PHP version can fail after your host upgrades PHP, or a new plugin may need a newer PHP version than your server runs.
- **Running out of memory**, especially on busy pages or during imports.

The good news: WordPress usually knows which plugin or theme caused the crash.

## Step 1: Check your email for the recovery link

When the error happens, WordPress tries to send an email to the site's admin address. The subject is similar to "Your Site is Experiencing a Technical Issue". It contains:

- the name of the plugin or theme that caused the error,
- the error message itself,
- and a special **recovery mode** link.

Recovery mode lets you log in with the crashing plugin paused for you only, so you can reach your dashboard while visitors still see the error. Click the link, log in, and WordPress will show you which plugin it paused.

Check your spam folder too. If the email never arrives (some hosts can't send email), skip to Step 3.

## Step 2: Fix it from recovery mode

Once you're in:

1. Go to **Plugins**. The plugin that crashed is marked.
2. Check whether an update is available for it. If yes, update it, then exit recovery mode and check your site.
3. If there's no update, deactivate that plugin. Your site should work again for everyone.
4. Contact the plugin's author with the error message from the email, so they can fix it.

Recovery mode only pauses the plugin for your browser. Until you deactivate or update it, visitors still see the error, so don't leave it there.

If you're not sure the paused plugin is really the cause (for example, two plugins clash and WordPress named only one of them), a troubleshooting tool can confirm it. In [Culprit Finder's whole-site-down guide](/docs/using/whole-site-down/) you start a session from recovery mode, exit recovery mode, and let it test the plugins for your browser only.

## Step 3: No email? Get back in another way

Without the recovery email you can still get in:

- **Ask another administrator** whether they received the email.
- **Use your host's tools.** Many hosts offer a one-click way to disable plugins, or a "staging" copy where you can test safely.
- **Rename the plugin's folder.** With FTP or your host's file manager, go to `wp-content/plugins/` and rename the folder of the plugin you suspect, for example from `acme-gallery` to `acme-gallery-off`. WordPress then can't load it and deactivates it. If you don't know which plugin it is, rename the whole `plugins` folder to `plugins-off`, check that the site loads, rename it back, and then turn plugins on one at a time from the Plugins screen.
- **Use WP-CLI** if you have command-line access: `wp plugin deactivate plugin-name`.

## Step 4: Read the error message

Whether from the email or from `wp-content/debug.log` (if debugging is on), the error message tells you a lot. A typical one looks like this:

> PHP Fatal error: Cannot redeclare acme_format_price() (previously declared in wp-content/plugins/acme-invoices/helpers.php:12) in wp-content/plugins/acme-shop/includes/format.php on line 40

Read it like this:

- **The type:** "Cannot redeclare" means two pieces of code define the same function. Here, two plugins clash.
- **The paths:** the folder names after `wp-content/plugins/` tell you which plugins are involved. Here, both "acme-invoices" and "acme-shop".
- **"Allowed memory size exhausted"** means a memory problem: ask your host to raise PHP's memory limit, or look for the plugin that uses so much memory.
- **"Call to undefined function"** often means a plugin needs another plugin that isn't active, or needs a newer PHP or WordPress version.

## Step 5: If it's a theme

If the error path contains `wp-content/themes/` instead of plugins, the theme is the problem. Rename the theme's folder the same way. WordPress falls back to a default theme if one is installed. Then update or reinstall the theme, or contact its author.

## Preventing the next one

- **Update one thing at a time**, and check your site after each update, so you know what changed.
- **Use a staging site** for big updates if your host offers one.
- **Keep a backup** you know how to restore.
- **Watch your PHP version.** Before your host upgrades PHP, check that your plugins support the new version.

The critical error message feels like a disaster, but it's WordPress doing its job: stopping a broken piece of code and telling you where to look. Follow the recovery email, find the plugin, update or replace it, and your site is back.
