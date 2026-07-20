<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CreateCommunityBoardDigestTable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE public.board_digests (
                id UUID NOT NULL,
                requested_user_id UUID NOT NULL,
                week CHAR(8) NOT NULL,
                content VARCHAR(255) NOT NULL,
                post_count INTEGER NOT NULL,
                comment_count INTEGER NOT NULL,
                created_at TIMESTAMPTZ NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT board_digests_week_shape CHECK (
                    week ~ '^[0-9]{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$'
                ),
                CONSTRAINT board_digests_content_length CHECK (char_length(content) BETWEEN 1 AND 255),
                CONSTRAINT board_digests_post_count_non_negative CHECK (post_count >= 0),
                CONSTRAINT board_digests_comment_count_non_negative CHECK (comment_count >= 0),
                CONSTRAINT board_digests_requested_user_foreign FOREIGN KEY (requested_user_id)
                    REFERENCES public.board_users (id) ON DELETE RESTRICT
            )
            SQL);
        $this->addSql(
            'CREATE INDEX board_digests_requested_user_created_index ON public.board_digests (requested_user_id, created_at DESC)',
        );
        $this->addSql('CREATE INDEX board_comments_created_at_index ON public.board_comments (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public.board_digests');
        $this->addSql('DROP INDEX public.board_comments_created_at_index');
    }
}
