<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;
use DateTimeZone;

#[PublicApi]
final readonly class ScheduleContext
{
    private DateTimeImmutable $scheduledAt;

    public function __construct(
        private string $name,
        DateTimeImmutable $scheduledAt,
        private string $timezone,
    ) {
        if (preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Schedule context is invalid.');
        }
        if ($timezone !== 'UTC' && !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('Schedule context is invalid.');
        }
        $this->scheduledAt = $scheduledAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function scheduledAt(): DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
