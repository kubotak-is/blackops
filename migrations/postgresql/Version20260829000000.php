<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20260829000000 extends AbstractMigration
{
    private readonly PostgreSqlAuditSchema $auditSchema;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->auditSchema = new PostgreSqlAuditSchema($schema);
    }

    public function getDescription(): string
    {
        return 'Add the PostgreSQL canonical audit store schema.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->auditSchema->statements() as $statement) {
            $this->addSql($statement);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Canonical audit records are retained for integrity evidence.');
    }
}
