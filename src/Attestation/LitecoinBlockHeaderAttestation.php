<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Attestation;

final readonly class LitecoinBlockHeaderAttestation implements Attestation
{
    public function __construct(public int $height)
    {
    }

    public function compareTo(Attestation $other): int
    {
        if (!$other instanceof self) {
            throw new \InvalidArgumentException('Cannot compare different attestation types.');
        }

        return $this->height <=> $other->height;
    }
}
