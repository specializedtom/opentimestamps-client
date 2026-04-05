<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\Tests;

use OpenTimestamps\Client\OtsClient\Git;
use PHPUnit\Framework\TestCase;

final class GitTest extends TestCase
{
    public function testHashSignedCommitMatchesDoubleSha256Composition(): void
    {
        for ($i = 0; $i < 64; $i++) {
            $gitCommit = random_bytes($i);
            $gpgSignature = random_bytes($i);

            $expected = hash('sha256', hash('sha256', $gitCommit, true) . hash('sha256', $gpgSignature, true), true);
            self::assertSame($expected, Git::hashSignedCommit($gitCommit, $gpgSignature));
        }
    }

    public function testExtractSignatureFromGitCommit(): void
    {
        $signedCommit = "tree abc\n"
            . "author test <test@example.com>\n"
            . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
            . " iQEz\n"
            . " -----END PGP SIGNATURE-----\n"
            . "\n"
            . "message\n";

        [$unsigned, $sig] = Git::extractSignatureFromGitCommit($signedCommit);

        self::assertStringNotContainsString('gpgsig ', $unsigned);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $sig);
    }
}
