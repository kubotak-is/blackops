<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use BlackOps\Database\DatabaseManager;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlSessionStore implements SessionStore
{
    private string $table;

    public function __construct(
        private DatabaseManager $databases,
        string $table = 'public.blackops_sessions',
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/D', $table) !== 1) {
            throw new InvalidArgumentException('Session store table name is invalid.');
        }

        $this->table = $table;
    }

    public function insert(NewSessionRecord $session): void
    {
        $this->connection()->transactional(function (Connection $connection) use ($session): void {
            $connection->executeStatement('INSERT INTO ' . $this->table . ' (
                    id, identity_id, token_hash, issued_at, expires_at, last_used_at
                ) VALUES (
                    :id, :identity_id, :token_hash, :issued_at, :expires_at, :last_used_at
                )', [
                'id' => $session->id,
                'identity_id' => $session->identityId,
                'token_hash' => $session->tokenHash,
                'issued_at' => $this->timestamp($session->issuedAt),
                'expires_at' => $this->timestamp($session->expiresAt),
                'last_used_at' => $this->timestamp($session->issuedAt),
            ]);
        });
    }

    public function authenticate(string $tokenHash, DateTimeImmutable $now, DateTimeImmutable $touchThreshold): ?string
    {
        return $this->connection()->transactional(function (Connection $connection) use (
            $tokenHash,
            $now,
            $touchThreshold,
        ): ?string {
            $row = $connection->fetchAssociative(
                'UPDATE ' . $this->table . '
                 SET last_used_at = :now
                 WHERE token_hash = :token_hash
                   AND revoked_at IS NULL
                   AND expires_at > :now
                   AND last_used_at <= :touch_threshold
                 RETURNING identity_id, token_hash',
                [
                    'token_hash' => $tokenHash,
                    'now' => $this->timestamp($now),
                    'touch_threshold' => $this->timestamp($touchThreshold),
                ],
            );

            if (!is_array($row)) {
                $row = $connection->fetchAssociative(
                    'SELECT identity_id, token_hash
                     FROM '
                    . $this->table
                    . '
                     WHERE token_hash = :token_hash
                       AND revoked_at IS NULL
                       AND expires_at > :now
                       AND last_used_at > :touch_threshold',
                    [
                        'token_hash' => $tokenHash,
                        'now' => $this->timestamp($now),
                        'touch_threshold' => $this->timestamp($touchThreshold),
                    ],
                );
            }

            if (!is_array($row) || !is_string($row['token_hash'] ?? null)) {
                return null;
            }

            if (!hash_equals($row['token_hash'], $tokenHash)) {
                return null;
            }

            return is_string($row['identity_id'] ?? null) ? $row['identity_id'] : null;
        });
    }

    public function rotate(
        string $tokenHash,
        string $successorId,
        string $successorTokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): ?string {
        try {
            return $this->connection()->transactional(function (Connection $connection) use (
                $tokenHash,
                $successorId,
                $successorTokenHash,
                $issuedAt,
                $expiresAt,
            ): ?string {
                $row = $connection->fetchAssociative(
                    'SELECT id, identity_id, token_hash
                     FROM ' . $this->table . '
                     WHERE token_hash = :token_hash
                     FOR UPDATE',
                    ['token_hash' => $tokenHash],
                );

                if (!is_array($row) || !is_string($row['token_hash'] ?? null)) {
                    return null;
                }

                $active = $connection->fetchOne(
                    'SELECT 1
                     FROM ' . $this->table . '
                     WHERE id = :id
                       AND revoked_at IS NULL
                       AND rotated_to_id IS NULL
                       AND expires_at > :now',
                    ['id' => $row['id'], 'now' => $this->timestamp($issuedAt)],
                );

                if ($active === false || !hash_equals($row['token_hash'], $tokenHash)) {
                    return null;
                }

                if (!is_string($row['identity_id'] ?? null)) {
                    return null;
                }

                $identityId = $row['identity_id'];

                $connection->executeStatement('INSERT INTO ' . $this->table . ' (
                        id, identity_id, token_hash, issued_at, expires_at, last_used_at
                    ) VALUES (
                        :id, :identity_id, :token_hash, :issued_at, :expires_at, :last_used_at
                    )', [
                    'id' => $successorId,
                    'identity_id' => $identityId,
                    'token_hash' => $successorTokenHash,
                    'issued_at' => $this->timestamp($issuedAt),
                    'expires_at' => $this->timestamp($expiresAt),
                    'last_used_at' => $this->timestamp($issuedAt),
                ]);
                $updated = $connection->executeStatement(
                    'UPDATE ' . $this->table . '
                     SET revoked_at = :revoked_at, rotated_to_id = :successor_id
                     WHERE id = :id
                       AND revoked_at IS NULL
                       AND rotated_to_id IS NULL
                       AND expires_at > :revoked_at',
                    [
                        'id' => $row['id'],
                        'revoked_at' => $this->timestamp($issuedAt),
                        'successor_id' => $successorId,
                    ],
                );

                if ($updated !== 1) {
                    throw new SessionRotationConflict();
                }

                return $identityId;
            });
        } catch (SessionRotationConflict) {
            return null;
        }
    }

    public function revoke(string $tokenHash, DateTimeImmutable $revokedAt): void
    {
        $this->connection()->transactional(function (Connection $connection) use ($tokenHash, $revokedAt): void {
            $row = $connection->fetchAssociative(
                'SELECT id, token_hash
                 FROM ' . $this->table . '
                 WHERE token_hash = :token_hash
                 FOR UPDATE',
                ['token_hash' => $tokenHash],
            );

            if (!is_array($row) || !is_string($row['token_hash'] ?? null)) {
                return;
            }

            if (!hash_equals($row['token_hash'], $tokenHash)) {
                return;
            }

            $connection->executeStatement(
                'UPDATE ' . $this->table . '
                 SET revoked_at = :revoked_at
                 WHERE id = :id AND revoked_at IS NULL',
                ['id' => $row['id'], 'revoked_at' => $this->timestamp($revokedAt)],
            );
        });
    }

    public function cleanup(DateTimeImmutable $retentionCutoff, DateTimeImmutable $now): int
    {
        return (int) $this->connection()->executeStatement(
            'DELETE FROM ' . $this->table . '
             WHERE (
                expires_at < :retention_cutoff
                OR (revoked_at IS NOT NULL AND revoked_at < :retention_cutoff)
             )
             AND (revoked_at IS NOT NULL OR expires_at <= :now)',
            [
                'retention_cutoff' => $this->timestamp($retentionCutoff),
                'now' => $this->timestamp($now),
            ],
        );
    }

    private function connection(): Connection
    {
        return $this->databases->connection();
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
