<?php

declare(strict_types=1);

namespace BlackOps\OperationData\Exception;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class OperationJournalQueryException extends RuntimeException
{
    public const string AUTHORIZATION_FAILED = 'operation_journal.authorization_failed';
    public const string STORAGE_FAILED = 'operation_journal.storage_failed';
    public const string PROTECTION_FAILED = 'operation_journal.protection_failed';
    public const string DECODE_FAILED = 'operation_journal.decode_failed';
    public const string INTEGRITY_FAILED = 'operation_journal.integrity_failed';

    private function __construct(
        private readonly string $queryCode,
    ) {
        parent::__construct($queryCode);
    }

    public static function authorizationFailed(): self
    {
        return new self(self::AUTHORIZATION_FAILED);
    }

    public static function storageFailed(): self
    {
        return new self(self::STORAGE_FAILED);
    }

    public static function protectionFailed(): self
    {
        return new self(self::PROTECTION_FAILED);
    }

    public static function decodeFailed(): self
    {
        return new self(self::DECODE_FAILED);
    }

    public static function integrityFailed(): self
    {
        return new self(self::INTEGRITY_FAILED);
    }

    public function queryCode(): string
    {
        return $this->queryCode;
    }
}
