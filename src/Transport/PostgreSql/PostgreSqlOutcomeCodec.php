<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Outcome;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use Throwable;

final readonly class PostgreSqlOutcomeCodec
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private PostgreSqlJournalValueCodec $values = new PostgreSqlJournalValueCodec(),
        private PostgreSqlJson $json = new PostgreSqlJson(),
    ) {}

    public function encode(Outcome $outcome): PostgreSqlEncodedOutcome
    {
        try {
            return new PostgreSqlEncodedOutcome(
                $outcome::class,
                self::SCHEMA_VERSION,
                $this->json->encode($this->values->encode($outcome)),
            );
        } catch (Throwable $exception) {
            throw new OutcomeStoreException('Failed to encode PostgreSQL outcome.', previous: $exception);
        }
    }

    public function decode(string $type, int $schemaVersion, string $payload): Outcome
    {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new OutcomeStoreException('PostgreSQL outcome schema version is unsupported.');
        }

        try {
            $decoded = $this->json->decode($payload);
            $payloadType = $this->json->string($decoded, '__class');
        } catch (Throwable $exception) {
            throw new OutcomeStoreException('PostgreSQL outcome payload is corrupt.', previous: $exception);
        }

        if ($payloadType !== $type) {
            throw new OutcomeStoreException('PostgreSQL outcome type does not match its payload.');
        }

        if (!class_exists($type) || !is_subclass_of($type, Outcome::class)) {
            throw new OutcomeStoreException('PostgreSQL outcome type is not an Outcome.');
        }

        try {
            $outcome = $this->values->decode($decoded);
        } catch (Throwable $exception) {
            throw new OutcomeStoreException('PostgreSQL outcome payload is corrupt.', previous: $exception);
        }

        if (!$outcome instanceof Outcome || $outcome::class !== $type) {
            throw new OutcomeStoreException('PostgreSQL outcome payload restored an invalid type.');
        }

        return $outcome;
    }
}
