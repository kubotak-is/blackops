<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Projection;

use BlackOps\Core\Audit\AuditProjectionException;
use BlackOps\Internal\Audit\Projection\AuditProjectionByteGuard;
use PHPUnit\Framework\TestCase;

final class AuditProjectionByteGuardTest extends TestCase
{
    public function testInclusiveByteCapAndTerminalLineFeed(): void
    {
        $guard = new AuditProjectionByteGuard();
        self::assertSame(str_repeat('x', 65_535) . "\n", $guard->guard(str_repeat('x', 65_535) . "\n"));
        self::assertSame(65_536, strlen($guard->guard(str_repeat('x', 65_535) . "\n")));
        foreach ([
            str_repeat('x', 65_536) . "\n",
            str_repeat('x', 65_536) . "\n\n",
            "\xEF\xBB\xBF{}\n",
            "{}\r\n",
            '{}',
            "\n",
        ] as $invalid) {
            try {
                $guard->guard($invalid);
                self::fail('invalid projection bytes accepted');
            } catch (AuditProjectionException $exception) {
                self::assertSame('projection_invalid', $exception->code());
            }
        }
    }

    public function testCapPlusOneAndMissingOrDoubleFeedNeverReturnBytes(): void
    {
        $guard = new AuditProjectionByteGuard();
        foreach ([
            str_repeat('x', 65_536) . "\n",
            "{}\n\n",
            "{}\r\n",
            "{}\r\n\n",
        ] as $invalid) {
            $this->expectException(AuditProjectionException::class);
            try {
                $guard->guard($invalid);
            } finally {
                $this->addToAssertionCount(1);
            }
        }
    }
}
