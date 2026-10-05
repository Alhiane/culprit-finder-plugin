---
title: How to find which plugin is breaking your WordPress site
description: A practical, step-by-step way to track down the plugin behind a broken page, a white screen or a strange error, without taking your site down for visitors.
order: 1
---

Something on your site stopped working. Maybe the checkout button does nothing, a page layout fell apart, a form no longer sends, or you see a blank white screen. Most of the time, a plugin is involved: either one plugin has a bug, or two plugins don't get along.

This guide walks you through finding out which one, calmly and in order.

## First, write down exactly what's broken

Before you change anything, describe the problem in one sentence you can check quickly:

- "The Add to cart button on product pages does nothing."
- "The contact form shows an error after I press Send."
- "The home page shows 'There has been a critical error on this website'."

Note the address of the page where you see it. You'll be checking this same page over and over, so a precise description saves time. If the problem only happens when you're logged in, or only on mobile, write that down too.

## Rule out the quick fixes

A few causes are worth checking before you start testing plugins:

1. **Caching.** Clear your caching plugin's cache, your host's cache and your browser cache, then check again. Old cached pages can make a fixed problem look broken, or the other way round.
2. **Recent changes.** Think about what changed right before the problem started. A plugin update, a new plugin, a theme update or a WordPress update is often the trigger. If you know which plugin you just updated, check that one first.
3. **The error log.** If you or your host can turn on WordPress debugging, the file `wp-content/debug.log` often names the plugin file where an error happened. Look for lines with "PHP Fatal error" and a path containing `wp-content/plugins/plugin-name/`.

If none of these point to a culprit, it's time to test plugins.

## The classic method, and why it hurts

The usual advice is: deactivate all plugins, check whether the problem is gone, then reactivate them one by one until it comes back. It works, but on a live site it has real costs:

- While plugins are off, your visitors lose them too. A shop loses its cart, a membership site loses its logins, and forms stop sending.
- With 20 or 30 plugins, reactivating them one at a time and checking after each takes a long time.
- If the problem only happens when **two** plugins are on together, turning them on one by one can be confusing. The problem appears only when you reach the second one, and you might blame the wrong plugin.

If you have a staging site (a private copy of your site that many hosts offer), do this kind of testing there instead of on the live site.

## A faster way: halve the list

You don't have to test plugins one at a time. Split the list in half instead:

1. Switch off all plugins except the ones the problem obviously needs (for a checkout problem, keep your shop plugin on).
2. Is the problem gone? Then a plugin is involved. If it's still there with every plugin off, the cause is elsewhere: your theme, your server, or WordPress itself.
3. Switch the first half of your plugins back on. If the problem comes back, the culprit is in that half. If not, it's in the other half.
4. Keep halving the suspects until one plugin is left.

With 30 plugins, this takes about 6 or 7 checks instead of 30. Keep notes as you go, because it's easy to lose track of which half you're testing.

## Doing it without affecting visitors

The halving method still switches plugins off for everyone if you do it from the Plugins screen. A troubleshooting plugin can do the same search only for your own browser. [Culprit Finder](/docs/getting-started/first-run/), for example, switches plugins off just for you, asks after each change whether the problem is still there, and does the halving for you. Your visitors and other administrators keep the normal site the whole time, and nothing is actually deactivated.

Whichever way you test, save a way back before you start. If a test makes your site show an error, you want to be able to undo it quickly. With manual testing, that means knowing how to reach your files over FTP or your host's file manager.

## When two plugins clash

Sometimes both plugins are fine on their own and only break together. Typical signs:

- The problem appears when you turn on one specific plugin, but that plugin works fine on another site.
- The error message mentions two different plugins, or says something like "Cannot redeclare function".

To confirm a clash, test the two plugins together with everything else off, then each one alone. If only the pair shows the problem, you've found a conflict. Report it to both plugin authors, because either of them might be able to fix it.

## You found the culprit. Now what?

Finding the plugin is half the job. Next:

1. **Check for an update.** The author may already have fixed it. Update the plugin and test again.
2. **Report it.** Open the plugin's support forum and describe the problem with exact steps. Include your WordPress version, PHP version, theme, and the other plugin involved if it's a conflict. Leave out your site address and personal details if you post publicly.
3. **Decide whether you can live without it for now.** If the plugin isn't essential, deactivate it until there's a fix. If it is essential, look for a similar plugin, or ask the author about a temporary workaround.

## If no plugin is to blame

If the problem stays with every plugin switched off, look at:

- **Your theme.** Switch to a default theme (such as a "Twenty" theme) on a staging site and check again.
- **Must-use plugins and drop-ins.** Files in `wp-content/mu-plugins/` and files like `object-cache.php` load no matter which plugins are active.
- **Your server.** PHP version changes, memory limits and server rules can all break things. Your host's support team can check their logs.

The key in every case is the same: change one thing at a time, check the same page each time, and write down what you tried. A methodical approach finds the problem much faster than guessing.
