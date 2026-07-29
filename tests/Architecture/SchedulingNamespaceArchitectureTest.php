<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SchedulingNamespaceArchitectureTest extends TestCase
{
    public function testSchedulingPublicNamespaceDependsOnlyOnCore(): void
    {
        $config = file_get_contents(__DIR__ . '/../../deptrac.yaml');
        self::assertIsString($config);
        self::assertStringContainsString("    - name: Scheduling\n", $config);
        self::assertStringContainsString("    Scheduling:\n      - Core\n", $config);
    }

    public function testPublicSchedulingTypesDoNotReferenceInternalNamespace(): void
    {
        foreach (glob(__DIR__ . '/../../src/Scheduling/*.php') ?: [] as $file) {
            self::assertStringNotContainsString('BlackOps\\Internal', (string) file_get_contents($file));
        }
    }
}
