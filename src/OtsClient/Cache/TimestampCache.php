<?php

declare(strict_types=1);

namespace OpenTimestamps\Client\OtsClient\Cache;

final class TimestampCache
{
    public function __construct(private readonly ?string $path)
    {
        if ($this->path === null) {
            return;
        }

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        $versionFile = $this->path . '/version';
        if (!file_exists($versionFile)) {
            file_put_contents($versionFile, "1.0\n");
        }
    }

    public function get(string $commitment): ?string
    {
        if ($this->path === null || strlen($commitment) > 128) {
            return null;
        }

        $filename = $this->commitmentToFilename($commitment);
        if (!file_exists($filename)) {
            return null;
        }

        return file_get_contents($filename) ?: null;
    }

    public function merge(string $commitment, string $timestamp): void
    {
        if ($this->path === null) {
            return;
        }

        $filename = $this->commitmentToFilename($commitment);
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($filename, $timestamp);
    }

    private function commitmentToFilename(string $commitment): string
    {
        $hex = bin2hex($commitment);

        return sprintf('%s/%s/%s/%s/%s/%s', $this->path, substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2), substr($hex, 6, 2), $hex);
    }
}
