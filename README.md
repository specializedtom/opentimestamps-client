# OpenTimestamps Client (PHP)

Command-line and library tooling for OpenTimestamps, packaged as a **PHP 8.4 Composer project** with PSR-4 classes.

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

## PSR-4 conversion of legacy `otsclient` modules

Python modules previously under `otsclient/` were converted into PHP classes under `src/OtsClient/`:

- `otsclient/args.py` → `OpenTimestamps\Client\OtsClient\Args`
- `otsclient/cache.py` → `OpenTimestamps\Client\OtsClient\Cache\TimestampCache`
- `otsclient/cmds.py` → `OpenTimestamps\Client\OtsClient\Cmds`
- `otsclient/git.py` → `OpenTimestamps\Client\OtsClient\Git`
- `otsclient/git_gpg_wrapper.py` → `OpenTimestamps\Client\OtsClient\GitGpgWrapper`
- `otsclient/ots.py` → `OpenTimestamps\Client\OtsClient\Ots`

Additionally, prune logic is implemented in `OpenTimestamps\Client\Command\Prune` and covered by PHPUnit tests.

## Development

Run tests:

```bash
composer test
```

Run lint checks:

```bash
find src tests scripts -name '*.php' -print0 | xargs -0 -n1 php -l
```

## License

LGPL-3.0-or-later.
