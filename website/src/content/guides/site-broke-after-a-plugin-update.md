---
title: 'Your site broke after a plugin update: a calm checklist'
description: A step-by-step checklist for when a WordPress plugin update breaks something, from the first five minutes to fixing it for good.
order: 3
---

You updated a few plugins, and now something is wrong. Maybe a page looks broken, a feature stopped working, or the whole site shows an error. Take a breath. Plugin updates rarely destroy anything; they usually just change code that now misbehaves. This checklist takes you from "it's broken" to "it's fixed" in order.

## The first five minutes

**1. Note what you updated.** Go to Dashboard → Updates or the Plugins screen and write down which plugins you just updated, and to which versions. If you updated several at once, list them all. If you can't get into your dashboard, check your email: WordPress may have sent a "technical issue" message naming the plugin (see [our guide to the critical error message](/guides/there-has-been-a-critical-error/)).

**2. Describe the problem precisely.** Write one sentence you can check quickly, and note the page address. "The booking calendar on /book/ shows no dates" is much easier to test than "bookings are broken".

**3. Clear caches.** Clear your caching plugin, your host's cache and your browser cache, then look again. After updates, an old cached stylesheet or script often makes a page look broken when it isn't.

**4. Decide how urgent it is.** If customers can't buy or log in, fix it fast, even with a temporary measure. If it's a small layout issue, you have time to find the real cause.

## Find which update caused it

If you updated only one plugin, you probably already know the culprit. Skip to "Fix it".

If you updated several, you need to know which one. Some ways to find out:

- **Check the error.** If there's an error message or a line in `wp-content/debug.log`, the folder name after `wp-content/plugins/` usually names the plugin.
- **Read the changelogs.** Each plugin's "Changelog" tab on WordPress.org lists what changed. A big release ("3.0", "rewritten from scratch") is more likely to cause trouble than a small fix.
- **Test plugins.** Switch the updated plugins off one at a time, ideally only for yourself so visitors aren't affected, and check after each. A troubleshooting plugin such as [Culprit Finder](/docs/getting-started/first-run/) does this for your browser only, and also catches the tricky case where the update didn't break anything by itself but now clashes with another plugin.

That last case is common: an update changes how a plugin works, and a second plugin that relied on the old behaviour breaks. The updated plugin isn't "wrong", and neither is the other one. They just don't fit together anymore.

## Fix it

Once you know which plugin is involved, you have several options. Go down the list until one works:

**1. Look for a follow-up update.** Authors often release a quick fix within a day or two of a problematic release. Check for updates again.

**2. Check the support forum.** On the plugin's WordPress.org page, open the "Support" tab. If others have the same problem, there may already be a workaround, and you'll see whether the author is working on it.

**3. Roll back to the previous version.** If the old version worked, going back is a reasonable temporary fix:
   - Your host's backup or staging tools may let you restore the plugin.
   - On WordPress.org, the plugin's "Advanced View" page lets you download previous versions. Deactivate and delete the current version, then upload the older zip via Plugins → Add New → Upload Plugin.
   - Keep in mind that old versions miss security fixes. Roll back only as a short-term measure, and update again once a fix is out.

**4. Deactivate it temporarily.** If the plugin isn't essential, switch it off until it's fixed. Check what it was doing first, so you don't lose something important like a redirect or a security rule.

**5. Report it.** Post in the plugin's support forum with clear steps to reproduce, the version that broke, the last version that worked, and your WordPress and PHP versions. If another plugin is involved, name both. Leave out your site address and personal data in public posts.

## If the update broke the whole site

If you can't reach your dashboard at all:

1. Use the **recovery mode link** from WordPress's email, if you received one. It pauses the crashing plugin for you so you can log in and deactivate or roll it back.
2. Otherwise, rename the plugin's folder in `wp-content/plugins/` using FTP or your host's file manager. WordPress deactivates a plugin it can't find.
3. Once you're back in, follow "Fix it" above.

## After it's fixed

A few habits make the next update much less stressful:

- **Update in small batches.** Update a few plugins at a time and check your important pages afterwards: home, checkout, contact form, login.
- **Keep a short list of key pages.** Check the same pages after every update round. It takes two minutes.
- **Use staging for big updates.** Many hosts offer a staging copy of your site. Try major updates there first.
- **Keep backups you can restore.** Test a restore once, so you know it works before you need it.
- **Don't auto-update everything blindly** on a site where downtime costs money. Auto-updates are great for security fixes, but for big feature releases, a quick manual check is worth it.

Updates are how plugins get safer and better, so don't stop updating because of one bad experience. With a calm, step-by-step approach, a broken update is usually a 15-minute problem, not a disaster.
