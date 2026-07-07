<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Journal\CanonicalJournalStore;
use BlackOps\Journal\Exception\JournalReadFailed;
use BlackOps\Journal\Exception\JournalWriteFailed;
use BlackOps\Journal\JournalRecord;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final readonly class PostgreSqlCanonicalJournalStore implements CanonicalJournalStore
{
    private PostgreSqlJournalSchema $schema;
    private PostgreSqlJournalRecordCodec $codec;

    public function __construct(
        private PDO $pdo,
        string $schema = 'blackops',
        ?PostgreSqlJournalRecordCodec $codec = null,
    ) {
        $this->schema = new PostgreSqlJournalSchema($schema);
        $this->codec = $codec ?? new PostgreSqlJournalRecordCodec();
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->pdo->exec($statement);
            }
        } catch (PDOException $exception) {
            throw new JournalWriteFailed('Failed to migrate PostgreSQL journal schema.', previous: $exception);
        }
    }

    public function append(JournalRecord $record): void
    {
        $table = $this->schema->journalTable();
        $sql = "INSERT INTO {$table} (
            record_id,
            operation_id,
            sequence,
            event,
            attempt_id,
            schema_version,
            occurred_at,
            encoded_record
        ) VALUES (
            :record_id,
            :operation_id,
            :sequence,
            :event,
            :attempt_id,
            :schema_version,
            :occurred_at,
            convert_to(:encoded_record, 'UTF8')
        )";

        try {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false) {
                throw new RuntimeException('Failed to prepare PostgreSQL journal insert.');
            }

            $statement->execute([
                'record_id' => $record->recordId->toString(),
                'operation_id' => $record->operation->id->toString(),
                'sequence' => $record->sequence,
                'event' => $record->event->value,
                'attempt_id' => $record->attempt?->id->toString(),
                'schema_version' => $record->schemaVersion,
                'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                'encoded_record' => $this->codec->encode($record),
            ]);
        } catch (Throwable $exception) {
            throw new JournalWriteFailed('Failed to append PostgreSQL journal record.', previous: $exception);
        }
    }

    public function records(OperationId $operationId): iterable
    {
        $table = $this->schema->journalTable();
        $sql = "SELECT convert_from(encoded_record, 'UTF8') AS encoded_record
            FROM {$table}
            WHERE operation_id = :operation_id
            ORDER BY sequence ASC";

        try {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false) {
                throw new RuntimeException('Failed to prepare PostgreSQL journal read.');
            }

            $statement->execute([
                'operation_id' => $operationId->toString(),
            ]);

            while (true) {
                /** @var mixed $row */
                $row = $statement->fetch(PDO::FETCH_ASSOC);

                if ($row === false) {
                    break;
                }

                if (!is_array($row)) {
                    throw new RuntimeException('PostgreSQL journal row was not an array.');
                }

                /** @var mixed $payload */
                $payload = $row['encoded_record'] ?? null;

                if (!is_string($payload)) {
                    throw new RuntimeException('PostgreSQL journal row did not contain an encoded record.');
                }

                yield $this->codec->decode($payload);
            }
        } catch (Throwable $exception) {
            throw new JournalReadFailed('Failed to read PostgreSQL journal records.', previous: $exception);
        }
    }
}
