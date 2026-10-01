<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeService;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Retention\RetentionPurgeAuditWriter;
use BlackOps\Internal\Retention\AuditedRetentionPurgeAuditPort;
use BlackOps\Internal\Retention\AuditedRetentionPurgeService;
use BlackOps\Transport\PostgreSql\PostgreSqlDeadLetterRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlIdempotencyRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPlanner;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeService;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use BlackOps\Transport\PostgreSql\PostgreSqlTransportPayloadTombstoneService;
use BlackOps\Transport\PostgreSql\SymfonyRetentionPurgeAuditIdGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_ as StringScalar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Keeps the purge writer and its two private activation boundaries closed.
 *
 * The source checks resolve aliases and qualified names before comparing symbols,
 * and exercise syntax-valid additions to each executable call category.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class RetentionPurgeAuditArchitectureTest extends TestCase
{
    private const string WRITER = 'src/Internal/Audit/Retention/RetentionPurgeAuditWriter.php';
    private const string PORT = 'src/Internal/Retention/AuditedRetentionPurgeAuditPort.php';
    private const string SERVICE = 'src/Internal/Retention/AuditedRetentionPurgeService.php';
    private const string PROVIDER = 'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog';

    public function testWriterDeclarationAndSensitiveContractAreClosed(): void
    {
        $reflection = new ReflectionClass(RetentionPurgeAuditWriter::class);
        self::assertTrue($reflection->isFinal());
        self::assertFalse($reflection->isReadonly());
        self::assertFalse($reflection->isAbstract());
        self::assertFalse($reflection->getParentClass());
        self::assertSame([], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['snapshot'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
        $expectedMethods = [
            '__construct' => 'public',
            'assertConnection' => 'public',
            'committed' => 'public',
            '__debugInfo' => 'public',
            '__serialize' => 'public',
            '__unserialize' => 'public',
            'write' => 'private',
            'catalog' => 'private',
            'definition' => 'private',
            'occurredAt' => 'private',
            'opaqueId' => 'private',
            'draft' => 'private',
            'selector' => 'private',
            'reference' => 'private',
            'value' => 'private',
            'failure' => 'private',
            'remap' => 'private',
        ];
        ksort($expectedMethods);
        self::assertSame($expectedMethods, $this->declaredMethods($reflection));
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $this->assertParameters($constructor, [
            ['opaqueIds',   HmacAuditOpaqueIdDeriver::class, false, true],
            ['selector',    AuditDeliverySelector::class,    false, true],
            ['delivery',    AuditDeliveryCoordinator::class, false, true],
            ['clock',       ClockInterface::class,           false, true],
            ['actor',       ActorContext::class,             false, true],
            ['correlation', AuditRecordCorrelation::class,   false, true],
            ['runtime',     AuditSourceRuntime::class,       false, false],
            ['buildId',     'string',                        false, true],
        ]);
        $this->assertMethod(
            $reflection->getMethod('assertConnection'),
            'void',
            [
                ['connection', Connection::class, false, true],
            ],
        );
        $this->assertMethod(
            $reflection->getMethod('committed'),
            'void',
            [
                ['auditId',     RetentionPurgeAuditId::class, false, true],
                ['operationId', OperationId::class,           false, true],
                ['target',      RetentionPurgeTarget::class,  false, true],
                ['tenant',      TenantRef::class,             true,  true],
            ],
        );
        $this->assertMethod($reflection->getMethod('__debugInfo'), 'array', []);
        $this->assertMethod($reflection->getMethod('__serialize'), 'array', []);
        $this->assertMethod(
            $reflection->getMethod('__unserialize'),
            'void',
            [
                ['data', 'array', false, true],
            ],
        );
    }

    public function testPrivateFacadeAndPortContractsAreClosed(): void
    {
        $port = new ReflectionClass(AuditedRetentionPurgeAuditPort::class);
        self::assertTrue($port->isFinal());
        self::assertTrue($port->isReadonly());
        self::assertSame([RetentionPurgeAuditPort::class], $port->getInterfaceNames());
        self::assertSame([], $port->getAttributes(PublicApi::class));
        self::assertSame(
            ['__clone', '__construct', '__debugInfo', '__serialize', '__unserialize', 'record'],
            $this->declaredMethodNames($port),
        );
        $constructor = $port->getConstructor();
        self::assertNotNull($constructor);
        $this->assertParameters($constructor, [
            ['connection', Connection::class,                false, true],
            ['writer',     RetentionPurgeAuditWriter::class, false, true],
            ['logger',     LoggerInterface::class,           false, true],
            ['schema',     'string',                         false, true],
        ]);
        self::assertSame('blackops', $constructor->getParameters()[3]->getDefaultValue());
        $this->assertMethod(
            $port->getMethod('record'),
            'void',
            [
                ['record', RetentionPurgeAuditRecord::class, false, true],
            ],
        );

        $service = new ReflectionClass(AuditedRetentionPurgeService::class);
        self::assertTrue($service->isFinal());
        self::assertTrue($service->isReadonly());
        self::assertSame([RetentionPurgeService::class], $service->getInterfaceNames());
        self::assertSame([], $service->getAttributes(PublicApi::class));
        self::assertSame(
            ['__clone', '__construct', '__debugInfo', '__serialize', '__unserialize', 'purge'],
            $this->declaredMethodNames($service),
        );
        $constructor = $service->getConstructor();
        self::assertNotNull($constructor);
        $this->assertParameters($constructor, [
            ['connection', Connection::class,                               false, true],
            ['writer',     RetentionPurgeAuditWriter::class,                false, true],
            ['logger',     LoggerInterface::class,                          false, true],
            ['schema',     'string',                                        false, true],
            ['clock',      ClockInterface::class,                           false, true],
            ['ids',        PostgreSqlRetentionPurgeAuditIdGenerator::class, false, true],
        ]);
        self::assertSame('blackops', $constructor->getParameters()[3]->getDefaultValue());
        self::assertInstanceOf(PostgreSqlSystemClock::class, $constructor->getParameters()[4]->getDefaultValue());
        self::assertInstanceOf(
            SymfonyRetentionPurgeAuditIdGenerator::class,
            $constructor->getParameters()[5]->getDefaultValue(),
        );
        $this->assertMethod(
            $service->getMethod('purge'),
            'BlackOps\\Core\\Retention\\RetentionPurgeResult',
            [
                ['policy',    'BlackOps\\Core\\Retention\\RetentionPolicy',    false, true],
                ['policyRef', 'BlackOps\\Core\\Retention\\RetentionPolicyRef', false, true],
                ['actor',     'BlackOps\\Core\\Retention\\RetentionActorRef',  false, true],
                ['now',       DateTimeImmutable::class,                        false, true],
            ],
        );
    }

    public function testWriterSourceGraphAndFixedProjectionRejectTargetedDrift(): void
    {
        $path = __DIR__ . '/../../' . self::WRITER;
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertSame([], $this->writerViolations($source));
        self::assertContains(
            'top-level-executable',
            $this->writerViolations($source . "\nfile_get_contents('writer-outside-class');\n"),
        );
        foreach ([
            'standalone delivery' => [
                'source' => $this->replaceOnce($source, 'deliverInCurrentTransaction(', 'deliverStandalone('),
                'violation' => 'delivery-edge',
            ],
            'extra coordinator method' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $delivery->deliverInCurrentTransaction($plan, $request);',
                    "            \$delivery->deliverInCurrentTransaction(\$plan, \$request);\n            \$delivery->append(\$request);",
                ),
                'violation' => 'method-call-graph',
            ],
            'extra global I/O' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $delivery->deliverInCurrentTransaction($plan, $request);',
                    "            file_get_contents('purge-canary');\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'violation' => 'function-call-graph',
            ],
            'extra direct SQL' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $delivery->deliverInCurrentTransaction($plan, $request);',
                    "            \$connection->insert('retention_purge_audits', []);\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'violation' => 'method-call-graph',
            ],
            'extra static helper' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $delivery->deliverInCurrentTransaction($plan, $request);',
                    "            PurgeHelper::run();\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'violation' => 'static-call-graph',
            ],
            'helper construction' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $delivery->deliverInCurrentTransaction($plan, $request);',
                    "            new \\stdClass();\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'violation' => 'helper-construction',
            ],
            'transaction ownership' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $delivery->deliverInCurrentTransaction($plan, $request);',
                    "            \$connection->beginTransaction();\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'violation' => 'transaction-ownership',
            ],
            'dynamic method' => [
                'source' => $this->replaceOnce($source, 'deliverInCurrentTransaction(', "{\$method}("),
                'violation' => 'dynamic-method',
            ],
        ] as $label => $mutation) {
            $violations = $this->writerViolations($mutation['source']);
            self::assertContains($mutation['violation'], $violations, $label);
        }
        foreach ([
            'retention.purge.committed',
            'retention_purged',
            'operation_data',
            'blackops.retention.purge',
            'retention-purge:',
        ] as $literal) {
            self::assertStringContainsString($literal, $source);
        }
    }

    public function testActivationAndSubjectGraphsRejectUnexpectedCallKinds(): void
    {
        $port = file_get_contents(__DIR__ . '/../../' . self::PORT);
        $service = file_get_contents(__DIR__ . '/../../' . self::SERVICE);
        self::assertIsString($port);
        self::assertIsString($service);
        self::assertSame([], $this->boundaryViolations($port, AuditedRetentionPurgeAuditPort::class));
        self::assertSame([], $this->boundaryViolations($service, AuditedRetentionPurgeService::class));
        self::assertContains('top-level-executable', $this->boundaryViolations(
            $port . "\nfile_get_contents('port-outside-class');\n",
            AuditedRetentionPurgeAuditPort::class,
        ));
        self::assertContains('top-level-executable', $this->boundaryViolations(
            $service . "\nfile_get_contents('service-outside-class');\n",
            AuditedRetentionPurgeService::class,
        ));
        $declareMutation = $this->replaceOnce(
            $service,
            "namespace BlackOps\\Internal\\Retention;\n\n",
            "namespace BlackOps\\Internal\\Retention;\ndeclare(ticks=1) { file_get_contents('declare-outside-class'); }\n\n",
        );
        self::assertContains('top-level-executable', $this->boundaryViolations(
            $declareMutation,
            AuditedRetentionPurgeService::class,
        ));
        $caseMutation = $this->replaceOnce($service, "\$values['native']->purge(", "\$values['native']->PURGE(");
        self::assertSame([], $this->boundaryViolations($caseMutation, AuditedRetentionPurgeService::class));
        $staticCaseMutation = $this->replaceOnce(
            $port,
            "        } catch (Throwable) {\n            throw self::failure();\n        }\n    }\n\n    public function record(",
            "        } catch (Throwable) {\n            throw SELF::FAILURE();\n        }\n    }\n\n    public function record(",
        );
        self::assertSame([], $this->boundaryViolations($staticCaseMutation, AuditedRetentionPurgeAuditPort::class));
        $aliasMutation = $this->replaceOnce(
            $port,
            'use BlackOps\\Internal\\Audit\\Retention\\RetentionPurgeAuditWriter;',
            'use BlackOps\\Internal\\Audit\\Retention\\RetentionPurgeAuditWriter as PurgeWriter;',
        );
        $aliasMutation = str_replace(
            [
                'RetentionPurgeAuditWriter $writer',
                'writer:RetentionPurgeAuditWriter',
                'instanceof RetentionPurgeAuditWriter',
            ],
            [
                'PurgeWriter $writer',
                'writer:PurgeWriter',
                'instanceof PurgeWriter',
            ],
            $aliasMutation,
            $aliasReferenceCount,
        );
        self::assertSame(4, $aliasReferenceCount);
        self::assertSame([], $this->boundaryViolations($aliasMutation, AuditedRetentionPurgeAuditPort::class));
        $inertMutation = $port . "\n// an inert insert and file_get_contents marker must not become AST edges\n";
        self::assertSame([], $this->boundaryViolations($inertMutation, AuditedRetentionPurgeAuditPort::class));
        $portMutation = $this->replaceOnce(
            $port,
            "            \$values['writer']->committed(",
            "            \$values['connection']->insert('retention_purge_audits', []);\n            \$values['writer']->committed(",
        );
        self::assertContains('method-call-graph', $this->boundaryViolations(
            $portMutation,
            AuditedRetentionPurgeAuditPort::class,
        ));
        $normalizedRecordFunctionMutation = $this->replaceOnce(
            $port,
            '        return new RetentionPurgeAuditRecord(',
            "        trim('normalized-record-canary');\n\n        return new RetentionPurgeAuditRecord(",
        );
        self::assertContains('function-call-graph', $this->boundaryViolations(
            $normalizedRecordFunctionMutation,
            AuditedRetentionPurgeAuditPort::class,
        ));
        $serviceMutation = $this->replaceOnce(
            $service,
            "            \$result = \$values['native']->purge(",
            "            file_get_contents('service-canary');\n            \$result = \$values['native']->purge(",
        );
        self::assertContains('function-call-graph', $this->boundaryViolations(
            $serviceMutation,
            AuditedRetentionPurgeService::class,
        ));
        $staticMutation = $this->replaceOnce(
            $service,
            "            \$result = \$values['native']->purge(",
            "            RetentionHelper::run();\n            \$result = \$values['native']->purge(",
        );
        self::assertContains('static-call-graph', $this->boundaryViolations(
            $staticMutation,
            AuditedRetentionPurgeService::class,
        ));
        $newMutation = $this->replaceOnce(
            $service,
            '            $this->snapshot = new \\SensitiveParameterValue([',
            "            new \\stdClass();\n            \$this->snapshot = new \\SensitiveParameterValue([",
        );
        self::assertContains('native-construction', $this->boundaryViolations(
            $newMutation,
            AuditedRetentionPurgeService::class,
        ));
        $dynamicMutation = $this->replaceOnce(
            $service,
            "            \$result = \$values['native']->purge(",
            "            \$result = \$values['native']->{\$method}(",
        );
        self::assertContains('dynamic-method', $this->boundaryViolations(
            $dynamicMutation,
            AuditedRetentionPurgeService::class,
        ));
    }

    public function testProductionActivationInventoryHasOnlyTheNewPrivateEdges(): void
    {
        $sources = $this->productionSources();
        self::assertSame(
            [self::WRITER, self::PORT, self::SERVICE],
            $this->referencePaths($sources, RetentionPurgeAuditWriter::class),
        );
        self::assertSame(
            [self::PORT, self::SERVICE],
            $this->referencePaths($sources, AuditedRetentionPurgeAuditPort::class),
        );
        self::assertSame([self::SERVICE], $this->referencePaths($sources, AuditedRetentionPurgeService::class));

        $alias = $sources;
        $alias['src/External/PurgeAlias.php'] = <<<'PHP'
            <?php
            use BlackOps\Internal\Retention\AuditedRetentionPurgeService as Purge;
            final class PurgeAlias { public function run(Purge $service): void {} }
            PHP;
        self::assertNotSame([self::SERVICE], $this->referencePaths($alias, AuditedRetentionPurgeService::class));
        $dynamic = $sources;
        $dynamic['src/External/PurgeDynamic.php'] = <<<'PHP'
            <?php
            $class = 'BlackOps\Internal\Retention\AuditedRetentionPurgeService';
            new $class($connection, $writer, $logger);
            PHP;
        self::assertNotSame([self::SERVICE], $this->referencePaths($dynamic, AuditedRetentionPurgeService::class));
    }

    /** @return list<string> */
    private function writerViolations(string $source): array
    {
        $violations = $this->classSourceViolations($source, RetentionPurgeAuditWriter::class, false, null);
        $class = $this->classNode($this->parse($source));
        if ($class === null) {
            return [...$violations, 'declaration'];
        }
        if ($this->methodNode($class, 'committed') === null) {
            $violations[] = 'public-methods';
        }
        $calls = [
            ...new NodeFinder()->findInstanceOf($class, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($class, NullsafeMethodCall::class),
        ];
        $allowed = [
            'authorization',
            'execution',
            'reference',
            'toarray',
            'usesconnection',
            'value',
            'write',
            'tostring',
            'from',
            'catalog',
            'definition',
            'occurredat',
            'opaqueid',
            'draft',
            'selector',
            'fordraft',
            'deliverincurrenttransaction',
            'now',
            'settimezone',
            'format',
            'derive',
            'type',
            'id',
            'getvalue',
            'failure',
        ];
        foreach ($calls as $call) {
            if ($call instanceof NullsafeMethodCall) {
                $violations[] = 'nullsafe-call';
            }
            if (!$call->name instanceof Identifier) {
                $violations[] = 'dynamic-method';
                continue;
            }
            $name = strtolower($call->name->toString());
            if (!in_array($name, $allowed, true)) {
                $violations[] = 'method-call-graph';
            }
            if (in_array($name, ['begintransaction', 'commit', 'rollback', 'transactional'], true)) {
                $violations[] = 'transaction-ownership';
            }
        }
        $deliveries = array_filter(
            $calls,
            static fn(MethodCall|NullsafeMethodCall $call): bool => (
                $call instanceof MethodCall
                && $call->name instanceof Identifier
                && strcasecmp($call->name->toString(), 'deliverInCurrentTransaction') === 0
            ),
        );
        if (count($deliveries) !== 1) {
            $violations[] = 'delivery-edge';
        }
        $functionNames = array_map(
            static fn(FuncCall $call): string => $call->name instanceof Name
                ? strtolower($call->name->toString())
                : 'dynamic',
            new NodeFinder()->findInstanceOf($class, FuncCall::class),
        );
        foreach ($functionNames as $name) {
            if (!in_array(
                $name,
                ['array_key_exists', 'array_keys', 'count', 'is_array', 'is_string', 'preg_match'],
                true,
            )) {
                $violations[] = 'function-call-graph';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($class, StaticCall::class) as $call) {
            if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                $violations[] = 'static-call-graph';
                continue;
            }
            $className = $this->sameSymbol($call->class->toString(), 'self')
                ? 'self'
                : strtolower($this->resolvedName($call->class));
            $method = strtolower($call->name->toString());
            $allowedStatic = [
                'blackops\\core\\audit\\auditrecordcorrelation::fromarray',
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring',
                'blackops\\core\\identifier\\retentionpurgeauditid::fromstring',
                'blackops\\core\\identifier\\operationid::fromstring',
                'blackops\\core\\retention\\retentionpurgetarget::from',
                strtolower(self::PROVIDER) . '::catalog',
                'self::failure',
                'self::remap',
            ];
            if (!in_array($className . '::' . $method, $allowedStatic, true)) {
                $violations[] = 'static-call-graph';
            }
        }
        $newNames = [];
        foreach (new NodeFinder()->findInstanceOf($class, New_::class) as $new) {
            $newNames[] = $new->class instanceof Name ? strtolower($this->resolvedName($new->class)) : 'dynamic';
        }
        $allowedNew = [
            'logicexception',
            'sensitiveparametervalue',
            'blackops\\core\\audit\\auditrecordactor',
            'blackops\\core\\audit\\audittypedreference',
            'blackops\\internal\\audit\\orchestration\\auditdeliveryrequest',
            'blackops\\internal\\audit\\orchestration\\auditdeliveryexception',
            'blackops\\core\\audit\\auditrecorddraft',
            'blackops\\core\\audit\\auditrecordevent',
            'blackops\\core\\audit\\auditrecordaction',
            'blackops\\core\\audit\\auditrecordresource',
            'blackops\\core\\audit\\auditrecordoutcome',
            'blackops\\core\\audit\\auditrecordpolicy',
            'blackops\\core\\audit\\auditrecordsource',
            'blackops\\core\\audit\\auditrecordevidence',
            'datetimezone',
        ];
        foreach ($newNames as $name) {
            if (!in_array($name, $allowedNew, true)) {
                $violations[] = 'helper-construction';
            }
        }
        $requestCount = count(array_filter($newNames, static fn(string $name): bool => str_ends_with(
            $name,
            '\\auditdeliveryrequest',
        )));
        if ($requestCount !== 1) {
            $violations[] = 'request-construction';
        }
        $evidenceCount = substr_count($source, 'new AuditRecordEvidence(1)');
        if ($evidenceCount !== 1) {
            $violations[] = 'evidence-construction';
        }
        $providerCalls = array_filter(
            new NodeFinder()->findInstanceOf($class, StaticCall::class),
            fn(StaticCall $call): bool => (
                $call->class instanceof Name
                && $call->name instanceof Identifier
                && $this->sameSymbol($this->resolvedName($call->class), self::PROVIDER)
                && $this->sameSymbol($call->name->toString(), 'catalog')
            ),
        );
        if (count($providerCalls) !== 1) {
            $violations[] = 'provider-reference';
        }
        foreach ([
            'retention.purge.committed',
            'retention_purged',
            'operation_data',
            'blackops.retention.purge',
        ] as $literal) {
            if (!str_contains($source, $literal)) {
                $violations[] = 'projection';
            }
        }
        $violations = [...$violations, ...$this->writerCallInventoryViolations($class)];
        $violations = [...$violations, ...$this->writerDeliveryViolations($class)];
        $violations = [...$violations, ...$this->writerForbiddenReferenceViolations($class)];

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function writerCallInventoryViolations(Class_ $class): array
    {
        $violations = [];
        $expectedMethodNames = [
            '__construct',
            '__debugInfo',
            '__serialize',
            '__unserialize',
            'assertConnection',
            'catalog',
            'committed',
            'definition',
            'draft',
            'failure',
            'occurredAt',
            'opaqueId',
            'reference',
            'remap',
            'selector',
            'value',
            'write',
        ];
        $actualMethodNames = [];
        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassMethod) {
                $actualMethodNames[] = $statement->name->toString();
            }
        }
        sort($expectedMethodNames);
        sort($actualMethodNames);
        if ($actualMethodNames !== $expectedMethodNames) {
            $violations[] = 'method-inventory';
        }

        $expectedCalls = [
            '__construct' => [
                '$actor->authorization()',
                '$actor->execution()',
                '$correlation->toarray()',
                '$this->reference()',
                '$this->reference()',
            ],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            'assertConnection' => ['$delivery->usesconnection()', '$this->value()'],
            'catalog' => [],
            'committed' => ['$this->write()'],
            'definition' => [],
            'draft' => ['$this->value()', '$this->value()', '$this->value()', '$this->value()'],
            'failure' => [],
            'occurredAt' => [
                '$clock->now()',
                '$clock->now()->settimezone()',
                '$clock->now()->settimezone()->format()',
                '$this->value()',
            ],
            'opaqueId' => ['$opaqueIds->derive()', '$this->value()'],
            'reference' => ['$reference->id()', '$reference->type()'],
            'remap' => ['$failure->failure()'],
            'selector' => ['$this->value()'],
            'value' => ['$this->snapshot->getvalue()'],
            'write' => [
                '$auditId->tostring()',
                '$delivery->deliverincurrenttransaction()',
                '$operationId->tostring()',
                '$tenant->id()',
                '$tenant->type()',
                '$this->catalog()',
                '$this->definition()',
                '$this->draft()',
                '$this->occurredat()',
                '$this->opaqueid()',
                '$this->opaqueid()',
                '$this->selector()',
                '$this->selector()->fordraft()',
                '$this->value()',
                '$this->value()',
                'blackops\\core\\identifier\\operationid::fromstring()->tostring()',
                'blackops\\core\\identifier\\retentionpurgeauditid::fromstring()->tostring()',
            ],
        ];
        foreach ($expectedCalls as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(fn(MethodCall|NullsafeMethodCall $call): string => $this->writerCallSignature(
                $call,
            ), $this->writerCalls($methodNode));
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'call-graph';
            }
        }

        $expectedFunctions = [
            '__construct' => [],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            'assertConnection' => [],
            'catalog' => [],
            'committed' => [],
            'definition' => ['count()'],
            'draft' => ['is_string()'],
            'failure' => [],
            'occurredAt' => ['preg_match()'],
            'opaqueId' => [],
            'reference' => [],
            'remap' => [],
            'selector' => [],
            'value' => ['array_key_exists()', 'array_keys()', 'is_array()', 'is_string()'],
            'write' => [],
        ];
        $expectedStaticCalls = [
            '__construct' => [
                'blackops\\core\\audit\\auditrecordcorrelation::fromarray()',
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'self::failure()',
            ],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => ['self::failure()'],
            'assertConnection' => ['self::failure()', 'self::failure()', 'self::remap()'],
            'catalog' => [
                strtolower(self::PROVIDER) . '::catalog()',
                'self::failure()',
            ],
            'committed' => [],
            'definition' => ['self::failure()', 'self::failure()'],
            'draft' => ['blackops\\core\\audit\\auditvalidationexception::assertsafestring()', 'self::failure()'],
            'failure' => [],
            'occurredAt' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'self::failure()',
                'self::failure()',
            ],
            'opaqueId' => ['self::failure()', 'self::failure()'],
            'reference' => [],
            'remap' => ['self::failure()', 'self::failure()'],
            'selector' => ['self::failure()'],
            'value' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'self::failure()',
                'self::failure()',
                'self::failure()',
            ],
            'write' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'blackops\\core\\identifier\\operationid::fromstring()',
                'blackops\\core\\identifier\\retentionpurgeauditid::fromstring()',
                'blackops\\core\\retention\\retentionpurgetarget::from()',
                'self::failure()',
                'self::failure()',
                'self::remap()',
            ],
        ];
        foreach ($expectedFunctions as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(
                fn(FuncCall $call): string => $this->writerFunctionCallSignature($call),
                new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], FuncCall::class),
            );
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'function-call-graph';
            }
        }
        foreach ($expectedStaticCalls as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(
                fn(StaticCall $call): string => $this->writerStaticCallSignature($call),
                new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], StaticCall::class),
            );
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'static-call-graph';
            }
        }

        $expectedNewClasses = [
            '__construct' => [
                'BlackOps\\Core\\Audit\\AuditRecordActor',
                'LogicException',
                'LogicException',
                'SensitiveParameterValue',
            ],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            'assertConnection' => ['LogicException'],
            'catalog' => ['LogicException'],
            'committed' => [],
            'definition' => [],
            'draft' => [
                'BlackOps\\Core\\Audit\\AuditRecordAction',
                'BlackOps\\Core\\Audit\\AuditRecordDraft',
                'BlackOps\\Core\\Audit\\AuditRecordEvent',
                'BlackOps\\Core\\Audit\\AuditRecordEvidence',
                'BlackOps\\Core\\Audit\\AuditRecordOutcome',
                'BlackOps\\Core\\Audit\\AuditRecordPolicy',
                'BlackOps\\Core\\Audit\\AuditRecordResource',
                'BlackOps\\Core\\Audit\\AuditRecordSource',
                'LogicException',
                'LogicException',
            ],
            'failure' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException'],
            'occurredAt' => ['DateTimeZone', 'LogicException'],
            'opaqueId' => [],
            'reference' => ['BlackOps\\Core\\Audit\\AuditTypedReference'],
            'remap' => [],
            'selector' => [],
            'value' => [],
            'write' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
                'LogicException',
            ],
        ];
        $expectedMethodKeys = $expectedMethodNames;
        sort($expectedMethodKeys);
        foreach ([$expectedCalls, $expectedFunctions, $expectedStaticCalls, $expectedNewClasses] as $inventory) {
            $actualMethodKeys = array_keys($inventory);
            sort($actualMethodKeys);
            if ($actualMethodKeys !== $expectedMethodKeys) {
                $violations[] = 'method-inventory';
            }
        }
        foreach ($expectedNewClasses as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = [];
            foreach (new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], New_::class) as $new) {
                $actual[] = $new->class instanceof Name ? $this->resolvedName($new->class) : 'dynamic';
            }
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = $method === '__construct' ? 'constructor-work' : 'helper-construction';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function writerDeliveryViolations(Class_ $class): array
    {
        $violations = [];
        $write = $this->methodNode($class, 'write');
        $committed = $this->methodNode($class, 'committed');
        if ($write === null || $committed === null) {
            return ['writer-methods'];
        }
        if ($this->countWriterThisCalls($committed, 'write') !== 1) {
            $violations[] = 'mutation-edge';
        }
        $selectorCalls = array_filter(
            new NodeFinder()->findInstanceOf($write, MethodCall::class),
            fn(MethodCall $call): bool => (
                $this->writerMethodName($call) === 'fordraft'
                && $call->var instanceof MethodCall
                && $this->writerMethodName($call->var) === 'selector'
                && $this->writerReceiver($call->var->var) === '$this'
            ),
        );
        $deliveryCalls = array_filter(
            new NodeFinder()->findInstanceOf($write, MethodCall::class),
            fn(MethodCall $call): bool => (
                $this->writerMethodName($call) === 'deliverincurrenttransaction'
                && $this->writerReceiver($call->var) === '$delivery'
            ),
        );
        if (count($selectorCalls) !== 1 || count($deliveryCalls) !== 1) {
            $violations[] = 'delivery-edge';
        }
        $requestCount = count(array_filter(
            new NodeFinder()->findInstanceOf($write->stmts ?? [], New_::class),
            fn(New_ $new): bool => (
                $new->class instanceof Name
                && $this->sameSymbol(
                    $this->resolvedName($new->class),
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
                )
            ),
        ));
        if ($requestCount !== 1) {
            $violations[] = 'request-construction';
        }
        $draft = $this->methodNode($class, 'draft');
        $evidenceCount = count(array_filter(
            $draft === null ? [] : new NodeFinder()->findInstanceOf($draft->stmts ?? [], New_::class),
            fn(New_ $new): bool => $this->isVersionOneEvidenceConstruction($new),
        ));
        if ($evidenceCount !== 1) {
            $violations[] = 'evidence-construction';
        }
        foreach ($this->writerCalls($class) as $call) {
            $name = $this->writerMethodName($call);
            if (in_array(
                $name,
                [
                    'deliverstandalone',
                    'append',
                    'appenddurable',
                    'appendrequired',
                    'begintransaction',
                    'commit',
                    'rollback',
                    'transactional',
                ],
                true,
            )) {
                $violations[] = 'forbidden-method:' . $name;
            }
            if ($call instanceof NullsafeMethodCall) {
                $violations[] = 'nullsafe-call';
            }
            if (!$call->name instanceof Identifier) {
                $violations[] = 'dynamic-method';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function writerForbiddenReferenceViolations(Class_ $class): array
    {
        $forbidden = [
            'BlackOps\\Core\\ExecutionContext',
            'BlackOps\\Core\\Retention\\RetentionActorRef',
            'BlackOps\\Core\\Retention\\RetentionPurgeAuditPort',
            'BlackOps\\Core\\Retention\\RetentionPurgeAuditRecord',
            'BlackOps\\Core\\Retention\\RetentionPurgeService',
            'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore',
            'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore',
            'BlackOps\\Internal\\Transaction\\OperationTransactionCoordinator',
        ];
        $violations = [];
        foreach (new NodeFinder()->findInstanceOf($class, Name::class) as $name) {
            $resolved = $this->resolvedName($name);
            foreach ($forbidden as $target) {
                if ($this->sameSymbol($resolved, $target)) {
                    $violations[] = 'forbidden-reference:' . $target;
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function boundaryViolations(string $source, string $className): array
    {
        $readonly = true;
        $interface = $className === AuditedRetentionPurgeAuditPort::class
            ? RetentionPurgeAuditPort::class
            : RetentionPurgeService::class;
        $violations = $this->classSourceViolations($source, $className, $readonly, $interface);
        $class = $this->classNode($this->parse($source));
        if ($class === null) {
            return [...$violations, 'declaration'];
        }
        $methodCalls = [
            ...new NodeFinder()->findInstanceOf($class, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($class, NullsafeMethodCall::class),
        ];
        $allowed = $className === AuditedRetentionPurgeAuditPort::class
            ? [
                'getvalue',
                'assertownedroot',
                'normalize',
                'normalizedrecord',
                'committed',
                'record',
                'values',
                'assertconnection',
                'isautocommit',
                'istransactionactive',
                'gettransactionnestinglevel',
                'fetchassociative',
                'operationstable',
                'id',
                'operationid',
                'target',
                'affectedcount',
                'policy',
                'purgedby',
                'purgedat',
                'tenant',
                'transporttenant',
                'validatetenant',
                'type',
                'tostring',
                'trim',
            ]
            : [
                'getvalue',
                'values',
                'assertadmission',
                'purge',
                'isautocommit',
                'istransactionactive',
                'gettransactionnestinglevel',
                'assertconnection',
            ];
        foreach ($methodCalls as $call) {
            if ($call instanceof NullsafeMethodCall) {
                $violations[] = 'nullsafe-call';
            }
            if (!$call->name instanceof Identifier) {
                $violations[] = 'dynamic-method';
                continue;
            }
            $method = strtolower($call->name->toString());
            if (!in_array($method, $allowed, true)) {
                $violations[] = 'method-call-graph';
            }
            if (in_array($method, ['begintransaction', 'commit', 'rollback', 'transactional'], true)) {
                $violations[] = 'transaction-ownership';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($class, FuncCall::class) as $call) {
            $name = $call->name instanceof Name ? strtolower($call->name->toString()) : 'dynamic';
            if (!in_array($name, ['array_keys', 'count', 'is_array', 'is_string', 'trim'], true)) {
                $violations[] = 'function-call-graph';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($class, StaticCall::class) as $call) {
            if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                $violations[] = 'static-call-graph';
                continue;
            }
            $staticClassName = $this->sameSymbol($call->class->toString(), 'self')
                ? 'self'
                : strtolower($this->resolvedName($call->class));
            $allowedStatic = $className === AuditedRetentionPurgeAuditPort::class
                ? [
                    'self::failure',
                    strtolower(OperationId::class) . '::fromstring',
                    strtolower(RetentionPurgeAuditId::class) . '::fromstring',
                ]
                : ['self::failure'];
            if (!in_array($staticClassName . '::' . strtolower($call->name->toString()), $allowedStatic, true)) {
                $violations[] = 'static-call-graph';
            }
        }
        $newClasses = array_map(
            fn(New_ $new): string => $new->class instanceof Name ? $this->resolvedName($new->class) : 'dynamic',
            new NodeFinder()->findInstanceOf($class, New_::class),
        );
        $expected = $className === AuditedRetentionPurgeAuditPort::class
            ? [
                PostgreSqlDeferredOperationSchema::class,
                'BlackOps\\Internal\\Retention\\LoggingRetentionPurgeAuditPort',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlRetentionPurgeAuditStore',
                'SensitiveParameterValue',
                'LogicException',
                'RuntimeException',
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\TenantRef',
                'BlackOps\\Core\\Identifier\\RetentionPurgeAuditId',
                'BlackOps\\Core\\Identifier\\OperationId',
                'BlackOps\\Core\\Retention\\RetentionPurgeAuditRecord',
                'BlackOps\\Core\\Exception\\DeferredTransportException',
            ]
            : [
                AuditedRetentionPurgeAuditPort::class,
                PostgreSqlRetentionPlanner::class,
                PostgreSqlTransportPayloadTombstoneService::class,
                PostgreSqlOutcomeRetentionDeleteService::class,
                PostgreSqlDeadLetterRetentionDeleteService::class,
                PostgreSqlJournalRetentionDeleteService::class,
                PostgreSqlIdempotencyRetentionDeleteService::class,
                PostgreSqlRetentionPurgeService::class,
                PostgreSqlSystemClock::class,
                SymfonyRetentionPurgeAuditIdGenerator::class,
                'SensitiveParameterValue',
                'LogicException',
                'RuntimeException',
                'BlackOps\\Core\\Exception\\DeferredTransportException',
            ];
        foreach ($newClasses as $newClass) {
            if (
                !in_array($newClass, $expected, true)
                && !in_array(strtolower($newClass), array_map('strtolower', $expected), true)
            ) {
                $violations[] = 'native-construction';
            }
        }
        if ($className === AuditedRetentionPurgeAuditPort::class) {
            $committed = array_filter(
                $methodCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && $call->name instanceof Identifier
                    && $this->sameSymbol($call->name->toString(), 'committed')
                ),
            );
            $fetches = array_filter(
                $methodCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && $call->name instanceof Identifier
                    && $this->sameSymbol($call->name->toString(), 'fetchAssociative')
                ),
            );
            if (count($committed) !== 1 || count($fetches) !== 1) {
                $violations[] = 'native-edge';
            }
            if (
                substr_count($source, 'SELECT operation_id::text AS operation_id') !== 1
                || substr_count($source, 'FROM {$schema->operationsTable()}') !== 1
                || substr_count($source, 'WHERE operation_id = :operation_id') !== 1
            ) {
                $violations[] = 'sql-inventory';
            }
        } else {
            $purges = array_filter(
                $methodCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && $call->name instanceof Identifier
                    && $this->sameSymbol($call->name->toString(), 'purge')
                ),
            );
            if (count($purges) !== 1) {
                $violations[] = 'native-edge';
            }
        }
        $violations = [...$violations, ...$this->boundaryCallInventoryViolations($class, $className)];

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function boundaryCallInventoryViolations(Class_ $class, string $className): array
    {
        $violations = [];
        $isPort = $className === AuditedRetentionPurgeAuditPort::class;
        $expectedMethodNames = $isPort
            ? [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertOwnedRoot',
                'failure',
                'normalize',
                'normalizedRecord',
                'record',
                'tenant',
                'transportTenant',
                'validateTenant',
                'values',
            ]
            : [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertAdmission',
                'failure',
                'purge',
                'values',
            ];
        $actualMethodNames = [];
        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassMethod) {
                $actualMethodNames[] = $statement->name->toString();
            }
        }
        sort($expectedMethodNames);
        sort($actualMethodNames);
        if ($actualMethodNames !== $expectedMethodNames) {
            $violations[] = 'method-inventory';
        }

        $expectedCalls = $isPort
            ? [
                '__clone' => [],
                '__construct' => [],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                'assertOwnedRoot' => [
                    '$connection->gettransactionnestinglevel()',
                    '$connection->isautocommit()',
                    '$connection->istransactionactive()',
                    '$writer->assertconnection()',
                ],
                'failure' => [],
                'normalize' => [
                    '$record->affectedcount()',
                    '$record->id()',
                    '$record->id()->tostring()',
                    '$record->operationid()',
                    '$record->operationid()->tostring()',
                    '$record->policy()',
                    '$record->purgedat()',
                    '$record->purgedby()',
                    '$record->target()',
                    '$record->target()',
                    '$record->tenant()',
                    'blackops\\core\\identifier\\operationid::fromstring()->tostring()',
                    'blackops\\core\\identifier\\retentionpurgeauditid::fromstring()->tostring()',
                    '$this->transporttenant()',
                    '$this->validatetenant()',
                    '$this->normalizedrecord()',
                    '$this->normalizedrecord()',
                ],
                'record' => [
                    '$normalized->id()',
                    '$normalized->operationid()',
                    '$normalized->target()',
                    '$normalized->tenant()',
                    '$this->assertownedroot()',
                    '$this->normalize()',
                    '$this->values()',
                    '$values[legacy]->record()',
                    '$values[writer]->committed()',
                ],
                'normalizedRecord' => [
                    '$record->affectedcount()',
                    '$record->policy()',
                    '$record->purgedat()',
                    '$record->purgedby()',
                    '$record->target()',
                ],
                'tenant' => [],
                'transportTenant' => [
                    '$connection->fetchassociative()',
                    '$schema->operationstable()',
                    '$this->tenant()',
                ],
                'validateTenant' => ['$tenant->id()', '$tenant->type()', '$this->tenant()'],
                'values' => ['$this->snapshot->getvalue()'],
            ]
            : [
                '__clone' => [],
                '__construct' => [],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                'assertAdmission' => [
                    '$connection->gettransactionnestinglevel()',
                    '$connection->isautocommit()',
                    '$connection->istransactionactive()',
                    '$writer->assertconnection()',
                ],
                'failure' => [],
                'purge' => ['$this->assertadmission()', '$this->values()', '$values[native]->purge()'],
                'values' => ['$this->snapshot->getvalue()'],
            ];
        foreach ($expectedCalls as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(fn(MethodCall|NullsafeMethodCall $call): string => $this->writerCallSignature(
                $call,
            ), $this->writerCalls($methodNode));
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'call-graph';
            }
        }

        $expectedFunctions = $isPort
            ? [
                '__clone' => [],
                '__construct' => [],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                'assertOwnedRoot' => [],
                'failure' => [],
                'normalize' => [],
                'normalizedRecord' => [],
                'record' => [],
                'tenant' => ['trim()', 'trim()'],
                'transportTenant' => ['array_keys()', 'is_array()', 'is_string()', 'is_string()', 'is_string()'],
                'validateTenant' => [],
                'values' => ['array_keys()', 'is_array()'],
            ]
            : [
                '__clone' => [],
                '__construct' => [],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                'assertAdmission' => [],
                'failure' => [],
                'purge' => [],
                'values' => ['array_keys()', 'is_array()'],
            ];
        $expectedStaticCalls = $isPort
            ? [
                '__clone' => ['self::failure()'],
                '__construct' => ['self::failure()'],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => ['self::failure()'],
                'assertOwnedRoot' => ['self::failure()'],
                'failure' => [],
                'normalize' => [
                    'blackops\\core\\identifier\\operationid::fromstring()',
                    'blackops\\core\\identifier\\retentionpurgeauditid::fromstring()',
                ],
                'normalizedRecord' => [
                    'blackops\\core\\identifier\\operationid::fromstring()',
                    'blackops\\core\\identifier\\retentionpurgeauditid::fromstring()',
                ],
                'record' => ['self::failure()'],
                'tenant' => [],
                'transportTenant' => [],
                'validateTenant' => [],
                'values' => ['self::failure()'],
            ]
            : [
                '__clone' => ['self::failure()'],
                '__construct' => ['self::failure()'],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => ['self::failure()'],
                'assertAdmission' => ['self::failure()'],
                'failure' => [],
                'purge' => ['self::failure()'],
                'values' => ['self::failure()'],
            ];
        foreach ($expectedFunctions as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(
                fn(FuncCall $call): string => $this->writerFunctionCallSignature($call),
                new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], FuncCall::class),
            );
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'function-call-graph';
            }
        }
        foreach ($expectedStaticCalls as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(
                fn(StaticCall $call): string => $this->writerStaticCallSignature($call),
                new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], StaticCall::class),
            );
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'static-call-graph';
            }
        }

        $expectedNewClasses = $isPort
            ? [
                '__clone' => [],
                '__construct' => [
                    'BlackOps\\Internal\\Retention\\LoggingRetentionPurgeAuditPort',
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlDeferredOperationSchema',
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlRetentionPurgeAuditStore',
                    'SensitiveParameterValue',
                ],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                'assertOwnedRoot' => ['RuntimeException'],
                'failure' => ['BlackOps\\Core\\Exception\\DeferredTransportException'],
                'normalize' => ['LogicException'],
                'normalizedRecord' => ['BlackOps\\Core\\Retention\\RetentionPurgeAuditRecord'],
                'record' => [],
                'tenant' => [
                    'BlackOps\\Core\\Audit\\AuditTypedReference',
                    'BlackOps\\Core\\TenantRef',
                    'RuntimeException',
                ],
                'transportTenant' => ['RuntimeException', 'RuntimeException'],
                'validateTenant' => [],
                'values' => ['LogicException'],
            ]
            : [
                '__clone' => [],
                '__construct' => [
                    AuditedRetentionPurgeAuditPort::class,
                    PostgreSqlDeadLetterRetentionDeleteService::class,
                    PostgreSqlIdempotencyRetentionDeleteService::class,
                    PostgreSqlJournalRetentionDeleteService::class,
                    PostgreSqlOutcomeRetentionDeleteService::class,
                    PostgreSqlRetentionPurgeService::class,
                    PostgreSqlRetentionPlanner::class,
                    PostgreSqlTransportPayloadTombstoneService::class,
                    'SensitiveParameterValue',
                ],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                'assertAdmission' => ['RuntimeException'],
                'failure' => ['BlackOps\\Core\\Exception\\DeferredTransportException'],
                'purge' => ['LogicException'],
                'values' => ['LogicException'],
            ];
        $expectedMethodKeys = $expectedMethodNames;
        sort($expectedMethodKeys);
        foreach ([$expectedCalls, $expectedFunctions, $expectedStaticCalls, $expectedNewClasses] as $inventory) {
            $actualMethodKeys = array_keys($inventory);
            sort($actualMethodKeys);
            if ($actualMethodKeys !== $expectedMethodKeys) {
                $violations[] = 'method-inventory';
            }
        }
        foreach ($expectedNewClasses as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = [];
            foreach (new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], New_::class) as $new) {
                $actual[] = $new->class instanceof Name ? $this->resolvedName($new->class) : 'dynamic';
            }
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = $method === '__construct' ? 'native-construction' : 'helper-construction';
            }
        }
        $violations = [...$violations, ...$this->boundaryConstructorArgumentViolations($class, $className)];

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function boundaryConstructorArgumentViolations(Class_ $class, string $className): array
    {
        $constructor = $this->methodNode($class, '__construct');
        if ($constructor === null) {
            return ['native-construction'];
        }

        $isPort = $className === AuditedRetentionPurgeAuditPort::class;
        $store = 'BlackOps\\Transport\\PostgreSql\\PostgreSqlRetentionPurgeAuditStore';
        $legacy = 'BlackOps\\Internal\\Retention\\LoggingRetentionPurgeAuditPort';
        $expected = $isPort
            ? [
                strtolower(PostgreSqlDeferredOperationSchema::class) => [['$schema']],
                strtolower($store) => [['$connection', '$schema']],
                strtolower($legacy) => [[
                    'new:' . strtolower($store) . '($connection,$schema)',
                    '$logger',
                ]],
                'sensitiveparametervalue' => [['array']],
            ]
            : [
                strtolower(AuditedRetentionPurgeAuditPort::class) => [['$connection', '$writer', '$logger', '$schema']],
                strtolower(PostgreSqlRetentionPlanner::class) => [['$connection', '$schema']],
                strtolower(PostgreSqlTransportPayloadTombstoneService::class) => [[
                    '$connection',
                    '$audit',
                    '$schema',
                    '$clock',
                    '$ids',
                ]],
                strtolower(PostgreSqlOutcomeRetentionDeleteService::class) => [[
                    '$connection',
                    '$audit',
                    '$schema',
                    '$clock',
                    '$ids',
                ]],
                strtolower(PostgreSqlDeadLetterRetentionDeleteService::class) => [[
                    '$connection',
                    '$audit',
                    '$schema',
                    '$clock',
                    '$ids',
                ]],
                strtolower(PostgreSqlJournalRetentionDeleteService::class) => [[
                    '$connection',
                    '$audit',
                    '$schema',
                    '$clock',
                    '$ids',
                ]],
                strtolower(PostgreSqlIdempotencyRetentionDeleteService::class) => [[
                    '$connection',
                    '$audit',
                    '$schema',
                    '$clock',
                    '$ids',
                ]],
                strtolower(PostgreSqlRetentionPurgeService::class) => [[
                    'new:' . strtolower(PostgreSqlRetentionPlanner::class) . '($connection,$schema)',
                    'new:'
                        . strtolower(PostgreSqlTransportPayloadTombstoneService::class)
                        . '($connection,$audit,$schema,$clock,$ids)',
                    'new:'
                        . strtolower(PostgreSqlOutcomeRetentionDeleteService::class)
                        . '($connection,$audit,$schema,$clock,$ids)',
                    'new:'
                        . strtolower(PostgreSqlDeadLetterRetentionDeleteService::class)
                        . '($connection,$audit,$schema,$clock,$ids)',
                    'new:'
                        . strtolower(PostgreSqlJournalRetentionDeleteService::class)
                        . '($connection,$audit,$schema,$clock,$ids)',
                    'new:'
                        . strtolower(PostgreSqlIdempotencyRetentionDeleteService::class)
                        . '($connection,$audit,$schema,$clock,$ids)',
                ]],
                'sensitiveparametervalue' => [['array']],
            ];
        $actual = [];
        foreach (new NodeFinder()->findInstanceOf($constructor->stmts ?? [], New_::class) as $new) {
            $key = $new->class instanceof Name ? strtolower($this->resolvedName($new->class)) : 'dynamic';
            $arguments = [];
            foreach ($new->args as $argument) {
                $arguments[] = $argument->name !== null || $argument->unpack
                    ? 'invalid-argument'
                    : $this->boundaryArgumentSignature($argument->value);
            }
            $actual[$key][] = $arguments;
        }
        $violations = [];
        foreach (array_values(array_unique([...array_keys($actual), ...array_keys($expected)])) as $key) {
            $actualArguments = $actual[$key] ?? [];
            $expectedArguments = $expected[$key] ?? [];
            sort($actualArguments);
            sort($expectedArguments);
            if ($actualArguments !== $expectedArguments) {
                $violations[] = 'native-construction';
            }
        }

        $snapshots = array_values(array_filter(
            new NodeFinder()->findInstanceOf($constructor->stmts ?? [], New_::class),
            fn(New_ $new): bool => (
                $new->class instanceof Name
                && $this->sameSymbol($this->resolvedName($new->class), 'SensitiveParameterValue')
            ),
        ));
        $expectedSnapshot = $isPort
            ? [
                ['connection', '$connection'],
                ['writer',     '$writer'],
                ['schema',     '$schemaHelper'],
                ['legacy',     '$legacy'],
            ]
            : [
                ['connection', '$connection'],
                ['writer',     '$writer'],
                ['native',     '$native'],
            ];
        if (count($snapshots) !== 1) {
            $violations[] = 'native-construction';
        } else {
            $snapshot = $snapshots[0];
            $actualSnapshot = [];
            $argument = count($snapshot->args) === 1 ? $snapshot->args[0]->value : null;
            if (!$argument instanceof \PhpParser\Node\Expr\Array_) {
                $violations[] = 'native-construction';
            } else {
                foreach ($argument->items as $item) {
                    if (
                        !$item instanceof \PhpParser\Node\ArrayItem
                        || !$item->key instanceof StringScalar
                        || !$item->value instanceof Variable
                        || !is_string($item->value->name)
                    ) {
                        $actualSnapshot[] = ['dynamic', 'dynamic'];
                        continue;
                    }
                    $actualSnapshot[] = [$item->key->value, '$' . $item->value->name];
                }
                if ($actualSnapshot !== $expectedSnapshot) {
                    $violations[] = 'native-construction';
                }
            }
        }

        return array_values(array_unique($violations));
    }

    private function boundaryArgumentSignature(Node $value): string
    {
        if ($value instanceof Variable && is_string($value->name)) {
            return '$' . $value->name;
        }
        if ($value instanceof New_) {
            return 'new:' . $this->boundaryNewSignature($value);
        }
        if ($value instanceof \PhpParser\Node\Expr\Array_) {
            return 'array';
        }

        return 'dynamic';
    }

    private function boundaryNewSignature(New_ $new): string
    {
        $class = $new->class instanceof Name ? strtolower($this->resolvedName($new->class)) : 'dynamic';
        $arguments = [];
        foreach ($new->args as $argument) {
            $arguments[] = $argument->name !== null || $argument->unpack
                ? 'invalid-argument'
                : $this->boundaryArgumentSignature($argument->value);
        }

        return $class . '(' . implode(',', $arguments) . ')';
    }

    /** @return array<string,string> */
    private function productionSources(): array
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
    private function referencePaths(array $sources, string $target): array
    {
        $paths = [];
        foreach ($sources as $path => $source) {
            if ($this->sourceReferencesTarget($source, $target)) {
                $paths[] = $path;
            }
        }
        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    private function sourceReferencesTarget(string $source, string $target): bool
    {
        try {
            $nodes = $this->parse($source);
        } catch (\Throwable) {
            return false;
        }
        $class = $this->classNode($nodes);
        if (
            $class !== null
            && $class->namespacedName instanceof Name
            && $this->sameSymbol($this->resolvedName($class->namespacedName), $target)
        ) {
            return true;
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if ($this->sameSymbol($this->resolvedName($name), $target)) {
                return true;
            }
        }

        $assignments = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Assign::class) as $assignment) {
            if (!$assignment->var instanceof Variable || !is_string($assignment->var->name)) {
                continue;
            }
            $assignments[$assignment->var->name] = $this->staticReferenceValue($assignment->expr);
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if ($new->class instanceof Variable && is_string($new->class->name)) {
                $value = $assignments[$new->class->name] ?? null;
                if ($value !== null && $this->sameSymbol($value, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function staticReferenceValue(Node $expression): ?string
    {
        if ($expression instanceof StringScalar) {
            return $expression->value;
        }
        if (
            $expression instanceof ClassConstFetch
            && $expression->class instanceof Name
            && $expression->name instanceof Identifier
            && $this->sameSymbol($expression->name->toString(), 'class')
        ) {
            return $this->resolvedName($expression->class);
        }

        return null;
    }

    /** @return list<MethodCall|NullsafeMethodCall> */
    private function writerCalls(Node|array $scope): array
    {
        return [
            ...new NodeFinder()->findInstanceOf($scope, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($scope, NullsafeMethodCall::class),
        ];
    }

    private function countWriterThisCalls(Node|array $scope, string $method): int
    {
        return count(array_filter(
            $this->writerCalls($scope),
            fn(MethodCall|NullsafeMethodCall $call): bool => (
                $call instanceof MethodCall
                && $this->writerReceiver($call->var) === '$this'
                && $this->writerMethodName($call) === strtolower($method)
            ),
        ));
    }

    private function writerReceiver(?Node $node): ?string
    {
        return $node instanceof Variable && is_string($node->name) ? '$' . $node->name : null;
    }

    private function writerMethodName(MethodCall|NullsafeMethodCall $call): string
    {
        return $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
    }

    private function writerCallSignature(MethodCall|NullsafeMethodCall $call): string
    {
        return (
            ($this->writerExpressionSignature($call->var) ?? 'unknown') . '->' . $this->writerMethodName($call) . '()'
        );
    }

    private function writerFunctionCallSignature(FuncCall $call): string
    {
        $name = $call->name instanceof Name ? strtolower(ltrim($call->name->toString(), '\\')) : 'dynamic';

        return $name . '()';
    }

    private function writerStaticCallSignature(StaticCall $call): string
    {
        if (!$call->class instanceof Name) {
            $class = 'dynamic';
        } elseif ($this->sameSymbol($call->class->toString(), 'self')) {
            $class = 'self';
        } else {
            $class = strtolower($this->resolvedName($call->class));
        }
        $method = $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';

        return $class . '::' . $method . '()';
    }

    private function writerExpressionSignature(Node $expression): ?string
    {
        if ($expression instanceof Variable && is_string($expression->name)) {
            return '$' . $expression->name;
        }
        if ($expression instanceof ArrayDimFetch) {
            $receiver = $this->writerExpressionSignature($expression->var);
            if ($receiver === null || !$expression->dim instanceof StringScalar) {
                return null;
            }

            return $receiver . '[' . $expression->dim->value . ']';
        }
        if ($expression instanceof PropertyFetch) {
            $receiver = $this->writerExpressionSignature($expression->var);
            $property = $expression->name instanceof Identifier ? strtolower($expression->name->toString()) : 'dynamic';
            if ($receiver === null) {
                return null;
            }

            return $receiver . '->' . $property;
        }
        if ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall) {
            $receiver = $this->writerExpressionSignature($expression->var);
            if ($receiver === null) {
                return null;
            }

            return $receiver . '->' . $this->writerMethodName($expression) . '()';
        }
        if ($expression instanceof StaticCall) {
            if (!$expression->class instanceof Name) {
                return null;
            }
            $class = $this->sameSymbol($expression->class->toString(), 'self')
                ? 'self'
                : strtolower($this->resolvedName($expression->class));
            $method = $expression->name instanceof Identifier ? strtolower($expression->name->toString()) : 'dynamic';

            return $class . '::' . $method . '()';
        }

        return null;
    }

    private function isVersionOneEvidenceConstruction(New_ $new): bool
    {
        return (
            $new->class instanceof Name
            && $this->sameSymbol($this->resolvedName($new->class), 'BlackOps\\Core\\Audit\\AuditRecordEvidence')
            && count($new->args) === 1
            && $new->args[0]->value instanceof Int_
            && $new->args[0]->value->value === 1
        );
    }

    /** @return list<string> */
    private function classSourceViolations(string $source, string $expected, bool $readonly, ?string $interface): array
    {
        try {
            $nodes = $this->parse($source);
        } catch (\Throwable) {
            return ['syntax'];
        }
        if ($this->hasExecutableTopLevelStatement($nodes)) {
            return ['top-level-executable'];
        }
        $classes = new NodeFinder()->findInstanceOf($nodes, ClassLike::class);
        if (count($classes) !== 1) {
            return ['declaration-inventory'];
        }
        $class = $this->classNode($nodes);
        if (
            $class === null
            || !$class->isFinal()
            || $class->isReadonly() !== $readonly
            || !$class->namespacedName instanceof Name
            || !$this->sameSymbol($this->resolvedName($class->namespacedName), $expected)
        ) {
            return ['declaration'];
        }
        if (
            $interface !== null
            && (
                count($class->implements) !== 1
                || !$this->sameSymbol($this->resolvedName($class->implements[0]), $interface)
            )
        ) {
            return ['interface'];
        }
        foreach (new NodeFinder()->findInstanceOf($class, Name::class) as $name) {
            if ($this->sameSymbol($this->resolvedName($name), PublicApi::class)) {
                return ['public-api'];
            }
        }

        return [];
    }

    /** @param list<Node> $nodes */
    private function hasExecutableTopLevelStatement(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                if ($this->hasExecutableTopLevelStatement($node->stmts)) {
                    return true;
                }
                continue;
            }
            if ($node instanceof \PhpParser\Node\Stmt\Declare_) {
                if ($node->stmts !== null && $node->stmts !== []) {
                    return true;
                }
                continue;
            }
            if (
                $node instanceof ClassLike
                || $node instanceof \PhpParser\Node\Stmt\GroupUse
                || $node instanceof \PhpParser\Node\Stmt\Nop
                || $node instanceof \PhpParser\Node\Stmt\Use_
            ) {
                continue;
            }
            return true;
        }

        return false;
    }

    /** @return array<string,string> */
    private function declaredMethods(ReflectionClass $reflection): array
    {
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $methods[$method->getName()] = $method->isPublic()
                ? 'public'
                : ($method->isProtected() ? 'protected' : 'private');
        }
        ksort($methods);

        return $methods;
    }

    /** @return list<string> */
    private function declaredMethodNames(ReflectionClass $reflection): array
    {
        $names = [];
        foreach ($this->declaredMethods($reflection) as $name => $visibility) {
            if ($visibility === 'public') {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    /** @return list<Node> */
    private function parse(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        if (!is_array($nodes)) {
            throw new \LogicException('Expected a statement list.');
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $traverser->traverse($nodes);
    }

    private function classNode(array $nodes): ?Class_
    {
        $classes = new NodeFinder()->findInstanceOf($nodes, Class_::class);

        return count($classes) === 1 ? $classes[0] : null;
    }

    private function methodNode(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if ($method->name instanceof Identifier && strcasecmp($method->name->toString(), $name) === 0) {
                return $method;
            }
        }

        return null;
    }

    private function resolvedName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return $resolved instanceof Name ? ltrim($resolved->toString(), '\\') : ltrim($name->toString(), '\\');
    }

    private function sameSymbol(string $left, string $right): bool
    {
        return strcasecmp(ltrim($left, '\\'), ltrim($right, '\\')) === 0;
    }

    private function replaceOnce(string $source, string $search, string $replacement): string
    {
        $result = str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count);

        return $result;
    }

    /** @param list<array{0:string,1:string,2:bool,3:bool}> $expected */
    private function assertParameters(ReflectionMethod $method, array $expected): void
    {
        self::assertCount(count($expected), $method->getParameters());
        foreach ($expected as $index => [$name, $type, $nullable, $sensitive]) {
            $parameter = $method->getParameters()[$index];
            self::assertSame($name, $parameter->getName());
            $reflectionType = $parameter->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $reflectionType);
            self::assertSame(ltrim($type, '?\\'), ltrim($reflectionType->getName(), '\\'));
            self::assertSame($nullable, $reflectionType->allowsNull());
            self::assertSame($sensitive, count($parameter->getAttributes(\SensitiveParameter::class)) === 1);
        }
    }

    /** @param list<array{0:string,1:string,2:bool,3:bool}> $expected */
    private function assertMethod(ReflectionMethod $method, string $return, array $expected): void
    {
        self::assertTrue($method->isPublic());
        self::assertSame(ltrim($return, '?\\'), ltrim($method->getReturnType()?->getName() ?? '', '\\'));
        $this->assertParameters($method, $expected);
    }
}
