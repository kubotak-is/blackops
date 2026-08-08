<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;

final readonly class PostgreSqlStorageProtectionRotationSql
{
    /** @param list<string> $where @param list<mixed> $params @param list<ParameterType|ArrayParameterType|string|Type> $types */
    public function __construct(
        public array $where,
        /** @var list<mixed> */
        public array $params,
        /** @var list<ParameterType|ArrayParameterType|string|Type> */
        public array $types,
    ) {}
}
