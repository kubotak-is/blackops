<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class PostgreSqlObserverReplaySelector
{
    public const OPERATION = 'operation';
    public const RECORD = 'record';
    public const TIME = 'time';

    private function __construct(
        public string $kind,
        public ?OperationId $operationId,
        public ?JournalRecordId $recordId,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $to,
    ) {}

    public static function operation(OperationId $id): self
    {
        return new self(self::OPERATION, $id, null, null, null);
    }

    public static function record(JournalRecordId $id): self
    {
        return new self(self::RECORD, null, $id, null, null);
    }

    public static function time(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        $from = $from->setTimezone(new DateTimeZone('UTC'));
        $to = $to->setTimezone(new DateTimeZone('UTC'));
        if ($from >= $to) {
            throw new InvalidArgumentException('Replay time range requires from before to.');
        }
        return new self(self::TIME, null, null, $from, $to);
    }
}
