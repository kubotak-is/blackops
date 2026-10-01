<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionHold;
use BlackOps\Core\Retention\RetentionHoldCategory;
use BlackOps\Core\Retention\RetentionHoldPort;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Retention\RetentionHoldAuditWriter;
use BlackOps\Internal\Retention\AuditedRetentionHoldPort;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use BlackOps\Transport\PostgreSql\SymfonyRetentionHoldIdGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
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
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Keeps the retention hold writer a closed, current-root projection boundary.
 *
 * The source checks intentionally resolve names before comparing symbols so an
 * alias, FQCN, or method-case mutation cannot turn a forbidden edge into a
 * passing short-name fixture.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class RetentionHoldAuditArchitectureTest extends TestCase
{
    private const string WRITER = 'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php';
    private const string ADAPTER = 'src/Internal/Retention/AuditedRetentionHoldPort.php';
    private const string PROVIDER = 'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog';

    public function testWriterDeclarationAndSensitiveContractAreClosed(): void
    {
        $reflection = new ReflectionClass(RetentionHoldAuditWriter::class);
        self::assertTrue($reflection->isFinal());
        self::assertFalse($reflection->isReadOnly());
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
            'placed' => 'public',
            'released' => 'public',
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
        $actualMethods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== RetentionHoldAuditWriter::class) {
                continue;
            }
            $actualMethods[$method->getName()] = $method->isPublic()
                ? 'public'
                : ($method->isProtected() ? 'protected' : 'private');
        }
        ksort($actualMethods);
        ksort($expectedMethods);
        self::assertSame($expectedMethods, $actualMethods);

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $this->assertParameters($constructor, [
            ['opaqueIds',   HmacAuditOpaqueIdDeriver::class, false, true],
            ['selector',    AuditDeliverySelector::class,    false, true],
            ['delivery',    AuditDeliveryCoordinator::class, false, true],
            ['clock',       'Psr\\Clock\\ClockInterface',    false, true],
            ['actor',       ActorContext::class,             false, true],
            ['correlation', AuditRecordCorrelation::class,   false, true],
            ['runtime',     AuditSourceRuntime::class,       false, false],
            ['buildId',     'string',                        false, true],
        ]);
        $this->assertMethod(
            $reflection->getMethod('assertConnection'),
            'void',
            [['connection', Connection::class, false, true]],
        );
        foreach (['placed', 'released'] as $name) {
            $this->assertMethod(
                $reflection->getMethod($name),
                'void',
                [
                    ['holdId', RetentionHoldId::class, false, true],
                    ['operationId', OperationId::class, false, true],
                    ['tenant', '?' . TenantRef::class, true, true],
                ],
            );
        }
        $this->assertMethod($reflection->getMethod('__debugInfo'), 'array', []);
        $this->assertMethod($reflection->getMethod('__serialize'), 'array', []);
        $this->assertMethod($reflection->getMethod('__unserialize'), 'void', [['data', 'array', false, true]]);
    }

    public function testAdapterDeclarationAndNativeContractAreClosed(): void
    {
        $reflection = new ReflectionClass(AuditedRetentionHoldPort::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertFalse($reflection->isAbstract());
        self::assertFalse($reflection->getParentClass());
        self::assertSame([RetentionHoldPort::class], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['snapshot'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );

        $publicMethods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === AuditedRetentionHoldPort::class && $method->isPublic()) {
                $publicMethods[$method->getName()] = true;
            }
        }
        ksort($publicMethods);
        self::assertSame(
            [
                '__clone' => true,
                '__construct' => true,
                '__debugInfo' => true,
                '__serialize' => true,
                '__unserialize' => true,
                'activeFor' => true,
                'place' => true,
                'release' => true,
            ],
            $publicMethods,
        );

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $constructorParameters = $constructor->getParameters();
        self::assertCount(5, $constructorParameters);
        $this->assertAdapterParameter($constructorParameters[0], 'connection', Connection::class, false, true);
        $this->assertAdapterParameter(
            $constructorParameters[1],
            'writer',
            RetentionHoldAuditWriter::class,
            false,
            true,
        );
        $this->assertAdapterParameter($constructorParameters[2], 'schema', 'string', false, true);
        $this->assertAdapterParameter($constructorParameters[3], 'clock', ClockInterface::class, false, true);
        $this->assertAdapterParameter(
            $constructorParameters[4],
            'ids',
            PostgreSqlRetentionHoldIdGenerator::class,
            false,
            true,
        );
        self::assertSame('blackops', $constructorParameters[2]->getDefaultValue());
        self::assertInstanceOf(PostgreSqlSystemClock::class, $constructorParameters[3]->getDefaultValue());
        self::assertInstanceOf(SymfonyRetentionHoldIdGenerator::class, $constructorParameters[4]->getDefaultValue());
        self::assertFalse($constructorParameters[0]->isDefaultValueAvailable());
        self::assertFalse($constructorParameters[1]->isDefaultValueAvailable());
        self::assertTrue($constructorParameters[2]->isDefaultValueAvailable());
        self::assertTrue($constructorParameters[3]->isDefaultValueAvailable());
        self::assertTrue($constructorParameters[4]->isDefaultValueAvailable());

        $this->assertMethod(
            $reflection->getMethod('place'),
            RetentionHold::class,
            [
                ['operationId', OperationId::class,           false, true],
                ['category',    RetentionHoldCategory::class, false, false],
                ['reason',      'string',                     false, true],
                ['placedBy',    RetentionActorRef::class,     false, true],
                ['placedAt',    DateTimeImmutable::class,     false, true],
            ],
        );
        $this->assertMethod(
            $reflection->getMethod('release'),
            RetentionHold::class,
            [
                ['holdId',     RetentionHoldId::class,   false, true],
                ['releasedBy', RetentionActorRef::class, false, true],
                ['releasedAt', DateTimeImmutable::class, false, true],
            ],
        );
        $this->assertMethod(
            $reflection->getMethod('activeFor'),
            'array',
            [['operationId', OperationId::class, false, true]],
        );
        $this->assertMethod($reflection->getMethod('__debugInfo'), 'array', []);
        $this->assertMethod($reflection->getMethod('__serialize'), 'array', []);
        $this->assertMethod($reflection->getMethod('__unserialize'), 'void', [['data', 'array', false, true]]);
        $this->assertMethod($reflection->getMethod('__clone'), 'void', []);
    }

    public function testResolvedAdapterInventoryAndNativeBoundariesRejectTargetedDrift(): void
    {
        $path = __DIR__ . '/../../' . self::ADAPTER;
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertSame([], $this->adapterSourceViolations([$path => $source]));

        $mutations = [
            'constructor instance work' => [
                'source' => $this->replaceOnce(
                    $source,
                    "            \$schemaHelper = new PostgreSqlDeferredOperationSchema(\$schema);",
                    "            \$this->values();\n            \$schemaHelper = new PostgreSqlDeferredOperationSchema(\$schema);",
                ),
                'violation' => 'constructor-work',
            ],
            'constructor function call' => [
                'source' => $this->replaceOnce(
                    $source,
                    "            \$schemaHelper = new PostgreSqlDeferredOperationSchema(\$schema);",
                    "            file_get_contents('constructor-canary');\n            \$schemaHelper = new PostgreSqlDeferredOperationSchema(\$schema);",
                ),
                'violation' => 'constructor-work',
            ],
            'transaction ownership' => [
                'source' => $this->replaceOnce(
                    $source,
                    "                \$this->assertOwnedRoot(\$values['connection']);\n                \$hold = \$values['native']->place(",
                    "                \$this->assertOwnedRoot(\$values['connection']);\n                \$values['connection']->beginTransaction();\n                \$hold = \$values['native']->place(",
                ),
                'violation' => 'transaction-ownership',
            ],
            'active read native mutation' => [
                'source' => $this->replaceOnce(
                    $source,
                    "            return \$this->values()['native']->activeFor(\$operationId);",
                    "            \$this->values()['native']->release(\$operationId, \$releasedBy, \$releasedAt);\n            return \$this->values()['native']->activeFor(\$operationId);",
                ),
                'violation' => 'active-read-edge',
            ],
            'different native connection' => [
                'source' => $this->replaceOnce(
                    $source,
                    'new PostgreSqlRetentionHoldStore($connection, $schema, $clock, $ids)',
                    'new PostgreSqlRetentionHoldStore($otherConnection, $schema, $clock, $ids)',
                ),
                'violation' => 'native-construction',
            ],
            'second subject query' => [
                'source' => $this->replaceOnce(
                    $source,
                    "        \$row = \$connection->fetchAssociative(\n",
                    "        \$connection->fetchAssociative('decoy', []);\n        \$row = \$connection->fetchAssociative(\n",
                ),
                'violation' => 'sql-inventory',
            ],
            'extra global I/O call' => [
                'source' => $this->replaceOnce(
                    $source,
                    "                \$hold = \$values['native']->place(",
                    "                file_get_contents('adapter-canary');\n                \$hold = \$values['native']->place(",
                ),
                'violation' => 'function-call-graph',
            ],
            'extra global SQL call' => [
                'source' => $this->replaceOnce(
                    $source,
                    "        \$row = \$connection->fetchAssociative(\n",
                    "        pg_query(\$connection, 'SELECT 1');\n        \$row = \$connection->fetchAssociative(\n",
                ),
                'violation' => 'function-call-graph',
            ],
            'extra static helper call' => [
                'source' => $this->replaceOnce(
                    $source,
                    "                \$hold = \$values['native']->place(",
                    "                AdapterHelper::run();\n                \$hold = \$values['native']->place(",
                ),
                'violation' => 'static-call-graph',
            ],
            'writer method drift' => [
                'source' => $this->replaceOnce(
                    $source,
                    "\$values['writer']->placed(",
                    "\$values['writer']->deliverInCurrentTransaction(",
                ),
                'violation' => 'writer-edge',
            ],
            'nullsafe writer call' => [
                'source' => $this->replaceOnce(
                    $source,
                    "\$values['writer']->released(",
                    "\$values['writer']?->released(",
                ),
                'violation' => 'nullsafe-call',
            ],
            'forbidden C3 type' => [
                'source' => $this->replaceOnce(
                    $source,
                    'private static function failure(): DeferredTransportException',
                    'private static function failure(\\BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector $selector): DeferredTransportException',
                ),
                'violation' => 'forbidden-reference:BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
            ],
            'failure call case' => [
                'source' => str_replace('self::failure()', 'SELF::FAILURE()', $source),
                'violation' => null,
            ],
            'writer alias' => [
                'source' => str_replace(
                    'use BlackOps\\Internal\\Audit\\Retention\\HoldWriter;',
                    'use BlackOps\\Internal\\Audit\\Retention\\RetentionHoldAuditWriter as HoldWriter;',
                    str_replace('RetentionHoldAuditWriter', 'HoldWriter', $source),
                ),
                'violation' => null,
            ],
        ];
        foreach ($mutations as $label => $mutation) {
            $nodes = $this->parse($mutation['source']);
            if ($mutation['violation'] === null) {
                self::assertSame([], $this->adapterSourceViolations([$path => $mutation['source']]), $label);
                continue;
            }
            self::assertContains(
                $mutation['violation'],
                $this->adapterSourceViolations([$path => $mutation['source']]),
                $label,
            );
            self::assertNotEmpty($nodes, $label);
        }
    }

    public function testAdapterRecursiveCallerAndConstructorInventoryRejectActivation(): void
    {
        $sources = $this->adapterProductionSources();
        self::assertSame(
            [
                AuditedRetentionHoldPort::class => [],
                RetentionHoldAuditWriter::class => [self::ADAPTER],
            ],
            $this->adapterReferenceInventory($sources),
        );
        self::assertSame(
            [
                AuditedRetentionHoldPort::class => [],
                RetentionHoldAuditWriter::class => [],
            ],
            $this->adapterConstructorInventory($sources),
        );
        self::assertSame([], $this->adapterGlobalViolations($sources));

        $alias = $sources;
        $alias['src/Internal/Retention/Nested/RetentionCaller.php'] = <<<'PHP'
            <?php

            namespace BlackOps\Internal\Retention\Nested;

            use BlackOps\Internal\Retention\AuditedRetentionHoldPort as HoldPort;

            final class RetentionCaller
            {
                public function accept(HoldPort $port): void {}
            }
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($alias));

        $qualified = $sources;
        $qualified['src/External/RetentionActivation.php'] = <<<'PHP'
            <?php

            new \BlackOps\Internal\Retention\AuditedRetentionHoldPort(
                $connection,
                $writer,
            );
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($qualified));

        $dynamic = $sources;
        $dynamic['src/External/RetentionDynamicActivation.php'] = <<<'PHP'
                <?php

                $class = 'BlackOps\\Internal\\Retention\\AuditedRetentionHoldPort';
                new $class($connection, $writer);
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($dynamic));

        $qualifiedDynamic = $sources;
        $qualifiedDynamic['src/External/RetentionQualifiedDynamicActivation.php'] = <<<'PHP'
            <?php

            $class = '\\blackops\\internal\\retention\\auditedretentionholdport';
            new $class($connection, $writer);
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($qualifiedDynamic));

        $writerConstruction = $sources;
        $writerConstruction['src/External/RetentionWriterActivation.php'] = <<<'PHP'
            <?php

            new \BlackOps\Internal\Audit\Retention\RetentionHoldAuditWriter(
                $opaqueIds,
                $selector,
                $delivery,
                $clock,
                $actor,
                $correlation,
                $runtime,
                $buildId,
            );
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($writerConstruction));

        $qualifiedCase = $sources;
        $qualifiedCase['src/External/RetentionCaseActivation.php'] = <<<'PHP'
            <?php

            new \blackops\internal\retention\auditedretentionholdport(
                $connection,
                $writer,
            );
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($qualifiedCase));

        $indirectClassConstant = $sources;
        $indirectClassConstant['src/External/RetentionClassConstantActivation.php'] = <<<'PHP'
            <?php

            $class = \BlackOps\Internal\Retention\AuditedRetentionHoldPort::class;
            new $class($connection, $writer);
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($indirectClassConstant));

        $splitDynamic = $sources;
        $splitDynamic['src/External/RetentionSplitDynamicActivation.php'] = <<<'PHP'
            <?php

            $prefix = 'BlackOps\\Internal\\Retention\\';
            $class = $prefix . 'AuditedRetentionHoldPort';
            new $class($connection, $writer);
            PHP;
        self::assertNotEmpty($this->adapterGlobalViolations($splitDynamic));

        $extraDml = $sources;
        $extraDml[self::ADAPTER] = $this->replaceOnce(
            $extraDml[self::ADAPTER],
            "                \$hold = \$values['native']->place(",
            "                \$values['connection']->insert('retention_holds', []);\n                \$hold = \$values['native']->place(",
        );
        self::assertContains('call-graph', $this->adapterSourceViolations($extraDml));

        $helperConstruction = $sources;
        $helperConstruction[self::ADAPTER] = $this->replaceOnce(
            $helperConstruction[self::ADAPTER],
            "                \$hold = \$values['native']->place(",
            "                new \\stdClass();\n                \$hold = \$values['native']->place(",
        );
        self::assertContains('helper-construction', $this->adapterSourceViolations($helperConstruction));
    }

    public function testResolvedSourceInventoryAndClosedWriterGraphAreExact(): void
    {
        $path = __DIR__ . '/../../' . self::WRITER;
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertSame([], $this->sourceViolations([$path => $source]));

        $mutations = [
            'standalone delivery' => [
                'source' => $this->replaceOnce($source, 'deliverInCurrentTransaction(', 'deliverStandalone('),
                'violation' => 'delivery-edge',
            ],
            'append call' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $delivery->deliverInCurrentTransaction($plan, $request);',
                    "            \$delivery->deliverInCurrentTransaction(\$plan, \$request);\n            \$delivery->append(\$request);",
                ),
                'violation' => 'forbidden-method:append',
            ],
            'provider alias and method case' => [
                'source' => str_replace(
                    'FrameworkOperationalAuditEventCatalog::catalog()',
                    'OperationalCatalog::CATALOG()',
                    str_replace(
                        'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog;',
                        'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog as OperationalCatalog;',
                        $source,
                    ),
                ),
                'violation' => null,
            ],
            'provider foreign alias' => [
                'source' => str_replace(
                    'FrameworkOperationalAuditEventCatalog::catalog()',
                    'OperationalCatalog::catalog()',
                    str_replace(
                        'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog;',
                        'use BlackOps\\Other\\FrameworkOperationalAuditEventCatalog as OperationalCatalog;',
                        $source,
                    ),
                ),
                'violation' => 'provider-reference',
            ],
            'dynamic provider receiver' => [
                'source' => str_replace(
                    'FrameworkOperationalAuditEventCatalog::catalog()',
                    '$provider::catalog()',
                    $source,
                ),
                'violation' => 'provider-reference',
            ],
            'constructor clock work' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $principal = $actor->authorization();',
                    "            \$clock->now();\n            \$principal = \$actor->authorization();",
                ),
                'violation' => 'constructor-work',
            ],
            'constructor function call' => [
                'source' => $this->replaceOnce(
                    $source,
                    '            $principal = $actor->authorization();',
                    "            file_get_contents('constructor-canary');\n            \$principal = \$actor->authorization();",
                ),
                'violation' => 'constructor-work',
            ],
        ];
        foreach ($mutations as $label => $mutation) {
            $nodes = $this->parse($mutation['source']);
            if ($mutation['violation'] === null) {
                self::assertSame([], $this->sourceViolations([$path => $mutation['source']]), $label);
                continue;
            }
            self::assertContains(
                $mutation['violation'],
                $this->sourceViolations([$path => $mutation['source']]),
                $label,
            );
            self::assertNotEmpty($nodes, $label);
        }
    }

    public function testOperationalProviderAndCurrentRootEdgesRejectQualifiedAndDynamicDrift(): void
    {
        $path = __DIR__ . '/../../' . self::WRITER;
        $source = file_get_contents($path);
        self::assertIsString($source);
        $qualified = str_replace(
            'FrameworkOperationalAuditEventCatalog::catalog()',
            '\\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::CATALOG()',
            $source,
        );
        self::assertSame([], $this->sourceViolations([$path => $qualified]));

        $dynamicMethod = str_replace('deliverInCurrentTransaction(', "{\$method}" . '(', $source);
        self::assertContains('delivery-edge', $this->sourceViolations([$path => $dynamicMethod]));
        self::assertContains('dynamic-method', $this->sourceViolations([$path => $dynamicMethod]));
    }

    public function testWriterSourceInventoryDoesNotAdmitExtraDeclarations(): void
    {
        $path = __DIR__ . '/../../' . self::WRITER;
        $source = file_get_contents($path);
        self::assertIsString($source);
        $extra = $source . <<<'PHP'

            final class UnexpectedRetentionWriterSibling {}
            PHP;
        self::assertContains('declaration-inventory', $this->sourceViolations([$path => $extra]));
    }

    public function testPurgeAuditBoundaryDoesNotEnterTheHoldAdapterGraph(): void
    {
        $production = $this->adapterProductionSources();
        $holdGraph = array_intersect_key(
            $production,
            array_flip([
                'src/Internal/Audit/Retention/RetentionHoldAuditWriter.php',
                'src/Internal/Retention/AuditedRetentionHoldPort.php',
            ]),
        );
        self::assertSame(2, count($holdGraph));
        foreach ($holdGraph as $path => $source) {
            self::assertStringNotContainsString('RetentionPurgeAuditWriter', $source, $path);
            self::assertStringNotContainsString('AuditedRetentionPurgeAuditPort', $source, $path);
            self::assertStringNotContainsString('AuditedRetentionPurgeService', $source, $path);
        }
        $purgeWriter = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Retention/RetentionPurgeAuditWriter.php');
        self::assertIsString($purgeWriter);
        self::assertStringNotContainsString('RetentionHoldAuditWriter', $purgeWriter);
        self::assertStringNotContainsString('RetentionHoldId', $purgeWriter);
    }

    /** @param array<string,string> $sources @return list<string> */
    private function sourceViolations(array $sources): array
    {
        $violations = [];
        if (array_keys($sources) !== [__DIR__ . '/../../' . self::WRITER]) {
            $violations[] = 'source-inventory';
        }
        foreach ($sources as $path => $source) {
            try {
                $nodes = $this->parse($source);
            } catch (\Throwable) {
                $violations[] = 'syntax';
                continue;
            }
            $classes = new NodeFinder()->findInstanceOf($nodes, ClassLike::class);
            if (count($classes) !== 1) {
                $violations[] = 'declaration-inventory';
                continue;
            }
            $class = $this->classNode($nodes);
            if (
                $class === null
                || !$class->isFinal()
                || $class->extends !== null
                || $class->implements !== []
                || !$class->namespacedName instanceof Name
                || !$this->sameSymbol($this->resolvedName($class->namespacedName), RetentionHoldAuditWriter::class)
            ) {
                $violations[] = 'declaration';
                continue;
            }
            if (!empty($class->attrGroups)) {
                foreach ($class->attrGroups as $group) {
                    foreach ($group->attrs as $attribute) {
                        if ($this->sameSymbol($this->resolvedName($attribute->name), PublicApi::class)) {
                            $violations[] = 'public-api';
                        }
                    }
                }
            }
            if ($this->methodNode($class, 'placed') === null || $this->methodNode($class, 'released') === null) {
                $violations[] = 'public-methods';
            }
            $violations = [...$violations, ...$this->providerViolations($class)];
            $violations = [...$violations, ...$this->deliveryViolations($class)];
            $violations = [...$violations, ...$this->writerConstructorViolations($class)];
            $violations = [...$violations, ...$this->forbiddenReferenceViolations($class)];
        }

        return array_values(array_unique($violations));
    }

    /** @param array<string,string> $sources @return list<string> */
    private function adapterSourceViolations(array $sources): array
    {
        $violations = [];
        $adapterPath = __DIR__ . '/../../' . self::ADAPTER;
        if (array_keys($sources) !== [$adapterPath]) {
            $violations[] = 'source-inventory';
        }
        foreach ($sources as $path => $source) {
            try {
                $nodes = $this->parse($source);
            } catch (\Throwable) {
                $violations[] = 'syntax';
                continue;
            }
            $classes = new NodeFinder()->findInstanceOf($nodes, ClassLike::class);
            if (count($classes) !== 1) {
                $violations[] = 'declaration-inventory';
                continue;
            }
            $class = $this->classNode($nodes);
            if (
                $class === null
                || !$class->isFinal()
                || !$class->isReadonly()
                || $class->extends !== null
                || count($class->implements) !== 1
                || !$this->sameSymbol($this->resolvedName($class->implements[0]), RetentionHoldPort::class)
                || !$class->namespacedName instanceof Name
                || !$this->sameSymbol($this->resolvedName($class->namespacedName), AuditedRetentionHoldPort::class)
            ) {
                $violations[] = 'declaration';
                continue;
            }
            foreach ($class->attrGroups as $group) {
                foreach ($group->attrs as $attribute) {
                    if ($this->sameSymbol($this->resolvedName($attribute->name), PublicApi::class)) {
                        $violations[] = 'public-api';
                    }
                }
            }
            $constructor = $this->methodNode($class, '__construct');
            if ($constructor === null) {
                $violations[] = 'constructor';
                continue;
            }
            $body = $constructor->stmts ?? [];
            $newExpressions = new NodeFinder()->findInstanceOf($body, New_::class);
            if (
                count($newExpressions) !== 3
                || !$this->adapterNewMatches(
                    $newExpressions[0] ?? null,
                    PostgreSqlDeferredOperationSchema::class,
                    ['schema'],
                )
                || !$this->adapterNewMatches($newExpressions[1] ?? null, PostgreSqlRetentionHoldStore::class, [
                    'connection',
                    'schema',
                    'clock',
                    'ids',
                ])
                || !$this->adapterNewClass($newExpressions[2] ?? null, 'SensitiveParameterValue')
            ) {
                $violations[] = 'native-construction';
            }
            if (
                new NodeFinder()->findInstanceOf($body, MethodCall::class) !== []
                || new NodeFinder()->findInstanceOf($body, NullsafeMethodCall::class) !== []
                || new NodeFinder()->findInstanceOf($body, FuncCall::class) !== []
                || count(array_filter(
                    new NodeFinder()->findInstanceOf($body, StaticCall::class),
                    fn(StaticCall $call): bool => !$this->adapterAllowedFailureCall($call),
                )) !== 0
            ) {
                $violations[] = 'constructor-work';
            }
            $violations = [...$violations, ...$this->adapterCallViolations($class)];
            $violations = [...$violations, ...$this->adapterExecutableCallViolations($class)];
            $violations = [...$violations, ...$this->adapterForbiddenReferenceViolations($class)];
            if (
                substr_count($source, 'SELECT operation_id::text AS operation_id') !== 1
                || substr_count($source, 'WHERE hold_id = :hold_id') !== 1
            ) {
                $violations[] = 'sql-inventory';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function adapterCallViolations(Class_ $class): array
    {
        $violations = [];
        $allCalls = $this->adapterCalls($class);
        foreach ($allCalls as $call) {
            if ($call instanceof NullsafeMethodCall) {
                $violations[] = 'nullsafe-call';
            }
            if (!$call->name instanceof Identifier) {
                $violations[] = 'dynamic-method';
            }
            if (in_array($this->methodName($call), ['begintransaction', 'commit', 'rollback'], true)) {
                $violations[] = 'transaction-ownership';
            }
        }

        $constructor = $this->methodNode($class, '__construct');
        $admission = $this->methodNode($class, 'assertAdmission');
        $place = $this->methodNode($class, 'place');
        $release = $this->methodNode($class, 'release');
        $activeFor = $this->methodNode($class, 'activeFor');
        $persistedTenant = $this->methodNode($class, 'persistedTenant');
        if (
            $constructor === null
            || $admission === null
            || $place === null
            || $release === null
            || $activeFor === null
            || $persistedTenant === null
        ) {
            return [...$violations, 'method-inventory'];
        }

        $expectedMethodNames = [
            '__clone',
            '__construct',
            '__debugInfo',
            '__serialize',
            '__unserialize',
            'activeFor',
            'assertAdmission',
            'assertOwnedRoot',
            'failure',
            'persistedTenant',
            'place',
            'release',
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

        $allowedCalls = [
            '__construct' => [],
            '__clone' => [],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            'place' => [
                '$this->values()',
                '$this->assertadmission()',
                '$values[connection]->transactional()',
                '$this->assertownedroot()',
                '$values[native]->place()',
                '$this->persistedtenant()',
                '$values[writer]->placed()',
                '$hold->id()',
                '$hold->operationid()',
            ],
            'release' => [
                '$this->values()',
                '$this->assertadmission()',
                '$values[connection]->transactional()',
                '$this->assertownedroot()',
                '$values[native]->release()',
                '$this->persistedtenant()',
                '$values[writer]->released()',
                '$hold->id()',
                '$hold->operationid()',
            ],
            'activeFor' => [
                '$this->values()',
                '$this->values()[native]->activefor()',
            ],
            'values' => ['$this->snapshot->getvalue()'],
            'assertAdmission' => [
                '$writer->assertconnection()',
                '$connection->isautocommit()',
                '$connection->istransactionactive()',
                '$connection->gettransactionnestinglevel()',
            ],
            'assertOwnedRoot' => [
                '$connection->isautocommit()',
                '$connection->istransactionactive()',
                '$connection->gettransactionnestinglevel()',
            ],
            'persistedTenant' => [
                '$connection->fetchassociative()',
                '$schema->retentionholdstable()',
                '$hold->id()',
                '$hold->id()->tostring()',
                '$hold->operationid()',
                '$hold->operationid()->tostring()',
            ],
            'failure' => [],
        ];
        foreach ($allowedCalls as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(fn(MethodCall|NullsafeMethodCall $call): string => $this->adapterCallSignature(
                $call,
            ), $this->adapterCalls($methodNode));
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'call-graph';
            }
        }

        $allowedNewClasses = [
            '__clone' => [],
            '__construct' => [
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlDeferredOperationSchema',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlRetentionHoldStore',
                'SensitiveParameterValue',
            ],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            'activeFor' => [],
            'assertAdmission' => ['RuntimeException'],
            'assertOwnedRoot' => ['RuntimeException'],
            'failure' => ['BlackOps\\Core\\Exception\\DeferredTransportException'],
            'persistedTenant' => [
                'BlackOps\\Core\\TenantRef',
                'RuntimeException',
                'RuntimeException',
            ],
            'place' => ['LogicException'],
            'release' => ['LogicException'],
            'values' => ['LogicException'],
        ];
        foreach ($allowedNewClasses as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
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

        $assertConnectionCalls = array_filter(
            $this->adapterCalls($admission),
            fn(MethodCall|NullsafeMethodCall $call): bool => (
                $call instanceof MethodCall
                && $this->methodName($call) === 'assertconnection'
                && $this->receiver($call->var) === '$writer'
            ),
        );
        if (count($assertConnectionCalls) !== 1) {
            $violations[] = 'connection-edge';
        }
        $placeCalls = $this->adapterCalls($place);
        $releaseCalls = $this->adapterCalls($release);
        if (
            count(array_filter(
                $placeCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && $this->methodName($call) === 'transactional'
                    && $this->adapterArrayReceiver($call->var, 'values', 'connection')
                ),
            )) !== 1
            || count(array_filter(
                $releaseCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && $this->methodName($call) === 'transactional'
                    && $this->adapterArrayReceiver($call->var, 'values', 'connection')
                ),
            )) !== 1
        ) {
            $violations[] = 'root-edge';
        }
        if (
            count(array_filter(
                $placeCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && $this->methodName($call) === 'place'
                    && $this->adapterArrayReceiver($call->var, 'values', 'native')
                ),
            )) !== 1
            || count(array_filter(
                $releaseCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && $this->methodName($call) === 'release'
                    && $this->adapterArrayReceiver($call->var, 'values', 'native')
                ),
            )) !== 1
        ) {
            $violations[] = 'native-edge';
        }
        $writerEdges = array_filter(
            [...$placeCalls, ...$releaseCalls],
            fn(MethodCall|NullsafeMethodCall $call): bool => $call instanceof MethodCall
            && $this->adapterArrayReceiver($call->var, 'values', 'writer'),
        );
        if (
            count(array_filter($writerEdges, fn(MethodCall $call): bool => $this->methodName($call) === 'placed')) !== 1
            || count(array_filter($writerEdges, fn(MethodCall $call): bool => $this->methodName($call) === 'released'))
                !== 1
        ) {
            $violations[] = 'writer-edge';
        }
        foreach ($writerEdges as $call) {
            if (!in_array($this->methodName($call), ['placed', 'released'], true)) {
                $violations[] = 'writer-edge';
            }
        }
        $activeCalls = array_filter(
            $this->adapterCalls($activeFor),
            fn(MethodCall|NullsafeMethodCall $call): bool => (
                $call instanceof MethodCall
                && $this->methodName($call) === 'activefor'
                && $this->adapterArrayReceiver($call->var, 'this', 'native')
            ),
        );
        if (count($activeCalls) !== 1) {
            $violations[] = 'active-edge';
        }
        foreach ($this->adapterCalls($activeFor) as $call) {
            if (!in_array($this->methodName($call), ['activefor', 'values'], true)) {
                $violations[] = 'active-read-edge';
            }
        }
        if (
            count(array_filter(
                $allCalls,
                fn(MethodCall|NullsafeMethodCall $call): bool => $this->methodName($call) === 'transactional',
            )) !== 2
        ) {
            $violations[] = 'root-edge';
        }
        $fetchCalls = array_filter(
            $this->adapterCalls($persistedTenant),
            fn(MethodCall|NullsafeMethodCall $call): bool => (
                $call instanceof MethodCall
                && $this->methodName($call) === 'fetchassociative'
                && $this->receiver($call->var) === '$connection'
            ),
        );
        $allFetchCalls = array_filter(
            $this->adapterCalls($class),
            fn(MethodCall|NullsafeMethodCall $call): bool => $this->methodName($call) === 'fetchassociative',
        );
        if (count($fetchCalls) !== 1 || count($allFetchCalls) !== 1) {
            $violations[] = 'sql-inventory';
        }
        foreach ($this->adapterCalls($class) as $call) {
            if (in_array(
                $this->methodName($call),
                ['executequery', 'executestatement', 'fetchallassociative', 'fetchone', 'prepare', 'query'],
                true,
            )) {
                $violations[] = 'sql-inventory';
            }
        }
        $schemaCalls = array_filter(
            $this->adapterCalls($persistedTenant),
            fn(MethodCall|NullsafeMethodCall $call): bool => (
                $call instanceof MethodCall
                && $this->methodName($call) === 'retentionholdstable'
                && $this->receiver($call->var) === '$schema'
            ),
        );
        if (count($schemaCalls) !== 1) {
            $violations[] = 'schema-edge';
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function adapterExecutableCallViolations(Class_ $class): array
    {
        $allowedFunctions = [
            '__clone' => [],
            '__construct' => [],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            'activeFor' => [],
            'assertAdmission' => [],
            'assertOwnedRoot' => [],
            'failure' => [],
            'persistedTenant' => [
                'array_keys()',
                'is_array()',
                'is_string()',
                'is_string()',
                'is_string()',
                'trim()',
                'trim()',
            ],
            'place' => [],
            'release' => [],
            'values' => ['array_keys()', 'is_array()'],
        ];
        $allowedStaticCalls = [
            '__clone' => ['self::failure()'],
            '__construct' => ['self::failure()'],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => ['self::failure()'],
            'activeFor' => ['self::failure()'],
            'assertAdmission' => ['self::failure()'],
            'assertOwnedRoot' => [],
            'failure' => [],
            'persistedTenant' => [],
            'place' => ['self::failure()'],
            'release' => ['self::failure()'],
            'values' => ['self::failure()'],
        ];
        $violations = [];
        foreach ($allowedFunctions as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(
                fn(FuncCall $call): string => $this->adapterFunctionCallSignature($call),
                new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], FuncCall::class),
            );
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'function-call-graph';
            }
        }
        foreach ($allowedStaticCalls as $method => $expected) {
            $methodNode = $this->methodNode($class, $method);
            if ($methodNode === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = array_map(
                fn(StaticCall $call): string => $this->adapterStaticCallSignature($call),
                new NodeFinder()->findInstanceOf($methodNode->stmts ?? [], StaticCall::class),
            );
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'static-call-graph';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function adapterForbiddenReferenceViolations(Class_ $class): array
    {
        $forbidden = [
            'BlackOps\\Core\\ExecutionContext',
            'BlackOps\\Core\\Audit\\AuditRecord',
            'BlackOps\\Core\\Audit\\AuditRecordDraft',
            'BlackOps\\Core\\Audit\\AuditRecordEvidence',
            'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
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

    /** @return list<MethodCall|NullsafeMethodCall> */
    private function adapterCalls(Node|array $scope): array
    {
        return [
            ...new NodeFinder()->findInstanceOf($scope, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($scope, NullsafeMethodCall::class),
        ];
    }

    private function adapterArrayReceiver(?Node $node, string $variable, string $key): bool
    {
        $arrayVariable = $node instanceof ArrayDimFetch ? $node->var : null;
        $isValuesCall =
            $variable === 'this'
            && $arrayVariable instanceof MethodCall
            && $this->receiver($arrayVariable->var) === '$this'
            && $this->methodName($arrayVariable) === 'values';

        return (
            $node instanceof ArrayDimFetch
            && ($arrayVariable instanceof Variable && $arrayVariable->name === $variable || $isValuesCall)
            && $node->dim instanceof StringScalar
            && $node->dim->value === $key
        );
    }

    /** @param list<string> $variables */
    private function adapterNewMatches(?New_ $new, string $class, array $variables): bool
    {
        if (!$new instanceof New_ || !$new->class instanceof Name || count($new->args) !== count($variables)) {
            return false;
        }
        if (!$this->sameSymbol($this->resolvedName($new->class), $class)) {
            return false;
        }
        foreach ($variables as $index => $variable) {
            if (!$new->args[$index]->value instanceof Variable || $new->args[$index]->value->name !== $variable) {
                return false;
            }
        }

        return true;
    }

    private function adapterNewClass(?New_ $new, string $class): bool
    {
        return (
            $new instanceof New_
            && $new->class instanceof Name
            && $this->sameSymbol($this->resolvedName($new->class), $class)
        );
    }

    private function adapterAllowedFailureCall(StaticCall $call): bool
    {
        return (
            $call->class instanceof Name
            && $this->sameSymbol($call->class->toString(), 'self')
            && $call->name instanceof Identifier
            && $this->sameSymbol($call->name->toString(), 'failure')
        );
    }

    private function adapterCallSignature(MethodCall|NullsafeMethodCall $call): string
    {
        $receiver = $this->adapterExpressionSignature($call->var);
        $method = $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';

        return ($receiver ?? 'unknown') . '->' . $method . '()';
    }

    private function adapterFunctionCallSignature(FuncCall $call): string
    {
        $name = $call->name instanceof Name ? strtolower(ltrim($call->name->toString(), '\\')) : 'dynamic';

        return $name . '()';
    }

    private function adapterStaticCallSignature(StaticCall $call): string
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

    private function adapterExpressionSignature(Node $expression): ?string
    {
        if ($expression instanceof Variable && is_string($expression->name)) {
            return '$' . $expression->name;
        }
        if ($expression instanceof ArrayDimFetch) {
            $receiver = $this->adapterExpressionSignature($expression->var);
            if ($receiver === null || !$expression->dim instanceof StringScalar) {
                return null;
            }

            return $receiver . '[' . $expression->dim->value . ']';
        }
        if ($expression instanceof PropertyFetch) {
            $receiver = $this->adapterExpressionSignature($expression->var);
            $property = $expression->name instanceof Identifier ? strtolower($expression->name->toString()) : 'dynamic';
            if ($receiver === null) {
                return null;
            }

            return $receiver . '->' . $property;
        }
        if ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall) {
            $receiver = $this->adapterExpressionSignature($expression->var);
            $method = $expression->name instanceof Identifier ? strtolower($expression->name->toString()) : 'dynamic';
            if ($receiver === null) {
                return null;
            }

            return $receiver . '->' . $method . '()';
        }

        return null;
    }

    /** @return array<string,string> */
    private function adapterProductionSources(): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources['src/' . str_replace($root . '/', '', $path)] = $source;
        }
        ksort($sources);

        return $sources;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function adapterReferenceInventory(array $sources): array
    {
        $targets = [AuditedRetentionHoldPort::class, RetentionHoldAuditWriter::class];
        $inventory = array_fill_keys($targets, []);
        foreach ($sources as $path => $source) {
            foreach (new NodeFinder()->findInstanceOf($this->parse($source), Name::class) as $name) {
                $resolved = $this->resolvedName($name);
                foreach ($targets as $target) {
                    if (!$this->sameSymbol($resolved, $target)) {
                        continue;
                    }
                    if (
                        $target === AuditedRetentionHoldPort::class && $path === self::ADAPTER
                        || $target === RetentionHoldAuditWriter::class && $path === self::WRITER
                    ) {
                        continue;
                    }
                    $inventory[$target][] = $path;
                }
            }
        }
        foreach ($inventory as &$paths) {
            $paths = array_values(array_unique($paths));
            sort($paths);
        }
        unset($paths);

        return $inventory;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function adapterConstructorInventory(array $sources): array
    {
        $targets = [AuditedRetentionHoldPort::class, RetentionHoldAuditWriter::class];
        $inventory = array_fill_keys($targets, []);
        foreach ($sources as $path => $source) {
            foreach (new NodeFinder()->findInstanceOf($this->parse($source), New_::class) as $new) {
                if (!$new->class instanceof Name) {
                    continue;
                }
                $resolved = $this->resolvedName($new->class);
                foreach ($targets as $target) {
                    if ($this->sameSymbol($resolved, $target)) {
                        $inventory[$target][] = $path;
                    }
                }
            }
        }
        foreach ($inventory as &$paths) {
            $paths = array_values(array_unique($paths));
            sort($paths);
        }
        unset($paths);

        return $inventory;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function adapterGlobalViolations(array $sources): array
    {
        $violations = [];
        $expectedReferences = [
            AuditedRetentionHoldPort::class => [],
            RetentionHoldAuditWriter::class => [self::ADAPTER],
        ];
        if ($this->adapterReferenceInventory($sources) !== $expectedReferences) {
            $violations[] = 'caller-inventory';
        }
        $expectedConstructors = [
            AuditedRetentionHoldPort::class => [],
            RetentionHoldAuditWriter::class => [],
        ];
        if ($this->adapterConstructorInventory($sources) !== $expectedConstructors) {
            $violations[] = 'constructor-inventory';
        }
        foreach ($sources as $source) {
            $nodes = $this->parse($source);
            $staticStrings = array_map(
                static fn(StringScalar $string): string => $string->value,
                new NodeFinder()->findInstanceOf($nodes, StringScalar::class),
            );
            $assignments = [];
            foreach (new NodeFinder()->findInstanceOf($nodes, Assign::class) as $assignment) {
                if (!$assignment->var instanceof Variable || !is_string($assignment->var->name)) {
                    continue;
                }
                $value = $this->staticStringValue($assignment->expr, $assignments);
                if ($value === null) {
                    continue;
                }
                $assignments[$assignment->var->name] = $value;
                $staticStrings[] = $value;
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, Concat::class) as $concat) {
                $value = $this->staticStringValue($concat, $assignments);
                if ($value !== null) {
                    $staticStrings[] = $value;
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
                if ($new->class instanceof Name) {
                    continue;
                }
                $value = $this->staticStringValue($new->class, $assignments);
                if ($value !== null) {
                    $staticStrings[] = $value;
                }
            }
            foreach ($staticStrings as $value) {
                foreach ([AuditedRetentionHoldPort::class, RetentionHoldAuditWriter::class] as $target) {
                    if ($this->sameSymbol($value, $target)) {
                        $violations[] = 'dynamic-activation';
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @param array<string,string> $assignments */
    private function staticStringValue(Node $node, array $assignments): ?string
    {
        if ($node instanceof StringScalar) {
            return $node->value;
        }
        if ($node instanceof Variable && is_string($node->name)) {
            return $assignments[$node->name] ?? null;
        }
        if (!$node instanceof Concat) {
            return null;
        }
        $left = $this->staticStringValue($node->left, $assignments);
        $right = $this->staticStringValue($node->right, $assignments);
        if ($left === null || $right === null) {
            return null;
        }

        return $left . $right;
    }

    /** @return list<string> */
    private function providerViolations(Class_ $class): array
    {
        $violations = [];
        $catalogMethod = $this->methodNode($class, 'catalog');
        $calls = new NodeFinder()->findInstanceOf($class, StaticCall::class);
        $providerCalls = array_values(array_filter($calls, fn(StaticCall $call): bool => $this->isNamedStaticCall(
            $call,
            self::PROVIDER,
            'catalog',
        )));
        if (count($providerCalls) !== 1 || $catalogMethod === null) {
            $violations[] = 'provider-reference';
        } elseif (!in_array(
            $providerCalls[0],
            new NodeFinder()->findInstanceOf($catalogMethod, StaticCall::class),
            true,
        )) {
            $violations[] = 'provider-reference';
        }
        foreach ($calls as $call) {
            if (
                $call->name instanceof Identifier
                && $this->sameSymbol($call->name->toString(), 'catalog')
                && !$call->class instanceof Name
            ) {
                $violations[] = 'provider-reference';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function writerConstructorViolations(Class_ $class): array
    {
        $constructor = $this->methodNode($class, '__construct');
        if ($constructor === null) {
            return ['constructor-work'];
        }
        $violations = [];
        if (new NodeFinder()->findInstanceOf($constructor->stmts ?? [], FuncCall::class) !== []) {
            $violations[] = 'constructor-work';
        }
        $methodCalls = new NodeFinder()->findInstanceOf($constructor->stmts ?? [], MethodCall::class);
        foreach ($methodCalls as $call) {
            if (
                $this->receiver($call->var) === '$actor'
                && in_array($this->methodName($call), ['authorization', 'execution'], true)
                || $this->receiver($call->var) === '$this' && $this->methodName($call) === 'reference'
                || $this->receiver($call->var) === '$correlation' && $this->methodName($call) === 'toarray'
            ) {
                continue;
            }
            $violations[] = 'constructor-work';
        }
        $expectedMethodCalls = [
            '$actor->authorization' => 1,
            '$actor->execution' => 1,
            '$this->reference' => 2,
            '$correlation->toarray' => 1,
        ];
        $actualMethodCalls = [
            '$actor->authorization' => 0,
            '$actor->execution' => 0,
            '$this->reference' => 0,
            '$correlation->toarray' => 0,
        ];
        foreach ($methodCalls as $call) {
            $key = match (true) {
                $this->receiver($call->var) === '$actor' && $this->methodName($call) === 'authorization'
                    => '$actor->authorization',
                $this->receiver($call->var) === '$actor' && $this->methodName($call) === 'execution'
                    => '$actor->execution',
                $this->receiver($call->var) === '$this' && $this->methodName($call) === 'reference'
                    => '$this->reference',
                $this->receiver($call->var) === '$correlation' && $this->methodName($call) === 'toarray'
                    => '$correlation->toarray',
                default => null,
            };
            if ($key !== null) {
                $actualMethodCalls[$key]++;
            }
        }
        if ($actualMethodCalls !== $expectedMethodCalls) {
            $violations[] = 'constructor-work';
        }

        $staticCalls = new NodeFinder()->findInstanceOf($constructor->stmts ?? [], StaticCall::class);
        foreach ($staticCalls as $call) {
            if (
                $this->isNamedStaticCall($call, 'BlackOps\\Core\\Audit\\AuditValidationException', 'assertSafeString')
                || $this->isNamedStaticCall($call, 'BlackOps\\Core\\Audit\\AuditRecordCorrelation', 'fromArray')
                || $call->class instanceof Name
                && $this->sameSymbol($call->class->toString(), 'self')
                && $call->name instanceof Identifier
                && $this->sameSymbol($call->name->toString(), 'failure')
            ) {
                continue;
            }
            $violations[] = 'constructor-work';
        }
        $expectedStaticCalls = [
            'assertSafeString' => 1,
            'fromArray' => 1,
            'failure' => 1,
        ];
        $actualStaticCalls = array_fill_keys(array_keys($expectedStaticCalls), 0);
        foreach ($staticCalls as $call) {
            $name = $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
            if ($name === 'assertsafestring') {
                $actualStaticCalls['assertSafeString']++;
            } elseif ($name === 'fromarray') {
                $actualStaticCalls['fromArray']++;
            } elseif ($name === 'failure') {
                $actualStaticCalls['failure']++;
            }
        }
        if ($actualStaticCalls !== $expectedStaticCalls) {
            $violations[] = 'constructor-work';
        }

        $newClasses = [];
        foreach (new NodeFinder()->findInstanceOf($constructor->stmts ?? [], New_::class) as $new) {
            if ($new->class instanceof Name) {
                $newClasses[] = $this->resolvedName($new->class);
            }
        }
        $expectedNewClasses = [
            'BlackOps\\Core\\Audit\\AuditRecordActor' => 1,
            'LogicException' => 2,
            'SensitiveParameterValue' => 1,
        ];
        $actualNewClasses = array_fill_keys(array_keys($expectedNewClasses), 0);
        foreach ($newClasses as $newClass) {
            foreach (array_keys($expectedNewClasses) as $expectedClass) {
                if ($this->sameSymbol($newClass, $expectedClass)) {
                    $actualNewClasses[$expectedClass]++;
                }
            }
        }
        if ($actualNewClasses !== $expectedNewClasses || count($newClasses) !== array_sum($expectedNewClasses)) {
            $violations[] = 'constructor-work';
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function deliveryViolations(Class_ $class): array
    {
        $violations = [];
        $write = $this->methodNode($class, 'write');
        $placed = $this->methodNode($class, 'placed');
        $released = $this->methodNode($class, 'released');
        if ($write === null || $placed === null || $released === null) {
            return ['writer-methods'];
        }
        if ($this->countThisCalls($placed, 'write') !== 1 || $this->countThisCalls($released, 'write') !== 1) {
            $violations[] = 'mutation-edge';
        }
        $selectorCalls = array_values(array_filter(
            new NodeFinder()->findInstanceOf($write, MethodCall::class),
            fn(MethodCall $call): bool => (
                $this->methodName($call) === 'fordraft'
                && $call->var instanceof MethodCall
                && $this->methodName($call->var) === 'selector'
                && $this->receiver($call->var->var) === '$this'
            ),
        ));
        $deliveryCalls = array_values(array_filter(
            new NodeFinder()->findInstanceOf($write, MethodCall::class),
            fn(MethodCall $call): bool => (
                $this->methodName($call) === 'deliverincurrenttransaction'
                && $this->receiver($call->var) === '$delivery'
            ),
        ));
        if (count($selectorCalls) !== 1 || count($deliveryCalls) !== 1) {
            $violations[] = 'delivery-edge';
        }
        $allCalls = [
            ...new NodeFinder()->findInstanceOf($class, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($class, NullsafeMethodCall::class),
        ];
        foreach ($allCalls as $call) {
            $name = $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
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
            if ($call->name instanceof Node\Expr) {
                $violations[] = 'dynamic-method';
            }
        }
        $requestCount = count(array_filter(
            new NodeFinder()->findInstanceOf($write, New_::class),
            fn(New_ $new): bool => $this->sameSymbol(
                $this->resolvedName($new->class),
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
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

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function forbiddenReferenceViolations(Class_ $class): array
    {
        $forbidden = [
            'BlackOps\\Core\\ExecutionContext',
            'BlackOps\\Core\\Retention\\RetentionHold',
            'BlackOps\\Core\\Retention\\RetentionActorRef',
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlRetentionHoldStore',
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
        foreach (new NodeFinder()->findInstanceOf($class, MethodCall::class) as $call) {
            $name = $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
            if (in_array($name, ['transactional', 'begintransaction', 'commit', 'rollback'], true)) {
                $violations[] = 'transaction-ownership';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<Node> */
    private function parse(string $source): array
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $nodes = $parser->parse($source);
        if (!is_array($nodes)) {
            throw new \LogicException('The source did not produce a statement list.');
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $resolved = $traverser->traverse($nodes);

        return $resolved;
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

    private function countThisCalls(Node|array $scope, string $method): int
    {
        return count(array_filter(
            new NodeFinder()->findInstanceOf($scope, MethodCall::class),
            fn(MethodCall $call): bool => (
                $this->receiver($call->var) === '$this'
                && $this->methodName($call) === strtolower($method)
            ),
        ));
    }

    private function isNamedStaticCall(StaticCall $call, string $class, string $method): bool
    {
        return (
            $call->class instanceof Name
            && $this->sameSymbol($this->resolvedName($call->class), $class)
            && $call->name instanceof Identifier
            && $this->sameSymbol($call->name->toString(), $method)
        );
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

    private function receiver(?Node $node): ?string
    {
        return $node instanceof Variable && is_string($node->name) ? '$' . $node->name : null;
    }

    private function methodName(MethodCall|NullsafeMethodCall $call): string
    {
        return $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
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

    private function assertAdapterParameter(
        ReflectionParameter $parameter,
        string $name,
        string $type,
        bool $nullable,
        bool $sensitive,
    ): void {
        self::assertSame($name, $parameter->getName());
        $reflectionType = $parameter->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $reflectionType);
        self::assertSame(ltrim($type, '?\\'), ltrim($reflectionType->getName(), '\\'));
        self::assertSame($nullable, $reflectionType->allowsNull());
        self::assertSame($sensitive, count($parameter->getAttributes(\SensitiveParameter::class)) === 1);
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
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
    }

    /** @param list<array{0:string,1:string,2:bool,3:bool}> $expected */
    private function assertMethod(ReflectionMethod $method, string $return, array $expected): void
    {
        self::assertTrue($method->isPublic());
        self::assertSame($return, $method->getReturnType()?->getName());
        $this->assertParameters($method, $expected);
    }
}
