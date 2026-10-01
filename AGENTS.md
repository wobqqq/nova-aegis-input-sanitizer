# AGENTS.md

Guidance for coding agents working in this repository.

## What this is

**Aegis Input Sanitizer** (`wobqqq/nova-aegis-input-sanitizer`) is an add-on module of [Aegis](https://github.com/wobqqq/nova-aegis), the security suite for Laravel Nova (Laravel 12, PHP 8.2+). It scores every request — the query string, the form body, JSON bodies when enabled, the keys of all of them, the headers and the URL segments, each decoded up to three times — against the administrator's regular expressions for XSS, encoded XSS, command injection, path traversal, template injection, null bytes and CSV injection, and answers 400 with the configured page (or JSON) once the score reaches the threshold.

It is the Laravel/Nova port of the October CMS Fortify module `oc-fortify-input-sanitizer-plugin`, with the fixes of its 1.0.3 release (patterns that do not compile or give up, settings applied as soon as they are saved, safe fallbacks for broken settings).

This is a **security product installed on production applications**. A bug here blocks every visitor or an administrator, or silently lets payloads through. Security and safe upgrades come before everything else.

## The self-check gate (run before every commit)

Everything runs in Docker; the host needs no PHP. The container mounts the parent directory, so the core's checkout must sit next to this one (`../nova-aegis`).

```bash
make install        # composer install (the core from ../nova-aegis, Nova from nova.laravel.com)
make code.fix       # composer normalize, Rector, PHP CS Fixer
make code.check     # validate, normalize --dry-run, composer audit, php -l, cs, Rector, PHPStan max
make test           # Pest
make test.coverage  # Pest with pcov, failing below 90 %
make ready          # all of the above
```

`make ready` must pass. PHPStan runs at `level: max` with strict rules and **no baseline**: fix the type, never add an ignore. Advisories from `composer audit` are fixed by updating the package, never ignored.

Installing Nova needs a license: `auth.json` (gitignored and export-ignored) holds the credentials. Never read, print or commit it.

## How the code is laid out

| Path | Holds |
|------|-------|
| `src/InputSanitizerServiceProvider.php` | Wiring only: the module and the check, the cache-clearing listeners, the global middleware, the views, the translations, the command. |
| `src/InputSanitizerModule.php` | The `input-sanitizer` section: defaults (the default patterns), rules, fields, the dashboard status. |
| `src/Enums/Category.php` | The seven kinds of payload, their setting key (`{category}_patterns`), label and default pattern. |
| `src/Settings/InputSanitizerSettings.php` | The typed settings, read again from any stored row with safe fallbacks; only the patterns that compile. |
| `src/Settings/SettingsStore.php` | The cached settings every request reads (`aegis.input-sanitizer.settings.v1`), scoped per request. |
| `src/Scanning/PatternMatcher.php` | The only place a pattern runs. A pattern that does not compile or gives up is no match, never an error. |
| `src/Scanning/RequestScanner.php` | What is scanned, the decoding, the score across the whole request, the exclusions. |
| `src/Http/Middleware/SanitizeInput.php` | Runs the scan on every request (Nova and the Aegis API skipped), refuses with 400, logs without values, fails open. |
| `src/Rules/` | `CompilablePattern` and `ExistingView`, the save-time validation. |
| `src/Checks/PatternsCheck.php` | Warns about a saved pattern that is skipped or a page that is gone. |
| `src/Console/DisableCommand.php` | `aegis:input-sanitizer:disable`, the recovery path. |
| `resources/views/blocked.blade.php` | The built-in page for a blocked request. |
| `resources/lang/en/input-sanitizer.php` | Every label and message, under `aegis-input-sanitizer::input-sanitizer.*`. |

### Working with the core

The core is a separate package that applications update on their own schedule; a module must keep working with every released core of the same major.

- Use only the core's public contract (listed in the core's AGENTS.md): `Aegis::module()`, `Aegis::check()`, `Aegis::settings('input-sanitizer')`, `Aegis::save()`, `Support\Values`, `Contracts\Module`, `Contracts\Check`, `CheckResult`, `Field`, `Events\SettingsSaved`. The only other dependency is the `aegis.cache_store` config key, with a fallback to the default store. The module requires core `^1.1`, the first release with `Aegis::save()` and `Values` as public API.
- Settings are read only through `Aegis::settings()` and written only through the core (the Aegis page or `Aegis::save()`), never through the table (an arch test enforces it).
- The cache is cleared on `SettingsSaved` for this section and on the settings model's `eloquent.saved` / `eloquent.deleted` events, listened to by name: a row may be written without the core's event.
- A newer core API is used only behind a check (`method_exists`, `class_exists`) with a fallback.

## Upgrading installed applications safely

Read the `package-upgrades` skill before changing anything that reaches an application that already runs the module. In short:

- A change to what is **stored** (a setting's key, type or meaning) keeps reading the old shape (`InputSanitizerSettings::fromArray()`), or uses a new key.
- A change to what is **cached** bumps the key's version (`SettingsStore::CACHE_KEY`).
- Defaults stay safe: a new protection ships disabled; a stricter default pattern is announced in the changelog.
- Every change is a line under *Unreleased* in `CHANGELOG.md`.

## Security rules (always)

Read the `aegis-security` skill for the full checklist. The non-negotiables:

- **Never break or lock out the application.** Patterns run only through `PatternMatcher`; the middleware fails open on any error; Nova is skipped unless `scan_nova` is on; the Aegis settings API (`nova-vendor/aegis`) is never scanned, so a pattern that is too broad can always be fixed from Nova.
- **Validate every setting twice**: in `rules()` when it is saved, and in `fromArray()` / `SettingsStore` when it is used.
- **Escape every output.** The blocked page prints translated strings only; the JSON answer is a fixed message.
- **No secrets in logs.** A blocked request is logged with the IP, the method, the score and where each match was, never a value, the path, a cookie or a header's content.
- **Every request stays cheap.** No database query per request: the settings come from the cache, memoized per request. Decoding is bounded and the scan stops at the threshold.
- **Defence in depth.** The sanitizer never replaces validation and output escaping in the application; do not describe it as making an application safe. Uploaded files and cookies are not scanned, JSON bodies only when enabled: say so wherever the coverage is described.

### Recovery commands

For an administrator whose site or API is blocked by a pattern that is too broad:

```bash
php artisan aegis:input-sanitizer:disable   # turns the module off and keeps the other settings
php artisan aegis:check                     # shows whether a saved pattern is skipped
```

The Aegis page in Nova stays reachable while the module is on: Nova and the Aegis settings API are not scanned by default.

## Tests

Pest 4 on Orchestra Testbench 10 with the real `laravel/nova` and the real Aegis core (SQLite in memory). No test reaches the network. Read the `package-testing` skill.

## Git workflow

- `main` is protected: **never push to it and never force-push.** The initial build was the only push to `main`; every change since goes through a pull request:
  1. branch off the latest `main`, named after the change (`fix/…`, `feat/…`, `chore/…`, `docs/…`);
  2. commit on the branch and `git push -u origin <branch>`;
  3. open a pull request with the template filled in (what changes, what it means for applications that upgrade);
  4. merge once `make ready` passed, then delete the branch.
- GitHub Actions run `composer code.check` and `composer test.coverage` on every pull request, with the core checked out next to the module (`nova-aegis`, branch `main`) for the path repository. They need the `NOVA_USERNAME` and `NOVA_LICENSE_KEY` secrets, and `AEGIS_CORE_TOKEN` (read access to `wobqqq/nova-aegis`) while the core is private.
- A release is a tag pushed on a merged commit of `main` (`git tag -a v1.0.0 -m "..." && git push origin v1.0.0`) with its section in `CHANGELOG.md`; the release workflow runs CI and publishes the GitHub release, Packagist reads the tag.
- Code, comments, commit messages, pull requests, issues and documentation are written in **English**.

## Conventions

- `declare(strict_types=1);` in every PHP file; PSR-12 via PHP CS Fixer.
- Code documents itself: names over comments. A comment explains a non-obvious *why*, in one sentence.
- Every class is `final`; value objects are `final readonly`.
- Laravel patterns: container bindings, `ValidationRule` objects, `Cache`, the view factory, `Psr\Log\LoggerInterface`.
- Commits: imperative subject saying what the change does for the application ("Skip the Aegis settings API"), a body with the why.
