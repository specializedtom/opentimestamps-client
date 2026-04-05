# OpenTimestamps Client (PHP)

Command-line and library tooling for OpenTimestamps, now packaged as a **PHP 8.4 Composer project**.

## Requirements

- PHP 8.4+
- [Composer](https://getcomposer.org/)

## Installation

```bash
composer install
```

## CLI

The repository exposes a Composer binary named `ots`:

```bash
./vendor/bin/ots
```

## Development

Run tests:

```bash
composer test
```

## What changed from the Python version?

- Packaging migrated from `setup.py`/`requirements.txt` to `composer.json`.
- Source code is now under `src/` with PSR-4 autoloading.
- The timestamp prune logic has been ported to PHP and covered with PHPUnit tests.

## License

LGPL-3.0-or-later.
