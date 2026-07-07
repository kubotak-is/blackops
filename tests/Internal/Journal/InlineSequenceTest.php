<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Journal;

use BlackOps\Internal\Journal\InlineSequence;
use PHPUnit\Framework\TestCase;

final class InlineSequenceTest extends TestCase
{
    public function testStartsAtOneAndIncrementsMonotonically(): void
    {
        $sequence = new InlineSequence();

        self::assertSame(1, $sequence->next());
        self::assertSame(2, $sequence->next());
        self::assertSame(3, $sequence->next());
    }
}
