<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\ClaimHeartbeat;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Execution\OperationReceiver;
use BlackOps\Core\Identifier\OperationId;
use DateInterval;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Throwable;

final readonly class PostgreSqlDeferredOperationReceiver implements OperationReceiver, ClaimHeartbeat
{
    private PostgreSqlDeferredOperationSchema $schema;
    private DateInterval $leaseDuration;
    private PostgreSqlDeferredOperationLeaseStore $leases;
    private PostgreSqlDeferredOperationMessageCodec $messages;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
        string $leaseOwner = 'blackops-worker',
        int $leaseSeconds = 60,
        private ClockInterface $clock = new PostgreSqlSystemClock(),
    ) {
        if ($leaseOwner === '') {
            throw new InvalidArgumentException('Lease owner must not be empty.');
        }

        if ($leaseSeconds < 1) {
            throw new InvalidArgumentException('Lease duration must be positive.');
        }

        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
        $this->leaseDuration = new DateInterval('PT' . $leaseSeconds . 'S');
        $this->leases = new PostgreSqlDeferredOperationLeaseStore($connection, $this->schema, $leaseOwner);
        $this->messages = new PostgreSqlDeferredOperationMessageCodec();
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to migrate PostgreSQL deferred operation receiver schema.',
                previous: $exception,
            );
        }
    }

    public function claim(ClaimRequest $request): ?OperationClaim
    {
        try {
            return $this->connection->transactional(function () use ($request): ?OperationClaim {
                $row = $this->leases->selectEligible($request->claimedAt());

                if ($row === null) {
                    return null;
                }

                $fencingToken = (int) $row['fencing_token'] + 1;
                $leaseExpiresAt = $request->claimedAt()->add($this->leaseDuration);
                $message = $this->messages->fromRow($row);

                $this->leases->markRunning($message->operationId(), $fencingToken, $leaseExpiresAt);

                return new OperationClaim($message, $this->claimToken($message->operationId(), $fencingToken));
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException(
                'Failed to claim PostgreSQL deferred operation.',
                previous: $exception,
            );
        }
    }

    public function heartbeat(OperationClaim $claim): OperationClaim
    {
        try {
            $token = $this->parseToken($claim);
            $heartbeatAt = $this->clock->now();
            $leaseExpiresAt = $heartbeatAt->add($this->leaseDuration);

            $this->leases->heartbeat($claim->message()->operationId(), $token, $leaseExpiresAt, $heartbeatAt);

            return $claim;
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException(
                'Failed to heartbeat PostgreSQL deferred operation claim.',
                previous: $exception,
            );
        }
    }

    private function claimToken(OperationId $operationId, #[SensitiveParameter] int $fencingToken): string
    {
        return $operationId->toString() . ':' . $fencingToken;
    }

    private function parseToken(OperationClaim $claim): int
    {
        $parts = explode(':', $claim->claimToken(), limit: 2);

        if (count($parts) !== 2 || $parts[0] !== $claim->message()->operationId()->toString()) {
            throw new DeferredTransportException('Deferred operation claim token does not match the operation.');
        }

        if (!ctype_digit($parts[1]) || $parts[1] === '0') {
            throw new DeferredTransportException('Deferred operation claim token has an invalid fencing token.');
        }

        return (int) $parts[1];
    }
}
