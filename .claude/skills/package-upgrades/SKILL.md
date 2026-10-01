---
name: package-upgrades
description: "How a change reaches the Laravel applications that already run the Input Sanitizer. Use before changing a stored setting (its key, type or meaning), a default value or a default pattern, the cache key or what is cached, what the module uses from the Aegis core, composer.json constraints, or when preparing a release or a tag."
license: MIT
---

# Upgrading installed applications

Applications update the core and each module independently with Composer. Every change is written for an application that has been running the previous version for months, with any released core of the same major.

## Versions and releases

- Semantic versions: a fix is a patch, a new option or check a minor, a removed option or a changed behaviour a major.
- Every change adds a line under *Unreleased* in `CHANGELOG.md` saying what changes for the developer. A release moves them under the version and date.
- Release: merge the pull request, then `git tag -a v1.0.1 -m "..." && git push origin v1.0.1`. Packagist reads the tag.

## Constraints

- `laravel/framework` and `laravel/nova` are whole majors. Supporting a new major is a minor release with both ranges and tests against both.
- `wobqqq/nova-aegis` is `^1.0 || dev-main`. Until the core is on Packagist, development reads it from the sibling checkout through the `path` repository; once it is released, drop `dev-main` and the path repository in one change.
- The lock file is for development only (export-ignored); the ranges are what applications resolve.

## Stored settings

The section is one `aegis_settings` row, key `input-sanitizer`, saved through the core.

- The core merges the stored values over `defaults()` and drops keys the defaults no longer name, so adding a setting needs no migration.
- Changing a setting's type or meaning: prefer a **new key** to converting an ambiguous value.
- `InputSanitizerSettings::fromArray()` still reads the old shape: an application can run the new code with a row an older version wrote.
- Never rename the section key or a pattern key (`{category}_patterns`): applications have tuned them.

## Default patterns

- A default pattern only applies to applications that never saved the section; a saved section keeps its own patterns.
- A stricter default blocks requests that passed before: say so in the changelog. A default never matches what an ordinary form, URL or browser header sends.

## Cached values

- The settings are cached as an array under `aegis.input-sanitizer.settings.v{n}`. A change to the shape of the cached array bumps the version in `SettingsStore::CACHE_KEY`.
- The reader treats an unreadable or non-array entry as a miss; keep reading through it.

## The core's contract

The module uses `Aegis::module()`, `::check()`, `::settings()`, `Contracts\Module` and `Check`, `CheckResult`, `Field`, `Events\SettingsSaved`, `Aegis::save()` (the console command) and `Support\Values` (core `^1.1`), plus the `aegis.cache_store` config key.

- Use only these. A newer core API is used behind a check (`method_exists`, `class_exists`) with a fallback.
- The model events are listened to by name (`eloquent.saved: Wobqqq\Aegis\Settings\AegisSetting`): a renamed class only silences that listener, `SettingsSaved` still clears the cache.

## Defaults

- A new protection ships disabled, or with a default that cannot block the current administrator.
- Changing a default changes the behaviour of every application that never saved the section: say so in the changelog, or keep the old default.
