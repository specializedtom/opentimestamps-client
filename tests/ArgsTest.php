<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Tests;

use OpenTimestamps\Client\OtsClient\Args;
use PHPUnit\Framework\TestCase;

final class ArgsTest extends TestCase
{
    public function testParsesCommonOptionsAndStampAlias(): void
    {
        $args = Args::parseOtsArgs(['-vv', '-q', '--whitelist', 'https://calendar.example', '--btc-testnet', 's', '--timeout', '9', '-m', '3', 'file1']);

        self::assertSame('stamp', $args->command);
        self::assertSame(1, $args->verbosity);
        self::assertSame('testnet', $args->btcNet);
        self::assertSame(9, $args->timeout);
        self::assertSame(3, $args->m);
        self::assertSame(['file1'], $args->files);
        self::assertContains('https://calendar.example', $args->effectiveWhitelist);
    }

    public function testParsesSocks5WithDefaultPort(): void
    {
        $args = Args::parseOtsArgs(['--socks5-proxy', 'localhost', 'info', 'stamp.ots']);

        self::assertSame(['host' => 'localhost', 'port' => 1080], $args->socks5Proxy);
        self::assertSame('info', $args->command);
        self::assertSame('stamp.ots', $args->infoFile);
    }

    public function testRejectsConflictingVerifyFlagsInPrune(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Args::parseOtsArgs(['prune', '--verify', 'btc', '--no-verify', 'x.ots']);
    }

    public function testParsesVerifyCommand(): void
    {
        $args = Args::parseOtsArgs(['verify', '-d', 'deadbeef', 'proof.ots']);

        self::assertSame('verify', $args->command);
        self::assertSame('deadbeef', $args->hexDigest);
        self::assertSame('proof.ots', $args->timestampFile);
    }
}
