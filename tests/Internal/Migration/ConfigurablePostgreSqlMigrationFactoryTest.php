<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Migration;

use BlackOps\Internal\Migration\ConfigurablePostgreSqlMigrationFactory;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ConfigurablePostgreSqlMigrationFactoryTest extends TestCase
{
    public function testRejectsClassOutsidePostgreSqlMigrationNamespace(): void
    {
        $factory = new ConfigurablePostgreSqlMigrationFactory(
            DriverManager::getConnection(['driver' => 'pdo_pgsql']),
            new NullLogger(),
            new PostgreSqlMigrationSchema('blackops_test'),
        );

        $this->expectException(MigrationClassNotFound::class);

        $factory->createVersion(self::class);
    }
}
