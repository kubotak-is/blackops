<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Command;

use BlackOps\Core\Execution\Inline;
use BlackOps\Internal\Console\ApplicationCommandCollisionValidator;
use BlackOps\Internal\Console\ApplicationCommandMetadata;
use BlackOps\Internal\Console\OperationConsoleCommandMetadata;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Internal\Projection\Command\CommandProjectionProducer;
use BlackOps\Internal\Projection\Command\CommandProjectionTarget;
use BlackOps\Internal\Projection\Command\CommandProjectionTargetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

final class CommandProjectionProducerTest extends TestCase
{
    public function testProducesExactThreeSourceUnionWithEmbeddedFrameworkRows(): void
    {
        $explicit = $this->metadata(
            ProducerExplicitCommand::class,
            'fixture:explicit',
            aliases: ['fe'],
            description: null,
            hidden: true,
        );
        $rawDiscovered = [
            $explicit,
            $this->metadata(
                ProducerDiscoveredCommand::class,
                'fixture:discovered',
                aliases: ['fd'],
                description: 'Discovered command',
            ),
        ];
        $discovered = new ApplicationCommandCollisionValidator()->merge($rawDiscovered, [$explicit], []);
        $operation = $this->operation('fixture:operation', 'Operation command');

        $projection = new CommandProjectionProducer()->produce('build-1', [$explicit], $discovered, [$operation]);

        self::assertSame('blackops_command_projection', $projection->kind);
        self::assertCount(38, $projection->records);
        $expectedFramework = array_map(static fn(CommandProjectionTarget $target): array => $target->record(
            'framework',
        ), CommandProjectionTargetRegistry::all());
        usort($expectedFramework, static fn(array $left, array $right): int => strcmp($left['name'], $right['name']));
        self::assertSame(
            $expectedFramework,
            array_values(array_filter(
                $projection->records,
                static fn(array $record): bool => $record['origin'] === 'framework',
            )),
        );
        self::assertSame(
            [
                'name' => 'fixture:explicit',
                'aliases' => ['fe'],
                'description' => '',
                'origin' => 'application',
                'hidden' => true,
            ],
            $this->record($projection->records, 'fixture:explicit'),
        );
        self::assertSame(
            [
                'name' => 'fixture:discovered',
                'aliases' => ['fd'],
                'description' => 'Discovered command',
                'origin' => 'application',
                'hidden' => false,
            ],
            $this->record($projection->records, 'fixture:discovered'),
        );
        self::assertSame(
            [
                'name' => 'fixture:operation',
                'aliases' => [],
                'description' => 'Operation command',
                'origin' => 'application',
                'hidden' => false,
            ],
            $this->record($projection->records, 'fixture:operation'),
        );
        self::assertCount(1, array_filter(
            $projection->records,
            static fn(array $record): bool => $record['name'] === 'fixture:explicit',
        ));

        $names = array_map(static fn(array $record): string => $record['name'], $projection->records);
        $expected = $names;
        sort($expected, SORT_STRING);
        self::assertSame($expected, $names);
        self::assertContains('build:compile', $names);
    }

    public function testProducerDoesNotAddReservedNamesOrCreatePublisherFiles(): void
    {
        $reserved = $this->metadata(ProducerReservedCommand::class, 'list');

        $this->expectExceptionMessage('conflicts with a framework command');
        new CommandProjectionProducer()->produce('build-1', [$reserved], [], []);
    }

    public function testCodecBoundsAndClosedRowsAreReused(): void
    {
        $command = $this->metadata(
            ProducerDiscoveredCommand::class,
            'fixture:bounded',
            description: str_repeat('x', 257),
        );

        $this->expectException(ProjectionBuildSetValidationException::class);
        new CommandProjectionProducer()->produce('build-1', [], [$command], []);
    }

    /** @param list<array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function record(array $records, string $name): array
    {
        foreach ($records as $record) {
            if ($record['name'] === $name) {
                return $record;
            }
        }

        self::fail('Missing projection row: ' . $name);
    }

    /** @param class-string<Command> $class */
    private function metadata(
        string $class,
        string $name,
        array $aliases = [],
        ?string $description = 'Application command',
        bool $hidden = false,
    ): ApplicationCommandMetadata {
        return new ApplicationCommandMetadata($class, $name, $description, $aliases, $hidden, null, []);
    }

    private function operation(string $name, string $description): OperationConsoleCommandMetadata
    {
        return new OperationConsoleCommandMetadata(
            'fixture.' . str_replace(':', '.', $name),
            ProducerOperation::class,
            ProducerOperationValue::class,
            ProducerOperationOutcome::class,
            Inline::class,
            $name,
            $description,
            [],
        );
    }
}

final class ProducerExplicitCommand extends Command {}

final class ProducerDiscoveredCommand extends Command {}

final class ProducerReservedCommand extends Command {}

final readonly class ProducerOperation implements \BlackOps\Core\Operation {}

final readonly class ProducerOperationValue implements \BlackOps\Core\OperationValue {}

final readonly class ProducerOperationOutcome implements \BlackOps\Core\Outcome {}
