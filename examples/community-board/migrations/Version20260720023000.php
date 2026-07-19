<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720023000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CreateCommunityBoardIdentityTables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE public.board_users (
                id UUID NOT NULL,
                email_canonical VARCHAR(254) NOT NULL,
                email_display VARCHAR(254) NOT NULL,
                display_name VARCHAR(80) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT board_users_email_canonical_unique UNIQUE (email_canonical)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE public.board_sessions (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                token_hash CHAR(64) NOT NULL,
                issued_at TIMESTAMPTZ NOT NULL,
                expires_at TIMESTAMPTZ NOT NULL,
                revoked_at TIMESTAMPTZ DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT board_sessions_token_hash_unique UNIQUE (token_hash),
                CONSTRAINT board_sessions_user_foreign FOREIGN KEY (user_id)
                    REFERENCES public.board_users (id)
            )
            SQL);
        $this->addSql('CREATE INDEX board_sessions_user_id_index ON public.board_sessions (user_id)');
        $this->addSql(
            'CREATE INDEX board_sessions_active_token_index ON public.board_sessions (token_hash, expires_at) WHERE revoked_at IS NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public.board_sessions');
        $this->addSql('DROP TABLE public.board_users');
    }
}
