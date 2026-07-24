<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CreateCommunityBoardNotificationTable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE public.board_notifications (
                id UUID NOT NULL,
                recipient_user_id UUID NOT NULL,
                source_post_id UUID NOT NULL,
                source_comment_id UUID NOT NULL,
                delivery_operation_id UUID NOT NULL,
                created_at TIMESTAMPTZ NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT board_notifications_delivery_operation_unique UNIQUE (delivery_operation_id)
            )
            SQL);
        $this->addSql(
            'CREATE INDEX board_notifications_recipient_created_index ON public.board_notifications (recipient_user_id, created_at DESC, id DESC)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX public.board_notifications_recipient_created_index');
        $this->addSql('DROP TABLE public.board_notifications');
    }
}
