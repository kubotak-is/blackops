<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
use BlackOps\Internal\Audit\Read\AuditReadEventWriter;
use BlackOps\Internal\Audit\Read\OperationReadAuditSession;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Internal\Audit\Read\OperationReadExecutionSource;
use BlackOps\Internal\Audit\Read\OperationReadNonceSource;
use BlackOps\Internal\Audit\Read\RandomOperationReadNonceSource;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizationRequest;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizer;
use BlackOps\Internal\Diagnostics\UnavailableOperationDiagnosticsAuthorizer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

final class AuditOperationReadArchitectureTest extends TestCase
{
    public function testExactD2DeclarationsPropertiesAndCallableInventories(): void
    {
        self::assertTrue(new ReflectionClass(AuditReadEventWriter::class)->isInterface());
        self::assertTrue(new ReflectionClass(OperationReadNonceSource::class)->isInterface());
        self::assertTrue(new ReflectionClass(OperationReadExecutionSource::class)->isInterface());
        self::assertTrue(new ReflectionClass(OperationDiagnosticsAuthorizer::class)->isInterface());
        self::assertTrue(new ReflectionClass(AuditProtectedReadGate::class)->implementsInterface(AuditReadEventWriter::class));
        self::assertFalse(
            new ReflectionClass(OperationReadNonceSource::class)->isInterface()
            && new ReflectionClass(RandomOperationReadNonceSource::class)->isInterface(),
        );

        foreach ([
            AuditReadEventWriter::class => ['requested', 'terminal'],
            OperationReadNonceSource::class => ['generate'],
            OperationReadExecutionSource::class => ['current'],
            OperationDiagnosticsAuthorizer::class => ['decide'],
            OperationReadAuditSessionFactory::class => [
                '__construct',
                'begin',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'value',
                'reference',
                'execution',
                'resourceId',
                'requestedAt',
                'nonce',
                'definition',
                'draft',
                'component',
            ],
            OperationReadAuditSession::class => [
                'open',
                'accessed',
                'unavailable',
                '__clone',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                '__construct',
                'terminal',
                'value',
            ],
            OperationDiagnosticsAuthorizationRequest::class => [
                '__construct',
                'operationId',
                'currentActor',
                'currentTenant',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'value',
            ],
            UnavailableOperationDiagnosticsAuthorizer::class => ['decide'],
        ] as $class => $expected) {
            $reflection = new ReflectionClass($class);
            $actual = [];
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() === $class) {
                    $actual[] = $method->getName();
                }
            }
            sort($actual);
            sort($expected);
            self::assertSame($expected, $actual, $class);
        }

        self::assertSame(['snapshot'], $this->propertyNames(OperationReadAuditSessionFactory::class));
        self::assertSame(['snapshot', 'terminalAttempted'], $this->propertyNames(OperationReadAuditSession::class));
        self::assertSame(['snapshot'], $this->propertyNames(OperationDiagnosticsAuthorizationRequest::class));
        self::assertSame([], $this->propertyNames(OperationReadNonceSource::class));
        self::assertSame([], $this->propertyNames(OperationReadExecutionSource::class));
        self::assertSame([], $this->propertyNames(OperationDiagnosticsAuthorizer::class));
        self::assertSame([], $this->propertyNames(RandomOperationReadNonceSource::class));
        self::assertSame([], $this->propertyNames(UnavailableOperationDiagnosticsAuthorizer::class));

        $factory = new ReflectionClass(OperationReadAuditSessionFactory::class);
        self::assertFalse($factory->isReadOnly());
        self::assertTrue($factory->isFinal());
        self::assertSame('SensitiveParameterValue', $factory->getProperty('snapshot')->getType()?->getName());
        self::assertTrue($factory->getProperty('snapshot')->isPrivate());
        self::assertFalse($factory->getProperty('snapshot')->isReadOnly());

        $session = new ReflectionClass(OperationReadAuditSession::class);
        self::assertTrue($session->isFinal());
        self::assertFalse($session->isReadOnly());
        self::assertSame('SensitiveParameterValue', $session->getProperty('snapshot')->getType()?->getName());
        self::assertSame('bool', $session->getProperty('terminalAttempted')->getType()?->getName());
        self::assertFalse($session->getProperty('terminalAttempted')->getDefaultValue());

        $request = new ReflectionClass(OperationDiagnosticsAuthorizationRequest::class);
        self::assertTrue($request->isFinal());
        self::assertSame('SensitiveParameterValue', $request->getProperty('snapshot')->getType()?->getName());
        self::assertTrue(new ReflectionClass(RandomOperationReadNonceSource::class)->isFinal());
        self::assertTrue(new ReflectionClass(RandomOperationReadNonceSource::class)->isReadOnly());
        self::assertTrue(new ReflectionClass(UnavailableOperationDiagnosticsAuthorizer::class)->isFinal());
        self::assertTrue(new ReflectionClass(UnavailableOperationDiagnosticsAuthorizer::class)->isReadOnly());

        $this->assertPropertyContract(
            OperationReadAuditSessionFactory::class,
            'snapshot',
            'SensitiveParameterValue',
            true,
            false,
            false,
            false,
            null,
        );
        $this->assertPropertyContract(
            OperationReadAuditSession::class,
            'snapshot',
            'SensitiveParameterValue',
            true,
            false,
            false,
            false,
            null,
        );
        $this->assertPropertyContract(
            OperationReadAuditSession::class,
            'terminalAttempted',
            'bool',
            true,
            false,
            false,
            false,
            false,
        );
        $this->assertPropertyContract(
            OperationDiagnosticsAuthorizationRequest::class,
            'snapshot',
            'SensitiveParameterValue',
            true,
            false,
            false,
            false,
            null,
        );
        $this->assertPropertyContract(
            AuditProtectedReadGate::class,
            'delivery',
            AuditDeliveryCoordinator::class,
            true,
            true,
            false,
            true,
            null,
        );
    }

    public function testExactSignaturesAndSensitiveParameterMatrix(): void
    {
        $this->assertMethod(
            AuditReadEventWriter::class,
            'requested',
            [
                ['basis', AuditReadRequestBasis::class, false, true],
                ['draft', AuditRecordDraft::class,      false, true],
            ],
            'void',
        );
        $this->assertMethod(
            AuditReadEventWriter::class,
            'terminal',
            [
                ['context', AuditReadContext::class, false, true],
                ['draft',   AuditRecordDraft::class, false, true],
            ],
            AuditReadAuthorizationDecision::class,
        );
        $this->assertMethod(OperationReadNonceSource::class, 'generate', [], 'string');
        $this->assertMethod(OperationReadExecutionSource::class, 'current', [], AuditTypedReference::class);
        $this->assertMethod(
            OperationDiagnosticsAuthorizer::class,
            'decide',
            [
                ['request', OperationDiagnosticsAuthorizationRequest::class, false, true],
            ],
            AuditReadAuthorizationDecision::class,
        );
        $this->assertMethod(
            UnavailableOperationDiagnosticsAuthorizer::class,
            'decide',
            [['request', OperationDiagnosticsAuthorizationRequest::class, false, true]],
            AuditReadAuthorizationDecision::class,
        );
        $this->assertMethod(RandomOperationReadNonceSource::class, 'generate', [], 'string');

        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            '__construct',
            [
                ['writer',     AuditReadEventWriter::class,         false, true],
                ['opaqueIds',  HmacAuditOpaqueIdDeriver::class,     false, true],
                ['nonces',     OperationReadNonceSource::class,     false, true],
                ['clock',      'Psr\\Clock\\ClockInterface',        false, true],
                ['executions', OperationReadExecutionSource::class, false, true],
                ['runtime',    AuditSourceRuntime::class,           false, false],
                ['buildId',    'string',                            false, true],
            ],
            null,
        );
        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            'begin',
            [
                ['purpose',       'BlackOps\\Core\\Audit\\AuditReadPurpose', false, false],
                ['operationId',   OperationId::class,                        false, true],
                ['currentActor',  ActorRef::class,                           true,  true],
                ['currentTenant', TenantRef::class,                          true,  true],
            ],
            OperationReadAuditSession::class,
        );
        $this->assertMethod(OperationReadAuditSessionFactory::class, '__debugInfo', [], 'array');
        $this->assertMethod(OperationReadAuditSessionFactory::class, '__serialize', [], 'array');
        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            '__unserialize',
            [['data', 'array', false, true]],
            'void',
        );

        $this->assertMethod(
            OperationReadAuditSession::class,
            'open',
            [
                ['writer',      AuditReadEventWriter::class,  false, true],
                ['basis',       AuditReadRequestBasis::class, false, true],
                ['requested',   AuditRecordDraft::class,      false, true],
                ['accessed',    AuditRecordDraft::class,      false, true],
                ['unavailable', AuditRecordDraft::class,      false, true],
            ],
            OperationReadAuditSession::class,
        );
        $this->assertMethod(OperationReadAuditSession::class, 'accessed', [], 'void');
        $this->assertMethod(OperationReadAuditSession::class, 'unavailable', [], 'void');
        $this->assertMethod(OperationReadAuditSession::class, '__clone', [], 'void');
        $this->assertMethod(OperationReadAuditSession::class, '__debugInfo', [], 'array');
        $this->assertMethod(OperationReadAuditSession::class, '__serialize', [], 'array');
        $this->assertMethod(
            OperationReadAuditSession::class,
            '__unserialize',
            [['data', 'array', false, true]],
            'void',
        );

        $this->assertMethod(
            OperationDiagnosticsAuthorizationRequest::class,
            '__construct',
            [
                ['operationId',   OperationId::class, false, true],
                ['currentActor',  ActorRef::class,    true,  true],
                ['currentTenant', TenantRef::class,   true,  true],
            ],
            null,
        );
        $this->assertMethod(OperationDiagnosticsAuthorizationRequest::class, 'operationId', [], OperationId::class);
        $this->assertMethod(OperationDiagnosticsAuthorizationRequest::class, 'currentActor', [], ActorRef::class);
        $this->assertMethod(OperationDiagnosticsAuthorizationRequest::class, 'currentTenant', [], TenantRef::class);
        self::assertTrue(
            new ReflectionClass(OperationDiagnosticsAuthorizationRequest::class)
                ->getMethod('currentActor')
                ->getReturnType()
                ?->allowsNull(),
        );
        self::assertTrue(
            new ReflectionClass(OperationDiagnosticsAuthorizationRequest::class)
                ->getMethod('currentTenant')
                ->getReturnType()
                ?->allowsNull(),
        );
        $this->assertMethod(OperationDiagnosticsAuthorizationRequest::class, '__debugInfo', [], 'array');
        $this->assertMethod(OperationDiagnosticsAuthorizationRequest::class, '__serialize', [], 'array');
        $this->assertMethod(
            OperationDiagnosticsAuthorizationRequest::class,
            '__unserialize',
            [['data', 'array', false, true]],
            'void',
        );

        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            'value',
            [['key', 'string', false, true]],
            'mixed',
        );
        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            'reference',
            [['reference', ActorRef::class . '|' . TenantRef::class, true, true]],
            AuditTypedReference::class,
        );
        $this->assertMethod(OperationReadAuditSessionFactory::class, 'execution', [], AuditTypedReference::class);
        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            'resourceId',
            [['operationId', OperationId::class, false, true]],
            'string',
        );
        $this->assertMethod(OperationReadAuditSessionFactory::class, 'requestedAt', [], 'string');
        $this->assertMethod(OperationReadAuditSessionFactory::class, 'nonce', [], 'string');
        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            'definition',
            [
                ['catalog', AuditEventCatalog::class, false, false],
                ['purpose', AuditReadPurpose::class,  false, false],
                ['phase',   'string',                 false, false],
            ],
            AuditEventDefinition::class,
        );
        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            'draft',
            [
                ['catalog',    AuditEventCatalog::class,     false, false],
                ['definition', AuditEventDefinition::class,  false, false],
                ['basis',      AuditReadRequestBasis::class, false, true],
                ['resourceId', 'string',                     false, true],
            ],
            AuditRecordDraft::class,
        );
        $this->assertMethod(
            OperationReadAuditSessionFactory::class,
            'component',
            [['purpose', AuditReadPurpose::class, false, false]],
            'string',
        );
        $this->assertMethod(
            OperationReadAuditSession::class,
            '__construct',
            [
                ['writer',      AuditReadEventWriter::class,  false, true],
                ['basis',       AuditReadRequestBasis::class, false, true],
                ['accessed',    AuditRecordDraft::class,      false, true],
                ['unavailable', AuditRecordDraft::class,      false, true],
            ],
            null,
        );
        $this->assertMethod(
            OperationReadAuditSession::class,
            'terminal',
            [
                ['decision', AuditReadAuthorizationDecision::class, false, false],
                ['draftKey', 'string',                              false, true],
            ],
            'void',
        );
        $this->assertMethod(OperationReadAuditSession::class, 'value', [['key', 'string', false, true]], 'mixed');
        $this->assertMethod(
            OperationDiagnosticsAuthorizationRequest::class,
            'value',
            [['key', 'string', false, true]],
            'mixed',
        );
        $this->assertMethod(
            AuditProtectedReadGate::class,
            'assertBinding',
            [
                ['basis', AuditReadRequestBasis::class, false, true],
                ['draft', AuditRecordDraft::class,      false, true],
                ['phase', 'string',                     false, false],
            ],
            'void',
        );
    }

    public function testSourceDependencyAndCallGraphBoundariesAreExact(): void
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $expectedPaths = [
            'Internal/Audit/Read/AuditReadEventWriter.php',
            'Internal/Audit/Read/OperationReadNonceSource.php',
            'Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'Internal/Audit/Read/OperationReadExecutionSource.php',
            'Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'Internal/Audit/Read/OperationReadAuditSession.php',
            'Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php',
            'Internal/Diagnostics/OperationDiagnosticsAuthorizer.php',
            'Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php',
        ];
        $actualPaths = [];
        foreach ($expectedPaths as $relative) {
            $path = $root . '/' . $relative;
            self::assertFileExists($path);
            $actualPaths[] = $relative;
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString('Reflection', $source, $relative);
            self::assertStringNotContainsString('call_user_func', $source, $relative);
            self::assertStringNotContainsString('new $', $source, $relative);
            self::assertStringNotContainsString('OperationReadAuditSessionFactory::', $source, $relative);
        }
        sort($actualPaths);
        $sortedExpected = $expectedPaths;
        sort($sortedExpected);
        self::assertSame($sortedExpected, $actualPaths);

        $allSources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative = str_replace($root . '/', '', $file->getPathname());
                $source = file_get_contents($file->getPathname());
                self::assertIsString($source);
                $allSources[$relative] = $source;
            }
        }
        $factoryOpen = 0;
        foreach ($allSources as $relative => $source) {
            $factoryOpen += preg_match_all('/OperationReadAuditSession::open\s*\(/', $source) ?: 0;
            if ($relative !== 'Internal/Audit/Read/OperationReadAuditSessionFactory.php') {
                self::assertStringNotContainsString('new OperationReadAuditSessionFactory(', $source, $relative);
                self::assertStringNotContainsString('OperationReadAuditSession::open(', $source, $relative);
            }
        }
        self::assertSame(1, $factoryOpen);
        self::assertSame(1, substr_count(
            $allSources['Internal/Audit/Read/RandomOperationReadNonceSource.php'],
            'random_bytes(16)',
        ));
        self::assertSame(1, substr_count(
            $allSources['Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
            'FrameworkAuditEventCatalog::catalog()',
        ));
        self::assertSame(1, substr_count(
            $allSources['Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
            '->derive(',
        ));
        self::assertSame(1, substr_count(
            $allSources['Internal/Audit/Read/OperationReadAuditSession.php'],
            '->requested(',
        ));
        self::assertSame(2, substr_count(
            $allSources['Internal/Audit/Orchestration/AuditProtectedReadGate.php'],
            '->deliverStandalone(',
        ));
        self::assertSame(
            [],
            array_diff(
                [
                    'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
                    'src/Internal/Audit/Read/OperationReadAuditSession.php',
                    'src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
                ],
                array_map(static fn(string $path): string => 'src/' . $path, $actualPaths),
            ),
        );

        $factorySource = $allSources['Internal/Audit/Read/OperationReadAuditSessionFactory.php'];
        $newSelf = strpos($allSources['Internal/Audit/Read/OperationReadAuditSession.php'], '$session = new self(');
        $requestedCall = strpos(
            $allSources['Internal/Audit/Read/OperationReadAuditSession.php'],
            '$writer->requested(',
        );
        $returnSession = strpos($allSources['Internal/Audit/Read/OperationReadAuditSession.php'], 'return $session;');
        self::assertNotFalse($newSelf);
        self::assertNotFalse($requestedCall);
        self::assertNotFalse($returnSession);
        self::assertTrue($newSelf < $requestedCall && $requestedCall < $returnSession);
        self::assertStringNotContainsString('beginTransaction', $factorySource);
        self::assertStringNotContainsString('OperationDiagnosticsQuery', $factorySource);
        self::assertStringNotContainsString('ApplicationAuditRuntimeComposer', $factorySource);
    }

    public function testOnlyGateImplementsWriterAndDefaultFacadesRemainUnactivated(): void
    {
        self::assertTrue(new ReflectionClass(AuditProtectedReadGate::class)->implementsInterface(AuditReadEventWriter::class));
        foreach ([
            OperationReadAuditSessionFactory::class,
            OperationReadAuditSession::class,
            OperationReadNonceSource::class,
            RandomOperationReadNonceSource::class,
            OperationReadExecutionSource::class,
            OperationDiagnosticsAuthorizationRequest::class,
            OperationDiagnosticsAuthorizer::class,
            UnavailableOperationDiagnosticsAuthorizer::class,
        ] as $class) {
            self::assertFalse(new ReflectionClass($class)->implementsInterface(AuditReadEventWriter::class), $class);
        }
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertIsString($sourceRoot);
        $factoryCallers = 0;
        $readerActivations = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $factoryCallers += substr_count($source, 'new OperationReadAuditSessionFactory(');
            $readerActivations += substr_count($source, 'new OperationReadExecutionSource(');
            self::assertStringNotContainsString('ApplicationAuditRuntime::fromComposition($factory', $source);
        }
        self::assertSame(0, $factoryCallers);
        self::assertSame(0, $readerActivations);
    }

    public function testTokenAwareDependencyAndCallGraphOracleIsExact(): void
    {
        $sources = $this->d2Sources();
        $allSources = $this->d2AllProductionSources();
        self::assertSame([], $this->d2ArchitectureViolations($sources, $allSources));

        $operationReadSession = 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession';
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_udiff_uassoc([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_udiff_uassoc([], [], 'strcmp', 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
            PHP, [$operationReadSession]));
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_uintersect_uassoc([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_uintersect_uassoc([], [], 'strcmp', 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_udiff_uassoc('BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', [], 'strcmp', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_uintersect_uassoc([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
                    array_udiff([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
                    array_udiff_assoc([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
                    array_uintersect([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
                    array_uintersect_assoc([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_diff_uassoc([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
            PHP, [$operationReadSession]));
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_diff_ukey([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
            PHP, [$operationReadSession]));
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_intersect_uassoc([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
            PHP, [$operationReadSession]));
        self::assertSame([$operationReadSession], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_intersect_ukey([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_diff_uassoc([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_diff_ukey([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_intersect_uassoc([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([], $this->d2ConsumedTargetReferences(<<<'PHP'
            <?php
            array_intersect_ukey([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open', 'strcmp');
            PHP, [$operationReadSession]));
        self::assertSame([2, 3], $this->d2ConsumedArgumentPositions('array_udiff_uassoc', 4));
        self::assertSame([2, 3], $this->d2ConsumedArgumentPositions('array_uintersect_uassoc', 4));
        self::assertSame([2], $this->d2ConsumedArgumentPositions('array_diff_uassoc', 3));
        self::assertSame([2], $this->d2ConsumedArgumentPositions('array_diff_ukey', 3));
        self::assertSame([2], $this->d2ConsumedArgumentPositions('array_intersect_uassoc', 3));
        self::assertSame([2], $this->d2ConsumedArgumentPositions('array_intersect_ukey', 3));

        $factory = $sources['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'];
        self::assertSame(
            [
                'BlackOps\\Core\\ActorRef',
                'BlackOps\\Core\\Audit\\AuditDelivery',
                'BlackOps\\Core\\Audit\\AuditEventCatalog',
                'BlackOps\\Core\\Audit\\AuditEventDefinition',
                'BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose',
                'BlackOps\\Core\\Audit\\AuditReadPurpose',
                'BlackOps\\Core\\Audit\\AuditReadRequestBasis',
                'BlackOps\\Core\\Audit\\AuditRecordAction',
                'BlackOps\\Core\\Audit\\AuditRecordActor',
                'BlackOps\\Core\\Audit\\AuditRecordCorrelation',
                'BlackOps\\Core\\Audit\\AuditRecordDraft',
                'BlackOps\\Core\\Audit\\AuditRecordEvent',
                'BlackOps\\Core\\Audit\\AuditRecordEvidence',
                'BlackOps\\Core\\Audit\\AuditRecordOutcome',
                'BlackOps\\Core\\Audit\\AuditRecordPolicy',
                'BlackOps\\Core\\Audit\\AuditRecordResource',
                'BlackOps\\Core\\Audit\\AuditRecordSource',
                'BlackOps\\Core\\Audit\\AuditSourceOwner',
                'BlackOps\\Core\\Audit\\AuditSourceRuntime',
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Core\\Identifier\\OperationId',
                'BlackOps\\Core\\TenantRef',
                'BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog',
                'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Read\\AuditReadEventWriter',
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
                'BlackOps\\Internal\\Audit\\Read\\OperationReadNonceSource',
            ],
            $this->d2DependencyInventory($factory, $allSources)['project'],
        );
        self::assertSame(
            [
                'DateTimeZone',
                'LogicException',
                'Psr\\Clock\\ClockInterface',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            $this->d2DependencyInventory($factory, $allSources)['builtin'],
        );

        $open = $this->d2MethodTokens($sources['src/Internal/Audit/Read/OperationReadAuditSession.php'], 'open');
        self::assertSame(1, $this->d2ObjectCallCount($open, 'requested'));
        self::assertSame(1, $this->d2ConstructorCount($open, 'self'));
        self::assertSame(1, $this->d2ReturnVariableCount($open, 'session'));
        self::assertTrue($this->d2OpenWriterOrderIsPure($open));

        $begin = $this->d2MethodTokens($factory, 'begin');
        self::assertSame(2, $this->d2ObjectCallCount($begin, 'reference'));
        foreach (['execution', 'resourceId', 'requestedAt', 'nonce', 'catalog', 'open'] as $method) {
            self::assertSame(1, $this->d2CallCount($begin, $method), $method);
        }
        self::assertSame(3, $this->d2CallCount($begin, 'definition'));
        self::assertSame(3, $this->d2CallCount($begin, 'draft'));
        self::assertSame(1, $this->d2StaticCallCount($begin, 'OperationReadAuditSession', 'open'));

        foreach (['requested', 'terminal'] as $method) {
            $gateMethod = $this->d2MethodTokens(
                $sources['src/Internal/Audit/Orchestration/AuditProtectedReadGate.php'],
                $method,
            );
            self::assertSame(1, $this->d2ObjectCallCount($gateMethod, 'deliverStandalone'), $method);
        }
    }

    public function testTokenAwareOracleRejectsArchitectureMutationsAndKeepsFalsePositives(): void
    {
        $sources = $this->d2Sources();
        $allSources = $this->d2AllProductionSources();
        $noncePath = 'src/Internal/Audit/Read/RandomOperationReadNonceSource.php';
        $mutations = [
            'alias-use' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/AuditReadEventWriter.php' => $this->d2ReplaceExact(
                    $this->d2ReplaceExact(
                        $candidate['src/Internal/Audit/Read/AuditReadEventWriter.php'],
                        'use BlackOps\\Core\\Audit\\AuditReadRequestBasis;',
                        'use BlackOps\\Core\\Audit\\AuditReadRequestBasis as Basis;',
                    ),
                    'AuditReadRequestBasis $basis',
                    'Basis $basis',
                ),
            ],
            'group-use' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/AuditReadEventWriter.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/AuditReadEventWriter.php'],
                    "use BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision;\nuse BlackOps\\Core\\Audit\\AuditReadContext;",
                    'use BlackOps\\Core\\Audit\\{AuditReadAuthorizationDecision, AuditReadContext};',
                ),
            ],
            'fully-qualified-bypass' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadExecutionSource.php' => $this->d2ReplaceExact(
                    $this->d2ReplaceExact(
                        $candidate['src/Internal/Audit/Read/OperationReadExecutionSource.php'],
                        'use BlackOps\\Core\\Audit\\AuditTypedReference;',
                        '',
                    ),
                    'AuditTypedReference',
                    '\\BlackOps\\Core\\Audit\\AuditTypedReference',
                ),
            ],
            'same-namespace-bypass' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2InsertBefore(
                    $this->d2AppendAfterFinalBrace(
                        $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                        'final class D2SameNamespaceCanary {}',
                    ),
                    '        return \'operation-\' . $purpose->value;',
                    <<<'PHP'
                                new D2SameNamespaceCanary();
                                return 'operation-' . $purpose->value;
                        PHP,
                ),
            ],
            'vendor-dependency' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        new \\Vendor\\AuditDependency();\n",
                ),
            ],
            'unknown-global-type' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        new \\stdClass();\n",
                ),
            ],
            'unknown-same-namespace-type' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        new MissingNonceDependency();\n",
                ),
            ],
            'unknown-blackops-type' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        new \\BlackOps\\Core\\Audit\\MissingNonceDependency();\n",
                ),
            ],
            'forbidden-environment' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        getenv('BLACKOPS_TEST_ENV');\n",
                ),
            ],
            'forbidden-filesystem' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        file_get_contents('php://memory');\n",
                ),
            ],
            'forbidden-network' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        stream_socket_client('tcp://127.0.0.1:1');\n",
                ),
            ],
            'forbidden-uuid' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        uuid_create();\n",
                ),
            ],
            'literal-class-string' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        \\BlackOps\\Internal\\Audit\\Read\\OperationReadNonceSource::class;\n",
                ),
            ],
            'one-hop-class-string' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            $class = 'BlackOps\Internal\Audit\Read\OperationReadNonceSource';
                            $one = $class;
                            class_exists($one);
                    PHP),
            ],
            'two-hop-class-string' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            $first = 'BlackOps\Internal\Audit\Read\OperationReadNonceSource';
                            $second = $first;
                            $third = $second;
                            class_exists($third);
                    PHP),
            ],
            'callable-array' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            call_user_func([\BlackOps\Internal\Audit\Read\OperationReadNonceSource::class, 'generate']);
                    PHP),
            ],
            'call-user-func' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            call_user_func([
                                'BlackOps\Internal\Audit\Read\OperationReadNonceSource',
                                'generate',
                            ]);
                    PHP),
            ],
            'generic-callback-sink' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            array_map([
                                'BlackOps\Internal\Audit\Read\OperationReadNonceSource',
                                'generate',
                            ], []);
                    PHP),
            ],
            'parenthesized-literal-callable' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            (['BlackOps\Internal\Audit\Read\OperationReadNonceSource', 'generate'])();
                    PHP),
            ],
            'callable-copy-one-hop' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            $first = [
                                'BlackOps\Internal\Audit\Read\OperationReadNonceSource',
                                'generate',
                            ];
                            $second = $first;
                            call_user_func($second);
                    PHP),
            ],
            'callable-copy-two-hop' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            $first = [
                                'BlackOps\Internal\Audit\Read\OperationReadNonceSource',
                                'generate',
                            ];
                            $second = $first;
                            $third = $second;
                            call_user_func($third);
                    PHP),
            ],
            'bare-callable-string-two-hop' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            $first = 'BlackOps\Internal\Audit\Read\OperationReadAuditSession::open';
                            $second = $first;
                            $third = $second;
                            $third();
                    PHP),
            ],
            'random-bytes-shape' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2ReplaceExact(
                    $candidate[$noncePath],
                    'return random_bytes(16);',
                    'return random_bytes(24);',
                ),
            ],
            'random-bytes-receiver' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2ReplaceExact(
                    $candidate[$noncePath],
                    'return random_bytes(16);',
                    'return RandomOperationReadNonceSource::random_bytes(16);',
                ),
            ],
            'variable-construction' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            $constructor = 'BlackOps\Internal\Audit\Read\RandomOperationReadNonceSource';
                            new $constructor();
                    PHP),
            ],
            'reflection' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    "        new \\ReflectionClass('BlackOps\\\\Internal\\\\Audit\\\\Read\\\\OperationReadNonceSource');\n",
                ),
            ],
            'extra-type' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2ReplaceExact(
                    $candidate[$noncePath],
                    'public function generate(): string',
                    'public function generate(): \\DateTimeImmutable',
                ),
            ],
            'outside-caller' => fn(array $candidate): array => [
                ...$candidate,
                'src/External/D2OutsideCaller.php' => <<<'PHP'
                    <?php

                    final class D2OutsideCaller
                    {
                        public function call(): void
                        {
                            \BlackOps\Internal\Audit\Read\OperationReadAuditSession::open();
                        }
                    }
                    PHP,
            ],
            'helper-added' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2InsertBeforeFinalBrace(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    '    private function extraHelper(): void {}',
                ),
            ],
            'dependency-added' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore($candidate[$noncePath], '        return random_bytes(16);', <<<'PHP'
                            $unused = \BlackOps\Core\Audit\AuditDelivery::Required;
                    PHP),
            ],
            'method-added' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2InsertBeforeFinalBrace(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    '    private function addedMethod(): void {}',
                ),
            ],
            'method-removed' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2RemoveMethod(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    '    private function component(',
                ),
            ],
            'method-renamed' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    'private function component(',
                    'private function renamedComponent(',
                ),
            ],
            'method-visibility' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    'private function component(',
                    'protected function component(',
                ),
            ],
            'method-static' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    'private function component(',
                    'private static function component(',
                ),
            ],
            'method-final' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    'private function component(',
                    'private final function component(',
                ),
            ],
            'anonymous-function' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    '        (static function (): void {});' . "\n",
                ),
            ],
            'arrow-function' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    '        $unused = static fn (): string => \'nonce\';' . "\n",
                ),
            ],
            'braced-dynamic-method' => fn(array $candidate): array => [
                ...$candidate,
                $noncePath => $this->d2InsertBefore(
                    $candidate[$noncePath],
                    '        return random_bytes(16);',
                    '        $this->{$method}();' . "\n",
                ),
            ],
            'receiver-helper' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    '$this->execution()',
                    '$operationId->execution()',
                ),
            ],
            'receiver-catalog' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    'FrameworkAuditEventCatalog::catalog()',
                    'AuditEventCatalog::catalog()',
                ),
            ],
            'receiver-derive' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'],
                    'AuditOpaqueIdPurpose::Resource',
                    'AuditOpaqueIdPurpose::Action',
                ),
            ],
            'receiver-writer' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSession.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSession.php'],
                    '$writer->requested(',
                    '$other->requested(',
                ),
            ],
            'receiver-gate' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => $this->d2ReplaceExactCount(
                    $candidate['src/Internal/Audit/Orchestration/AuditProtectedReadGate.php'],
                    '$this->delivery->deliverStandalone(',
                    '$other->deliverStandalone(',
                    2,
                ),
            ],
            'open-order' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSession.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSession.php'],
                    '            $session = new self($writer, $basis, $accessed, $unavailable);',
                    <<<'PHP'
                                    $writer->requested($basis, $requested);
                                    $session = new self($writer, $basis, $accessed, $unavailable);
                        PHP,
                ),
            ],
            'post-writer-call' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSession.php' => $this->d2ReplaceExact(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSession.php'],
                    '        return $session;',
                    <<<'PHP'
                                strlen('post-writer-canary');
                                return $session;
                        PHP,
                ),
            ],
            'post-writer-assignment' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSession.php' => $this->d2InsertBefore(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSession.php'],
                    '        return $session;',
                    <<<'PHP'
                                $after = 1;
                        PHP,
                ),
            ],
            'post-writer-branch' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSession.php' => $this->d2InsertBefore(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSession.php'],
                    '        return $session;',
                    <<<'PHP'
                                if (true) {
                                }
                        PHP,
                ),
            ],
            'post-writer-new' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSession.php' => $this->d2InsertBefore(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSession.php'],
                    '        return $session;',
                    <<<'PHP'
                                new \stdClass();
                        PHP,
                ),
            ],
            'post-writer-property' => fn(array $candidate): array => [
                ...$candidate,
                'src/Internal/Audit/Read/OperationReadAuditSession.php' => $this->d2InsertBefore(
                    $candidate['src/Internal/Audit/Read/OperationReadAuditSession.php'],
                    '        return $session;',
                    <<<'PHP'
                                $session;
                        PHP,
                ),
            ],
        ];
        $expectedRules = [
            'alias-use' => 'imports:src/Internal/Audit/Read/AuditReadEventWriter.php',
            'group-use' => 'imports:src/Internal/Audit/Read/AuditReadEventWriter.php',
            'fully-qualified-bypass' => 'imports:src/Internal/Audit/Read/OperationReadExecutionSource.php',
            'same-namespace-bypass' => 'declarations:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'vendor-dependency' => 'builtin-dependencies:src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'unknown-global-type' => 'unknown-dependencies:src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'unknown-same-namespace-type' => 'unknown-dependencies:src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'unknown-blackops-type' => 'unknown-dependencies:src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'forbidden-environment' => 'environment',
            'forbidden-filesystem' => 'filesystem',
            'forbidden-network' => 'network',
            'forbidden-uuid' => 'uuid',
            'literal-class-string' => 'class-string',
            'one-hop-class-string' => 'class-string',
            'two-hop-class-string' => 'class-string',
            'callable-array' => 'callable-array',
            'call-user-func' => 'callable-function',
            'generic-callback-sink' => 'class-string',
            'parenthesized-literal-callable' => 'class-string',
            'callable-copy-one-hop' => 'class-string',
            'callable-copy-two-hop' => 'class-string',
            'bare-callable-string-two-hop' => 'class-string',
            'random-bytes-shape' => 'random-bytes-shape',
            'random-bytes-receiver' => 'random-bytes-shape',
            'variable-construction' => 'variable-construction',
            'reflection' => 'reflection',
            'extra-type' => 'signatures:src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'outside-caller' => 'outside-target-reference:src/External/D2OutsideCaller.php',
            'helper-added' => 'callables:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'dependency-added' => 'project-dependencies:src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'method-added' => 'callables:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'method-removed' => 'callables:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'method-renamed' => 'callables:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'method-visibility' => 'callables:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'method-static' => 'callables:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'method-final' => 'signatures:src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'anonymous-function' => 'anonymous-function',
            'arrow-function' => 'arrow-function',
            'braced-dynamic-method' => 'dynamic-method',
            'receiver-helper' => 'begin:execution-receiver',
            'receiver-catalog' => 'begin:catalog-receiver',
            'receiver-derive' => 'factory:derive-binding',
            'receiver-writer' => 'session:open-order',
            'receiver-gate' => 'gate:requested:delivery',
            'open-order' => 'session:open-order',
            'post-writer-call' => 'session:open-order',
            'post-writer-assignment' => 'session:open-order',
            'post-writer-branch' => 'session:open-order',
            'post-writer-new' => 'session:open-order',
            'post-writer-property' => 'session:open-order',
        ];
        self::assertSame(array_keys($expectedRules), array_keys($mutations));
        foreach ($mutations as $name => $mutation) {
            $candidate = $mutation($sources);
            $this->assertD2MutationSyntax($candidate, $name);
            $violations = $this->d2ArchitectureViolations($candidate, [...$allSources, ...$candidate]);
            self::assertNotEmpty($violations, $name . ' mutation was accepted');
            self::assertTrue(
                count(array_filter($violations, static fn(string $violation): bool => str_contains(
                    $violation,
                    $expectedRules[$name],
                ))) > 0,
                $name . ' did not trigger its intended architecture rule: ' . implode(',', $violations),
            );
        }

        $d2sOutsidePath = 'src/External/D2SOutsideCaller.php';
        $d2sOutsideSource = <<<'PHP'
            <?php
            new \BlackOps\Internal\Status\AuditedOperationStatusQuery($a, $b, $c, $d);
            PHP;
        $this->assertD2MutationSyntax([$d2sOutsidePath => $d2sOutsideSource], 'd2s-outside-caller');
        $d2sOutsideViolations = $this->d2ArchitectureViolations($sources, [
            ...$allSources,
            $d2sOutsidePath => $d2sOutsideSource,
        ]);
        self::assertContains('outside-target-reference:' . $d2sOutsidePath, $d2sOutsideViolations);

        $d2jOutsideCallers = [
            'journal-query' => [
                'BlackOps\\Internal\\OperationData\\AuditedOperationJournalQuery',
                'new \\BlackOps\\Internal\\OperationData\\AuditedOperationJournalQuery($a, $b, $c, $d, $e);',
            ],
            'outcome-query' => [
                'BlackOps\\Internal\\OperationData\\AuditedOperationOutcomeQuery',
                'new \\BlackOps\\Internal\\OperationData\\AuditedOperationOutcomeQuery($a, $b, $c, $d, $e);',
            ],
            'tenant-authorizer' => [
                'BlackOps\\Internal\\OperationData\\OperationDataAuditTenantAuthorizer',
                'new \\BlackOps\\Internal\\OperationData\\OperationDataAuditTenantAuthorizer($a);',
            ],
            'journal-execution' => [
                'BlackOps\\Internal\\OperationData\\OperationJournalReadExecutionSource',
                'new \\BlackOps\\Internal\\OperationData\\OperationJournalReadExecutionSource($a);',
            ],
            'outcome-execution' => [
                'BlackOps\\Internal\\OperationData\\OperationOutcomeReadExecutionSource',
                'new \\BlackOps\\Internal\\OperationData\\OperationOutcomeReadExecutionSource($a);',
            ],
        ];
        foreach ($d2jOutsideCallers as $name => [$target, $expression]) {
            $path = 'src/External/D2J' . str_replace('-', '', ucwords($name, '-')) . 'OutsideCaller.php';
            $source = "<?php\n\nfinal class D2JOutsideCaller\n{\n    public function call(): void\n    {\n        {$expression}\n    }\n}\n";
            $this->assertD2MutationSyntax([$path => $source], 'd2j-outside-' . $name);
            $violations = $this->d2ArchitectureViolations($sources, [
                ...$allSources,
                $path => $source,
            ]);
            self::assertContains('outside-target-reference:' . $path, $violations, $target);
        }

        $d2xOutsideCallers = [
            'diagnostics-query' => [
                'BlackOps\\Internal\\Diagnostics\\AuditedOperationDiagnosticsQuery',
                'new \\BlackOps\\Internal\\Diagnostics\\AuditedOperationDiagnosticsQuery($a, $b, $c, $d, $e, $f);',
            ],
            'diagnostics-execution' => [
                'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsReadExecutionSource',
                'new \\BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsReadExecutionSource($a);',
            ],
        ];
        foreach ($d2xOutsideCallers as $name => [$target, $expression]) {
            $path = 'src/External/D2X' . str_replace('-', '', ucwords($name, '-')) . 'OutsideCaller.php';
            $source = "<?php\n\nfinal class D2XOutsideCaller\n{\n    public function call(): void\n    {\n        {$expression}\n    }\n}\n";
            $this->assertD2MutationSyntax([$path => $source], 'd2x-outside-' . $name);
            $violations = $this->d2ArchitectureViolations($sources, [
                ...$allSources,
                $path => $source,
            ]);
            self::assertContains('outside-target-reference:' . $path, $violations, $target);
        }

        $extraSourcePath = 'src/Internal/Audit/Read/OperationReadAuditSessionExtra.php';
        $extraSource = <<<'PHP'
            <?php

            namespace BlackOps\Internal\Audit\Read;

            final class OperationReadAuditSessionExtra {}
            PHP;
        $this->assertD2MutationSyntax([$extraSourcePath => $extraSource], 'extra-production-file');
        $extraFileViolations = $this->d2ArchitectureViolations($sources, [
            ...$allSources,
            $extraSourcePath => $extraSource,
        ]);
        self::assertContains('source-path-inventory:' . $extraSourcePath, $extraFileViolations);

        $outsideIndirectMutations = [
            'outside-literal-class-string' => <<<'PHP'
                <?php
                class_exists('BlackOps\Internal\Audit\Read\OperationReadAuditSession');
                PHP,
            'outside-one-hop-class-string' => <<<'PHP'
                <?php
                $class = 'BlackOps\Internal\Audit\Read\OperationReadAuditSession';
                $one = $class;
                class_exists($one);
                PHP,
            'outside-two-hop-class-string' => <<<'PHP'
                <?php
                $first = 'BlackOps\Internal\Audit\Read\OperationReadAuditSession';
                $second = $first;
                $third = $second;
                class_exists($third);
                PHP,
            'outside-callable-array' => <<<'PHP'
                <?php
                call_user_func([
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                ]);
                PHP,
            'outside-call-user-func' => <<<'PHP'
                <?php
                $callable = [
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                ];
                call_user_func($callable);
                PHP,
            'outside-long-callable-array' => <<<'PHP'
                <?php
                call_user_func(array(
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                ));
                PHP,
            'outside-long-callable-variable' => <<<'PHP'
                <?php
                $callable = array(
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                );
                call_user_func($callable);
                PHP,
            'outside-callable-string' => <<<'PHP'
                <?php
                call_user_func('BlackOps\Internal\Audit\Read\OperationReadAuditSession::open');
                PHP,
            'outside-parenthesized-callable' => <<<'PHP'
                <?php
                $callable = array(
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                );
                ($callable)();
                PHP,
            'outside-closure-callable' => <<<'PHP'
                <?php
                Closure::fromCallable([
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                ]);
                PHP,
            'outside-reflection' => <<<'PHP'
                <?php
                new \ReflectionClass('BlackOps\Internal\Audit\Read\OperationReadAuditSession');
                PHP,
            'outside-variable-construction' => <<<'PHP'
                <?php
                $class = 'BlackOps\Internal\Audit\Read\RandomOperationReadNonceSource';
                new $class();
                PHP,
            'outside-variable-static-call' => <<<'PHP'
                <?php
                $class = 'BlackOps\Internal\Audit\Read\OperationReadAuditSession';
                $class::open();
                PHP,
            'outside-generic-callback-sink' => <<<'PHP'
                <?php
                array_map([
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                ], []);
                PHP,
            'outside-array-diff-uassoc-sink' => <<<'PHP'
                <?php
                array_diff_uassoc([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
                PHP,
            'outside-array-diff-ukey-sink' => <<<'PHP'
                <?php
                array_diff_ukey([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
                PHP,
            'outside-array-intersect-uassoc-sink' => <<<'PHP'
                <?php
                array_intersect_uassoc([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
                PHP,
            'outside-array-intersect-ukey-sink' => <<<'PHP'
                <?php
                array_intersect_ukey([], [], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::open');
                PHP,
            'outside-parenthesized-literal-callable' => <<<'PHP'
                <?php
                (['BlackOps\Internal\Audit\Read\OperationReadAuditSession', 'open'])();
                PHP,
            'outside-callable-copy-one-hop' => <<<'PHP'
                <?php
                $first = [
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                ];
                $second = $first;
                call_user_func($second);
                PHP,
            'outside-callable-copy-two-hop' => <<<'PHP'
                <?php
                $first = [
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                ];
                $second = $first;
                $third = $second;
                call_user_func($third);
                PHP,
        ];
        foreach ($outsideIndirectMutations as $name => $outsideSource) {
            $path = 'src/External/' . $name . '.php';
            $this->assertD2MutationSyntax([$path => $outsideSource], $name);
            $violations = $this->d2ArchitectureViolations($sources, [...$allSources, $path => $outsideSource]);
            self::assertContains('outside-target-reference:' . $path, $violations, $name);
            self::assertContains($path . ':class-string', $violations, $name);
        }

        $falsePositives = $sources;
        $falsePositiveAllSources = [...$allSources, 'src/External/D2UnusedLongCallable.php' => <<<'PHP'
                <?php
                $unused = array(
                    'BlackOps\Internal\Audit\Read\OperationReadAuditSession',
                    'open',
                );
                $literal = 'BlackOps\Internal\Audit\Read\OperationReadAuditSession::open';
                PHP];
        $falsePositives[$noncePath] = $this->d2InsertBefore(
            $falsePositives[$noncePath],
            '        return random_bytes(16);',
            <<<'PHP'
                        // call_user_func(), ReflectionClass, new $class, and a vendor FQCN are documentation only.
                        /** @var string $class */
                        $text = 'call_user_func([ReflectionClass::class, "newInstance"]);';
                        $enumClass = \UnitEnum::class;
                        $value = ['unrelated'];
                        $callback = ['strlen', 'length'];
                        array_map($callback, []);
                        array_map([], 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession');
                        array_filter('BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession', null);
                        preg_replace_callback('/canary/', 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession', 'subject');
                        (['strlen', 'length'])();
                        $length = strlen($text);
                        $allowed = $this->generate;
                        <<<'D2_NOWDOC'
                        new $class();
                        D2_NOWDOC;
                PHP,
        );
        self::assertSame([], $this->d2ArchitectureViolations($falsePositives, $falsePositiveAllSources));
    }

    private function d2ReplaceExact(string $source, string $needle, string $replacement): string
    {
        self::assertSame(1, substr_count($source, $needle), 'mutation marker count for ' . $needle);

        return str_replace($needle, $replacement, $source);
    }

    private function d2ReplaceExactCount(string $source, string $needle, string $replacement, int $count): string
    {
        self::assertSame($count, substr_count($source, $needle), 'mutation marker count for ' . $needle);

        return str_replace($needle, $replacement, $source);
    }

    private function d2InsertBefore(string $source, string $needle, string $insertion): string
    {
        return $this->d2ReplaceExact($source, $needle, $insertion . $needle);
    }

    private function d2RemoveMethod(string $source, string $needle): string
    {
        self::assertSame(1, substr_count($source, $needle), 'mutation marker count for ' . $needle);
        $start = strpos($source, $needle);
        $end = strrpos($source, "\n}");
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertTrue($start < $end);

        return substr($source, 0, $start) . substr($source, $end);
    }

    private function d2InsertBeforeFinalBrace(string $source, string $insertion): string
    {
        $position = strrpos($source, "\n}");
        self::assertNotFalse($position);

        return substr_replace($source, "\n" . $insertion, $position, 0);
    }

    private function d2AppendAfterFinalBrace(string $source, string $insertion): string
    {
        return rtrim($source) . "\n\n" . $insertion . "\n";
    }

    /** @param array<string,string> $candidate */
    private function assertD2MutationSyntax(array $candidate, string $name): void
    {
        foreach ($candidate as $path => $source) {
            self::assertStringStartsWith('<?php', $source, $name . ':' . $path);
            try {
                \PhpToken::tokenize($source, TOKEN_PARSE);
            } catch (\ParseError $error) {
                self::fail($name . ':' . $path . ' parse error: ' . $error->getMessage());
            }
            $depth = ['{' => 0, '(' => 0, '[' => 0];
            foreach (token_get_all($source) as $token) {
                $value = is_array($token) ? $token[1] : $token;
                if ($value === '#[') {
                    $depth['[']++;
                } elseif (array_key_exists($value, $depth)) {
                    $depth[$value]++;
                }
                if ($value === '}') {
                    $depth['{']--;
                } elseif ($value === ')') {
                    $depth['(']--;
                } elseif ($value === ']') {
                    $depth['[']--;
                }
            }
            self::assertSame(['{' => 0, '(' => 0, '[' => 0], $depth, $name . ':' . $path);
        }
    }

    /** @return array<string,string> */
    private function d2Sources(): array
    {
        $paths = $this->d2ExpectedSourcePaths();
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertIsString($sourceRoot);
        $actualDirectoryPaths = [];
        foreach (['Internal/Audit/Read'] as $directory) {
            $directoryRoot = realpath($sourceRoot . '/' . $directory);
            self::assertIsString($directoryRoot);
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directoryRoot)) as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $actualDirectoryPaths[] = 'src/' . str_replace($sourceRoot . '/', '', $file->getPathname());
            }
        }
        $gatePath = realpath($sourceRoot . '/Internal/Audit/Orchestration/AuditProtectedReadGate.php');
        self::assertIsString($gatePath);
        $actualDirectoryPaths[] = 'src/' . str_replace($sourceRoot . '/', '', $gatePath);
        foreach (array_filter($paths, static fn(string $path): bool => str_starts_with(
            $path,
            'src/Internal/Diagnostics/',
        )) as $path) {
            self::assertFileExists($sourceRoot . '/' . substr($path, strlen('src/')));
            $actualDirectoryPaths[] = $path;
        }
        $expectedDirectoryPaths = $paths;
        sort($actualDirectoryPaths);
        sort($expectedDirectoryPaths);
        self::assertSame($expectedDirectoryPaths, $actualDirectoryPaths);
        $sources = [];
        foreach ($paths as $path) {
            $source = file_get_contents(__DIR__ . '/../../' . $path);
            self::assertIsString($source);
            $sources[$path] = $source;
        }

        return $sources;
    }

    /** @return list<string> */
    private function d2ExpectedSourcePaths(): array
    {
        return [
            'src/Internal/Audit/Read/AuditReadEventWriter.php',
            'src/Internal/Audit/Read/OperationReadNonceSource.php',
            'src/Internal/Audit/Read/RandomOperationReadNonceSource.php',
            'src/Internal/Audit/Read/OperationReadExecutionSource.php',
            'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php',
            'src/Internal/Audit/Read/OperationReadAuditSession.php',
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php',
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php',
            'src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php',
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php',
        ];
    }

    /** @return array<string,string> */
    private function d2AllProductionSources(): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $sources['src/' . str_replace($root . '/', '', $file->getPathname())] = $source;
        }

        return $sources;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function d2ProjectSymbols(array $sources): array
    {
        $symbols = [];
        foreach ($sources as $source) {
            $metadata = $this->d2Metadata($source);
            foreach ($metadata['declarations'] as $declaration) {
                if (str_starts_with($declaration['name'], 'BlackOps\\')) {
                    $symbols[] = $declaration['name'];
                }
            }
        }

        return array_values(array_unique($symbols));
    }

    /** @param array<string,string> $sources @param array<string,string> $allSources @return list<string> */
    private function d2ArchitectureViolations(array $sources, array $allSources): array
    {
        $expectedPaths = array_keys($this->d2ExpectedDependencies());
        $actualPaths = array_keys($sources);
        sort($expectedPaths);
        sort($actualPaths);
        $violations = [];
        if ($actualPaths !== $expectedPaths) {
            $violations[] = 'source-path-inventory';
        }
        $expectedD2Paths = array_keys($this->d2ExpectedDependencies());
        $actualD2Paths = array_values(array_filter(
            array_keys($allSources),
            static fn(string $path): bool => (
                str_starts_with($path, 'src/Internal/Audit/Read/')
                || in_array(
                    $path,
                    [
                        'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php',
                        'src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php',
                        'src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php',
                    ],
                    true,
                )
                || $path === 'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php'
            ),
        ));
        sort($expectedD2Paths);
        sort($actualD2Paths);
        if ($actualD2Paths !== $expectedD2Paths) {
            foreach (array_diff($actualD2Paths, $expectedD2Paths) as $extraPath) {
                $violations[] = 'source-path-inventory:' . $extraPath;
            }
            foreach (array_diff($expectedD2Paths, $actualD2Paths) as $missingPath) {
                $violations[] = 'source-path-inventory:missing:' . $missingPath;
            }
            if ($actualD2Paths === [] || $expectedD2Paths === []) {
                $violations[] = 'source-path-inventory';
            }
        }
        $symbols = $this->d2ProjectSymbols($allSources);
        $expectedDeclarations = $this->d2ExpectedDeclarations();
        $expectedCallables = $this->d2ExpectedCallables();
        $expectedImports = $this->d2ExpectedImports();
        foreach ($expectedDeclarations as $path => $declaration) {
            if (!array_key_exists($path, $sources)) {
                $violations[] = 'missing:' . $path;
                continue;
            }
            $metadata = $this->d2Metadata($sources[$path]);
            if ($metadata['declarations'] !== [$declaration]) {
                $violations[] = 'declarations:' . $path;
            }
            if ($metadata['uses'] !== $expectedImports[$path]) {
                $violations[] = 'imports:' . $path;
            }
            $inventory = $this->d2DependencyInventory($sources[$path], $allSources, $symbols);
            $expected = $this->d2ExpectedDependencies()[$path];
            if ($inventory['project'] !== $expected['project']) {
                $violations[] = 'project-dependencies:' . $path;
            }
            if ($inventory['builtin'] !== $expected['builtin']) {
                $violations[] = 'builtin-dependencies:' . $path;
            }
            foreach ($this->d2UnknownDependencyReferences($sources[$path], $symbols) as $unknown) {
                $violations[] = 'unknown-dependencies:' . $path . ':' . $unknown;
            }
            $actualCallables = $this->d2CallableInventory($sources[$path]);
            $requiredCallables = $expectedCallables[$path];
            sort($requiredCallables);
            if ($actualCallables !== $requiredCallables) {
                $violations[] = 'callables:' . $path;
            }
            $actualSignatures = $this->d2SignatureInventory($sources[$path]);
            $expectedSignatures = $this->d2ExpectedSignatures()[$path] ?? [];
            sort($expectedSignatures);
            if ($actualSignatures !== $expectedSignatures) {
                $violations[] = 'signatures:' . $path;
            }
            $actualProperties = $this->d2PropertyInventory($sources[$path]);
            if ($actualProperties !== ($this->d2ExpectedProperties()[$path] ?? [])) {
                $violations[] = 'properties:' . $path;
            }
            foreach ($this->d2ForbiddenExecutable($sources[$path], $symbols) as $finding) {
                $violations[] = $path . ':' . $finding;
            }
        }

        $factory = $sources['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php'] ?? '';
        $session = $sources['src/Internal/Audit/Read/OperationReadAuditSession.php'] ?? '';
        $gate = $sources['src/Internal/Audit/Orchestration/AuditProtectedReadGate.php'] ?? '';
        if ($factory === '' || $session === '' || $gate === '') {
            return $violations;
        }
        $begin = $this->d2MethodTokens($factory, 'begin');
        if ($this->d2ObjectCallCount($begin, 'reference') !== 2) {
            $violations[] = 'begin:reference';
        }
        foreach (['execution', 'resourceId', 'requestedAt', 'nonce', 'catalog'] as $method) {
            if ($this->d2CallCount($begin, $method) !== 1) {
                $violations[] = 'begin:' . $method;
            }
        }
        foreach (['reference', 'execution', 'resourceId', 'requestedAt', 'nonce', 'definition', 'draft'] as $method) {
            $expectedCount = $method === 'reference' ? 2 : ($method === 'definition' || $method === 'draft' ? 3 : 1);
            if ($this->d2ObjectCallCountOnReceiver($begin, $method, '$this') !== $expectedCount) {
                $violations[] = 'begin:' . $method . '-receiver';
            }
        }
        if ($this->d2CallCount($begin, 'definition') !== 3 || $this->d2CallCount($begin, 'draft') !== 3) {
            $violations[] = 'begin:three-drafts';
        }
        if (
            $this->d2StaticTargetCallCount($begin, 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession', 'open')
            !== 1
        ) {
            $violations[] = 'begin:session-open';
        }
        if (
            $this->d2StaticTargetCallCount(
                $begin,
                'BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog',
                'catalog',
            ) !== 1
        ) {
            $violations[] = 'begin:catalog-receiver';
        }
        $resourceId = $this->d2MethodTokens($factory, 'resourceId');
        if (
            $this->d2ObjectCallCountOnReceiver($resourceId, 'derive', '$deriver') !== 1
            || $this->d2CallShape($resourceId, 'derive', '$deriver')
                !== 'derive(AuditOpaqueIdPurpose::Resource,$operationId->toString())'
        ) {
            $violations[] = 'factory:derive-binding';
        }
        $open = $this->d2MethodTokens($session, 'open');
        if (
            $this->d2ObjectCallCount($open, 'requested') !== 1
            || $this->d2ObjectCallCountOnReceiver($open, 'requested', '$writer') !== 1
            || $this->d2ConstructorCount($open, 'self') !== 1
            || $this->d2ReturnVariableCount($open, 'session') !== 1
            || !$this->d2OpenWriterOrderIsPure($open)
        ) {
            $violations[] = 'session:open-order';
        }
        $terminal = $this->d2MethodTokens($session, 'terminal');
        if (
            $this->d2ObjectCallCountOnReceiver($terminal, 'terminal', '$writer') !== 1
            || $this->d2CallShape($terminal, 'terminal', '$writer') !== 'terminal($context,$draft)'
            || $this->d2TokenSequenceCount($terminal, ['$returned', '!==', '$decision']) !== 1
        ) {
            $violations[] = 'session:terminal-binding';
        }
        foreach (['requested', 'terminal'] as $method) {
            if (
                $this->d2ObjectCallCount($this->d2MethodTokens($gate, $method), 'deliverStandalone') !== 1
                || $this->d2ObjectCallCountOnReceiver(
                    $this->d2MethodTokens($gate, $method),
                    'deliverStandalone',
                    '$this->delivery',
                ) !== 1
            ) {
                $violations[] = 'gate:' . $method . ':delivery';
            }
        }
        if ($this->d2FunctionCallCount($factory, 'random_bytes') !== 0) {
            $violations[] = 'factory:random-bytes';
        }
        if (
            $this->d2FunctionCallCount(
                $sources['src/Internal/Audit/Read/RandomOperationReadNonceSource.php'],
                'random_bytes',
            ) !== 1
        ) {
            $violations[] = 'nonce:random-bytes';
        }
        if ($this->d2ObjectCallCount($this->d2SignificantTokens($factory), 'derive') !== 1) {
            $violations[] = 'factory:resource-derive';
        }
        if (
            $this->d2ObjectCallCount($this->d2SignificantTokens($factory), 'now') !== 1
            || $this->d2ObjectCallCount($this->d2SignificantTokens($factory), 'generate') !== 1
        ) {
            $violations[] = 'factory:clock-or-nonce';
        }

        $openCallers = [];
        $factoryConstructors = [];
        foreach ($allSources as $path => $source) {
            $openCount = $this->d2StaticTargetCallCount(
                $this->d2SignificantTokens($source),
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                'open',
            );
            if ($openCount > 0) {
                $openCallers[$path] = $openCount;
            }
            $constructorCount = $this->d2ConstructorTargetCount(
                $source,
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
            );
            if ($constructorCount > 0) {
                $factoryConstructors[$path] = $constructorCount;
            }
            if (
                !in_array($path, array_keys($sources), true)
                && $this->d2DependencyInventory($source, $allSources, $symbols)['project'] !== []
                && in_array(
                    'BlackOps\\Internal\\Audit\\Read\\AuditReadEventWriter',
                    $this->d2DependencyInventory($source, $allSources, $symbols)['project'],
                    true,
                )
            ) {
                $violations[] = 'writer-outside-foundation:' . $path;
            }
        }
        if ($openCallers !== ['src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => 1]) {
            $violations[] = 'session-open-callers';
        }
        if ($factoryConstructors !== []) {
            $violations[] = 'factory-callers';
        }

        $outsideTargets = [
            'BlackOps\\Internal\\Audit\\Read\\AuditReadEventWriter',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadNonceSource',
            'BlackOps\\Internal\\Audit\\Read\\RandomOperationReadNonceSource',
            'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizationRequest',
            'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizer',
            'BlackOps\\Internal\\Diagnostics\\UnavailableOperationDiagnosticsAuthorizer',
            'BlackOps\\Internal\\Status\\AuditedOperationStatusQuery',
            'BlackOps\\Internal\\Status\\OperationStatusAuditTenantAuthorizer',
            'BlackOps\\Internal\\Status\\OperationStatusReadExecutionSource',
            'BlackOps\\Internal\\OperationData\\AuditedOperationJournalQuery',
            'BlackOps\\Internal\\OperationData\\AuditedOperationOutcomeQuery',
            'BlackOps\\Internal\\OperationData\\OperationDataAuditTenantAuthorizer',
            'BlackOps\\Internal\\OperationData\\OperationJournalReadExecutionSource',
            'BlackOps\\Internal\\OperationData\\OperationOutcomeReadExecutionSource',
            'BlackOps\\Internal\\Diagnostics\\AuditedOperationDiagnosticsQuery',
            'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsReadExecutionSource',
        ];
        $allowedTargetFiles = array_keys($this->d2ExpectedDependencies());
        $adapterTargetAllowances = [
            'src/Internal/Status/AuditedOperationStatusQuery.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
                'BlackOps\\Internal\\Status\\AuditedOperationStatusQuery',
                'BlackOps\\Internal\\Status\\OperationStatusAuditTenantAuthorizer',
            ],
            'src/Internal/Status/OperationStatusAuditTenantAuthorizer.php' => [
                'BlackOps\\Internal\\Status\\OperationStatusAuditTenantAuthorizer',
            ],
            'src/Internal/Status/OperationStatusReadExecutionSource.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
                'BlackOps\\Internal\\Status\\OperationStatusReadExecutionSource',
            ],
            'src/Internal/OperationData/AuditedOperationJournalQuery.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
                'BlackOps\\Internal\\OperationData\\AuditedOperationJournalQuery',
                'BlackOps\\Internal\\OperationData\\OperationDataAuditTenantAuthorizer',
            ],
            'src/Internal/OperationData/AuditedOperationOutcomeQuery.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
                'BlackOps\\Internal\\OperationData\\AuditedOperationOutcomeQuery',
                'BlackOps\\Internal\\OperationData\\OperationDataAuditTenantAuthorizer',
            ],
            'src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php' => [
                'BlackOps\\Internal\\OperationData\\OperationDataAuditTenantAuthorizer',
            ],
            'src/Internal/OperationData/OperationJournalReadExecutionSource.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
                'BlackOps\\Internal\\OperationData\\OperationJournalReadExecutionSource',
            ],
            'src/Internal/OperationData/OperationOutcomeReadExecutionSource.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
                'BlackOps\\Internal\\OperationData\\OperationOutcomeReadExecutionSource',
            ],
            'src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
                'BlackOps\\Internal\\Diagnostics\\AuditedOperationDiagnosticsQuery',
                'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizationRequest',
                'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizer',
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php' => [
                'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
                'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsReadExecutionSource',
            ],
        ];
        foreach ($allSources as $path => $source) {
            $consumedTargetReferences = $this->d2ConsumedTargetReferences($source, $outsideTargets);
            $targetReferences = array_values(array_unique([
                ...$this->d2TargetReferences($source, $outsideTargets, $symbols),
                ...$consumedTargetReferences,
            ]));
            if (
                $targetReferences !== []
                && !in_array($path, $allowedTargetFiles, true)
                && (
                    !array_key_exists($path, $adapterTargetAllowances)
                    || array_diff($targetReferences, $adapterTargetAllowances[$path]) !== []
                    || array_diff($adapterTargetAllowances[$path], $targetReferences) !== []
                )
            ) {
                $violations[] = 'outside-target-reference:' . $path;
            }
            if ($consumedTargetReferences !== []) {
                $violations[] = $path . ':class-string';
            }
            if ($targetReferences !== []) {
                foreach ($this->d2ForbiddenExecutable($source, $symbols) as $finding) {
                    $violations[] = $path . ':' . $finding;
                }
            }
        }

        return $violations;
    }

    /** @return array<string,array{project:list<string>,builtin:list<string>}> */
    private function d2ExpectedDependencies(): array
    {
        $p = static fn(string $name): string => 'BlackOps\\' . $name;

        return [
            'src/Internal/Audit/Read/AuditReadEventWriter.php' => [
                'project' => [
                    $p('Core\\Audit\\AuditReadAuthorizationDecision'),
                    $p('Core\\Audit\\AuditReadContext'),
                    $p('Core\\Audit\\AuditReadRequestBasis'),
                    $p('Core\\Audit\\AuditRecordDraft'),
                ],
                'builtin' => ['SensitiveParameter'],
            ],
            'src/Internal/Audit/Read/OperationReadNonceSource.php' => [
                'project' => [],
                'builtin' => [],
            ],
            'src/Internal/Audit/Read/RandomOperationReadNonceSource.php' => [
                'project' => [$p('Internal\\Audit\\Read\\OperationReadNonceSource')],
                'builtin' => [],
            ],
            'src/Internal/Audit/Read/OperationReadExecutionSource.php' => [
                'project' => [$p('Core\\Audit\\AuditTypedReference')],
                'builtin' => [],
            ],
            'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => [
                'project' => [
                    $p('Core\\ActorRef'),
                    $p('Core\\Audit\\AuditDelivery'),
                    $p('Core\\Audit\\AuditEventCatalog'),
                    $p('Core\\Audit\\AuditEventDefinition'),
                    $p('Core\\Audit\\AuditOpaqueIdPurpose'),
                    $p('Core\\Audit\\AuditReadPurpose'),
                    $p('Core\\Audit\\AuditReadRequestBasis'),
                    $p('Core\\Audit\\AuditRecordAction'),
                    $p('Core\\Audit\\AuditRecordActor'),
                    $p('Core\\Audit\\AuditRecordCorrelation'),
                    $p('Core\\Audit\\AuditRecordDraft'),
                    $p('Core\\Audit\\AuditRecordEvent'),
                    $p('Core\\Audit\\AuditRecordEvidence'),
                    $p('Core\\Audit\\AuditRecordOutcome'),
                    $p('Core\\Audit\\AuditRecordPolicy'),
                    $p('Core\\Audit\\AuditRecordResource'),
                    $p('Core\\Audit\\AuditRecordSource'),
                    $p('Core\\Audit\\AuditSourceOwner'),
                    $p('Core\\Audit\\AuditSourceRuntime'),
                    $p('Core\\Audit\\AuditTypedReference'),
                    $p('Core\\Audit\\AuditValidationException'),
                    $p('Core\\Identifier\\OperationId'),
                    $p('Core\\TenantRef'),
                    $p('Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog'),
                    $p('Internal\\Audit\\HmacAuditOpaqueIdDeriver'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryException'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryFailure'),
                    $p('Internal\\Audit\\Read\\AuditReadEventWriter'),
                    $p('Internal\\Audit\\Read\\OperationReadAuditSession'),
                    $p('Internal\\Audit\\Read\\OperationReadExecutionSource'),
                    $p('Internal\\Audit\\Read\\OperationReadNonceSource'),
                ],
                'builtin' => [
                    'DateTimeZone',
                    'LogicException',
                    'Psr\\Clock\\ClockInterface',
                    'SensitiveParameter',
                    'SensitiveParameterValue',
                    'Throwable',
                ],
            ],
            'src/Internal/Audit/Read/OperationReadAuditSession.php' => [
                'project' => [
                    $p('Core\\Audit\\AuditReadAuthorizationDecision'),
                    $p('Core\\Audit\\AuditReadContext'),
                    $p('Core\\Audit\\AuditReadRequestBasis'),
                    $p('Core\\Audit\\AuditRecordDraft'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryException'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryFailure'),
                    $p('Internal\\Audit\\Read\\AuditReadEventWriter'),
                ],
                'builtin' => ['SensitiveParameter', 'SensitiveParameterValue', 'Throwable'],
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php' => [
                'project' => [$p('Core\\ActorRef'), $p('Core\\Identifier\\OperationId'), $p('Core\\TenantRef')],
                'builtin' => ['LogicException', 'SensitiveParameter', 'SensitiveParameterValue', 'Throwable'],
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php' => [
                'project' => [
                    $p('Core\\Audit\\AuditReadAuthorizationDecision'),
                    $p('Internal\\Diagnostics\\OperationDiagnosticsAuthorizationRequest'),
                ],
                'builtin' => ['SensitiveParameter'],
            ],
            'src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php' => [
                'project' => [
                    $p('Core\\Audit\\AuditReadAuthorizationDecision'),
                    $p('Internal\\Diagnostics\\OperationDiagnosticsAuthorizationRequest'),
                    $p('Internal\\Diagnostics\\OperationDiagnosticsAuthorizer'),
                ],
                'builtin' => ['SensitiveParameter'],
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                'project' => [
                    $p('Core\\Audit\\AuditActionPhase'),
                    $p('Core\\Audit\\AuditDelivery'),
                    $p('Core\\Audit\\AuditEventCategory'),
                    $p('Core\\Audit\\AuditOutcomeStatus'),
                    $p('Core\\Audit\\AuditPolicyDecision'),
                    $p('Core\\Audit\\AuditReadAuthorizationDecision'),
                    $p('Core\\Audit\\AuditReadContext'),
                    $p('Core\\Audit\\AuditReadRequestBasis'),
                    $p('Core\\Audit\\AuditRecordDraft'),
                    $p('Core\\Audit\\AuditSourceOwner'),
                    $p('Core\\Audit\\AuditValidationException'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryException'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryFailure'),
                    $p('Internal\\Audit\\Orchestration\\AuditDeliveryRequest'),
                    $p('Internal\\Audit\\Read\\AuditReadEventWriter'),
                ],
                'builtin' => ['SensitiveParameter', 'Throwable'],
            ],
        ];
    }

    /** @return array<string,list<string>> */
    private function d2ExpectedImports(): array
    {
        return [
            'src/Internal/Audit/Read/AuditReadEventWriter.php' => [
                'use BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision;',
                'use BlackOps\\Core\\Audit\\AuditReadContext;',
                'use BlackOps\\Core\\Audit\\AuditReadRequestBasis;',
                'use BlackOps\\Core\\Audit\\AuditRecordDraft;',
            ],
            'src/Internal/Audit/Read/OperationReadNonceSource.php' => [],
            'src/Internal/Audit/Read/RandomOperationReadNonceSource.php' => [],
            'src/Internal/Audit/Read/OperationReadExecutionSource.php' => [
                'use BlackOps\\Core\\Audit\\AuditTypedReference;',
            ],
            'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => [
                'use BlackOps\\Core\\ActorRef;',
                'use BlackOps\\Core\\Audit\\AuditDelivery;',
                'use BlackOps\\Core\\Audit\\AuditEventCatalog;',
                'use BlackOps\\Core\\Audit\\AuditEventDefinition;',
                'use BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose;',
                'use BlackOps\\Core\\Audit\\AuditReadPurpose;',
                'use BlackOps\\Core\\Audit\\AuditReadRequestBasis;',
                'use BlackOps\\Core\\Audit\\AuditRecordAction;',
                'use BlackOps\\Core\\Audit\\AuditRecordActor;',
                'use BlackOps\\Core\\Audit\\AuditRecordCorrelation;',
                'use BlackOps\\Core\\Audit\\AuditRecordDraft;',
                'use BlackOps\\Core\\Audit\\AuditRecordEvent;',
                'use BlackOps\\Core\\Audit\\AuditRecordEvidence;',
                'use BlackOps\\Core\\Audit\\AuditRecordOutcome;',
                'use BlackOps\\Core\\Audit\\AuditRecordPolicy;',
                'use BlackOps\\Core\\Audit\\AuditRecordResource;',
                'use BlackOps\\Core\\Audit\\AuditRecordSource;',
                'use BlackOps\\Core\\Audit\\AuditSourceOwner;',
                'use BlackOps\\Core\\Audit\\AuditSourceRuntime;',
                'use BlackOps\\Core\\Audit\\AuditTypedReference;',
                'use BlackOps\\Core\\Audit\\AuditValidationException;',
                'use BlackOps\\Core\\Identifier\\OperationId;',
                'use BlackOps\\Core\\TenantRef;',
                'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog;',
                'use BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure;',
                'use Psr\\Clock\\ClockInterface;',
            ],
            'src/Internal/Audit/Read/OperationReadAuditSession.php' => [
                'use BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision;',
                'use BlackOps\\Core\\Audit\\AuditReadContext;',
                'use BlackOps\\Core\\Audit\\AuditReadRequestBasis;',
                'use BlackOps\\Core\\Audit\\AuditRecordDraft;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure;',
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php' => [
                'use BlackOps\\Core\\ActorRef;',
                'use BlackOps\\Core\\Identifier\\OperationId;',
                'use BlackOps\\Core\\TenantRef;',
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php' => [
                'use BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision;',
            ],
            'src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php' => [
                'use BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision;',
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                'use BlackOps\\Core\\Audit\\AuditActionPhase;',
                'use BlackOps\\Core\\Audit\\AuditDelivery;',
                'use BlackOps\\Core\\Audit\\AuditEventCategory;',
                'use BlackOps\\Core\\Audit\\AuditOutcomeStatus;',
                'use BlackOps\\Core\\Audit\\AuditPolicyDecision;',
                'use BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision;',
                'use BlackOps\\Core\\Audit\\AuditReadContext;',
                'use BlackOps\\Core\\Audit\\AuditReadRequestBasis;',
                'use BlackOps\\Core\\Audit\\AuditRecordDraft;',
                'use BlackOps\\Core\\Audit\\AuditSourceOwner;',
                'use BlackOps\\Core\\Audit\\AuditValidationException;',
                'use BlackOps\\Internal\\Audit\\Read\\AuditReadEventWriter;',
            ],
        ];
    }

    /** @return array<string,array{name:string,kind:string,modifiers:list<string>,extends:list<string>,implements:list<string>}> */
    private function d2ExpectedDeclarations(): array
    {
        return [
            'src/Internal/Audit/Read/AuditReadEventWriter.php' => [
                'name' => 'BlackOps\\Internal\\Audit\\Read\\AuditReadEventWriter',
                'kind' => 'interface',
                'modifiers' => [],
                'extends' => [],
                'implements' => [],
            ],
            'src/Internal/Audit/Read/OperationReadNonceSource.php' => [
                'name' => 'BlackOps\\Internal\\Audit\\Read\\OperationReadNonceSource',
                'kind' => 'interface',
                'modifiers' => [],
                'extends' => [],
                'implements' => [],
            ],
            'src/Internal/Audit/Read/RandomOperationReadNonceSource.php' => [
                'name' => 'BlackOps\\Internal\\Audit\\Read\\RandomOperationReadNonceSource',
                'kind' => 'class',
                'modifiers' => ['final', 'readonly'],
                'extends' => [],
                'implements' => ['BlackOps\\Internal\\Audit\\Read\\OperationReadNonceSource'],
            ],
            'src/Internal/Audit/Read/OperationReadExecutionSource.php' => [
                'name' => 'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
                'kind' => 'interface',
                'modifiers' => [],
                'extends' => [],
                'implements' => [],
            ],
            'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => [
                'name' => 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
                'kind' => 'class',
                'modifiers' => ['final'],
                'extends' => [],
                'implements' => [],
            ],
            'src/Internal/Audit/Read/OperationReadAuditSession.php' => [
                'name' => 'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                'kind' => 'class',
                'modifiers' => ['final'],
                'extends' => [],
                'implements' => [],
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php' => [
                'name' => 'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizationRequest',
                'kind' => 'class',
                'modifiers' => ['final'],
                'extends' => [],
                'implements' => [],
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php' => [
                'name' => 'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizer',
                'kind' => 'interface',
                'modifiers' => [],
                'extends' => [],
                'implements' => [],
            ],
            'src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php' => [
                'name' => 'BlackOps\\Internal\\Diagnostics\\UnavailableOperationDiagnosticsAuthorizer',
                'kind' => 'class',
                'modifiers' => ['final', 'readonly'],
                'extends' => [],
                'implements' => ['BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizer'],
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                'name' => 'BlackOps\\Internal\\Audit\\Orchestration\\AuditProtectedReadGate',
                'kind' => 'class',
                'modifiers' => ['final', 'readonly'],
                'extends' => [],
                'implements' => ['BlackOps\\Internal\\Audit\\Read\\AuditReadEventWriter'],
            ],
        ];
    }

    /** @return array<string,list<string>> */
    private function d2ExpectedCallables(): array
    {
        return [
            'src/Internal/Audit/Read/AuditReadEventWriter.php' => ['public:requested', 'public:terminal'],
            'src/Internal/Audit/Read/OperationReadNonceSource.php' => ['public:generate'],
            'src/Internal/Audit/Read/RandomOperationReadNonceSource.php' => ['public:generate'],
            'src/Internal/Audit/Read/OperationReadExecutionSource.php' => ['public:current'],
            'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => [
                'public:__construct',
                'public:__debugInfo',
                'public:__serialize',
                'public:__unserialize',
                'public:begin',
                'private:component',
                'private:definition',
                'private:draft',
                'private:execution',
                'private:nonce',
                'private:reference',
                'private:requestedAt',
                'private:resourceId',
                'private:value',
            ],
            'src/Internal/Audit/Read/OperationReadAuditSession.php' => [
                'private:__construct',
                'public:__clone',
                'public:__debugInfo',
                'public:__serialize',
                'public:__unserialize',
                'public:accessed',
                'public static:open',
                'public:unavailable',
                'private:terminal',
                'private:value',
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php' => [
                'public:__construct',
                'public:__debugInfo',
                'public:__serialize',
                'public:__unserialize',
                'public:currentActor',
                'public:currentTenant',
                'public:operationId',
                'private:value',
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php' => ['public:decide'],
            'src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php' => ['public:decide'],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                'public:__construct',
                'public:requested',
                'public:terminal',
                'private:assertBinding',
            ],
        ];
    }

    /** @return array<string,list<string>> */
    private function d2ExpectedSignatures(): array
    {
        $s = static fn(string $visibility, string $header): string => $visibility . ':' . $header;

        return [
            'src/Internal/Audit/Read/AuditReadEventWriter.php' => [
                $s(
                    'public',
                    'functionrequested(#[\\SensitiveParameter]AuditReadRequestBasis$basis,#[\\SensitiveParameter]AuditRecordDraft$draft,):void',
                ),
                $s(
                    'public',
                    'functionterminal(#[\\SensitiveParameter]AuditReadContext$context,#[\\SensitiveParameter]AuditRecordDraft$draft,):AuditReadAuthorizationDecision',
                ),
            ],
            'src/Internal/Audit/Read/OperationReadNonceSource.php' => [
                $s('public', 'functiongenerate():string'),
            ],
            'src/Internal/Audit/Read/RandomOperationReadNonceSource.php' => [
                $s('public', 'functiongenerate():string'),
            ],
            'src/Internal/Audit/Read/OperationReadExecutionSource.php' => [
                $s('public', 'functioncurrent():AuditTypedReference'),
            ],
            'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => [
                $s(
                    'public',
                    'function__construct(#[\\SensitiveParameter]AuditReadEventWriter$writer,#[\\SensitiveParameter]HmacAuditOpaqueIdDeriver$opaqueIds,#[\\SensitiveParameter]OperationReadNonceSource$nonces,#[\\SensitiveParameter]ClockInterface$clock,#[\\SensitiveParameter]OperationReadExecutionSource$executions,AuditSourceRuntime$runtime,#[\\SensitiveParameter]string$buildId,)',
                ),
                $s(
                    'public',
                    'functionbegin(AuditReadPurpose$purpose,#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?ActorRef$currentActor,#[\\SensitiveParameter]?TenantRef$currentTenant,):OperationReadAuditSession',
                ),
                $s('public', 'function__debugInfo():array'),
                $s('public', 'function__serialize():array'),
                $s('public', 'function__unserialize(#[\\SensitiveParameter]array$data):void'),
                $s('private', 'functionvalue(#[\\SensitiveParameter]string$key):mixed'),
                $s(
                    'private',
                    'functionreference(#[\\SensitiveParameter]ActorRef|TenantRef|null$reference):?AuditTypedReference',
                ),
                $s('private', 'functionexecution():AuditTypedReference'),
                $s('private', 'functionresourceId(#[\\SensitiveParameter]OperationId$operationId):string'),
                $s('private', 'functionrequestedAt():string'),
                $s('private', 'functionnonce():string'),
                $s(
                    'private',
                    'functiondefinition(AuditEventCatalog$catalog,AuditReadPurpose$purpose,string$phase,):AuditEventDefinition',
                ),
                $s(
                    'private',
                    'functiondraft(AuditEventCatalog$catalog,AuditEventDefinition$definition,#[\\SensitiveParameter]AuditReadRequestBasis$basis,#[\\SensitiveParameter]string$resourceId,):AuditRecordDraft',
                ),
                $s('private', 'functioncomponent(AuditReadPurpose$purpose):string'),
            ],
            'src/Internal/Audit/Read/OperationReadAuditSession.php' => [
                $s(
                    'private',
                    'function__construct(#[\\SensitiveParameter]AuditReadEventWriter$writer,#[\\SensitiveParameter]AuditReadRequestBasis$basis,#[\\SensitiveParameter]AuditRecordDraft$accessed,#[\\SensitiveParameter]AuditRecordDraft$unavailable,)',
                ),
                $s(
                    'public static',
                    'functionopen(#[\\SensitiveParameter]AuditReadEventWriter$writer,#[\\SensitiveParameter]AuditReadRequestBasis$basis,#[\\SensitiveParameter]AuditRecordDraft$requested,#[\\SensitiveParameter]AuditRecordDraft$accessed,#[\\SensitiveParameter]AuditRecordDraft$unavailable,):self',
                ),
                $s('public', 'functionaccessed():void'),
                $s('public', 'functionunavailable():void'),
                $s('public', 'function__clone():void'),
                $s('public', 'function__debugInfo():array'),
                $s('public', 'function__serialize():array'),
                $s('public', 'function__unserialize(#[\\SensitiveParameter]array$data):void'),
                $s(
                    'private',
                    'functionterminal(AuditReadAuthorizationDecision$decision,#[\\SensitiveParameter]string$draftKey):void',
                ),
                $s('private', 'functionvalue(#[\\SensitiveParameter]string$key):mixed'),
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php' => [
                $s(
                    'public',
                    'function__construct(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?ActorRef$currentActor,#[\\SensitiveParameter]?TenantRef$currentTenant,)',
                ),
                $s('public', 'functionoperationId():OperationId'),
                $s('public', 'functioncurrentActor():?ActorRef'),
                $s('public', 'functioncurrentTenant():?TenantRef'),
                $s('public', 'function__debugInfo():array'),
                $s('public', 'function__serialize():array'),
                $s('public', 'function__unserialize(#[\\SensitiveParameter]array$data):void'),
                $s('private', 'functionvalue(#[\\SensitiveParameter]string$key):mixed'),
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php' => [
                $s(
                    'public',
                    'functiondecide(#[\\SensitiveParameter]OperationDiagnosticsAuthorizationRequest$request,):AuditReadAuthorizationDecision',
                ),
            ],
            'src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php' => [
                $s(
                    'public',
                    'functiondecide(#[\\SensitiveParameter]OperationDiagnosticsAuthorizationRequest$request,):AuditReadAuthorizationDecision',
                ),
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                $s('public', 'function__construct(privateAuditDeliveryCoordinator$delivery,)'),
                $s(
                    'public',
                    'functionrequested(#[\\SensitiveParameter]AuditReadRequestBasis$basis,#[\\SensitiveParameter]AuditRecordDraft$draft,):void',
                ),
                $s(
                    'public',
                    'functionterminal(#[\\SensitiveParameter]AuditReadContext$context,#[\\SensitiveParameter]AuditRecordDraft$draft,):AuditReadAuthorizationDecision',
                ),
                $s(
                    'private',
                    'functionassertBinding(#[\\SensitiveParameter]AuditReadRequestBasis$basis,#[\\SensitiveParameter]AuditRecordDraft$draft,string$phase,):void',
                ),
            ],
        ];
    }

    /** @return array<string,list<string>> */
    private function d2ExpectedProperties(): array
    {
        return [
            'src/Internal/Audit/Read/OperationReadAuditSessionFactory.php' => [
                'private\\SensitiveParameterValue$snapshot',
            ],
            'src/Internal/Audit/Read/OperationReadAuditSession.php' => [
                'private\\SensitiveParameterValue$snapshot',
                'privatebool$terminalAttempted=false',
            ],
            'src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php' => [
                'private\\SensitiveParameterValue$snapshot',
            ],
        ];
    }

    /** @return list<string> */
    private function d2SignatureInventory(string $source): array
    {
        $tokens = $this->d2SignificantTokens($source);
        $inventory = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $name = $this->d2NextToken($tokens, $index + 1);
            if ($name !== null && $tokens[$name] === '&') {
                $name = $this->d2NextToken($tokens, $name + 1);
            }
            if ($name === null || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING) {
                continue;
            }
            $visibility = 'public';
            $static = false;
            $final = false;
            for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
                if ($tokens[$cursor] === ';' || $tokens[$cursor] === '}' || $tokens[$cursor] === '{') {
                    break;
                }
                if (is_array($tokens[$cursor])) {
                    $visibility = match ($tokens[$cursor][0]) {
                        T_PRIVATE => 'private',
                        T_PROTECTED => 'protected',
                        T_PUBLIC => 'public',
                        default => $visibility,
                    };
                    if ($tokens[$cursor][0] === T_STATIC) {
                        $static = true;
                    }
                    if ($tokens[$cursor][0] === T_FINAL) {
                        $final = true;
                    }
                }
            }
            $header = '';
            $parentheses = 0;
            for ($cursor = $index; $cursor < count($tokens); $cursor++) {
                $part = $tokens[$cursor];
                $header .= is_array($part) ? $part[1] : $part;
                if ($part === '(') {
                    $parentheses++;
                } elseif ($part === ')') {
                    $parentheses--;
                } elseif (($part === '{' || $part === ';') && $parentheses === 0) {
                    $header = substr($header, 0, -1);
                    break;
                }
            }
            $inventory[] = $visibility . ($static ? ' static' : '') . ($final ? ' final' : '') . ':' . $header;
        }
        sort($inventory);

        return $inventory;
    }

    /** @return list<string> */
    private function d2PropertyInventory(string $source): array
    {
        $tokens = $this->d2SignificantTokens($source);
        $classOpen = null;
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT], true)) {
                $cursor = $this->d2NextToken($tokens, $index + 1);
                while ($cursor !== null && $tokens[$cursor] !== '{' && $tokens[$cursor] !== ';') {
                    $cursor = $this->d2NextToken($tokens, $cursor + 1);
                }
                if ($cursor !== null && $tokens[$cursor] === '{') {
                    $classOpen = $cursor;
                }
                break;
            }
        }
        if ($classOpen === null) {
            return [];
        }
        $properties = [];
        $statement = [];
        $inMethod = false;
        $methodDepth = null;
        $depth = 1;
        for ($index = $classOpen + 1; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($inMethod) {
                if ($token === '{') {
                    $depth++;
                    continue;
                }
                if ($token === '}') {
                    $depth--;
                    if ($depth === $methodDepth) {
                        $inMethod = false;
                        $methodDepth = null;
                    }
                    continue;
                }
                if ($token === ';') {
                    continue;
                }
                continue;
            }
            if ($token === '}') {
                $depth--;
                break;
            }
            if ($token === '{') {
                $depth++;
                continue;
            }
            if (is_array($token) && $token[0] === T_FUNCTION) {
                $statement = [];
                $inMethod = true;
                $methodDepth = $depth;
                continue;
            }
            if ($token === ';') {
                if ($statement !== [] && $this->d2PropertyStatement($statement)) {
                    $properties[] = $this->d2CompactTokens($statement);
                }
                $statement = [];
                continue;
            }
            $statement[] = $token;
        }
        sort($properties);

        return $properties;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $statement */
    private function d2PropertyStatement(array $statement): bool
    {
        foreach ($statement as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE) {
                return true;
            }
            if ($token === '(' || $token === ')') {
                return false;
            }
        }

        return false;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2CompactTokens(array $tokens): string
    {
        $value = '';
        foreach ($tokens as $token) {
            $value .= is_array($token) ? $token[1] : $token;
        }

        return $value;
    }

    /** @return array{namespace:string,uses:list<string>,aliases:array<string,string>,declarations:list<array{name:string,kind:string,modifiers:list<string>,extends:list<string>,implements:list<string>}>} */
    private function d2Metadata(string $source): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $uses = [];
        $aliases = [];
        $declarations = [];
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                }
                continue;
            }
            if ($depth === 0 && $token[0] === T_NAMESPACE) {
                $parts = [];
                for (
                    $cursor = $this->d2NextToken($tokens, $index + 1);
                    $cursor !== null;
                    $cursor = $this->d2NextToken($tokens, $cursor + 1)
                ) {
                    if ($tokens[$cursor] === ';' || $tokens[$cursor] === '{') {
                        break;
                    }
                    $parts[] = is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
                }
                $namespace = trim(implode('', $parts), '\\');
                continue;
            }
            if ($depth === 0 && $token[0] === T_USE) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next === null || $tokens[$next] === '(') {
                    continue;
                }
                $parts = [];
                for ($cursor = $next; $cursor !== null; $cursor = $this->d2NextToken($tokens, $cursor + 1)) {
                    if ($tokens[$cursor] === ';') {
                        break;
                    }
                    $parts[] = is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
                }
                $statement = preg_replace('/\s+/', '', implode('', $parts));
                self::assertIsString($statement);
                $uses[] = 'use ' . $statement . ';';
                foreach ($this->d2ParseUse($statement) as [$alias, $name]) {
                    $aliases[strtolower($alias)] = $name;
                }
                continue;
            }
            if ($depth !== 0 || !in_array($token[0], [T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT], true)) {
                continue;
            }
            $nameIndex = $this->d2NextToken($tokens, $index + 1);
            if ($nameIndex === null || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) {
                continue;
            }
            $kind = match ($token[0]) {
                T_INTERFACE => 'interface',
                T_ENUM => 'enum',
                T_TRAIT => 'trait',
                default => 'class',
            };
            $modifiers = [];
            for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
                if ($tokens[$cursor] === ';' || $tokens[$cursor] === '}') {
                    break;
                }
                if (
                    is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], [T_FINAL, T_READONLY, T_ABSTRACT], true)
                ) {
                    $modifiers[] = strtolower($tokens[$cursor][1]);
                }
            }
            sort($modifiers);
            $extends = [];
            $implements = [];
            for (
                $cursor = $this->d2NextToken($tokens, $nameIndex + 1);
                $cursor !== null;
                $cursor = $this->d2NextToken($tokens, $cursor + 1)
            ) {
                if ($tokens[$cursor] === '{' || $tokens[$cursor] === ';') {
                    break;
                }
                if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_EXTENDS) {
                    for (
                        $member = $this->d2NextToken($tokens, $cursor + 1);
                        $member !== null;
                        $member = $this->d2NextToken($tokens, $member + 1)
                    ) {
                        if ($tokens[$member] === '{' || $tokens[$member] === ';') {
                            break;
                        }
                        if (is_array($tokens[$member]) && $tokens[$member][0] === T_IMPLEMENTS) {
                            break;
                        }
                        if ($tokens[$member] === ',') {
                            continue;
                        }
                        $nameParts = $this->d2NameParts($tokens, $member);
                        if ($nameParts[0] !== '') {
                            $extends[] = $this->d2ResolveName($nameParts[0], $namespace, $aliases);
                            $member = $nameParts[1];
                        }
                    }
                    continue;
                }
                if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_IMPLEMENTS) {
                    for (
                        $member = $this->d2NextToken($tokens, $cursor + 1);
                        $member !== null;
                        $member = $this->d2NextToken($tokens, $member + 1)
                    ) {
                        if ($tokens[$member] === '{' || $tokens[$member] === ';') {
                            break;
                        }
                        if ($tokens[$member] === ',') {
                            continue;
                        }
                        $nameParts = $this->d2NameParts($tokens, $member);
                        if ($nameParts[0] !== '') {
                            $implements[] = $this->d2ResolveName($nameParts[0], $namespace, $aliases);
                            $member = $nameParts[1];
                        }
                    }
                    break;
                }
            }
            $declarations[] = [
                'name' => $namespace === '' ? $tokens[$nameIndex][1] : $namespace . '\\' . $tokens[$nameIndex][1],
                'kind' => $kind,
                'modifiers' => $modifiers,
                'extends' => $extends,
                'implements' => $implements,
            ];
        }
        return ['namespace' => $namespace, 'uses' => $uses, 'aliases' => $aliases, 'declarations' => $declarations];
    }

    /** @return list<array{0:string,1:string}> */
    private function d2ParseUse(string $statement): array
    {
        $body = $statement;
        if (str_starts_with($body, 'function')) {
            $body = substr($body, strlen('function'));
        } elseif (str_starts_with($body, 'const')) {
            $body = substr($body, strlen('const'));
        }
        $body = trim($body, ';');
        $imports = [];
        if (preg_match('/^(.+)\\\{(.+)\}$/', $body, $matches) === 1) {
            $prefix = trim($matches[1], '\\');
            foreach (explode(',', $matches[2]) as $member) {
                $parts = preg_split('/as/i', $member, 2);
                self::assertIsArray($parts);
                $name = trim($prefix . '\\' . trim($parts[0]), '\\');
                $alias = trim((string) ($parts[1] ?? $this->d2TerminalName($name)));
                $imports[] = [$alias, $name];
            }
            return $imports;
        }
        foreach (explode(',', $body) as $member) {
            $parts = preg_split('/as/i', $member, 2);
            self::assertIsArray($parts);
            $name = trim((string) $parts[0], '\\');
            if ($name === '') {
                continue;
            }
            $imports[] = [trim((string) ($parts[1] ?? $this->d2TerminalName($name))), $name];
        }

        return $imports;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2NextToken(array $tokens, int $index): ?int
    {
        for (; $index < count($tokens); $index++) {
            if (
                is_array($tokens[$index])
                && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2PreviousToken(array $tokens, int $index): ?int
    {
        for (; $index >= 0; $index--) {
            if (
                is_array($tokens[$index])
                && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array{0:string,1:int} */
    private function d2NameParts(array $tokens, int $index): array
    {
        $parts = [];
        $cursor = $index;
        while ($cursor !== null) {
            $token = $tokens[$cursor];
            if (
                is_array($token)
                && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
            ) {
                $parts[] = $token[1];
                $cursor = $this->d2NextToken($tokens, $cursor + 1);
                continue;
            }
            if ($token === '\\') {
                $parts[] = '\\';
                $cursor = $this->d2NextToken($tokens, $cursor + 1);
                continue;
            }
            break;
        }

        return [implode('', $parts), $cursor === null ? count($tokens) : $cursor - 1];
    }

    /** @param array<string,string> $allSources @param list<string>|null $symbols @return array{project:list<string>,builtin:list<string>} */
    private function d2DependencyInventory(string $source, array $allSources, ?array $symbols = null): array
    {
        $metadata = $this->d2Metadata($source);
        $symbols ??= $this->d2ProjectSymbols($allSources);
        $project = [];
        $builtin = [];
        $current = $metadata['declarations'][0]['name'] ?? '';
        foreach ($this->d2SignificantTokens($source) as $tokenIndex => $token) {
            if (!is_array($token) || !in_array($token[0], $this->d2NameTokenIds(), true)) {
                continue;
            }
            $raw = $token[1];
            if (in_array(strtolower(trim($raw, '\\')), ['self', 'static', 'parent', 'namespace'], true)) {
                continue;
            }
            $resolved = $this->d2ResolveName($raw, $metadata['namespace'], $metadata['aliases']);
            if ($raw === $current || $resolved === $current) {
                continue;
            }
            if (in_array($resolved, $symbols, true)) {
                $project[] = $resolved;
                continue;
            }
            $terminal = $this->d2TerminalName($resolved);
            $matching = array_values(array_filter($symbols, static fn(string $name): bool => str_ends_with(
                $name,
                '\\' . $terminal,
            )));
            if (!str_contains($resolved, '\\') && count($matching) === 1) {
                $project[] = $matching[0];
                continue;
            }
            if (
                str_starts_with($resolved, 'Psr\\')
                || in_array(
                    $terminal,
                    ['DateTimeZone', 'LogicException', 'SensitiveParameter', 'SensitiveParameterValue', 'Throwable'],
                    true,
                )
                || str_starts_with($resolved, 'Doctrine\\')
                || str_starts_with($resolved, 'Reflection')
                || $terminal === 'DateTimeImmutable'
            ) {
                $builtin[] = str_starts_with($resolved, 'Psr\\') ? $resolved : $terminal;
                continue;
            }
            if (str_contains($resolved, '\\') && !str_starts_with($resolved, 'BlackOps\\')) {
                $builtin[] = $resolved;
            }
        }
        $project = array_values(array_unique($project));
        $builtin = array_values(array_unique($builtin));
        sort($project);
        sort($builtin);

        return ['project' => $project, 'builtin' => $builtin];
    }

    /** @param list<string> $symbols @return list<string> */
    private function d2UnknownDependencyReferences(string $source, array $symbols): array
    {
        $metadata = $this->d2Metadata($source);
        $tokens = $this->d2SignificantTokens($source);
        $unknown = [];
        $allowed = [
            'array',
            'backedenum',
            'bool',
            'callable',
            'date_time_immutable',
            'datetimeimmutable',
            'datetimezone',
            'false',
            'float',
            'int',
            'iterable',
            'logicexception',
            'mixed',
            'never',
            'null',
            'object',
            'self',
            'sensitiveparameter',
            'sensitiveparametervalue',
            'static',
            'string',
            'throwable',
            'true',
            'unitenum',
            'void',
        ];
        $add = function (int $index) use (&$add, &$unknown, $allowed, $metadata, $symbols, $tokens): void {
            if (
                !isset($tokens[$index])
                || !is_array($tokens[$index])
                || !in_array($tokens[$index][0], $this->d2NameTokenIds(), true)
            ) {
                return;
            }
            [$raw] = $this->d2NameParts($tokens, $index);
            $resolved = $this->d2ResolveName($raw, $metadata['namespace'], $metadata['aliases']);
            $terminal = strtolower($this->d2TerminalName($resolved));
            if (in_array(strtolower(trim($raw, '\\')), $allowed, true) || in_array($terminal, $allowed, true)) {
                return;
            }
            if (in_array($resolved, $symbols, true)) {
                return;
            }
            $terminalName = $this->d2TerminalName($resolved);
            $matching = array_values(array_filter($symbols, static fn(string $name): bool => str_ends_with(
                $name,
                '\\' . $terminalName,
            )));
            if (
                count($matching) === 1
                && (!str_contains(trim($raw, '\\'), '\\') || str_starts_with($resolved, $metadata['namespace'] . '\\'))
            ) {
                return;
            }
            if (str_contains($resolved, '\\') && !str_starts_with($resolved, 'BlackOps\\')) {
                return;
            }
            $unknown[] = $resolved;
        };

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_NEW, T_INSTANCEOF], true)) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next !== null) {
                    $add($next);
                }
                continue;
            }
            if (in_array($token[0], [T_EXTENDS, T_IMPLEMENTS], true)) {
                for ($cursor = $this->d2NextToken($tokens, $index + 1); $cursor !== null;) {
                    if ($tokens[$cursor] === '{' || $tokens[$cursor] === ';') {
                        break;
                    }
                    if ($tokens[$cursor] === ',') {
                        $cursor = $this->d2NextToken($tokens, $cursor + 1);
                        continue;
                    }
                    if (is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], $this->d2NameTokenIds(), true)) {
                        $add($cursor);
                        [, $last] = $this->d2NameParts($tokens, $cursor);
                        $cursor = $this->d2NextToken($tokens, $last + 1);
                        continue;
                    }
                    $cursor = $this->d2NextToken($tokens, $cursor + 1);
                }
                continue;
            }
            if ($token[0] === T_CATCH) {
                $opening = $this->d2NextToken($tokens, $index + 1);
                $closing = $opening === null ? null : $this->d2MatchingParenthesis($tokens, $opening);
                if ($opening === null || $closing === null) {
                    continue;
                }
                for ($cursor = $this->d2NextToken($tokens, $opening + 1); $cursor !== null && $cursor < $closing;) {
                    if (is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], $this->d2NameTokenIds(), true)) {
                        $add($cursor);
                        [, $last] = $this->d2NameParts($tokens, $cursor);
                        $cursor = $this->d2NextToken($tokens, $last + 1);
                        continue;
                    }
                    $cursor = $this->d2NextToken($tokens, $cursor + 1);
                }
                continue;
            }
            if (!in_array($token[0], $this->d2NameTokenIds(), true)) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            $previous = $this->d2PreviousToken($tokens, $index - 1);
            $nextValue = $next === null ? null : $tokens[$next];
            $previousValue = $previous === null ? null : $tokens[$previous];
            if (
                $nextValue === '$'
                || is_array($nextValue) && $nextValue[0] === T_VARIABLE
                || in_array($nextValue, ['|', '&', '?'], true)
                || in_array($previousValue, [':', '|', '&', '?'], true)
            ) {
                $add($index);
                continue;
            }
            if ($next !== null && $tokens[$next] === T_DOUBLE_COLON) {
                $class = $this->d2NextToken($tokens, $next + 1);
                if (
                    $class !== null
                    && is_array($tokens[$class])
                    && in_array($tokens[$class][0], [T_CLASS, defined('T_CLASS_C') ? constant('T_CLASS_C') : -1], true)
                ) {
                    $add($index);
                }
            }
        }

        $unknown = array_values(array_unique($unknown));
        sort($unknown);

        return $unknown;
    }

    /** @param list<string> $targets @param list<string> $symbols @return list<string> */
    private function d2TargetReferences(string $source, array $targets, array $symbols): array
    {
        $metadata = $this->d2Metadata($source);
        $references = [];
        $tokens = $this->d2SignificantTokens($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], $this->d2NameTokenIds(), true)) {
                continue;
            }
            $resolved = $this->d2ResolveName($token[1], $metadata['namespace'], $metadata['aliases']);
            if (!in_array($resolved, $symbols, true) && !str_starts_with($resolved, 'BlackOps\\')) {
                $terminal = $this->d2TerminalName($resolved);
                $matching = array_values(array_filter($symbols, static fn(string $name): bool => str_ends_with(
                    $name,
                    '\\' . $terminal,
                )));
                if (!str_contains($resolved, '\\') && count($matching) === 1) {
                    $resolved = $matching[0];
                }
            }
            if (in_array($resolved, $targets, true)) {
                $references[] = $resolved;
            }
        }
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @param list<string> $targets @return list<string> */
    private function d2ConsumedTargetReferences(string $source, array $targets): array
    {
        $tokens = token_get_all($source);
        $metadata = $this->d2Metadata($source);
        $assignments = $this->d2ClassStringAssignments($tokens, $metadata);
        $callableAssignments = $this->d2CallableArrayAssignments($tokens, $metadata, $assignments);
        $references = [];
        $sinks = [
            'class_exists',
            'enum_exists',
            'interface_exists',
            'is_a',
            'is_subclass_of',
            'call_user_func',
            'call_user_func_array',
            'fromcallable',
            'reflectionclass',
            'reflectionmethod',
            'array_map',
            'array_filter',
            'array_reduce',
            'array_walk',
            'array_walk_recursive',
            'array_udiff',
            'array_udiff_assoc',
            'array_udiff_uassoc',
            'array_uintersect',
            'array_uintersect_assoc',
            'array_uintersect_uassoc',
            'array_diff_uassoc',
            'array_diff_ukey',
            'array_intersect_uassoc',
            'array_intersect_ukey',
            'iterator_apply',
            'preg_replace_callback',
            'preg_replace_callback_array',
            'register_shutdown_function',
            'set_error_handler',
            'set_exception_handler',
            'uasort',
            'uksort',
            'usort',
        ];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], $this->d2NameTokenIds(), true)) {
                continue;
            }
            $name = strtolower($this->d2TerminalName(trim($token[1], '\\')));
            if (!in_array($name, $sinks, true)) {
                continue;
            }
            $opening = $this->d2NextToken($tokens, $index + 1);
            if ($opening === null || $tokens[$opening] !== '(') {
                continue;
            }
            $closing = $this->d2MatchingParenthesis($tokens, $opening);
            if ($closing === null) {
                continue;
            }
            $ranges = $this->d2ArgumentRanges($tokens, $opening, $closing);
            foreach ($this->d2ConsumedArgumentPositions($name, count($ranges)) as $position) {
                if (!isset($ranges[$position])) {
                    continue;
                }
                [$argumentStart, $argumentEnd] = $ranges[$position];
                for ($argument = $argumentStart; $argument <= $argumentEnd; $argument++) {
                    $value = $this->d2ConsumedClassStringValue($tokens, $argument, $metadata, $assignments);
                    $target = $this->d2ConsumedTargetName($value, $targets);
                    if ($target !== null) {
                        $references[] = $target;
                    }
                    if (
                        is_array($tokens[$argument] ?? null)
                        && $tokens[$argument][0] === T_VARIABLE
                        && in_array($tokens[$argument][1], array_keys($callableAssignments), true)
                    ) {
                        foreach ($callableAssignments[$tokens[$argument][1]] as $callableTarget) {
                            $target = $this->d2ConsumedTargetName($callableTarget, $targets);
                            if ($target !== null) {
                                $references[] = $target;
                            }
                        }
                        foreach ($this->d2CallableTargetsInExpression(
                            $tokens,
                            $argument,
                            $metadata,
                            $assignments,
                            $callableAssignments,
                            $targets,
                        ) as $target) {
                            $references[] = $target;
                        }
                    }
                }
            }
        }

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_NEW) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if (
                    $next !== null
                    && is_array($tokens[$next])
                    && $tokens[$next][0] === T_VARIABLE
                    && ($value = $this->d2ResolvedClassStringAssignment($tokens[$next][1], $assignments)) !== null
                    && ($target = $this->d2ConsumedTargetName($value, $targets)) !== null
                ) {
                    $references[] = $target;
                }
            }
            if ($token[0] !== T_VARIABLE) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if ($next !== null && $tokens[$next] === '(') {
                if (isset($callableAssignments[$token[1]])) {
                    foreach ($callableAssignments[$token[1]] as $callableTarget) {
                        $target = $this->d2ConsumedTargetName($callableTarget, $targets);
                        if ($target !== null) {
                            $references[] = $target;
                        }
                    }
                } else {
                    $target = $this->d2ConsumedTargetName(
                        $this->d2ResolvedClassStringAssignment($token[1], $assignments),
                        $targets,
                    );
                    if ($target !== null) {
                        $references[] = $target;
                    }
                }
            }
            if ($next !== null && $tokens[$next] === ')') {
                $opening = $this->d2NextToken($tokens, $next + 1);
                if ($opening !== null && $tokens[$opening] === '(') {
                    if (isset($callableAssignments[$token[1]])) {
                        foreach ($callableAssignments[$token[1]] as $callableTarget) {
                            $target = $this->d2ConsumedTargetName($callableTarget, $targets);
                            if ($target !== null) {
                                $references[] = $target;
                            }
                        }
                    } else {
                        $value = $this->d2ResolvedClassStringAssignment($token[1], $assignments);
                        $target = $this->d2ConsumedTargetName($value, $targets);
                        if ($target !== null) {
                            $references[] = $target;
                        }
                    }
                }
            }
            if ($next === null || $this->d2TokenId($tokens[$next]) !== T_DOUBLE_COLON) {
                continue;
            }
            $value = $this->d2ResolvedClassStringAssignment($token[1], $assignments);
            $target = $this->d2ConsumedTargetName($value, $targets);
            if ($target !== null) {
                $references[] = $target;
            }
        }

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $closing = $this->d2NextToken($tokens, $index + 1);
            $opening = $closing === null ? null : $this->d2NextToken($tokens, $closing + 1);
            if ($closing === null || $opening === null || $tokens[$closing] !== ')' || $tokens[$opening] !== '(') {
                continue;
            }
            $target = $this->d2ConsumedTargetName(
                $this->d2ClassStringValue($tokens, $index, $metadata['namespace'], $metadata['aliases']),
                $targets,
            );
            if ($target !== null) {
                $references[] = $target;
            }
        }

        foreach ($tokens as $index => $token) {
            if ($token !== '[') {
                continue;
            }
            $closing = $this->d2MatchingBracket($tokens, $index);
            if ($closing === null) {
                continue;
            }
            $after = $this->d2NextToken($tokens, $closing + 1);
            $invoked = $after !== null && $tokens[$after] === '(';
            if (!$invoked && $after !== null && $tokens[$after] === ')') {
                $callOpening = $this->d2NextToken($tokens, $after + 1);
                $invoked = $callOpening !== null && $tokens[$callOpening] === '(';
            }
            if (!$invoked) {
                continue;
            }
            for ($member = $index + 1; $member < $closing; $member++) {
                $value = $this->d2ConsumedClassStringValue($tokens, $member, $metadata, $assignments);
                $target = $this->d2ConsumedTargetName($value, $targets);
                if ($target !== null) {
                    $references[] = $target;
                }
            }
        }

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || strtolower($token[1]) !== 'array') {
                continue;
            }
            $opening = $this->d2NextToken($tokens, $index + 1);
            if ($opening === null || $tokens[$opening] !== '(') {
                continue;
            }
            $closing = $this->d2MatchingParenthesis($tokens, $opening);
            $after = $closing === null ? null : $this->d2NextToken($tokens, $closing + 1);
            if ($closing === null || $after === null || $tokens[$after] !== '(') {
                continue;
            }
            for ($member = $opening + 1; $member < $closing; $member++) {
                $value = $this->d2ConsumedClassStringValue($tokens, $member, $metadata, $assignments);
                $target = $this->d2ConsumedTargetName($value, $targets);
                if ($target !== null) {
                    $references[] = $target;
                }
            }
        }

        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return list<array{0:int,1:int}> */
    private function d2ArgumentRanges(array $tokens, int $opening, int $closing): array
    {
        $first = $this->d2NextToken($tokens, $opening + 1);
        if ($first === null || $first >= $closing) {
            return [];
        }
        $ranges = [];
        $start = $first;
        $parentheses = 0;
        $brackets = 0;
        $braces = 0;
        for ($index = $first; $index < $closing; $index++) {
            $token = $tokens[$index];
            if ($token === '(') {
                $parentheses++;
            } elseif ($token === ')') {
                $parentheses--;
            } elseif ($token === '[') {
                $brackets++;
            } elseif ($token === ']') {
                $brackets--;
            } elseif ($token === '{') {
                $braces++;
            } elseif ($token === '}') {
                $braces--;
            }
            if ($token !== ',' || $parentheses !== 0 || $brackets !== 0 || $braces !== 0) {
                continue;
            }
            $end = $this->d2PreviousToken($tokens, $index - 1);
            if ($end !== null && $end >= $start) {
                $ranges[] = [$start, $end];
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if ($next === null || $next >= $closing) {
                return $ranges;
            }
            $start = $next;
        }
        $end = $this->d2PreviousToken($tokens, $closing - 1);
        if ($end !== null && $end >= $start) {
            $ranges[] = [$start, $end];
        }

        return $ranges;
    }

    /** @return list<int> */
    private function d2ConsumedArgumentPositions(string $name, int $argumentCount): array
    {
        if ($argumentCount === 0) {
            return [];
        }
        $last = $argumentCount - 1;

        return match ($name) {
            'class_exists',
            'enum_exists',
            'interface_exists',
            'call_user_func',
            'call_user_func_array',
            'fromcallable',
            'reflectionclass',
            'reflectionmethod',
            'array_map',
            'register_shutdown_function',
            'set_error_handler',
            'set_exception_handler',
                => [0],
            'is_a', 'is_subclass_of' => [0, 1],
            'array_filter',
            'array_reduce',
            'array_walk',
            'array_walk_recursive',
            'iterator_apply',
            'uasort',
            'uksort',
            'usort',
                => [1],
            'preg_replace_callback' => [1],
            'preg_replace_callback_array' => [0],
            'array_udiff',
            'array_udiff_assoc',
            'array_uintersect',
            'array_uintersect_assoc',
            'array_diff_uassoc',
            'array_diff_ukey',
            'array_intersect_uassoc',
            'array_intersect_ukey',
                => [$last],
            'array_udiff_uassoc', 'array_uintersect_uassoc' => [$last - 1, $last],
            default => [],
        };
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array{namespace:string,aliases:array<string,string>} $metadata @return array<string,string|array{0:string}|null> */
    private function d2ClassStringAssignments(array $tokens, array $metadata): array
    {
        $assignments = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }
            $equals = $this->d2NextToken($tokens, $index + 1);
            if ($equals === null || $tokens[$equals] !== '=') {
                continue;
            }
            $valueIndex = $this->d2NextToken($tokens, $equals + 1);
            if ($valueIndex === null) {
                continue;
            }
            $assignments[$token[1]] = $this->d2ClassStringValue(
                $tokens,
                $valueIndex,
                $metadata['namespace'],
                $metadata['aliases'],
            );
        }

        return $assignments;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array{namespace:string,aliases:array<string,string>} $metadata @param array<string,string|array{0:string}|null> $assignments @return array<string,list<string>> */
    private function d2CallableArrayAssignments(array $tokens, array $metadata, array $assignments): array
    {
        $callables = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }
            $equals = $this->d2NextToken($tokens, $index + 1);
            $value = $equals === null ? null : $this->d2NextToken($tokens, $equals + 1);
            if ($equals === null || $tokens[$equals] !== '=' || $value === null) {
                continue;
            }
            $opening = $value;
            $kind = 'bracket';
            if (is_array($tokens[$value]) && strtolower($tokens[$value][1]) === 'array') {
                $opening = $this->d2NextToken($tokens, $value + 1);
                $kind = 'parenthesis';
            }
            if ($opening === null || $tokens[$opening] !== '[' && $tokens[$opening] !== '(') {
                continue;
            }
            $closing = $kind === 'parenthesis'
                ? $this->d2MatchingParenthesis($tokens, $opening)
                : $this->d2MatchingBracket($tokens, $opening);
            if ($closing === null) {
                continue;
            }
            $values = [];
            for ($member = $opening + 1; $member < $closing; $member++) {
                $value = $this->d2ConsumedClassStringValue($tokens, $member, $metadata, $assignments);
                if (is_string($value)) {
                    $values[] = $value;
                }
            }
            if ($values !== []) {
                $callables[$token[1]] = array_values(array_unique($values));
            }
        }

        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($assignments as $variable => $value) {
                if (isset($callables[$variable]) || !is_array($value) || count($value) !== 1) {
                    continue;
                }
                $sourceVariable = $value[0];
                if (isset($callables[$sourceVariable])) {
                    $callables[$variable] = $callables[$sourceVariable];
                }
            }
        }

        return $callables;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array{namespace:string,aliases:array<string,string>} $metadata @param array<string,string|array{0:string}|null> $assignments @param array<string,list<string>> $callableAssignments @param list<string> $targets @return list<string> */
    private function d2CallableTargetsInExpression(
        array $tokens,
        int $index,
        array $metadata,
        array $assignments,
        array $callableAssignments,
        array $targets,
    ): array {
        $references = [];
        $value = $this->d2ConsumedClassStringValue($tokens, $index, $metadata, $assignments);
        $target = $this->d2ConsumedTargetName($value, $targets);
        if ($target !== null) {
            $references[] = $target;
        }
        $token = $tokens[$index] ?? null;
        if (is_array($token) && $token[0] === T_VARIABLE && isset($callableAssignments[$token[1]])) {
            foreach ($callableAssignments[$token[1]] as $callableTarget) {
                $target = $this->d2ConsumedTargetName($callableTarget, $targets);
                if ($target !== null) {
                    $references[] = $target;
                }
            }
        }
        $opening = null;
        $closing = null;
        if ($token === '[') {
            $opening = $index;
            $closing = $this->d2MatchingBracket($tokens, $index);
        } elseif (is_array($token) && $token[0] === T_ARRAY) {
            $opening = $this->d2NextToken($tokens, $index + 1);
            $closing = $opening === null ? null : $this->d2MatchingParenthesis($tokens, $opening);
        }
        if ($opening !== null && $closing !== null) {
            for ($member = $opening + 1; $member < $closing; $member++) {
                $value = $this->d2ConsumedClassStringValue($tokens, $member, $metadata, $assignments);
                $target = $this->d2ConsumedTargetName($value, $targets);
                if ($target !== null) {
                    $references[] = $target;
                }
            }
        }

        return array_values(array_unique($references));
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array<string,string> $aliases @return string|array{0:string}|null */
    private function d2ClassStringValue(array $tokens, int $index, string $namespace, array $aliases): string|array|null
    {
        $token = $tokens[$index] ?? null;
        if (!is_array($token)) {
            return null;
        }
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $quote = $token[1][0] ?? '';
            $value = substr($token[1], 1, -1);

            return (
                $quote === "'"
                    ? str_replace(['\\\\', "\\'"], ['\\', "'"], $value)
                    : str_replace(['\\\\', '\\"'], ['\\', '"'], $value)
            );
        }
        if ($token[0] === T_VARIABLE) {
            return [$token[1]];
        }
        if (in_array($token[0], $this->d2NameTokenIds(), true)) {
            $doubleColon = $this->d2NextToken($tokens, $index + 1);
            $classToken = $doubleColon === null ? null : $this->d2NextToken($tokens, $doubleColon + 1);
            $classTokenIds = [T_CLASS];
            if (defined('T_CLASS_C')) {
                $classTokenIds[] = constant('T_CLASS_C');
            }
            if (
                $doubleColon !== null
                && $this->d2TokenId($tokens[$doubleColon]) === T_DOUBLE_COLON
                && $classToken !== null
                && is_array($tokens[$classToken])
                && in_array($tokens[$classToken][0], $classTokenIds, true)
            ) {
                return $this->d2ResolveName($token[1], $namespace, $aliases);
            }
        }
        if ($token[0] === T_START_HEREDOC) {
            $value = '';
            for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_END_HEREDOC) {
                    break;
                }
                $value .= is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
            }

            return trim($value);
        }

        return null;
    }

    /** @param array<string,string|array{0:string}|null> $assignments */
    private function d2ResolvedClassStringAssignment(string $variable, array $assignments, int $depth = 0): ?string
    {
        if ($depth > 2 || !array_key_exists($variable, $assignments)) {
            return null;
        }
        $value = $assignments[$variable];
        if (is_array($value)) {
            return $this->d2ResolvedClassStringAssignment($value[0], $assignments, $depth + 1);
        }

        return is_string($value) ? trim($value, " \t\r\n\0\x0B\\") : null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array{namespace:string,aliases:array<string,string>} $metadata @param array<string,string|array{0:string}|null> $assignments */
    private function d2ConsumedClassStringValue(array $tokens, int $index, array $metadata, array $assignments): ?string
    {
        $value = $this->d2ClassStringValue($tokens, $index, $metadata['namespace'], $metadata['aliases']);
        if (is_array($value)) {
            $value = $this->d2ResolvedClassStringAssignment($value[0], $assignments);
        }

        return is_string($value) ? trim($value, " \t\r\n\0\x0B\\") : null;
    }

    /** @param list<string> $targets */
    private function d2ConsumedTargetName(?string $value, array $targets): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = trim($value, " \t\r\n\0\x0B\\");
        if (in_array($normalized, $targets, true)) {
            return $normalized;
        }
        $separator = strrpos($normalized, '::');
        if ($separator === false) {
            return null;
        }
        $owner = trim(substr($normalized, 0, $separator), " \t\r\n\0\x0B\\");

        return in_array($owner, $targets, true) ? $owner : null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2MatchingParenthesis(array $tokens, int $opening): ?int
    {
        $depth = 0;
        for ($index = $opening; $index < count($tokens); $index++) {
            if ($tokens[$index] === '(') {
                $depth++;
            } elseif ($tokens[$index] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2MatchingBracket(array $tokens, int $opening): ?int
    {
        $depth = 0;
        for ($index = $opening; $index < count($tokens); $index++) {
            if ($tokens[$index] === '[') {
                $depth++;
            } elseif ($tokens[$index] === ']') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /** @return list<int> */
    private function d2NameTokenIds(): array
    {
        return array_values(array_filter(
            [
                T_STRING,
                defined('T_NAME_QUALIFIED') ? constant('T_NAME_QUALIFIED') : null,
                defined('T_NAME_FULLY_QUALIFIED') ? constant('T_NAME_FULLY_QUALIFIED') : null,
                defined('T_NAME_RELATIVE') ? constant('T_NAME_RELATIVE') : null,
            ],
            static fn(mixed $id): bool => is_int($id),
        ));
    }

    private function d2ResolveName(string $name, string $namespace, array $aliases): string
    {
        $resolved = trim($name, '\\');
        if (str_starts_with(strtolower($resolved), 'namespace\\')) {
            $relative = substr($resolved, strlen('namespace\\'));

            return $namespace === '' ? $relative : $namespace . '\\' . $relative;
        }
        $parts = explode('\\', $resolved);
        $first = strtolower($parts[0]);
        if (array_key_exists($first, $aliases)) {
            $parts[0] = $aliases[$first];

            return implode('\\', $parts);
        }

        return !str_contains($resolved, '\\') && $namespace !== '' ? $namespace . '\\' . $resolved : $resolved;
    }

    private function d2TerminalName(string $name): string
    {
        $parts = explode('\\', trim($name, '\\'));

        return $parts[count($parts) - 1];
    }

    /** @return list<array{0:int,1:string,2:int}|string> */
    private function d2SignificantTokens(string $source): array
    {
        $tokens = [];
        foreach (token_get_all($source) as $token) {
            if (
                is_array($token)
                && in_array(
                    $token[0],
                    [
                        T_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT,
                        T_CONSTANT_ENCAPSED_STRING,
                        T_ENCAPSED_AND_WHITESPACE,
                        T_START_HEREDOC,
                        T_END_HEREDOC,
                        T_INLINE_HTML,
                    ],
                    true,
                )
            ) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /** @return list<string> */
    private function d2CallableInventory(string $source): array
    {
        $tokens = $this->d2SignificantTokens($source);
        $depth = 0;
        $inventory = [];
        foreach ($tokens as $index => $token) {
            if ($token === '{') {
                $depth++;
                continue;
            }
            if ($token === '}') {
                $depth--;
                continue;
            }
            if (!is_array($token) || $token[0] !== T_FUNCTION || $depth !== 1) {
                continue;
            }
            $name = $this->d2NextToken($tokens, $index + 1);
            if ($name !== null && $tokens[$name] === '&') {
                $name = $this->d2NextToken($tokens, $name + 1);
            }
            if ($name === null || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING) {
                continue;
            }
            $visibility = 'public';
            $static = false;
            for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
                if ($tokens[$cursor] === ';' || $tokens[$cursor] === '}') {
                    break;
                }
                if (
                    is_array($tokens[$cursor])
                    && in_array($tokens[$cursor][0], [T_PRIVATE, T_PROTECTED, T_PUBLIC], true)
                ) {
                    $visibility = strtolower($tokens[$cursor][1]);
                }
                if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_STATIC) {
                    $static = true;
                }
            }
            $inventory[] = $visibility . ($static ? ' static:' : ':') . $tokens[$name][1];
        }
        sort($inventory);

        return $inventory;
    }

    /** @return list<string> */
    private function d2ForbiddenExecutable(string $source, array $symbols): array
    {
        $tokens = $this->d2SignificantTokens($source);
        $metadata = $this->d2Metadata($source);
        $findings = [];
        $forbiddenGlobalCalls = [
            'checkdnsrr' => 'network',
            'closedir' => 'filesystem',
            'com_create_guid' => 'uuid',
            'copy' => 'filesystem',
            'curl_exec' => 'network',
            'curl_init' => 'network',
            'curl_multi_init' => 'network',
            'curl_setopt' => 'network',
            'dns_get_record' => 'network',
            'file_get_contents' => 'filesystem',
            'file_put_contents' => 'filesystem',
            'fclose' => 'filesystem',
            'fopen' => 'filesystem',
            'fread' => 'filesystem',
            'fwrite' => 'filesystem',
            'fsockopen' => 'network',
            'get_current_user' => 'environment',
            'getenv' => 'environment',
            'gethostbyname' => 'network',
            'gethostbynamel' => 'network',
            'glob' => 'filesystem',
            'opendir' => 'filesystem',
            'pfsockopen' => 'network',
            'putenv' => 'environment',
            'random_int' => 'uuid',
            'readfile' => 'filesystem',
            'readdir' => 'filesystem',
            'rename' => 'filesystem',
            'scandir' => 'filesystem',
            'socket_connect' => 'network',
            'socket_create' => 'network',
            'stream_socket_client' => 'network',
            'stream_socket_server' => 'network',
            'unlink' => 'filesystem',
            'uniqid' => 'uuid',
            'uuid_create' => 'uuid',
        ];
        foreach ($tokens as $index => $token) {
            if ($token === '{') {
                $previous = $this->d2PreviousToken($tokens, $index - 1);
                if (
                    $previous !== null
                    && in_array($this->d2TokenId($tokens[$previous]), [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)
                ) {
                    $findings[] = 'dynamic-method';
                }
            }
            if ($token === '[') {
                $next = $this->d2NextToken($tokens, $index + 1);
                $after = $next === null ? null : $this->d2NextToken($tokens, $next + 1);
                if (
                    $next !== null
                    && $after !== null
                    && $tokens[$after] === ','
                    && is_array($tokens[$next])
                    && $tokens[$next][0] === T_VARIABLE
                ) {
                    $findings[] = 'callable-array';
                }
                $closing = $this->d2MatchingBracket($tokens, $index);
                if ($closing !== null) {
                    $before = $this->d2PreviousToken($tokens, $index - 1);
                    $call = $before === null ? null : $this->d2PreviousToken($tokens, $before - 1);
                    $isCallUserFunc =
                        $before !== null
                        && $tokens[$before] === '('
                        && $call !== null
                        && is_array($tokens[$call])
                        && in_array($tokens[$call][1], ['call_user_func', 'call_user_func_array'], true);
                    $first = $this->d2NextToken($tokens, $index + 1);
                    $firstValue = $first === null
                        ? null
                        : $this->d2ClassStringValue($tokens, $first, $metadata['namespace'], $metadata['aliases']);
                    $firstIsTarget =
                        is_string($firstValue) && in_array(trim($firstValue, " \t\r\n\0\x0B\\"), $symbols, true);
                    if ($isCallUserFunc && $firstIsTarget) {
                        $findings[] = 'callable-array';
                    }
                }
            }
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_VARIABLE && in_array($token[1], ['$_ENV', '$_SERVER'], true)) {
                $findings[] = 'environment';
            }
            if ($token[0] === T_STRING) {
                $function = strtolower($token[1]);
                $next = $this->d2NextToken($tokens, $index + 1);
                $previous = $this->d2PreviousToken($tokens, $index - 1);
                $receiver =
                    $previous !== null
                    && in_array($this->d2TokenId($tokens[$previous]), [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
                if ($next !== null && $tokens[$next] === '(' && !$receiver) {
                    if (isset($forbiddenGlobalCalls[$function])) {
                        $findings[] = $forbiddenGlobalCalls[$function];
                    }
                    if ($function === 'random_bytes') {
                        $closing = $this->d2MatchingParenthesis($tokens, $next);
                        $ranges = $closing === null ? [] : $this->d2ArgumentRanges($tokens, $next, $closing);
                        if (
                            count($ranges) !== 1
                            || $this->d2CompactTokens(array_slice(
                                $tokens,
                                $ranges[0][0],
                                $ranges[0][1] - $ranges[0][0] + 1,
                            )) !== '16'
                        ) {
                            $findings[] = 'random-bytes-shape';
                        }
                    }
                } elseif ($function === 'random_bytes' && $next !== null && $tokens[$next] === '(') {
                    $findings[] = 'random-bytes-shape';
                }
            }
            if ($token[0] === T_FUNCTION) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next !== null && $tokens[$next] === '(') {
                    $findings[] = 'anonymous-function';
                }
            }
            if (defined('T_FN') && $token[0] === constant('T_FN')) {
                $findings[] = 'arrow-function';
            }
            if (
                $token[0] === T_STRING
                && in_array(strtolower($token[1]), ['call_user_func', 'call_user_func_array'], true)
            ) {
                $findings[] = 'callable-function';
            }
            if (
                in_array($token[0], $this->d2NameTokenIds(), true)
                && str_starts_with(trim($token[1], '\\'), 'Reflection')
            ) {
                $findings[] = 'reflection';
            }
            if ($token[0] === T_NEW) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                    $findings[] = 'variable-construction';
                }
            }
            if ($token[0] === T_VARIABLE) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next !== null && $tokens[$next] === '(') {
                    $findings[] = 'variable-call';
                }
                if ($next !== null && $this->d2TokenId($tokens[$next]) === T_DOUBLE_COLON) {
                    $findings[] = 'variable-static-call';
                }
            }
            if ($token[0] === T_DOUBLE_COLON) {
                $next = $this->d2NextToken($tokens, $index + 1);
                $classTokenIds = [T_CLASS];
                if (defined('T_CLASS_C')) {
                    $classTokenIds[] = constant('T_CLASS_C');
                }
                if ($next !== null && is_array($tokens[$next]) && in_array($tokens[$next][0], $classTokenIds, true)) {
                    $previous = $this->d2PreviousToken($tokens, $index - 1);
                    if (
                        $previous !== null
                        && is_array($tokens[$previous])
                        && in_array($tokens[$previous][0], $this->d2NameTokenIds(), true)
                    ) {
                        $raw = $tokens[$previous][1];
                        $resolved = $this->d2ResolveName($raw, '', []);
                        $terminal = $this->d2TerminalName($resolved);
                        $matching = array_values(array_filter($symbols, static fn(string $name): bool => str_ends_with(
                            $name,
                            '\\' . $terminal,
                        )));
                        if (
                            in_array($resolved, $symbols, true)
                            || !str_contains($resolved, '\\') && count($matching) === 1
                        ) {
                            $findings[] = 'class-string';
                        }
                    } else {
                        $findings[] = 'class-string';
                    }
                }
            }
            if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
                $findings[] = 'filesystem';
            }
        }

        return array_values(array_unique($findings));
    }

    /** @return list<array{0:int,1:string,2:int}|string> */
    private function d2MethodTokens(string $source, string $method): array
    {
        $tokens = $this->d2SignificantTokens($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $name = $this->d2NextToken($tokens, $index + 1);
            if ($name !== null && $tokens[$name] === '&') {
                $name = $this->d2NextToken($tokens, $name + 1);
            }
            if ($name === null || !is_array($tokens[$name]) || $tokens[$name][1] !== $method) {
                continue;
            }
            $open = $this->d2NextToken($tokens, $name + 1);
            while ($open !== null && $tokens[$open] !== '{' && $tokens[$open] !== ';') {
                $open = $this->d2NextToken($tokens, $open + 1);
            }
            if ($open === null || $tokens[$open] === ';') {
                return [];
            }
            $depth = 1;
            for ($cursor = $open + 1; $cursor < count($tokens); $cursor++) {
                if ($tokens[$cursor] === '{') {
                    $depth++;
                } elseif ($tokens[$cursor] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return array_slice($tokens, $open + 1, $cursor - $open - 1);
                    }
                }
            }
        }

        return [];
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2CallCount(array $tokens, string $method): int
    {
        $count = 0;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if (
                $next !== null
                && is_array($tokens[$next])
                && $tokens[$next][0] === T_STRING
                && $tokens[$next][1] === $method
            ) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2ObjectCallCount(array $tokens, string $method): int
    {
        $count = 0;
        foreach ($tokens as $index => $token) {
            if ($this->d2TokenId($token) !== T_OBJECT_OPERATOR) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if ($next !== null && is_array($tokens[$next]) && $tokens[$next][1] === $method) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2ObjectCallCountOnReceiver(array $tokens, string $method, string $receiver): int
    {
        $count = 0;
        foreach ($tokens as $index => $token) {
            if ($this->d2TokenId($token) !== T_OBJECT_OPERATOR) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if (
                $next === null
                || !is_array($tokens[$next])
                || $tokens[$next][0] !== T_STRING
                || $tokens[$next][1] !== $method
            ) {
                continue;
            }
            if ($this->d2ReceiverBeforeOperator($tokens, $index) === $receiver) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2ReceiverBeforeOperator(array $tokens, int $operator): ?string
    {
        $cursor = $this->d2PreviousToken($tokens, $operator - 1);
        if ($cursor === null || !is_array($tokens[$cursor])) {
            return null;
        }
        $parts = [$tokens[$cursor][1]];
        $cursor = $this->d2PreviousToken($tokens, $cursor - 1);
        while ($cursor !== null && $this->d2TokenId($tokens[$cursor]) === T_OBJECT_OPERATOR) {
            $left = $this->d2PreviousToken($tokens, $cursor - 1);
            if ($left === null || !is_array($tokens[$left])) {
                return null;
            }
            array_unshift($parts, $tokens[$left][1], '->');
            $cursor = $this->d2PreviousToken($tokens, $left - 1);
        }

        return implode('', $parts);
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2CallShape(array $tokens, string $method, string $receiver): ?string
    {
        foreach ($tokens as $index => $token) {
            if ($this->d2TokenId($token) !== T_OBJECT_OPERATOR) {
                continue;
            }
            $name = $this->d2NextToken($tokens, $index + 1);
            if (
                $name === null
                || !is_array($tokens[$name])
                || $tokens[$name][0] !== T_STRING
                || $tokens[$name][1] !== $method
                || $this->d2ReceiverBeforeOperator($tokens, $index) !== $receiver
            ) {
                continue;
            }
            $open = $this->d2NextToken($tokens, $name + 1);
            if ($open === null || $tokens[$open] !== '(') {
                return null;
            }
            $depth = 0;
            for ($cursor = $open; $cursor < count($tokens); $cursor++) {
                if ($tokens[$cursor] === '(') {
                    $depth++;
                } elseif ($tokens[$cursor] === ')') {
                    $depth--;
                    if ($depth === 0) {
                        return $this->d2CompactTokens(array_slice($tokens, $name, $cursor - $name + 1));
                    }
                }
            }
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param list<string> $sequence */
    private function d2TokenSequenceCount(array $tokens, array $sequence): int
    {
        $values = array_map(static fn(mixed $token): string => is_array($token) ? $token[1] : $token, $tokens);
        $count = 0;
        $length = count($sequence);
        for ($index = 0; $index <= (count($values) - $length); $index++) {
            if (array_slice($values, $index, $length) === $sequence) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2StaticCallCount(array $tokens, string $class, string $method): int
    {
        $count = 0;
        foreach ($tokens as $index => $token) {
            if ($this->d2TokenId($token) !== T_DOUBLE_COLON) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            $previous = $this->d2PreviousToken($tokens, $index - 1);
            if ($next === null || $previous === null || !is_array($tokens[$next]) || !is_array($tokens[$previous])) {
                continue;
            }
            if ($tokens[$next][1] === $method && $tokens[$previous][1] === $class) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2StaticTargetCallCount(array $tokens, string $target, string $method): int
    {
        $count = 0;
        foreach ($tokens as $index => $token) {
            if ($this->d2TokenId($token) !== T_DOUBLE_COLON) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            $previous = $this->d2PreviousToken($tokens, $index - 1);
            if ($next === null || $previous === null || !is_array($tokens[$next]) || !is_array($tokens[$previous])) {
                continue;
            }
            if ($tokens[$next][1] === $method && $tokens[$previous][1] === $this->d2TerminalName($target)) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2ConstructorCount(array $tokens, string $class): int
    {
        $count = 0;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_NEW) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if ($next !== null && is_array($tokens[$next]) && strtolower($tokens[$next][1]) === strtolower($class)) {
                $count++;
            }
        }

        return $count;
    }

    private function d2ConstructorTargetCount(string $source, string $target): int
    {
        $metadata = $this->d2Metadata($source);
        $count = 0;
        $tokens = $this->d2SignificantTokens($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_NEW) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if ($next === null || !is_array($tokens[$next])) {
                continue;
            }
            if ($this->d2ResolveName($tokens[$next][1], $metadata['namespace'], $metadata['aliases']) === $target) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2ReturnVariableCount(array $tokens, string $variable): int
    {
        $count = 0;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_RETURN) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if (
                $next !== null
                && is_array($tokens[$next])
                && $tokens[$next][0] === T_VARIABLE
                && $tokens[$next][1] === '$' . $variable
            ) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function d2OpenWriterOrderIsPure(array $tokens): bool
    {
        $constructor = null;
        $requested = null;
        $return = null;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_NEW) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next !== null && is_array($tokens[$next]) && $tokens[$next][1] === 'self') {
                    $constructor = $index;
                }
            }
            if ($this->d2TokenId($token) === T_OBJECT_OPERATOR) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next !== null && is_array($tokens[$next]) && $tokens[$next][1] === 'requested') {
                    $requested = $index;
                }
            }
            if (is_array($token) && $token[0] === T_RETURN) {
                $next = $this->d2NextToken($tokens, $index + 1);
                if ($next !== null && is_array($tokens[$next]) && $tokens[$next][1] === '$session') {
                    $return = $index;
                }
            }
        }
        if (
            $constructor === null
            || $requested === null
            || $return === null
            || !($constructor < $requested && $requested < $return)
        ) {
            return false;
        }
        $requestEnd = $requested;
        while ($requestEnd < $return && $tokens[$requestEnd] !== ';') {
            $requestEnd++;
        }
        if ($requestEnd >= $return) {
            return false;
        }
        $segment = array_slice($tokens, $requestEnd + 1, $return - $requestEnd - 1);

        return (
            $this->d2CompactTokens($segment)
            === '}catch(AuditDeliveryException$failure){throw$failure;}catch(\\Throwable){thrownewAuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);}'
        );
    }

    private function d2FunctionCallCount(string $source, string $function): int
    {
        $tokens = $this->d2SignificantTokens($source);
        $count = 0;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== $function) {
                continue;
            }
            $next = $this->d2NextToken($tokens, $index + 1);
            if ($next !== null && $tokens[$next] === '(') {
                $count++;
            }
        }

        return $count;
    }

    private function d2TokenId(mixed $token): ?int
    {
        return is_array($token) && is_int($token[0]) ? $token[0] : null;
    }

    /** @return list<string> */
    private function propertyNames(string $class): array
    {
        $names = [];
        foreach (new ReflectionClass($class)->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $class) {
                $names[] = $property->getName();
            }
        }
        sort($names);

        return $names;
    }

    private function assertPropertyContract(
        string $class,
        string $name,
        string $type,
        bool $private,
        bool $promoted,
        bool $static,
        bool $readonly,
        mixed $default,
    ): void {
        $property = new ReflectionClass($class)->getProperty($name);
        self::assertSame($class, $property->getDeclaringClass()->getName());
        self::assertSame($private, $property->isPrivate());
        self::assertSame($promoted, $property->isPromoted());
        self::assertSame($static, $property->isStatic());
        self::assertSame($readonly, $property->isReadOnly());
        self::assertSame($type, $property->getType()?->getName());
        if ($default === null && !$property->hasDefaultValue()) {
            self::assertFalse($property->hasDefaultValue());
        } else {
            self::assertTrue($property->hasDefaultValue());
            self::assertSame($default, $property->getDefaultValue());
        }
    }

    /** @param list<array{0:string,1:string,2:bool,3:bool}> $parameters */
    private function assertMethod(string $class, string $name, array $parameters, ?string $returnType): void
    {
        $method = new ReflectionClass($class)->getMethod($name);
        self::assertSame($class, $method->getDeclaringClass()->getName());
        self::assertTrue($method->isPublic() || $method->isPrivate());
        $actual = $method->getParameters();
        self::assertCount(count($parameters), $actual, $class . '::' . $name);
        foreach ($parameters as $index => [$parameterName, $type, $nullable, $sensitive]) {
            $parameter = $actual[$index];
            self::assertSame($parameterName, $parameter->getName());
            $parameterType = $parameter->getType();
            if ($parameterType instanceof ReflectionUnionType) {
                $actualTypes = [];
                foreach ($parameterType->getTypes() as $unionType) {
                    if ($unionType->getName() !== 'null') {
                        $actualTypes[] = $unionType->getName();
                    }
                }
                sort($actualTypes);
                $expectedTypes = explode('|', $type);
                sort($expectedTypes);
                self::assertSame($expectedTypes, $actualTypes);
            } else {
                self::assertInstanceOf(ReflectionNamedType::class, $parameterType);
                self::assertSame($type, $parameterType->getName());
            }
            self::assertSame($nullable, $parameterType?->allowsNull());
            self::assertSame($sensitive ? 1 : 0, count($parameter->getAttributes(\SensitiveParameter::class)));
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
        if ($returnType !== null) {
            $actualReturn = $method->getReturnType();
            self::assertInstanceOf(ReflectionNamedType::class, $actualReturn);
            self::assertSame($returnType, $actualReturn->getName());
        }
    }
}
