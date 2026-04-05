<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Attestation;

interface Attestation
{
    public function compareTo(Attestation $other): int;
}
