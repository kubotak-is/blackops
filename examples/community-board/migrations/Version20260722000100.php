<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move Community Board sessions to the BlackOps session store.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE public.blackops_sessions (
                id UUID PRIMARY KEY,
                identity_id VARCHAR(255) NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                issued_at TIMESTAMPTZ NOT NULL,
                expires_at TIMESTAMPTZ NOT NULL,
                last_used_at TIMESTAMPTZ NOT NULL,
                revoked_at TIMESTAMPTZ NULL,
                rotated_to_id UUID NULL UNIQUE,
                CONSTRAINT blackops_sessions_token_hash_check
                    CHECK (token_hash ~ '^[0-9a-f]{64}$'),
                CONSTRAINT blackops_sessions_expiry_check
                    CHECK (expires_at > issued_at),
                CONSTRAINT blackops_sessions_last_used_check
                    CHECK (last_used_at >= issued_at AND last_used_at <= expires_at),
                CONSTRAINT blackops_sessions_rotated_to_id_fkey
                    FOREIGN KEY (rotated_to_id)
                    REFERENCES public.blackops_sessions (id)
                    ON DELETE SET NULL
            )
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO public.blackops_sessions (
                id,
                identity_id,
                token_hash,
                issued_at,
                expires_at,
                last_used_at,
                revoked_at,
                rotated_to_id
            )
            SELECT
                id,
                user_id::text,
                token_hash,
                issued_at,
                expires_at,
                issued_at,
                revoked_at,
                NULL
            FROM public.board_sessions
            SQL);
        $this->addSql(
            'CREATE INDEX blackops_sessions_identity_idx ON public.blackops_sessions (identity_id, expires_at)',
        );
        $this->addSql('CREATE INDEX blackops_sessions_expiry_cleanup_idx ON public.blackops_sessions (expires_at, id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX blackops_sessions_revocation_cleanup_idx
                ON public.blackops_sessions (revoked_at, id)
                WHERE revoked_at IS NOT NULL
            SQL);
        $this->addSql('DROP TABLE public.board_sessions');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'The session migration cannot be reverted because doing so could destroy rotated session state.',
        );
    }
}
