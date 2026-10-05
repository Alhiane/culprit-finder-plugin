---
title: Install
description: Requirements and how to install Culprit Finder on your WordPress site.
---

## Requirements

- WordPress 6.5 or newer
- PHP 7.4 or newer
- A single site. Multisite networks aren't supported yet; Culprit Finder refuses to start there.
- A writable `wp-content/mu-plugins` folder (Culprit Finder creates it if needed). If your host doesn't allow it, see [Troubleshooting](/docs/help/troubleshooting/).

## Install the plugin

1. Download Culprit Finder (the zip file).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and press **Install Now**.
3. Press **Activate**.

On activation, Culprit Finder copies one small helper file to `wp-content/mu-plugins/culprit-finder-loader.php`. Must-use plugins load before every other plugin, which is what lets Culprit Finder decide which plugins load for your browser. The helper does nothing for any request without your troubleshooting cookie, so it doesn't slow your site down.

## Where to find it

After activation you'll see **Culprit Finder** in the admin menu, right after Plugins, and a small Culprit Finder widget on your Dashboard. Only administrators who can manage plugins see either.

Next: [your first run](/docs/getting-started/first-run/).
