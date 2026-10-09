<?php

declare(strict_types=1);

namespace BlackOps\Migrations\PostgreSql;

use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointSchema;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

final class Version20261001000000 extends AbstractMigration
{
    private readonly PostgreSqlAuditCheckpointSchema $checkpointSchema;

    public function __construct(Connection $connection, LoggerInterface $logger, string $schema)
    {
        parent::__construct($connection, $logger);
        $this->checkpointSchema = new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema($schema));
    }

    public function getDescription(): string
    {
        return 'Add the PostgreSQL audit checkpoint persistence schema.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->checkpointSchema->statements() as $statement) {
            $this->addSql($statement);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Audit checkpoint inputs and complete bytes are retained for integrity evidence.',
        );
    }
}
