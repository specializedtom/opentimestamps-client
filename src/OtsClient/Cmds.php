<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient;

use OpenTimestamps\Client\Attestation\BitcoinBlockHeaderAttestation;
use OpenTimestamps\Client\Attestation\LitecoinBlockHeaderAttestation;
use OpenTimestamps\Client\Attestation\PendingAttestation;
use OpenTimestamps\Client\Attestation\UnknownAttestation;
use OpenTimestamps\Client\Command\Prune;
use OpenTimestamps\Client\Model\Timestamp;

/**
 * PSR-4 port of the legacy Python otsclient/cmds.py command-layer behavior.
 *
 * NOTE:
 * - Pruning/attestation selection behavior is fully implemented.
 * - Network/Bitcoin/OpenTimestamps-wire-format operations are exposed through
 *   injectable callables so the command layer is portable without framework lock-in.
 */
final class Cmds
{
    /** @var list<string> */
    private const DEFAULT_CALENDARS = [
        'https://a.pool.opentimestamps.org',
        'https://b.pool.opentimestamps.org',
        'https://a.pool.eternitywall.com',
        'https://ots.btc.catallaxy.com',
    ];

    /**
     * Create a remote calendar client from URI.
     *
     * @param callable(string):object $calendarFactory
     */
    public static function remoteCalendar(string $calendarUri, callable $calendarFactory): object
    {
        return $calendarFactory($calendarUri);
    }

    /**
     * Port of create_timestamp(): validates M-of-N semantics and merges remote responses.
     *
     * @param list<string> $calendarUrls
     * @param callable(string,string,int):Timestamp $submitFn (calendarUrl, commitment, timeout)
     * @param callable(string):void|null $logFn
     */
    public static function createTimestamp(
        Timestamp $timestamp,
        array $calendarUrls,
        ParsedArgs $args,
        callable $submitFn,
        ?callable $logFn = null,
    ): void {
        $m = $args->m;
        $n = count($calendarUrls);

        if ($m > $n || $m <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'm (%d) cannot be greater than available calendars (%d) and must be > 0',
                $m,
                $n
            ));
        }

        $merged = 0;
        foreach ($calendarUrls as $calendarUrl) {
            try {
                $remoteStamp = $submitFn($calendarUrl, $timestamp->msg(), $args->timeout);
                $timestamp->merge($remoteStamp);
                $merged++;
            } catch (\Throwable $exception) {
                $logFn?->__invoke(sprintf('Calendar %s failed: %s', $calendarUrl, $exception->getMessage()));
            }
        }

        if ($merged < $m) {
            throw new \RuntimeException(sprintf(
                'Failed to create timestamp: need at least %d attestations but received %d.',
                $m,
                $merged
            ));
        }
    }

    /**
     * Port of submit_async(). In PHP we expose synchronous callback wiring and let callers parallelize if desired.
     *
     * @param callable(string,string,int):Timestamp $submitFn
     */
    public static function submit(
        string $calendarUrl,
        string $msg,
        int $timeout,
        callable $submitFn,
    ): Timestamp {
        return $submitFn($calendarUrl, $msg, $timeout);
    }

    /**
     * Port of stamp_command() orchestration without file/hash wire-format internals.
     *
     * @param list<string> $files
     * @param callable(string):Timestamp $fileToTimestamp
     * @param callable(list<Timestamp>):Timestamp $buildMerkleTree
     * @param callable(Timestamp,string):void $writeOutput
     * @param callable(string,string,int):Timestamp $submitFn
     */
    public static function stampCommand(
        ParsedArgs $args,
        array $files,
        callable $fileToTimestamp,
        callable $buildMerkleTree,
        callable $writeOutput,
        callable $submitFn,
        ?callable $logFn = null,
    ): void {
        $fileTimestamps = [];
        $merkleLeaves = [];

        foreach ($files as $file) {
            $fileTimestamp = $fileToTimestamp($file);
            $nonceNode = $fileTimestamp->addOp(random_bytes(16));
            $leaf = $nonceNode->addOp('sha256');
            $fileTimestamps[] = [$file, $fileTimestamp];
            $merkleLeaves[] = $leaf;
        }

        /** @var Timestamp $merkleTip */
        $merkleTip = $buildMerkleTree($merkleLeaves);

        $calendarUrls = $args->calendarUrls !== [] ? $args->calendarUrls : self::DEFAULT_CALENDARS;
        self::createTimestamp($merkleTip, $calendarUrls, $args, $submitFn, $logFn);

        foreach ($fileTimestamps as [$file, $timestamp]) {
            $writeOutput((string) $file . '.ots', $timestamp);
        }
    }

    public static function isTimestampComplete(Timestamp $stamp): bool
    {
        foreach ($stamp->allAttestations() as [, $attestation]) {
            if ($attestation instanceof BitcoinBlockHeaderAttestation) {
                return true;
            }
        }

        return false;
    }

    /**
     * Port of upgrade_timestamp(): merges cache + remote upgrades until complete or non-wait mode ends.
     *
     * @param callable(string):?Timestamp $cacheLookup
     * @param callable(Timestamp):void $cacheMerge
     * @param callable(string,string):?Timestamp $calendarLookup (calendarUrl, commitmentHex)
     * @param callable(string):void|null $logFn
     */
    public static function upgradeTimestamp(
        Timestamp $timestamp,
        ParsedArgs $args,
        callable $cacheLookup,
        callable $cacheMerge,
        callable $calendarLookup,
        ?callable $logFn = null,
    ): bool {
        $changed = false;
        $existingAttestations = self::attestationSet($timestamp);

        foreach (self::walkStamp($timestamp) as $subStamp) {
            $cached = $cacheLookup($subStamp->msg());
            if ($cached === null) {
                continue;
            }

            $subStamp->merge($cached);
        }

        $afterCache = self::attestationSet($timestamp);
        if (count(array_diff_key($afterCache, $existingAttestations)) > 0) {
            $changed = true;
            $existingAttestations = $afterCache;
        }

        while (!self::isTimestampComplete($timestamp)) {
            $foundNew = false;
            foreach (self::directlyVerified($timestamp) as $subStamp) {
                foreach ($subStamp->attestations() as $attestation) {
                    if (!$attestation instanceof PendingAttestation) {
                        continue;
                    }

                    $calendarUrls = $args->calendarUrls !== [] ? $args->calendarUrls : [$attestation->calendarUrl];
                    foreach ($calendarUrls as $calendarUrl) {
                        $upgraded = $calendarLookup($calendarUrl, bin2hex($subStamp->msg()));
                        if ($upgraded === null) {
                            continue;
                        }

                        $before = self::attestationSet($subStamp);
                        $subStamp->merge($upgraded);
                        $after = self::attestationSet($subStamp);

                        if (count(array_diff_key($after, $before)) > 0) {
                            $changed = true;
                            $foundNew = true;
                            $cacheMerge($upgraded);
                            $logFn?->__invoke(sprintf('Got new attestation(s) from %s', $calendarUrl));
                        }
                    }
                }
            }

            if (!$args->wait || !$foundNew) {
                break;
            }
        }

        return $changed;
    }

    /**
     * Port of verify_timestamp() flow with injectable block-header verification.
     *
     * @param callable(int):array<string,mixed> $loadBlockHeaderByHeight
     * @param callable(BitcoinBlockHeaderAttestation,string,array<string,mixed>):int $verifyBitcoinAttestation
     */
    public static function verifyTimestamp(
        Timestamp $timestamp,
        ParsedArgs $args,
        callable $loadBlockHeaderByHeight,
        callable $verifyBitcoinAttestation,
    ): bool {
        $good = false;
        $attestations = $timestamp->allAttestations();

        usort(
            $attestations,
            static function (array $a, array $b): int {
                $attA = $a[1];
                $attB = $b[1];

                $kA = $attA instanceof BitcoinBlockHeaderAttestation ? $attA->height : PHP_INT_MAX;
                $kB = $attB instanceof BitcoinBlockHeaderAttestation ? $attB->height : PHP_INT_MAX;

                return $kA <=> $kB;
            }
        );

        foreach ($attestations as [$msg, $attestation]) {
            if (!$attestation instanceof BitcoinBlockHeaderAttestation) {
                continue;
            }

            if (!$args->useBitcoin) {
                continue;
            }

            $header = $loadBlockHeaderByHeight($attestation->height);
            $verifyBitcoinAttestation($attestation, $msg, $header);
            $good = true;
            break;
        }

        return $good;
    }

    /**
     * Port helper from prune_command() for --verify list.
     *
     * @param list<string> $specifiers
     * @return list<class-string>
     */
    public static function resolveAttestationsToVerify(array $specifiers, bool $noVerify): array
    {
        if ($specifiers === []) {
            return $noVerify ? [] : [BitcoinBlockHeaderAttestation::class];
        }

        $resolved = [];
        foreach ($specifiers as $specifier) {
            if ($specifier === 'btc') {
                $resolved[] = BitcoinBlockHeaderAttestation::class;
                continue;
            }

            throw new \InvalidArgumentException(sprintf(
                "argument --verify: invalid choice '%s' (choose from 'btc')",
                $specifier
            ));
        }

        return $resolved;
    }

    /**
     * Port helper from prune_command() for --discard list.
     *
     * @param list<string> $specifiers
     * @return list<class-string|PendingAttestation>
     */
    public static function resolveAttestationsToDiscard(array $specifiers): array
    {
        if ($specifiers === []) {
            return [PendingAttestation::class];
        }

        $resolved = [];
        foreach ($specifiers as $specifier) {
            if ($specifier === 'btc') {
                $resolved[] = BitcoinBlockHeaderAttestation::class;
            } elseif ($specifier === 'ltc') {
                $resolved[] = LitecoinBlockHeaderAttestation::class;
            } elseif ($specifier === 'unknown') {
                $resolved[] = UnknownAttestation::class;
            } elseif (str_starts_with($specifier, 'pending:')) {
                $uri = substr($specifier, strlen('pending:'));
                $resolved[] = $uri === '*' ? PendingAttestation::class : new PendingAttestation($uri);
            } else {
                throw new \InvalidArgumentException(sprintf(
                    "argument --discard: invalid choice '%s' (choose from 'btc', 'ltc', 'unknown', 'pending:*', 'pending:uri')",
                    $specifier
                ));
            }
        }

        return $resolved;
    }

    /**
     * Port of prune_timestamp() pure behavior.
     *
     * @param list<class-string> $attestationsToVerify
     * @param list<class-string|PendingAttestation> $attestationsToDiscard
     * @param callable(Timestamp,list<class-string>):void|null $verifyAllAttestations
     * @return array{0: bool, 1: bool}
     */
    public static function pruneTimestamp(
        Timestamp $timestamp,
        array $attestationsToVerify,
        array $attestationsToDiscard,
        ?callable $verifyAllAttestations = null,
    ): array {
        if ($verifyAllAttestations !== null) {
            $verifyAllAttestations($timestamp, $attestationsToVerify);
        }

        Prune::discardAttestations($timestamp, $attestationsToDiscard);
        Prune::discardSuboptimal($timestamp, BitcoinBlockHeaderAttestation::class);
        Prune::discardSuboptimal($timestamp, LitecoinBlockHeaderAttestation::class);

        return Prune::pruneTree($timestamp);
    }

    /**
     * Port of prune_command() decision logic (without file serialization side-effects).
     *
     * @return array{empty:bool,changed:bool,verify:list<class-string>,discard:list<class-string|PendingAttestation>}
     */
    public static function pruneCommand(
        Timestamp $timestamp,
        ParsedArgs $args,
        ?callable $verifyAllAttestations = null,
    ): array {
        $verify = self::resolveAttestationsToVerify($args->attestationsToVerify, $args->noVerify);
        $discard = self::resolveAttestationsToDiscard($args->attestationsToDiscard);

        [$empty, $changed] = self::pruneTimestamp($timestamp, $verify, $discard, $verifyAllAttestations);

        return [
            'empty' => $empty,
            'changed' => $changed,
            'verify' => $verify,
            'discard' => $discard,
        ];
    }

    public static function verifyCommand(): never
    {
        throw new \RuntimeException('verify_command file deserialization path is not yet ported; use verifyTimestamp() with injected dependencies.');
    }

    public static function infoCommand(): never
    {
        throw new \RuntimeException('info_command detached timestamp deserialization path is not yet ported.');
    }

    public static function upgradeCommand(): never
    {
        throw new \RuntimeException('upgrade_command file migration path is not yet ported; use upgradeTimestamp() programmatically.');
    }

    public static function gitExtractCommand(): never
    {
        throw new \RuntimeException('git_extract_command Git object walking is not yet ported.');
    }

    /** @return list<Timestamp> */
    private static function walkStamp(Timestamp $timestamp): array
    {
        $result = [$timestamp];
        foreach ($timestamp->ops() as $subStamp) {
            array_push($result, ...self::walkStamp($subStamp));
        }

        return $result;
    }

    /** @return list<Timestamp> */
    private static function directlyVerified(Timestamp $timestamp): array
    {
        if ($timestamp->attestations() !== []) {
            return [$timestamp];
        }

        $result = [];
        foreach ($timestamp->ops() as $subStamp) {
            array_push($result, ...self::directlyVerified($subStamp));
        }

        return $result;
    }

    /**
     * @return array<string,true>
     */
    private static function attestationSet(Timestamp $timestamp): array
    {
        $set = [];
        foreach ($timestamp->allAttestations() as [, $attestation]) {
            $set[serialize($attestation)] = true;
        }

        return $set;
    }
}
