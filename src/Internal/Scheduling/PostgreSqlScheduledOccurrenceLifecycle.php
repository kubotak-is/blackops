<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\PostgreSql\PostgreSqlScheduleSchema;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;

final readonly class PostgreSqlScheduledOccurrenceLifecycle
{
    private PostgreSqlScheduleSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlScheduleSchema($schema);
    }

    public function transition(
        OperationId $operationId,
        string $expectedState,
        string $targetState,
        ?string $category,
        DateTimeImmutable $at,
    ): void {
        try {
            $this->assertTransition($expectedState, $targetState, $category);
            $acceptedAt = $expectedState === 'claimed' && $targetState === 'accepted' ? $this->timestamp($at) : null;
            $updated = $this->connection->executeStatement(
                "UPDATE {$this->schema->occurrencesTable()}
                    SET state = :target_state,
                        category = :category,
                        accepted_at = COALESCE(:accepted_at, accepted_at),
                        updated_at = :updated_at
                    WHERE operation_id = :operation_id
                        AND state = :expected_state",
                [
                    'target_state' => $targetState,
                    'category' => $category,
                    'accepted_at' => $acceptedAt,
                    'updated_at' => $this->timestamp($at),
                    'operation_id' => $operationId->toString(),
                    'expected_state' => $expectedState,
                ],
            );
            if ((int) $updated !== 1) {
                throw new LogicException('Scheduled occurrence transition did not update exactly one row.');
            }
        } catch (\Throwable $exception) {
            if ($exception instanceof LogicException) {
                throw $exception;
            }
            throw new \RuntimeException('Scheduled occurrence transition failed.', previous: $exception);
        }
    }

    private function assertTransition(string $expected, string $target, ?string $category): void
    {
        $allowed = [
            'claimed' => ['accepted', 'completed', 'rejected', 'failed'],
            'accepted' => ['accepted', 'completed', 'rejected', 'failed', 'dead_lettered'],
        ];
        if (!isset($allowed[$expected]) || !in_array($target, $allowed[$expected], true)) {
            throw new LogicException('Scheduled occurrence transition is invalid.');
        }
        if (in_array($target, ['accepted', 'completed'], true) && $category !== null) {
            throw new LogicException('Scheduled occurrence success transition cannot contain a category.');
        }
        if (
            in_array($target, ['rejected', 'failed', 'dead_lettered'], true)
            && ($category === null || preg_match('/^[a-z][a-z0-9_.-]*$/D', $category) !== 1)
        ) {
            throw new LogicException('Scheduled occurrence failure transition requires a safe category.');
        }
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
