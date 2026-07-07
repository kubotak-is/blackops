<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\Framework;
use PHPUnit\Framework\TestCase;

final class FrameworkTest extends TestCase
{
    public function testFrameworkName(): void
    {
        $framework = new Framework();

        self::assertSame('BlackOps', $framework->name());
    }
}
