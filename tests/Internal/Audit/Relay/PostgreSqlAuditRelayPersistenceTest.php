<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Relay\AuditRelayException;
use BlackOps\Internal\Audit\Relay\AuditRelayFailure;
use BlackOps\Internal\Audit\Relay\AuditRelayOutcome;
use BlackOps\Internal\Audit\Relay\AuditRelayPersistence;
use BlackOps\Internal\Audit\Relay\PostgreSqlAuditRelayPersistence;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PostgreSqlAuditRelayPersistenceTest extends TestCase
{
    public function testOutcomeFailureAndExceptionSurfacesAreClosedAndValueFree(): void
    {
        self::assertSame(
            [
                'idle',
                'recovered_pending',
                'recovery_poisoned',
                'relayed_inserted',
                'relayed_existing',
                'retried',
                'poisoned',
                'stale',
            ],
            array_map(static fn(AuditRelayOutcome $outcome): string => $outcome->value, AuditRelayOutcome::cases()),
        );
        self::assertSame(
            ['transaction_invalid', 'resource_unavailable', 'storage_unavailable'],
            array_map(static fn(AuditRelayFailure $failure): string => $failure->value, AuditRelayFailure::cases()),
        );

        $exception = new AuditRelayException(AuditRelayFailure::ResourceUnavailable);
        self::assertSame('Audit relay operation failed.', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertSame(AuditRelayFailure::ResourceUnavailable, $exception->failure());
        self::assertSame('resource_unavailable', $exception->code());
        self::assertSame([], $exception->__debugInfo());
        self::assertSame([], $exception->__serialize());
        foreach ([
            print_r($exception, true),
            var_export($exception, true),
            serialize($exception),
            json_encode($exception, JSON_THROW_ON_ERROR),
            json_encode($exception->getTrace(), JSON_THROW_ON_ERROR),
        ] as $surface) {
            self::assertStringNotContainsString('lease-token', $surface);
            self::assertStringNotContainsString('resource_unavailable', $surface);
        }

        try {
            $exception->__unserialize(['canary' => 'lease-token']);
            self::fail('exception unserialization accepted data');
        } catch (AuditRelayException $failure) {
            self::assertSame(AuditRelayFailure::StorageUnavailable, $failure->failure());
            foreach ([
                print_r($failure, true),
                var_export($failure, true),
                serialize($failure),
                json_encode($failure, JSON_THROW_ON_ERROR),
                json_encode($failure->getTrace(), JSON_THROW_ON_ERROR),
            ] as $surface) {
                self::assertStringNotContainsString('lease-token', $surface);
            }
            self::assertNull($failure->getPrevious());
        }
    }

    public function testPersistenceSurfaceAndAdapterDelegationAreExact(): void
    {
        $interface = new ReflectionClass(AuditRelayPersistence::class);
        self::assertTrue($interface->isInterface());
        self::assertSame(
            [
                'recoverNextExpired',
                'claimNext',
                'appendCanonical',
                'retry',
                'relay',
                'poison',
            ],
            array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $interface->getMethods()),
        );
        $methods = [
            'recoverNextExpired' => [AuditOutboxLookupResult::class, []],
            'claimNext' => [AuditOutboxLookupResult::class, []],
            'appendCanonical' => [
                AuditAppendResult::class,
                [
                    ['logical', CanonicalAuditLogicalRecord::class, false, true],
                ],
            ],
            'retry' => [AuditOutboxRecord::class, $this->fenceParameters()],
            'relay' => [AuditOutboxRecord::class, $this->fenceParameters()],
            'poison' => [AuditOutboxRecord::class, $this->fenceParameters()],
        ];
        foreach ([$interface, new ReflectionClass(PostgreSqlAuditRelayPersistence::class)] as $contract) {
            foreach ($methods as $name => [$returnType, $parameters]) {
                $this->assertMethodContract($contract, $name, $returnType, $parameters);
            }
        }

        $adapter = new ReflectionClass(PostgreSqlAuditRelayPersistence::class);
        self::assertTrue($adapter->isFinal());
        self::assertTrue($adapter->isReadOnly());
        $parameters = $adapter->getConstructor()?->getParameters() ?? [];
        self::assertSame(
            [PostgreSqlAuditOutboxStore::class, PostgreSqlAuditRecordStore::class],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $parameters),
        );

        $source = file_get_contents(__DIR__
        . '/../../../../src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php');
        self::assertIsString($source);
        self::assertSame([], $this->adapterContractViolations($source));
    }

    public function testAdapterRejectsIndependentOneToOneDelegationMutations(): void
    {
        $source = file_get_contents(__DIR__
        . '/../../../../src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php');
        self::assertIsString($source);
        $mutations = [
            'replaced lookup operation' => str_replace(
                'return $this->outbox->recoverNextExpired();',
                'return $this->outbox->claimNext();',
                $source,
            ),
            'wrapped lookup result' => str_replace(
                'return $this->outbox->claimNext();',
                'return AuditOutboxLookupResult::notFound();',
                $source,
            ),
            'replaced append result and inserted flag' => str_replace(
                'return $this->records->appendDurable($logical);',
                'return new AuditAppendResult($this->records->appendDurable($logical)->record(), false);',
                $source,
            ),
            'reordered retry arguments' => str_replace(
                'return $this->outbox->retry($recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken);',
                'return $this->outbox->retry($recordId, $expectedTenant, $expectedPartitionId, $expectedToken, $expectedVersion);',
                $source,
            ),
            'replaced relay argument' => str_replace(
                'return $this->outbox->relay($recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken);',
                'return $this->outbox->relay($recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $recordId);',
                $source,
            ),
            'translated exception' =>
                str_replace('return $this->outbox->poison(', 'try { return $this->outbox->poison(', $source)
                    . ' catch (\\Throwable) { throw new AuditRelayException(AuditRelayFailure::StorageUnavailable); }',
            'added transaction control' => str_replace(
                'return $this->outbox->relay($recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken);',
                '$this->outbox->beginTransaction(); return $this->outbox->relay($recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken);',
                $source,
            ),
            'reordered poison arguments' => str_replace(
                "return \$this->outbox->poison(\n            \$recordId,\n            \$expectedTenant,\n            \$expectedPartitionId,\n            \$expectedVersion,\n            \$expectedToken,\n        );",
                "return \$this->outbox->poison(\n            \$recordId,\n            \$expectedTenant,\n            \$expectedPartitionId,\n            \$expectedToken,\n            \$expectedVersion,\n        );",
                $source,
            ),
        ];

        $kills = 0;
        foreach ($mutations as $name => $mutated) {
            self::assertNotSame($source, $mutated, $name . ' did not mutate the candidate');
            self::assertNotSame([], $this->adapterContractViolations($mutated), $name . ' survived');
            ++$kills;
        }
        self::assertSame(count($mutations), $kills);
    }

    public function testAdapterRethrowsStoreFailureWithoutRelayTranslation(): void
    {
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $adapter = new PostgreSqlAuditRelayPersistence($outbox, $records);

        try {
            $adapter->recoverNextExpired();
            self::fail('uninitialised outbox store unexpectedly returned');
        } catch (AuditOutboxStoreException $failure) {
            self::assertSame('storage_unavailable', $failure->code());
            self::assertNull($failure->getPrevious());
            self::assertNotInstanceOf(AuditRelayException::class, $failure);
        }

        $logical = new ReflectionClass(CanonicalAuditLogicalRecord::class)->newInstanceWithoutConstructor();
        try {
            $adapter->appendCanonical($logical);
            self::fail('uninitialised record store unexpectedly returned');
        } catch (AuditStoreException $failure) {
            self::assertSame('storage_unavailable', $failure->code());
            self::assertNull($failure->getPrevious());
            self::assertNotInstanceOf(AuditRelayException::class, $failure);
        }
    }

    /** @return list<string> */
    private function adapterContractViolations(string $source): array
    {
        $expected = [
            'recoverNextExpired' => 'return$this->outbox->recoverNextExpired();',
            'claimNext' => 'return$this->outbox->claimNext();',
            'appendCanonical' => 'return$this->records->appendDurable($logical);',
            'retry' => 'return$this->outbox->retry($recordId,$expectedTenant,$expectedPartitionId,$expectedVersion,$expectedToken);',
            'relay' => 'return$this->outbox->relay($recordId,$expectedTenant,$expectedPartitionId,$expectedVersion,$expectedToken);',
            'poison' => 'return$this->outbox->poison($recordId,$expectedTenant,$expectedPartitionId,$expectedVersion,$expectedToken,);',
        ];
        $violations = [];
        foreach ($expected as $method => $body) {
            $actual = preg_replace('/\s+/', '', $this->methodBody($source, $method));
            if (!is_string($actual) || trim($actual) !== $body) {
                $violations[] = $method . ': body';
            }
        }
        foreach ([
            'beginTransaction',
            'commit(',
            'rollBack(',
            'catch',
            'Clock',
            'Uuid',
            'sleep(',
            'random_',
            'file_get_contents',
        ] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                $violations[] = 'forbidden:' . $forbidden;
            }
        }
        return $violations;
    }

    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, 'public function ' . $method . '(');
        if ($start === false) {
            return '';
        }
        $open = strpos($source, '{', $start);
        if ($open === false) {
            return '';
        }
        $depth = 0;
        $length = strlen($source);
        for ($index = $open; $index < $length; ++$index) {
            if ($source[$index] === '{') {
                ++$depth;
                continue;
            }
            if ($source[$index] !== '}') {
                continue;
            }
            --$depth;
            if ($depth === 0) {
                return substr($source, $open + 1, $index - $open - 1);
            }
        }
        return '';
    }

    /** @return list<array{string,class-string,bool,bool}> */
    private function fenceParameters(): array
    {
        return [
            ['recordId',            'string',                   false, true],
            ['expectedTenant',      AuditTypedReference::class, true,  true],
            ['expectedPartitionId', 'string',                   false, true],
            ['expectedVersion',     'int',                      false, true],
            ['expectedToken',       'string',                   false, true],
        ];
    }

    /**
     * @param list<array{string,class-string,bool,bool}> $expectedParameters
     */
    private function assertMethodContract(
        ReflectionClass $contract,
        string $name,
        string $returnType,
        array $expectedParameters,
    ): void {
        $method = $contract->getMethod($name);
        self::assertTrue($method->isPublic(), $contract->getName() . '::' . $name . ' is not public');
        self::assertSame($returnType, $method->getReturnType()?->getName());
        $parameters = $method->getParameters();
        self::assertCount(count($expectedParameters), $parameters);
        foreach ($expectedParameters as $index => [$parameterName, $parameterType, $nullable, $sensitive]) {
            $parameter = $parameters[$index];
            self::assertSame($parameterName, $parameter->getName());
            self::assertSame($parameterType, $parameter->getType()?->getName());
            self::assertSame($nullable, $parameter->allowsNull());
            self::assertSame($sensitive, $parameter->getAttributes(\SensitiveParameter::class) !== []);
            self::assertFalse($parameter->isOptional());
        }
    }
}
