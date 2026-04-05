<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient;

final class Args
{
    public function __construct(
        public readonly int $verbosity,
        public readonly string $command,
        /** @var list<string> */
        public readonly array $arguments,
    ) {
    }

    public static function parse(array $argv): self
    {
        $verbosity = 0;
        $tokens = [];

        foreach ($argv as $arg) {
            if ($arg === '-v' || $arg === '--verbose') {
                $verbosity++;
                continue;
            }

            if ($arg === '-q' || $arg === '--quiet') {
                $verbosity--;
                continue;
            }

            $tokens[] = $arg;
        }

        $command = $tokens[0] ?? '';
        $arguments = array_slice($tokens, 1);

        return new self($verbosity, $command, $arguments);
    }
}
