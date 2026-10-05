---
title: Uninstalling
description: How to remove Culprit Finder completely.
---

1. If a session is running, press **Stop and exit** (or just continue: deactivating ends it).
2. Go to **Plugins** and **Deactivate** Culprit Finder. This ends any session, expires the session cookie in your browser, and deletes the helper file from `wp-content/mu-plugins/`.
3. Press **Delete**. This removes the plugin's files and all of its data: the session, the results history, and any stored notes.

Your real plugin settings were never changed, so there's nothing to restore.

If you can't reach your dashboard, deleting `wp-content/mu-plugins/culprit-finder-loader.php` with FTP immediately stops Culprit Finder from switching plugins off for anyone.
