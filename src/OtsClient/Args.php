<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient;

use OpenTimestamps\Client\OtsClient\Cache\TimestampCache;

final class Args
{
    /** @var list<string> */
    private const DEFAULT_CALENDAR_WHITELIST = [
        'https://a.pool.opentimestamps.org',
        'https://b.pool.opentimestamps.org',
        'https://a.pool.eternitywall.com',
        'https://ots.btc.catallaxy.com',
    ];

    /**
     * @param list<string> $rawArgs
     */
    public static function parseOtsArgs(array $rawArgs): ParsedArgs
    {
        $state = new ParsedArgs();
        $tokens = $rawArgs;

        while ($tokens !== []) {
            $token = array_shift($tokens);
            if ($token === null) {
                break;
            }

            if (!str_starts_with($token, '-')) {
                $state->command = self::normalizeCommand($token);
                break;
            }

            self::parseCommonOption($state, $token, $tokens);
        }

        if ($state->command === null) {
            return self::handleCommonOptions($state);
        }

        self::parseSubcommandOptions($state, $tokens);

        return self::handleCommonOptions($state);
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseCommonOption(ParsedArgs $state, string $token, array &$tokens): void
    {
        if (preg_match('/^-v+$/', $token) === 1) {
            $state->verbose += strlen($token) - 1;
            return;
        }

        if (preg_match('/^-q+$/', $token) === 1) {
            $state->quiet += strlen($token) - 1;
            return;
        }

        switch ($token) {
            case '--version':
                $state->showVersion = true;
                return;
            case '-q':
            case '--quiet':
                $state->quiet++;
                return;
            case '-v':
            case '--verbose':
                $state->verbose++;
                return;
            case '-l':
            case '--whitelist':
                $state->whitelist[] = self::requireValue($token, $tokens);
                return;
            case '--no-default-whitelist':
                $state->noDefaultWhitelist = true;
                return;
            case '--cache':
                $state->cachePath = self::requireValue($token, $tokens);
                return;
            case '--no-cache':
                $state->cachePath = null;
                return;
            case '--btc-testnet':
                $state->btcNet = 'testnet';
                return;
            case '--btc-regtest':
                $state->btcNet = 'regtest';
                return;
            case '--no-bitcoin':
                $state->useBitcoin = false;
                return;
            case '-w':
            case '--wait':
                $state->wait = true;
                return;
            case '--wait-interval':
                $state->waitInterval = self::requireIntValue($token, $tokens);
                return;
            case '--socks5-proxy':
                $state->socks5Proxy = self::parseSocks5Proxy(self::requireValue($token, $tokens));
                return;
            case '--bitcoin-node':
                $state->bitcoinNode = self::requireValue($token, $tokens);
                return;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown option: %s', $token));
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseSubcommandOptions(ParsedArgs $state, array $tokens): void
    {
        switch ($state->command) {
            case 'stamp':
                self::parseStampOptions($state, $tokens);
                return;
            case 'upgrade':
                self::parseUpgradeOptions($state, $tokens);
                return;
            case 'verify':
                self::parseVerifyOptions($state, $tokens);
                return;
            case 'info':
                self::parseInfoOptions($state, $tokens);
                return;
            case 'prune':
                self::parsePruneOptions($state, $tokens);
                return;
            case 'git-extract':
                self::parseGitExtractOptions($state, $tokens);
                return;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown subcommand: %s', $state->command));
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseStampOptions(ParsedArgs $state, array $tokens): void
    {
        while ($tokens !== []) {
            $token = array_shift($tokens);
            if ($token === null) {
                break;
            }

            if (!str_starts_with($token, '-')) {
                $state->files[] = $token;
                continue;
            }

            switch ($token) {
                case '-c':
                case '--calendar':
                    $state->calendarUrls[] = self::requireValue($token, $tokens);
                    break;
                case '-b':
                case '--btc-wallet':
                    $state->useBtcWallet = true;
                    break;
                case '--timeout':
                    $state->timeout = self::requireIntValue($token, $tokens);
                    break;
                case '-m':
                    $state->m = self::requireIntValue($token, $tokens);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown stamp option: %s', $token));
            }
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseUpgradeOptions(ParsedArgs $state, array $tokens): void
    {
        while ($tokens !== []) {
            $token = array_shift($tokens);
            if ($token === null) {
                break;
            }

            if (!str_starts_with($token, '-')) {
                $state->files[] = $token;
                continue;
            }

            switch ($token) {
                case '-c':
                case '--calendar':
                    $state->calendarUrls[] = self::requireValue($token, $tokens);
                    break;
                case '-n':
                case '--dry-run':
                    $state->dryRun = true;
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown upgrade option: %s', $token));
            }
        }

        if ($state->files === []) {
            throw new \InvalidArgumentException('upgrade requires at least one timestamp file.');
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseVerifyOptions(ParsedArgs $state, array $tokens): void
    {
        while ($tokens !== []) {
            $token = array_shift($tokens);
            if ($token === null) {
                break;
            }

            if (!str_starts_with($token, '-')) {
                $state->timestampFile = $token;
                continue;
            }

            switch ($token) {
                case '-f':
                    if ($state->hexDigest !== null) {
                        throw new \InvalidArgumentException('Use either -f or -d, not both.');
                    }

                    $state->targetFile = self::requireValue($token, $tokens);
                    break;
                case '-d':
                    if ($state->targetFile !== null) {
                        throw new \InvalidArgumentException('Use either -f or -d, not both.');
                    }

                    $state->hexDigest = self::requireValue($token, $tokens);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown verify option: %s', $token));
            }
        }

        if ($state->timestampFile === null) {
            throw new \InvalidArgumentException('verify requires a timestamp file.');
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseInfoOptions(ParsedArgs $state, array $tokens): void
    {
        if (count($tokens) !== 1) {
            throw new \InvalidArgumentException('info expects exactly one file path.');
        }

        $state->infoFile = $tokens[0];
    }

    /**
     * @param list<string> $tokens
     */
    private static function parsePruneOptions(ParsedArgs $state, array $tokens): void
    {
        while ($tokens !== []) {
            $token = array_shift($tokens);
            if ($token === null) {
                break;
            }

            if (!str_starts_with($token, '-')) {
                $state->timestampFile = $token;
                continue;
            }

            switch ($token) {
                case '--verify':
                    if ($state->noVerify) {
                        throw new \InvalidArgumentException('Cannot combine --verify with --no-verify.');
                    }

                    $state->attestationsToVerify[] = self::requireValue($token, $tokens);
                    break;
                case '--no-verify':
                    if ($state->attestationsToVerify !== []) {
                        throw new \InvalidArgumentException('Cannot combine --verify with --no-verify.');
                    }

                    $state->noVerify = true;
                    break;
                case '--discard':
                    $state->attestationsToDiscard[] = self::requireValue($token, $tokens);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown prune option: %s', $token));
            }
        }

        if ($state->timestampFile === null) {
            throw new \InvalidArgumentException('prune requires a timestamp file.');
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseGitExtractOptions(ParsedArgs $state, array $tokens): void
    {
        while ($tokens !== []) {
            $token = array_shift($tokens);
            if ($token === null) {
                break;
            }

            if ($token === '--annex') {
                $state->annex = true;
                continue;
            }

            $state->gitExtractArguments[] = $token;
        }

        if ($state->gitExtractArguments === []) {
            throw new \InvalidArgumentException('git-extract requires at least a PATH argument.');
        }
    }

    private static function handleCommonOptions(ParsedArgs $args): ParsedArgs
    {
        $args->verbosity = $args->verbose - $args->quiet;

        if ($args->cachePath !== null) {
            $expanded = str_starts_with($args->cachePath, '~')
                ? str_replace('~', (string) getenv('HOME'), $args->cachePath)
                : $args->cachePath;
            $args->cachePath = self::normalizePath($expanded);
        }

        $args->cache = new TimestampCache($args->cachePath);

        $defaultWhitelist = $args->noDefaultWhitelist ? [] : self::DEFAULT_CALENDAR_WHITELIST;
        $args->effectiveWhitelist = array_values(array_unique(array_merge($defaultWhitelist, $args->whitelist)));

        return $args;
    }

    private static function normalizeCommand(string $command): string
    {
        return match ($command) {
            's' => 'stamp',
            'u' => 'upgrade',
            'v' => 'verify',
            'i' => 'info',
            'p' => 'prune',
            default => $command,
        };
    }

    /**
     * @param list<string> $tokens
     */
    private static function requireValue(string $option, array &$tokens): string
    {
        $value = array_shift($tokens);
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException(sprintf('Missing value for %s', $option));
        }

        return $value;
    }

    /**
     * @param list<string> $tokens
     */
    private static function requireIntValue(string $option, array &$tokens): int
    {
        $value = self::requireValue($option, $tokens);
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new \InvalidArgumentException(sprintf('Expected integer for %s, got %s', $option, $value));
        }

        return (int) $value;
    }

    /**
     * @return array{host:string,port:int}
     */
    private static function parseSocks5Proxy(string $rawProxy): array
    {
        $parts = explode(':', $rawProxy, 2);
        $host = $parts[0];

        if ($host === '') {
            throw new \InvalidArgumentException('SOCKS5 proxy host cannot be empty.');
        }

        if (!isset($parts[1])) {
            return ['host' => $host, 'port' => 1080];
        }

        if (!preg_match('/^\d+$/', $parts[1])) {
            throw new \InvalidArgumentException(sprintf('SOCKS5 proxy port must be an integer; got %s', $parts[1]));
        }

        return ['host' => $host, 'port' => (int) $parts[1]];
    }

    private static function normalizePath(string $path): string
    {
        $segments = [];
        $prefix = str_starts_with($path, '/') ? '/' : '';

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . implode('/', $segments);
    }
}

final class ParsedArgs
{
    public bool $showVersion = false;
    public int $quiet = 0;
    public int $verbose = 0;
    public int $verbosity = 0;

    /** @var list<string> */
    public array $whitelist = [];
    public bool $noDefaultWhitelist = false;

    public ?string $cachePath;
    public TimestampCache $cache;

    public string $btcNet = 'mainnet';
    public bool $useBitcoin = true;
    public bool $wait = false;
    public int $waitInterval = 30;

    /** @var array{host:string,port:int}|null */
    public ?array $socks5Proxy = null;
    public ?string $bitcoinNode = null;

    public ?string $command = null;

    /** @var list<string> */
    public array $calendarUrls = [];
    public bool $useBtcWallet = false;
    /** @var list<string> */
    public array $files = [];
    public int $timeout = 5;
    public int $m = 2;
    public bool $dryRun = false;
    public ?string $targetFile = null;
    public ?string $hexDigest = null;
    public ?string $timestampFile = null;
    public ?string $infoFile = null;

    /** @var list<string> */
    public array $attestationsToVerify = [];
    public bool $noVerify = false;
    /** @var list<string> */
    public array $attestationsToDiscard = [];

    public bool $annex = false;
    /** @var list<string> */
    public array $gitExtractArguments = [];

    /** @var list<string> */
    public array $effectiveWhitelist = [];

    public function __construct()
    {
        $this->cachePath = sprintf('%s/.cache/ots', (string) getenv('HOME'));
    }
}
