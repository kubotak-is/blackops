<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Command;

use BlackOps\Internal\Console\FrameworkCommandNames;
use BlackOps\Internal\Projection\Command\CommandProjectionReservationRegistry;
use BlackOps\Internal\Projection\Command\CommandProjectionTarget;
use BlackOps\Internal\Projection\Command\CommandProjectionTargetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

final class CommandProjectionTargetRegistryTest extends TestCase
{
    public function testTargetRegistryHasExactFrameworkAndDefaultRows(): void
    {
        $rows = array_map(static fn(CommandProjectionTarget $target): array => $target->record(
            'framework',
        ), CommandProjectionTargetRegistry::all());

        self::assertCount(35, $rows);
        self::assertSame(
            [
                [
                    'name' => 'build:compile',
                    'aliases' => [],
                    'description' => 'Compile application operation, HTTP, command, and container artifacts.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'operation:list',
                    'aliases' => [],
                    'description' => 'List operation metadata from application providers.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'make:operation',
                    'aliases' => [],
                    'description' => 'Generate a typed self-handled operation.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'make:migration',
                    'aliases' => [],
                    'description' => 'Generate an application database migration.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'make:seeder',
                    'aliases' => [],
                    'description' => 'Generate an application database seeder.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'make:auth',
                    'aliases' => [],
                    'description' => 'Generate a bearer session authentication starter.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'database:status',
                    'aliases' => [],
                    'description' => 'Show applied and pending BlackOps database migrations.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'database:migrate',
                    'aliases' => [],
                    'description' => 'Apply or preview BlackOps database migrations.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'database:seed',
                    'aliases' => [],
                    'description' => 'Run the compiled application database root seeder.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'worker:run',
                    'aliases' => [],
                    'description' => 'Run the deferred operation worker loop.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'operation:schedule:run',
                    'aliases' => [],
                    'description' => 'Evaluate and invoke application schedules once.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'retention:plan',
                    'aliases' => [],
                    'description' => 'Build and print a retention purge plan without applying it.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'retention:purge',
                    'aliases' => [],
                    'description' => 'Dry-run or apply retention purge.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'scheduler:run',
                    'aliases' => [],
                    'description' => 'Run due BlackOps maintenance tasks once.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'scheduler:daemon',
                    'aliases' => [],
                    'description' => 'Run BlackOps maintenance tasks on an interval.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'outbox:relay:run',
                    'aliases' => [],
                    'description' => 'Deliver pending transactional outbox records.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'outbox:relay:daemon',
                    'aliases' => [],
                    'description' => 'Run transactional outbox delivery on an interval.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'outbox:dead-letter:retry',
                    'aliases' => [],
                    'description' => 'Retry one dead-lettered transactional outbox record.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'operation:inspect',
                    'aliases' => [],
                    'description' => 'Inspect one operation lifecycle and outcome.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'operation:viewer',
                    'aliases' => [],
                    'description' => 'Start the read-only local operation diagnostics viewer.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'frontend:generate',
                    'aliases' => [],
                    'description' => 'Generate TypeScript operation objects from the frontend contract.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'frontend:check',
                    'aliases' => [],
                    'description' => 'Check generated TypeScript operation objects for drift.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'journal:observer:replay',
                    'aliases' => [],
                    'description' => 'Replay canonical journal records to selected observers.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'storage:protection:plan',
                    'aliases' => [],
                    'description' => 'Plan a bounded protected storage key rotation without writing data.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'storage:protection:rotate',
                    'aliases' => [],
                    'description' => 'Dry-run or apply a bounded protected storage key rotation.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'diagnostics:check',
                    'aliases' => [],
                    'description' => 'Check application readiness and compiled artifacts.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'queue:status',
                    'aliases' => [],
                    'description' => 'Show aggregate deferred-operation queue status.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'about',
                    'aliases' => [],
                    'description' => 'Show installed BlackOps package, runtime, and projection status.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'outbox:status',
                    'aliases' => [],
                    'description' => 'Show aggregate transactional outbox status.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'list',
                    'aliases' => ['ls'],
                    'description' => 'List commands',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'help',
                    'aliases' => [],
                    'description' => 'Display help for a command',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'completion',
                    'aliases' => [],
                    'description' => 'Dump the shell completion script',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => '_complete',
                    'aliases' => [],
                    'description' => 'Internal command to provide shell completion suggestions',
                    'origin' => 'framework',
                    'hidden' => true,
                ],
                [
                    'name' => 'route:list',
                    'aliases' => ['route:ls'],
                    'description' => 'List compiled HTTP routes.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
                [
                    'name' => 'schedule:list',
                    'aliases' => ['schedule:ls'],
                    'description' => 'List compiled application schedules.',
                    'origin' => 'framework',
                    'hidden' => false,
                ],
            ],
            $rows,
        );
    }

    public function testFrameworkNamesAreTheCurrentTwentyNineOnly(): void
    {
        self::assertCount(29, FrameworkCommandNames::all());
        self::assertSame(
            array_map(
                static fn(CommandProjectionTarget $target): string => $target->name,
                CommandProjectionTargetRegistry::framework(),
            ),
            FrameworkCommandNames::all(),
        );
        self::assertNotContains('list', FrameworkCommandNames::all());
        self::assertNotContains('ls', FrameworkCommandNames::all());
    }

    public function testSymfonyDefaultsMatchExceptForTheFutureListAlias(): void
    {
        $application = new Application('BlackOps');

        foreach (CommandProjectionTargetRegistry::defaults() as $target) {
            $command = $application->find($target->name);
            if ($target->name === 'list') {
                self::assertSame([], $command->getAliases());
                self::assertNotSame($target->aliases, $command->getAliases());
            } else {
                self::assertSame($target->aliases, $command->getAliases());
            }
            self::assertSame($target->description, $command->getDescription());
            self::assertSame($target->hidden, $command->isHidden());
        }
    }

    public function testReservationRegistryIsSeparateAndExact(): void
    {
        self::assertSame(
            [
                'list',
                'ls',
                'help',
                'completion',
                '_complete',
                'route:list',
                'route:ls',
                'schedule:list',
                'schedule:ls',
                'diagnostics:check',
                'queue:status',
                'about',
                'outbox:status',
            ],
            CommandProjectionReservationRegistry::names(),
        );
        self::assertNotContains('worker:list', CommandProjectionReservationRegistry::names());
        self::assertNotContains('worker:ls', CommandProjectionReservationRegistry::names());
    }
}
