<?php

declare(strict_types=1);

namespace BlackOps\Internal\Identifier;

use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use Psr\Clock\ClockInterface;

/**
 * Framework IDの生成を集約するInternal Factory。UUID生成源とClockを内部Portとして注入可能。
 *
 * 生成はUUIDv7とし、Symfony UIDはIdentifierFactory配下でのみ利用し、
 * 公開ID型からはSymfony型を露出させない。
 *
 * このClassはPHP Public APIではなく、Framework内部実装詳細である。
 */
final readonly class IdentifierFactory
{
    public function __construct(
        private Uuidv7Generator $generator,
        private ClockInterface $clock,
    ) {}

    public function newOperationId(): OperationId
    {
        return OperationId::fromString($this->generate());
    }

    public function newAttemptId(): AttemptId
    {
        return AttemptId::fromString($this->generate());
    }

    public function newJournalRecordId(): JournalRecordId
    {
        return JournalRecordId::fromString($this->generate());
    }

    public function newCorrelationId(): CorrelationId
    {
        return CorrelationId::fromString($this->generate());
    }

    public function newCausationId(): CausationId
    {
        return CausationId::fromString($this->generate());
    }

    public function newRetentionHoldId(): RetentionHoldId
    {
        return RetentionHoldId::fromString($this->generate());
    }

    public function newRetentionPurgeAuditId(): RetentionPurgeAuditId
    {
        return RetentionPurgeAuditId::fromString($this->generate());
    }

    private function generate(): string
    {
        return $this->generator->generate($this->clock->now());
    }
}
