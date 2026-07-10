<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduler;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeResult;
use BlackOps\Core\Retention\RetentionPurgeService;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\MaintenanceTask;
use BlackOps\Internal\Scheduler\MaintenanceTaskResult;
use BlackOps\Internal\Scheduler\RetentionMaintenanceTask;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MaintenanceSchedulerTest extends TestCase
{
    public const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9689001';

    public function testRunsRegisteredTasksOnce(): void
    {
        $now = new DateTimeImmutable('2026-07-11T00:00:00Z');
        $first = new SchedulerTestTask('first', 2);
        $second = new SchedulerTestTask('second', 3);
        $scheduler = new MaintenanceScheduler([$first, $second]);

        $result = $scheduler->run($now);

        self::assertSame(2, $result->count());
        self::assertSame(5, $result->totalAffected());
        self::assertSame($now, $first->now);
        self::assertSame($now, $second->now);
        self::assertSame('first', $result->taskResults()[0]->taskName());
        self::assertSame('second', $result->taskResults()[1]->taskName());
    }

    public function testRejectsInvalidTaskResult(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MaintenanceTaskResult('', 0, 'empty');
    }

    public function testRetentionMaintenanceTaskRunsPurgeService(): void
    {
        $purge = new SchedulerRetentionPurgeService();
        $policy = new RetentionPolicy(
            RetentionPeriod::days(1),
            RetentionPeriod::days(30),
            RetentionPeriod::days(14),
            RetentionPeriod::days(7),
        );
        $policyRef = RetentionPolicyRef::fromString('production-retention-v1');
        $actor = RetentionActorRef::fromString('system:retention');
        $task = new RetentionMaintenanceTask($purge, $policy, $policyRef, $actor);
        $now = new DateTimeImmutable('2026-07-11T00:00:00Z');

        $result = $task->run($now);

        self::assertSame('retention', $task->name());
        self::assertSame('retention', $result->taskName());
        self::assertSame(3, $result->affectedCount());
        self::assertSame($policy, $purge->policy);
        self::assertSame($policyRef, $purge->policyRef);
        self::assertSame($actor, $purge->actor);
        self::assertSame($now, $purge->now);
    }
}

final class SchedulerTestTask implements MaintenanceTask
{
    public ?DateTimeImmutable $now = null;

    public function __construct(
        private readonly string $name,
        private readonly int $affectedCount,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function run(DateTimeImmutable $now): MaintenanceTaskResult
    {
        $this->now = $now;

        return new MaintenanceTaskResult($this->name, $this->affectedCount, 'done');
    }
}

final class SchedulerRetentionPurgeService implements RetentionPurgeService
{
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
        $this->policy = $policy;
        $this->policyRef = $policyRef;
        $this->actor = $actor;
        $this->now = $now;

        return new RetentionPurgeResult(
            new RetentionPlan([
                new RetentionPlanItem(
                    OperationId::fromString(MaintenanceSchedulerTest::OPERATION_ID),
                    RetentionTarget::TransportPayload,
                    new DateTimeImmutable('2026-07-08T00:00:00Z'),
                    new DateTimeImmutable('2026-07-09T00:00:00Z'),
                ),
            ]),
            1,
            2,
        );
    }
}
