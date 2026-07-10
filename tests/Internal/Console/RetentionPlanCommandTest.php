<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Internal\Console\RetentionPlanCommand;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class RetentionPlanCommandTest extends TestCase
{
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688f01';

    public function testCommandPrintsRetentionPlanWithoutApplyingPurge(): void
    {
        $planner = new RecordingRetentionPlanner(new RetentionPlan([
            new RetentionPlanItem(
                OperationId::fromString(self::OPERATION_ID),
                RetentionTarget::TransportPayload,
                new DateTimeImmutable('2026-07-08T00:00:00Z'),
                new DateTimeImmutable('2026-07-09T00:00:00Z'),
            ),
        ]));
        $tester = new CommandTester(
            new RetentionPlanCommand($planner, new FixedRetentionPlanCommandClock('2026-07-11T00:00:00Z')),
        );

        $status = $tester->execute([
            '--transport-payload-days' => '1',
            '--journal-days' => '30',
            '--outcome-days' => '14',
            '--dead-letter-days' => '2',
        ]);

        self::assertSame(0, $status);
        self::assertSame('2026-07-11T00:00:00+00:00', $planner->now?->format(DATE_ATOM));
        self::assertSame(86_400, $planner->policy?->transportPayloadRetention()->secondsValue());
        self::assertSame(2_592_000, $planner->policy?->journalRetention()->secondsValue());
        self::assertStringContainsString('Retention plan', $tester->getDisplay());
        self::assertStringContainsString('Total: 1', $tester->getDisplay());
        self::assertStringContainsString('transport_payload: 1', $tester->getDisplay());
        self::assertStringContainsString(self::OPERATION_ID, $tester->getDisplay());
    }

    public function testCommandRejectsMissingExplicitPolicyOption(): void
    {
        $tester = new CommandTester(
            new RetentionPlanCommand(
                new RecordingRetentionPlanner(new RetentionPlan([])),
                new FixedRetentionPlanCommandClock('2026-07-11T00:00:00Z'),
            ),
        );

        $this->expectException(InvalidArgumentException::class);

        $tester->execute([
            '--transport-payload-days' => '1',
            '--journal-days' => '30',
            '--outcome-days' => '14',
        ]);
    }
}

final class RecordingRetentionPlanner implements RetentionPlanner
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

final readonly class FixedRetentionPlanCommandClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}
