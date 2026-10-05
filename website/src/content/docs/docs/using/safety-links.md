---
title: Your two safety links
description: The control panel and emergency exit links, and when to use them.
---

Before you start, Culprit Finder shows two links. Bookmark both, then tick **I've saved both links**. Start stays disabled until you do.

## Control panel

`/wp-admin/admin.php?page=culprit-finder&culprit_safe=1`

The Culprit Finder page in **safe mode**: it loads with only Culprit Finder and the plugins you kept on, never the plugins being tested in the current step. Use it when a step makes your pages crash, so you can still answer, undo or exit. It's the same address on every run.

## Emergency exit

`/?culprit-finder-exit=…` followed by a long secret key.

Opening it ends the session immediately and puts your browser back to the normal site. It works **even when you're logged out**, and from any browser. The key is made for one session only, so:

- bookmark it from the page where you press Start (each page load makes a new link),
- keep it private,
- after **Run again**, bookmark the new link shown next to the button.

## Other ways out

- **Stop and exit** on the Culprit Finder page, in the toolbar, or in the dashboard widget.
- Wait 60 minutes without answering; the session ends by itself.
- Deactivate Culprit Finder.
- Clear your browser cookies or use another browser: only your browser was affected.
- Last resort: delete `wp-content/mu-plugins/culprit-finder-loader.php` with FTP or your host's file manager.
