<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Build;

use BlackOps\Internal\Build\BuildLock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BuildLockTest extends TestCase
{
    public function testRunsOperationWhileLockIsAvailable(): void
    {
        $result = false;

        new BuildLock()->run($this->path('lock'), static function () use (&$result): void {
            $result = true;
        });

        self::assertTrue($result);
    }

    public function testRejectsMissingLockDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BuildLock()->run($this->path('missing') . '/build.lock', static function (): void {});
    }

    public function testRejectsAlreadyHeldLock(): void
    {
        $path = $this->path('lock');
        $handle = fopen($path, 'c');

        self::assertIsResource($handle);
        flock($handle, LOCK_EX);

        try {
            $this->expectException(RuntimeException::class);

            new BuildLock()->run($path, static function (): void {});
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-build-lock-' . $name . '-' . bin2hex(random_bytes(8));
    }
}
