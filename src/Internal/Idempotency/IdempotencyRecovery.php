<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Status\OperationStatusJournalValidator;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Reconstructs a claimed operation only from a complete, validated durable journal.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class IdempotencyRecovery
{
    public function __construct(
        private CanonicalJournalReader $journal,
        private IdempotencyStore $store,
        private JsonOperationResponder $responder,
        private ?Connection $connection = null,
        private string $schema = 'blackops',
        private OperationStatusJournalValidator $validator = new OperationStatusJournalValidator(),
    ) {}

    public function inline(ProcessingRecord $processing): ?OperationResult
    {
        $records = $this->records($processing->operationId());
        if ($records === []) {
            return null;
        }

        try {
            $validated = $this->validator->validate($processing->operationId(), $records);
            if ($validated->operation->strategy !== 'inline') {
                throw new \RuntimeException('Journal strategy does not match inline recovery.');
            }
            $terminal = $this->terminal($records);
            if ($terminal === null) {
                return null;
            }
            $result = match ($terminal->event) {
                JournalEvent::OperationCompleted => $terminal->data instanceof OperationCompletedData
                    ? OperationResult::completed($terminal->data->outcome, $processing->operationId())
                    : throw new \RuntimeException('Completed journal data is invalid.'),
                JournalEvent::OperationRejected => $terminal->data instanceof OperationRejectedData
                    ? OperationResult::rejected($terminal->data->reason, $processing->operationId())
                    : throw new \RuntimeException('Rejected journal data is invalid.'),
                JournalEvent::OperationFailed => $terminal->data instanceof OperationFailedData
                    ? throw new \RuntimeException('Inline operation failed evidence requires safe replay failure.')
                    : throw new \RuntimeException('Failed journal data is invalid.'),
                default => throw new \RuntimeException('Journal has no recoverable inline terminal.'),
            };
        } catch (Throwable) {
            try {
                $this->internalFailure($processing);
            } catch (IdempotencyRecoveryWinner $winner) {
                $snapshot = $winner->record()->result();
                if ($snapshot === null || $snapshot->isInternalFailure()) {
                    throw new IdempotencyReplayFailure($winner->record()->operationId());
                }

                return $snapshot->result()->asReplayed();
            } catch (IdempotencyRecoveryInProgress) {
                return OperationResult::rejected(\BlackOps\Core\Rejection\RejectionReason::conflict(
                    'idempotency_in_progress',
                ));
            }
        }

        $snapshot = $this->responder->snapshot($this->responder->respond($result));
        $terminalRecord = new TerminalRecord(
            $processing->scope(),
            $processing->key(),
            $processing->fingerprint(),
            $processing->operationId(),
            new Inline(),
            $processing->createdAt(),
            $processing->expiresAt(),
            $snapshot,
            new IdempotencyResultSnapshot($result),
        );
        $winner = $this->persist($processing, $terminalRecord);
        if ($winner instanceof ProcessingRecord) {
            return OperationResult::rejected(\BlackOps\Core\Rejection\RejectionReason::conflict(
                'idempotency_in_progress',
            ));
        }
        if ($winner instanceof TerminalRecord) {
            $snapshot = $winner->result();
            if ($snapshot === null || $snapshot->isInternalFailure()) {
                throw new IdempotencyReplayFailure($winner->operationId());
            }

            return $snapshot->result()->asReplayed();
        }

        return $result->asReplayed();
    }

    /** @mago-expect lint:halstead */
    public function deferred(ProcessingRecord $processing): ?DeferredAcknowledgement
    {
        $records = $this->records($processing->operationId());
        if ($records === []) {
            return null;
        }

        try {
            $validated = $this->validator->validate($processing->operationId(), $records);
            if ($validated->operation->strategy !== 'deferred' || $this->connection === null) {
                throw new \RuntimeException('Journal strategy or deferred storage is invalid.');
            }
            $accepted = array_values(array_filter(
                $records,
                static fn(JournalRecord $record): bool => $record->event === JournalEvent::OperationAccepted,
            ));
            if ($accepted === []) {
                return null;
            }
            if (count($accepted) !== 1) {
                throw new \RuntimeException('Deferred acceptance evidence is ambiguous.');
            }
            $row = $this->connection->fetchAssociative(
                'SELECT operation_type, state, accepted_at FROM '
                . new PostgreSqlDeferredOperationSchema($this->schema)->operationsTable()
                . ' WHERE operation_id = :operation_id',
                ['operation_id' => $processing->operationId()->toString()],
            );
            if (
                !is_string($row['operation_type'] ?? null)
                || $row['operation_type'] !== $validated->operation->type
                || !is_string($row['state'] ?? null)
                || !in_array(
                    $row['state'],
                    [
                        'accepted',
                        'running',
                        'supervising',
                        'retry_scheduled',
                        'completed',
                        'rejected',
                        'failed',
                        'dead_lettered',
                    ],
                    strict: true,
                )
                || !is_string($row['accepted_at'] ?? null)
                || $row['accepted_at'] === ''
            ) {
                throw new \RuntimeException('Deferred operation evidence is missing.');
            }
            $acceptedAt = new DateTimeImmutable($row['accepted_at']);
        } catch (Throwable) {
            try {
                $this->internalFailure($processing);
            } catch (IdempotencyRecoveryWinner $winner) {
                $acceptedAt = $winner->record()->acceptedAt();
                if ($acceptedAt === null) {
                    throw new IdempotencyReplayFailure($winner->record()->operationId());
                }

                return new DeferredAcknowledgement($winner->record()->operationId(), $acceptedAt, true);
            } catch (IdempotencyRecoveryInProgress) {
                return null;
            }
        }

        $acknowledgement = new DeferredAcknowledgement($processing->operationId(), $acceptedAt, true);
        $response = $this->responder->snapshot($this->responder->respondAcknowledgement($acknowledgement));
        $terminalRecord = new TerminalRecord(
            $processing->scope(),
            $processing->key(),
            $processing->fingerprint(),
            $processing->operationId(),
            new Deferred(),
            $processing->createdAt(),
            $processing->expiresAt(),
            $response,
            null,
            $acceptedAt,
        );
        if (!$this->store->terminalize($processing->operationId(), $terminalRecord)) {
            $winner = $this->race($processing);
            if ($winner instanceof ProcessingRecord) {
                return null;
            }
            $acceptedAt = $winner->acceptedAt();
            if ($acceptedAt === null) {
                throw new IdempotencyReplayFailure($winner->operationId());
            }

            return new DeferredAcknowledgement($winner->operationId(), $acceptedAt, true);
        }

        return $acknowledgement;
    }

    /** @return list<JournalRecord> */
    private function records(OperationId $operationId): array
    {
        try {
            return array_values(iterator_to_array($this->journal->records($operationId), preserve_keys: false));
        } catch (Throwable) {
            throw new IdempotencyReplayFailure($operationId);
        }
    }

    /** @param list<JournalRecord> $records */
    private function terminal(array $records): ?JournalRecord
    {
        $terminals = array_values(array_filter($records, static fn(JournalRecord $record): bool => in_array(
            $record->event,
            [JournalEvent::OperationCompleted, JournalEvent::OperationRejected, JournalEvent::OperationFailed],
            strict: true,
        )));
        if ($terminals === []) {
            return null;
        }
        if (count($terminals) !== 1) {
            throw new \RuntimeException('Journal terminal evidence is ambiguous.');
        }

        return $terminals[0];
    }

    private function persist(
        ProcessingRecord $processing,
        TerminalRecord $terminal,
    ): bool|TerminalRecord|ProcessingRecord {
        if (!$this->store->terminalize($processing->operationId(), $terminal)) {
            return $this->race($processing);
        }

        return true;
    }

    private function internalFailure(ProcessingRecord $processing): never
    {
        $response = null;
        try {
            $response = $this->responder->snapshot($this->responder->respondInternalError($processing->operationId()));
        } catch (Throwable) {
            $response = null;
        }
        $terminal = new TerminalRecord(
            $processing->scope(),
            $processing->key(),
            $processing->fingerprint(),
            $processing->operationId(),
            $processing->strategy(),
            $processing->createdAt(),
            $processing->expiresAt(),
            $response,
            IdempotencyResultSnapshot::internalFailure($processing->operationId()),
        );
        try {
            if (!$this->store->terminalize($processing->operationId(), $terminal)) {
                $winner = $this->race($processing);
                if ($winner instanceof TerminalRecord) {
                    throw new IdempotencyRecoveryWinner($winner);
                }
                throw new IdempotencyRecoveryInProgress();
            }
        } catch (IdempotencyRecoveryWinner|IdempotencyRecoveryInProgress $race) {
            throw $race;
        } catch (Throwable) {
            throw new IdempotencyReplayFailure($processing->operationId());
        }
        throw new IdempotencyReplayFailure($processing->operationId());
    }

    private function race(ProcessingRecord $processing): TerminalRecord|ProcessingRecord
    {
        $existing = $this->store->find($processing->scope());
        if (
            $existing instanceof TerminalRecord
            && $existing->fingerprint()->equals($processing->fingerprint())
            && $existing->operationId()->equals($processing->operationId())
        ) {
            return $existing;
        }
        if ($existing instanceof ProcessingRecord) {
            return $existing;
        }
        throw new IdempotencyReplayFailure($processing->operationId());
    }
}
