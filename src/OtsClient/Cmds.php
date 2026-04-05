<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient;

use OpenTimestamps\Client\Attestation\BitcoinBlockHeaderAttestation;
use OpenTimestamps\Client\Attestation\LitecoinBlockHeaderAttestation;
use OpenTimestamps\Client\Command\Prune;
use OpenTimestamps\Client\Model\Timestamp;

final class Cmds
{
    /**
     * @param list<class-string|object> $attestationsToDiscard
     */
    public static function discardAttestations(Timestamp $timestamp, array $attestationsToDiscard): void
    {
        Prune::discardAttestations($timestamp, $attestationsToDiscard);
    }

    /**
     * @param class-string $targetAttestation
     */
    public static function discardSuboptimal(Timestamp $timestamp, string $targetAttestation): void
    {
        Prune::discardSuboptimal($timestamp, $targetAttestation);
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    public static function pruneTree(Timestamp $timestamp): array
    {
        return Prune::pruneTree($timestamp);
    }

    /**
     * @param list<class-string|object> $attestationsToDiscard
     * @return array{0: bool, 1: bool}
     */
    public static function pruneTimestamp(Timestamp $timestamp, array $attestationsToDiscard): array
    {
        self::discardAttestations($timestamp, $attestationsToDiscard);
        self::discardSuboptimal($timestamp, BitcoinBlockHeaderAttestation::class);
        self::discardSuboptimal($timestamp, LitecoinBlockHeaderAttestation::class);

        return self::pruneTree($timestamp);
    }

    public static function stampCommand(): void
    {
        throw new \RuntimeException('Stamp command has not yet been ported from Python to PHP.');
    }

    public static function upgradeCommand(): void
    {
        throw new \RuntimeException('Upgrade command has not yet been ported from Python to PHP.');
    }

    public static function verifyCommand(): void
    {
        throw new \RuntimeException('Verify command has not yet been ported from Python to PHP.');
    }

    public static function infoCommand(): void
    {
        throw new \RuntimeException('Info command has not yet been ported from Python to PHP.');
    }

    public static function gitExtractCommand(): void
    {
        throw new \RuntimeException('Git extract command has not yet been ported from Python to PHP.');
    }
}
