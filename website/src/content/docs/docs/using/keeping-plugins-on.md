---
title: Keeping plugins on
description: Keep plugins on in every step when the problem needs them, and how plugin dependencies are handled.
---

Some problems only appear with a certain plugin on. A checkout problem needs your shop plugin; a broken form needs your form plugin. If Culprit Finder switched those off, the problem would disappear in every step and the search would go wrong.

## How to keep plugins on

On the Troubleshoot tab, under **Keep any plugins on?**, tick the plugins the problem needs. They:

- stay on in every step,
- are never blamed for the problem,
- and still load normally for everyone else (as do all plugins).

Keep the list short. Every plugin you keep on is one Culprit Finder can't test. If the culprit is among them, the result says the problem isn't caused by the plugins tested.

## Plugins that need other plugins

Since WordPress 6.5, plugins can declare that they need other plugins ("Requires Plugins"). Culprit Finder respects that:

- If you keep a plugin on, the plugins it requires stay on too. In each step's plugin list they're labelled "needed by a kept-on plugin".
- A plugin is never switched on without the plugins it requires, so a step never causes a false error from a missing dependency.
- If a culprit has required plugins, the result says it was tested together with them.
