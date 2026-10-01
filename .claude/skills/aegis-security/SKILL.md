---
name: aegis-security
description: "Security checklist for the Aegis Input Sanitizer. Use for any change to what a request can do or what gets through: the SanitizeInput middleware, the RequestScanner (what is scanned, how it is decoded, how it is scored), PatternMatcher, a default pattern, a setting and its validation rules, the Nova and Aegis exclusions, the blocked page or the JSON answer, logging, the console command, caching of the settings, or a security review of this package."
license: MIT
---

# Input Sanitizer security checklist

The sanitizer runs on every request of production applications. A mistake either blocks everyone (an administrator included) or silently lets payloads through. Treat every rule below as a test to write, not a guideline to remember.

## 1. Never break or lock out the application

- Every pattern runs through `PatternMatcher` (an arch test enforces it). A pattern that does not compile, or gives up on the backtrack, recursion or JIT stack limit, is **no match**, never a warning, an exception or a 500.
- `SanitizeInput` catches every `Throwable` from the settings and the scan, reports it and lets the request through: the application keeps working when the sanitizer breaks.
- Nova (`nova.path`, `nova-api`, `nova-vendor`) is skipped unless `scan_nova` is on. `nova-vendor/aegis` is **always** skipped: the settings it saves are the patterns themselves, so scanning it would stop an administrator from fixing a pattern that is too broad.
- A new default never blocks more than the previous one did; a new kind of scan ships off (`scan_json`, `scan_nova`).
- `aegis:input-sanitizer:disable` is the recovery path: it must work even when the stored row breaks the rules (invalid values go back to their defaults).

## 2. Validate every setting twice

- `InputSanitizerModule::rules()`: booleans, `block_threshold` 1-1000, `view` (`bail`, format without `..`, `ExistingView`), patterns `max:1000` + `CompilablePattern`, excluded names with a strict regex and `max:150` rows.
- `InputSanitizerSettings::fromArray()` reads the stored row again with safe fallbacks: the row may predate the rules or be written by hand. A pattern that does not compile is dropped there; a missing view falls back to the built-in page in `SettingsStore`.

## 3. Output: escape everything

- The blocked page prints only translated strings with `{{ }}`; it never echoes a request value, the pattern or the matched input.
- The JSON answer is a fixed message. Both send `Cache-Control: no-store` and `X-Content-Type-Options: nosniff`.

## 4. Logs: never a value

- A blocked request is logged with the IP, the method, the score and where each match was (`query.q (xss)`), never the value, the path, a cookie or a header's content: they may hold a password, a token or the payload itself.
- A source name comes from the request: it is reduced to `[A-Za-z0-9_.-]` and 100 characters before it is logged.

## 5. Every request stays cheap

- The middleware reads `SettingsStore` only: the settings are cached as an array (`aegis.input-sanitizer.settings.v1`), memoized per request (scoped binding), and never read from the database on a request.
- The cache is cleared on `SettingsSaved` for the `input-sanitizer` section and on the settings model's `eloquent.saved` / `eloquent.deleted` events (a row written without the core's event).
- Decoding is bounded (three URL-decoding rounds, then HTML entities); the scan is one pass over the values and stops at the threshold. Do not add work that grows faster than the size of the request.

## 6. What is and is not covered (say so wherever coverage is described)

- Scanned: the query string, the form body, JSON bodies when `scan_json` is on, the keys of all of them, the headers (except `cookie`, `accept` and the excluded ones) and the URL segments.
- Not scanned: uploaded files, cookies, a body whose JSON does not parse, Nova unless asked.
- The sanitizer is defence in depth. It never replaces validation and output escaping in the application; never describe it as making an application safe.

## Review procedure

1. `git diff --stat` and list every changed setting, rule, pattern, scanned source, exclusion, response and cache.
2. Walk each through sections 1 to 6 and name the test that pins it.
3. Run `make ready`.
4. Report each finding as: file:line, what an attacker sends, what happens, the fix.
