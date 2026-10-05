# Culprit Finder dev tasks. PHP tooling runs on the host when php/composer exist,
# otherwise in Docker (ADR-0012). wp-env is the ONLY test site (ADR-0011).

BASE      ?= http://localhost:8888
PHP_IMAGE ?= php:8.2-cli
WPENV     ?= npx --yes @wordpress/env

DOCKER_RUN = docker run --rm -v "$(CURDIR)":/app -w /app
# Use host php/composer only if they run a script cleanly (output must be exactly
# the probe string); a broken shim on PATH (e.g. a removed Herd install) must not win.
HOST_PHP_PROBE := $(shell echo '<?php echo "cf-php-ok";' | php 2>/dev/null)
ifeq ($(HOST_PHP_PROBE),cf-php-ok)
PHP      := php
COMPOSER := $(if $(shell composer --version 2>/dev/null),composer,$(DOCKER_RUN) composer:2)
else
PHP      := $(DOCKER_RUN) $(PHP_IMAGE) php
COMPOSER := $(DOCKER_RUN) composer:2
endif

export BASE WPENV

.PHONY: help install up down destroy lint fix unit e2e test zip zip-test release-check pot screenshots banners compat website website-dev website-test clean

help:
	@echo "make up | down | destroy | lint | fix | unit | e2e | test | zip | zip-test | release-check | pot | screenshots | banners | compat | website | website-dev | website-test"
	@echo "Site: $(BASE)  (admin / password)"

vendor/autoload.php: composer.json
	$(COMPOSER) install --no-interaction --no-progress
	@touch $@

install: vendor/autoload.php

up:
	$(WPENV) start
	@echo "WordPress: $(BASE)/wp-admin  (admin / password)"

down:
	$(WPENV) stop

destroy:
	$(WPENV) destroy

lint: vendor/autoload.php
	$(PHP) vendor/bin/phpcs

fix: vendor/autoload.php
	-$(PHP) vendor/bin/phpcbf

unit: vendor/autoload.php
	$(PHP) vendor/bin/phpunit

e2e:
	bash tests/e2e/run.sh $(SCENARIO)

test: lint unit release-check e2e

zip:
	bash bin/build-zip.sh

# readme.txt rules and no paid-plan wording in anything published (run after make zip for the zip check).
release-check:
	bash tests/release/readme-lint.sh
	bash tests/release/forbidden-terms.sh
	bash tests/release/distignore-match.sh

# Translation template (runs WP-CLI in the wp-env cli container).
pot:
	bash -c 'source tests/e2e/lib.sh; docker exec -w /var/www/html/wp-content/plugins/culprit-finder "$$CLI_CONTAINER" wp i18n make-pot . languages/culprit-finder.pot --slug=culprit-finder --domain=culprit-finder --exclude=tests,vendor,build,docs,.claude,node_modules,.wordpress-org,.github,website'

# WordPress.org screenshots from the cff-showcase fixtures (needs make up; resets the wp-env site).
screenshots:
	bash tests/screenshots/run.sh

# solo + pair on WordPress 6.5/latest x PHP 7.4/8.5 (PHP CLI images + SQLite, port 8892).
compat:
	bash tests/compat/run.sh

# Website (website/, Astro + Starlight).
website: website/node_modules
	cd website && npm run build

website-dev: website/node_modules
	cd website && npm run dev

website-test: website/node_modules
	cd website && npm test

website/node_modules: website/package.json
	cd website && npm ci --no-audit --no-fund
	@touch $@

# WordPress.org banners + website og-image from tests/screenshots/banner.html.
banners:
	cd tests/screenshots && ( [ -d node_modules ] || npm install --silent --no-audit --no-fund ) && node banners.mjs

# Install the zip on a fresh, separate wp-env (ports 8890/8891) and run the solo scenario.
zip-test: zip
	bash bin/zip-test.sh

clean:
	rm -rf build vendor .phpunit.result.cache
