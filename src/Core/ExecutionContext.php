<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Operationの伝播と追跡に必要な不変Metadataを保持する不変Context。
 *
 * 現時点ではCore Context、Attempt、Deadlineを先行実装する。Actor、Tenant、Idempotency Key、
 * Context ExtensionはOptional Getterとして後方互換な拡張で後続追加する。
 *
 * 生成と遷移はInternal Factoryが行い、利用者はGetterでの読み取りのみを許可する。
 * 公開 `with...()` Methodは提供しない。
 */
#[PublicApi]
final readonly class ExecutionContext
{
    private DateTimeImmutable $receivedAt;
    private ?DateTimeImmutable $deadline;

    public function __construct(
        private OperationId $operationId,
        DateTimeImmutable $receivedAt,
        private CorrelationId $correlationId,
        private ?CausationId $causationId = null,
        private ?AttemptContext $attempt = null,
        ?DateTimeImmutable $deadline = null,
    ) {
        $this->receivedAt = $this->toUtc($receivedAt);
        $this->deadline = $deadline === null ? null : $this->toUtc($deadline);
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function receivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }

    public function causationId(): ?CausationId
    {
        return $this->causationId;
    }

    public function attempt(): ?AttemptContext
    {
        return $this->attempt;
    }

    public function deadline(): ?DateTimeImmutable
    {
        return $this->deadline;
    }

    private function toUtc(DateTimeImmutable $time): DateTimeImmutable
    {
        if ($time->getTimezone()->getName() === 'UTC') {
            return $time;
        }

        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
