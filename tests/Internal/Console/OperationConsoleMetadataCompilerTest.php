<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Console\OperationConsoleMetadataCompiler;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationConsoleMetadataCompilerTest extends TestCase
{
    public function testCompilesOnlyAttributedOperationWithoutConstructingValue(): void
    {
        $commands = $this->compile(ConsoleMetadataOperation::class, NonConsoleMetadataOperation::class);

        self::assertCount(1, $commands);
        $command = $commands[0];
        self::assertSame('fixture:console', $command->name);
        self::assertSame('Console fixture.', $command->description);
        self::assertSame('fixture.console', $command->typeId);
        self::assertSame(ConsoleMetadataValue::class, $command->value);
        self::assertSame(
            ['user-id', 'url-value', 'limit', 'ratio', 'enabled'],
            array_map(static fn($option): string => $option->name, $command->options),
        );
        self::assertSame(
            [true, true, true, false, false],
            array_map(static fn($option): bool => $option->required, $command->options),
        );
        self::assertSame(
            [null, null, null, 1.5, false],
            array_map(static fn($option): string|int|float|bool|null => $option->default, $command->options),
        );
    }

    /** @return iterable<string, array{class-string<Operation>}> */
    public static function invalidOperations(): iterable
    {
        yield 'sensitive value' => [SensitiveConsoleOperation::class];
        yield 'unsupported array' => [ArrayConsoleOperation::class];
        yield 'option collision' => [CollidingConsoleOperation::class];
        yield 'reserved json' => [ReservedConsoleOperation::class];
        yield 'sensitive outcome' => [SensitiveOutcomeConsoleOperation::class];
    }

    #[DataProvider('invalidOperations')]
    public function testRejectsUnsupportedSensitiveAndCollidingContracts(string $operation): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compile($operation);
    }

    /** @param class-string<Operation> ...$operations @return list<\BlackOps\Internal\Console\OperationConsoleCommandMetadata> */
    private function compile(string ...$operations): array
    {
        $compiler = new OperationMetadataCompiler();

        return new OperationConsoleMetadataCompiler()->compile(new OperationRegistry(array_map(
            static fn(string $operation) => $compiler->compile($operation),
            $operations,
        )));
    }
}

#[ConsoleCommand('fixture:console', 'Console fixture.')]
#[OperationType('fixture.console')]
final readonly class ConsoleMetadataOperation implements Operation
{
    public function handle(ConsoleMetadataValue $value): ConsoleMetadataOutcome
    {
        return new ConsoleMetadataOutcome();
    }
}

final readonly class ConsoleMetadataValue implements OperationValue
{
    public function __construct(
        public string $userId,
        public string $URLValue,
        public ?int $limit,
        public float $ratio = 1.5,
        public bool $enabled = false,
    ) {
        throw new \LogicException('Metadata compilation must not instantiate values.');
    }
}

final readonly class ConsoleMetadataOutcome implements Outcome {}

#[OperationType('fixture.hidden')]
final readonly class NonConsoleMetadataOperation implements Operation
{
    public function handle(NonConsoleMetadataValue $value): ConsoleMetadataOutcome
    {
        return new ConsoleMetadataOutcome();
    }
}

final readonly class NonConsoleMetadataValue implements OperationValue {}

#[ConsoleCommand('fixture:sensitive')]
#[OperationType('fixture.sensitive')]
final readonly class SensitiveConsoleOperation implements Operation
{
    public function handle(SensitiveConsoleValue $value): ConsoleMetadataOutcome
    {
        return new ConsoleMetadataOutcome();
    }
}

final readonly class SensitiveConsoleValue implements OperationValue
{
    public function __construct(
        #[Sensitive]
        public string $secret,
    ) {}
}

#[ConsoleCommand('fixture:array')]
#[OperationType('fixture.array')]
final readonly class ArrayConsoleOperation implements Operation
{
    public function handle(ArrayConsoleValue $value): ConsoleMetadataOutcome
    {
        return new ConsoleMetadataOutcome();
    }
}

final readonly class ArrayConsoleValue implements OperationValue
{
    public function __construct(
        public array $items,
    ) {}
}

#[ConsoleCommand('fixture:collision')]
#[OperationType('fixture.collision')]
final readonly class CollidingConsoleOperation implements Operation
{
    public function handle(CollidingConsoleValue $value): ConsoleMetadataOutcome
    {
        return new ConsoleMetadataOutcome();
    }
}

final readonly class CollidingConsoleValue implements OperationValue
{
    public function __construct(
        public string $userId,
        public string $user_id,
    ) {}
}

#[ConsoleCommand('fixture:reserved')]
#[OperationType('fixture.reserved')]
final readonly class ReservedConsoleOperation implements Operation
{
    public function handle(ReservedConsoleValue $value): ConsoleMetadataOutcome
    {
        return new ConsoleMetadataOutcome();
    }
}

final readonly class ReservedConsoleValue implements OperationValue
{
    public function __construct(
        public string $json,
    ) {}
}

#[ConsoleCommand('fixture:sensitive-outcome')]
#[OperationType('fixture.sensitive-outcome')]
final readonly class SensitiveOutcomeConsoleOperation implements Operation
{
    public function handle(NonConsoleMetadataValue $value): SensitiveConsoleOutcome
    {
        return new SensitiveConsoleOutcome('secret');
    }
}

final readonly class SensitiveConsoleOutcome implements Outcome
{
    public function __construct(
        #[Sensitive]
        public string $secret,
    ) {}
}
