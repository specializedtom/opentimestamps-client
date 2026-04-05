<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient;

final class Ots
{
    public static function main(array $argv): int
    {
        $args = Args::parse(array_slice($argv, 1));

        if ($args->command === '' || in_array($args->command, ['-h', '--help'], true)) {
            self::printHelp();
            return 0;
        }

        return match ($args->command) {
            'prune' => self::runPrune(),
            'stamp' => self::unsupported('stamp'),
            'upgrade' => self::unsupported('upgrade'),
            'verify' => self::unsupported('verify'),
            'info' => self::unsupported('info'),
            default => self::unknown($args->command),
        };
    }

    private static function runPrune(): int
    {
        fwrite(STDOUT, "Prune command is available as a library API in OpenTimestamps\\Client\\Command\\Prune.\n");
        return 0;
    }

    private static function unsupported(string $command): int
    {
        fwrite(STDERR, sprintf("Command '%s' is recognized but not ported yet.\n", $command));
        return 1;
    }

    private static function unknown(string $command): int
    {
        fwrite(STDERR, sprintf("Unknown command '%s'.\n", $command));
        self::printHelp();
        return 1;
    }

    private static function printHelp(): void
    {
        fwrite(STDOUT, "Usage: ots <command> [options]\n");
        fwrite(STDOUT, "Commands: stamp, upgrade, verify, info, prune\n");
    }
}
