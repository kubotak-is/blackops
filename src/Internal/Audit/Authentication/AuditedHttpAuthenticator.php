<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Core\TenantRef;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Gates an explicit authentication result on its durable audit event.
 *
 * The separate guard methods keep entry, result, and post-write transaction
 * checks explicit at this security boundary.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class AuditedHttpAuthenticator implements HttpAuthenticator
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        HttpAuthenticator $authenticator,
        #[\SensitiveParameter]
        AuthenticationAuditEventWriter $writer,
        #[\SensitiveParameter]
        Connection $connection,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'authenticator' => $authenticator,
            'writer' => $writer,
            'connection' => $connection,
        ]);
    }

    /**
     * The explicit state branches preserve the denial and release ordering
     * required by the authentication boundary.
     *
     * @mago-expect lint:halstead
     */
    public function authenticate(#[\SensitiveParameter] ServerRequestInterface $request): AuthenticationResult
    {
        $connection = $this->connection();
        $this->assertIdle($connection);

        $authenticator = $this->value('authenticator');
        if (!$authenticator instanceof HttpAuthenticator) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $result = $authenticator->authenticate($request);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::ResourceUnavailable);
        }
        if (!$result instanceof AuthenticationResult) {
            throw self::failure(AuditDeliveryFailure::ResourceUnavailable);
        }

        $state = $this->state($result);
        if ($state === self::INVALID) {
            try {
                $this->assertIdle($connection);
                $writer = $this->value('writer');
                if (!$writer instanceof AuthenticationAuditEventWriter) {
                    return $result;
                }
                $writer->write($result);
                $this->assertIdle($connection);
            } catch (Throwable) {
                return $result;
            }

            return $result;
        }

        try {
            $this->assertIdle($connection);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }

        if ($state === self::ANONYMOUS) {
            return $result;
        }

        $writer = $this->value('writer');
        if (!$writer instanceof AuthenticationAuditEventWriter) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $writer->write($result);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $this->assertIdle($connection);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }

        return $result;
    }

    /** @return array<never, never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never, never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw self::failure(AuditDeliveryFailure::StorageUnavailable);
    }

    private const int ANONYMOUS = 0;
    private const int AUTHENTICATED = 1;
    private const int INVALID = 2;

    private function connection(): Connection
    {
        $connection = $this->value('connection');
        if (!$connection instanceof Connection) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $connection;
    }

    private function state(#[\SensitiveParameter] AuthenticationResult $result): int
    {
        try {
            $anonymous = $result->isAnonymous();
            $authenticated = $result->isAuthenticated();
            $invalid = $result->isInvalid();
            if (!is_bool($anonymous) || !is_bool($authenticated) || !is_bool($invalid)) {
                throw new \LogicException();
            }
            if (((int) $anonymous + (int) $authenticated + (int) $invalid) !== 1) {
                throw new \LogicException();
            }
            $actor = $result->actor();
            $tenant = $result->tenant();
            if ($authenticated) {
                if (!$actor instanceof ActorRef || $tenant !== null && !$tenant instanceof TenantRef) {
                    throw new \LogicException();
                }

                return self::AUTHENTICATED;
            }
            if ($actor !== null || $tenant !== null) {
                throw new \LogicException();
            }
            if ($anonymous) {
                return self::ANONYMOUS;
            }

            return self::INVALID;
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function assertIdle(#[\SensitiveParameter] Connection $connection): void
    {
        try {
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw new \LogicException();
            }
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['authenticator', 'writer', 'connection']
            || !$value['authenticator'] instanceof HttpAuthenticator
            || !$value['writer'] instanceof AuthenticationAuditEventWriter
            || !$value['connection'] instanceof Connection
            || !array_key_exists($key, $value)
        ) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value[$key];
    }

    private static function failure(AuditDeliveryFailure $failure): AuditDeliveryException
    {
        return new AuditDeliveryException($failure);
    }

    private static function remap(#[\SensitiveParameter] AuditDeliveryException $failure): AuditDeliveryException
    {
        try {
            return self::failure($failure->failure());
        } catch (Throwable) {
            return self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }
}
