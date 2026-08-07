<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use RuntimeException;

final class OperationDataSubjectReadFailure extends RuntimeException
{
    public const string STORAGE = 'storage';
    public const string INTEGRITY = 'integrity';

    private function __construct(
        public readonly string $kind,
    ) {
        parent::__construct('Operation data subject read failed.');
    }

    public static function storage(): self
    {
        return new self(self::STORAGE);
    }

    public static function integrity(): self
    {
        return new self(self::INTEGRITY);
    }
}
