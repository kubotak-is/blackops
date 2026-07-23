<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeResult;
use BlackOps\Core\Retention\RetentionPurgeService;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Internal\Console\RetentionPurgeCommand;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class RetentionPurgeCommandTest extends TestCase
{
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9689001';

    public function testDryRunPrintsPlanWithoutApplyingPurge(): void
    {
        $planner = new PurgeCommandPlanner($this->plan());
        $purge = new PurgeCommandService();
        $tester = new CommandTester(
            new RetentionPurgeCommand($planner, $purge, new FixedRetentionPurgeCommandClock('2026-07-11T00:00:00Z')),
        );

        $status = $tester->execute($this->options(['--dry-run' => true]));

        self::assertSame(0, $status);
        self::assertSame('2026-07-11T00:00:00+00:00', $planner->now?->format(DATE_ATOM));
        self::assertFalse($purge->called);
        self::assertStringContainsString('Retention purge dry run', $tester->getDisplay());
        self::assertStringContainsString('Total: 1', $tester->getDisplay());
        self::assertStringContainsString('transport_payload: 1', $tester->getDisplay());
    }

    public function testConfirmAppliesPurgeAndPrintsResult(): void
    {
        $planner = new PurgeCommandPlanner($this->plan());
        $purge = new PurgeCommandService();
        $tester = new CommandTester(
            new RetentionPurgeCommand($planner, $purge, new FixedRetentionPurgeCommandClock('2026-07-11T00:00:00Z')),
        );

        $status = $tester->execute($this->options([
            '--confirm' => true,
            '--policy-ref' => 'production-retention-v1',
            '--actor' => 'system:retention',
        ]));

        self::assertSame(0, $status);
        self::assertTrue($purge->called);
        self::assertSame('production-retention-v1', $purge->policyRef?->toString());
        self::assertSame('system:retention', $purge->actor?->toString());
        self::assertStringContainsString('Retention purge applied', $tester->getDisplay());
        self::assertStringContainsString('idempotency_records_deleted: 1', $tester->getDisplay());
        self::assertStringContainsString('total_affected: 4', $tester->getDisplay());
    }

    public function testRejectsMissingMode(): void
    {
        $tester = new CommandTester(
            new RetentionPurgeCommand(
                new PurgeCommandPlanner($this->plan()),
                new PurgeCommandService(),
                new FixedRetentionPurgeCommandClock('2026-07-11T00:00:00Z'),
            ),
        );

        $this->expectException(InvalidArgumentException::class);

        $tester->execute($this->options([]));
    }

    public function testRejectsDryRunAndConfirmTogether(): void
    {
        $tester = new CommandTester(
            new RetentionPurgeCommand(
                new PurgeCommandPlanner($this->plan()),
                new PurgeCommandService(),
                new FixedRetentionPurgeCommandClock('2026-07-11T00:00:00Z'),
            ),
        );

        $this->expectException(InvalidArgumentException::class);

        $tester->execute($this->options(['--dry-run' => true, '--confirm' => true]));
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function options(array $override): array
    {
        return array_replace([
            '--transport-payload-days' => '1',
            '--journal-days' => '30',
            '--outcome-days' => '14',
            '--dead-letter-days' => '2',
            '--idempotency-record-days' => '1',
        ], $override);
    }

    private function plan(): RetentionPlan
    {
        return new RetentionPlan([
            new RetentionPlanItem(
                OperationId::fromString(self::OPERATION_ID),
                RetentionTarget::TransportPayload,
                new DateTimeImmutable('2026-07-08T00:00:00Z'),
                new DateTimeImmutable('2026-07-09T00:00:00Z'),
            ),
        ]);
    }
}

final class PurgeCommandPlanner implements RetentionPlanner
{
    public ?RetentionPolicy $policy = null;
    public ?DateTimeImmutable $now = null;

    public function __construct(
        private RetentionPlan $plan,
    ) {}

    public function plan(RetentionPolicy $policy, DateTimeImmutable $now): RetentionPlan
    {
        $this->policy = $policy;
        $this->now = $now;

        return $this->plan;
    }
}

final class PurgeCommandService implements RetentionPurgeService
{
    public bool $called = false;
    public ?RetentionPolicy $policy = null;
    public ?RetentionPolicyRef $policyRef = null;
    public ?RetentionActorRef $actor = null;
    public ?DateTimeImmutable $now = null;

    public function purge(
        RetentionPolicy $policy,
        RetentionPolicyRef $policyRef,
        RetentionActorRef $actor,
        DateTimeImmutable $now,
    ): RetentionPurgeResult {
        $this->called = true;
        $this->policy = $policy;
        $this->policyRef = $policyRef;
        $this->actor = $actor;
        $this->now = $now;

        return new RetentionPurgeResult(new RetentionPlan([]), 1, 2, idempotencyRecordsDeleted: 1);
    }
}

final readonly class FixedRetentionPurgeCommandClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}
