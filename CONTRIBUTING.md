# Contributing to Culprit Finder

Thanks for helping. Bug reports, fixes and documentation improvements are all welcome.

## Reporting a bug

Open an issue with the bug report template. The most useful thing you can include is the **support report** from Culprit Finder → Results (Copy for forum). It contains no site address, user names or emails.

Security issues: email security@getculpritfinder.com instead (see [SECURITY.md](SECURITY.md)).

## Development setup

You need Docker, Node 18+, `jq`, and `make`. PHP and Composer are optional: the Makefile runs them in Docker when they aren't installed.

```sh
make install   # dev dependencies (PHPUnit, coding standards)
make up        # disposable WordPress at http://localhost:8888 (admin / password)
make test      # lint + unit tests + release checks + end-to-end scenarios
make zip       # build/culprit-finder.zip
```

## Ground rules

- Culprit Finder must never write the `active_plugins` option, never affect visitors, and always offer a way out. Changes that risk these are not accepted.
- PHP 7.4+ syntax, WordPress 6.5+, WordPress Coding Standards (`make lint`).
- No external requests, tracking, or upsells.
- Add or update tests with every change: unit tests for the search engine, end-to-end scenarios (`tests/e2e/scenarios/`) for behavior.
- Extension points are documented on the website's Hooks reference; prefer a hook over a core change.

## Pull requests

Keep them focused, describe what changed and why, and make sure `make test` passes.
