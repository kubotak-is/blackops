<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Transport\PostgreSql\PostgreSqlScheduleSchema;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final readonly class ScheduleEvaluator
{
    private PostgreSqlScheduleSchema $schema;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private IdentifierFactory $identifiers,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlScheduleSchema($schema);
    }

    public function evaluate(OperationMetadata $metadata): ScheduleEvaluationResult
    {
        if ($metadata->schedule === null)
            throw new InvalidArgumentException('Operation schedule metadata is required.');
        $schedule = $metadata->schedule;
        $evaluatedAt = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $nowMinute = $this->floorMinute($evaluatedAt);
        $timezone = new DateTimeZone($schedule->timezone);
        try {
            return $this->connection->transactional(function () use (
                $metadata,
                $schedule,
                $evaluatedAt,
                $nowMinute,
                $timezone,
            ): ScheduleEvaluationResult {
                $states = $this->schema->statesTable();
                $occurrences = $this->schema->occurrencesTable();
                $row = $this->connection->fetchAssociative(
                    "SELECT operation_type, cursor_at FROM {$states} WHERE schedule_name = :name FOR UPDATE",
                    ['name' => $schedule->name],
                );
                if ($row === false) {
                    $cursor = $nowMinute;
                    $inserted = $this->connection->executeStatement(
                        "INSERT INTO {$states} (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (:name, :type, :cursor, :created, :updated) ON CONFLICT (schedule_name) DO NOTHING",
                        [
                            'name' => $schedule->name,
                            'type' => $metadata->typeId,
                            'cursor' => $this->timestamp($cursor),
                            'created' => $this->timestamp($evaluatedAt),
                            'updated' => $this->timestamp($evaluatedAt),
                        ],
                    );
                    $row = $this->connection->fetchAssociative(
                        "SELECT operation_type, cursor_at FROM {$states} WHERE schedule_name = :name FOR UPDATE",
                        ['name' => $schedule->name],
                    );
                    if ($row !== false) {
                        $cursor = $this->floorMinute(new DateTimeImmutable((string) $row['cursor_at']));
                        if ((string) $row['operation_type'] !== $metadata->typeId)
                            throw new InvalidArgumentException('Schedule operation type assignment is invalid.');
                    }
                    $slots = [];
                    if ($inserted === 1) {
                        $slots = [$cursor];
                    } else {
                        for (
                            $slot = $cursor->modify('+1 minute');
                            $slot <= $nowMinute;
                            $slot = $slot->modify('+1 minute')
                        )
                            $slots[] = $slot;
                    }
                } else {
                    if ((string) $row['operation_type'] !== $metadata->typeId)
                        throw new InvalidArgumentException('Schedule operation type assignment is invalid.');
                    $cursor = $this->floorMinute(new DateTimeImmutable((string) $row['cursor_at']));
                    $slots = [];
                    for ($slot = $cursor->modify('+1 minute'); $slot <= $nowMinute; $slot = $slot->modify('+1 minute'))
                        $slots[] = $slot;
                }
                if ($nowMinute < $cursor)
                    return new ScheduleEvaluationResult([], false);
                /** @var list<DateTimeImmutable> $matches */
                $matches = $this->matchingSlots($slots, $schedule->cron, $timezone);
                $occurrencesResult = [];
                foreach ($matches as $index => $candidate) {
                    if ($index >= (count($matches) - 1))
                        continue;
                    $occurrencesResult[] = $this->insertSkip(
                        $occurrences,
                        $schedule->name,
                        $candidate,
                        $evaluatedAt,
                        'skipped_misfire',
                    );
                }
                $latest = null;
                if ($matches !== []) {
                    $candidateLatest = $matches[count($matches) - 1];
                    $latest = $candidateLatest;
                }
                if ($latest !== null) {
                    $active = (int) $this->connection->fetchOne(
                        "SELECT count(*) FROM {$occurrences} WHERE schedule_name = :name AND state IN ('claimed','accepted')",
                        ['name' => $schedule->name],
                    );
                    $occurrencesResult[] = $active > 0
                        ? $this->insertSkip($occurrences, $schedule->name, $latest, $evaluatedAt, 'skipped_overlap')
                        : $this->insertClaim($occurrences, $schedule->name, $latest, $evaluatedAt);
                }
                $this->connection->update(
                    $states,
                    ['cursor_at' => $this->timestamp($nowMinute), 'updated_at' => $this->timestamp($evaluatedAt)],
                    ['schedule_name' => $schedule->name],
                );
                $last = $occurrencesResult === [] ? null : $occurrencesResult[array_key_last($occurrencesResult)];
                return new ScheduleEvaluationResult($occurrencesResult, $last?->state === 'claimed');
            });
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Schedule evaluation failed.', previous: $exception);
        }
    }

    /** @param list<DateTimeImmutable> $slots @return list<DateTimeImmutable> */
    private function matchingSlots(array $slots, string $cron, DateTimeZone $timezone): array
    {
        $expression = CronExpression::parse($cron);
        /** @var array<string, true> $seen */
        $seen = [];
        $result = [];
        foreach ($slots as $slot) {
            $local = $slot->setTimezone($timezone);
            $key = $local->format('Y-m-d H:i');
            if (isset($seen[$key]))
                continue;
            $seen[$key] = true;
            if (!$this->isFirstUtcOccurrence($slot, $local, $timezone))
                continue;
            $field = static fn(CronField $f, int $v): bool => in_array($v, $f->values, true);
            $dom = $field($expression->dayOfMonth, (int) $local->format('j'));
            $dow = (int) $local->format('w');
            $dow = $field($expression->dayOfWeek, $dow);
            if (
                $field($expression->minute, (int) $local->format('i'))
                && $field($expression->hour, (int) $local->format('G'))
                && $field($expression->month, (int) $local->format('n'))
                && (
                    $expression->usesDayOfMonthDayOfWeekOrSemantics()
                        ? $dom || $dow
                        : (
                            $expression->dayOfMonth->wildcard
                                ? $dow
                                : ($expression->dayOfWeek->wildcard ? $dom : $dom && $dow)
                        )
                )
            )
                $result[] = $slot;
        }
        return $result;
    }

    private function isFirstUtcOccurrence(
        DateTimeImmutable $slot,
        DateTimeImmutable $local,
        DateTimeZone $timezone,
    ): bool {
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $local->format('Y-m-d H:i'), new DateTimeZone('UTC'));
        if ($wall === false)
            return true;
        $offsets = [];
        foreach ($timezone->getTransitions(
            $slot->modify('-3 days')->getTimestamp(),
            $slot->modify('+3 days')->getTimestamp(),
        ) ?: [] as $transition)
            $offsets[(int) $transition['offset']] = true;
        $candidates = [];
        foreach (array_keys($offsets) as $offset) {
            $candidate = $wall->setTimestamp($wall->getTimestamp() - (int) $offset);
            if ($candidate->setTimezone($timezone)->format('Y-m-d H:i') === $local->format('Y-m-d H:i'))
                $candidates[] = $candidate->getTimestamp();
        }
        return $candidates === [] || $slot->getTimestamp() === min($candidates);
    }

    private function insertClaim(
        string $table,
        string $name,
        DateTimeImmutable $slot,
        DateTimeImmutable $evaluatedAt,
    ): ScheduleOccurrence {
        $id = $this->identifiers->newOperationId();
        $this->connection->insert($table, [
            'schedule_name' => $name,
            'scheduled_at' => $this->timestamp($slot),
            'evaluated_at' => $this->timestamp($evaluatedAt),
            'state' => 'claimed',
            'category' => null,
            'operation_id' => $id->toString(),
            'accepted_at' => null,
            'created_at' => $this->timestamp($evaluatedAt),
            'updated_at' => $this->timestamp($evaluatedAt),
        ]);
        return new ScheduleOccurrence($name, $slot, $evaluatedAt, 'claimed', null, $id);
    }

    private function insertSkip(
        string $table,
        string $name,
        DateTimeImmutable $slot,
        DateTimeImmutable $evaluatedAt,
        string $state,
    ): ScheduleOccurrence {
        $this->connection->insert($table, [
            'schedule_name' => $name,
            'scheduled_at' => $this->timestamp($slot),
            'evaluated_at' => $this->timestamp($evaluatedAt),
            'state' => $state,
            'category' => $state,
            'operation_id' => null,
            'accepted_at' => null,
            'created_at' => $this->timestamp($evaluatedAt),
            'updated_at' => $this->timestamp($evaluatedAt),
        ]);
        return new ScheduleOccurrence($name, $slot, $evaluatedAt, $state, $state, null);
    }

    private function floorMinute(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTime((int) $time->format('H'), (int) $time->format('i'), 0, 0);
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
