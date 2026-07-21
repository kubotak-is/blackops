<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Validation\Violation;
use BlackOps\Internal\Console\OperationConsoleCommandMetadata;
use BlackOps\Internal\Console\OperationConsoleOptionMetadata;
use BlackOps\Internal\Console\OperationConsoleValueBinder;
use PHPUnit\Framework\TestCase;

final class OperationConsoleValueBinderTest extends TestCase
{
    public function testBindsCanonicalScalarOptionsAndDefaults(): void
    {
        $value = new OperationConsoleValueBinder()->bind($this->metadata(), [
            'name' => 'Ada',
            'count' => '-12',
            'ratio' => '1.25e2',
            'enabled' => 'false',
        ]);

        self::assertInstanceOf(ConsoleBinderValue::class, $value);
        self::assertSame('Ada', $value->name);
        self::assertSame(-12, $value->count);
        self::assertSame(125.0, $value->ratio);
        self::assertFalse($value->enabled);
        self::assertNull($value->optional);
    }

    public function testReturnsSafeRequiredAndTypeViolationsWithoutInputValue(): void
    {
        $missing = new OperationConsoleValueBinder()->bind($this->metadata(), []);
        self::assertEquals([new Violation('name', 'required', 'binding.required')], $missing);

        $invalid = new OperationConsoleValueBinder()->bind($this->metadata(), [
            'name' => 'credential-value',
            'count' => '+1',
            'ratio' => 'NaN',
            'enabled' => 'yes',
        ]);
        self::assertEquals([new Violation('count', 'type', 'binding.type')], $invalid);
        self::assertStringNotContainsString('credential-value', serialize($invalid));
    }

    private function metadata(): OperationConsoleCommandMetadata
    {
        return new OperationConsoleCommandMetadata(
            'fixture.bind',
            ConsoleBinderOperation::class,
            ConsoleBinderValue::class,
            ConsoleBinderOutcome::class,
            Inline::class,
            'fixture:bind',
            '',
            [
                new OperationConsoleOptionMetadata('name', 'name', 'string', false, true, null),
                new OperationConsoleOptionMetadata('count', 'count', 'int', false, true, null),
                new OperationConsoleOptionMetadata('ratio', 'ratio', 'float', false, true, null),
                new OperationConsoleOptionMetadata('enabled', 'enabled', 'bool', false, true, null),
                new OperationConsoleOptionMetadata('optional', 'optional', 'string', true, false, null),
            ],
        );
    }
}

final readonly class ConsoleBinderOperation implements Operation {}

final readonly class ConsoleBinderValue implements OperationValue
{
    public function __construct(
        public string $name,
        public int $count,
        public float $ratio,
        public bool $enabled,
        public ?string $optional,
    ) {}
}

final readonly class ConsoleBinderOutcome implements Outcome {}
