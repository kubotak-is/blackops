<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditRecordTime;

final class PostgreSqlAuditSegmentTextFields
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const string HASH = '/^[0-9a-f]{64}$/D';

    private const string FENCING_TOKEN_PATTERN = '/^aft:v1:[0-9a-f]{64}$/D';

    private const array SEGMENT_NULLABLE_PATTERNS = [
        'head_record_hash' => self::HASH,
        'predecessor_record_hash' => self::HASH,
        'successor_segment_id' => self::UUID_V7,
        'close_fencing_token' => self::FENCING_TOKEN_PATTERN,
    ];

    private function __construct() {}

    /**
     * @param array<array-key,mixed> $row
     * @return array{headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string}
     */
    public static function read(#[\SensitiveParameter] array $row, #[\SensitiveParameter] string $segmentId): array
    {
        $head = $row['head_record_hash'];
        $predecessor = $row['predecessor_record_hash'];
        $opened = $row['opened_at'];
        $closed = $row['closed_at'];
        $successor = $row['successor_segment_id'];
        $token = $row['close_fencing_token'];
        if (!is_string($opened) || $closed !== null && !is_string($closed)) {
            throw new \RuntimeException();
        }
        foreach (self::SEGMENT_NULLABLE_PATTERNS as $field => $pattern) {
            $value = $row[$field];
            if ($value !== null && (!is_string($value) || preg_match($pattern, $value) !== 1)) {
                throw new \RuntimeException();
            }
        }
        /** @var ?string $head */
        /** @var ?string $predecessor */
        /** @var string $opened */
        /** @var ?string $closed */
        /** @var ?string $successor */
        /** @var ?string $token */
        if ($successor !== null && $successor === $segmentId) {
            throw new \RuntimeException();
        }
        AuditRecordTime::assertTimestamp($opened);
        if ($closed !== null) {
            AuditRecordTime::assertTimestamp($closed);
        }

        return [
            'headRecordHash' => $head,
            'predecessorRecordHash' => $predecessor,
            'openedAt' => $opened,
            'closedAt' => $closed,
            'successorSegmentId' => $successor,
            'closeFencingToken' => $token,
        ];
    }
}
