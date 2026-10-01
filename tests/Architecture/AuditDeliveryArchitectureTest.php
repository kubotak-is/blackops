<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Audit\AuditOpaqueIdKeyProvider;
use BlackOps\Audit\AuditProtectionKeyProvider;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Application\ApplicationAuditConfiguration;
use BlackOps\Internal\Application\ApplicationAuditRuntime;
use BlackOps\Internal\Application\ApplicationAuditRuntimeComposer;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditCoordinator;
use BlackOps\Internal\Audit\Key\ApplicationAuditProtectionKeySource;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditMutationTransactionCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
use BlackOps\Internal\Audit\Retention\RetentionHoldAuditWriter;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class AuditDeliveryArchitectureTest extends TestCase
{
    public function testExactProductionInventoryModifiersAndApiMarkerBoundary(): void
    {
        $root = realpath(__DIR__ . '/../../');
        self::assertIsString($root);
        $expected = [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => AuditOpaqueIdKeyProvider::class,
            'src/Audit/AuditProtectionKeyProvider.php' => AuditProtectionKeyProvider::class,
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php' => AuditDeliveryFailure::class,
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => AuditDeliveryException::class,
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => AuditDeliveryPlan::class,
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => AuditDeliveryRequest::class,
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => AuditDeliverySelector::class,
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => AuditDeliveryCoordinator::class,
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' =>
                AuditMutationTransactionCoordinator::class,
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => AuditProtectedReadGate::class,
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' =>
                ApplicationAuditProtectionKeySource::class,
            'src/Internal/Application/ApplicationAuditConfiguration.php' => ApplicationAuditConfiguration::class,
            'src/Internal/Application/ApplicationAuditRuntime.php' => ApplicationAuditRuntime::class,
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => ApplicationAuditRuntimeComposer::class,
        ];
        foreach ($expected as $relative => $type) {
            self::assertFileExists($root . '/' . $relative);
            self::assertTrue(class_exists($type) || interface_exists($type) || enum_exists($type), $type);
            $reflection = new ReflectionClass($type);
            if (in_array($type, [AuditOpaqueIdKeyProvider::class, AuditProtectionKeyProvider::class], true)) {
                self::assertTrue($reflection->isInterface(), $type);
                self::assertCount(1, $reflection->getAttributes(PublicApi::class), $type);
            } else {
                self::assertSame([], $reflection->getAttributes(PublicApi::class), $type);
            }
        }
        self::assertTrue(new ReflectionClass(AuditDeliveryFailure::class)->isEnum());
        self::assertTrue(new ReflectionClass(AuditDeliveryException::class)->isFinal());
        self::assertFalse(new ReflectionClass(AuditDeliveryException::class)->isReadOnly());
        self::assertSame(
            \RuntimeException::class,
            new ReflectionClass(AuditDeliveryException::class)->getParentClass()?->getName(),
        );
        $exceptionSource = file_get_contents($root . '/src/Internal/Audit/Orchestration/AuditDeliveryException.php');
        self::assertIsString($exceptionSource);
        $logicException = str_replace('extends RuntimeException', 'extends \\LogicException', $exceptionSource, $count);
        self::assertSame(1, $count);
        self::assertNotSame('RuntimeException', $this->c3ParentClass($logicException));
        foreach ([
            AuditDeliveryPlan::class,
            AuditDeliverySelector::class,
            AuditDeliveryCoordinator::class,
            AuditMutationTransactionCoordinator::class,
            AuditProtectedReadGate::class,
            ApplicationAuditProtectionKeySource::class,
            ApplicationAuditConfiguration::class,
            ApplicationAuditRuntime::class,
            ApplicationAuditRuntimeComposer::class,
        ] as $type) {
            self::assertTrue(new ReflectionClass($type)->isFinal(), $type);
            self::assertTrue(new ReflectionClass($type)->isReadOnly(), $type);
        }
        self::assertTrue(new ReflectionClass(AuditDeliveryRequest::class)->isFinal());
    }

    public function testPublicAndProtectedCallableSurfacesAndSensitiveMatrixAreExact(): void
    {
        $expected = [
            AuditOpaqueIdKeyProvider::class => ['key'],
            AuditProtectionKeyProvider::class => ['activeRecordKey', 'recordKey', 'activeOutboxKey', 'outboxKey'],
            AuditDeliveryException::class => [
                '__construct',
                'failure',
                'code',
                '__debugInfo',
                '__serialize',
                '__unserialize',
            ],
            AuditDeliveryPlan::class => [
                'fromSelection',
                'catalogHash',
                'eventType',
                'eventVersion',
                'evidenceVersion',
                'delivery',
            ],
            AuditDeliveryRequest::class => [
                '__construct',
                'draft',
                'idempotencyKey',
                '__debugInfo',
                '__serialize',
                '__unserialize',
            ],
            AuditDeliverySelector::class => ['__construct', 'select', 'forDraft', 'validate'],
            AuditDeliveryCoordinator::class => [
                '__construct',
                'deliverStandalone',
                'deliverInCurrentTransaction',
                'usesConnection',
            ],
            OperationTransactionCoordinator::class => [
                '__construct',
                'execute',
                'isTransactional',
                'sharesFrameworkConnection',
                'usesFrameworkConnection',
            ],
            AuditMutationTransactionCoordinator::class => ['__construct', 'execute'],
            AuditProtectedReadGate::class => ['__construct', 'requested', 'terminal'],
            ApplicationAuditProtectionKeySource::class => ['__construct', 'activeKey', 'key'],
            ApplicationAuditConfiguration::class => ['fromConfiguration', 'outbox'],
            ApplicationAuditRuntime::class => [
                'fromComposition',
                'outboxConfiguration',
                'opaqueIds',
                'deliverySelector',
                'delivery',
                'mutations',
                'protectedReads',
                'records',
                'outbox',
                'relay',
            ],
            ApplicationAuditRuntimeComposer::class => ['compose'],
        ];
        foreach ($expected as $type => $names) {
            $actual = [];
            foreach (new ReflectionClass($type)->getMethods() as $method) {
                if (
                    $method->getDeclaringClass()->getName() === $type
                    && ($method->isPublic() || $method->isProtected())
                ) {
                    $actual[] = $method->getName();
                }
            }
            sort($actual);
            sort($names);
            self::assertSame($names, $actual, $type);
        }

        $this->assertParameters(AuditOpaqueIdKeyProvider::class, 'key', [], 'StorageKey');
        $this->assertParameters(
            AuditProtectionKeyProvider::class,
            'activeRecordKey',
            [['partitionId', 'string', 1]],
            'StorageKey',
        );
        $this->assertParameters(
            AuditProtectionKeyProvider::class,
            'recordKey',
            [
                ['keyId',       'string', 1],
                ['partitionId', 'string', 1],
            ],
            'StorageKey',
        );
        $this->assertParameters(
            AuditProtectionKeyProvider::class,
            'activeOutboxKey',
            [['partitionId', 'string', 1]],
            'StorageKey',
        );
        $this->assertParameters(
            AuditProtectionKeyProvider::class,
            'outboxKey',
            [
                ['keyId',       'string', 1],
                ['partitionId', 'string', 1],
            ],
            'StorageKey',
        );
        $this->assertParameters(
            AuditDeliveryPlan::class,
            'fromSelection',
            [
                ['catalogHash',     'string',        1],
                ['eventType',       'string',        1],
                ['eventVersion',    'int',           1],
                ['evidenceVersion', 'int',           1],
                ['delivery',        'AuditDelivery', 0],
            ],
            'AuditDeliveryPlan',
        );
        $this->assertParameters(
            AuditDeliveryRequest::class,
            '__construct',
            [
                ['draft',          'AuditRecordDraft', 1],
                ['idempotencyKey', '?string',          1],
            ],
        );
        $this->assertParameters(AuditDeliveryRequest::class, '__unserialize', [['data', 'array', 1]], 'void');
        $this->assertParameters(
            AuditDeliverySelector::class,
            'select',
            [
                ['catalog',     'AuditEventCatalog',    0],
                ['definition',  'AuditEventDefinition', 0],
                ['requirement', 'mixed',                1],
            ],
            'AuditDeliveryPlan',
        );
        $this->assertParameters(
            AuditDeliverySelector::class,
            'forDraft',
            [['draft', 'AuditRecordDraft', 1]],
            'AuditDeliveryPlan',
        );
        $this->assertParameters(
            AuditDeliverySelector::class,
            'validate',
            [
                ['plan',    'AuditDeliveryPlan',    0],
                ['request', 'AuditDeliveryRequest', 1],
            ],
            'AuditDelivery',
        );
        $this->assertParameters(
            AuditDeliveryCoordinator::class,
            'deliverStandalone',
            [['request', 'AuditDeliveryRequest', 1]],
            'void',
        );
        $this->assertParameters(
            AuditDeliveryCoordinator::class,
            'deliverInCurrentTransaction',
            [
                ['plan',    'AuditDeliveryPlan',    0],
                ['request', 'AuditDeliveryRequest', 1],
            ],
            'void',
        );
        $this->assertParameters(
            AuditDeliveryCoordinator::class,
            'usesConnection',
            [['connection', 'Connection', 1]],
            'bool',
        );
        $this->assertParameters(
            OperationTransactionCoordinator::class,
            'usesFrameworkConnection',
            [['connection', 'Connection', 1]],
            'bool',
        );
        $this->assertParameters(
            AuditMutationTransactionCoordinator::class,
            'execute',
            [
                ['plan',            'AuditDeliveryPlan', 0],
                ['metadata',        'OperationMetadata', 0],
                ['invoke',          'Closure',           1],
                ['deliveryRequest', 'Closure',           1],
            ],
            'OperationResult',
        );
        $this->assertParameters(
            AuditProtectedReadGate::class,
            'requested',
            [
                ['basis', 'AuditReadRequestBasis', 1],
                ['draft', 'AuditRecordDraft',      1],
            ],
            'void',
        );
        $this->assertParameters(
            AuditProtectedReadGate::class,
            'terminal',
            [
                ['context', 'AuditReadContext', 1],
                ['draft',   'AuditRecordDraft', 1],
            ],
            'AuditReadAuthorizationDecision',
        );
        $this->assertParameters(
            ApplicationAuditProtectionKeySource::class,
            'activeKey',
            [
                ['tenant',  '?AuditTypedReference',   1],
                ['purpose', 'AuditProtectionPurpose', 0],
            ],
            'StorageKey',
        );
        $this->assertParameters(
            ApplicationAuditProtectionKeySource::class,
            'key',
            [
                ['keyId',   'string',                 1],
                ['tenant',  '?AuditTypedReference',   1],
                ['purpose', 'AuditProtectionPurpose', 0],
            ],
            'StorageKey',
        );
        $this->assertParameters(
            ApplicationAuditConfiguration::class,
            'fromConfiguration',
            [['configuration', 'array', 1]],
            'ApplicationAuditConfiguration',
        );
        $this->assertParameters(ApplicationAuditConfiguration::class, 'outbox', [], 'AuditOutboxConfiguration');
        $this->assertParameters(
            ApplicationAuditRuntime::class,
            'fromComposition',
            [
                ['outboxConfiguration', 'AuditOutboxConfiguration',            1],
                ['opaqueIds',           'HmacAuditOpaqueIdDeriver',            1],
                ['deliverySelector',    'AuditDeliverySelector',               1],
                ['delivery',            'AuditDeliveryCoordinator',            1],
                ['mutations',           'AuditMutationTransactionCoordinator', 1],
                ['protectedReads',      'AuditProtectedReadGate',              1],
                ['records',             'PostgreSqlAuditRecordStore',          1],
                ['outbox',              'PostgreSqlAuditOutboxStore',          1],
                ['relay',               'AuditOutboxRelayRuntime',             1],
            ],
            'ApplicationAuditRuntime',
        );
        $this->assertParameters(
            ApplicationAuditRuntimeComposer::class,
            'compose',
            [
                ['configuration', 'ApplicationConfigurationSnapshot',       1],
                ['operations',    'ApplicationOperationRuntimeComposition', 1],
                ['catalogs',      'ImmutableAuditEventCatalogLookup',       1],
            ],
            'ApplicationAuditRuntime',
        );
    }

    public function testConnectionIdentityPredicatesRemainPureObjectComparisons(): void
    {
        $expected = [
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => [
                'class' => 'AuditDeliveryCoordinator',
                'method' => 'usesConnection',
                'property' => 'connection',
            ],
            'src/Internal/Transaction/OperationTransactionCoordinator.php' => [
                'class' => 'OperationTransactionCoordinator',
                'method' => 'usesFrameworkConnection',
                'property' => 'frameworkConnection',
            ],
        ];
        foreach ($expected as $relative => $definition) {
            $source = file_get_contents(__DIR__ . '/../../' . $relative);
            self::assertIsString($source, $relative);
            $nodes = $this->parseArchitectureSource($source);
            $class = $this->architectureClass($nodes, $definition['class']);
            self::assertNotNull($class, $relative);
            $method = $this->architectureMethod($class, $definition['method']);
            self::assertNotNull($method, $relative);
            self::assertNotNull($method->stmts, $relative);
            self::assertCount(1, $method->stmts, $relative);
            $statement = $method->stmts[0];
            self::assertInstanceOf(Return_::class, $statement, $relative);
            self::assertInstanceOf(Identical::class, $statement->expr, $relative);
            self::assertTrue($this->isIdentityComparison($statement->expr, $definition['property']), $relative);
            self::assertSame([], new NodeFinder()->findInstanceOf($method, Node\Expr\MethodCall::class), $relative);
            self::assertSame([], new NodeFinder()->findInstanceOf($method, Node\Expr\StaticCall::class), $relative);
            self::assertSame([], new NodeFinder()->findInstanceOf($method, Node\Expr\New_::class), $relative);
        }
    }

    public function testNativeAuthorizationAuditDependencyIsTrailingOptionalSensitive(): void
    {
        foreach ([
            InlineDispatcher::class,
            DeferredAcceptanceOrchestrator::class,
            DeferredWorkerRuntime::class,
        ] as $type) {
            $constructor = new ReflectionClass($type)->getConstructor();
            self::assertNotNull($constructor, $type);
            $parameters = $constructor->getParameters();
            self::assertNotEmpty($parameters, $type);
            $parameter = $parameters[count($parameters) - 1];
            self::assertSame('authorizationAudit', $parameter->getName(), $type);
            self::assertSame(AuthorizationAuditCoordinator::class, $parameter->getType()?->getName(), $type);
            self::assertTrue($parameter->allowsNull(), $type);
            self::assertTrue($parameter->isDefaultValueAvailable(), $type);
            self::assertNull($parameter->getDefaultValue(), $type);
            self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class), $type);
            $property = new ReflectionClass($type)->getProperty('authorizationAudit');
            self::assertTrue($property->isPrivate(), $type);
            self::assertTrue($property->isReadOnly(), $type);
            self::assertSame(AuthorizationAuditCoordinator::class, $property->getType()?->getName(), $type);
        }
    }

    public function testDirectCallAndConstructionMapsRemainBoundedAndActivationIsAbsent(): void
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace($root . '/', '', $file->getPathname());
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $sources[$relative] = $source;
        }
        $composer = $sources['Internal/Application/ApplicationAuditRuntimeComposer.php'];
        self::assertSame(1, substr_count($composer, 'new HmacAuditOpaqueIdDeriver('));
        self::assertSame(1, substr_count($composer, 'new ApplicationAuditProtectionKeySource('));
        self::assertSame(1, substr_count($composer, 'new AuditMutationTransactionCoordinator('));
        self::assertSame(1, substr_count($composer, 'ApplicationAuditRuntime::fromComposition('));
        self::assertSame(1, substr_count(
            $sources['Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'],
            '->appendRequired(',
        ));
        self::assertSame(1, substr_count(
            $sources['Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'],
            '->appendDurable(',
        ));
        self::assertSame(0, substr_count(
            $sources['Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'],
            'beginTransaction(',
        ));
        self::assertSame(0, substr_count(
            $sources['Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'],
            '->commit(',
        ));
        self::assertSame(0, substr_count(
            $sources['Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'],
            '->rollBack(',
        ));
        self::assertSame(0, substr_count(
            $sources['Internal/Audit/Key/ApplicationAuditProtectionKeySource.php'],
            'StorageKeyProvider',
        ));
        self::assertSame(0, substr_count(
            $sources['Internal/Audit/Key/ApplicationAuditProtectionKeySource.php'],
            'StoragePurpose',
        ));
        self::assertSame(0, substr_count($composer, 'new ApplicationAuditRuntimeComposer('));
        foreach ($sources as $relative => $source) {
            if ($relative === 'Internal/Application/ApplicationAuditRuntimeComposer.php') {
                continue;
            }
            self::assertStringNotContainsString('new ApplicationAuditRuntimeComposer(', $source, $relative);
        }
        self::assertSame(1, substr_count(
            $sources['Internal/Application/ApplicationOperationRuntimeComposer.php'],
            'new ApplicationOperationRuntimeComposition(',
        ));
    }

    public function testC3InventoryAndDeclarationOracleRejectsStructuralMutations(): void
    {
        $sources = $this->c3Sources();
        $expected = $this->c3ExpectedPaths();
        self::assertSame($expected, $this->c3Inventory($sources));
        $baseline = $this->c3DeclarationBaseline($sources);
        self::assertSame([], $this->c3DeclarationViolations($sources, $baseline));

        $extra = $sources;
        $extra['src/Internal/Audit/Orchestration/Nested/Decoy.php'] = '<?php';
        self::assertNotEmpty($this->c3DeclarationViolations($extra, $baseline));

        $missing = $sources;
        unset($missing['src/Internal/Audit/Orchestration/AuditDeliveryPlan.php']);
        self::assertNotEmpty($this->c3DeclarationViolations($missing, $baseline));

        $type = $sources;
        $type['src/Internal/Audit/Orchestration/AuditDeliveryPlan.php'] = str_replace(
            'final readonly class AuditDeliveryPlan',
            'final class AuditDeliveryPlan',
            $type['src/Internal/Audit/Orchestration/AuditDeliveryPlan.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($type, $baseline));

        $signature = $sources;
        $signature['src/Internal/Audit/Orchestration/AuditDeliveryException.php'] = str_replace(
            'private readonly AuditDeliveryFailure $failure',
            'protected readonly AuditDeliveryFailure $failure',
            $signature['src/Internal/Audit/Orchestration/AuditDeliveryException.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($signature, $baseline));

        $writerSignature = $sources;
        $writerSignature['src/Internal/Audit/Retention/RetentionHoldAuditWriter.php'] = str_replace(
            'string $buildId,',
            'int $buildId,',
            $writerSignature['src/Internal/Audit/Retention/RetentionHoldAuditWriter.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($writerSignature, $baseline));

        $parent = $sources;
        $parent['src/Internal/Audit/Orchestration/AuditDeliveryException.php'] = str_replace(
            'extends RuntimeException',
            'extends \\LogicException',
            $parent['src/Internal/Audit/Orchestration/AuditDeliveryException.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($parent, $baseline));

        $planReturn = $sources;
        $planReturn['src/Internal/Audit/Orchestration/AuditDeliveryPlan.php'] = str_replace(
            '): self',
            '): object',
            $planReturn['src/Internal/Audit/Orchestration/AuditDeliveryPlan.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($planReturn, $baseline));

        $runtimeReturn = $sources;
        $runtimeReturn['src/Internal/Application/ApplicationAuditRuntime.php'] = str_replace(
            '): self',
            '): object',
            $runtimeReturn['src/Internal/Application/ApplicationAuditRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($runtimeReturn, $baseline));

        $attribute = $sources;
        $attribute['src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'] = str_replace(
            "#[\\SensitiveParameter]\n        AuditDeliveryRequest \$request",
            'AuditDeliveryRequest $request',
            $attribute['src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($attribute, $baseline));

        $decoy = $sources;
        $decoy['src/Internal/Audit/Orchestration/AuditDeliveryPlan.php'] .= <<<'NOWDOC'

            // final readonly class FakePlan { public function fake(): void {} }
            NOWDOC;
        self::assertSame([], $this->c3DeclarationViolations($decoy, $baseline));
    }

    public function testC3ReferenceOracleIsSemanticAndRejectsIndirectOrDynamicCallers(): void
    {
        $sources = $this->c3Sources();
        self::assertSame([], $this->c3ReferenceViolations($sources));
        self::assertSame(
            [
                'appendRequired' => [
                    'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => 1,
                ],
                'appendDurable' => [
                    'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => 1,
                    'src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => 1,
                ],
                'deliverInCurrentTransaction' => [
                    'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => 2,
                    'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => 1,
                    'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 1,
                ],
                'deliverInMutationTransaction' => [
                    'src/Internal/Execution/DeferredWorkerRuntime.php' => 1,
                    'src/Internal/Execution/InlineDispatcher.php' => 1,
                ],
                'deliverStandalone' => [
                    'src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php' => 1,
                    'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => 1,
                    'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => 2,
                    'src/Internal/Execution/InlineDispatcher.php' => 2,
                ],
            ],
            $this->c3DirectCalls($sources),
        );

        $removedImplementation = $sources;
        $removedImplementation['src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php'] = str_replace(
            ' implements AuditProtectionKeySource',
            '',
            $removedImplementation['src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3ReferenceViolations($removedImplementation));

        $secondImplementation = $sources;
        $secondImplementation['src/Internal/External/C3SecondKeySource.php'] = <<<'PHP'
            <?php

            namespace BlackOps\Internal\External;

            use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;

            final class C3SecondKeySource implements AuditProtectionKeySource {}
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations($secondImplementation));

        $added = $sources;
        $added['src/External/C3Caller.php'] = '<?php $store->appendRequired($draft);';
        self::assertNotEmpty($this->c3ReferenceViolations($added));

        $authenticationReference = $sources;
        $authenticationReference['src/External/C3AuthenticationReference.php'] = <<<'PHP'
            <?php

            use BlackOps\Internal\Audit\Authentication\AuthenticationAuditEventWriter;

            final class C3AuthenticationReference
            {
                public function write(AuthenticationAuditEventWriter $writer): void {}
            }
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations($authenticationReference));

        $authenticationConstructor = $sources;
        $authenticationConstructor['src/External/C3AuthenticationConstructor.php'] = <<<'PHP'
            <?php

            use BlackOps\Internal\Audit\Authentication\AuditedHttpAuthenticator;

            new AuditedHttpAuthenticator($authenticator, $writer, $connection);
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations($authenticationConstructor));

        $authenticationDelivery = $sources;
        $authenticationDelivery['src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php'] = str_replace(
            '->deliverStandalone(',
            '->deliverRemoved(',
            $authenticationDelivery['src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3ReferenceViolations($authenticationDelivery));

        $removed = $sources;
        $removed['src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'] = str_replace(
            '->appendRequired(',
            '->removed(',
            $removed['src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3ReferenceViolations($removed));

        $writerReceiver = $sources;
        $writerReceiver['src/Internal/Audit/Retention/RetentionHoldAuditWriter.php'] = str_replace(
            '$delivery->deliverInCurrentTransaction(',
            '$alternateDelivery->deliverInCurrentTransaction(',
            $writerReceiver['src/Internal/Audit/Retention/RetentionHoldAuditWriter.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3ReferenceViolations($writerReceiver));

        $dynamic = $sources;
        $dynamic['src/External/C3Caller.php'] = '<?php $store->{$method}($draft);';
        self::assertNotEmpty($this->c3ReferenceViolations($dynamic));

        $reflection = $sources;
        $reflection['src/External/C3Caller.php'] = "<?php ReflectionClass::newInstance('BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator');";
        self::assertNotEmpty($this->c3ReferenceViolations($reflection));

        $classString = $sources;
        $classString['src/External/C3Caller.php'] = "<?php \$name = 'BlackOps\\\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator'; \$name::deliverStandalone();";
        self::assertNotEmpty($this->c3ReferenceViolations($classString));

        $parenthesized = $sources;
        $parenthesized['src/External/C3Caller.php'] = '<?php (AuditDeliveryCoordinator::deliverStandalone)($request);';
        self::assertNotEmpty($this->c3ReferenceViolations($parenthesized));

        $qualified = $sources;
        $qualified['src/Internal/Application/ApplicationAuditRuntimeComposer.php'] = str_replace(
            'new HmacAuditOpaqueIdDeriver(',
            'new \\BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver(',
            $qualified['src/Internal/Application/ApplicationAuditRuntimeComposer.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertSame([], $this->c3ReferenceViolations($qualified));

        $alias = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Application;
            use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver as Opaque;
            new Opaque('key');
            PHP;
        self::assertSame(1, $this->c3ConstructorCalls($alias, 'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver'));

        $group = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Application;
            use BlackOps\Internal\Audit\{HmacAuditOpaqueIdDeriver as Opaque, AuditDeliveryPlan};
            new Opaque('key');
            PHP;
        self::assertSame(1, $this->c3ConstructorCalls($group, 'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver'));

        $sameNamespace = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit;
            new HmacAuditOpaqueIdDeriver('key');
            PHP;
        self::assertSame(1, $this->c3ConstructorCalls(
            $sameNamespace,
            'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
        ));

        $decoy = $alias . <<<'NOWDOC'

            // new BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver('decoy');
            <<<'TEXT'
            new BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver('decoy');
            TEXT;
            NOWDOC;
        self::assertSame(1, $this->c3ConstructorCalls($decoy, 'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver'));

        $composerCaller = $sources;
        $composerCaller['src/External/C3Caller.php'] = <<<'PHP'
            <?php
            namespace BlackOpsExternal;
            use BlackOps\Internal\Application\ApplicationAuditRuntimeComposer as RuntimeComposer;
            new RuntimeComposer();
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations($composerCaller));

        $factoryAlias = <<<'PHP'
            <?php
            namespace BlackOpsExternal;
            use BlackOps\Internal\Application\ApplicationAuditRuntime as Runtime;
            Runtime::fromComposition();
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3Factory.php' => $factoryAlias,
        ]));

        $factoryTwoHop = <<<'PHP'
            <?php
            $first = 'BlackOps\\Internal\\Application\\ApplicationAuditRuntime';
            $second = $first;
            $second::fromComposition();
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3Factory.php' => $factoryTwoHop,
        ]));

        $factoryHeredoc = <<<'PHP'
            <?php
            $target = <<<TARGET
            BlackOps\Internal\Application\ApplicationAuditRuntime
            TARGET;
            new ReflectionClass($target);
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3Factory.php' => $factoryHeredoc,
        ]));

        $callable = <<<'PHP'
            <?php
            call_user_func([ApplicationAuditRuntimeComposer::class, 'compose'], $configuration, $operations, $catalogs);
            [$coordinator, 'deliverStandalone']($request);
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([...$sources, 'src/External/C3Callable.php' => $callable]));

        $manualComposition = <<<'PHP'
            <?php
            namespace BlackOpsExternal;
            use BlackOps\Internal\Application\ApplicationOperationRuntimeComposition as Composition;
            new Composition();
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3ManualComposition.php' => $manualComposition,
        ]));

        $alternateManager = <<<'PHP'
            <?php
            namespace BlackOpsExternal;
            new \BlackOps\Internal\Database\DoctrineDatabaseManager();
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3AlternateManager.php' => $alternateManager,
        ]));

        $directClassStrings = <<<'PHP'
            <?php
            new ReflectionClass(\BlackOps\Internal\Application\ApplicationOperationRuntimeComposition::class);
            new ReflectionClass(\BlackOps\Internal\Database\DoctrineDatabaseManager::class);
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3ClassStrings.php' => $directClassStrings,
        ]));

        $classStringTwoHop = <<<'PHP'
            <?php
            $composition = 'BlackOps\\Internal\\Application\\ApplicationOperationRuntimeComposition';
            $manager = 'BlackOps\\Internal\\Database\\DoctrineDatabaseManager';
            $compositionAlias = $composition;
            $managerAlias = $manager;
            new ReflectionClass($compositionAlias);
            new ReflectionClass($managerAlias);
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3ClassStringHops.php' => $classStringTwoHop,
        ]));

        foreach ([
            'class_exists',
            'enum_exists',
            'interface_exists',
            'is_a',
            'is_subclass_of',
        ] as $consumer) {
            $literalConsumer = str_replace('CONSUMER', $consumer, <<<'PHP'
                <?php
                CONSUMER('BlackOps\Internal\Application\ApplicationOperationRuntimeComposition');
                PHP);
            self::assertNotEmpty(
                $this->c3ReferenceViolations([
                    ...$sources,
                    'src/External/C3ConsumedClassString.php' => $literalConsumer,
                ]),
                $consumer . ' literal class-string target must be rejected',
            );
            $hopConsumer = str_replace('CONSUMER', $consumer, <<<'PHP'
                <?php
                $first = 'BlackOps\Internal\Database\DoctrineDatabaseManager';
                $second = $first;
                CONSUMER($second);
                PHP);
            self::assertNotEmpty(
                $this->c3ReferenceViolations([
                    ...$sources,
                    'src/External/C3ConsumedClassString.php' => $hopConsumer,
                ]),
                $consumer . ' two-hop class-string target must be rejected',
            );
        }
        $sameNamespaceClassString = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Application;
            class_exists(ApplicationOperationRuntimeComposition::class);
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3SameNamespaceClassString.php' => $sameNamespaceClassString,
        ]));

        $dynamicTargets = <<<'PHP'
            <?php
            $target = 'BlackOps\\Internal\\Database\\DoctrineDatabaseManager';
            new $target();
            PHP;
        self::assertNotEmpty($this->c3ReferenceViolations([
            ...$sources,
            'src/External/C3DynamicManager.php' => $dynamicTargets,
        ]));

        $unusedDecoy = <<<'PHP'
            <?php
            $decoy = 'BlackOps\\Internal\\Application\\ApplicationAuditRuntime';
            $manager = 'BlackOps\\Internal\\Database\\DoctrineDatabaseManager';
            PHP;
        self::assertSame([], $this->c3ReferenceViolations([...$sources, 'src/External/C3Factory.php' => $unusedDecoy]));
    }

    public function testC3SensitiveStorageEnumAndExactExceptionConstructorContracts(): void
    {
        self::assertSame(
            [
                'configuration_invalid',
                'policy_invalid',
                'transaction_invalid',
                'key_unavailable',
                'resource_unavailable',
                'storage_unavailable',
            ],
            array_map(
                static fn(AuditDeliveryFailure $failure): string => $failure->value,
                AuditDeliveryFailure::cases(),
            ),
        );

        $exceptionConstructor = new ReflectionClass(AuditDeliveryException::class)->getConstructor();
        self::assertNotNull($exceptionConstructor);
        self::assertSame('failure', $exceptionConstructor->getParameters()[0]->getName());
        self::assertTrue($exceptionConstructor->getParameters()[0]->isPromoted());
        $exceptionFailure = new ReflectionClass(AuditDeliveryException::class)->getProperty('failure');
        self::assertTrue($exceptionFailure->isPrivate());
        self::assertTrue($exceptionFailure->isReadOnly());
        self::assertSame(AuditDeliveryFailure::class, $exceptionConstructor->getParameters()[0]->getType()?->getName());
        self::assertSame([], new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable)->__debugInfo());
        self::assertSame([], new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable)->__serialize());

        $requestProperties = array_map(
            static fn(\ReflectionProperty $property): string => $property->getName(),
            new ReflectionClass(AuditDeliveryRequest::class)->getProperties(),
        );
        self::assertSame(['snapshot'], $requestProperties);
        $sourceProperties = new ReflectionClass(ApplicationAuditProtectionKeySource::class)->getProperties();
        self::assertSame(
            ['snapshot'],
            array_map(static fn(\ReflectionProperty $property): string => $property->getName(), $sourceProperties),
        );
        self::assertSame('SensitiveParameterValue', $sourceProperties[0]->getType()?->getName());
        self::assertSame(
            [],
            array_filter(
                $sourceProperties,
                static fn(\ReflectionProperty $property): bool => (
                    $property->getName() !== 'snapshot'
                    && (str_contains($property->getName(), 'provider') || str_contains($property->getName(), 'key'))
                ),
            ),
        );
    }

    public function testC3PropertyDependencyAndForbiddenReferenceMatrixIsNormative(): void
    {
        self::assertSame($this->c3ExpectedPropertyMatrix(), $this->c3PropertyMatrix());

        $sources = $this->c3Sources();
        self::assertSame([], $this->c3DependencyViolations($sources));
        self::assertSame([], $this->c3ForbiddenReferenceViolations($sources));

        $propertyMutation = $sources;
        $propertyMutation['src/Internal/Audit/Orchestration/AuditDeliveryRequest.php'] = str_replace(
            'private \\SensitiveParameterValue $snapshot',
            'protected \\SensitiveParameterValue $snapshot',
            $propertyMutation['src/Internal/Audit/Orchestration/AuditDeliveryRequest.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DependencyViolations($propertyMutation));

        $defaultMutation = $sources;
        $defaultMutation['src/Internal/Audit/Orchestration/AuditDeliveryRequest.php'] = str_replace(
            '?string $idempotencyKey,',
            '?string $idempotencyKey = null,',
            $defaultMutation['src/Internal/Audit/Orchestration/AuditDeliveryRequest.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DeclarationViolations($defaultMutation, $this->c3DeclarationBaseline($sources)));

        $dependencyMutation = $sources;
        $dependencyMutation['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'] = str_replace(
            'use BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator;',
            'use BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator as WrongValidator;',
            $dependencyMutation['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DependencyViolations($dependencyMutation));

        $forbiddenMutation = $sources;
        $forbiddenMutation['src/Internal/Application/ApplicationAuditRuntimeComposer.php'] .= "\nuse BlackOps\\StorageProtection\\StorageKeyProvider;\n";
        self::assertNotEmpty($this->c3ForbiddenReferenceViolations($forbiddenMutation));

        $generalProjection = $sources;
        $generalProjection['src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php'] .= <<<'PHP'

            new BlackOpsInternalProjectionSensitiveProjectionFilter();
            PHP;
        self::assertNotEmpty($this->c3ForbiddenReferenceViolations($generalProjection));

        $fallbackDelivery = $sources;
        $fallbackDelivery['src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php'] = str_replace(
            "    private function buildId(): string\n    {",
            "    private function buildId(): string\n    {\n        \$fallbackDelivery->deliverStandalone(\$request);",
            $fallbackDelivery['src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3ForbiddenReferenceViolations($fallbackDelivery));

        $otherFallbackDelivery = $sources;
        $otherFallbackDelivery['src/Internal/Application/ApplicationAuditRuntime.php'] .= <<<'PHP'

            $fallbackDelivery->deliverStandalone($request);
            PHP;
        self::assertNotEmpty($this->c3ForbiddenReferenceViolations($otherFallbackDelivery));

        $extraDependency = $sources;
        $extraDependency['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'] = str_replace(
            'use BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator;',
            "use BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator;\nuse BlackOps\\Core\\Audit\\AuditDelivery;",
            $extraDependency['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DependencyViolations($extraDependency));

        $bodyFqcn = $sources;
        $bodyFqcn['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'] .= <<<'PHP'

            new \BlackOps\Internal\Logging\ExecutionScopedLogger();
            PHP;
        self::assertNotEmpty($this->c3DependencyViolations($bodyFqcn));

        $externalBodyFqcn = $sources;
        $externalBodyFqcn['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'] .= <<<'PHP'

            new \Doctrine\DBAL\Query\QueryBuilder();
            PHP;
        self::assertNotEmpty($this->c3DependencyViolations($externalBodyFqcn));

        $namespaceMutation = $sources;
        $namespaceMutation['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'] = str_replace(
            'namespace BlackOps\\Internal\\Audit\\Orchestration;',
            'namespace BlackOps\\Internal\\Audit\\Mutated;',
            $namespaceMutation['src/Internal/Audit/Orchestration/AuditDeliverySelector.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->c3DependencyViolations($namespaceMutation));

        $outsideForbidden = $sources;
        $outsideForbidden['src/Internal/External/C3ForbiddenOwner.php'] = <<<'PHP'
            <?php

            use BlackOps\Internal\Application\ApplicationAuditRuntime;

            final class C3ForbiddenOwner
            {
                public function run(ApplicationAuditRuntime $runtime): void
                {
                    getenv('BLACKOPS_AUDIT_KEY');
                    $runtime->downgrade();
                }
            }
            PHP;
        self::assertNotEmpty($this->c3ForbiddenReferenceViolations($outsideForbidden));
    }

    /** @return list<string> */
    private function c3ExpectedPaths(): array
    {
        return [
            'src/Audit/AuditOpaqueIdKeyProvider.php',
            'src/Audit/AuditProtectionKeyProvider.php',
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php',
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php',
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php',
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php',
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php',
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php',
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php',
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php',
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php',
            'src/Internal/Application/ApplicationAuditConfiguration.php',
            'src/Internal/Application/ApplicationAuditRuntime.php',
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php',
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php',
        ];
    }

    /** @return array<string,string> */
    private function c3Sources(): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $relative = 'src/' . str_replace($root . '/', '', $path);
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources[$relative] = $source;
        }
        ksort($sources);

        return $sources;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function c3Inventory(array $sources): array
    {
        $paths = [];
        foreach (array_keys($sources) as $path) {
            if (
                str_starts_with($path, 'src/Audit/')
                || str_starts_with($path, 'src/Internal/Audit/Orchestration/')
                || $path === 'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php'
                || $path === 'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php'
                || preg_match('#^src/Internal/Application/ApplicationAudit[^/]*\.php$#', $path) === 1
            ) {
                $paths[] = $path;
            }
        }
        $ordered = [];
        foreach ($this->c3ExpectedPaths() as $expected) {
            if (in_array($expected, $paths, true)) {
                $ordered[] = $expected;
            }
        }
        $extras = array_values(array_diff($paths, $ordered));
        sort($extras);

        return [...$ordered, ...$extras];
    }

    /** @param array<string,string> $sources @return array<string,array{declaration:string,structure:string}> */
    private function c3DeclarationBaseline(array $sources): array
    {
        $expected = $this->c3ExpectedDeclarations();
        $baseline = [];
        foreach ($this->c3ExpectedPaths() as $path) {
            self::assertArrayHasKey($path, $sources);
            $baseline[$path] = [
                'declaration' => $expected[$path]['declaration'],
                'structure' => $expected[$path]['structure'],
            ];
        }

        return $baseline;
    }

    /** @param array<string,string> $sources @param array<string,array{declaration:string,structure:string}> $baseline @return list<string> */
    private function c3DeclarationViolations(array $sources, array $baseline): array
    {
        $violations = [];
        if ($this->c3Inventory($sources) !== $this->c3ExpectedPaths()) {
            $violations[] = 'inventory';
        }
        foreach ($this->c3ExpectedPaths() as $path) {
            if (!array_key_exists($path, $sources) || !array_key_exists($path, $baseline)) {
                $violations[] = 'missing:' . $path;
                continue;
            }
            if ($this->c3Declaration($sources[$path]) !== $baseline[$path]['declaration']) {
                $violations[] = 'declaration:' . $path;
            }
            if ($this->c3Structure($sources[$path]) !== $baseline[$path]['structure']) {
                $violations[] = 'structure:' . $path;
            }
            if ($this->c3SignatureViolations($sources[$path], $path) !== []) {
                $violations[] = 'signature:' . $path;
            }
            $expectedParent = $path === 'src/Internal/Audit/Orchestration/AuditDeliveryException.php'
                ? 'RuntimeException'
                : null;
            if ($expectedParent !== null && $this->c3ParentClass($sources[$path]) !== $expectedParent) {
                $violations[] = 'parent:' . $path;
            }
            if ($this->c3HasParameterDefault($sources[$path])) {
                $violations[] = 'default:' . $path;
            }
        }

        return $violations;
    }

    /** @return array<string,array{declaration:string,structure:string}> */
    private function c3ExpectedDeclarations(): array
    {
        $members = [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => ['method:key'],
            'src/Audit/AuditProtectionKeyProvider.php' => [
                'method:activeRecordKey',
                'method:recordKey',
                'method:activeOutboxKey',
                'method:outboxKey',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php' => [
                'case:ConfigurationInvalid',
                'case:PolicyInvalid',
                'case:TransactionInvalid',
                'case:KeyUnavailable',
                'case:ResourceUnavailable',
                'case:StorageUnavailable',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => [
                'method:__construct',
                'method:failure',
                'method:code',
                'method:__debugInfo',
                'method:__serialize',
                'method:__unserialize',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => [
                'method:__construct',
                'method:fromSelection',
                'method:catalogHash',
                'method:eventType',
                'method:eventVersion',
                'method:evidenceVersion',
                'method:delivery',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => [
                'property:$snapshot',
                'method:__construct',
                'method:draft',
                'method:idempotencyKey',
                'method:__debugInfo',
                'method:__serialize',
                'method:__unserialize',
                'method:value',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => [
                'method:__construct',
                'method:select',
                'method:forDraft',
                'method:validate',
                'method:member',
                'method:definition',
                'method:policyFailure',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => [
                'method:__construct',
                'method:usesConnection',
                'method:deliverStandalone',
                'method:deliverInCurrentTransaction',
                'method:append',
                'method:assertStandaloneTransaction',
                'method:assertCurrentTransaction',
                'method:policyFailure',
                'method:resourceFailure',
                'method:storageFailure',
            ],
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => [
                'method:__construct',
                'method:execute',
                'method:assertMetadata',
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                'method:__construct',
                'method:requested',
                'method:terminal',
                'method:assertBinding',
            ],
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => [
                'property:$snapshot',
                'method:__construct',
                'method:activeKey',
                'method:key',
                'method:validated',
                'method:provider',
                'method:partitions',
                'method:opaqueKey',
                'method:value',
            ],
            'src/Internal/Application/ApplicationAuditConfiguration.php' => [
                'method:__construct',
                'method:fromConfiguration',
                'method:outbox',
            ],
            'src/Internal/Application/ApplicationAuditRuntime.php' => [
                'method:__construct',
                'method:fromComposition',
                'method:outboxConfiguration',
                'method:opaqueIds',
                'method:deliverySelector',
                'method:delivery',
                'method:mutations',
                'method:protectedReads',
                'method:records',
                'method:outbox',
                'method:relay',
            ],
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                'method:compose',
                'method:database',
                'method:assertConnection',
                'method:providers',
            ],
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => [
                'property:$snapshot',
                'method:__construct',
                'method:assertConnection',
                'method:placed',
                'method:released',
                'method:__debugInfo',
                'method:__serialize',
                'method:__unserialize',
                'method:write',
                'method:catalog',
                'method:definition',
                'method:occurredAt',
                'method:opaqueId',
                'method:draft',
                'method:selector',
                'method:reference',
                'method:value',
                'method:failure',
                'method:remap',
            ],
        ];
        $declarations = [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => 'interface AuditOpaqueIdKeyProvider',
            'src/Audit/AuditProtectionKeyProvider.php' => 'interface AuditProtectionKeyProvider',
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php' => 'enum AuditDeliveryFailure',
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => 'final class AuditDeliveryException',
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => 'final readonly class AuditDeliveryPlan',
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => 'final class AuditDeliveryRequest',
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => 'final readonly class AuditDeliverySelector',
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => 'final readonly class AuditDeliveryCoordinator',
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => 'final readonly class AuditMutationTransactionCoordinator',
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => 'final readonly class AuditProtectedReadGate',
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => 'final readonly class ApplicationAuditProtectionKeySource',
            'src/Internal/Application/ApplicationAuditConfiguration.php' => 'final readonly class ApplicationAuditConfiguration',
            'src/Internal/Application/ApplicationAuditRuntime.php' => 'final readonly class ApplicationAuditRuntime',
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 'final readonly class ApplicationAuditRuntimeComposer',
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 'final class RetentionHoldAuditWriter',
        ];
        $expected = [];
        foreach ($this->c3ExpectedPaths() as $path) {
            $expected[$path] = [
                'declaration' => $declarations[$path],
                'structure' => 'declarations:1;members:' . implode(',', $members[$path]),
            ];
        }

        return $expected;
    }

    /** @return array<string,array<string,array{visibility:string,readonly:bool,promoted:bool,type:string,nullable:bool,sensitive:int}>> */
    private function c3ExpectedPropertyMatrix(): array
    {
        $empty = [];

        return [
            AuditOpaqueIdKeyProvider::class => $empty,
            AuditProtectionKeyProvider::class => $empty,
            AuditDeliveryFailure::class => [
                'name' => [
                    'visibility' => 'public',
                    'readonly' => true,
                    'promoted' => false,
                    'type' => 'string',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'value' => [
                    'visibility' => 'public',
                    'readonly' => true,
                    'promoted' => false,
                    'type' => 'string',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            AuditDeliveryException::class => [
                'failure' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditDeliveryFailure::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            AuditDeliveryPlan::class => [
                'catalogHash' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'string',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'eventType' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'string',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'eventVersion' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'int',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'evidenceVersion' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'int',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'delivery' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Core\\Audit\\AuditDelivery',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            AuditDeliveryRequest::class => [
                'snapshot' => [
                    'visibility' => 'private',
                    'readonly' => false,
                    'promoted' => false,
                    'type' => 'SensitiveParameterValue',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            AuditDeliverySelector::class => [
                'lookup' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Catalog\\ImmutableAuditEventCatalogLookup',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'validator' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            AuditDeliveryCoordinator::class => [
                'connection' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'Doctrine\\DBAL\\Connection',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'selector' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditDeliverySelector::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'records' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'outbox' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            AuditMutationTransactionCoordinator::class => [
                'transactions' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Transaction\\OperationTransactionCoordinator',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'delivery' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditDeliveryCoordinator::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            AuditProtectedReadGate::class => [
                'delivery' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditDeliveryCoordinator::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            ApplicationAuditProtectionKeySource::class => [
                'snapshot' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => false,
                    'type' => 'SensitiveParameterValue',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            ApplicationAuditConfiguration::class => [
                'outbox' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            ApplicationAuditRuntime::class => [
                'outboxConfiguration' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'opaqueIds' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'deliverySelector' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditDeliverySelector::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'delivery' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditDeliveryCoordinator::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'mutations' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditMutationTransactionCoordinator::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'protectedReads' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => AuditProtectedReadGate::class,
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'records' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'outbox' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
                'relay' => [
                    'visibility' => 'private',
                    'readonly' => true,
                    'promoted' => true,
                    'type' => 'BlackOps\\Internal\\Audit\\Relay\\AuditOutboxRelayRuntime',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
            ApplicationAuditRuntimeComposer::class => $empty,
            RetentionHoldAuditWriter::class => [
                'snapshot' => [
                    'visibility' => 'private',
                    'readonly' => false,
                    'promoted' => false,
                    'type' => 'SensitiveParameterValue',
                    'nullable' => false,
                    'sensitive' => 0,
                ],
            ],
        ];
    }

    /** @return array<string,array<string,array{visibility:string,readonly:bool,promoted:bool,type:string,nullable:bool,sensitive:int}>> */
    private function c3PropertyMatrix(): array
    {
        $actual = [];
        foreach ($this->c3ExpectedPropertyMatrix() as $type => $expected) {
            $properties = [];
            foreach (new ReflectionClass($type)->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $type) {
                    continue;
                }
                $typeInfo = $property->getType();
                $properties[$property->getName()] = [
                    'visibility' => $property->isPrivate()
                        ? 'private'
                        : ($property->isProtected() ? 'protected' : 'public'),
                    'readonly' => $property->isReadOnly(),
                    'promoted' => $property->isPromoted(),
                    'type' => $typeInfo?->getName() ?? '',
                    'nullable' => $typeInfo?->allowsNull() ?? false,
                    'sensitive' => count($property->getAttributes(\SensitiveParameter::class)),
                ];
            }
            $actual[$type] = $properties;
        }

        return $actual;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function c3DependencyViolations(array $sources): array
    {
        $expectedNamespaces = [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => 'BlackOps\\Audit',
            'src/Audit/AuditProtectionKeyProvider.php' => 'BlackOps\\Audit',
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => 'BlackOps\\Internal\\Audit\\Orchestration',
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => 'BlackOps\\Internal\\Audit\\Key',
            'src/Internal/Application/ApplicationAuditConfiguration.php' => 'BlackOps\\Internal\\Application',
            'src/Internal/Application/ApplicationAuditRuntime.php' => 'BlackOps\\Internal\\Application',
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 'BlackOps\\Internal\\Application',
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 'BlackOps\\Internal\\Audit\\Retention',
        ];
        $expectedFullyQualifiedNames = [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => [],
            'src/Audit/AuditProtectionKeyProvider.php' => ['\\SensitiveParameter'],
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php' => [],
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => ['\\SensitiveParameter'],
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => [
                '\\SensitiveParameter',
                '\\RuntimeException',
                '\\Throwable',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => [
                '\\SensitiveParameterValue',
                '\\SensitiveParameter',
                '\\Throwable',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => [
                '\\SensitiveParameter',
                '\\RuntimeException',
                '\\Throwable',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => [
                '\\SensitiveParameter',
                '\\Throwable',
                '\\RuntimeException',
            ],
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => [
                '\\SensitiveParameter',
                '\\Throwable',
                '\\RuntimeException',
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                '\\SensitiveParameter',
                '\\Throwable',
            ],
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => [
                '\\SensitiveParameterValue',
                '\\SensitiveParameter',
                '\\Throwable',
                '\\RuntimeException',
            ],
            'src/Internal/Application/ApplicationAuditConfiguration.php' => [
                '\\SensitiveParameter',
                '\\RuntimeException',
                '\\Throwable',
            ],
            'src/Internal/Application/ApplicationAuditRuntime.php' => ['\\SensitiveParameter'],
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                '\\SensitiveParameter',
                '\\Throwable',
                '\\BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator',
                '\\RuntimeException',
            ],
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => [
                '\\DateTimeZone',
                '\\LogicException',
                '\\SensitiveParameter',
                '\\SensitiveParameterValue',
            ],
        ];
        $expected = [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => [
                'use BlackOps\\Core\\Attribute\\PublicApi;',
                'use BlackOps\\StorageProtection\\StorageKey;',
            ],
            'src/Audit/AuditProtectionKeyProvider.php' => [
                'use BlackOps\\Core\\Attribute\\PublicApi;',
                'use BlackOps\\StorageProtection\\StorageKey;',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php' => [],
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => [
                'use RuntimeException;',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => [
                'use BlackOps\\Core\\Audit\\AuditDelivery;',
                'use BlackOps\\Core\\Audit\\AuditValidationException;',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => [
                'use BlackOps\\Core\\Audit\\AuditRecordDraft;',
                'use BlackOps\\Core\\Audit\\AuditValidationException;',
                'private \\SensitiveParameterValue $snapshot;',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => [
                'use BlackOps\\Core\\Audit\\AuditDelivery;',
                'use BlackOps\\Core\\Audit\\AuditEventCatalog;',
                'use BlackOps\\Core\\Audit\\AuditEventDefinition;',
                'use BlackOps\\Core\\Audit\\AuditRecordDraft;',
                'use BlackOps\\Internal\\Audit\\Catalog\\ImmutableAuditEventCatalogLookup;',
                'use BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator;',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => [
                'use BlackOps\\Core\\Audit\\AuditDelivery;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure;',
                'use BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore;',
                'use BlackOps\\Internal\\Audit\\Storage\\AuditStoreException;',
                'use BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure;',
                'use BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore;',
                'use Doctrine\\DBAL\\Connection;',
            ],
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => [
                'use BlackOps\\Core\\OperationResult;',
                'use BlackOps\\Core\\Registry\\OperationMetadata;',
                'use BlackOps\\Internal\\Transaction\\OperationTransactionCoordinator;',
                'use Closure;',
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
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => [
                'use BlackOps\\Audit\\AuditProtectionKeyProvider;',
                'use BlackOps\\Core\\Audit\\AuditTypedReference;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditProtectionKeySource;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditProtectionPurpose;',
                'use BlackOps\\Internal\\Audit\\Record\\AuditPartitionIdDeriver;',
                'use BlackOps\\StorageProtection\\StorageKey;',
            ],
            'src/Internal/Application/ApplicationAuditConfiguration.php' => [
                'use BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure;',
            ],
            'src/Internal/Application/ApplicationAuditRuntime.php' => [
                'use BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration;',
                'use BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditMutationTransactionCoordinator;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditProtectedReadGate;',
                'use BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore;',
                'use BlackOps\\Internal\\Audit\\Relay\\AuditOutboxRelayRuntime;',
                'use BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore;',
            ],
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                'use BlackOps\\Audit\\AuditOpaqueIdKeyProvider;',
                'use BlackOps\\Audit\\AuditProtectionKeyProvider;',
                'use BlackOps\\Internal\\Audit\\AuditJcsCodec;',
                'use BlackOps\\Internal\\Audit\\Catalog\\ImmutableAuditEventCatalogLookup;',
                'use BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver;',
                'use BlackOps\\Internal\\Audit\\Key\\ApplicationAuditProtectionKeySource;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditMutationTransactionCoordinator;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditProtectedReadGate;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxIdempotencyKeyHasher;',
                'use BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxRecordHydrator;',
                'use BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxSchema;',
                'use BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore;',
                'use BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditProtectedIngressHydrator;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditAssociatedData;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditIngressEnvelopeCodec;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec;',
                'use BlackOps\\Internal\\Audit\\Record\\AuditPartitionIdDeriver;',
                'use BlackOps\\Internal\\Audit\\Record\\AuditRecordCodec;',
                'use BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer;',
                'use BlackOps\\Internal\\Audit\\Relay\\AuditIngressCanonicalizer;',
                'use BlackOps\\Internal\\Audit\\Relay\\AuditOutboxRelayRuntime;',
                'use BlackOps\\Internal\\Audit\\Relay\\PostgreSqlAuditRelayPersistence;',
                'use BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore;',
                'use BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditSchema;',
                'use BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditStoredRecordHydrator;',
                'use BlackOps\\Internal\\Identifier\\SymfonyUuidv7Generator;',
                'use BlackOps\\Internal\\Migration\\PostgreSqlMigrationSchema;',
                'use BlackOps\\Internal\\StorageProtection\\BopdBinaryEnvelopeCodec;',
                'use BlackOps\\Internal\\StorageProtection\\RandomNonceSource;',
                'use BlackOps\\StorageProtection\\StorageKey;',
                'use Psr\\Container\\ContainerInterface;',
            ],
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => [
                'use BlackOps\\Core\\ActorContext;',
                'use BlackOps\\Core\\ActorRef;',
                'use BlackOps\\Core\\Audit\\AuditActionPhase;',
                'use BlackOps\\Core\\Audit\\AuditDelivery;',
                'use BlackOps\\Core\\Audit\\AuditEventCatalog;',
                'use BlackOps\\Core\\Audit\\AuditEventCategory;',
                'use BlackOps\\Core\\Audit\\AuditEventDefinition;',
                'use BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose;',
                'use BlackOps\\Core\\Audit\\AuditOutcomeStatus;',
                'use BlackOps\\Core\\Audit\\AuditPolicyDecision;',
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
                'use BlackOps\\Core\\Audit\\AuditSemanticTuple;',
                'use BlackOps\\Core\\Audit\\AuditSourceOwner;',
                'use BlackOps\\Core\\Audit\\AuditSourceRuntime;',
                'use BlackOps\\Core\\Audit\\AuditTypedReference;',
                'use BlackOps\\Core\\Audit\\AuditValidationException;',
                'use BlackOps\\Core\\Identifier\\OperationId;',
                'use BlackOps\\Core\\Identifier\\RetentionHoldId;',
                'use BlackOps\\Core\\TenantRef;',
                'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog;',
                'use BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest;',
                'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector;',
                'use Doctrine\\DBAL\\Connection;',
                'use Psr\\Clock\\ClockInterface;',
                'use Throwable;',
            ],
        ];
        $violations = [];
        foreach ($this->c3ExpectedTargetReferenceMatrix() as $path => $expectedTargets) {
            if (!array_key_exists($path, $sources)) {
                continue;
            }
            sort($expectedTargets);
            $actualTargets = $this->c3ReferencedTargetClasses($sources[$path]);
            if ($actualTargets !== $expectedTargets) {
                $violations[] = 'target-dependencies:' . $path;
            }
        }
        foreach ($expected as $path => $fragments) {
            if (!array_key_exists($path, $sources)) {
                $violations[] = 'missing:' . $path;
                continue;
            }
            [$namespace] = $this->c3NameContext(token_get_all($sources[$path]));
            if ($namespace !== $expectedNamespaces[$path]) {
                $violations[] = 'namespace:' . $path;
            }
            $actualFullyQualifiedNames = $this->c3FullyQualifiedNames($sources[$path]);
            $expectedNames = $expectedFullyQualifiedNames[$path];
            sort($expectedNames);
            if ($actualFullyQualifiedNames !== $expectedNames) {
                $violations[] = 'fully-qualified-names:' . $path;
            }
            $expectedImports = [];
            $requiredFragments = [];
            foreach ($fragments as $fragment) {
                if (str_starts_with($fragment, 'use ')) {
                    $expectedImports[] = $this->c3CodeWithoutComments($fragment);
                } else {
                    $requiredFragments[] = $fragment;
                }
            }
            if ($this->c3ImportInventory($sources[$path]) !== $expectedImports) {
                $violations[] = 'imports:' . $path;
            }
            $source = $this->c3CodeWithoutComments($sources[$path]);
            foreach ($requiredFragments as $fragment) {
                if (!str_contains($source, $this->c3CodeWithoutComments($fragment))) {
                    $violations[] = $path . ':' . $fragment;
                }
            }
            foreach ([
                'ResponseEmitter',
                'SapiResponseEmitter',
                'ResponseCallback',
                'Psr\\Http',
                'HttpFoundation',
            ] as $needle) {
                if (str_contains($this->c3ExecutableCode($sources[$path]), $needle)) {
                    $violations[] = 'dependency:' . $path . ':' . $needle;
                }
            }
        }

        return $violations;
    }

    /** @return array<string,list<string>> */
    private function c3ExpectedTargetReferenceMatrix(): array
    {
        $target = static fn(string $name): string => 'BlackOps\\Internal\\Audit\\Orchestration\\' . $name;

        return [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => ['BlackOps\\Audit\\AuditOpaqueIdKeyProvider'],
            'src/Audit/AuditProtectionKeyProvider.php' => ['BlackOps\\Audit\\AuditProtectionKeyProvider'],
            'src/Internal/Audit/Orchestration/AuditDeliveryFailure.php' => [$target('AuditDeliveryFailure')],
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => [
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => [
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
                $target('AuditDeliveryPlan'),
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => [
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
                $target('AuditDeliveryRequest'),
            ],
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => [
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
                $target('AuditDeliveryPlan'),
                $target('AuditDeliveryRequest'),
                $target('AuditDeliverySelector'),
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => [
                $target('AuditDeliveryCoordinator'),
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
                $target('AuditDeliveryPlan'),
                $target('AuditDeliveryRequest'),
                $target('AuditDeliverySelector'),
            ],
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => [
                $target('AuditDeliveryCoordinator'),
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
                $target('AuditDeliveryPlan'),
                $target('AuditDeliveryRequest'),
                $target('AuditMutationTransactionCoordinator'),
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                $target('AuditDeliveryCoordinator'),
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
                $target('AuditDeliveryRequest'),
                $target('AuditProtectedReadGate'),
            ],
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => [
                'BlackOps\\Audit\\AuditProtectionKeyProvider',
                'BlackOps\\Internal\\Audit\\Key\\ApplicationAuditProtectionKeySource',
            ],
            'src/Internal/Application/ApplicationAuditConfiguration.php' => [
                'BlackOps\\Internal\\Application\\ApplicationAuditConfiguration',
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
            ],
            'src/Internal/Application/ApplicationAuditRuntime.php' => [
                'BlackOps\\Internal\\Application\\ApplicationAuditRuntime',
                'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                $target('AuditDeliveryCoordinator'),
                $target('AuditDeliverySelector'),
                $target('AuditMutationTransactionCoordinator'),
                $target('AuditProtectedReadGate'),
            ],
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                'BlackOps\\Audit\\AuditOpaqueIdKeyProvider',
                'BlackOps\\Audit\\AuditProtectionKeyProvider',
                'BlackOps\\Internal\\Application\\ApplicationAuditConfiguration',
                'BlackOps\\Internal\\Application\\ApplicationAuditRuntime',
                'BlackOps\\Internal\\Application\\ApplicationAuditRuntimeComposer',
                'BlackOps\\Internal\\Application\\ApplicationOperationRuntimeComposition',
                'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                'BlackOps\\Internal\\Audit\\Key\\ApplicationAuditProtectionKeySource',
                $target('AuditDeliveryCoordinator'),
                $target('AuditDeliveryException'),
                $target('AuditDeliveryFailure'),
                $target('AuditDeliverySelector'),
                $target('AuditMutationTransactionCoordinator'),
                $target('AuditProtectedReadGate'),
            ],
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => [
                'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
                'BlackOps\\Internal\\Audit\\Retention\\RetentionHoldAuditWriter',
            ],
        ];
    }

    /** @return list<string> */
    private function c3ReferencedTargetClasses(string $source): array
    {
        $tokens = token_get_all($source);
        [$namespace, $aliases] = $this->c3NameContext($tokens);
        $targets = array_map(static fn(string $target): string => ltrim($target, '\\'), $this->c3TargetClasses());
        $references = [];
        foreach ($tokens as $index => $token) {
            if (
                !is_array($token)
                || !in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
            ) {
                continue;
            }
            $resolved = $this->c3ResolveName($token[1], $namespace, $aliases);
            if (in_array($resolved, $targets, true)) {
                $references[] = $resolved;
            }
            if ($token[0] !== T_STRING) {
                continue;
            }
            $separator = $this->c3NextToken($tokens, $index + 1);
            if ($separator !== null && $tokens[$separator] === '\\') {
                $parts = [$token[1]];
                for ($cursor = $separator; $cursor !== null && $tokens[$cursor] === '\\';) {
                    $part = $this->c3NextToken($tokens, $cursor + 1);
                    if ($part === null || !is_array($tokens[$part])) {
                        break;
                    }
                    $parts[] = $tokens[$part][1];
                    $cursor = $this->c3NextToken($tokens, $part + 1);
                }
                $resolved = $this->c3ResolveName(implode('\\', $parts), $namespace, $aliases);
                if (in_array($resolved, $targets, true)) {
                    $references[] = $resolved;
                }
            }
        }
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @return list<string> */
    private function c3FullyQualifiedNames(string $source): array
    {
        $references = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_NAME_FULLY_QUALIFIED) {
                $references[] = $token[1];
            }
        }
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @return list<string> */
    private function c3ImportInventory(string $source): array
    {
        $code = $this->c3CodeWithoutComments($source);
        $declaration = strlen($code);
        foreach (['interface', 'enum', 'class', 'trait'] as $keyword) {
            $position = strpos($code, $keyword);
            if ($position !== false) {
                $declaration = min($declaration, $position);
            }
        }
        $header = substr($code, 0, $declaration);
        preg_match_all('/use[^;]+;/', $header, $matches);
        self::assertIsArray($matches[0]);

        return array_values($matches[0]);
    }

    /** @param array<string,string> $sources @return list<string> */
    private function c3ForbiddenReferenceViolations(array $sources): array
    {
        $forbidden = [
            'StorageKeyProvider',
            'StoragePurpose',
            'SAVEPOINT',
            'savepoint',
            'beginTransaction',
            '->commit(',
            '->rollBack(',
            'new TransactionRuntime(',
            'new OperationTransactionCoordinator(',
            'OutboxRelay',
            'ObservedAudit',
            'Projection',
            'projection',
            '->relay(',
            'afterCommit(',
            'getenv(',
            '$_ENV',
            'fallback',
            'downgrade',
            'ResponseEmitter',
            'SapiResponseEmitter',
            'ResponseCallback',
            'Psr\\Http',
            'HttpFoundation',
        ];
        $expectedPaths = $this->c3ExpectedPaths();
        $violations = [];
        foreach ($sources as $path => $rawSource) {
            $isC3Owner = in_array($path, $expectedPaths, true) || $this->c3ReferencesTargetClass($rawSource);
            if (!$isC3Owner) {
                continue;
            }
            foreach ($forbidden as $needle) {
                if (
                    $needle === 'Psr\\Http'
                    && in_array(
                        $path,
                        [
                            'src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php',
                        ],
                        true,
                    )
                ) {
                    continue;
                }
                if (
                    $needle === 'OutboxRelay'
                    && in_array(
                        $path,
                        [
                            'src/Internal/Application/ApplicationAuditRuntime.php',
                            'src/Internal/Application/ApplicationAuditRuntimeComposer.php',
                        ],
                        true,
                    )
                ) {
                    continue;
                }
                if ($this->c3ContainsForbiddenNeedle($rawSource, $path, $needle)) {
                    $violations[] = $path . ':' . $needle;
                }
            }
        }

        return $violations;
    }

    private function c3ContainsForbiddenNeedle(string $rawSource, string $path, string $needle): bool
    {
        if (!in_array($needle, ['Projection', 'fallback'], true)) {
            return str_contains($this->c3ExecutableCode($rawSource), $needle);
        }
        $tokens = token_get_all($rawSource);
        foreach ($tokens as $index => $token) {
            if (
                is_array($token)
                && in_array(
                    $token[0],
                    [
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
            $text = is_array($token) ? $token[1] : $token;
            if (!str_contains($text, $needle)) {
                continue;
            }
            if (
                $needle === 'Projection'
                && $path === 'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php'
                && is_array($token)
                && $token[0] === T_STRING
                && $token[1] === 'assertProjectionAgreement'
                && $this->c3IsProjectionAgreementReference($tokens, $index)
            ) {
                continue;
            }
            if (
                $needle === 'fallback'
                && $path === 'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php'
                && is_array($token)
                && $token[0] === T_VARIABLE
                && $token[1] === '$fallback'
                && $this->c3IsExecutionFallbackVariable($tokens, $index)
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3IsProjectionAgreementReference(array $tokens, int $index): bool
    {
        $next = $this->c3NextToken($tokens, $index + 1);
        if ($next === null || $tokens[$next] !== '(') {
            return false;
        }
        $previous = $this->c3PreviousToken($tokens, $index - 1);
        if ($previous === null) {
            return false;
        }
        if (is_array($tokens[$previous]) && $tokens[$previous][0] === T_FUNCTION) {
            return true;
        }
        if (!$this->c3Operator($tokens[$previous], [T_DOUBLE_COLON])) {
            return false;
        }
        $self = $this->c3PreviousToken($tokens, $previous - 1);

        return (
            $self !== null
            && is_array($tokens[$self])
            && $tokens[$self][0] === T_STRING
            && strtolower($tokens[$self][1]) === 'self'
        );
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3IsExecutionFallbackVariable(array $tokens, int $index): bool
    {
        for ($cursor = $index; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];
            if (is_array($token) && $token[0] === T_FN) {
                return false;
            }
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $name = $this->c3NextToken($tokens, $cursor + 1);

            return (
                $name !== null
                && is_array($tokens[$name])
                && $tokens[$name][0] === T_STRING
                && strtolower($tokens[$name][1]) === 'executionfallback'
            );
        }

        return false;
    }

    private function c3ReferencesTargetClass(string $source): bool
    {
        $tokens = token_get_all($source);
        [$namespace, $aliases] = $this->c3NameContext($tokens);
        $targets = array_values(array_filter(
            array_map(static fn(string $target): string => ltrim($target, '\\'), $this->c3TargetClasses()),
            static fn(string $target): bool => !in_array(
                $target,
                [
                    'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                    'BlackOps\\Internal\\Audit\\Retention\\RetentionHoldAuditWriter',
                ],
                true,
            ),
        ));
        foreach ($tokens as $index => $token) {
            if (
                !is_array($token)
                || !in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
            ) {
                continue;
            }
            $raw = $token[1];
            $resolved = $this->c3ResolveName($raw, $namespace, $aliases);
            if (in_array($resolved, $targets, true)) {
                return true;
            }
            if ($token[0] !== T_STRING) {
                continue;
            }
            $next = $this->c3NextToken($tokens, $index + 1);
            if ($next !== null && $tokens[$next] === '\\') {
                $parts = [$raw];
                for ($cursor = $next; $cursor !== null && $tokens[$cursor] === '\\';) {
                    $part = $this->c3NextToken($tokens, $cursor + 1);
                    if ($part === null || !is_array($tokens[$part])) {
                        break;
                    }
                    $parts[] = $tokens[$part][1];
                    $cursor = $this->c3NextToken($tokens, $part + 1);
                }
                if (in_array($this->c3ResolveName(implode('\\', $parts), $namespace, $aliases), $targets, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function c3ExecutableCode(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (
                is_array($token)
                && in_array(
                    $token[0],
                    [
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
            $code .= is_array($token) ? $token[1] : $token;
        }
        $code = preg_replace('/\s+/', '', $code);
        self::assertIsString($code);

        return $code;
    }

    private function c3Declaration(string $source): string
    {
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_CLASS, T_INTERFACE, T_ENUM], true)) {
                continue;
            }
            $next = $this->c3NextToken($tokens, $index + 1);
            if ($next === null || !is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) {
                continue;
            }
            $modifiers = [];
            for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
                $candidate = $tokens[$cursor];
                if ($candidate === ';' || $candidate === '}') {
                    break;
                }
                if (is_array($candidate) && in_array($candidate[0], [T_FINAL, T_READONLY], true)) {
                    $modifiers[] = strtolower($candidate[1]);
                }
            }
            $modifiers = array_reverse($modifiers);
            $kind = match ($token[0]) {
                T_INTERFACE => 'interface',
                T_ENUM => 'enum',
                default => 'class',
            };

            return implode(' ', [...$modifiers, $kind, $tokens[$next][1]]);
        }

        return '';
    }

    private function c3ParentClass(string $source): ?string
    {
        $tokens = token_get_all($source);
        [$namespace, $aliases] = $this->c3NameContext($tokens);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }
            $name = $this->c3NextToken($tokens, $index + 1);
            if ($name === null || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING) {
                continue;
            }
            for (
                $cursor = $this->c3NextToken($tokens, $name + 1);
                $cursor !== null;
                $cursor = $this->c3NextToken($tokens, $cursor + 1)
            ) {
                if ($tokens[$cursor] === '{' || $tokens[$cursor] === ';') {
                    break;
                }
                if (!is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_EXTENDS) {
                    continue;
                }
                $parts = [];
                for (
                    $parent = $this->c3NextToken($tokens, $cursor + 1);
                    $parent !== null;
                    $parent = $this->c3NextToken($tokens, $parent + 1)
                ) {
                    $candidate = $tokens[$parent];
                    if ($candidate === '{' || $candidate === ';' || $candidate === T_IMPLEMENTS) {
                        break;
                    }
                    if ($candidate === ',') {
                        break;
                    }
                    $text = is_array($candidate) ? $candidate[1] : $candidate;
                    if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\\\\\]*$/', $text) === 1 || $text === '\\\\') {
                        $parts[] = $text;
                    }
                }

                return $parts === [] ? null : $this->c3ResolveName(implode('', $parts), $namespace, $aliases);
            }
        }

        return null;
    }

    /** @return list<string> */
    private function c3SignatureViolations(string $source, string $path): array
    {
        $code = $this->c3CodeWithoutComments($source);
        $expected = [
            'src/Audit/AuditOpaqueIdKeyProvider.php' => [
                'publicfunctionkey():StorageKey',
            ],
            'src/Audit/AuditProtectionKeyProvider.php' => [
                'publicfunctionactiveRecordKey(#[\SensitiveParameter]string$partitionId):StorageKey',
                'publicfunctionrecordKey(#[\SensitiveParameter]string$keyId,#[\SensitiveParameter]string$partitionId):StorageKey',
                'publicfunctionactiveOutboxKey(#[\SensitiveParameter]string$partitionId):StorageKey',
                'publicfunctionoutboxKey(#[\SensitiveParameter]string$keyId,#[\SensitiveParameter]string$partitionId):StorageKey',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryException.php' => [
                'publicfunction__construct(privatereadonlyAuditDeliveryFailure$failure)',
                'publicfunctionfailure():AuditDeliveryFailure',
                'publicfunctioncode():string',
                'publicfunction__debugInfo():array',
                'publicfunction__serialize():array',
                'publicfunction__unserialize(#[\SensitiveParameter]array$data):void',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => [
                'privatefunction__construct(#[\SensitiveParameter]privatestring$catalogHash,#[\SensitiveParameter]privatestring$eventType,#[\SensitiveParameter]privateint$eventVersion,#[\SensitiveParameter]privateint$evidenceVersion,privateAuditDelivery$delivery)',
                'publicstaticfunctionfromSelection(',
                'publicfunctioncatalogHash():string',
                'publicfunctioneventType():string',
                'publicfunctioneventVersion():int',
                'publicfunctionevidenceVersion():int',
                'publicfunctiondelivery():AuditDelivery',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryRequest.php' => [
                'private\SensitiveParameterValue$snapshot;',
                'publicfunction__construct(#[\SensitiveParameter]AuditRecordDraft$draft,#[\SensitiveParameter]?string$idempotencyKey)',
                'publicfunctiondraft():AuditRecordDraft',
                'publicfunctionidempotencyKey():?string',
                'publicfunction__debugInfo():array',
                'publicfunction__serialize():array',
                'publicfunction__unserialize(#[\SensitiveParameter]array$data):void',
                'privatefunctionvalue(#[\SensitiveParameter]string$key):mixed',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliverySelector.php' => [
                'publicfunction__construct(privateImmutableAuditEventCatalogLookup$lookup,privateAuditRecordValidator$validator)',
                'publicfunctionselect(AuditEventCatalog$catalog,AuditEventDefinition$definition,#[\SensitiveParameter]mixed$requirement):AuditDeliveryPlan',
                'publicfunctionforDraft(#[\SensitiveParameter]AuditRecordDraft$draft):AuditDeliveryPlan',
                'publicfunctionvalidate(AuditDeliveryPlan$plan,#[\SensitiveParameter]AuditDeliveryRequest$request):AuditDelivery',
                'privatefunctionmember(AuditEventCatalog$catalog,#[\SensitiveParameter]AuditEventDefinition$definition):?AuditEventDefinition',
                'privatefunctiondefinition(AuditEventCatalog$catalog,#[\SensitiveParameter]AuditRecordDraft$draft):?AuditEventDefinition',
            ],
            'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => [
                'publicfunction__construct(privateConnection$connection,privateAuditDeliverySelector$selector,privatePostgreSqlAuditRecordStore$records,privatePostgreSqlAuditOutboxStore$outbox)',
                'publicfunctionusesConnection(#[\SensitiveParameter]Connection$connection):bool',
                'publicfunctiondeliverStandalone(#[\SensitiveParameter]AuditDeliveryRequest$request):void',
                'publicfunctiondeliverInCurrentTransaction(AuditDeliveryPlan$plan,#[\SensitiveParameter]AuditDeliveryRequest$request):void',
                'privatefunctionappend(AuditDelivery$delivery,#[\SensitiveParameter]AuditDeliveryRequest$request):void',
            ],
            'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => [
                'publicfunction__construct(privateOperationTransactionCoordinator$transactions,privateAuditDeliveryCoordinator$delivery)',
                'publicfunctionexecute(AuditDeliveryPlan$plan,OperationMetadata$metadata,#[\SensitiveParameter]Closure$invoke,#[\SensitiveParameter]Closure$deliveryRequest):OperationResult',
                'privatefunctionassertMetadata(#[\SensitiveParameter]OperationMetadata$metadata):void',
            ],
            'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                'publicfunction__construct(privateAuditDeliveryCoordinator$delivery)',
                'publicfunctionrequested(#[\SensitiveParameter]AuditReadRequestBasis$basis,#[\SensitiveParameter]AuditRecordDraft$draft):void',
                'publicfunctionterminal(#[\SensitiveParameter]AuditReadContext$context,#[\SensitiveParameter]AuditRecordDraft$draft):AuditReadAuthorizationDecision',
                'privatefunctionassertBinding(#[\SensitiveParameter]AuditReadRequestBasis$basis,#[\SensitiveParameter]AuditRecordDraft$draft,string$phase):void',
            ],
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => [
                'private\SensitiveParameterValue$snapshot;',
                'publicfunction__construct(#[\SensitiveParameter]AuditProtectionKeyProvider$provider,AuditPartitionIdDeriver$partitions,#[\SensitiveParameter]StorageKey$opaqueKey)',
                'publicfunctionactiveKey(#[\SensitiveParameter]?AuditTypedReference$tenant,AuditProtectionPurpose$purpose):StorageKey',
                'publicfunctionkey(#[\SensitiveParameter]string$keyId,#[\SensitiveParameter]?AuditTypedReference$tenant,AuditProtectionPurpose$purpose):StorageKey',
                'privatefunctionvalidated(#[\SensitiveParameter]mixed$key,#[\SensitiveParameter]?string$expectedId=null):StorageKey',
                'privatefunctionvalue(#[\SensitiveParameter]string$name):mixed',
            ],
            'src/Internal/Application/ApplicationAuditConfiguration.php' => [
                'privatefunction__construct(#[\SensitiveParameter]privateAuditOutboxConfiguration$outbox)',
                'publicstaticfunctionfromConfiguration(#[\SensitiveParameter]array$configuration):self',
                'publicfunctionoutbox():AuditOutboxConfiguration',
            ],
            'src/Internal/Application/ApplicationAuditRuntime.php' => [
                'privatefunction__construct(#[\SensitiveParameter]privateAuditOutboxConfiguration$outboxConfiguration,#[\SensitiveParameter]privateHmacAuditOpaqueIdDeriver$opaqueIds,#[\SensitiveParameter]privateAuditDeliverySelector$deliverySelector,#[\SensitiveParameter]privateAuditDeliveryCoordinator$delivery,#[\SensitiveParameter]privateAuditMutationTransactionCoordinator$mutations,#[\SensitiveParameter]privateAuditProtectedReadGate$protectedReads,#[\SensitiveParameter]privatePostgreSqlAuditRecordStore$records,#[\SensitiveParameter]privatePostgreSqlAuditOutboxStore$outbox,#[\SensitiveParameter]privateAuditOutboxRelayRuntime$relay)',
                'publicstaticfunctionfromComposition(',
                'publicfunctionoutboxConfiguration():AuditOutboxConfiguration',
                'publicfunctionopaqueIds():HmacAuditOpaqueIdDeriver',
                'publicfunctiondeliverySelector():AuditDeliverySelector',
                'publicfunctiondelivery():AuditDeliveryCoordinator',
                'publicfunctionmutations():AuditMutationTransactionCoordinator',
                'publicfunctionprotectedReads():AuditProtectedReadGate',
                'publicfunctionrecords():PostgreSqlAuditRecordStore',
                'publicfunctionoutbox():PostgreSqlAuditOutboxStore',
                'publicfunctionrelay():AuditOutboxRelayRuntime',
            ],
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                'publicfunctioncompose(#[\SensitiveParameter]ApplicationConfigurationSnapshot$configuration,#[\SensitiveParameter]ApplicationOperationRuntimeComposition$operations,#[\SensitiveParameter]ImmutableAuditEventCatalogLookup$catalogs):ApplicationAuditRuntime',
                'privatefunctiondatabase(#[\SensitiveParameter]ApplicationConfigurationSnapshot$configuration):ApplicationDatabaseConfiguration',
                'privatefunctionassertConnection(#[\SensitiveParameter]ApplicationDatabaseConfiguration$database,#[\SensitiveParameter]ApplicationOperationRuntimeComposition$operations):void',
                'privatefunctionproviders(#[\SensitiveParameter]ContainerInterface$container):array',
            ],
            'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => [
                'private\SensitiveParameterValue$snapshot;',
                'publicfunction__construct(#[\SensitiveParameter]HmacAuditOpaqueIdDeriver$opaqueIds,#[\SensitiveParameter]AuditDeliverySelector$selector,#[\SensitiveParameter]AuditDeliveryCoordinator$delivery,#[\SensitiveParameter]ClockInterface$clock,#[\SensitiveParameter]ActorContext$actor,#[\SensitiveParameter]AuditRecordCorrelation$correlation,AuditSourceRuntime$runtime,#[\SensitiveParameter]string$buildId)',
                'publicfunctionassertConnection(#[\SensitiveParameter]Connection$connection):void',
                'publicfunctionplaced(#[\SensitiveParameter]RetentionHoldId$holdId,#[\SensitiveParameter]OperationId$operationId,#[\SensitiveParameter]?TenantRef$tenant):void',
                'publicfunctionreleased(#[\SensitiveParameter]RetentionHoldId$holdId,#[\SensitiveParameter]OperationId$operationId,#[\SensitiveParameter]?TenantRef$tenant):void',
                'publicfunction__debugInfo():array',
                'publicfunction__serialize():array',
                'publicfunction__unserialize(#[\SensitiveParameter]array$data):void',
                'privatefunctionwrite(string$eventType,string$outcomeCode,#[\SensitiveParameter]RetentionHoldId$holdId,#[\SensitiveParameter]OperationId$operationId,#[\SensitiveParameter]?TenantRef$tenant):void',
                'privatefunctioncatalog():AuditEventCatalog',
                'privatefunctiondefinition(#[\SensitiveParameter]AuditEventCatalog$catalog,string$eventType,string$outcomeCode):AuditEventDefinition',
                'privatefunctionoccurredAt():string',
                'privatefunctionopaqueId(AuditOpaqueIdPurpose$purpose,#[\SensitiveParameter]string$context):string',
                'privatefunctiondraft(#[\SensitiveParameter]AuditEventDefinition$definition,#[\SensitiveParameter]string$actionId,#[\SensitiveParameter]string$resourceId,#[\SensitiveParameter]string$occurredAt,#[\SensitiveParameter]?AuditTypedReference$tenant):AuditRecordDraft',
                'privatefunctionselector():AuditDeliverySelector',
                'privatefunctionreference(?ActorRef$reference):?AuditTypedReference',
                'privatefunctionvalue(#[\SensitiveParameter]string$key):mixed',
                'privatestaticfunctionfailure(AuditDeliveryFailure$failure):AuditDeliveryException',
                'privatestaticfunctionremap(#[\SensitiveParameter]AuditDeliveryException$failure):AuditDeliveryException',
            ],
        ];
        $violations = [];
        foreach ($expected[$path] ?? [] as $fragment) {
            if (!str_contains($code, $fragment)) {
                $violations[] = $path . ':' . $fragment;
            }
        }
        $returnTypes = match ($path) {
            'src/Internal/Audit/Orchestration/AuditDeliveryPlan.php' => ['fromSelection' => 'self'],
            'src/Internal/Application/ApplicationAuditRuntime.php' => ['fromComposition' => 'self'],
            default => [],
        };
        foreach ($returnTypes as $method => $returnType) {
            $pattern =
                '/publicstaticfunction'
                . preg_quote($method, '/')
                . '\\([^{}]*\\):'
                . preg_quote($returnType, '/')
                . '(?:\\{|;)/';
            if (preg_match($pattern, $code) !== 1) {
                $violations[] = $path . ':return:' . $method . ':' . $returnType;
            }
        }
        if ($path === 'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php') {
            if (!str_contains($code, 'function(#[\SensitiveParameter]OperationResult$result)')) {
                $violations[] = $path . ':sensitive-result-callback';
            }
        }

        return $violations;
    }

    private function c3CodeWithoutComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        $code = preg_replace('/\s+/', '', $code);
        self::assertIsString($code);
        $code = preg_replace('/,+\)/', ')', $code);
        self::assertIsString($code);

        return $code;
    }

    private function c3HasParameterDefault(string $source): bool
    {
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $private = false;
            for ($modifier = $index - 1; $modifier >= 0; $modifier--) {
                $candidate = $tokens[$modifier];
                if ($candidate === ';' || $candidate === '}' || $candidate === '{') {
                    break;
                }
                if (is_array($candidate) && $candidate[0] === T_PRIVATE) {
                    $private = true;
                    break;
                }
            }
            if ($private) {
                continue;
            }
            $open = null;
            for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                if ($tokens[$cursor] === '(') {
                    $open = $cursor;
                    break;
                }
                if ($tokens[$cursor] === ';' || $tokens[$cursor] === '{') {
                    break;
                }
            }
            if ($open === null) {
                continue;
            }
            $depth = 1;
            for ($cursor = $open + 1; $cursor < count($tokens) && $depth > 0; $cursor++) {
                $candidate = $tokens[$cursor];
                if ($candidate === '(') {
                    $depth++;
                    continue;
                }
                if ($candidate === ')') {
                    $depth--;
                    continue;
                }
                if ($depth === 1 && $candidate === '=') {
                    return true;
                }
            }
        }

        return false;
    }

    private function c3Structure(string $source): string
    {
        $tokens = token_get_all($source);
        $start = null;
        $declarations = 0;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_ENUM], true)) {
                $next = $this->c3NextToken($tokens, $index + 1);
                if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                    $declarations++;
                    $start ??= $index;
                }
            }
        }
        if ($start === null) {
            return '';
        }
        $body = null;
        for ($index = $start; $index < count($tokens); $index++) {
            if ($tokens[$index] === '{') {
                $body = $index;
                break;
            }
        }
        if ($body === null) {
            return 'declarations:' . $declarations . ';members:';
        }
        $members = [];
        $depth = 1;
        for ($index = $body + 1; $index < count($tokens) && $depth > 0; $index++) {
            $token = $tokens[$index];
            if ($depth === 1 && is_array($token) && $token[0] === T_FUNCTION) {
                $name = $this->c3NextToken($tokens, $index + 1);
                if ($name !== null && is_array($tokens[$name]) && $tokens[$name][0] === T_STRING) {
                    $members[] = 'method:' . $tokens[$name][1];
                }
                for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                    if ($tokens[$cursor] === ';') {
                        $index = $cursor;
                        break;
                    }
                    if ($tokens[$cursor] !== '{') {
                        continue;
                    }
                    $nested = 1;
                    for ($bodyCursor = $cursor + 1; $bodyCursor < count($tokens); $bodyCursor++) {
                        if ($tokens[$bodyCursor] === '{') {
                            $nested++;
                        } elseif ($tokens[$bodyCursor] === '}') {
                            $nested--;
                            if ($nested === 0) {
                                $index = $bodyCursor;
                                break;
                            }
                        }
                    }
                    break;
                }
                continue;
            }
            if ($depth === 1 && is_array($token) && $token[0] === T_CASE) {
                $name = $this->c3NextToken($tokens, $index + 1);
                if ($name !== null && is_array($tokens[$name]) && $tokens[$name][0] === T_STRING) {
                    $members[] = 'case:' . $tokens[$name][1];
                }
                continue;
            }
            if ($depth === 1 && is_array($token) && $token[0] === T_VARIABLE) {
                $members[] = 'property:' . $token[1];
                continue;
            }
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }
        }

        return 'declarations:' . $declarations . ';members:' . implode(',', $members);
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3NextToken(array $tokens, int $index): ?int
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

    /** @param array<string,string> $sources @return list<string> */
    private function c3ReferenceViolations(array $sources): array
    {
        $violations = [];
        $expectedImplementations = [
            'src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => 1,
        ];
        if ($this->c3InterfaceImplementationInventory($sources) !== $expectedImplementations) {
            $violations[] = 'audit-protection-key-source-implementation-inventory';
        }
        $expected = [
            'appendRequired' => ['src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => 1],
            'appendDurable' => [
                'src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => 1,
                'src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => 1,
            ],
            'deliverInCurrentTransaction' => [
                'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => 2,
                'src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => 1,
                'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 1,
            ],
            'deliverInMutationTransaction' => [
                'src/Internal/Execution/DeferredWorkerRuntime.php' => 1,
                'src/Internal/Execution/InlineDispatcher.php' => 1,
            ],
            'deliverStandalone' => [
                'src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php' => 1,
                'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => 1,
                'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => 2,
                'src/Internal/Execution/InlineDispatcher.php' => 2,
            ],
        ];
        if ($this->c3DirectCalls($sources) !== $expected) {
            $violations[] = 'direct-call-inventory';
        }
        $receivers = [
            'appendDurable|src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => ['$this->outbox'],
            'appendDurable|src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => ['$this->records'],
            'appendRequired|src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => ['$this->records'],
            'deliverInCurrentTransaction|src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => [
                '$values',
                '$values',
            ],
            'deliverInCurrentTransaction|src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => [
                '$this->delivery',
            ],
            'deliverInCurrentTransaction|src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => [
                '$delivery',
            ],
            'deliverInMutationTransaction|src/Internal/Execution/DeferredWorkerRuntime.php' => [
                '$authorizationAttempt',
            ],
            'deliverInMutationTransaction|src/Internal/Execution/InlineDispatcher.php' => [
                '$authorizationAttempt',
            ],
            'deliverStandalone|src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php' => [
                '$delivery',
            ],
            'deliverStandalone|src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => ['$values'],
            'deliverStandalone|src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => [
                '$this->delivery',
                '$this->delivery',
            ],
            'deliverStandalone|src/Internal/Execution/InlineDispatcher.php' => [
                '$authorizationAttempt',
                '$authorizationAttempt',
            ],
        ];
        if ($this->c3DirectCallReceivers($sources) !== $receivers) {
            $violations[] = 'direct-call-receivers';
        }
        $expectedClassStrings = [
            'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                'BlackOps\\Audit\\AuditOpaqueIdKeyProvider',
                'BlackOps\\Audit\\AuditProtectionKeyProvider',
            ],
        ];
        if ($this->c3ClassStringInventory($sources) !== $expectedClassStrings) {
            $violations[] = 'class-string-inventory';
        }
        $constructors = [
            'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver' => [
                'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 1,
            ],
            'BlackOps\\Internal\\Audit\\Key\\ApplicationAuditProtectionKeySource' => [
                'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 1,
            ],
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditMutationTransactionCoordinator' => [
                'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 1,
            ],
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator' => [
                'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 1,
            ],
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector' => [
                'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 1,
            ],
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditProtectedReadGate' => [
                'src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 1,
            ],
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest' => [
                'src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php' => 1,
                'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php' => 1,
                'src/Internal/Audit/Orchestration/AuditProtectedReadGate.php' => 2,
                'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 1,
            ],
            'BlackOps\\Internal\\Application\\ApplicationOperationRuntimeComposition' => [
                'src/Internal/Application/ApplicationOperationRuntimeComposer.php' => 1,
            ],
            'BlackOps\\Internal\\Database\\DoctrineDatabaseManager' => [
                'src/Internal/Application/ApplicationDatabaseConfiguration.php' => 1,
            ],
            'BlackOps\\Internal\\Application\\ApplicationAuditRuntimeComposer' => [],
            'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter' => [],
            'BlackOps\\Internal\\Audit\\Authentication\\DurableAuthenticationAuditEventWriter' => [],
            'BlackOps\\Internal\\Audit\\Authentication\\AuditedHttpAuthenticator' => [],
        ];
        foreach ($constructors as $target => $expectedFiles) {
            $actualFiles = [];
            foreach ($sources as $path => $source) {
                $count = $this->c3ConstructorCalls($source, $target);
                if ($count > 0) {
                    $actualFiles[$path] = $count;
                }
            }
            ksort($actualFiles);
            if ($actualFiles !== $expectedFiles) {
                $violations[] = 'constructor-inventory:' . $target;
            }
        }
        foreach ($sources as $path => $source) {
            if ($this->c3DynamicCall($source, str_starts_with($path, 'src/External/'))) {
                $violations[] = 'dynamic:' . $path;
            }
            if ($this->c3IndirectReference($source)) {
                $violations[] = 'indirect:' . $path;
            }
        }
        $runtimeFactoryCallers = [];
        foreach ($sources as $path => $source) {
            $count = $this->c3StaticMethodCalls(
                $source,
                'BlackOps\\Internal\\Application\\ApplicationAuditRuntime',
                'fromComposition',
            );
            if ($count > 0) {
                $runtimeFactoryCallers[$path] = $count;
            }
        }
        ksort($runtimeFactoryCallers);
        if ($runtimeFactoryCallers !== ['src/Internal/Application/ApplicationAuditRuntimeComposer.php' => 1]) {
            $violations[] = 'runtime-factory-callers';
        }

        $violations = [...$violations, ...$this->c3AuthenticationReferenceViolations($sources)];

        return $violations;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function c3AuthenticationReferenceViolations(array $sources): array
    {
        $expected = [
            'src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php' => [
                'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter',
            ],
            'src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php' => [
                'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter',
                'BlackOps\\Internal\\Audit\\Authentication\\DurableAuthenticationAuditEventWriter',
                'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            ],
            'src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php' => [
                'BlackOps\\Internal\\Audit\\Authentication\\AuditedHttpAuthenticator',
                'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
            ],
        ];
        $violations = [];
        $authenticationTargets = [
            'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter',
            'BlackOps\\Internal\\Audit\\Authentication\\DurableAuthenticationAuditEventWriter',
            'BlackOps\\Internal\\Audit\\Authentication\\AuditedHttpAuthenticator',
        ];
        foreach ($expected as $path => $expectedReferences) {
            if (!array_key_exists($path, $sources)) {
                $violations[] = 'authentication-missing:' . $path;
                continue;
            }
            sort($expectedReferences);
            if ($this->c3ReferencedTargetClasses($sources[$path]) !== $expectedReferences) {
                $violations[] = 'authentication-references:' . $path;
            }
        }
        foreach ($sources as $path => $source) {
            if (array_key_exists($path, $expected)) {
                continue;
            }
            if (array_intersect($authenticationTargets, $this->c3ReferencedTargetClasses($source)) !== []) {
                $violations[] = 'authentication-external-reference:' . $path;
            }
        }

        return $violations;
    }

    /** @param array<string,string> $sources @return array<string,int> */
    private function c3InterfaceImplementationInventory(array $sources): array
    {
        $target = 'BlackOps\\Internal\\Audit\\Protection\\AuditProtectionKeySource';
        $inventory = [];
        foreach ($sources as $path => $source) {
            $count = 0;
            foreach ($this->c3ImplementedInterfaces($source) as $interface) {
                if ($interface === $target) {
                    $count++;
                }
            }
            if ($count > 0) {
                $inventory[$path] = $count;
            }
        }
        ksort($inventory);

        return $inventory;
    }

    /** @return list<string> */
    private function c3ImplementedInterfaces(string $source): array
    {
        $tokens = token_get_all($source);
        [$namespace, $aliases] = $this->c3NameContext($tokens);
        $interfaces = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }
            $name = $this->c3NextToken($tokens, $index + 1);
            if ($name === null || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING) {
                continue;
            }
            for (
                $cursor = $this->c3NextToken($tokens, $name + 1);
                $cursor !== null;
                $cursor = $this->c3NextToken($tokens, $cursor + 1)
            ) {
                if ($tokens[$cursor] === '{' || $tokens[$cursor] === ';') {
                    break;
                }
                if (!is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_IMPLEMENTS) {
                    continue;
                }
                $parts = [];
                for (
                    $interface = $this->c3NextToken($tokens, $cursor + 1);
                    $interface !== null;
                    $interface = $this->c3NextToken($tokens, $interface + 1)
                ) {
                    $candidate = $tokens[$interface];
                    if ($candidate === '{' || $candidate === ';') {
                        if ($parts !== []) {
                            $interfaces[] = $this->c3ResolveName(implode('', $parts), $namespace, $aliases);
                        }
                        break;
                    }
                    if ($candidate === ',') {
                        if ($parts !== []) {
                            $interfaces[] = $this->c3ResolveName(implode('', $parts), $namespace, $aliases);
                            $parts = [];
                        }
                        continue;
                    }
                    $text = is_array($candidate) ? $candidate[1] : $candidate;
                    if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $text) === 1) {
                        $parts[] = $text;
                    }
                }
                break;
            }
        }

        return $interfaces;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function c3ClassStringInventory(array $sources): array
    {
        $inventory = [];
        foreach ($sources as $path => $source) {
            $references = $this->c3ClassStringReferences($source);
            if ($references !== []) {
                $inventory[$path] = $references;
            }
        }
        ksort($inventory);

        return $inventory;
    }

    /** @return list<string> */
    private function c3ClassStringReferences(string $source): array
    {
        $tokens = token_get_all($source);
        [$namespace, $aliases] = $this->c3NameContext($tokens);
        $targets = $this->c3TargetClasses();
        $references = [];
        foreach ($tokens as $index => $token) {
            if (!$this->c3Operator($token, [T_DOUBLE_COLON])) {
                continue;
            }
            $owner = $this->c3PreviousToken($tokens, $index - 1);
            $member = $this->c3NextToken($tokens, $index + 1);
            if (
                $owner === null
                || $member === null
                || !is_array($tokens[$owner])
                || !is_array($tokens[$member])
                || $tokens[$member][0] !== T_CLASS
                || !in_array(
                    $tokens[$owner][0],
                    [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR],
                    true,
                )
            ) {
                continue;
            }
            $resolved = $this->c3ResolveName($tokens[$owner][1], $namespace, $aliases);
            if (in_array($resolved, $targets, true)) {
                $references[] = $resolved;
            }
        }
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function c3DirectCalls(array $sources): array
    {
        $methods = [
            'appendRequired',
            'appendDurable',
            'deliverInCurrentTransaction',
            'deliverInMutationTransaction',
            'deliverStandalone',
        ];
        $result = array_fill_keys($methods, []);
        foreach ($sources as $path => $source) {
            $tokens = token_get_all($source);
            for ($index = 0; $index < count($tokens); $index++) {
                $token = $tokens[$index];
                if (!is_array($token) || !in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                    continue;
                }
                $name = $this->c3NextToken($tokens, $index + 1);
                if ($name === null || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING) {
                    continue;
                }
                $open = $this->c3NextToken($tokens, $name + 1);
                $method = $this->c3MethodName($tokens[$name][1]);
                if ($open === null || $tokens[$open] !== '(' || $method === null) {
                    continue;
                }
                $result[$method][$path] = ($result[$method][$path] ?? 0) + 1;
            }
        }
        foreach ($result as &$files) {
            ksort($files);
        }
        unset($files);

        return $result;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function c3DirectCallReceivers(array $sources): array
    {
        $result = [];
        foreach ($sources as $path => $source) {
            $tokens = token_get_all($source);
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || !in_array($token[0], $this->c3ObjectOperators(), true)) {
                    continue;
                }
                $name = $this->c3NextToken($tokens, $index + 1);
                if (
                    $name === null
                    || !is_array($tokens[$name])
                    || $tokens[$name][0] !== T_STRING
                    || $this->c3MethodName($tokens[$name][1]) === null
                ) {
                    continue;
                }
                $open = $this->c3NextToken($tokens, $name + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $receiver = $this->c3Receiver($tokens, $index);
                $method = $this->c3MethodName($tokens[$name][1]);
                if ($method === null) {
                    continue;
                }
                $key = $method . '|' . $path;
                $result[$key][] = $receiver;
            }
        }
        ksort($result);
        foreach ($result as &$receivers) {
            sort($receivers);
        }
        unset($receivers);

        return $result;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3Receiver(array $tokens, int $operator): ?string
    {
        $name = $this->c3PreviousToken($tokens, $operator - 1);
        if ($name === null) {
            return null;
        }
        if (is_array($tokens[$name])) {
            if ($tokens[$name][0] === T_VARIABLE) {
                return $tokens[$name][1];
            }
            if ($tokens[$name][0] !== T_STRING) {
                return null;
            }
        } elseif ($tokens[$name] !== ']') {
            return null;
        }
        if ($tokens[$name] === ']') {
            $depth = 1;
            $opening = null;
            for ($cursor = $name - 1; $cursor >= 0; $cursor--) {
                if ($tokens[$cursor] === ']') {
                    $depth++;
                } elseif ($tokens[$cursor] === '[') {
                    $depth--;
                    if ($depth === 0) {
                        $opening = $cursor;
                        break;
                    }
                }
            }
            if ($opening !== null) {
                $base = $this->c3PreviousToken($tokens, $opening - 1);
                if ($base !== null && is_array($tokens[$base]) && $tokens[$base][0] === T_VARIABLE) {
                    return $tokens[$base][1];
                }
            }
            return null;
        }
        $parts = [$tokens[$name][1]];
        $cursor = $this->c3PreviousToken($tokens, $name - 1);
        while (
            $cursor !== null
            && is_array($tokens[$cursor])
            && in_array($tokens[$cursor][0], $this->c3ObjectOperators(), true)
        ) {
            $base = $this->c3PreviousToken($tokens, $cursor - 1);
            if ($base === null || !is_array($tokens[$base])) {
                return null;
            }
            if ($tokens[$base][0] === T_VARIABLE || $tokens[$base][0] === T_STRING) {
                array_unshift($parts, $tokens[$base][1]);
                $cursor = $this->c3PreviousToken($tokens, $base - 1);
                continue;
            }
            return null;
        }
        return implode('->', $parts);
    }

    private function c3MethodName(string $method): ?string
    {
        foreach ([
            'appendRequired',
            'appendDurable',
            'deliverInCurrentTransaction',
            'deliverInMutationTransaction',
            'deliverStandalone',
        ] as $candidate) {
            if (strtolower($method) === strtolower($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<int> */
    private function c3ObjectOperators(): array
    {
        $operators = [T_OBJECT_OPERATOR];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $operators[] = T_NULLSAFE_OBJECT_OPERATOR;
        }

        return $operators;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3PreviousToken(array $tokens, int $index): ?int
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

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3DynamicCall(string $source, bool $strict = false): bool
    {
        $targetMentioned = $strict;
        if (!$targetMentioned) {
            foreach ([
                'AuditDeliveryCoordinator',
                'AuditDeliveryRequest',
                'AuditMutationTransactionCoordinator',
                'ApplicationAuditRuntime',
                'ApplicationAuditRuntimeComposer',
                'ApplicationOperationRuntimeComposition',
                'DoctrineDatabaseManager',
                'HmacAuditOpaqueIdDeriver',
                'AuthenticationAuditEventWriter',
                'DurableAuthenticationAuditEventWriter',
                'AuditedHttpAuthenticator',
                'appendRequired',
                'appendDurable',
                'deliverStandalone',
                'deliverInCurrentTransaction',
                'deliverInMutationTransaction',
            ] as $target) {
                if (str_contains($source, $target)) {
                    $targetMentioned = true;
                    break;
                }
            }
        }
        if (!$targetMentioned) {
            return false;
        }
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }
            $next = $this->c3NextToken($tokens, $index + 1);
            if ($next === null) {
                continue;
            }
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                return true;
            }
            if ($tokens[$next] === '{') {
                return true;
            }
            if ($token[0] === T_DOUBLE_COLON) {
                $after = $this->c3NextToken($tokens, $next + 1);
                if ($after !== null && $tokens[$after] === ')') {
                    $opening = $this->c3NextToken($tokens, $after + 1);
                    if ($opening !== null && $tokens[$opening] === '(') {
                        return true;
                    }
                }
            }
        }
        if ($this->c3CallableArray($tokens)) {
            return true;
        }

        return false;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3CallableArray(array $tokens): bool
    {
        $methods = [
            'appendrequired',
            'appenddurable',
            'deliverstandalone',
            'deliverincurrenttransaction',
            'deliverinmutationtransaction',
            'compose',
            'fromcomposition',
        ];
        for ($index = 0; $index < count($tokens); $index++) {
            if ($tokens[$index] !== '[') {
                continue;
            }
            $depth = 1;
            $close = null;
            for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                if ($tokens[$cursor] === '[') {
                    $depth++;
                } elseif ($tokens[$cursor] === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $close = $cursor;
                        break;
                    }
                }
            }
            if ($close === null) {
                continue;
            }
            $first = $this->c3NextToken($tokens, $index + 1);
            if ($first === null) {
                continue;
            }
            $comma = null;
            for ($cursor = $first + 1; $cursor < $close; $cursor++) {
                if ($tokens[$cursor] === ',') {
                    $comma = $cursor;
                    break;
                }
            }
            if ($comma === null) {
                continue;
            }
            $method = $this->c3NextToken($tokens, $comma + 1);
            if (
                $method === null
                || !is_array($tokens[$method])
                || $tokens[$method][0] !== T_CONSTANT_ENCAPSED_STRING
                || !in_array(strtolower(substr($tokens[$method][1], 1, -1)), $methods, true)
            ) {
                continue;
            }
            $after = $this->c3NextToken($tokens, $close + 1);
            if ($after !== null && $tokens[$after] === '(') {
                return true;
            }
            if ($after !== null && $tokens[$after] === ')') {
                $invocation = $this->c3NextToken($tokens, $after + 1);
                if ($invocation !== null && $tokens[$invocation] === '(') {
                    return true;
                }
            }
            $before = $this->c3PreviousToken($tokens, $index - 1);
            if ($before === null || $tokens[$before] !== '(') {
                continue;
            }
            $call = $this->c3PreviousToken($tokens, $before - 1);
            if (
                $call !== null
                && is_array($tokens[$call])
                && $tokens[$call][0] === T_STRING
                && in_array($tokens[$call][1], ['call_user_func', 'call_user_func_array'], true)
            ) {
                return true;
            }
        }

        return false;
    }

    private function c3IndirectReference(string $source): bool
    {
        $tokens = token_get_all($source);
        $targetNames = array_map(static fn(string $class): string => ltrim($class, '\\'), $this->c3TargetClasses());
        $variables = [];
        do {
            $changed = false;
            for ($index = 0; $index < count($tokens); $index++) {
                $token = $tokens[$index];
                if (!is_array($token) || $token[0] !== T_VARIABLE) {
                    continue;
                }
                $equals = $this->c3NextToken($tokens, $index + 1);
                if ($equals === null || $tokens[$equals] !== '=') {
                    continue;
                }
                $value = $this->c3NextToken($tokens, $equals + 1);
                if ($value === null) {
                    continue;
                }
                $literal = $this->c3StringLiteral($tokens, $value);
                $isTarget = $literal !== null && in_array($literal, $targetNames, true);
                $isAlias =
                    is_array($tokens[$value])
                    && $tokens[$value][0] === T_VARIABLE
                    && ($variables[$tokens[$value][1]] ?? false);
                if (($isTarget || $isAlias) && !($variables[$token[1]] ?? false)) {
                    $variables[$token[1]] = true;
                    $changed = true;
                }
            }
        } while ($changed);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE || !($variables[$token[1]] ?? false)) {
                continue;
            }
            $next = $this->c3NextToken($tokens, $index + 1);
            $previous = $this->c3PreviousToken($tokens, $index - 1);
            if ($previous !== null && $tokens[$previous] === '(') {
                $call = $this->c3PreviousToken($tokens, $previous - 1);
                if (
                    $call !== null
                    && is_array($tokens[$call])
                    && $tokens[$call][0] === T_STRING
                    && in_array(
                        $tokens[$call][1],
                        [
                            'call_user_func',
                            'call_user_func_array',
                            'class_exists',
                            'enum_exists',
                            'interface_exists',
                            'is_a',
                            'is_subclass_of',
                            'ReflectionClass',
                            'ReflectionMethod',
                        ],
                        true,
                    )
                ) {
                    return true;
                }
                if ($call !== null && is_array($tokens[$call]) && $tokens[$call][0] === T_STRING) {
                    $operator = $this->c3PreviousToken($tokens, $call - 1);
                    $owner = $operator === null ? null : $this->c3PreviousToken($tokens, $operator - 1);
                    if (
                        $operator !== null
                        && $this->c3Operator($tokens[$operator], [T_DOUBLE_COLON])
                        && $owner !== null
                        && is_array($tokens[$owner])
                        && $tokens[$owner][0] === T_STRING
                        && in_array($tokens[$owner][1], ['ReflectionClass', 'ReflectionMethod'], true)
                    ) {
                        return true;
                    }
                }
            }
            if (
                $next !== null && $this->c3Operator($tokens[$next], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, 'nullsafe'])
                || $previous !== null
                && $this->c3Operator($tokens[$previous], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, 'nullsafe'])
            ) {
                return true;
            }
            if (
                $previous !== null && $tokens[$previous] === '['
                || $next !== null && ($tokens[$next] === ',' || $tokens[$next] === ']')
            ) {
                return true;
            }
        }
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_START_HEREDOC], true)) {
                continue;
            }
            $value = $this->c3StringLiteral($tokens, $index);
            if (
                $value !== null
                && in_array($value, $targetNames, true)
                && $this->c3LiteralIsIndirect($tokens, $index)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3LiteralIsIndirect(array $tokens, int $index): bool
    {
        $previous = $this->c3PreviousToken($tokens, $index - 1);
        $next = $this->c3NextToken($tokens, $index + 1);
        if ($previous !== null && $tokens[$previous] === '[' || $next !== null && $tokens[$next] === ',') {
            $left = $previous;
            while ($left !== null && $tokens[$left] !== '[' && $tokens[$left] !== ';') {
                $left = $this->c3PreviousToken($tokens, $left - 1);
            }
            $right = $next;
            while ($right !== null && $tokens[$right] !== ']' && $tokens[$right] !== ';') {
                $right = $this->c3NextToken($tokens, $right + 1);
            }
            if ($left !== null && $right !== null && $tokens[$left] === '[' && $tokens[$right] === ']') {
                return true;
            }
        }
        if ($previous !== null && $tokens[$previous] === '(') {
            $call = $this->c3PreviousToken($tokens, $previous - 1);
            if (
                $call !== null
                && is_array($tokens[$call])
                && $tokens[$call][0] === T_STRING
                && in_array(
                    $tokens[$call][1],
                    [
                        'call_user_func',
                        'call_user_func_array',
                        'class_exists',
                        'enum_exists',
                        'interface_exists',
                        'is_a',
                        'is_subclass_of',
                        'ReflectionClass',
                        'ReflectionMethod',
                    ],
                    true,
                )
            ) {
                return true;
            }
            if ($call !== null && is_array($tokens[$call]) && $tokens[$call][0] === T_STRING) {
                $operator = $this->c3PreviousToken($tokens, $call - 1);
                $owner = $operator === null ? null : $this->c3PreviousToken($tokens, $operator - 1);
                if (
                    $operator !== null
                    && $this->c3Operator($tokens[$operator], [T_DOUBLE_COLON])
                    && $owner !== null
                    && is_array($tokens[$owner])
                    && $tokens[$owner][0] === T_STRING
                    && in_array($tokens[$owner][1], ['ReflectionClass', 'ReflectionMethod'], true)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array{0:int,1:string,2:int}|string $token @param list<int|string> $types */
    private function c3Operator(array|string $token, array $types): bool
    {
        if (is_string($token)) {
            return in_array($token, $types, true);
        }
        if ($token[0] === T_DOUBLE_COLON || $token[0] === T_OBJECT_OPERATOR) {
            return in_array($token[0], $types, true);
        }
        if (defined('T_NULLSAFE_OBJECT_OPERATOR') && $token[0] === T_NULLSAFE_OBJECT_OPERATOR) {
            return in_array('nullsafe', $types, true);
        }

        return false;
    }

    /** @return list<string> */
    private function c3TargetClasses(): array
    {
        return [
            'BlackOps\\Audit\\AuditOpaqueIdKeyProvider',
            'BlackOps\\Audit\\AuditProtectionKeyProvider',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditMutationTransactionCoordinator',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditProtectedReadGate',
            'BlackOps\\Internal\\Audit\\Key\\ApplicationAuditProtectionKeySource',
            'BlackOps\\Internal\\Application\\ApplicationAuditConfiguration',
            'BlackOps\\Internal\\Application\\ApplicationAuditRuntime',
            'BlackOps\\Internal\\Application\\ApplicationAuditRuntimeComposer',
            'BlackOps\\Internal\\Application\\ApplicationOperationRuntimeComposition',
            'BlackOps\\Internal\\Database\\DoctrineDatabaseManager',
            'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
            'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter',
            'BlackOps\\Internal\\Audit\\Authentication\\DurableAuthenticationAuditEventWriter',
            'BlackOps\\Internal\\Audit\\Authentication\\AuditedHttpAuthenticator',
            'BlackOps\\Internal\\Audit\\Retention\\RetentionHoldAuditWriter',
        ];
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function c3StringLiteral(array $tokens, int $index): ?string
    {
        if (!is_array($tokens[$index])) {
            return null;
        }
        if ($tokens[$index][0] === T_CONSTANT_ENCAPSED_STRING) {
            $value = substr($tokens[$index][1], 1, -1);

            return str_replace(['\\\\', "\\'", '\\"'], ['\\', "'", '"'], $value);
        }
        if ($tokens[$index][0] !== T_START_HEREDOC) {
            return null;
        }
        $value = '';
        for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
            if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_END_HEREDOC) {
                break;
            }
            $value .= is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function c3StaticMethodCalls(string $source, string $class, string $method): int
    {
        $tokens = token_get_all($source);
        [$namespace, $aliases] = $this->c3NameContext($tokens);
        $count = 0;
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (!$this->c3Operator($token, [T_DOUBLE_COLON])) {
                continue;
            }
            $name = $this->c3PreviousToken($tokens, $index - 1);
            $methodIndex = $this->c3NextToken($tokens, $index + 1);
            if (
                $name === null
                || $methodIndex === null
                || !is_array($tokens[$name])
                || !is_array($tokens[$methodIndex])
                || $tokens[$methodIndex][0] !== T_STRING
                || $tokens[$methodIndex][1] !== $method
            ) {
                continue;
            }
            $resolved = $this->c3ResolveName($tokens[$name][1], $namespace, $aliases);
            if ($resolved === ltrim($class, '\\')) {
                $open = $this->c3NextToken($tokens, $methodIndex + 1);
                if ($open !== null && $tokens[$open] === '(') {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function c3ConstructorCalls(string $source, string $target): int
    {
        $tokens = token_get_all($source);
        [$namespace, $aliases] = $this->c3NameContext($tokens);

        $count = 0;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_NEW) {
                continue;
            }
            $nameIndex = $this->c3NextToken($tokens, $index + 1);
            if ($nameIndex === null || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] === T_VARIABLE) {
                continue;
            }
            $parts = [$tokens[$nameIndex][1]];
            for (
                $cursor = $this->c3NextToken($tokens, $nameIndex + 1);
                $cursor !== null;
                $cursor = $this->c3NextToken($tokens, $cursor + 1)
            ) {
                if ($tokens[$cursor] === '(') {
                    break;
                }
                if (
                    !is_array($tokens[$cursor])
                    || !in_array(
                        $tokens[$cursor][0],
                        [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR],
                        true,
                    )
                ) {
                    break;
                }
                $parts[] = $tokens[$cursor][1];
            }
            $name = implode('', $parts);
            $resolved = $this->c3ResolveName($name, $namespace, $aliases);
            if (ltrim($target, '\\') === $resolved) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array{0:string,1:array<string,string>} */
    private function c3NameContext(array $tokens): array
    {
        $namespace = '';
        $aliases = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_NAMESPACE) {
                continue;
            }
            $parts = [];
            for (
                $cursor = $this->c3NextToken($tokens, $index + 1);
                $cursor !== null;
                $cursor = $this->c3NextToken($tokens, $cursor + 1)
            ) {
                if ($tokens[$cursor] === ';' || $tokens[$cursor] === '{') {
                    break;
                }
                $parts[] = is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
            }
            $namespace = trim(implode('', $parts), '\\');
            break;
        }
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_USE) {
                continue;
            }
            $start = $this->c3NextToken($tokens, $index + 1);
            if ($start === null || $tokens[$start] === '(') {
                continue;
            }
            $parts = [];
            for ($cursor = $start; $cursor !== null; $cursor = $this->c3NextToken($tokens, $cursor + 1)) {
                if ($tokens[$cursor] === ';') {
                    break;
                }
                $parts[] = is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_AS
                    ? "\0"
                    : (is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor]);
            }
            $statementRaw = implode('', $parts);
            $statement = preg_replace('/\s+/', '', $statementRaw);
            self::assertIsString($statement);
            if (preg_match('/^(.+)\\\{(.+)\}$/', $statement, $matches) === 1) {
                $prefix = trim($matches[1], '\\');
                foreach (explode(',', $matches[2]) as $member) {
                    $memberParts = explode("\0", $member, 2);
                    $name = trim((string) $memberParts[0], '\\');
                    $alias = trim((string) ($memberParts[1] ?? $this->c3TerminalName($name)));
                    if ($name !== '') {
                        $aliases[strtolower($alias)] = $prefix . '\\' . $name;
                    }
                }
                continue;
            }
            $memberParts = explode("\0", $statementRaw, 2);
            $name = trim((string) $memberParts[0], '\\');
            if ($name !== '') {
                $alias = trim((string) ($memberParts[1] ?? $this->c3TerminalName($name)));
                $aliases[strtolower($alias)] = $name;
            }
        }

        return [$namespace, $aliases];
    }

    private function c3ResolveName(string $name, string $namespace, array $aliases): string
    {
        $resolved = trim($name, '\\');
        if (str_starts_with(strtolower($resolved), 'namespace\\')) {
            $relative = substr($resolved, strlen('namespace\\'));

            return $namespace === '' ? $relative : $namespace . '\\' . $relative;
        }
        $parts = explode('\\', $resolved);
        $first = strtolower($parts[0]);
        if (isset($aliases[$first])) {
            $parts[0] = $aliases[$first];

            return implode('\\', $parts);
        }

        return !str_contains($resolved, '\\') && $namespace !== '' ? $namespace . '\\' . $resolved : $resolved;
    }

    private function c3TerminalName(string $name): string
    {
        $parts = explode('\\', trim($name, '\\'));

        return $parts[count($parts) - 1];
    }

    /** @param list<array{0:string,1:string,2:int}> $parameters */
    private function assertParameters(string $type, string $method, array $parameters, ?string $return = null): void
    {
        $reflection = new ReflectionClass($type)->getMethod($method);
        $actual = [];
        foreach ($reflection->getParameters() as $parameter) {
            $actual[] = [
                $parameter->getName(),
                $this->typeName($parameter),
                count($parameter->getAttributes(\SensitiveParameter::class)),
            ];
        }
        self::assertSame($parameters, $actual, $type . '::' . $method);
        if ($return !== null) {
            self::assertSame($return, $this->typeName($reflection->getReturnType()), $type . '::' . $method);
        }
    }

    private function typeName(ReflectionParameter|\ReflectionType|null $value): string
    {
        $type = $value instanceof ReflectionParameter ? $value->getType() : $value;
        if (!$type instanceof ReflectionNamedType) {
            return $type === null ? '' : (string) $type;
        }
        $name = $type->getName();
        if (str_contains($name, '\\')) {
            $name = substr($name, strrpos($name, '\\') + 1);
        }
        return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $name;
    }

    /** @return list<Node> */
    private function parseArchitectureSource(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        self::assertIsArray($nodes);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $traverser->traverse($nodes);
    }

    /** @param list<Node> $nodes */
    private function architectureClass(array $nodes, string $name): ?Class_
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
            if ($class instanceof Class_ && $class->name?->toString() === $name) {
                return $class;
            }
        }

        return null;
    }

    private function architectureMethod(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === strtolower($name)) {
                return $method;
            }
        }

        return null;
    }

    private function isVariableNamed(?Node $node, string $name): bool
    {
        return $node instanceof Variable && $node->name === $name;
    }

    private function isThisProperty(?Node $node, string $property): bool
    {
        return (
            $node instanceof PropertyFetch
            && $this->isVariableNamed($node->var, 'this')
            && $node->name instanceof \PhpParser\Node\Identifier
            && $node->name->toString() === $property
        );
    }

    private function isIdentityComparison(Identical $comparison, string $property): bool
    {
        return (
            $this->isVariableNamed($comparison->left, 'connection')
            && $this->isThisProperty($comparison->right, $property)
            || $this->isThisProperty($comparison->left, $property)
            && $this->isVariableNamed($comparison->right, 'connection')
        );
    }
}
