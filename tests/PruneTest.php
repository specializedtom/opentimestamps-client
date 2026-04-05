<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Tests;

use OpenTimestamps\Client\Attestation\BitcoinBlockHeaderAttestation;
use OpenTimestamps\Client\Attestation\LitecoinBlockHeaderAttestation;
use OpenTimestamps\Client\Attestation\PendingAttestation;
use OpenTimestamps\Client\Attestation\UnknownAttestation;
use OpenTimestamps\Client\Command\Prune;
use OpenTimestamps\Client\Model\Timestamp;
use PHPUnit\Framework\TestCase;

final class PruneTest extends TestCase
{
    public function testDiscardAttestations(): void
    {
        $t = new Timestamp('');
        $t1 = $t->addOp("\x01");
        $t2 = $t->addOp("\x02");
        $t->addAttestation(new UnknownAttestation('unknown.', ''));
        $t1->addAttestation(new BitcoinBlockHeaderAttestation(1));
        $t2->addAttestation(new PendingAttestation('c2'));
        $t2->addAttestation(new PendingAttestation('c1'));

        Prune::discardAttestations($t, [UnknownAttestation::class, new PendingAttestation('c1')]);

        self::assertCount(0, $t->attestations());
        self::assertCount(1, $t1->attestations());
        self::assertEquals([new PendingAttestation('c2')], $t2->attestations());
    }

    public function testDiscardSuboptimal(): void
    {
        $t = new Timestamp('');
        $t1 = $t->addOp("\x01");
        $t2 = $t->addOp("\x02");
        $t3 = $t->addOp("\x03\x03");
        $t4 = $t->addOp("\x04");
        $t1->addAttestation(new BitcoinBlockHeaderAttestation(2));
        $t2->addAttestation(new BitcoinBlockHeaderAttestation(1));
        $t3->addAttestation(new LitecoinBlockHeaderAttestation(1));
        $t4->addAttestation(new LitecoinBlockHeaderAttestation(1));

        Prune::discardSuboptimal($t, BitcoinBlockHeaderAttestation::class);
        Prune::discardSuboptimal($t, LitecoinBlockHeaderAttestation::class);

        self::assertCount(0, $t1->attestations());
        self::assertCount(1, $t2->attestations());
        self::assertCount(0, $t3->attestations());
        self::assertCount(1, $t4->attestations());
    }

    public function testPruneTree(): void
    {
        $t = new Timestamp('');

        [$empty, $changed] = Prune::pruneTree($t);

        self::assertTrue($empty);
        self::assertFalse($changed);

        $t1 = $t->addOp("\x01");
        $t->addOp("\x02");
        $t1->addAttestation(new PendingAttestation('c'));

        [$empty, $changed] = Prune::pruneTree($t);

        self::assertFalse($empty);
        self::assertTrue($changed);
        self::assertCount(1, $t->ops());

        [, $changedAgain] = Prune::pruneTree($t);

        self::assertFalse($changedAgain);
    }

    public function testPruneTimestampFlow(): void
    {
        $t = new Timestamp('');
        $t1 = $t->addOp("\x01");
        $t2 = $t->addOp("\x02");
        $t3 = $t->addOp("\x03");
        $t21 = $t2->addOp("\x02");
        $t31 = $t3->addOp("\x03");
        $t1->addAttestation(new PendingAttestation('c1'));
        $t2->addAttestation(new PendingAttestation('c2'));
        $t3->addAttestation(new PendingAttestation('c3'));
        $t21->addAttestation(new BitcoinBlockHeaderAttestation(2));
        $t31->addAttestation(new BitcoinBlockHeaderAttestation(1));

        Prune::discardAttestations($t, [PendingAttestation::class]);
        Prune::discardSuboptimal($t, BitcoinBlockHeaderAttestation::class);
        Prune::discardSuboptimal($t, LitecoinBlockHeaderAttestation::class);
        [$empty, $changed] = Prune::pruneTree($t);

        self::assertFalse($empty);
        self::assertTrue($changed);
        self::assertCount(1, $t->ops());
        self::assertCount(1, $t3->ops());
        self::assertCount(1, $t31->attestations());
    }
}
