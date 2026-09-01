<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Delivery\AuditOutboxTransition;
use BlackOps\Internal\Audit\Outbox\AuditOutboxAppendResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelope;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class AuditOutboxStorageArchitectureTest extends TestCase
{
    public function testExactTenFileInventoryAndTypeModifiersAreClosed(): void
    {
        $root = $this->repositoryRoot();
        $expected = $this->expectedInventory();
        self::assertSame($expected, $this->inventory($root));

        $fixtureRoot = sys_get_temp_dir() . '/blackops-audit-outbox-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($fixtureRoot . '/src/Internal/Audit/Outbox', 0700, true));
        try {
            foreach (array_keys($expected) as $relative) {
                $target = $fixtureRoot . '/' . $relative;
                self::assertTrue(mkdir(dirname($target), 0700, true) || is_dir(dirname($target)));
                self::assertTrue(copy($root . '/' . $relative, $target));
            }
            $extra = $fixtureRoot . '/src/Internal/Audit/Outbox/Nested/Extra.php';
            self::assertTrue(mkdir(dirname($extra), 0700, true));
            self::assertIsInt(file_put_contents(
                $extra,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Outbox;\nfinal class Extra {}\n",
            ));
            self::assertNotSame($expected, $this->inventory($fixtureRoot));
        } finally {
            $this->removeDirectory($fixtureRoot);
        }

        $ordinary = [
            AuditOutboxStoreFailure::class,
            AuditOutboxStoreException::class,
            AuditOutboxRecord::class,
            AuditOutboxAppendResult::class,
            AuditOutboxLookupResult::class,
            PostgreSqlAuditOutboxStore::class,
        ];
        foreach ($ordinary as $type) {
            $reflection = new ReflectionClass($type);
            self::assertTrue($reflection->isFinal(), $type);
            self::assertFalse($reflection->isReadOnly(), $type);
        }
        foreach ([
            AuditOutboxIdempotencyKeyHasher::class,
            PostgreSqlAuditProtectedIngressHydrator::class,
            PostgreSqlAuditOutboxRecordHydrator::class,
            PostgreSqlAuditOutboxSchema::class,
        ] as $type) {
            $reflection = new ReflectionClass($type);
            self::assertTrue($reflection->isFinal(), $type);
            self::assertTrue($reflection->isReadOnly(), $type);
        }
        self::assertTrue(new ReflectionClass(AuditOutboxStoreFailure::class)->isEnum());
    }

    public function testPublicProtectedApisAndConstructorMatricesAreExact(): void
    {
        $expectedMethods = [
            AuditOutboxStoreFailure::class => ['cases', 'from', 'tryFrom'],
            AuditOutboxStoreException::class => [
                '__construct',
                'failure',
                'code',
                '__debugInfo',
                '__serialize',
                '__unserialize',
            ],
            AuditOutboxRecord::class => [
                'fromParts',
                'protectedIngress',
                'stateSnapshot',
                'toArray',
                '__debugInfo',
                '__serialize',
                '__unserialize',
            ],
            AuditOutboxAppendResult::class => [
                '__construct',
                'record',
                'inserted',
                '__debugInfo',
                '__serialize',
                '__unserialize',
            ],
            AuditOutboxLookupResult::class => [
                'notFound',
                'fromRecord',
                'isFound',
                'record',
                '__debugInfo',
                '__serialize',
                '__unserialize',
            ],
            AuditOutboxIdempotencyKeyHasher::class => ['hash'],
            PostgreSqlAuditProtectedIngressHydrator::class => ['hydrate'],
            PostgreSqlAuditOutboxRecordHydrator::class => ['__construct', 'hydrate'],
            PostgreSqlAuditOutboxSchema::class => [
                '__construct',
                'schemaName',
                'partitionStateTable',
                'recordsTable',
                'statements',
            ],
            PostgreSqlAuditOutboxStore::class => [
                '__construct',
                'appendDurable',
                'find',
                'claimNext',
                'recoverNextExpired',
                'retry',
                'relay',
                'poison',
            ],
        ];
        foreach ($expectedMethods as $type => $expected) {
            $actual = [];
            foreach (new ReflectionClass($type)->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $type) {
                    continue;
                }
                if ($method->isPublic() || $method->isProtected()) {
                    $actual[] = $method->getName();
                }
            }
            sort($actual);
            sort($expected);
            self::assertSame($expected, $actual, $type);
        }

        self::assertNull(new ReflectionClass(AuditOutboxIdempotencyKeyHasher::class)->getConstructor());
        self::assertNull(new ReflectionClass(PostgreSqlAuditProtectedIngressHydrator::class)->getConstructor());
        $this->assertConstructor(
            AuditOutboxStoreException::class,
            'public',
            [
                ['storeFailure', AuditOutboxStoreFailure::class, false, false, null, 0],
            ],
        );
        $this->assertConstructor(
            AuditOutboxRecord::class,
            'private',
            [
                ['protectedIngress', AuditProtectedIngress::class,    false, false, null, 1],
                ['stateSnapshot',    AuditOutboxStateSnapshot::class, false, false, null, 1],
            ],
        );
        $this->assertConstructor(
            AuditOutboxAppendResult::class,
            'public',
            [
                ['record',   AuditOutboxRecord::class, false, false, null, 1],
                ['inserted', 'bool',                   false, false, null, 1],
            ],
        );
        $this->assertConstructor(
            AuditOutboxLookupResult::class,
            'private',
            [
                ['found', 'bool', false, false, null, 1],
                ['record', '?' . AuditOutboxRecord::class, false, false, null, 1],
            ],
        );
        $this->assertConstructor(
            PostgreSqlAuditOutboxRecordHydrator::class,
            'public',
            [
                ['protectedIngress', PostgreSqlAuditProtectedIngressHydrator::class, false, false, null, 0],
            ],
        );
        $this->assertConstructor(
            PostgreSqlAuditOutboxSchema::class,
            'public',
            [
                ['schema', PostgreSqlMigrationSchema::class, false, false, null, 0],
            ],
        );
        $this->assertConstructor(
            PostgreSqlAuditOutboxStore::class,
            'public',
            [
                ['connection',    Connection::class,                          false, false, null, 0],
                ['schema',        PostgreSqlAuditOutboxSchema::class,         false, false, null, 0],
                ['clock',         ClockInterface::class,                      false, false, null, 0],
                ['uuids',         Uuidv7Generator::class,                     false, false, null, 0],
                ['validator',     AuditRecordValidator::class,                false, false, null, 0],
                ['finalizer',     AuditRecordFinalizer::class,                false, false, null, 0],
                ['partitions',    AuditPartitionIdDeriver::class,             false, false, null, 0],
                ['envelopes',     AuditIngressEnvelopeCodec::class,           false, false, null, 0],
                ['protection',    AuditProtectedBodyCodec::class,             false, false, null, 0],
                ['hasher',        AuditOutboxIdempotencyKeyHasher::class,     false, false, null, 0],
                ['hydrator',      PostgreSqlAuditOutboxRecordHydrator::class, false, false, null, 0],
                ['configuration', AuditOutboxConfiguration::class,            false, false, null, 0],
            ],
        );

        $this->assertMethod(
            AuditOutboxStoreException::class,
            'failure',
            'public',
            AuditOutboxStoreFailure::class,
            false,
            [],
        );
        $this->assertMethod(AuditOutboxStoreException::class, 'code', 'public', 'string', false, []);
        $this->assertMethod(
            AuditOutboxRecord::class,
            'fromParts',
            'public',
            AuditOutboxRecord::class,
            true,
            [
                ['protectedIngress', AuditProtectedIngress::class,    1, false, false, null],
                ['stateSnapshot',    AuditOutboxStateSnapshot::class, 1, false, false, null],
            ],
        );
        $this->assertMethod(AuditOutboxAppendResult::class, 'record', 'public', AuditOutboxRecord::class, false, []);
        $this->assertMethod(AuditOutboxAppendResult::class, 'inserted', 'public', 'bool', false, []);
        $this->assertMethod(
            AuditOutboxLookupResult::class,
            'notFound',
            'public',
            AuditOutboxLookupResult::class,
            true,
            [],
        );
        $this->assertMethod(
            AuditOutboxLookupResult::class,
            'fromRecord',
            'public',
            AuditOutboxLookupResult::class,
            true,
            [
                ['record', AuditOutboxRecord::class, 1, false, false, null],
            ],
        );
        $this->assertMethod(AuditOutboxLookupResult::class, 'isFound', 'public', 'bool', false, []);
        $this->assertMethod(
            AuditOutboxLookupResult::class,
            'record',
            'public',
            '?' . AuditOutboxRecord::class,
            false,
            [],
        );
        $this->assertMethod(
            AuditOutboxIdempotencyKeyHasher::class,
            'hash',
            'public',
            'string',
            false,
            [
                ['partitionId',    'string', 1, false, false, null],
                ['idempotencyKey', 'string', 1, false, false, null],
            ],
        );
        $this->assertMethod(
            PostgreSqlAuditProtectedIngressHydrator::class,
            'hydrate',
            'public',
            AuditProtectedIngress::class,
            false,
            [
                ['row', 'array', 1, false, false, null],
            ],
        );
        $this->assertMethod(
            PostgreSqlAuditOutboxRecordHydrator::class,
            'hydrate',
            'public',
            AuditOutboxRecord::class,
            false,
            [
                ['row',           'array',                         1, false, false, null],
                ['configuration', AuditOutboxConfiguration::class, 0, false, false, null],
            ],
        );
        foreach (['schemaName', 'partitionStateTable', 'recordsTable'] as $method) {
            $this->assertMethod(PostgreSqlAuditOutboxSchema::class, $method, 'public', 'string', false, []);
        }
        $this->assertMethod(PostgreSqlAuditOutboxSchema::class, 'statements', 'public', 'array', false, []);
        $this->assertMethod(
            PostgreSqlAuditOutboxStore::class,
            'appendDurable',
            'public',
            'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxAppendResult',
            false,
            [
                ['draft',          AuditRecordDraft::class, 1, false, false, null],
                ['idempotencyKey', 'string',                1, false, false, null],
            ],
        );
        $this->assertMethod(
            PostgreSqlAuditOutboxStore::class,
            'find',
            'public',
            AuditOutboxLookupResult::class,
            false,
            [
                ['recordId', 'string', 1, false, false, null],
                ['expectedTenant', '?' . AuditTypedReference::class, 1, false, false, null],
                ['expectedPartitionId', 'string', 1, false, false, null],
            ],
        );
        foreach (['claimNext', 'recoverNextExpired'] as $method) {
            $this->assertMethod(
                PostgreSqlAuditOutboxStore::class,
                $method,
                'public',
                AuditOutboxLookupResult::class,
                false,
                [],
            );
        }
        foreach (['retry', 'relay', 'poison'] as $method) {
            $this->assertMethod(
                PostgreSqlAuditOutboxStore::class,
                $method,
                'public',
                AuditOutboxRecord::class,
                false,
                [
                    ['recordId', 'string', 1, false, false, null],
                    ['expectedTenant', '?' . AuditTypedReference::class, 1, false, false, null],
                    ['expectedPartitionId', 'string', 1, false, false, null],
                    ['expectedVersion', 'int', 1, false, false, null],
                    ['expectedToken', 'string', 1, false, false, null],
                ],
            );
        }
    }

    public function testEveryDataBearingParameterIsSensitiveWithoutPrivateLayoutAuthority(): void
    {
        foreach (array_values($this->expectedInventory()) as $types) {
            foreach ($types as $type) {
                $reflection = new ReflectionClass($type);
                foreach ($reflection->getMethods() as $method) {
                    if ($method->getDeclaringClass()->getName() !== $type) {
                        continue;
                    }
                    foreach ($method->getParameters() as $parameter) {
                        $expected = $this->isDataBearingParameter($parameter) ? 1 : 0;
                        self::assertSame(
                            $expected,
                            count($parameter->getAttributes(\SensitiveParameter::class)),
                            $type . '::' . $method->getName() . ' $' . $parameter->getName(),
                        );
                    }
                }
            }
        }
    }

    public function testSensitiveParameterOracleSeesArbitraryScalarAndThrowableMutations(): void
    {
        eval(<<<'PHP'
            namespace BlackOps\Tests\Architecture;

            final class SensitiveParameterMutationProbe
            {
                private function renamed(int $opaque, string $payload, \Throwable $failure): void
                {
                }
            }
            PHP);
        $method = new ReflectionMethod(SensitiveParameterMutationProbe::class, 'renamed');
        $expected = array_fill(0, count($method->getParameters()), 1);
        $actual = array_map(
            fn(ReflectionParameter $parameter): int => count($parameter->getAttributes(\SensitiveParameter::class)),
            $method->getParameters(),
        );

        self::assertSame(
            [true, true, true],
            array_map(fn(ReflectionParameter $parameter): bool => $this->isDataBearingParameter(
                $parameter,
            ), $method->getParameters()),
        );
        self::assertNotSame($expected, $actual, 'an unannotated arbitrary scalar mutation must be rejected');

        eval(<<<'PHP'
            namespace BlackOps\Tests\Architecture;

            final class UnionParameterMutationProbe
            {
                private function renamed(string|int $opaque): void
                {
                }
            }

            final class IntersectionParameterMutationProbe
            {
                private function renamed(\DateTimeImmutable&\DateTimeInterface $opaque): void
                {
                }
            }
            PHP);
        $unionMethod = new ReflectionMethod(UnionParameterMutationProbe::class, 'renamed');
        $intersectionMethod = new ReflectionMethod(IntersectionParameterMutationProbe::class, 'renamed');
        self::assertTrue($this->isDataBearingParameter($unionMethod->getParameters()[0]));
        self::assertTrue($this->isDataBearingParameter($intersectionMethod->getParameters()[0]));
        self::assertTrue($this->hasSensitiveParameterViolation($unionMethod));
        self::assertTrue($this->hasSensitiveParameterViolation($intersectionMethod));

        eval(<<<'PHP'
            namespace BlackOps\Tests\Architecture;

            final class BooleanParameterMutationProbe
            {
                private function arbitrary(bool $opaque): void
                {
                }
            }
            PHP);
        $booleanMethod = new ReflectionMethod(BooleanParameterMutationProbe::class, 'arbitrary');
        $booleanParameter = $booleanMethod->getParameters()[0];
        self::assertFalse(
            $this->isDataBearingParameter($booleanParameter),
            'a private control boolean must not become sensitive by helper layout',
        );
        eval(<<<'PHP'
            namespace BlackOps\Tests\Architecture;

            final class SyntheticResult
            {
                private function renamed(bool $result): void
                {
                }
            }
            PHP);
        $resultMethod = new ReflectionMethod(SyntheticResult::class, 'renamed');
        $resultParameter = $resultMethod->getParameters()[0];
        self::assertTrue(
            $this->isDataBearingParameter($resultParameter),
            'a result-state boolean must not be exempt by its private helper name',
        );
        self::assertNotSame(
            1,
            count($resultParameter->getAttributes(\SensitiveParameter::class)),
            'an unannotated arbitrary result boolean mutation must be rejected',
        );
        self::assertTrue($this->hasSensitiveParameterViolation($resultMethod));
        $appendParameter = new ReflectionMethod(AuditOutboxAppendResult::class, '__construct')->getParameters()[1];
        $lookupParameter = new ReflectionMethod(AuditOutboxLookupResult::class, '__construct')->getParameters()[0];
        self::assertTrue($this->isDataBearingParameter($appendParameter));
        self::assertTrue($this->isDataBearingParameter($lookupParameter));
        self::assertSame(1, count($appendParameter->getAttributes(\SensitiveParameter::class)));
        self::assertSame(1, count($lookupParameter->getAttributes(\SensitiveParameter::class)));
        self::assertTrue($this->hasSensitiveParameterViolation($method));
        self::assertFalse($this->hasSensitiveParameterViolation($appendParameter->getDeclaringFunction()));
        self::assertFalse($this->hasSensitiveParameterViolation($lookupParameter->getDeclaringFunction()));
    }

    public function testDependencyAndRuntimeBoundaryRejectsSubstitutionAndRegistration(): void
    {
        $expectedDependencies = [
            'AuditOutboxAppendResult.php' => [
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            'AuditOutboxIdempotencyKeyHasher.php' => [
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'AuditOutboxLookupResult.php' => [
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            'AuditOutboxRecord.php' => [
                'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxStateSnapshot',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedIngress',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            'AuditOutboxStoreException.php' => [
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
            ],
            'AuditOutboxStoreFailure.php' => [],
            'PostgreSqlAuditOutboxRecordHydrator.php' => [
                'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration',
                'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxStateSnapshot',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditProtectedIngressHydrator',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'PostgreSqlAuditOutboxSchema.php' => [
                'BlackOps\\Internal\\Migration\\PostgreSqlMigrationSchema',
                'SensitiveParameter',
            ],
            'PostgreSqlAuditOutboxStore.php' => [
                'BlackOps\\Core\\Audit\\AuditRecordDraft',
                'BlackOps\\Core\\Audit\\AuditRecordTime',
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Core\\Audit\\AuditValidationFailure',
                'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration',
                'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxException',
                'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxStateSnapshot',
                'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxTransition',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxAppendResult',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxLookupResult',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'BlackOps\\Internal\\Audit\\Protection\\AuditIngressEnvelope',
                'BlackOps\\Internal\\Audit\\Protection\\AuditIngressEnvelopeCodec',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedIngress',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException',
                'BlackOps\\Internal\\Audit\\Record\\AuditPartitionIdDeriver',
                'BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer',
                'BlackOps\\Internal\\Audit\\Record\\AuditRecordStamp',
                'BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator',
                'BlackOps\\Internal\\Audit\\Record\\CanonicalAuditLogicalRecord',
                'BlackOps\\Internal\\Identifier\\Uuidv7Generator',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxIdempotencyKeyHasher',
                'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxRecordHydrator',
                'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxSchema',
                'DateTimeImmutable',
                'DateTimeZone',
                'Doctrine\\DBAL\\Connection',
                'Doctrine\\DBAL\\ParameterType',
                'Psr\\Clock\\ClockInterface',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'PostgreSqlAuditProtectedIngressHydrator.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException',
                'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedIngress',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlBytea',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
        ];
        $root = $this->repositoryRoot();
        foreach ($expectedDependencies as $basename => $expected) {
            $source = file_get_contents($root . '/src/Internal/Audit/Outbox/' . $basename);
            self::assertIsString($source);
            sort($expected);
            self::assertSame($expected, $this->dependencies($source), $basename);
            self::assertDoesNotMatchRegularExpression('/^use\\s+[^;]+\\bas\\b/m', $source, $basename);
            self::assertStringNotContainsString('PublicApi', $source, $basename);
            foreach ([
                'BlackOps\\Application\\',
                'BlackOps\\Container\\',
                'BlackOps\\Console\\',
                'BlackOps\\Http\\',
                'BlackOps\\Outbox\\',
                'BlackOps\\Scheduler\\',
                'BlackOps\\Worker\\',
                'BlackOps\\Transport\\Generic\\',
                'RuntimeRegistration',
                'class-string',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $basename);
            }
            self::assertSame(0, $this->heredocTokens($source), $basename);
            self::assertSame(0, $this->traitTokens($source), $basename);
            self::assertSame(0, $this->dynamicInvocationCount($source), $basename);
        }

        $sources = $this->sourceFiles($root . '/src');
        self::assertSame([], $this->outsideReferences($sources));
        $sources['Internal/Other/Caller.php'] = '<?php use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord; $x = new AuditOutboxRecord();';
        self::assertSame(['Internal/Other/Caller.php'], $this->outsideReferences($sources));
        $sources['Internal/Other/AliasCaller.php'] = '<?php use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord as Record; $x = new Record();';
        $sources['Internal/Other/GroupedCaller.php'] = '<?php use BlackOps\\Internal\\Audit\\Outbox\\{AuditOutboxRecord}; $x = new AuditOutboxRecord();';
        $sources['Internal/Other/FullyQualifiedCaller.php'] = '<?php $x = new \\BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord();';
        $sources['Internal/Other/ClassStringCaller.php'] = '<?php use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord; $x = AuditOutboxRecord::class;';
        $sources['Internal/Other/ReflectionClassStringCaller.php'] = '<?php $x = new \\ReflectionClass(\\BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord::class);';
        $sources['Internal/Other/Decoys.php'] = <<<'PHP'
            <?php
            // BlackOps\Internal\Audit\Outbox\AuditOutboxRecord
            $comment = 'BlackOps\Internal\Audit\Outbox\AuditOutboxRecord';
            $ordinary = "new AuditOutboxRecord()";
            $ordinaryTarget = 'BlackOps\Internal\Audit\Outbox\AuditOutboxRecord';
            PHP;
        $sources['Internal/Other/LiteralClassStringCaller.php'] = "<?php \\class_exists('BlackOps\\\\Internal\\\\Audit\\\\Outbox\\\\AuditOutboxRecord');";
        $sources['Internal/Other/VariableClassStringCaller.php'] = <<<'PHP'
            <?php
            $raw = 'BlackOps\Internal\Audit\Outbox\AuditOutboxRecord';
            $class = $raw;
            new \ReflectionClass($class);
            PHP;
        self::assertSame(
            [
                'Internal/Other/AliasCaller.php',
                'Internal/Other/Caller.php',
                'Internal/Other/ClassStringCaller.php',
                'Internal/Other/FullyQualifiedCaller.php',
                'Internal/Other/GroupedCaller.php',
                'Internal/Other/LiteralClassStringCaller.php',
                'Internal/Other/ReflectionClassStringCaller.php',
                'Internal/Other/VariableClassStringCaller.php',
            ],
            $this->outsideReferences($sources),
        );

        $store = $sources['Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php'];
        $storeDependencies = $expectedDependencies['PostgreSqlAuditOutboxStore.php'];
        sort($storeDependencies);
        $hasher = $sources['Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php'];
        $replacementCount = 0;
        $mutatedHasher = str_replace(
            'string $partitionId,',
            'AuditOutboxRecord $record,' . PHP_EOL . '        string $partitionId,',
            $hasher,
            $replacementCount,
        );
        self::assertIsString($mutatedHasher);
        self::assertSame(1, $replacementCount);
        self::assertContains(
            'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord',
            $this->dependencies($mutatedHasher),
            'same-namespace type hints must be resolved without an import',
        );
        $inlineDependency = $store . "\n\\BlackOps\\Application\\SyntheticDependency::class;\n";
        $mutatedDependencies = $this->dependencies($inlineDependency);
        self::assertContains('BlackOps\\Application\\SyntheticDependency', $mutatedDependencies);
        self::assertNotSame($storeDependencies, $mutatedDependencies);
        $decoyClass = chr(92) . 'BlackOps' . chr(92) . 'Application' . chr(92) . 'DecoyDependency';
        $dependencyDecoys =
            $store
            . "\n// {$decoyClass}::class\n\$ordinaryDependency = '{$decoyClass}';\n\$unusedRaw = '{$decoyClass}';\n\$unusedAlias = \$unusedRaw;\n";
        self::assertSame($storeDependencies, $this->dependencies($dependencyDecoys));
        $twoHop = <<<'PHP'
            <?php
            $raw = 'BlackOps\Internal\Audit\Outbox\AuditOutboxRecord';
            $class = $raw;
            new ReflectionClass($class);
            PHP;
        self::assertContains(
            'BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord',
            $this->dependencies($twoHop),
            'class-string aliases must be propagated to a fixed point',
        );
        $dependencyFixture = <<<'PHP'
            <?php
            namespace BlackOps\Synthetic;

            use Vendor\ImportedDependency as ImportedAlias;
            use Vendor\{GroupedDependency};

            final class DependencyFixture
            {
                private \Vendor\PropertyDependency $property;

                public function resolve(ImportedAlias $parameter, GroupedDependency $group): \Vendor\ReturnDependency
                {
                    return new \Vendor\ConstructedDependency($parameter, $group);
                }
            }
            PHP;
        $fixtureDependencies = $this->dependencies($dependencyFixture);
        foreach ([
            'Vendor\\ImportedDependency',
            'Vendor\\GroupedDependency',
            'Vendor\\PropertyDependency',
            'Vendor\\ReturnDependency',
            'Vendor\\ConstructedDependency',
        ] as $dependency) {
            self::assertContains($dependency, $fixtureDependencies, $dependency);
        }
        $dynamic = '<?php $object->{$method}(); $object?->{$method}(); $factory::{$method}(); new $class();';
        self::assertGreaterThan(0, $this->dynamicInvocationCount($dynamic));
        self::assertGreaterThan(0, $this->heredocTokens("<?php <<<TEXT\nvalue\nTEXT;"));
        self::assertGreaterThan(0, $this->traitTokens('<?php trait Added {} use Added;'));
    }

    public function testConstructionProtectionTransitionAndFinalizerCallsitesHaveOneOwner(): void
    {
        $root = $this->repositoryRoot();
        $sources = $this->sourceFiles($root . '/src');
        $expected = [
            'AuditProtectedIngress::fromArray' => [
                'Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1,
                'Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php' => 1,
            ],
            'AuditOutboxStateSnapshot::fromArray' => [
                'Internal/Audit/Delivery/AuditOutboxTransition.php' => 1,
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php' => 1,
            ],
            'AuditOutboxStateSnapshot::pending' => [
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1,
            ],
            'AuditOutboxRecord::fromParts' => [
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php' => 1,
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 4,
            ],
            'new AuditOutboxTransition' => [
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1,
            ],
            'protectOutbox' => [
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1,
            ],
            'unprotectOutbox' => [
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1,
            ],
            'finalizer->finalize' => [
                'Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1,
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1,
            ],
        ];
        foreach ($expected as $call => $expectedFiles) {
            $actual = [];
            foreach ($sources as $path => $source) {
                $count = match ($call) {
                    'new AuditOutboxTransition' => $this->constructorCalls($source, 'AuditOutboxTransition'),
                    'AuditProtectedIngress::fromArray' => $this->staticCalls(
                        $source,
                        'AuditProtectedIngress',
                        'fromArray',
                    ),
                    'AuditOutboxStateSnapshot::fromArray' => $this->staticCalls(
                        $source,
                        'AuditOutboxStateSnapshot',
                        'fromArray',
                    ),
                    'AuditOutboxStateSnapshot::pending' => $this->staticCalls(
                        $source,
                        'AuditOutboxStateSnapshot',
                        'pending',
                    ),
                    'AuditOutboxRecord::fromParts' => $this->staticCalls($source, 'AuditOutboxRecord', 'fromParts'),
                    'protectOutbox' => $this->directPropertyMethodCalls($source, 'protection', 'protectOutbox'),
                    'unprotectOutbox' => $this->directPropertyMethodCalls($source, 'protection', 'unprotectOutbox'),
                    'finalizer->finalize' => $this->directPropertyMethodCalls($source, 'finalizer', 'finalize'),
                    default => $this->methodCalls($source, $call),
                };
                if ($count > 0) {
                    $actual[$path] = $count;
                }
            }
            ksort($actual);
            $normalized = $expectedFiles;
            ksort($normalized);
            self::assertSame($normalized, $actual, $call);
        }

        $store = $sources['Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php'];
        self::assertSame(2, $this->methodCalls($store, 'derive'));
        self::assertSame(1, $this->methodCalls($store, 'hydrate'));
        self::assertSame(1, $this->directPropertyMethodCalls($store, 'finalizer', 'finalize'));
        foreach (['claim', 'recover', 'retry', 'relay', 'poison'] as $method) {
            self::assertSame(1, $this->methodCalls($store, $method), $method);
        }
        self::assertSame(1, $this->staticCalls(
            $sources['Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php'],
            'AuditOutboxStateSnapshot',
            'fromArray',
        ));
        self::assertStringContainsString('new AuditOutboxTransition($configuration)', $store);
        self::assertStringContainsString(
            'AuditOutboxStateSnapshot::pending($assignedAt, $this->configuration)',
            $store,
        );

        $removed = str_replace('new AuditOutboxTransition(', 'new RemovedTransition(', $store, $replacementCount);
        self::assertSame(1, $replacementCount);
        self::assertSame(0, $this->constructorCalls($removed, 'AuditOutboxTransition'));
        $added = $store . "\nnew AuditOutboxTransition(\$configuration);\n";
        self::assertSame(2, $this->constructorCalls($added, 'AuditOutboxTransition'));
        $dynamic = $store . "\n\$this->{\$method}();\n";
        self::assertGreaterThan(0, $this->dynamicInvocationCount($dynamic));
        $aliasedProtection =
            $store . "\n\$protection = \$this->protection;\n\$protection->protectOutbox(\$envelope);\n";
        self::assertSame(1, $this->directPropertyMethodCalls($aliasedProtection, 'protection', 'protectOutbox'));
        self::assertSame(2, $this->methodCalls($aliasedProtection, 'protectOutbox'));
        $decoyCalls =
            $store
            . "\n// \$this->protection->protectOutbox(\$envelope);\n\$decoy = '\$this->protection->protectOutbox(';\n";
        self::assertSame(1, $this->directPropertyMethodCalls($decoyCalls, 'protection', 'protectOutbox'));
        self::assertSame(1, $this->constructorCalls(
            '<?php namespace BlackOps\\Internal\\Audit\\Other; use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxTransition as Transition; new Transition();',
            'AuditOutboxTransition',
        ));
        self::assertSame(1, $this->constructorCalls(
            '<?php namespace BlackOps\\Internal\\Audit\\Other; use BlackOps\\Internal\\Audit\\Outbox\\{AuditOutboxTransition}; new AuditOutboxTransition();',
            'AuditOutboxTransition',
        ));
        self::assertSame(1, $this->constructorCalls(
            '<?php new \\BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxTransition();',
            'AuditOutboxTransition',
        ));
        self::assertSame(0, $this->constructorCalls(
            "<?php // new AuditOutboxTransition();\n\$decoy = 'new AuditOutboxTransition(';",
            'AuditOutboxTransition',
        ));
        self::assertSame(0, $this->staticCalls(
            '<?php $class = AuditOutboxTransition::class;',
            'AuditOutboxTransition',
            'fromArray',
        ));
    }

    public function testImmutableSqlColumnsAndForbiddenClearFieldsAreClosed(): void
    {
        $root = $this->repositoryRoot();
        $store = file_get_contents($root . '/src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php');
        $schema = file_get_contents($root . '/src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php');
        self::assertIsString($store);
        self::assertIsString($schema);
        $withoutComments = $this->withoutComments($store);
        preg_match_all('/\\bSET\\s+(.*?)(?=\\bWHERE\\b)/is', $withoutComments, $matches);
        self::assertCount(2, $matches[1] ?? []);
        $setColumns = [];
        foreach ($matches[1] ?? [] as $setClause) {
            preg_match_all('/\\b([a-z_]+)\\s*=/i', $setClause, $columns);
            $setColumns[] = array_map('strtolower', $columns[1] ?? []);
        }
        self::assertSame(
            [
                ['last_ingress_ordinal', 'last_assigned_recorded_at', 'state_version'],
                ['state', 'attempt', 'available_at', 'lease_fencing_token', 'lease_expires_at', 'version'],
            ],
            $setColumns,
        );
        foreach ([
            'schema_version',
            'kind',
            'record_id',
            'partition_id',
            'tenant_type',
            'tenant_id',
            'ingress_ordinal',
            'idempotency_key_hash',
            'content_hash',
            'protected_body',
        ] as $immutable) {
            foreach ($matches[1] ?? [] as $setClause) {
                self::assertDoesNotMatchRegularExpression(
                    '/\\b' . preg_quote($immutable, '/') . '\\s*=/i',
                    $setClause,
                    $immutable,
                );
            }
        }
        foreach (['plaintext', 'clear_body', 'raw_key', 'ciphertext', 'nonce', 'payload'] as $forbidden) {
            self::assertDoesNotMatchRegularExpression(
                '/\\b' . preg_quote($forbidden, '/') . '\\b/i',
                $store,
                $forbidden,
            );
            self::assertDoesNotMatchRegularExpression(
                '/\\b' . preg_quote($forbidden, '/') . '\\b/i',
                $schema,
                $forbidden,
            );
        }
        self::assertStringNotContainsString('CURRENT_TIMESTAMP', $store);
        self::assertStringNotContainsString('SET TIME ZONE', strtoupper($store));
        $mutated = str_replace(
            'version = CAST(:next_version AS bigint)',
            'protected_body = :protected_body',
            $withoutComments,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        preg_match_all('/\\bSET\\s+(.*?)(?=\\bWHERE\\b)/is', $mutated, $mutatedMatches);
        $mutatedColumns = [];
        foreach ($mutatedMatches[1] ?? [] as $setClause) {
            preg_match_all('/\\b([a-z_]+)\\s*=/i', $setClause, $columns);
            $mutatedColumns[] = array_map('strtolower', $columns[1] ?? []);
        }
        self::assertNotSame($setColumns, $mutatedColumns);
    }

    private function repositoryRoot(): string
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertNotFalse($root);

        return $root;
    }

    /** @return array<string,list<class-string>> */
    private function expectedInventory(): array
    {
        return [
            'src/Internal/Audit/Outbox/AuditOutboxAppendResult.php' => [AuditOutboxAppendResult::class],
            'src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php' => [AuditOutboxIdempotencyKeyHasher::class],
            'src/Internal/Audit/Outbox/AuditOutboxLookupResult.php' => [AuditOutboxLookupResult::class],
            'src/Internal/Audit/Outbox/AuditOutboxRecord.php' => [AuditOutboxRecord::class],
            'src/Internal/Audit/Outbox/AuditOutboxStoreException.php' => [AuditOutboxStoreException::class],
            'src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php' => [AuditOutboxStoreFailure::class],
            'src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php' => [
                PostgreSqlAuditOutboxRecordHydrator::class,
            ],
            'src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php' => [PostgreSqlAuditOutboxSchema::class],
            'src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => [PostgreSqlAuditOutboxStore::class],
            'src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php' => [
                PostgreSqlAuditProtectedIngressHydrator::class,
            ],
        ];
    }

    /** @return array<string,list<string>> */
    private function inventory(string $root): array
    {
        $directory = $root . '/src/Internal/Audit/Outbox';
        $inventory = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $source = file_get_contents($path);
            self::assertIsString($source);
            $relative = substr($path, strlen($root) + 1);
            self::assertIsString($relative);
            $inventory[$relative] = $this->declaredTypes($source);
        }
        ksort($inventory);

        return $inventory;
    }

    /** @return list<string> */
    private function declaredTypes(string $source): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $types = [];
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = $this->namespaceName(array_slice($tokens, $index + 1));
                continue;
            }
            if (!is_array($token) || !in_array($token[0], [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT], true)) {
                continue;
            }
            $name = $this->nextName($tokens, $index + 1);
            if ($name !== null) {
                $types[] = $namespace . '\\' . $name;
            }
        }
        sort($types);

        return $types;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function namespaceName(array $tokens): string
    {
        $name = '';
        foreach ($tokens as $token) {
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
            }
        }

        return trim($name, '\\');
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function nextName(array $tokens, int $start): ?string
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
            if ($token === '{') {
                return null;
            }
        }

        return null;
    }

    private function assertConstructor(string $type, string $visibility, array $expected, bool $exact = true): void
    {
        $constructor = new ReflectionClass($type)->getConstructor();
        self::assertNotNull($constructor, $type);
        self::assertSame($visibility === 'public', $constructor->isPublic(), $type);
        self::assertSame($visibility === 'private', $constructor->isPrivate(), $type);
        $actual = [];
        foreach ($constructor->getParameters() as $parameter) {
            $actual[] = [
                $parameter->getName(),
                $this->typeName($parameter->getType()),
                $parameter->isVariadic(),
                $parameter->isDefaultValueAvailable(),
                $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                count($parameter->getAttributes(\SensitiveParameter::class)),
            ];
        }
        if ($exact) {
            self::assertSame($expected, $actual, $type);
        } else {
            self::assertSame(array_slice($expected, 0, 8), array_slice($actual, 0, 8), $type);
        }
    }

    private function assertMethod(
        string $type,
        string $name,
        string $visibility,
        string $return,
        bool $static,
        array $expected,
    ): void {
        $method = new ReflectionClass($type)->getMethod($name);
        self::assertSame($visibility === 'public', $method->isPublic(), $type . '::' . $name);
        self::assertSame($visibility === 'protected', $method->isProtected(), $type . '::' . $name);
        self::assertSame($static, $method->isStatic(), $type . '::' . $name);
        self::assertSame($return, $this->typeName($method->getReturnType()), $type . '::' . $name);
        $actual = [];
        foreach ($method->getParameters() as $parameter) {
            $actual[] = [
                $parameter->getName(),
                $this->typeName($parameter->getType()),
                count($parameter->getAttributes(\SensitiveParameter::class)),
                $parameter->isVariadic(),
                $parameter->isDefaultValueAvailable(),
                $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }
        self::assertSame($expected, $actual, $type . '::' . $name);
    }

    private function typeName(?ReflectionType $type): string
    {
        if (!$type instanceof ReflectionNamedType) {
            return $type === null ? '' : (string) $type;
        }

        return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
    }

    private function isDataBearingParameter(ReflectionParameter $parameter): bool
    {
        $function = $parameter->getDeclaringFunction();
        if (
            $function instanceof ReflectionMethod
            && $function->getDeclaringClass()->isEnum()
            && in_array($function->getName(), ['from', 'tryFrom'], true)
        ) {
            return false;
        }

        return $this->isDataBearingType($parameter->getType(), $parameter);
    }

    private function isDataBearingType(?ReflectionType $type, ReflectionParameter $parameter): bool
    {
        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $arm) {
                if ($this->isDataBearingType($arm, $parameter)) {
                    return true;
                }
            }

            return false;
        }
        if (!$type instanceof ReflectionNamedType) {
            return false;
        }
        $baseType = $type->getName();
        if ($baseType === 'bool') {
            $function = $parameter->getDeclaringFunction();
            $declaringClass = $function instanceof ReflectionMethod ? $function->getDeclaringClass()->getName() : '';

            return str_ends_with($declaringClass, 'Result');
        }
        if (in_array($baseType, ['array', 'int', 'mixed', 'string', 'Throwable'], true)) {
            return true;
        }

        return in_array(
            $baseType,
            [
                AuditRecordDraft::class,
                AuditTypedReference::class,
                AuditOutboxRecord::class,
                AuditOutboxStateSnapshot::class,
                AuditProtectedIngress::class,
                AuditIngressEnvelope::class,
                'BlackOps\\Internal\\Audit\\Record\\CanonicalAuditLogicalRecord',
                AuditRecordStamp::class,
                \DateTimeImmutable::class,
            ],
            true,
        );
    }

    private function hasSensitiveParameterViolation(\ReflectionFunctionAbstract $function): bool
    {
        foreach ($function->getParameters() as $parameter) {
            $expected = $this->isDataBearingParameter($parameter) ? 1 : 0;
            if ($expected !== count($parameter->getAttributes(\SensitiveParameter::class))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function dependencies(string $source): array
    {
        $parsed = $this->parsePhp($source);
        $namespace = $parsed['namespace'];
        $dependencies = [];
        foreach ($this->classReferences($parsed) as $reference) {
            $reference = trim($reference, '\\');
            if ($reference === '' || $reference === $namespace) {
                continue;
            }
            if (
                $namespace !== ''
                && str_starts_with($reference, $namespace . '\\')
                && in_array(substr($reference, strlen($namespace) + 1), ['self', 'parent', 'static'], true)
            ) {
                continue;
            }
            $dependencies[$reference] = true;
        }
        $result = array_keys($dependencies);
        sort($result);

        return $result;
    }

    /** @return array<string,string> */
    private function sourceFiles(string $root): array
    {
        $sources = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $relative = substr($file->getPathname(), strlen($root) + 1);
            self::assertIsString($relative);
            $sources[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = $source;
        }
        ksort($sources);

        return $sources;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function outsideReferences(array $sources): array
    {
        $paths = [];
        foreach ($sources as $path => $source) {
            if (str_contains($path, 'Internal/Audit/Outbox/')) {
                continue;
            }
            if ($this->referencesOutbox($source)) {
                $paths[] = $path;
            }
        }
        sort($paths);

        return $paths;
    }

    private function withoutComments(string $source): string
    {
        $result = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $result .= is_array($token) ? $token[1] : $token;
        }

        return $result;
    }

    private function constructorCalls(string $source, string $class): int
    {
        $parsed = $this->parsePhp($source);
        $count = 0;
        foreach ($parsed['tokens'] as $index => $token) {
            if (!is_array($token) || $token[0] !== T_NEW) {
                continue;
            }
            $nameIndex = $this->nextSignificantToken($parsed['tokens'], $index + 1);
            if ($nameIndex === null) {
                continue;
            }
            $name = $this->nameAt($parsed['tokens'], $nameIndex);
            if ($name !== null && $this->sameClass($name[0], $class, $parsed)) {
                $count++;
            }
        }

        return $count;
    }

    private function staticCalls(string $source, string $class, string $method): int
    {
        $parsed = $this->parsePhp($source);
        $count = 0;
        foreach ($parsed['tokens'] as $index => $token) {
            if (!$this->isNameToken($token)) {
                continue;
            }
            $name = $this->nameAt($parsed['tokens'], $index);
            if ($name === null || !$this->sameClass($name[0], $class, $parsed)) {
                continue;
            }
            $operator = $this->nextSignificantToken($parsed['tokens'], $name[1] + 1);
            if ($operator === null || $this->tokenText($parsed['tokens'][$operator]) !== '::') {
                continue;
            }
            $called = $this->nextSignificantToken($parsed['tokens'], $operator + 1);
            $opening = $called === null ? null : $this->nextSignificantToken($parsed['tokens'], $called + 1);
            if (
                $called !== null
                && is_array($parsed['tokens'][$called])
                && $parsed['tokens'][$called][0] === T_STRING
                && $parsed['tokens'][$called][1] === $method
                && $opening !== null
                && $parsed['tokens'][$opening] === '('
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function methodCalls(string $source, string $method): int
    {
        $parsed = $this->parsePhp($source);
        $count = 0;
        foreach ($parsed['tokens'] as $index => $token) {
            if ($this->tokenText($token) !== '->') {
                continue;
            }
            $called = $this->nextSignificantToken($parsed['tokens'], $index + 1);
            $opening = $called === null ? null : $this->nextSignificantToken($parsed['tokens'], $called + 1);
            if (
                $called !== null
                && is_array($parsed['tokens'][$called])
                && $parsed['tokens'][$called][0] === T_STRING
                && $parsed['tokens'][$called][1] === $method
                && $opening !== null
                && $parsed['tokens'][$opening] === '('
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function propertyMethodCalls(string $source, string $property, string $method): int
    {
        $parsed = $this->parsePhp($source);
        $count = 0;
        foreach ($parsed['tokens'] as $index => $token) {
            if ($this->tokenText($token) !== '->') {
                continue;
            }
            $propertyIndex = $this->nextSignificantToken($parsed['tokens'], $index + 1);
            $methodOperator = $propertyIndex === null
                ? null
                : $this->nextSignificantToken($parsed['tokens'], $propertyIndex + 1);
            $methodIndex = $methodOperator === null
                ? null
                : $this->nextSignificantToken($parsed['tokens'], $methodOperator + 1);
            $opening = $methodIndex === null ? null : $this->nextSignificantToken($parsed['tokens'], $methodIndex + 1);
            if (
                $propertyIndex !== null
                && is_array($parsed['tokens'][$propertyIndex])
                && $parsed['tokens'][$propertyIndex][0] === T_STRING
                && $parsed['tokens'][$propertyIndex][1] === $property
                && $methodOperator !== null
                && $this->tokenText($parsed['tokens'][$methodOperator]) === '->'
                && $methodIndex !== null
                && is_array($parsed['tokens'][$methodIndex])
                && $parsed['tokens'][$methodIndex][0] === T_STRING
                && $parsed['tokens'][$methodIndex][1] === $method
                && $opening !== null
                && $parsed['tokens'][$opening] === '('
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function directPropertyMethodCalls(string $source, string $property, string $method): int
    {
        $parsed = $this->parsePhp($source);
        $count = 0;
        foreach ($parsed['tokens'] as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== '$this') {
                continue;
            }
            $propertyOperator = $this->nextSignificantToken($parsed['tokens'], $index + 1);
            $propertyIndex = $propertyOperator === null
                ? null
                : $this->nextSignificantToken($parsed['tokens'], $propertyOperator + 1);
            $methodOperator = $propertyIndex === null
                ? null
                : $this->nextSignificantToken($parsed['tokens'], $propertyIndex + 1);
            $methodIndex = $methodOperator === null
                ? null
                : $this->nextSignificantToken($parsed['tokens'], $methodOperator + 1);
            $opening = $methodIndex === null ? null : $this->nextSignificantToken($parsed['tokens'], $methodIndex + 1);
            if (
                $propertyOperator !== null
                && $this->tokenText($parsed['tokens'][$propertyOperator]) === '->'
                && $propertyIndex !== null
                && is_array($parsed['tokens'][$propertyIndex])
                && $parsed['tokens'][$propertyIndex][0] === T_STRING
                && $parsed['tokens'][$propertyIndex][1] === $property
                && $methodOperator !== null
                && $this->tokenText($parsed['tokens'][$methodOperator]) === '->'
                && $methodIndex !== null
                && is_array($parsed['tokens'][$methodIndex])
                && $parsed['tokens'][$methodIndex][0] === T_STRING
                && $parsed['tokens'][$methodIndex][1] === $method
                && $opening !== null
                && $parsed['tokens'][$opening] === '('
            ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{
     *     tokens:list<array{0:int,1:string,2:int}|string>,
     *     namespace:string,
     *     imports:array<string,string>,
     * }
     */
    private function parsePhp(string $source): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $imports = [];
        $braceDepth = 0;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $name = '';
                for ($nameIndex = $index + 1, $count = count($tokens); $nameIndex < $count; $nameIndex++) {
                    $nameToken = $tokens[$nameIndex];
                    if ($nameToken === ';' || $nameToken === '{') {
                        break;
                    }
                    if (
                        is_array($nameToken)
                        && in_array(
                            $nameToken[0],
                            [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR],
                            true,
                        )
                    ) {
                        $name .= $nameToken[1];
                    } elseif (is_string($nameToken) && $nameToken === '\\') {
                        $name .= $nameToken;
                    }
                }
                $namespace = trim($name, '\\');
                continue;
            }
            if (is_string($token)) {
                if ($token === '{') {
                    $braceDepth++;
                } elseif ($token === '}') {
                    $braceDepth = max(0, $braceDepth - 1);
                }
                continue;
            }
            if ($token[0] !== T_USE || $braceDepth !== 0) {
                continue;
            }
            $clause = '';
            $end = $index + 1;
            for ($count = count($tokens); $end < $count; $end++) {
                $statementToken = $tokens[$end];
                if ($statementToken === ';') {
                    break;
                }
                if (is_array($statementToken) && in_array($statementToken[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $clause .= is_array($statementToken) ? $statementToken[1] : $statementToken;
            }
            foreach ($this->parseUseClause($clause) as $alias => $fullName) {
                $imports[$alias] = $fullName;
            }
        }

        return ['tokens' => $tokens, 'namespace' => $namespace, 'imports' => $imports];
    }

    /** @return array<string,string> */
    private function parseUseClause(string $clause): array
    {
        $clause = trim((string) preg_replace('/\s+/', ' ', $clause));
        if ($clause === '' || preg_match('/^(?:function|const)\b/i', $clause) === 1) {
            return [];
        }
        $imports = [];
        if (str_contains($clause, '{')) {
            $open = strpos($clause, '{');
            $close = strrpos($clause, '}');
            if ($open === false || $close === false || $close < $open) {
                return [];
            }
            $prefix = rtrim(trim(substr($clause, 0, $open)), '\\');
            $members = substr($clause, $open + 1, $close - $open - 1);
            foreach (explode(',', $members) as $member) {
                $this->addUseImport($imports, $prefix . '\\' . trim($member));
            }

            return $imports;
        }
        foreach (explode(',', $clause) as $member) {
            $this->addUseImport($imports, trim($member));
        }

        return $imports;
    }

    /** @param array<string,string> $imports */
    private function addUseImport(array &$imports, string $member): void
    {
        $member = trim($member);
        if ($member === '') {
            return;
        }
        $parts = preg_split('/\s+as\s+/i', $member, 2);
        $fullName = trim($parts[0] ?? '', " \t\n\r\0\x0B\\");
        if ($fullName === '') {
            return;
        }
        $alias = trim($parts[1] ?? basename(str_replace('\\', '/', $fullName)));
        if ($alias !== '') {
            $imports[strtolower($alias)] = $fullName;
        }
    }

    /** @param array{tokens:list<array{0:int,1:string,2:int}|string>,namespace:string,imports:array<string,string>} $parsed */
    private function sameClass(string $name, string $expected, array $parsed): bool
    {
        $resolved = $this->resolveClassName($name, $parsed);
        $target = trim($expected, '\\');
        if (str_contains($target, '\\')) {
            return $resolved === $target;
        }

        return $resolved === $target || str_ends_with($resolved, '\\' . $target);
    }

    /** @param array{tokens:list<array{0:int,1:string,2:int}|string>,namespace:string,imports:array<string,string>} $parsed */
    private function resolveClassName(string $name, array $parsed): string
    {
        $absolute = str_starts_with($name, '\\');
        $name = trim($name, '\\');
        if (str_starts_with(strtolower($name), 'namespace\\')) {
            $name = substr($name, strlen('namespace\\'));
            $absolute = false;
        }
        $topLevel = strtolower(explode('\\', $name, 2)[0]);
        if (!$absolute && in_array($topLevel, ['blackops', 'doctrine', 'psr'], true)) {
            $absolute = true;
        }
        $parts = explode('\\', $name, 2);
        $alias = strtolower($parts[0] ?? '');
        if (!$absolute && isset($parsed['imports'][$alias])) {
            return $parsed['imports'][$alias] . (isset($parts[1]) ? '\\' . $parts[1] : '');
        }
        if ($absolute || $parsed['namespace'] === '') {
            return $name;
        }

        return $parsed['namespace'] . '\\' . $name;
    }

    /** @param array{tokens:list<array{0:int,1:string,2:int}|string>,namespace:string,imports:array<string,string>} $parsed */
    private function referencesOutbox(string $source): bool
    {
        $parsed = $this->parsePhp($source);
        foreach ($parsed['imports'] as $import) {
            if ($this->isOutboxName($import)) {
                return true;
            }
        }
        foreach ($this->classReferences($parsed) as $reference) {
            if ($this->isOutboxName($reference)) {
                return true;
            }
        }

        return false;
    }

    /** @param array{tokens:list<array{0:int,1:string,2:int}|string>,namespace:string,imports:array<string,string>} $parsed @return list<string> */
    private function classReferences(array $parsed): array
    {
        $references = array_values($parsed['imports']);
        $tokens = $parsed['tokens'];
        $classStringVariables = [];
        $classStringAliases = [];
        $importNames = array_map(static fn(string $name): string => trim($name, '\\'), $parsed['imports']);
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_VARIABLE) {
                $assignment = $this->nextSignificantToken($tokens, $index + 1);
                if ($assignment === null || $this->tokenText($tokens[$assignment]) !== '=') {
                    continue;
                }
                $value = $this->nextSignificantToken($tokens, $assignment + 1);
                if ($value === null || !is_array($tokens[$value])) {
                    continue;
                }
                if ($tokens[$value][0] === T_CONSTANT_ENCAPSED_STRING) {
                    $literal = substr($tokens[$value][1], 1, -1);
                    $classStringVariables[$token[1]] = str_replace('\\\\', '\\', $literal);
                } elseif ($tokens[$value][0] === T_VARIABLE) {
                    $classStringAliases[$token[1]] = $tokens[$value][1];
                }
            }
        }
        do {
            $changed = false;
            foreach ($classStringAliases as $alias => $source) {
                if (!isset($classStringVariables[$source]) || isset($classStringVariables[$alias])) {
                    continue;
                }
                $classStringVariables[$alias] = $classStringVariables[$source];
                $changed = true;
            }
        } while ($changed);

        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $literal = substr($token[1], 1, -1);
                $literal = str_replace('\\\\', '\\', $literal);
                $previous = $this->previousSignificantToken($tokens, $index - 1);
                if ($previous !== null && $tokens[$previous] === '(') {
                    $call = $this->previousSignificantToken($tokens, $previous - 1);
                    $callName = $call === null ? null : ltrim($this->tokenText($tokens[$call]), '\\');
                    if ($this->isClassStringConsumer($callName)) {
                        $references[] = trim($literal, '\\');
                    }
                }
                continue;
            }
            if (is_array($token) && $token[0] === T_VARIABLE && isset($classStringVariables[$token[1]])) {
                $previous = $this->previousSignificantToken($tokens, $index - 1);
                if ($previous !== null && $tokens[$previous] === '(') {
                    $call = $this->previousSignificantToken($tokens, $previous - 1);
                    $callName = $call === null ? null : ltrim($this->tokenText($tokens[$call]), '\\');
                    if ($this->isClassStringConsumer($callName)) {
                        $references[] = trim($classStringVariables[$token[1]], '\\');
                    }
                }
                continue;
            }
            if (!$this->isNameToken($token)) {
                continue;
            }
            $name = $this->nameAt($tokens, $index);
            if ($name === null) {
                continue;
            }
            $raw = $name[0];
            $resolved = in_array(trim($raw, '\\'), $importNames, true)
                ? trim($raw, '\\')
                : $this->resolveClassName($raw, $parsed);
            $next = $this->nextSignificantToken($tokens, $name[1] + 1);
            $previous = $this->previousSignificantToken($tokens, $index - 1);
            if (isset($parsed['imports'][strtolower($raw)])) {
                $references[] = $resolved;
            }
            if (
                str_contains($raw, '\\')
                || str_starts_with($raw, '\\')
                || $next !== null && $this->tokenText($tokens[$next]) === '::'
                || $this->isTypeReference($tokens, $index, $name[1])
                || $previous !== null
                && is_array($tokens[$previous])
                && in_array($tokens[$previous][0], [T_NEW, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_CATCH], true)
            ) {
                if (!$this->isTypeReference($tokens, $index, $name[1]) || !$this->isBuiltinTypeName($raw)) {
                    $references[] = $resolved;
                }
            }
        }
        $references = array_values(array_unique(array_map(static fn(string $value): string => trim(
            $value,
            '\\',
        ), $references)));

        return $references;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function isTypeReference(array $tokens, int $start, int $end): bool
    {
        $next = $this->nextSignificantToken($tokens, $end + 1);
        if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
            return true;
        }
        if ($next !== null && in_array($this->tokenText($tokens[$next]), ['|', '&'], true)) {
            return true;
        }
        $previous = $this->previousSignificantToken($tokens, $start - 1);
        if ($previous !== null && in_array($this->tokenText($tokens[$previous]), ['?', '|', '&'], true)) {
            return true;
        }
        if ($previous !== null && $this->tokenText($tokens[$previous]) === ':') {
            $beforeColon = $this->previousSignificantToken($tokens, $previous - 1);

            return $beforeColon !== null && $tokens[$beforeColon] === ')';
        }

        return false;
    }

    private function isBuiltinTypeName(string $name): bool
    {
        return in_array(
            strtolower(trim($name, '\\')),
            [
                'array',
                'bool',
                'callable',
                'false',
                'float',
                'int',
                'iterable',
                'mixed',
                'never',
                'null',
                'object',
                'self',
                'static',
                'string',
                'true',
                'void',
            ],
            true,
        );
    }

    private function isClassStringConsumer(?string $name): bool
    {
        return in_array(
            $name,
            [
                'class_exists',
                'enum_exists',
                'interface_exists',
                'is_a',
                'is_subclass_of',
                'ReflectionClass',
            ],
            true,
        );
    }

    private function isOutboxName(string $name): bool
    {
        return str_starts_with(trim($name, '\\'), 'BlackOps\\Internal\\Audit\\Outbox\\');
    }

    /** @param array{0:int,1:string,2:int}|string|null $token */
    private function isNameToken(array|string|null $token): bool
    {
        return (
            is_array($token)
            && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
        );
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array{0:string,1:int}|null */
    private function nameAt(array $tokens, int $start): ?array
    {
        if (!$this->isNameToken($tokens[$start] ?? null)) {
            return null;
        }
        $name = $this->tokenText($tokens[$start]);
        $end = $start;
        while (true) {
            $separator = $this->nextSignificantToken($tokens, $end + 1);
            if ($separator === null || $this->tokenText($tokens[$separator]) !== '\\') {
                break;
            }
            $part = $this->nextSignificantToken($tokens, $separator + 1);
            if ($part === null || !$this->isNameToken($tokens[$part])) {
                break;
            }
            $name .= '\\' . $this->tokenText($tokens[$part]);
            $end = $part;
        }

        return [$name, $end];
    }

    /** @param array{0:int,1:string,2:int}|string $token */
    private function tokenText(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    private function dynamicInvocationCount(string $source): int
    {
        $tokens = token_get_all($source);
        $count = 0;
        foreach ($tokens as $index => $token) {
            $operator = is_array($token) ? $token[1] : $token;
            if ($operator !== '->' && $operator !== '::') {
                continue;
            }
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null) {
                continue;
            }
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE || $tokens[$next] === '{') {
                $count++;
            }
        }
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_NEW) {
                continue;
            }
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function nextSignificantToken(array $tokens, int $start): ?int
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function previousSignificantToken(array $tokens, int $start): ?int
    {
        for ($index = $start; $index >= 0; $index--) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function heredocTokens(string $source): int
    {
        $count = 0;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_START_HEREDOC) {
                $count++;
            }
        }

        return $count;
    }

    private function traitTokens(string $source): int
    {
        $count = 0;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_TRAIT) {
                $count++;
            }
        }
        $withoutImports = preg_replace('/^use\\s+[^;]+;\\s*/m', '', $this->withoutComments($source));
        if (
            is_string($withoutImports)
            && preg_match('/\\buse\\s+[A-Z_a-z][A-Za-z0-9_\\\\]*(?:\\s*,|\\s*;)/', $withoutImports) === 1
        ) {
            $count++;
        }

        return $count;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
