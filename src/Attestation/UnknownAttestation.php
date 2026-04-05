<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Attestation;

final readonly class UnknownAttestation implements Attestation
{
    public function __construct(public string $tag, public string $payload)
    {
    }

    public function compareTo(Attestation $other): int
    {
        if (!$other instanceof self) {
            throw new \InvalidArgumentException('Cannot compare different attestation types.');
        }

        return [$this->tag, $this->payload] <=> [$other->tag, $other->payload];
    }
}
