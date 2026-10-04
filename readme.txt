=== Culprit Finder ===
Contributors: alhiane
Tags: troubleshooting, plugin conflict, debug, health check, white screen
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find the plugin that broke your site in a few clicks, without breaking it for your visitors.

== Description ==

When something on your site breaks, the usual advice is "deactivate all plugins, then turn them back on one by one". With 30 plugins that takes ages, and doing it on a live site breaks carts, logins and forms for real visitors.

Culprit Finder switches plugins off **for your browser only**. It asks one question per step: "Is the problem still there?" Answer Yes or No, and it narrows things down until it names the plugin that causes the problem, or the two plugins that conflict with each other. Visitors and other admins see the normal site the whole time.

* About 7 questions for one culprit among 30 plugins.
* Detects conflicts between two plugins (for example "Cannot redeclare" fatal errors).
* Keep plugins on that the problem needs (for example WooCommerce for a checkout bug).
* Respects plugin dependencies ("Requires Plugins").
* Answer from the admin bar on any page, or from a control panel that keeps working even when a step breaks your pages.
* Ends by itself after an hour without answers, and has a logged-out emergency exit link.
* A copyable support report for forums, with no site address, user names or emails.
* Never changes your real plugin settings. No external requests, no tracking.

Go to Tools → Culprit Finder to start.

== Installation ==

1. Upload the plugin and activate it.
2. Culprit Finder copies a small helper file to `wp-content/mu-plugins/culprit-finder-loader.php`. If your host doesn't allow that, Tools → Culprit Finder shows how to copy it by hand.
3. Go to Tools → Culprit Finder, bookmark the two links it shows, and press Start.

== Frequently Asked Questions ==

= Will my visitors notice anything? =

No. Plugins are only switched off for requests from your own browser while you are logged in. Logged-out visitors and other administrators always get every plugin, and pages filtered for you are marked as not cacheable.

= A step shows "There has been a critical error on this website". What now? =

That's normal: it often means the plugins switched on in that step are the problem. Open the **control panel** link you bookmarked (Tools → Culprit Finder with `culprit_safe=1`), which always loads safely, and answer there. WordPress may also email you about the error; you can ignore it during troubleshooting.

= How do I get out if everything is broken? =

Any of these ends troubleshooting:

* Press **Exit** on the Tools page or in the admin bar.
* Open the **emergency exit** link you bookmarked. It works even when you're logged out.
* Wait an hour without answering; the session expires by itself.
* Deactivate Culprit Finder.
* Clear your browser cookies or use another browser: only your browser was affected.
* Delete `wp-content/mu-plugins/culprit-finder-loader.php` with FTP or your host's file manager.

= Does any data leave my site? =

No. Culprit Finder makes no external requests and has no tracking. The support report is only shown to you, and it contains no site address, user names or emails.

= What can't it test? =

Your theme, must-use plugins, drop-ins (such as `object-cache.php` or `advanced-cache.php`), and server configuration. If the problem stays with every plugin off, the report says so.

= Does it work on multisite? =

Not yet. It refuses to start on multisite networks.

= Can I manage plugins during troubleshooting? =

No. The Plugins screen is paused for you while a session runs, so the shortened list you see can never be saved by accident. Exit first.

== Changelog ==

= 0.1.0 =
* First release: session-only plugin isolation, conflict search with pair detection, pinned plugins, dependencies, admin bar controls, support report, WP-CLI command.
