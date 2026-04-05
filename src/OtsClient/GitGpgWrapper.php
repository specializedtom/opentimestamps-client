<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient;

final class GitGpgWrapper
{
    public static function main(array $argv): int
    {
        if (in_array('--verify', $argv, true)) {
            fwrite(STDERR, "Verification mode is not yet ported to PHP.\n");
            return 1;
        }

        fwrite(STDERR, "Signing mode is not yet ported to PHP.\n");
        return 1;
    }
}
