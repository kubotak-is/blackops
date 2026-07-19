<?php

declare(strict_types=1);

namespace BlackOps\Status\Exception;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class OperationStatusQueryException extends RuntimeException
{
    public const string AUTHORIZATION_FAILED = 'status_query.authorization_failed';
    public const string STORAGE_FAILED = 'status_query.storage_failed';
    public const string DECODE_FAILED = 'status_query.decode_failed';
    public const string INTEGRITY_FAILED = 'status_query.integrity_failed';

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
