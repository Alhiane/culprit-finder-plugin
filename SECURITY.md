# Security policy

## Reporting a vulnerability

Please report security issues **privately** by email to **security@getculpritfinder.com**. Don't open a public GitHub issue for them.

Include what you found, how to reproduce it, and the plugin version. We aim to confirm receipt within 3 working days and to keep you updated until a fix is released. We're happy to credit you in the changelog if you'd like.

## Supported versions

Security fixes go into the latest release. Please update to it before reporting.

## Scope

Culprit Finder changes which plugins load for one administrator's browser. Issues we especially want to hear about:

- anything that lets a visitor, or a user without the `activate_plugins` capability, influence which plugins load;
- anything that changes the site's real list of active plugins;
- leaks of the session token or the emergency exit key;
- missing nonce or capability checks on Culprit Finder actions.
