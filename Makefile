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

.PHONY: help install up down destroy lint fix unit e2e test zip zip-test clean

help:
	@echo "make up | down | destroy | lint | fix | unit | e2e | test | zip | zip-test"
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

test: lint unit e2e

zip:
	bash bin/build-zip.sh

# Install the zip on a fresh, separate wp-env (ports 8890/8891) and run the solo scenario.
zip-test: zip
	bash bin/zip-test.sh

clean:
	rm -rf build vendor .phpunit.result.cache
