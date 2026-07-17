<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop;

class PlainService
{
    public function execute(string $value): string
    {
        return $value;
    }
}
