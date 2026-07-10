<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
final readonly class RetentionPlanItem
{
    public function __construct(
        private OperationId $operationId,
        private RetentionTarget $target,
        private DateTimeImmutable $basisAt,
        private DateTimeImmutable $eligibleAt,
    ) {
        if ($eligibleAt < $basisAt) {
            throw new InvalidArgumentException('Retention plan item eligible time must not be before basis time.');
        }
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function target(): RetentionTarget
    {
        return $this->target;
    }

    public function basisAt(): DateTimeImmutable
    {
        return $this->utc($this->basisAt);
    }

    public function eligibleAt(): DateTimeImmutable
    {
        return $this->utc($this->eligibleAt);
    }

    private function utc(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
