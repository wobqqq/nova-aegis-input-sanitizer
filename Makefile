SHELL := /bin/bash

export UID := $(shell id -u)
export GID := $(shell id -g)

PHP := docker compose run --rm php

.PHONY: docker.build install update shell \
	composer.code.fix composer.code.check composer.code.stan composer.test composer.test.coverage composer.test.mutate \
	code.fix code.check test test.coverage test.mutate ready

# ─────────────────────────────── Docker ───────────────────────────────
docker.build:
	docker compose build

shell:
	$(PHP) sh

# ────────────────────────────── Composer ──────────────────────────────
install:
	$(PHP) composer install

update:
	$(PHP) composer update

composer.code.fix:
	$(PHP) composer code.fix

composer.code.check:
	$(PHP) composer code.check

composer.code.stan:
	$(PHP) composer code.stan

composer.test:
	$(PHP) composer test

composer.test.coverage:
	$(PHP) composer test.coverage

composer.test.mutate:
	$(PHP) composer test.mutate

# ───────────────────────────── Aggregates ─────────────────────────────
code.fix: composer.code.fix

code.check: composer.code.check

test: composer.test

test.coverage: composer.test.coverage

test.mutate: composer.test.mutate

ready: code.fix code.check test.coverage
