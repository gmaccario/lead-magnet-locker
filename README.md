# Lead Magnet Locker

[![WordPress plugin version](https://img.shields.io/wordpress/plugin/v/lead-magnet-locker)](https://wordpress.org/plugins/lead-magnet-locker/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/lead-magnet-locker)](https://wordpress.org/plugins/lead-magnet-locker/)
[![Required PHP](https://img.shields.io/wordpress/plugin/required-php/lead-magnet-locker)](https://wordpress.org/plugins/lead-magnet-locker/)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENCE)

A WordPress plugin that locks downloads behind an email form. Visitors enter their email address and get a unique, time-limited download link; you get a list of leads you can search and export as CSV.

**Install it from wordpress.org:** <https://wordpress.org/plugins/lead-magnet-locker/>. The user documentation, FAQ and changelog are in [`README.txt`](README.txt).

```text
[lead_magnet file="guide.pdf" title="Get the free guide" button_text="Download"]
```

## Features

- Files kept in a private, randomly named folder: no direct URL access, also on Nginx
- The public form sends an opaque token, never the file name
- Unique download links tied to a nonce, served through PHP
- Several forms per page, optional site-wide schedule
- Download history with search, CSV export (formula-injection safe) and statistics
- No external services: everything stays in your WordPress database

## Requirements

- WordPress 6.7 or later (tested up to 7.1)
- PHP 8.1 or later

## Development

```sh
composer install
```

| Command | What it does |
|---|---|
| `composer lint` | WordPress Coding Standards (`phpcs.xml`) |
| `composer fix` | Fix what `phpcbf` can fix automatically |
| `composer analyse` | PHPStan, level 8, with WordPress stubs |
| `composer test` | Unit tests: domain, use cases, architecture rules |
| `composer qa` | All of the above |
| `composer test:e2e` | End-to-end tests against a running WordPress site |

### End-to-end tests

The E2E suite drives a real WordPress site over HTTP, as visitors, an administrator and a subscriber: shortcode output, submissions, downloads, direct-URL blocking, uploads, the storage migration, settings, history, CSV export and the form's JavaScript (headless Chrome). HTML snapshots catch unintended markup changes.

It needs:

- a WordPress site with the plugin active (default `http://localhost:85`);
- a way to run `tests/E2E/fixture.php` with the PHP CLI inside that site, which creates temporary users, pages and files and restores everything afterwards;
- Chrome, for the browser test (skipped when missing).

| Variable | Default |
|---|---|
| `LML_E2E_BASE_URL` | `http://localhost:85` |
| `LML_E2E_FIXTURE` | `docker compose … exec -T webserver php …/tests/E2E/fixture.php` |
| `LML_E2E_CHROME` | `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome` |
| `LML_E2E_SNAPSHOTS` | `tests/E2E/__snapshots__` (git-ignored; created on the first run) |
| `LML_E2E_UPDATE_SNAPSHOTS` | Set to `1` to re-record snapshots after an intended change |

Use a local site only: the tests create and delete data.

## Architecture

The code follows Clean Architecture. Dependencies point inward only:

```text
src/
├── Domain/          Entities and rules, plain PHP (DownloadRecord, Schedule, FileTypes, …)
├── Application/     Use cases (SubmitEmail, UploadFile, ResolveDownload, …) and ports (interfaces)
├── Infrastructure/  Adapters implementing the ports with WordPress (wpdb, uploads folder, options)
├── Presentation/    WordPress-facing controllers and endpoints (admin pages, shortcode, AJAX, downloads)
└── Plugin.php       Composition root: builds the services and registers every hook
```

- **Domain** depends on nothing; **Application** only on Domain.
- Use cases return error codes, not messages; Presentation words and translates them.
- `tests/Unit/Architecture/LayersTest.php` fails the build if a layer imports an outer one, or if Domain or Application calls a WordPress function.
- Views live in `views/`; public helpers for themes in `functions.php`; class names of earlier versions are aliased in `compat.php`.

Public contract kept stable across versions: the shortcode and its attributes, hooks and filters, AJAX and `admin-post` actions, database tables, options and `lead_magnet_locker_file_exists()`. See *Frequently Used Hooks*, *Developer Functions* and *Configuration Constants* in [`README.txt`](README.txt).

## Releasing

Releases are published to the wordpress.org SVN repository. Files marked `export-ignore` in `.gitattributes` (tests, tool configs, this file) stay out of the package.

1. Update the version in `lead-magnet-locker.php` (header and `LML_PLUGIN_VERSION`) and `Stable tag` in `README.txt`; add the changelog and upgrade notice.
2. Run `composer qa` and `composer test:e2e`.
3. Build: `git archive HEAD | tar -x -C build/` then `composer install --no-dev --optimize-autoloader` in `build/`.
4. Copy the build to SVN `trunk/`, `svn cp trunk tags/X.Y.Z`, then `svn ci`.

## Contributing

Issues and pull requests are welcome. Please run `composer qa` (and, if you can, `composer test:e2e`) before opening a pull request, and keep the public contract above backward compatible.

Found a security issue? Please do **not** open a public issue; see [SECURITY.md](SECURITY.md).

## License

GPL-2.0-or-later. See [LICENCE](LICENCE).
