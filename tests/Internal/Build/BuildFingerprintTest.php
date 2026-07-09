<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Build;

use BlackOps\Internal\Build\BuildFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BuildFingerprintTest extends TestCase
{
    public function testBuildsStableFingerprintFromFileMetadata(): void
    {
        $path = $this->path('input');
        file_put_contents($path, 'same');

        $fingerprint = new BuildFingerprint();

        self::assertSame($fingerprint->hash([$path]), $fingerprint->hash([$path]));
    }

    public function testFingerprintChangesWhenFileSizeChanges(): void
    {
        $path = $this->path('input');
        file_put_contents($path, 'before');
        $before = new BuildFingerprint()->hash([$path]);
        file_put_contents($path, 'after-change');

        self::assertNotSame($before, new BuildFingerprint()->hash([$path]));
    }

    public function testRejectsMissingInputFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BuildFingerprint()->hash([$this->path('missing')]);
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-build-fingerprint-' . $name . '-' . bin2hex(random_bytes(8));
    }
}
