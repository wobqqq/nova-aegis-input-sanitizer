# Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [semantic versioning](https://semver.org/).

## [Unreleased]

### Added

- Laravel 13 support; CI runs the suite on Laravel 12 and 13.

### Changed

- PHP 8.4 or later is required.
- Native types throughout: typed constants and properties, `#[\Override]` on every overriding method, readonly value objects; no behaviour change.

## [1.0.1] - 2026-10-01

### Changed

- Development and CI run on a test double of Nova (`stubs/nova`, not shipped) and need no Nova license; `make test.nova` runs the PHP suite on the real Nova. Nothing changes for applications.
- The README splits the installation into numbered steps.
- The Dependabot config no longer reads the Nova registry.

## [1.0.0] - 2026-10-01

### Added

- The Input Sanitizer section of the Aegis settings, off until enabled: block threshold, the page shown to a blocked request, JSON and Nova scanning (both off), logging, one pattern per kind of payload and the excluded inputs and headers.
- Default patterns for XSS, encoded XSS, command injection, path traversal, template injection, null bytes and CSV injection.
- A global middleware that scores the query string, the form body, JSON bodies (optional), the input names, the headers and the URL segments of every request, and answers 400 with the configured page or a JSON message.
- Nova's routes are skipped unless enabled; the Aegis settings API is never scanned.
- Patterns that do not compile are refused when saved and skipped at runtime; a pattern that gives up on a long input is no match.
- Blocked requests are logged without values.
- A dashboard status line and a check for skipped patterns and missing pages.
- `aegis:input-sanitizer:disable` console command.
- Requires Aegis 1.1 or later.

[Unreleased]: https://github.com/wobqqq/nova-aegis-input-sanitizer/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/wobqqq/nova-aegis-input-sanitizer/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/wobqqq/nova-aegis-input-sanitizer/releases/tag/v1.0.0
