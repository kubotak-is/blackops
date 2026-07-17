<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CreateQuickstartOrderTables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE public.quickstart_orders (reference VARCHAR(64) NOT NULL, PRIMARY KEY (reference))',
        );
        $this->addSql(
            'CREATE TABLE public.quickstart_order_commits (reference VARCHAR(64) NOT NULL, PRIMARY KEY (reference))',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public.quickstart_order_commits');
        $this->addSql('DROP TABLE public.quickstart_orders');
    }
}
