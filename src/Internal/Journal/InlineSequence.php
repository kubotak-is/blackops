<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

final class InlineSequence
{
    private int $next = 1;

    public function next(): int
    {
        return $this->next++;
    }
}
