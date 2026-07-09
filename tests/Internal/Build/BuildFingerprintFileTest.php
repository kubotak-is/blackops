<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Build;

use BlackOps\Internal\Build\BuildFingerprintFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BuildFingerprintFileTest extends TestCase
{
    public function testWritesAndMatchesFingerprintFile(): void
    {
        $path = $this->path('fingerprint');
        $file = new BuildFingerprintFile();

        $file->write($path, 'hash-value');

        self::assertTrue($file->matches($path, 'hash-value'));
        self::assertFalse($file->matches($path, 'other-value'));
    }

    public function testMissingFingerprintFileDoesNotMatch(): void
    {
        self::assertFalse(new BuildFingerprintFile()->matches($this->path('missing'), 'hash-value'));
    }

    public function testRejectsMissingFingerprintDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BuildFingerprintFile()->write($this->path('missing') . '/fingerprint', 'hash-value');
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-build-fingerprint-file-' . $name . '-' . bin2hex(random_bytes(8));
    }
}
