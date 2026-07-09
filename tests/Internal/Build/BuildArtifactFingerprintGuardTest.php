<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Build;

use BlackOps\Internal\Build\BuildArtifactFingerprintGuard;
use PHPUnit\Framework\TestCase;

final class BuildArtifactFingerprintGuardTest extends TestCase
{
    public function testIsFreshAfterUpdateWhenOutputsExist(): void
    {
        $input = $this->path('input');
        $output = $this->path('output');
        $fingerprint = $this->path('fingerprint');
        file_put_contents($input, 'input');
        file_put_contents($output, 'output');
        $guard = new BuildArtifactFingerprintGuard();

        $guard->update($fingerprint, [$input]);

        self::assertTrue($guard->isFresh($fingerprint, [$input], [$output]));
    }

    public function testIsNotFreshWhenOutputIsMissing(): void
    {
        $input = $this->path('input');
        $fingerprint = $this->path('fingerprint');
        file_put_contents($input, 'input');
        $guard = new BuildArtifactFingerprintGuard();

        $guard->update($fingerprint, [$input]);

        self::assertFalse($guard->isFresh($fingerprint, [$input], [$this->path('missing')]));
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-build-fingerprint-guard-' . $name . '-' . bin2hex(random_bytes(8));
    }
}
