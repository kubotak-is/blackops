<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

/** @mago-expect lint:block-statement */
final readonly class AuditRecordTime
{
    public function __construct(
        public string $occurredAt,
        public string $recordedAt,
    ) {
        self::assertTimestamp($occurredAt);
        self::assertTimestamp($recordedAt);
    }

    /** @return array{occurredAt:string,recordedAt:string} */
    public function toArray(): array
    {
        return ['occurredAt' => $this->occurredAt, 'recordedAt' => $this->recordedAt];
    }

    public static function assertTimestamp(string $value): void
    {
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z$/D', $value) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.u\\Z', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)
            || $date->format('Y-m-d\\TH:i:s.u\\Z') !== $value
        )
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
    }
}
