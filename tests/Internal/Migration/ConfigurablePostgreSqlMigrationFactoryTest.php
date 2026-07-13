<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Migration {
    use App\Migrations\Version20260713000001;
    use BlackOps\Internal\Migration\ConfigurablePostgreSqlMigrationFactory;
    use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
    use Doctrine\DBAL\DriverManager;
    use Doctrine\Migrations\Exception\MigrationClassNotFound;
    use PHPUnit\Framework\TestCase;
    use Psr\Log\NullLogger;

    final class ConfigurablePostgreSqlMigrationFactoryTest extends TestCase
    {
        public function testCreatesApplicationMigrationWithDoctrineStandardConstructor(): void
        {
            $migration = $this->factory()->createVersion(Version20260713000001::class);

            self::assertInstanceOf(Version20260713000001::class, $migration);
        }

        public function testRejectsClassOutsideSupportedMigrationNamespaces(): void
        {
            $this->expectException(MigrationClassNotFound::class);

            $this->factory()->createVersion(UnsupportedNamespaceMigration::class);
        }

        public function testRejectsNonMigrationClass(): void
        {
            $this->expectException(MigrationClassNotFound::class);

            $this->factory()->createVersion(self::class);
        }

        private function factory(): ConfigurablePostgreSqlMigrationFactory
        {
            return new ConfigurablePostgreSqlMigrationFactory(
                DriverManager::getConnection([
                    'driver' => 'pdo_pgsql',
                    'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
                    'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
                    'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
                    'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
                    'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
                ]),
                new NullLogger(),
                new PostgreSqlMigrationSchema('blackops_test'),
            );
        }
    }

    final class UnsupportedNamespaceMigration extends \Doctrine\Migrations\AbstractMigration
    {
        public function up(\Doctrine\DBAL\Schema\Schema $schema): void {}

        public function down(\Doctrine\DBAL\Schema\Schema $schema): void {}
    }
}

namespace App\Migrations {
    use Doctrine\DBAL\Schema\Schema;
    use Doctrine\Migrations\AbstractMigration;

    final class Version20260713000001 extends AbstractMigration
    {
        public function up(Schema $schema): void {}

        public function down(Schema $schema): void {}
    }
}
