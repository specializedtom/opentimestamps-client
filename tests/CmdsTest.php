<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Tests;

use OpenTimestamps\Client\Attestation\BitcoinBlockHeaderAttestation;
use OpenTimestamps\Client\Attestation\PendingAttestation;
use OpenTimestamps\Client\OtsClient\Cmds;
use OpenTimestamps\Client\OtsClient\ParsedArgs;
use OpenTimestamps\Client\Model\Timestamp;
use PHPUnit\Framework\TestCase;

final class CmdsTest extends TestCase
{
    public function testResolveAttestationsToVerifyDefaultsToBtc(): void
    {
        $verify = Cmds::resolveAttestationsToVerify([], false);

        self::assertSame([BitcoinBlockHeaderAttestation::class], $verify);
    }

    public function testResolveAttestationsToDiscardSupportsPendingUri(): void
    {
        $discard = Cmds::resolveAttestationsToDiscard(['pending:https://calendar.example']);

        self::assertCount(1, $discard);
        self::assertInstanceOf(PendingAttestation::class, $discard[0]);
        self::assertSame('https://calendar.example', $discard[0]->calendarUrl);
    }

    public function testPruneCommandReturnsChangedFlags(): void
    {
        $timestamp = new Timestamp('');
        $child = $timestamp->addOp("\x01");
        $child->addAttestation(new PendingAttestation('https://calendar.example'));

        $args = new ParsedArgs();
        $args->attestationsToDiscard = ['pending:*'];
        $args->attestationsToVerify = [];
        $args->noVerify = true;

        $result = Cmds::pruneCommand($timestamp, $args, null);

        self::assertTrue($result['empty']);
        self::assertTrue($result['changed']);
    }
}
