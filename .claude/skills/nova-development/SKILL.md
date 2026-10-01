---
name: nova-development
description: >-
  Use whenever a change touches how the Input Sanitizer meets Nova or the Aegis
  page: the fields of InputSanitizerModule (drawn by the core's Vue page), the
  Nova and Aegis route exclusions in SanitizeInput, nova.path, the dashboard
  status line and the check, or the way the service provider wires the module.
  Use it with aegis-security for anything about what gets through.
metadata:
  author: project
---

# Nova and the Aegis page (this package)

The module has no Nova tool, card, route or Vue code of its own: the Aegis core draws its section from `InputSanitizerModule::fields()` and saves it through its own API (`PUT /nova-vendor/aegis/settings/input-sanitizer`, behind `nova`, `nova.auth` and `viewAegis`). Check the core's source in `vendor/` before relying on an API. `vendor/laravel/nova` here is the test double in `stubs/nova`, not Nova: check a version-specific API in a real Nova install, and add any Nova class or method you start using to the core's `stubs/nova` with its real signature, then copy it here (see `package-testing`).

## The pieces

- `InputSanitizerModule` — `key()` `input-sanitizer`, `defaults()`, `rules()`, `fields()` built with the core's `Field` factories (`toggle`, `number`, `text`, `textarea`, `table`), `status()` for the dashboard line.
- `PatternsCheck` — registered with `Aegis::check()`, shown with the core's checks and by `php artisan aegis:check`.
- `SanitizeInput` — pushed onto the HTTP kernel's global middleware. It skips `Nova::path()`, `nova-api` and `nova-vendor` unless `scan_nova` is on, and `nova-vendor/aegis` always. Nova served from `/` skips only `nova-api` and `nova-vendor`.

## Rules

- A new setting is a key in `defaults()`, a rule in `rules()`, a field in `fields()`, a read in `InputSanitizerSettings::fromArray()` and strings in `resources/lang/en/input-sanitizer.php`, together.
- Use only the field types the core ships; a new one is a change to the core first.
- Labels and help come from `aegis-input-sanitizer::input-sanitizer.*`; they are printed as text by the core's page.
- No business logic in the module class or the provider: it belongs in the scanner, the settings or the middleware, where the tests call it.

## Checklist

- [ ] Nova's rich-text fields still save with the defaults (`scan_nova` off).
- [ ] The Aegis settings API is never scanned.
- [ ] New strings in the lang file; `make ready` passes.
