<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720214000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CreateCommunityBoardPostAndCommentTables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE public.board_posts (
                id UUID NOT NULL,
                author_id UUID NOT NULL,
                title VARCHAR(120) NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT board_posts_title_length CHECK (char_length(title) BETWEEN 1 AND 120),
                CONSTRAINT board_posts_body_length CHECK (char_length(body) BETWEEN 1 AND 10000),
                CONSTRAINT board_posts_author_foreign FOREIGN KEY (author_id)
                    REFERENCES public.board_users (id) ON DELETE RESTRICT
            )
            SQL);
        $this->addSql('CREATE INDEX board_posts_feed_index ON public.board_posts (created_at DESC, id DESC)');
        $this->addSql('CREATE INDEX board_posts_author_index ON public.board_posts (author_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE public.board_comments (
                id UUID NOT NULL,
                post_id UUID NOT NULL,
                author_id UUID NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT board_comments_body_length CHECK (char_length(body) BETWEEN 1 AND 2000),
                CONSTRAINT board_comments_post_foreign FOREIGN KEY (post_id)
                    REFERENCES public.board_posts (id) ON DELETE CASCADE,
                CONSTRAINT board_comments_author_foreign FOREIGN KEY (author_id)
                    REFERENCES public.board_users (id) ON DELETE RESTRICT
            )
            SQL);
        $this->addSql(
            'CREATE INDEX board_comments_detail_index ON public.board_comments (post_id, created_at ASC, id ASC)',
        );
        $this->addSql('CREATE INDEX board_comments_author_index ON public.board_comments (author_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public.board_comments');
        $this->addSql('DROP TABLE public.board_posts');
    }
}
