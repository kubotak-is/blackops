<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditValidationException;
use InvalidArgumentException;

final class AuditHistoryScalar
{
    private const array NULLABLE_KINDS = [
        '?I' => true,
        '?U' => true,
        '?H' => true,
        '?T' => true,
        '?F' => true,
        '?N' => true,
        '?stepOrdinal' => true,
        '?length' => true,
        'null' => true,
    ];

    private const array NULLABLE_ALIASES = [
        '?I' => 'I',
        '?U' => 'U',
        '?H' => 'H',
        '?T' => 'T',
        '?F' => 'F',
        '?N' => 'N',
        '?stepOrdinal' => 'stepOrdinal',
        '?length' => 'length',
    ];

    private const array INTEGER_BOUNDS = [
        'v1' => [1, 1],
        'phase' => [1, 15],
        'N' => [1, AuditValidationException::MAX_SAFE_INTEGER],
        'Z' => [0, AuditValidationException::MAX_SAFE_INTEGER],
        'stepCount' => [0, 4],
        'ordinal' => [1, 4],
        'stepOrdinal' => [1, 3],
        'ordinalZero' => [0, 4],
        'closedVersion' => [2, AuditValidationException::MAX_SAFE_INTEGER - 1],
        'length' => [1, 1_048_575],
    ];

    private const array STRING_KINDS = [
        'I' => true,
        'S' => true,
        'P' => true,
        'U' => true,
        'H' => true,
        'F' => true,
        'T' => true,
        'stateClosed' => true,
        'stateSuccessor' => true,
        'stateOpen' => true,
        'checkpointArtifact' => true,
        'productionClass' => true,
        'stepPhase' => true,
        'transitionCause' => true,
        'systemPartition' => true,
    ];

    private const array STRING_PATTERNS = [
        'S' => '/^[a-z_][a-z0-9_]{0,62}$/D',
        'P' => '/^(?:system:global|tenant:[a-f0-9]{64})$/D',
        'U' => '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
        'H' => '/^[0-9a-f]{64}$/D',
        'F' => '/^aft:v1:[0-9a-f]{64}$/D',
    ];

    private const array STRING_VALUES = [
        'systemPartition' => ['system:global' => true],
        'stateClosed' => ['closed' => true, 'sealed' => true],
        'stateSuccessor' => ['open' => true, 'closed' => true, 'sealed' => true],
        'stateOpen' => ['open' => true],
        'checkpointArtifact' => ['audit_checkpoint' => true],
        'productionClass' => ['ordinary' => true, 'control' => true],
        'stepPhase' => [
            'preparation_bound' => true,
            'complete_candidate' => true,
            'artifact_persisted' => true,
            'ingress_acknowledged' => true,
        ],
        'transitionCause' => ['root_revision' => true, 'segment_advance' => true],
    ];

    private function __construct() {}

    public static function admit(
        #[\SensitiveParameter]
        string $kind,
        #[\SensitiveParameter]
        mixed $value,
    ): bool|int|string|null {
        if ($value === null) {
            return array_key_exists($kind, self::NULLABLE_KINDS)
                ? null
                : throw new InvalidArgumentException('History scalar is invalid.');
        }

        $kind = self::NULLABLE_ALIASES[$kind] ?? $kind;

        return match (true) {
            $kind === 'B' => self::admitBoolean($value),
            array_key_exists($kind, self::INTEGER_BOUNDS) => self::admitInteger($kind, $value),
            array_key_exists($kind, self::STRING_KINDS) => self::admitString($kind, $value),
            default => throw new InvalidArgumentException('History scalar is invalid.'),
        };
    }

    private static function admitBoolean(#[\SensitiveParameter] mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('History boolean scalar is invalid.');
        }

        return $value;
    }

    private static function admitInteger(#[\SensitiveParameter] string $kind, #[\SensitiveParameter] mixed $value): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException('History integer scalar is invalid.');
        }

        $bounds = self::INTEGER_BOUNDS[$kind];
        [$minimum, $maximum] = $bounds;
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('History integer scalar is invalid.');
        }

        return $value;
    }

    private static function admitString(
        #[\SensitiveParameter]
        string $kind,
        #[\SensitiveParameter]
        mixed $value,
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException('History string scalar is invalid.');
        }

        try {
            AuditValidationException::assertSafeString($value, 1, 256);
            if ($kind === 'T') {
                AuditRecordTime::assertTimestamp($value);

                return $value;
            }
        } catch (\Throwable) {
            throw new InvalidArgumentException('History string scalar is invalid.');
        }

        $pattern = self::STRING_PATTERNS[$kind] ?? '/(?!)/D';
        $values = self::STRING_VALUES[$kind] ?? [];
        $valid = array_key_exists($value, $values) || $kind === 'I' || preg_match($pattern, $value) === 1;
        if (!$valid) {
            throw new InvalidArgumentException('History string scalar is invalid.');
        }

        return $value;
    }
}
