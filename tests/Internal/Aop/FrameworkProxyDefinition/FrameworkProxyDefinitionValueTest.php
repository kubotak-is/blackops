<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop\FrameworkProxyDefinition;

use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionCompilation;
use PHPUnit\Framework\TestCase;

final class FrameworkProxyDefinitionValueTest extends TestCase
{
    public function testEmptyCompilationHasNoBindings(): void
    {
        self::assertSame([], new FrameworkProxyDefinitionCompilation([])->bindings);
    }
}
