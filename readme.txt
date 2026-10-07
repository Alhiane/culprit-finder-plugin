=== Culprit Finder ===
Contributors: alhiane
Tags: troubleshooting, plugin conflict, debug, critical error, white screen
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find the plugin that broke your site. Plugins are switched off for your browser only, so visitors never notice.

== Description ==

When something on your site breaks, the usual advice is "deactivate all plugins, then turn them back on one by one". With 30 plugins that takes ages, and doing it on a live site breaks carts, logins and forms for real visitors.

Culprit Finder switches plugins off **for your browser only**. It asks one question per step: "Is the problem still there?" Answer Yes or No, and it narrows things down until it names the plugin that causes the problem, or the two plugins that conflict with each other. Visitors and other admins see the normal site the whole time.

Guides and answers to common questions are in the documentation: https://getculpritfinder.com/docs

* **Visitors never notice.** Only your browser sees plugins switched off. Nothing is deactivated, and your real plugin settings never change.
* **Fast.** About 7 answers for 30 plugins, because each answer halves the list.
* **Catches two-plugin conflicts**, such as two plugins that only crash together ("Cannot redeclare" fatal errors).
* **Keep plugins on** that the problem needs, for example your shop plugin for a checkout problem. Plugin dependencies ("Requires Plugins") are respected.
* **Answer from anywhere:** the Culprit Finder page, the toolbar on any page, the dashboard widget, or a control panel that keeps working even when a step breaks your pages.
* **Always a way out:** an Exit button, a logged-out emergency exit link, and an automatic end after an hour without answers, or after three hours at most.
* **A support report** for forums and plugin authors, with no site address, user names or emails. Copy it, or download it as .md or .txt.
* **Results history:** your last 10 results stay on the Results tab, on your own site.

= What it can't test =

Your theme, must-use plugins, drop-ins (such as `object-cache.php`) and server settings. If the problem stays with every plugin off, the result says so. Multisite networks aren't supported yet.

= Privacy =

Culprit Finder makes no external requests and has no tracking. It stores its results only on your site, and uninstalling removes them.

= Source code and support =

Culprit Finder is free software (GPLv2 or later). The source code is on GitHub: https://github.com/Alhiane/culprit-finder-plugin. Documentation lives at https://getculpritfinder.com/docs.

== Installation ==

1. Install Culprit Finder from Plugins → Add New, or upload the zip, then activate it.
2. Activation changes nothing on your site. When you press **Start troubleshooting**, Culprit Finder adds a small helper file, `wp-content/mu-plugins/culprit-finder-loader.php`, which is what lets it switch plugins off for your browser only. It removes the file again when troubleshooting ends, and it never touches a file it didn't create. If your host doesn't allow writing there, the Culprit Finder page shows how to add it by hand.
3. Open **Culprit Finder** in the admin menu, bookmark the two safety links, tick "I've saved both links", and press **Start troubleshooting**.

== Frequently Asked Questions ==

= Will my visitors notice anything? =

No. Plugins are only switched off for requests from your own browser while you are logged in. Logged-out visitors and other administrators always get every plugin, and pages filtered for you are marked as not cacheable.

= A step shows "There has been a critical error on this website". What now? =

That's normal: it often means the plugins switched on in that step are the problem. Open the **control panel** link you bookmarked (the Culprit Finder page with `culprit_safe=1`), which always loads safely, and answer there. WordPress may also email you about the error; you can ignore it during troubleshooting.

= My whole site is down, including wp-admin. Can I still use it? =

Yes, through WordPress's own recovery mode:

1. When a plugin crashes the whole site, WordPress emails the site admin a "recovery mode" link. Open it and log in. WordPress pauses the plugin that crashed, for you only, so wp-admin loads again.
2. Go to Culprit Finder, bookmark the two links, and press Start.
3. Click **Exit Recovery Mode** in the admin bar straight away. This matters: while recovery mode is on, WordPress keeps that plugin paused and your answers would be wrong. Culprit Finder now keeps your site usable instead, and the control panel link always works.
4. Answer the questions as usual.

If the recovery email never arrives (some hosts can't send email), ask another administrator, use WP-CLI (`wp plugin deactivate`), or rename the plugin's folder via FTP to get back in. WordPress's recovery email usually names the plugin that crashed; Culprit Finder is most useful when the cause isn't obvious, such as two plugins that only fail together.

= How do I get out if everything is broken? =

Any of these ends troubleshooting:

* Press **Exit** on the Culprit Finder page, in the dashboard widget, or in the admin bar.
* Open the **emergency exit** link you bookmarked. It works even when you're logged out.
* Wait an hour without answering; the session expires by itself. Every session also ends by itself after three hours at most.
* Deactivate Culprit Finder.
* Clear your browser cookies or use another browser: only your browser was affected.
* Delete `wp-content/mu-plugins/culprit-finder-loader.php` with FTP or your host's file manager.

= Does any data leave my site? =

No. Culprit Finder makes no external requests and has no tracking. Results are stored only on your site (your last 10), and the support report contains no site address, user names or emails. Uninstalling removes everything.

= How many questions will it ask? =

About 7 for 30 plugins, because each answer halves the list. Finding two plugins that only fail together takes a few more.

= What can't it test? =

Your theme, must-use plugins, drop-ins (such as `object-cache.php` or `advanced-cache.php`), and server configuration. If the problem stays with every plugin off, the report says so.

= Does it work on multisite? =

Not yet. It refuses to start on multisite networks.

= Why does it add a file to wp-content/mu-plugins? =

To switch plugins off for your browser only, Culprit Finder must run before WordPress loads the other plugins, and only must-use plugins load that early. So when you press Start it adds one small helper file there, the same approach as WordPress's own Health Check & Troubleshooting plugin. Without your troubleshooting cookie the file does nothing. It is removed when troubleshooting ends (Exit, the emergency exit link, the 60-minute timeout or the three-hour limit, or deactivating the plugin), and Culprit Finder never overwrites or deletes a file it didn't create.

= Can I manage plugins during troubleshooting? =

No. The Plugins screen is paused for you while a session runs, so the shortened list you see can never be saved by accident. Exit first.

== Screenshots ==

1. Pick plugins to keep on and save your two safety links.
2. One question per step: is the problem still there?
3. Answer from the toolbar on any page.
4. The culprit, what to do next, and a report ready for any support forum.
5. Your last 10 results stay on this site.
6. How it works and every way out.

== Changelog ==

= 0.2.0 =
* Add-ons for Culprit Finder now stay on automatically during troubleshooting. You no longer need to tick them under "Keep any plugins on?", and the setup screen, each step and the report show them as kept on.
* Every troubleshooting session now ends by itself after three hours at most, even when answers keep coming.
* Fixed: when answers came in automatically, your browser could drop out of a session that was still running.
* For developers: new hooks and functions for add-ons (keep an add-on on, add setup fields, a step panel, result buttons and a toolbar label, and answer the current question from your own code). See https://getculpritfinder.com/docs/developers/hooks/

= 0.1.0 =
* First release: switch plugins off for your browser only, find the plugin (or the two plugins) behind a problem, keep plugins on, respect plugin dependencies, answer from the page, the toolbar or the dashboard widget, results history with .md and .txt downloads, and a WP-CLI command.

== Upgrade Notice ==

= 0.2.0 =
Add-ons now stay on automatically during troubleshooting, and every session ends after three hours at most.

= 0.1.0 =
First release.
