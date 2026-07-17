<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop;

use BlackOps\Database\Attribute\AfterCommit;

class AfterCommitService
{
    /** @var list<string> */
    public array $values = [];

    #[AfterCommit]
    public function record(string $value): void
    {
        $this->values[] = $value;
    }
}
