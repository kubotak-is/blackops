<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:block-statement
 */
final readonly class AuditRecordEvidence
{
    /**
     * The array form is useful at a decoding boundary; the integer form is
     * convenient for typed producers that have no evidence fields yet.
     *
     * @param array<string,string|int|bool|null> $fields
     */
    public function __construct(int|array $version, array $fields = [])
    {
        if (is_array($version)) {
            if (!array_key_exists('version', $version) || !is_int($version['version']))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            $fields = $version;
            unset($fields['version']);
            $version = $version['version'];
        }
        if ($version < 1 || $version > AuditValidationException::MAX_SAFE_INTEGER)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        if ($fields !== [] && array_is_list($fields))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (array_key_exists('version', $fields))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        foreach ($fields as $name => $value) {
            if (!is_string($name))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null)
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if (
                is_int($value)
                && (
                    $value < -AuditValidationException::MAX_SAFE_INTEGER
                    || $value > AuditValidationException::MAX_SAFE_INTEGER
                )
            )
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if (is_string($value))
                AuditValidationException::assertSafeString($value);
        }
        $this->version = $version;
        /** @var array<string,string|int|bool|null> $fields */
        $this->fields = $fields;
    }

    public int $version;

    /** @var array<string,string|int|bool|null> */
    public array $fields;

    /** @return array<string,string|int|bool|null> */
    public function toArray(): array
    {
        return ['version' => $this->version, ...$this->fields];
    }
}
