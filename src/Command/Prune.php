<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Command;

use OpenTimestamps\Client\Attestation\Attestation;
use OpenTimestamps\Client\Attestation\PendingAttestation;
use OpenTimestamps\Client\Model\Timestamp;

final class Prune
{
    /**
     * @param list<class-string<Attestation>|Attestation> $attestationsToDiscard
     */
    public static function discardAttestations(Timestamp $timestamp, array $attestationsToDiscard): void
    {
        foreach ($timestamp->attestations() as $attestation) {
            if ($attestation instanceof PendingAttestation) {
                if (in_array(PendingAttestation::class, $attestationsToDiscard, true)
                    || self::containsAttestation($attestationsToDiscard, $attestation)) {
                    $timestamp->removeAttestation($attestation);
                }

                continue;
            }

            if (in_array($attestation::class, $attestationsToDiscard, true)) {
                $timestamp->removeAttestation($attestation);
            }
        }

        foreach ($timestamp->ops() as $stamp) {
            self::discardAttestations($stamp, $attestationsToDiscard);
        }
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    public static function pruneTree(Timestamp $timestamp): array
    {
        $prunable = count($timestamp->attestations()) === 0;
        $changed = false;

        foreach ($timestamp->ops() as $operation => $stamp) {
            [$stampPrunable, $stampChanged] = self::pruneTree($stamp);
            $changed = $changed || $stampChanged || $stampPrunable;

            if ($stampPrunable) {
                $timestamp->removeOp($operation);
            } else {
                $prunable = false;
            }
        }

        return [$prunable, $changed];
    }

    /**
     * @param class-string<Attestation> $targetAttestation
     * @return array{0: Attestation|null, 1: Timestamp|null, 2: int}
     */
    public static function discardSuboptimal(Timestamp $timestamp, string $targetAttestation): array
    {
        $optimalAttestation = null;
        $optimalNode = null;
        $optimalDepth = 0;

        foreach ($timestamp->ops() as $operation => $stamp) {
            [$currentOpt, $currentNode, $currentDepth] = self::discardSuboptimal($stamp, $targetAttestation);
            $currentDepth += 1 + strlen($operation);

            if ($currentOpt === null || $currentNode === null) {
                continue;
            }

            if ($optimalAttestation === null || $optimalNode === null) {
                $optimalAttestation = $currentOpt;
                $optimalNode = $currentNode;
                $optimalDepth = $currentDepth;
                continue;
            }

            $comparison = $currentOpt->compareTo($optimalAttestation);
            if ($comparison > 0) {
                $currentNode->removeAttestation($currentOpt);
            } elseif ($comparison < 0) {
                $optimalNode->removeAttestation($optimalAttestation);
                $optimalAttestation = $currentOpt;
                $optimalNode = $currentNode;
                $optimalDepth = $currentDepth;
            } elseif ($currentDepth < $optimalDepth) {
                $optimalNode->removeAttestation($optimalAttestation);
                $optimalAttestation = $currentOpt;
                $optimalNode = $currentNode;
                $optimalDepth = $currentDepth;
            } else {
                $currentNode->removeAttestation($currentOpt);
            }
        }

        foreach ($timestamp->attestations() as $attestation) {
            if (!$attestation instanceof $targetAttestation) {
                continue;
            }

            if ($optimalAttestation === null || $optimalNode === null) {
                $optimalAttestation = $attestation;
                $optimalNode = $timestamp;
                continue;
            }

            $comparison = $attestation->compareTo($optimalAttestation);
            if ($comparison > 0) {
                $timestamp->removeAttestation($attestation);
            } else {
                $optimalNode->removeAttestation($optimalAttestation);
                $optimalAttestation = $attestation;
                $optimalNode = $timestamp;
            }
        }

        return [$optimalAttestation, $optimalNode, $optimalDepth];
    }

    private static function containsAttestation(array $haystack, Attestation $needle): bool
    {
        foreach ($haystack as $item) {
            if ($item instanceof Attestation && $item == $needle) {
                return true;
            }
        }

        return false;
    }
}
