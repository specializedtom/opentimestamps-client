<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient;

final class Git
{
    private const ASCII_ARMOR_HEADER = "-----BEGIN OPENTIMESTAMPS GIT TIMESTAMP-----\n\n";
    private const ASCII_ARMOR_FOOTER = "-----END OPENTIMESTAMPS GIT TIMESTAMP-----\n";

    public static function hashSignedCommit(string $gitCommit, string $gpgSignature): string
    {
        return hash('sha256', hash('sha256', $gitCommit, true) . hash('sha256', $gpgSignature, true), true);
    }

    public static function writeAsciiArmored(string $serializedTimestamp, int $minorVersion = 1): string
    {
        $header = chr(1) . chr($minorVersion);
        $encoded = chunk_split(base64_encode($header . $serializedTimestamp), 64, "\n");

        return self::ASCII_ARMOR_HEADER . $encoded . self::ASCII_ARMOR_FOOTER;
    }

    /**
     * @return array{0:int|null,1:int|null,2:string|null}
     */
    public static function deserializeAsciiArmoredTimestamp(string $gitCommit, string $gpgSignature): array
    {
        $start = strpos($gpgSignature, self::ASCII_ARMOR_HEADER);
        if ($start === false) {
            return [null, null, null];
        }

        $end = strpos($gpgSignature, "\n" . self::ASCII_ARMOR_FOOTER, $start);
        if ($end === false) {
            return [null, null, null];
        }

        $encoded = substr($gpgSignature, $start + strlen(self::ASCII_ARMOR_HEADER), $end - ($start + strlen(self::ASCII_ARMOR_HEADER)));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) < 2) {
            return [null, null, null];
        }

        $majorVersion = ord($decoded[0]);
        $minorVersion = ord($decoded[1]);
        if ($majorVersion !== 1) {
            return [null, null, null];
        }

        $initialMessage = self::hashSignedCommit($gitCommit, substr($gpgSignature, 0, $start));
        $timestampPayload = substr($decoded, 2);

        return [$majorVersion, $minorVersion, $initialMessage . $timestampPayload];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function extractSignatureFromGitCommit(string $signedGitCommit): array
    {
        $unsignedLines = [];
        $signatureLines = [];

        $foundSignature = false;
        $signatureDone = false;

        foreach (explode("\n", $signedGitCommit) as $line) {
            if ($foundSignature && $signatureDone) {
                $unsignedLines[] = $line;
                continue;
            }

            if ($foundSignature && !$signatureDone) {
                if ($line !== '') {
                    $signatureLines[] = substr($line, 1);
                } else {
                    $unsignedLines[] = $line;
                    $signatureDone = true;
                }

                continue;
            }

            if (str_starts_with($line, 'gpgsig ')) {
                $foundSignature = true;
                $signatureLines[] = substr($line, strlen('gpgsig '));
                continue;
            }

            $unsignedLines[] = $line;
        }

        return [implode("\n", $unsignedLines), implode("\n", $signatureLines) . "\n"];
    }
}
