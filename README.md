# Aegis Input Sanitizer

[![CI](https://github.com/wobqqq/nova-aegis-input-sanitizer/actions/workflows/ci.yml/badge.svg)](https://github.com/wobqqq/nova-aegis-input-sanitizer/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/wobqqq/nova-aegis-input-sanitizer)](https://packagist.org/packages/wobqqq/nova-aegis-input-sanitizer)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://github.com/wobqqq/nova-aegis-input-sanitizer/blob/main/composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](https://github.com/wobqqq/nova-aegis-input-sanitizer/blob/main/phpstan.neon.dist)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://github.com/wobqqq/nova-aegis-input-sanitizer/blob/main/LICENSE.md)

**Input Sanitizer** is a module of [Aegis](https://github.com/wobqqq/nova-aegis), the security suite for Laravel Nova. It refuses a request whose query string, body, headers or URL carry an XSS, template injection, command injection or path traversal payload before it reaches your application, and is configured from the **Aegis → Settings** page.

## 🚀 Features

- **Seven kinds of payload**, one editable regular expression each: XSS, encoded XSS, command injection, path traversal, template injection (`{{ }}`, `{% %}`, `{!! !!}`), null bytes and CSV injection.
- **The whole request is scored**: the query string, the form body, JSON bodies (optional), the input names, the headers and the URL segments, each URL-decoded up to three times and HTML-entity-decoded. Each match adds one; the request is refused once the score reaches the threshold.
- **400 with your page**: any Blade view, or the built-in one; a JSON client gets a JSON message.
- **Safe with a broken pattern**: a pattern that does not compile is refused when it is saved and skipped if an older row still holds it; a pattern that gives up on a long input (PCRE backtrack limit) is no match. Neither ever turns into a 500.
- **Nova keeps working**: Nova's own requests (rich-text fields send HTML on purpose) are not scanned unless you turn it on, and the Aegis settings are never scanned, so a pattern that is too broad can always be fixed.
- **Exclusions**: input names or dotted paths (`content`, `post.body`) and headers that are never scanned. `Cookie` and `Accept` are never scanned.
- **Cheap on every request**: the settings are cached (no database query per request) and cleared as soon as they are saved.
- **Logs without values**: a blocked request is logged with the IP, the method and where each match was (`query.q (xss)`), never the value.
- **Dashboard and checks**: a status line on the Aegis dashboard, and a check that warns about a saved pattern that is skipped or a page that is gone (also in `php artisan aegis:check`).

## 📦 Requirements

- PHP 8.2 or higher
- Laravel 12
- Laravel Nova 5
- [Aegis](https://github.com/wobqqq/nova-aegis) 1.1 or later (installed with the module)

## 📥 Installation

```bash
composer require wobqqq/nova-aegis-input-sanitizer
php artisan migrate
```

The service provider is discovered automatically; `php artisan migrate` creates the Aegis settings table if the core is new to the application. The module adds no table of its own.

Then open **Aegis → Settings** in Nova, review the **Input Sanitizer** section and switch on **Scan the requests**. Nothing is blocked until you do.

To change the built-in page for a blocked request, publish it, or name any view of your own in the settings:

```bash
php artisan vendor:publish --tag=aegis-input-sanitizer-views
```

## ⚙️ Configuration

Everything is set in the **Input Sanitizer** section of the Aegis settings:

| Setting | Default | |
|---|---|---|
| Scan the requests | off | Nothing is scanned until it is on. |
| Block at score | `1` | Refuse a request once this many pattern matches are found in it (1 to 1000). |
| Page shown to a blocked request | `aegis-input-sanitizer::blocked` | A Blade view name; it must exist when saved, and the built-in page is used if it is gone later. |
| Scan JSON request bodies | off | Off, JSON bodies pass unscanned (their query string, headers and URL are still scanned). |
| Scan Nova requests too | off | Nova's routes (`nova.path`, `nova-api`, `nova-vendor`). The Aegis settings API is never scanned. |
| Log blocked requests | on | A warning in the default log channel, without values. |
| Patterns | one per kind | A PCRE pattern with its delimiters (`~<script~i`), up to 1000 characters; leave one empty to turn that kind off. |
| Inputs never scanned | none | Input names or dotted paths, case-insensitive, up to 150. |
| Headers never scanned | none | Header names, case-insensitive, up to 150. `Cookie` and `Accept` always. |

The settings are cached in the store named by the core's `aegis.cache_store` (`AEGIS_CACHE_STORE`), the default store otherwise.

## 🆘 Recovery commands

If a pattern that is too broad blocks your site or your API, turn the module off from the console. The other settings are kept:

```bash
php artisan aegis:input-sanitizer:disable
```

The Aegis page in Nova stays reachable while the module is on (unless you enabled **Scan Nova requests too** and a pattern blocks Nova itself), so you can also fix the pattern there. `php artisan aegis:check` reports a saved pattern that is skipped.

## ⚠️ Good to know

- **Defence in depth, not a firewall.** It stops common payloads, not every attack. Keep validating input and escaping output in your own code.
- **Not scanned**: uploaded files, cookies, JSON bodies unless enabled, and Nova unless enabled. With JSON scanning off, a client can send its payload as JSON: turn it on once your APIs accept no HTML.
- **Headers are visitor input.** Every header a client sends is scanned, including `User-Agent` and `Referer`: a command-line client whose user agent starts with `curl/` matches the default command injection pattern. Exclude a header (`user-agent`) rather than weakening a pattern.
- **Proxies add headers.** A load balancer, a CDN or a proxy adds its own headers (`X-Forwarded-*`, `CF-*`, `Forwarded`, …) to every request. They are scanned like any other: if one of them carries characters the patterns flag, exclude it by name. Remember that any of these headers can also be sent, forged, by the client itself.
- **The logged IP is `$request->ip()`.** Behind a proxy or a load balancer it is the proxy's address unless the proxy is configured as a trusted proxy in Laravel (`trustProxies`); never trust forwarded headers from an untrusted source.
- **Rich-text editors send HTML on purpose.** Outside Nova, add their input names to *Inputs never scanned*, or every save is refused.
- **A loose threshold is a weaker filter.** A threshold above 1 lets a request through with fewer matches; each pattern counts once per value.
- **Long-running workers** (Octane, queues) read the settings again on every request; a save clears the cache for every server sharing the cache store.

## ⬆️ Upgrading

See [CHANGELOG.md](https://github.com/wobqqq/nova-aegis-input-sanitizer/blob/main/CHANGELOG.md).

## 🔒 Security

Please report a vulnerability privately, as described in [SECURITY.md](https://github.com/wobqqq/nova-aegis-input-sanitizer/blob/main/SECURITY.md).

## 🛠️ Development

The toolchain runs in Docker, the host needs nothing but `docker` and `make`. The module is developed against the core's checkout in the sibling directory `../nova-aegis` (a Composer `path` repository; the container mounts the parent directory). Nova is a licensed package, so installing the development dependencies needs your own Nova license: put its credentials in `auth.json` (gitignored) or run `composer config http-basic.nova.laravel.com <email> <license-key>`.

```bash
make install        # composer install
make code.fix       # composer normalize, Rector, PHP CS Fixer
make code.check     # composer validate/audit, php -l, PHP CS Fixer, Rector, PHPStan (level max)
make test.coverage  # Pest with coverage (90 % minimum)
make ready          # everything above
```
