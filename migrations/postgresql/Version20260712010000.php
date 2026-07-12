<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260712010000 extends AbstractMigration
{
    private readonly PostgreSqlMigrationSchema $schemaName;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->schemaName = new PostgreSqlMigrationSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Make retention holds and purge audits independent from operations rows.';
    }

    public function up(Schema $schema): void
    {
        $audits = $this->schemaName->table('retention_purge_audits');
        $holds = $this->schemaName->table('retention_holds');
        $this->addSql("ALTER TABLE {$holds}
            DROP CONSTRAINT IF EXISTS retention_holds_operation_id_fkey");
        $this->addSql("ALTER TABLE {$audits}
            DROP CONSTRAINT IF EXISTS retention_purge_audits_operation_id_fkey");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Operation references cannot be restored because inline hold and audit rows may not have operations rows.',
        );
    }
}
