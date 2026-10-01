---
name: package-testing
description: "How the Input Sanitizer is tested. Use when writing or changing anything in tests/ (Pest files, tests/TestCase.php, tests/Pest.php, tests/Fixtures), phpunit.xml.dist or stubs/nova (the Nova test double), when code or a test uses a Nova class or method not used before, when a test needs Nova, the Aegis core, a request, a user, a view or the console, when a test fails only in the suite, or when PHPStan complains about a test."
license: MIT
---

# Testing the package

## The harness

- Pest 4 on Orchestra Testbench 10 (Laravel 12) with `laravel/nova` resolved to the test double in `stubs/nova` (see below) and the real Aegis core (`vendor/wobqqq/nova-aegis`, the sibling checkout). SQLite in memory, array cache and session (`phpunit.xml.dist`).
- `tests/TestCase.php` loads `Inertia\ServiceProvider`, `NovaCoreServiceProvider`, `AegisServiceProvider` and `InputSanitizerServiceProvider`, runs the core's migration, creates the `users` table for `tests/Fixtures/User.php`, registers `AegisTool`, defines `viewAegis` as `is_admin`, adds `tests/Fixtures/views` as a view location and defines the routes the requests hit (`/page/{path?}`, `/api/comments`, `/nova/...`, `/admin/...`).
- `tests/Pest.php` gives `admin()`, `editor()` and `sanitize(array $values)`: saves the section through the core (validated, caches cleared) with the defaults and `enabled => true`.
- `Feature/` drives real HTTP requests through the global middleware, the Aegis API, the console and the check runner; `Unit/` holds `PatternMatcher` and the `arch()` rules (strict types, final classes, readonly value objects, string-backed enums, patterns only through the matcher, no network, no direct table access).

## The Nova test double (`stubs/nova`)

- `composer.json` declares `stubs/nova` as the `nova` path repository (`"versions": {"laravel/nova": "5.99.0"}`, `"symlink": true`), so `vendor/laravel/nova` links to it. No license, no `auth.json`, the same in CI. It is export-ignored; applications install the real Nova.
- It is a verbatim copy of the core's `stubs/nova` (the core's `package-testing` skill lists what it provides and leaves out). Never edit it here: change the core's copy in a pull request on the core, then copy the directory here unchanged.
- **The module uses a Nova API the double lacks:** read the real signature in a Nova install and add the class or method to the core's `stubs/nova` with the same name, parameters, native types and PHPDoc (never narrower), implementing only the behaviour the packages rely on; copy it here, then run `make ready` and, with a license, `make test.nova`.
- A test is about the module, not Nova: when a test only passes on one of them, rewrite it against the behaviour of the double instead of deleting the coverage.
- `make test.nova` runs the suite on the real Nova in a throwaway copy (`docker/test-nova.sh`): it needs `auth.json` with your license; `NOVA_VERSION=5.9.3 make test.nova` pins a release.

## Rules

- Test what an application sees: the status and body of a request, the JSON of the Aegis API, the status a check reports, the exit code of a command, the log context. Not private methods.
- A security rule is a test: a payload in each source, an excluded name, a broken or catastrophic pattern, a broken view, a broken cache, a value that must not be logged.
- Save settings with `sanitize()` or the API. Write `AegisSetting` rows by hand only to test a row the rules would refuse, then flush (`SettingsRepository::flush()`, `SettingsStore::forget()`) when the write bypassed the model events.
- The scoped `SettingsStore` survives inside a test: `app()->forgetScopedInstances()` simulates the next request.
- Coverage stays at 90 % or more (`make test.coverage`).

## PHPStan max on tests, without ignores

- Use the global `Pest\Laravel\*` functions (`get`, `post`, `postJson`, `actingAs`, `withHeaders`), never `$this->` in a closure.
- Console: `expect(Artisan::call('aegis:input-sanitizer:disable'))->toBe(0)` and `Artisan::output()`.
- Annotate mocks (`/** @var ViewFactory&MockInterface $views */`) and narrow `mixed` JSON with `is_array()` or `data_get()`.
- Capture logs with `Event::listen(MessageLogged::class, ...)`.

## Workflow

1. Write the change and its tests; iterate with `docker compose run --rm php vendor/bin/pest --filter='...'`.
2. `make composer.test.coverage` for gaps; cover the uncovered decisions, not getters.
3. `make ready` before the commit; `make test.nova` too when the change touches Nova and you have a license.
