<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

#[PublicApi]
final readonly class RetentionHold
{
    public function __construct(
        private RetentionHoldId $id,
        private OperationId $operationId,
        private RetentionHoldCategory $category,
        private string $reason,
        private DateTimeImmutable $placedAt,
        private RetentionActorRef $placedBy,
        private ?DateTimeImmutable $releasedAt = null,
        private ?RetentionActorRef $releasedBy = null,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Retention hold reason must not be empty.');
        }

        if (($releasedAt === null) !== ($releasedBy === null)) {
            throw new InvalidArgumentException('Retention hold release metadata must be complete.');
        }

        if ($releasedAt !== null && $releasedAt < $placedAt) {
            throw new InvalidArgumentException('Retention hold release time must not be before placement time.');
        }
    }

    public function id(): RetentionHoldId
    {
        return $this->id;
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function category(): RetentionHoldCategory
    {
        return $this->category;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function placedAt(): DateTimeImmutable
    {
        return $this->utc($this->placedAt);
    }

    public function placedBy(): RetentionActorRef
    {
        return $this->placedBy;
    }

    public function releasedAt(): ?DateTimeImmutable
    {
        return $this->releasedAt === null ? null : $this->utc($this->releasedAt);
    }

    public function releasedBy(): ?RetentionActorRef
    {
        return $this->releasedBy;
    }

    public function isActive(): bool
    {
        return $this->releasedAt === null;
    }

    public function release(DateTimeImmutable $releasedAt, RetentionActorRef $releasedBy): self
    {
        if (!$this->isActive()) {
            throw new LogicException('Retention hold is already released.');
        }

        return new self(
            $this->id,
            $this->operationId,
            $this->category,
            $this->reason,
            $this->placedAt,
            $this->placedBy,
            $releasedAt,
            $releasedBy,
        );
    }

    private function utc(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
