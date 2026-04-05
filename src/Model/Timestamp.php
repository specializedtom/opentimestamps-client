<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Model;

use OpenTimestamps\Client\Attestation\Attestation;

final class Timestamp
{
    /** @var array<string, Timestamp> */
    private array $ops = [];

    /** @var list<Attestation> */
    private array $attestations = [];

    public function __construct(private readonly string $msg)
    {
    }

    public function msg(): string
    {
        return $this->msg;
    }

    public function addOp(string $operation): self
    {
        return $this->ops[$operation] ??= new self($this->msg . $operation);
    }

    /** @return array<string, Timestamp> */
    public function ops(): array
    {
        return $this->ops;
    }

    public function removeOp(string $operation): void
    {
        unset($this->ops[$operation]);
    }

    public function addAttestation(Attestation $attestation): void
    {
        foreach ($this->attestations as $current) {
            if ($current == $attestation) {
                return;
            }
        }

        $this->attestations[] = $attestation;
    }

    /** @return list<Attestation> */
    public function attestations(): array
    {
        return $this->attestations;
    }

    public function removeAttestation(Attestation $attestation): void
    {
        $this->attestations = array_values(array_filter(
            $this->attestations,
            static fn (Attestation $current): bool => $current != $attestation
        ));
    }
}
