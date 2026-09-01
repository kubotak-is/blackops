<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260901000000 extends AbstractMigration
{
    private readonly PostgreSqlAuditOutboxSchema $outboxSchema;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema($schema));
    }

    public function getDescription(): string
    {
        return 'Add the PostgreSQL audit outbox persistence schema.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->outboxSchema->statements() as $statement) {
            $this->addSql($statement);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Audit outbox records are retained for delivery evidence.');
    }
}
