<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Internal\Audit\Replay\ObserverReplayAuditFailure;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditHook;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriter;
use BlackOps\Internal\Replay\ObserverReplayRuntime;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayAdvanceRoot;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRoot;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginTransaction;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayPhaseHook;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

final class ObserverReplayAuditArchitectureTest extends TestCase
{
    public function testPhaseInterfaceAndImplementationsHaveTheExactSensitiveSurface(): void
    {
        $interface = new ReflectionClass(PostgreSqlObserverReplayPhaseHook::class);
        self::assertTrue($interface->isInterface());
        self::assertSame(
            [
                'assertEntry',
                'failed',
                'finished',
                'progressed',
                'started',
            ],
            $this->methodNames($interface),
        );
        foreach ($interface->getMethods() as $method) {
            self::assertSame('void', $method->getReturnType()?->getName(), $method->getName());
            $this->assertSensitive($method->getParameters(), $method->getName());
        }
        $interfaceParameters = array_map(
            static fn(ReflectionMethod $method): array => array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                $method->getParameters(),
            ),
            array_combine(
                $interface->getMethods()
                    ? array_map(
                        static fn(ReflectionMethod $method): string => $method->getName(),
                        $interface->getMethods(),
                    ) : [],
                $interface->getMethods(),
            ),
        );
        ksort($interfaceParameters);
        self::assertSame(
            [
                'assertEntry' => ['connection', 'checkpoint'],
                'failed' => ['connection', 'checkpoint', 'auditId'],
                'finished' => ['connection', 'checkpoint', 'auditId', 'state'],
                'progressed' => ['connection', 'checkpoint', 'auditId', 'cursor', 'source'],
                'started' => ['connection', 'checkpoint', 'auditId'],
            ],
            $interfaceParameters,
        );
        $progressed = $interface->getMethod('progressed')->getParameters();
        self::assertSame(JournalRecord::class, $progressed[4]->getType()?->getName());
        self::assertFalse($progressed[4]->allowsNull());

        self::assertSame(
            [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertEntry',
                'failed',
                'finished',
                'progressed',
                'started',
            ],
            $this->methodNames(new ReflectionClass(ObserverReplayAuditHook::class)),
        );
        self::assertSame(
            [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertConnection',
                'failed',
                'progressed',
                'started',
                'succeeded',
            ],
            $this->methodNames(new ReflectionClass(ObserverReplayAuditWriter::class)),
        );
        self::assertTrue(new ReflectionClass(ObserverReplayAuditHook::class)->isFinal());
        self::assertTrue(new ReflectionClass(ObserverReplayAuditWriter::class)->isFinal());

        foreach ([
            [ObserverReplayAuditHook::class,   'assertEntry'],
            [ObserverReplayAuditHook::class,   'started'],
            [ObserverReplayAuditHook::class,   'progressed'],
            [ObserverReplayAuditHook::class,   'finished'],
            [ObserverReplayAuditHook::class,   'failed'],
            [ObserverReplayAuditWriter::class, 'assertConnection'],
            [ObserverReplayAuditWriter::class, 'started'],
            [ObserverReplayAuditWriter::class, 'succeeded'],
            [ObserverReplayAuditWriter::class, 'failed'],
            [ObserverReplayAuditWriter::class, 'progressed'],
        ] as [$type, $name]) {
            $method = new ReflectionMethod($type, $name);
            $this->assertSensitive($method->getParameters(), $type . '::' . $name);
        }
    }

    public function testWriterConstructorAndStoreHookAreTrailingOptionalAndUnactivated(): void
    {
        $writerConstructor = new ReflectionClass(ObserverReplayAuditWriter::class)->getConstructor();
        self::assertNotNull($writerConstructor);
        self::assertSame(
            [
                'opaqueIds',
                'selector',
                'delivery',
                'clock',
                'actor',
                'correlation',
                'buildId',
            ],
            array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                $writerConstructor->getParameters(),
            ),
        );
        self::assertSame(
            [
                'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
                'Psr\\Clock\\ClockInterface',
                'BlackOps\\Core\\ActorContext',
                'BlackOps\\Core\\Audit\\AuditRecordCorrelation',
                'string',
            ],
            array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getType()?->getName() ?? '',
                $writerConstructor->getParameters(),
            ),
        );
        self::assertFalse($writerConstructor->getParameters()[6]->isDefaultValueAvailable());

        $storeConstructor = new ReflectionClass(PostgreSqlObserverReplayStore::class)->getConstructor();
        self::assertNotNull($storeConstructor);
        $parameters = $storeConstructor->getParameters();
        self::assertSame('hook', $parameters[count($parameters) - 1]->getName());
        self::assertSame(
            PostgreSqlObserverReplayPhaseHook::class,
            $parameters[count($parameters) - 1]->getType()?->getName(),
        );
        self::assertTrue($parameters[count($parameters) - 1]->allowsNull());
        self::assertTrue($parameters[count($parameters) - 1]->isDefaultValueAvailable());
        self::assertNull($parameters[count($parameters) - 1]->getDefaultValue());
        self::assertCount(1, $parameters[count($parameters) - 1]->getAttributes(\SensitiveParameter::class));
        $advanceParameters = new ReflectionMethod(PostgreSqlObserverReplayStore::class, 'advance')->getParameters();
        self::assertSame(
            ['checkpoint', 'cursor', 'delivered', 'auditId', 'now'],
            array_map(static fn(ReflectionParameter $parameter): string => $parameter->getName(), $advanceParameters),
        );
        self::assertSame(
            ['string', 'string', 'int', 'string', 'DateTimeImmutable'],
            array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getType()?->getName() ?? '',
                $advanceParameters,
            ),
        );
        self::assertTrue($advanceParameters[1]->allowsNull());
        self::assertTrue($advanceParameters[4]->allowsNull());
        self::assertTrue($advanceParameters[4]->isDefaultValueAvailable());
        self::assertNull($advanceParameters[4]->getDefaultValue());
        $selectedParameters = new ReflectionMethod(
            PostgreSqlObserverReplayStore::class,
            'advanceSelected',
        )->getParameters();
        self::assertSame(
            ['checkpoint', 'cursor', 'auditId', 'source', 'now'],
            array_map(static fn(ReflectionParameter $parameter): string => $parameter->getName(), $selectedParameters),
        );
        self::assertSame(JournalRecord::class, $selectedParameters[3]->getType()?->getName());
        self::assertFalse($selectedParameters[3]->allowsNull());
        self::assertTrue($selectedParameters[1]->allowsNull());
        self::assertTrue($selectedParameters[4]->allowsNull());
        self::assertTrue($selectedParameters[4]->isDefaultValueAvailable());
        self::assertNull($selectedParameters[4]->getDefaultValue());
        foreach (['begin', 'advance', 'advanceSelected', 'finishInvocation', 'fail'] as $methodName) {
            $method = new ReflectionMethod(PostgreSqlObserverReplayStore::class, $methodName);
            $this->assertSensitive($method->getParameters(), PostgreSqlObserverReplayStore::class . '::' . $methodName);
        }
        foreach (['replay', 'resume', 'deliver', 'recordFailure', 'finalize', 'release'] as $methodName) {
            $method = new ReflectionMethod(ObserverReplayRuntime::class, $methodName);
            $this->assertSensitive($method->getParameters(), ObserverReplayRuntime::class . '::' . $methodName);
        }

        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $source = file_get_contents($file->getPathname());
                self::assertIsString($source);
                $sources[$file->getPathname()] = $source;
            }
        }
        foreach ($sources as $path => $source) {
            if (str_ends_with($path, '/ObserverReplayAuditHook.php')) {
                continue;
            }
            self::assertStringNotContainsString('new ObserverReplayAuditHook(', $source, $path);
            self::assertStringNotContainsString('new ObserverReplayAuditWriter(', $source, $path);
        }
    }

    public function testCorrectedHelpersHaveOnlyTheirIssuedStaticSurfaces(): void
    {
        $expected = [
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditFailure' => ['__construct', 'fresh', 'remap'],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard' => [
                '__construct',
                'auditId',
                'checkpoint',
                'entry',
                'root',
                'writer',
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage' => [
                '__construct',
                'validate',
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding' => [
                '__construct',
                'capture',
                'reference',
                'values',
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission' => [
                '__construct',
                'write',
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy' => [
                '__construct',
                'assertAuditId',
                'assertRecordId',
                'assertSubject',
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterCatalog' => [
                '__construct',
                'catalog',
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterDefinition' => [
                '__construct',
                'definition',
                'matches',
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord' => [
                '__construct',
                'draft',
                'occurredAt',
                'opaqueId',
                'selector',
                'tenantReference',
            ],
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayAdvanceRoot' => [
                '__construct',
                'run',
            ],
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBeginRoot' => [
                '__construct',
                'run',
            ],
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBeginTransaction' => [
                '__construct',
                'run',
            ],
        ];
        foreach ($expected as $type => $methods) {
            $reflection = new ReflectionClass($type);
            self::assertTrue($reflection->isFinal(), $type);
            self::assertSame($methods, $this->methodNames($reflection), $type);
            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor, $type);
            self::assertTrue($constructor->isPrivate(), $type);
        }
        $surfaces = [
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditFailure' => [
                'fresh' => [
                    'return' => [
                        'type' => 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                        'nullable' => false,
                    ],
                    'parameters' => [[
                        'name' => 'failure',
                        'type' => 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                        'nullable' => false,
                        'sensitive' => false,
                    ]],
                ],
                'remap' => [
                    'return' => [
                        'type' => 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                        'nullable' => false,
                    ],
                    'parameters' => [[
                        'name' => 'failure',
                        'type' => 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                        'nullable' => false,
                        'sensitive' => true,
                    ]],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard' => [
                'writer' => [
                    'return' => [
                        'type' => 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriter',
                        'nullable' => false,
                    ],
                    'parameters' => [[
                        'name' => 'snapshot',
                        'type' => 'SensitiveParameterValue',
                        'nullable' => false,
                        'sensitive' => true,
                    ]],
                ],
                'entry' => [
                    'return' => [
                        'type' => 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriter',
                        'nullable' => false,
                    ],
                    'parameters' => [
                        [
                            'name' => 'snapshot',
                            'type' => 'SensitiveParameterValue',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                        [
                            'name' => 'connection',
                            'type' => 'Doctrine\\DBAL\\Connection',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                    ],
                ],
                'root' => [
                    'return' => [
                        'type' => 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriter',
                        'nullable' => false,
                    ],
                    'parameters' => [
                        [
                            'name' => 'snapshot',
                            'type' => 'SensitiveParameterValue',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                        [
                            'name' => 'connection',
                            'type' => 'Doctrine\\DBAL\\Connection',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                    ],
                ],
                'checkpoint' => [
                    'return' => ['type' => 'void', 'nullable' => false],
                    'parameters' => [[
                        'name' => 'checkpoint',
                        'type' => 'string',
                        'nullable' => false,
                        'sensitive' => true,
                    ]],
                ],
                'auditId' => [
                    'return' => ['type' => 'void', 'nullable' => false],
                    'parameters' => [[
                        'name' => 'auditId',
                        'type' => 'string',
                        'nullable' => false,
                        'sensitive' => true,
                    ]],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage' => [
                'validate' => [
                    'return' => ['type' => 'array', 'nullable' => false],
                    'parameters' => [
                        ['name' => 'cursor', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                        [
                            'name' => 'source',
                            'type' => 'BlackOps\\Journal\\JournalRecord',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                    ],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding' => [
                'capture' => [
                    'return' => ['type' => 'SensitiveParameterValue', 'nullable' => false],
                    'parameters' => [['name' => 'input', 'type' => 'array', 'nullable' => false, 'sensitive' => true]],
                ],
                'values' => [
                    'return' => ['type' => 'array', 'nullable' => false],
                    'parameters' => [[
                        'name' => 'snapshot',
                        'type' => 'SensitiveParameterValue',
                        'nullable' => false,
                        'sensitive' => true,
                    ]],
                ],
                'reference' => [
                    'return' => ['type' => 'BlackOps\\Core\\Audit\\AuditTypedReference', 'nullable' => true],
                    'parameters' => [[
                        'name' => 'reference',
                        'type' => 'BlackOps\\Core\\ActorRef',
                        'nullable' => true,
                        'sensitive' => true,
                    ]],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission' => [
                'write' => [
                    'return' => ['type' => 'void', 'nullable' => false],
                    'parameters' => [
                        [
                            'name' => 'snapshot',
                            'type' => 'SensitiveParameterValue',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                        ['name' => 'eventType', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                        ['name' => 'subject', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                        ['name' => 'auditId', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                        [
                            'name' => 'tenant',
                            'type' => 'BlackOps\\Core\\TenantRef',
                            'nullable' => true,
                            'sensitive' => true,
                        ],
                    ],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy' => [
                'assertSubject' => [
                    'return' => ['type' => 'void', 'nullable' => false],
                    'parameters' => [
                        ['name' => 'eventType', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                        ['name' => 'subject', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                    ],
                ],
                'assertAuditId' => [
                    'return' => ['type' => 'void', 'nullable' => false],
                    'parameters' => [[
                        'name' => 'auditId',
                        'type' => 'string',
                        'nullable' => false,
                        'sensitive' => true,
                    ]],
                ],
                'assertRecordId' => [
                    'return' => ['type' => 'void', 'nullable' => false],
                    'parameters' => [[
                        'name' => 'recordId',
                        'type' => 'string',
                        'nullable' => false,
                        'sensitive' => true,
                    ]],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterCatalog' => [
                'catalog' => [
                    'return' => ['type' => 'BlackOps\\Core\\Audit\\AuditEventCatalog', 'nullable' => false],
                    'parameters' => [],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterDefinition' => [
                'definition' => [
                    'return' => ['type' => 'BlackOps\\Core\\Audit\\AuditEventDefinition', 'nullable' => false],
                    'parameters' => [
                        [
                            'name' => 'catalog',
                            'type' => 'BlackOps\\Core\\Audit\\AuditEventCatalog',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                        ['name' => 'eventType', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                    ],
                ],
                'matches' => [
                    'return' => ['type' => 'bool', 'nullable' => false],
                    'parameters' => [
                        [
                            'name' => 'definition',
                            'type' => 'BlackOps\\Core\\Audit\\AuditEventDefinition',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                        ['name' => 'expected', 'type' => 'array', 'nullable' => false, 'sensitive' => true],
                        ['name' => 'eventType', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                    ],
                ],
            ],
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord' => [
                'occurredAt' => [
                    'return' => ['type' => 'string', 'nullable' => false],
                    'parameters' => [['name' => 'values', 'type' => 'array', 'nullable' => false, 'sensitive' => true]],
                ],
                'opaqueId' => [
                    'return' => ['type' => 'string', 'nullable' => false],
                    'parameters' => [
                        ['name' => 'values', 'type' => 'array', 'nullable' => false, 'sensitive' => true],
                        [
                            'name' => 'purpose',
                            'type' => 'BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose',
                            'nullable' => false,
                            'sensitive' => true,
                        ],
                        ['name' => 'context', 'type' => 'string', 'nullable' => false, 'sensitive' => true],
                    ],
                ],
                'selector' => [
                    'return' => [
                        'type' => 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
                        'nullable' => false,
                    ],
                    'parameters' => [['name' => 'values', 'type' => 'array', 'nullable' => false, 'sensitive' => true]],
                ],
                'tenantReference' => [
                    'return' => ['type' => 'BlackOps\\Core\\Audit\\AuditTypedReference', 'nullable' => true],
                    'parameters' => [[
                        'name' => 'tenant',
                        'type' => 'BlackOps\\Core\\TenantRef',
                        'nullable' => true,
                        'sensitive' => true,
                    ]],
                ],
                'draft' => [
                    'return' => ['type' => 'BlackOps\\Core\\Audit\\AuditRecordDraft', 'nullable' => false],
                    'parameters' => [['name' => 'input', 'type' => 'array', 'nullable' => false, 'sensitive' => true]],
                ],
            ],
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayAdvanceRoot' => [
                'run' => [
                    'return' => ['type' => 'void', 'nullable' => false],
                    'parameters' => [['name' => 'input', 'type' => 'array', 'nullable' => false, 'sensitive' => true]],
                ],
            ],
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBeginRoot' => [
                'run' => [
                    'return' => [
                        'type' => 'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBinding',
                        'nullable' => false,
                    ],
                    'parameters' => [['name' => 'input', 'type' => 'array', 'nullable' => false, 'sensitive' => true]],
                ],
            ],
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBeginTransaction' => [
                'run' => [
                    'return' => [
                        'type' => 'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBinding',
                        'nullable' => false,
                    ],
                    'parameters' => [['name' => 'input', 'type' => 'array', 'nullable' => false, 'sensitive' => true]],
                ],
            ],
        ];
        foreach ($surfaces as $type => $methods) {
            $reflection = new ReflectionClass($type);
            self::assertSame([], $reflection->getProperties(), $type . ' must remain stateless');
            foreach ($methods as $methodName => $surface) {
                $method = $reflection->getMethod($methodName);
                self::assertTrue($method->isStatic(), $type . '::' . $methodName);
                $return = $method->getReturnType();
                self::assertNotNull($return, $type . '::' . $methodName . ' return');
                self::assertSame(
                    $surface['return']['type'],
                    $return->getName(),
                    $type . '::' . $methodName . ' return',
                );
                self::assertSame(
                    $surface['return']['nullable'],
                    $return->allowsNull(),
                    $type . '::' . $methodName . ' nullable',
                );
                $parameters = $method->getParameters();
                self::assertCount(count($surface['parameters']), $parameters, $type . '::' . $methodName);
                foreach ($surface['parameters'] as $index => $expectedParameter) {
                    $parameter = $parameters[$index];
                    self::assertSame($expectedParameter['name'], $parameter->getName(), $type . '::' . $methodName);
                    self::assertSame(
                        $expectedParameter['type'],
                        $parameter->getType()?->getName(),
                        $type . '::' . $methodName,
                    );
                    self::assertSame(
                        $expectedParameter['nullable'],
                        $parameter->allowsNull(),
                        $type . '::' . $methodName,
                    );
                    self::assertSame(
                        $expectedParameter['sensitive'] ? 1 : 0,
                        count($parameter->getAttributes(\SensitiveParameter::class)),
                        $type . '::' . $methodName,
                    );
                }
            }
        }
    }

    public function testNativeRootsForwardTheRawRecordAndPlaceEventsBeforeTheirCommit(): void
    {
        $store = file_get_contents(__DIR__ . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayStore.php');
        $runtime = file_get_contents(__DIR__ . '/../../src/Internal/Replay/ObserverReplayRuntime.php');
        $beginRoot = file_get_contents(__DIR__
        . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayBeginRoot.php');
        $beginTransaction = file_get_contents(__DIR__
        . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayBeginTransaction.php');
        $advanceRoot = file_get_contents(__DIR__
        . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayAdvanceRoot.php');
        self::assertIsString($store);
        self::assertIsString($runtime);
        self::assertIsString($beginRoot);
        self::assertIsString($beginTransaction);
        self::assertIsString($advanceRoot);

        self::assertStringContainsString('PostgreSqlObserverReplayBeginRoot::run(', $store);
        self::assertStringContainsString('PostgreSqlObserverReplayAdvanceRoot::run(', $store);
        self::assertStringContainsString("'source' => null", $store);
        self::assertStringContainsString("'source' => \$source", $store);
        $entry = strpos($beginRoot, '$input[\'hook\']?->assertEntry(');
        $advisoryLock = strpos($beginRoot, '$connection->fetchOne(');
        $begin = strpos($beginRoot, '$connection->beginTransaction();');
        $beginTransactionCall = strpos($beginRoot, 'PostgreSqlObserverReplayBeginTransaction::run(');
        self::assertNotFalse($entry);
        self::assertNotFalse($advisoryLock);
        self::assertNotFalse($begin);
        self::assertNotFalse($beginTransactionCall);
        self::assertTrue($entry < $advisoryLock, 'entry admission must precede native advisory lock work');
        self::assertTrue($advisoryLock < $begin, 'advisory ownership must precede native begin');
        self::assertTrue($begin < $beginTransactionCall, 'begin helper must run inside the native root');
        $started = strpos($beginTransaction, '$input[\'hook\']?->started(');
        $beginCommit = strpos($beginTransaction, '$input[\'connection\']->commit();');
        self::assertNotFalse($started);
        self::assertNotFalse($beginCommit);
        self::assertTrue($started < $beginCommit, 'started must be emitted before native commit');
        self::assertStringContainsString('Replay audit progress requires a selected record.', $advanceRoot);
        $advanceEntry = strpos($advanceRoot, '$hook?->assertEntry(');
        $sourceReject = strpos($advanceRoot, 'Replay audit progress requires a selected record.');
        $advanceClock = strpos($advanceRoot, '$now = $input[\'now\']');
        $progressed = strpos($advanceRoot, '$hook?->progressed(');
        $advanceCommit = strpos($advanceRoot, '$connection->commit();');
        self::assertNotFalse($advanceEntry);
        self::assertNotFalse($sourceReject);
        self::assertNotFalse($advanceClock);
        self::assertNotFalse($progressed);
        self::assertNotFalse($advanceCommit);
        self::assertTrue($advanceEntry < $sourceReject, 'entry admission must precede configured source rejection');
        self::assertTrue($sourceReject < $advanceClock, 'source rejection must precede clock work');
        self::assertTrue($progressed < $advanceCommit, 'progress must be emitted before native commit');
        foreach (['started', 'progressed', 'finished', 'failed'] as $event) {
            $eventSource = match ($event) {
                'started' => $beginTransaction,
                'progressed' => $advanceRoot,
                default => $store,
            };
            $hookNeedle = match ($event) {
                'started' => '$input[\'hook\']?->started(',
                'progressed' => '$hook?->progressed(',
                default => '$this->hook?' . '->' . $event . '(',
            };
            $hook = strpos($eventSource, $hookNeedle);
            self::assertNotFalse($hook, $event);
            $commit = strpos($eventSource, '->commit();', $hook);
            self::assertNotFalse($commit, $event);
            self::assertTrue($hook < $commit, $event . ' must be emitted before its native commit');
        }
        self::assertStringContainsString('$this->store->advanceSelected(', $runtime);
        self::assertStringNotContainsString('$this->store->advance(', $runtime);
        self::assertStringContainsString('$binding->auditId', $runtime);
        self::assertStringContainsString('$request->now', $runtime);
        self::assertStringContainsString('$record,', $runtime);
        self::assertStringContainsString('$this->store->cursorFor($request->selector, $record)', $runtime);
        self::assertStringContainsString('$this->codec->decode($encoded)', $store);
        self::assertStringContainsString('Canonical journal tenant subject is inconsistent.', $store);
        self::assertStringContainsString('Canonical journal clear metadata is inconsistent.', $store);
        self::assertStringNotContainsString('new \\BlackOps\\Journal\\JournalRecord(', $runtime);
        self::assertStringContainsString('$this->store->select(', substr($runtime, strpos(
            $runtime,
            'private function deliver',
        )));
        $dryRunOffset = strpos($runtime, 'public function dryRun');
        $releaseOffset = strpos($runtime, 'private function release');
        self::assertNotFalse($dryRunOffset);
        self::assertNotFalse($releaseOffset);
        self::assertStringNotContainsString('->hook', substr($runtime, $dryRunOffset));
        self::assertStringNotContainsString('->hook', substr($runtime, $releaseOffset));
    }

    public function testC3AndSourceBoundaryStayNarrowAndAvoidPartitionOrAmbientProvenance(): void
    {
        $writer = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Replay/ObserverReplayAuditWriter.php');
        $record = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Replay/ObserverReplayAuditWriterRecord.php');
        $emission = file_get_contents(__DIR__
        . '/../../src/Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php');
        $lineage = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Replay/ObserverReplayAuditHookLineage.php');
        $hook = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Replay/ObserverReplayAuditHook.php');
        self::assertIsString($writer);
        self::assertIsString($record);
        self::assertIsString($emission);
        self::assertIsString($lineage);
        self::assertIsString($hook);
        self::assertStringContainsString('AuditSourceRuntime::Replay', $record);
        self::assertStringContainsString("'blackops.replay.observer'", $record);
        self::assertStringContainsString('deliverInCurrentTransaction($plan, $request)', $emission);
        self::assertStringNotContainsString('AuditPartitionIdDeriver', $writer . $record . $emission);
        self::assertStringNotContainsString('AuditPartition', $writer . $record . $emission);
        self::assertStringNotContainsString('currentTenant', $writer);
        self::assertStringNotContainsString('getTenant', $writer);
        self::assertStringNotContainsString('executeStatement(', $hook);
        self::assertStringNotContainsString('->now(', $hook);
        self::assertStringNotContainsString('random_bytes(', $hook);
        self::assertStringContainsString('new AuditTypedReference($type, $id)', $lineage);
        self::assertStringContainsString('JournalRecordId::fromString($recordIdValue)->toString()', $lineage);
        self::assertStringContainsString('$sequence . \'|\' . $recordIdValue', $lineage);
    }

    public function testResolvedAstGuardsRawLineageAndSoleNativeAndC3Roots(): void
    {
        $runtimeSource = file_get_contents(__DIR__ . '/../../src/Internal/Replay/ObserverReplayRuntime.php');
        $storeSource = file_get_contents(__DIR__ . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayStore.php');
        $advanceSource = file_get_contents(__DIR__
        . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayAdvanceRoot.php');
        $beginSource = file_get_contents(__DIR__
        . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayBeginRoot.php');
        $beginTransactionSource = file_get_contents(__DIR__
        . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayBeginTransaction.php');
        $emissionSource = file_get_contents(__DIR__
        . '/../../src/Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php');
        foreach ([
            $runtimeSource,
            $storeSource,
            $advanceSource,
            $beginSource,
            $beginTransactionSource,
            $emissionSource,
        ] as $source) {
            self::assertIsString($source);
        }
        self::assertTrue($this->replayForwardingOracle($runtimeSource, $storeSource));

        $runtimeDeliver = $this->astMethod(
            $this->astClass($this->parseSource($runtimeSource), 'ObserverReplayRuntime'),
            'deliver',
        );
        $advanceCalls = $this->methodCalls($runtimeDeliver, 'advanceSelected');
        self::assertCount(1, $advanceCalls);
        $advanceCall = $advanceCalls[0];
        self::assertTrue($this->propertyFetch($advanceCall->var, 'this', 'store'));
        self::assertTrue($this->propertyFetch($advanceCall->args[0]->value, 'request', 'checkpoint'));
        self::assertTrue($this->variable($advanceCall->args[1]->value, 'cursor'));
        self::assertTrue($this->propertyFetch($advanceCall->args[2]->value, 'binding', 'auditId'));
        self::assertTrue($this->variable($advanceCall->args[3]->value, 'record'));
        self::assertTrue($this->propertyFetch($advanceCall->args[4]->value, 'request', 'now'));
        $runtimeSelectCalls = $this->methodCalls($runtimeDeliver, 'select');
        self::assertCount(1, $runtimeSelectCalls);
        self::assertTrue($this->propertyFetch($runtimeSelectCalls[0]->var, 'this', 'store'));
        self::assertTrue($this->propertyFetch($runtimeSelectCalls[0]->args[0]->value, 'request', 'selector'));
        $runtimeLoops = array_values(array_filter(
            new NodeFinder()->findInstanceOf($runtimeDeliver, Foreach_::class),
            fn(Foreach_ $loop): bool => $this->variable($loop->valueVar, 'record'),
        ));
        self::assertCount(1, $runtimeLoops);
        $runtimeLoop = $runtimeLoops[0];
        self::assertTrue($this->variable($runtimeLoop->valueVar, 'record'));
        self::assertInstanceOf(ArrayDimFetch::class, $runtimeLoop->expr);
        self::assertTrue($this->variable($runtimeLoop->expr->var, 'batch'));
        self::assertInstanceOf(String_::class, $runtimeLoop->expr->dim);
        self::assertSame('records', $runtimeLoop->expr->dim->value);
        self::assertCount(1, $this->methodCalls($runtimeDeliver, 'cursorFor'));
        $cursorCall = $this->methodCalls($runtimeDeliver, 'cursorFor')[0];
        self::assertTrue($this->propertyFetch($cursorCall->args[0]->value, 'request', 'selector'));
        self::assertTrue($this->variable($cursorCall->args[1]->value, 'record'));
        self::assertSame(
            [],
            array_filter(
                new NodeFinder()->findInstanceOf($runtimeDeliver, Assign::class),
                static fn(Assign $assign): bool => $assign->var instanceof Variable && $assign->var->name === 'record',
            ),
        );
        foreach (new NodeFinder()->findInstanceOf($runtimeDeliver, New_::class) as $new) {
            self::assertFalse(
                $new->class instanceof Name && $this->resolvedName($new->class) === JournalRecord::class,
                'deliver must not construct a replacement JournalRecord',
            );
        }
        self::assertCount(0, $this->methodCalls($runtimeDeliver, 'advance'));

        $storeNodes = $this->parseSource($storeSource);
        $storeClass = $this->astClass($storeNodes, 'PostgreSqlObserverReplayStore');
        $selectedRun = $this->staticRun(
            $this->astMethod($storeClass, 'advanceSelected'),
            PostgreSqlObserverReplayAdvanceRoot::class,
        );
        $selectedInput = $this->arrayItems($selectedRun->args[0]->value);
        self::assertInstanceOf(LNumber::class, $selectedInput['delivered'] ?? null);
        self::assertSame(1, $selectedInput['delivered']->value);
        self::assertTrue($this->variable($selectedInput['source'] ?? null, 'source'));
        $legacyRun = $this->staticRun(
            $this->astMethod($storeClass, 'advance'),
            PostgreSqlObserverReplayAdvanceRoot::class,
        );
        $legacyInput = $this->arrayItems($legacyRun->args[0]->value);
        self::assertInstanceOf(ConstFetch::class, $legacyInput['source'] ?? null);
        self::assertSame('null', strtolower($legacyInput['source']->name->toString()));

        $selectMethod = $this->astMethod($storeClass, 'select');
        $iterateCalls = $this->methodCalls($selectMethod, 'iterateAssociative');
        self::assertCount(1, $iterateCalls);
        self::assertTrue($this->propertyFetch($iterateCalls[0]->var, 'this', 'connection'));
        $decodeCalls = $this->methodCalls($selectMethod, 'decode');
        self::assertCount(1, $decodeCalls);
        self::assertTrue($this->propertyFetch($decodeCalls[0]->var, 'this', 'codec'));
        self::assertTrue($this->variable($decodeCalls[0]->args[0]->value, 'encoded'));
        self::assertStringContainsString('$records[] = $record;', $storeSource);
        $selectLoops = new NodeFinder()->findInstanceOf($selectMethod, Foreach_::class);
        self::assertCount(1, $selectLoops);
        $selectLoop = $selectLoops[0];
        self::assertTrue($this->variable($selectLoop->valueVar, 'row'));
        self::assertInstanceOf(MethodCall::class, $selectLoop->expr);
        self::assertSame('iterateAssociative', $selectLoop->expr->name->toString());
        self::assertTrue($this->propertyFetch($selectLoop->expr->var, 'this', 'connection'));
        $cursorMethod = $this->astMethod($storeClass, 'cursorFor');
        self::assertNotEmpty(array_filter(
            new NodeFinder()->findInstanceOf($cursorMethod, PropertyFetch::class),
            fn(PropertyFetch $fetch): bool => $this->propertyFetch($fetch, 'record', 'sequence'),
        ));
        self::assertNotEmpty(array_filter(
            new NodeFinder()->findInstanceOf($cursorMethod, PropertyFetch::class),
            fn(PropertyFetch $fetch): bool => $this->propertyFetch($fetch, 'record', 'occurredAt'),
        ));
        self::assertNotEmpty(array_filter(
            new NodeFinder()->findInstanceOf($cursorMethod, PropertyFetch::class),
            fn(PropertyFetch $fetch): bool => $this->propertyFetch($fetch, 'record', 'recordId'),
        ));
        self::assertNotEmpty(array_filter(
            new NodeFinder()->findInstanceOf($cursorMethod, PropertyFetch::class),
            fn(PropertyFetch $fetch): bool => $this->propertyFetch($fetch, 'selector', 'kind'),
        ));

        $advanceClass = $this->astClass($this->parseSource($advanceSource), 'PostgreSqlObserverReplayAdvanceRoot');
        self::assertCount(1, $this->methodCalls($this->astMethod($advanceClass, 'run'), 'beginTransaction'));
        self::assertCount(1, $this->methodCalls($this->astMethod($advanceClass, 'run'), 'commit'));
        $beginClass = $this->astClass($this->parseSource($beginSource), 'PostgreSqlObserverReplayBeginRoot');
        self::assertCount(1, $this->methodCalls($this->astMethod($beginClass, 'run'), 'beginTransaction'));
        self::assertCount(1, $this->staticCalls(
            $this->astMethod($beginClass, 'run'),
            PostgreSqlObserverReplayBeginTransaction::class,
            'run',
        ));
        $beginTransactionClass = $this->astClass(
            $this->parseSource($beginTransactionSource),
            'PostgreSqlObserverReplayBeginTransaction',
        );
        self::assertCount(1, $this->methodCalls($this->astMethod($beginTransactionClass, 'run'), 'commit'));

        $advanceMethod = $this->astMethod($advanceClass, 'run');
        $advanceBegin = $this->methodCalls($advanceMethod, 'beginTransaction')[0] ?? null;
        $advanceSql = $this->methodCalls($advanceMethod, 'executeStatement');
        $advanceProgress = $this->methodCalls($advanceMethod, 'progressed')[0] ?? null;
        $advanceCommit = $this->methodCalls($advanceMethod, 'commit')[0] ?? null;
        self::assertInstanceOf(MethodCall::class, $advanceBegin);
        self::assertCount(2, $advanceSql);
        self::assertInstanceOf(NullsafeMethodCall::class, $advanceProgress);
        self::assertInstanceOf(MethodCall::class, $advanceCommit);
        self::assertTrue($advanceBegin->getStartFilePos() < $advanceSql[0]->getStartFilePos());
        self::assertTrue($advanceSql[1]->getStartFilePos() < $advanceProgress->getStartFilePos());
        self::assertTrue($advanceProgress->getStartFilePos() < $advanceCommit->getStartFilePos());
        self::assertCount(0, $this->methodCalls($advanceMethod, 'fail'));
        $progressMutation = str_replace(
            '$hook?->progressed(',
            '$hook?->progressedMoved(',
            $advanceSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $mutatedAdvanceMethod = $this->astMethod(
            $this->astClass($this->parseSource($progressMutation), 'PostgreSqlObserverReplayAdvanceRoot'),
            'run',
        );
        self::assertCount(0, $this->methodCalls($mutatedAdvanceMethod, 'progressed'));

        $emissionClass = $this->astClass($this->parseSource($emissionSource), 'ObserverReplayAuditWriterEmission');
        $emissionMethod = $this->astMethod($emissionClass, 'write');
        $deliveryCalls = $this->methodCalls($emissionMethod, 'deliverInCurrentTransaction');
        self::assertCount(1, $deliveryCalls);
        self::assertTrue($this->variable($deliveryCalls[0]->var, 'delivery'));
        foreach (['append', 'commit', 'partition'] as $forbidden) {
            self::assertCount(0, $this->methodCalls($emissionMethod, $forbidden), $forbidden);
        }

        $lineageMutation = str_replace('$record,', '$observed,', $runtimeSource, $replacements);
        self::assertSame(1, $replacements);
        $mutatedDeliver = $this->astMethod(
            $this->astClass($this->parseSource($lineageMutation), 'ObserverReplayRuntime'),
            'deliver',
        );
        $mutatedCall = $this->methodCalls($mutatedDeliver, 'advanceSelected')[0] ?? null;
        self::assertInstanceOf(MethodCall::class, $mutatedCall);
        self::assertFalse($this->variable($mutatedCall->args[3]->value, 'record'));
        self::assertFalse($this->replayForwardingOracle($lineageMutation, $storeSource));

        $projectorMutation = str_replace(
            '$this->projector->project($record)',
            '$this->projector->project($previousRecord)',
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($projectorMutation, $storeSource));

        $runtimeLoopMutation = str_replace(
            '$batch[\'records\'] as $record',
            '$targets as $record',
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $mutatedRuntimeLoops = array_values(array_filter(
            new NodeFinder()->findInstanceOf(
                $this->astMethod(
                    $this->astClass($this->parseSource($runtimeLoopMutation), 'ObserverReplayRuntime'),
                    'deliver',
                ),
                Foreach_::class,
            ),
            fn(Foreach_ $loop): bool => $this->variable($loop->valueVar, 'record'),
        ));
        self::assertCount(1, $mutatedRuntimeLoops);
        self::assertFalse($mutatedRuntimeLoops[0]->expr instanceof ArrayDimFetch);
        self::assertFalse($this->replayForwardingOracle($runtimeLoopMutation, $storeSource));

        $batchReassignment = str_replace(
            "            try {\n                \$observed = \$this->projector->project(\$record);",
            "            \$batch = \$otherBatch;\n            try {\n                \$observed = \$this->projector->project(\$record);",
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($batchReassignment, $storeSource));
        $batchRecordReassignment = str_replace(
            "            try {\n                \$observed = \$this->projector->project(\$record);",
            "            \$batch['records'] = \$batch['records'];\n            try {\n                \$observed = \$this->projector->project(\$record);",
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($batchRecordReassignment, $storeSource));

        $cursorMutation = str_replace('$request->selector, $record', '$cursor, $record', $runtimeSource, $replacements);
        self::assertSame(1, $replacements);
        $mutatedCursor =
            $this->methodCalls(
                $this->astMethod(
                    $this->astClass($this->parseSource($cursorMutation), 'ObserverReplayRuntime'),
                    'deliver',
                ),
                'cursorFor',
            )[0] ?? null;
        self::assertInstanceOf(MethodCall::class, $mutatedCursor);
        self::assertFalse($this->propertyFetch($mutatedCursor->args[0]->value, 'request', 'selector'));
        self::assertFalse($this->replayForwardingOracle($cursorMutation, $storeSource));

        $cursorLine = '                $cursor = $this->store->cursorFor($request->selector, $record);';
        $advanceLine = '                $this->store->advanceSelected($request->checkpoint, $cursor, $binding->auditId, $record, $request->now);';
        $cursorOverwriteMutation = str_replace(
            $cursorLine,
            $cursorLine . "\n                \$cursor = \$previousCursor;",
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($cursorOverwriteMutation, $storeSource));
        $cursorOrderMutation = str_replace(
            $cursorLine . "\n" . $advanceLine,
            $advanceLine . "\n" . $cursorLine,
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($cursorOrderMutation, $storeSource));

        $producerMutation = str_replace('advanceSelected(', 'advance(', $runtimeSource, $replacements);
        self::assertSame(1, $replacements);
        $producerMethod = $this->astMethod(
            $this->astClass($this->parseSource($producerMutation), 'ObserverReplayRuntime'),
            'deliver',
        );
        self::assertCount(0, $this->methodCalls($producerMethod, 'advanceSelected'));
        self::assertFalse($this->replayForwardingOracle($producerMutation, $storeSource));
        $failAfterAdvance = str_replace(
            '$this->store->advanceSelected($request->checkpoint, $cursor, $binding->auditId, $record, $request->now);',
            '$this->store->advanceSelected($request->checkpoint, $cursor, $binding->auditId, $record, $request->now);'
            . "\n                "
            . '$this->store->fail($request->checkpoint, new \\RuntimeException(\'mutation\'), $request->now, $binding->auditId);',
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($failAfterAdvance, $storeSource));

        $positiveAlias =
            str_replace('advanceSelected(', 'ADVANCESELECTED(', $runtimeSource, $replacements)
            . "\n/* \$this->store->advance(new JournalRecord()); */\n";
        self::assertSame(1, $replacements);
        $positiveMethod = $this->astMethod(
            $this->astClass($this->parseSource($positiveAlias), 'ObserverReplayRuntime'),
            'deliver',
        );
        $positiveCall = $this->methodCalls($positiveMethod, 'advanceSelected')[0] ?? null;
        self::assertInstanceOf(MethodCall::class, $positiveCall);
        self::assertTrue($this->variable($positiveCall->args[3]->value, 'record'));
        self::assertTrue($this->replayForwardingOracle($positiveAlias, $storeSource));

        $positiveNamed = str_replace(
            '$this->store->advanceSelected($request->checkpoint, $cursor, $binding->auditId, $record, $request->now);',
            '$this->store->advanceSelected($request->checkpoint, $cursor, $binding->auditId, $record, now: $request->now);',
            $runtimeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $namedMethod = $this->astMethod(
            $this->astClass($this->parseSource($positiveNamed), 'ObserverReplayRuntime'),
            'deliver',
        );
        $namedCall = $this->methodCalls($namedMethod, 'advanceSelected')[0] ?? null;
        self::assertInstanceOf(MethodCall::class, $namedCall);
        self::assertTrue($this->propertyFetch($namedCall->args[4]->value, 'request', 'now'));
        self::assertInstanceOf(Identifier::class, $namedCall->args[4]->name);
        self::assertSame('now', $namedCall->args[4]->name->toString());
        self::assertTrue($this->replayForwardingOracle($runtimeSource, $storeSource));
        self::assertTrue($this->replayForwardingOracle($positiveNamed, $storeSource));

        $selectedIterableMutation = str_replace(
            '$this->connection->iterateAssociative(',
            '$this->connection->iterateNamed(',
            $storeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $mutatedSelect = $this->astMethod(
            $this->astClass($this->parseSource($selectedIterableMutation), 'PostgreSqlObserverReplayStore'),
            'select',
        );
        self::assertCount(0, $this->methodCalls($mutatedSelect, 'iterateAssociative'));
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $selectedIterableMutation));
        $deliveredMutation = str_replace("'delivered' => 1", "'delivered' => 2", $storeSource, $replacements);
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $deliveredMutation));

        $decodeMutation = str_replace(
            '$this->codec->decode($encoded)',
            '$this->codec->decode($other)',
            $storeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $mutatedDecode =
            $this->methodCalls(
                $this->astMethod(
                    $this->astClass($this->parseSource($decodeMutation), 'PostgreSqlObserverReplayStore'),
                    'select',
                ),
                'decode',
            )[0] ?? null;
        self::assertInstanceOf(MethodCall::class, $mutatedDecode);
        self::assertFalse($this->variable($mutatedDecode->args[0]->value, 'encoded'));
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $decodeMutation));

        $tenantIdentityMutation = str_replace(
            '($row[\'tenant_type\'] ?? null) !== $tenant?->type()',
            'false',
            $storeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $tenantIdentityMutation));
        $recordIdentityMutation = str_replace(
            '(string) $row[\'record_id\'] !== $record->recordId->toString()',
            'false',
            $storeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $recordIdentityMutation));

        $tenantBypassMutation = str_replace(<<<'PHP'
                            if (
                                ($row['tenant_type'] ?? null) !== $tenant?->type()
                                || ($row['tenant_id'] ?? null) !== $tenant?->id()
                            ) {
            PHP, <<<'PHP'
                            if (false && (
                                ($row['tenant_type'] ?? null) !== $tenant?->type()
                                || ($row['tenant_id'] ?? null) !== $tenant?->id()
                            )) {
            PHP, $storeSource, $replacements);
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $tenantBypassMutation));
        $recordBypassMutation = str_replace(<<<'PHP'
                            if (
                                (string) $row['record_id'] !== $record->recordId->toString()
                                || (string) $row['operation_id'] !== $record->operation->id->toString()
                                || (string) $row['operation_type'] !== $record->operation->type
                                || (int) $row['schema_version'] !== $record->schemaVersion
                                || (int) $row['operation_schema_version'] !== $record->operation->schemaVersion
                                || ($row['origin_actor_type'] ?? null) !== $record->operation->actorContext?->origin()?->type()
                                || ($row['origin_actor_id'] ?? null) !== $record->operation->actorContext?->origin()?->id()
                            ) {
            PHP, <<<'PHP'
                            if (false && (
                                (string) $row['record_id'] !== $record->recordId->toString()
                                || (string) $row['operation_id'] !== $record->operation->id->toString()
                                || (string) $row['operation_type'] !== $record->operation->type
                                || (int) $row['schema_version'] !== $record->schemaVersion
                                || (int) $row['operation_schema_version'] !== $record->operation->schemaVersion
                                || ($row['origin_actor_type'] ?? null) !== $record->operation->actorContext?->origin()?->type()
                                || ($row['origin_actor_id'] ?? null) !== $record->operation->actorContext?->origin()?->id()
                            )) {
            PHP, $storeSource, $replacements);
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $recordBypassMutation));

        $cursorSelectorMutation = str_replace('$selector->kind', '$other->kind', $storeSource, $replacements);
        self::assertSame(1, $replacements);
        $mutatedCursorMethod = $this->astMethod(
            $this->astClass($this->parseSource($cursorSelectorMutation), 'PostgreSqlObserverReplayStore'),
            'cursorFor',
        );
        self::assertSame(
            [],
            array_filter(
                new NodeFinder()->findInstanceOf($mutatedCursorMethod, PropertyFetch::class),
                fn(PropertyFetch $fetch): bool => $this->propertyFetch($fetch, 'selector', 'kind'),
            ),
        );
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $cursorSelectorMutation));

        $selectedLoopMutation = str_replace(
            'foreach ($this->connection->iterateAssociative($query[\'sql\'], $params) as $row) {',
            'foreach ($params as $row) {',
            $storeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $mutatedSelectLoop = new NodeFinder()->findInstanceOf(
            $this->astMethod(
                $this->astClass($this->parseSource($selectedLoopMutation), 'PostgreSqlObserverReplayStore'),
                'select',
            ),
            Foreach_::class,
        );
        self::assertCount(1, $mutatedSelectLoop);
        self::assertFalse($mutatedSelectLoop[0]->expr instanceof MethodCall);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $selectedLoopMutation));

        $advanceGuardMutation = str_replace(
            'if ($affected !== 1)',
            'if ($affected !== 2)',
            $advanceSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, $advanceGuardMutation));
        $phaseHookMutation = str_replace(
            '$hook?->progressed(',
            '$hook?->progressedMoved(',
            $advanceSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, $phaseHookMutation));
        $progressBlock =
            "            if (\$source !== null) {\n"
            . "                \$hook?->progressed(\$connection, \$checkpoint, \$auditId, \$cursor ?? '', \$source);\n"
            . '            }';
        $advanceWithoutProgress = str_replace($progressBlock, '', $advanceSource, $replacements);
        self::assertSame(1, $replacements);
        $progressBeforeSql = str_replace(
            '            $affected = $connection->executeStatement(',
            $progressBlock . "\n            \$affected = \$connection->executeStatement(",
            $advanceWithoutProgress,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, $progressBeforeSql));
        $progressAfterCommit = str_replace(
            '            $connection->commit();',
            '            $connection->commit();' . "\n" . $progressBlock,
            $advanceWithoutProgress,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, $progressAfterCommit));
        $progressSkipMutation = str_replace(
            'if ($source !== null)',
            'if (false && $source !== null)',
            $advanceSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, $progressSkipMutation));
        $finishHookMutation = str_replace(
            '$this->hook?->finished($this->connection, $checkpoint, $auditId, $state);',
            <<<'PHP'
                if (false && $this->hook !== null) {
                                $this->hook?->finished($this->connection, $checkpoint, $auditId, $state);
                            }
                PHP,
            $storeSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->parseSource($finishHookMutation));
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $finishHookMutation));
        $failureHookMutation = str_replace('$this->hook?->failed($this->connection, $checkpoint, $auditId);', <<<'PHP'
            if (false && $this->hook !== null) {
                            $this->hook?->failed($this->connection, $checkpoint, $auditId);
                        }
            PHP, $storeSource, $replacements);
        self::assertSame(1, $replacements);
        self::assertNotEmpty($this->parseSource($failureHookMutation));
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $failureHookMutation));
        $startedAfterCommit = str_replace(
            "        \$input['hook']?->started(\$input['connection'], \$input['request']->checkpoint, \$auditId);\n        \$input['connection']->commit();",
            "        \$input['connection']->commit();\n        \$input['hook']?->started(\$input['connection'], \$input['request']->checkpoint, \$auditId);",
            $beginTransactionSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, null, $startedAfterCommit));
        $beginHookSkip = str_replace(
            "        \$input['hook']?->assertEntry(\$connection, \$request->checkpoint);",
            "        if (false && \$input['hook'] !== null) {\n"
            . "            \$input['hook']?->assertEntry(\$connection, \$request->checkpoint);\n"
            . '        }',
            $beginSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, null, null, $beginHookSkip));
        $connectionNeedle = "'connection' => \$this->connection,";
        $connectionOffset = strrpos($storeSource, $connectionNeedle);
        self::assertNotFalse($connectionOffset);
        $connectionMutation = substr_replace(
            $storeSource,
            "'connection' => \$otherConnection,",
            $connectionOffset,
            strlen($connectionNeedle),
        );
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $connectionMutation));
        $actorMutation = str_replace(
            "\$input['request'],\n            \$input['selectorHash'],",
            "\$input['request']->actor,\n            \$input['selectorHash'],",
            $beginTransactionSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->replayForwardingOracle($runtimeSource, $storeSource, null, $actorMutation));

        $graph = [
            PostgreSqlObserverReplayPhaseHook::class => 'Transport/PostgreSql/PostgreSqlObserverReplayPhaseHook.php',
            ObserverReplayAuditHook::class => 'Internal/Audit/Replay/ObserverReplayAuditHook.php',
            ObserverReplayAuditWriter::class => 'Internal/Audit/Replay/ObserverReplayAuditWriter.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditFailure' => 'Internal/Audit/Replay/ObserverReplayAuditFailure.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard' => 'Internal/Audit/Replay/ObserverReplayAuditHookGuard.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage' => 'Internal/Audit/Replay/ObserverReplayAuditHookLineage.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding' => 'Internal/Audit/Replay/ObserverReplayAuditWriterBinding.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission' => 'Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy' => 'Internal/Audit/Replay/ObserverReplayAuditWriterPolicy.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterCatalog' => 'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterDefinition' => 'Internal/Audit/Replay/ObserverReplayAuditWriterDefinition.php',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord' => 'Internal/Audit/Replay/ObserverReplayAuditWriterRecord.php',
            PostgreSqlObserverReplayAdvanceRoot::class => 'Transport/PostgreSql/PostgreSqlObserverReplayAdvanceRoot.php',
            PostgreSqlObserverReplayBeginRoot::class => 'Transport/PostgreSql/PostgreSqlObserverReplayBeginRoot.php',
            PostgreSqlObserverReplayBeginTransaction::class => 'Transport/PostgreSql/PostgreSqlObserverReplayBeginTransaction.php',
            PostgreSqlObserverReplayStore::class => 'Transport/PostgreSql/PostgreSqlObserverReplayStore.php',
            ObserverReplayRuntime::class => 'Internal/Replay/ObserverReplayRuntime.php',
        ];
        self::assertSame($graph, $this->resolvedReplayDeclarations($graph));
        self::assertSame(
            [
                ObserverReplayAuditHook::class => 'src/Internal/Audit/Replay/ObserverReplayAuditHook.php',
            ],
            $this->resolvedReplayPhaseHookImplementers(),
        );
        $extraPhaseHook = [
            'src/Internal/Replay/SyntheticReplayPhaseHook.php' => <<<'PHP'
                <?php
                namespace BlackOpsInternalReplay;
                final class SyntheticReplayPhaseHook implements \BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayPhaseHook {}
                PHP,
        ];
        self::assertNotSame(
            [
                ObserverReplayAuditHook::class => 'src/Internal/Audit/Replay/ObserverReplayAuditHook.php',
            ],
            $this->resolvedReplayPhaseHookImplementers($extraPhaseHook),
        );
        $writerSource = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Replay/ObserverReplayAuditWriter.php');
        self::assertIsString($writerSource);
        $mutatedWriter = str_replace(
            'final class ObserverReplayAuditWriter',
            'final class ObserverReplayAuditWriterMoved',
            $writerSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $mutatedDeclarations = $this->resolvedReplayDeclarations($graph, [
            'Internal/Audit/Replay/ObserverReplayAuditWriter.php' => $mutatedWriter,
        ]);
        self::assertArrayNotHasKey(ObserverReplayAuditWriter::class, $mutatedDeclarations);
        self::assertArrayHasKey(
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterMoved',
            $mutatedDeclarations,
        );
    }

    public function testInstalledReplayCallGraphHasOneProducerAndNoUnboundActivation(): void
    {
        $failure = ObserverReplayAuditFailure::class;
        $guard = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard';
        $lineage = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage';
        $binding = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding';
        $emission = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission';
        $policy = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy';
        $catalog = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterCatalog';
        $definition = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterDefinition';
        $record = 'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord';
        $expected = [
            $failure . '::remap' => [$failure . '::fresh' => 2],
            ObserverReplayAuditHook::class . '::assertEntry' => [
                $guard . '::checkpoint' => 1,
                $guard . '::entry' => 1,
                $failure . '::fresh' => 1,
                $failure . '::remap' => 1,
            ],
            ObserverReplayAuditHook::class . '::started' => [
                $guard . '::auditId' => 1,
                $guard . '::checkpoint' => 1,
                $guard . '::root' => 1,
                $failure . '::fresh' => 1,
                $failure . '::remap' => 1,
            ],
            ObserverReplayAuditHook::class . '::progressed' => [
                $guard . '::auditId' => 1,
                $guard . '::checkpoint' => 1,
                $guard . '::root' => 1,
                $lineage . '::validate' => 1,
                $failure . '::fresh' => 1,
                $failure . '::remap' => 1,
            ],
            ObserverReplayAuditHook::class . '::finished' => [
                $guard . '::auditId' => 1,
                $guard . '::checkpoint' => 1,
                $guard . '::root' => 1,
                $failure . '::fresh' => 1,
                $failure . '::remap' => 1,
            ],
            ObserverReplayAuditHook::class . '::failed' => [
                $guard . '::auditId' => 1,
                $guard . '::checkpoint' => 1,
                $guard . '::root' => 1,
                $failure . '::fresh' => 1,
                $failure . '::remap' => 1,
            ],
            ObserverReplayAuditHook::class . '::__clone' => [$failure . '::fresh' => 1],
            ObserverReplayAuditHook::class . '::__unserialize' => [$failure . '::fresh' => 1],
            $guard . '::writer' => [$failure . '::fresh' => 2],
            $guard . '::entry' => [
                $guard . '::writer' => 1,
                $failure . '::fresh' => 1,
                $failure . '::remap' => 1,
            ],
            $guard . '::root' => [
                $guard . '::writer' => 1,
                $failure . '::fresh' => 1,
                $failure . '::remap' => 1,
            ],
            $guard . '::checkpoint' => [$policy . '::assertSubject' => 1],
            $guard . '::auditId' => [$policy . '::assertAuditId' => 1],
            $lineage . '::validate' => [$failure . '::fresh' => 1, $failure . '::remap' => 1],
            ObserverReplayAuditWriter::class . '::__clone' => [$failure . '::fresh' => 1],
            ObserverReplayAuditWriter::class . '::__unserialize' => [$failure . '::fresh' => 1],
            ObserverReplayAuditWriter::class . '::__construct' => [$binding . '::capture' => 1],
            ObserverReplayAuditWriter::class . '::assertConnection' => [
                $binding . '::values' => 1,
                $failure . '::fresh' => 2,
                $failure . '::remap' => 1,
            ],
            ObserverReplayAuditWriter::class . '::started' => [$emission . '::write' => 1],
            ObserverReplayAuditWriter::class . '::succeeded' => [$emission . '::write' => 1],
            ObserverReplayAuditWriter::class . '::failed' => [$emission . '::write' => 1],
            ObserverReplayAuditWriter::class . '::progressed' => [$emission . '::write' => 1],
            $binding . '::capture' => [$binding . '::reference' => 2, $failure . '::fresh' => 1],
            $binding . '::values' => [$failure . '::fresh' => 3],
            $catalog . '::catalog' => [$failure . '::fresh' => 1],
            $definition . '::definition' => [
                $definition . '::matches' => 1,
                $failure . '::fresh' => 2,
                $failure . '::remap' => 1,
            ],
            $emission . '::write' => [
                $binding . '::values' => 1,
                $catalog . '::catalog' => 1,
                $definition . '::definition' => 1,
                $policy . '::assertAuditId' => 1,
                $policy . '::assertSubject' => 1,
                $record . '::draft' => 1,
                $record . '::occurredAt' => 1,
                $record . '::opaqueId' => 2,
                $record . '::selector' => 1,
                $record . '::tenantReference' => 1,
                $failure . '::fresh' => 2,
                $failure . '::remap' => 1,
            ],
            $policy . '::assertSubject' => [$policy . '::assertRecordId' => 1],
            $record . '::occurredAt' => [$failure . '::fresh' => 2],
            $record . '::opaqueId' => [$failure . '::fresh' => 2],
            $record . '::selector' => [$failure . '::fresh' => 1],
            $record . '::tenantReference' => [$failure . '::fresh' => 1],
            $record . '::draft' => [$failure . '::fresh' => 1],
            PostgreSqlObserverReplayBeginRoot::class . '::run' => [
                PostgreSqlObserverReplayBeginTransaction::class . '::run' => 1,
            ],
            PostgreSqlObserverReplayStore::class . '::begin' => [
                PostgreSqlObserverReplayBeginRoot::class . '::run' => 1,
            ],
            PostgreSqlObserverReplayStore::class . '::advance' => [
                PostgreSqlObserverReplayAdvanceRoot::class . '::run' => 1,
            ],
            PostgreSqlObserverReplayStore::class . '::advanceSelected' => [
                PostgreSqlObserverReplayAdvanceRoot::class . '::run' => 1,
            ],
        ];
        foreach ($expected as &$operations) {
            ksort($operations);
        }
        unset($operations);
        ksort($expected);
        self::assertSame($expected, $this->resolvedReplayStaticCallInventory());
        self::assertSame([ObserverReplayRuntime::class . '::deliver' => 1], $this->resolvedReplayMethodCallInventory());
        self::assertSame([], $this->resolvedReplayConstructionInventory());

        $synthetic = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Replay;
            use BlackOps\Internal\Audit\Replay\ObserverReplayAuditHookGuard as Guard;
            use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
            final class SyntheticReplayCaller
            {
                public static function run(PostgreSqlObserverReplayStore $store): void
                {
                    Guard::checkpoint('synthetic');
                    \BlackOps\Internal\Audit\Replay\ObserverReplayAuditHookGuard::checkpoint('synthetic');
                    $store->advanceSelected('synthetic', null, '0123456789abcdef0123456789abcdef', new \BlackOps\Journal\JournalRecord());
                    new \BlackOps\Internal\Audit\Replay\ObserverReplayAuditHook(new \BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriter());
                }
            }
            function syntheticReplayFunction(): void
            {
                \BlackOps\Internal\Audit\Replay\ObserverReplayAuditHookGuard::checkpoint('synthetic');
            }
            \BlackOps\Internal\Audit\Replay\ObserverReplayAuditHookGuard::checkpoint('synthetic-file');
            $syntheticStore->advanceSelected('synthetic-file');
            PHP;
        $sources = ['src/Internal/Replay/SyntheticReplayCaller.php' => $synthetic];
        $staticMutation = $this->resolvedReplayStaticCallInventory($sources);
        self::assertSame(
            2,
            $staticMutation['BlackOps\\Internal\\Replay\\SyntheticReplayCaller::run']['BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard::checkpoint']
            ?? 0,
        );
        self::assertSame(
            1,
            $staticMutation['src/Internal/Replay/SyntheticReplayCaller.php::syntheticReplayFunction']['BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard::checkpoint']
            ?? 0,
        );
        self::assertSame(
            1,
            $staticMutation['src/Internal/Replay/SyntheticReplayCaller.php::file']['BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard::checkpoint']
            ?? 0,
        );
        $methodMutation = $this->resolvedReplayMethodCallInventory($sources);
        self::assertSame(1, $methodMutation['BlackOps\\Internal\\Replay\\SyntheticReplayCaller::run'] ?? 0);
        self::assertSame(1, $methodMutation['src/Internal/Replay/SyntheticReplayCaller.php::file'] ?? 0);
        $constructionMutation = $this->resolvedReplayConstructionInventory($sources);
        self::assertSame(
            [
                'src/Internal/Replay/SyntheticReplayCaller.php' => [
                    'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHook' => 1,
                    'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriter' => 1,
                ],
            ],
            $constructionMutation,
        );

        $owned = $this->resolvedReplayOwnedOperationInventory();
        self::assertSame(
            [
                'new' => [
                    'BlackOps\\Core\\Audit\\AuditRecordAction' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordActor' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding::capture' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordDraft' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordEvent' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordEvidence' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordOutcome' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordPolicy' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordResource' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordSource' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditTypedReference' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage::validate' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding::reference' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::tenantReference' => 1,
                    ],
                    'BlackOps\\Core\\TenantRef' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage::validate' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::tenantReference' => 1,
                    ],
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission::write' => 1,
                    ],
                    'DateTimeZone' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::occurredAt' => 1,
                    ],
                ],
                'receiver' => [
                    'deliverInCurrentTransaction' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission::write' => 1,
                    ],
                    'forDraft' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission::write' => 1,
                    ],
                    'usesConnection' => [
                        ObserverReplayAuditWriter::class . '::assertConnection' => 1,
                    ],
                ],
                'static' => [
                    'BlackOps\\Core\\Audit\\AuditRecordCorrelation::fromArray' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding::capture' => 1,
                    ],
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertSafeString' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding::capture' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding::values' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy::assertAuditId' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy::assertRecordId' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy::assertSubject' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::draft' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::occurredAt' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord::opaqueId' => 1,
                    ],
                    'BlackOps\\Core\\Identifier\\JournalRecordId::fromString' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage::validate' => 1,
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy::assertRecordId' => 1,
                    ],
                    'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::catalog' => [
                        'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterCatalog::catalog' => 1,
                    ],
                ],
            ],
            $owned,
        );
        $emissionSource = file_get_contents(__DIR__
        . '/../../src/Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php');
        self::assertIsString($emissionSource);
        $movedEmission = str_replace(
            'public static function write(',
            'public static function moved(',
            $emissionSource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $movedOwned = $this->resolvedReplayOwnedOperationInventory([
            'src/Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php' => $movedEmission,
        ]);
        self::assertArrayNotHasKey(
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission::write',
            $movedOwned['receiver']['forDraft'] ?? [],
        );
        self::assertArrayHasKey(
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission::moved',
            $movedOwned['receiver']['forDraft'] ?? [],
        );
        self::assertArrayNotHasKey(
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission::write',
            $movedOwned['receiver']['deliverInCurrentTransaction'] ?? [],
        );
        self::assertArrayHasKey(
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission::moved',
            $movedOwned['receiver']['deliverInCurrentTransaction'] ?? [],
        );

        $policyRelative = 'src/Internal/Audit/Replay/ObserverReplayAuditWriterPolicy.php';
        $policySource = file_get_contents(__DIR__ . '/../../' . $policyRelative);
        self::assertIsString($policySource);
        $movedPolicy = str_replace(
            'if (JournalRecordId::fromString($recordId)->toString() !== $recordId) {',
            'if ($recordId !== $recordId) {',
            $policySource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $movedPolicy = str_replace(
            "        AuditValidationException::assertSafeString(\$subject, 1, 128);\n",
            "        AuditValidationException::assertSafeString(\$subject, 1, 128);\n"
            . "        JournalRecordId::fromString(\$subject)->toString();\n",
            $movedPolicy,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $movedPolicyOwned = $this->resolvedReplayOwnedOperationInventory([$policyRelative => $movedPolicy]);
        $recordIdTarget = 'BlackOps\\Core\\Identifier\\JournalRecordId::fromString';
        self::assertArrayNotHasKey(
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy::assertRecordId',
            $movedPolicyOwned['static'][$recordIdTarget] ?? [],
        );
        self::assertSame(
            1,
            $movedPolicyOwned['static'][$recordIdTarget]['BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy::assertSubject']
            ?? 0,
        );

        $aliasPolicy = str_replace(
            'use BlackOps\\Core\\Identifier\\JournalRecordId;',
            'use BlackOps\\Core\\Identifier\\JournalRecordId as RecordIdentifier;',
            $policySource,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $aliasPolicy = str_replace(
            'JournalRecordId::fromString($recordId)',
            'RecordIdentifier::FROMSTRING($recordId)',
            $aliasPolicy,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $aliasOwned = $this->resolvedReplayOwnedOperationInventory([$policyRelative => $aliasPolicy]);
        self::assertSame(
            1,
            $aliasOwned['static'][$recordIdTarget]['BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy::assertRecordId']
            ?? 0,
        );

        $ownerSynthetic = $policySource . <<<'PHP'

            final class SyntheticReplayPolicyOwner
            {
                public static function moved(): void
                {
                    \BlackOps\Core\Audit\AuditValidationException::assertSafeString('synthetic');
                    $delivery->DELIVERINCURRENTTRANSACTION($plan, $request);
                }
            }

            function syntheticReplayPolicy(): void
            {
                \BlackOps\Core\Audit\AuditRecordCorrelation::fromArray([]);
            }

            \BlackOps\Core\Audit\AuditValidationException::assertSafeString('synthetic-file');
            PHP;
        $ownerSyntheticOwned = $this->resolvedReplayOwnedOperationInventory([$policyRelative => $ownerSynthetic]);
        $safeStringTarget = 'BlackOps\\Core\\Audit\\AuditValidationException::assertSafeString';
        self::assertSame(
            1,
            $ownerSyntheticOwned['static'][$safeStringTarget]['BlackOps\\Internal\\Audit\\Replay\\SyntheticReplayPolicyOwner::moved']
            ?? 0,
        );
        self::assertSame(1, $ownerSyntheticOwned['static'][$safeStringTarget][$policyRelative . '::file'] ?? 0);
        self::assertSame(
            1,
            $ownerSyntheticOwned['static']['BlackOps\\Core\\Audit\\AuditRecordCorrelation::fromArray'][$policyRelative
                . '::syntheticReplayPolicy'] ?? 0,
        );
        self::assertSame(
            1,
            $ownerSyntheticOwned['receiver']['deliverInCurrentTransaction']['BlackOps\\Internal\\Audit\\Replay\\SyntheticReplayPolicyOwner::moved']
            ?? 0,
        );
    }

    /** @param array<string,string> $graph @param array<string,string> $overrides @return array<string,string> */
    private function resolvedReplayDeclarations(array $graph, array $overrides = []): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $declarations = [];
        foreach ($graph as $expected => $relative) {
            $source = $overrides[$relative] ?? file_get_contents($root . '/' . $relative);
            self::assertIsString($source, $relative);
            $nodes = $this->parseSource($source);
            $finder = new NodeFinder();
            $declarationsInFile = [
                ...$finder->findInstanceOf($nodes, Class_::class),
                ...$finder->findInstanceOf($nodes, Interface_::class),
            ];
            self::assertCount(1, $declarationsInFile, $relative);
            $name = $declarationsInFile[0]->namespacedName;
            self::assertInstanceOf(Name::class, $name, $relative);
            $declarations[ltrim($name->toString(), '\\')] = $relative;
        }
        return $declarations;
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function resolvedReplayPhaseHookImplementers(array $overrides = []): array
    {
        $implementers = [];
        foreach ($this->installedReplaySources($overrides) as $relative => $source) {
            foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), Class_::class) as $class) {
                $name = $class->namespacedName;
                if (!$name instanceof Name) {
                    continue;
                }
                foreach ($class->implements as $interface) {
                    if (
                        $interface instanceof Name
                        && $this->resolvedName($interface) === PostgreSqlObserverReplayPhaseHook::class
                    ) {
                        $implementers[ltrim($name->toString(), '\\')] = $relative;
                    }
                }
            }
        }
        ksort($implementers);

        return $implementers;
    }

    private function replayForwardingOracle(
        string $runtimeSource,
        string $storeSource,
        ?string $advanceSource = null,
        ?string $beginTransactionSource = null,
        ?string $beginSource = null,
    ): bool {
        try {
            $advanceSource ??= file_get_contents(__DIR__
            . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayAdvanceRoot.php');
            $beginTransactionSource ??= file_get_contents(__DIR__
            . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayBeginTransaction.php');
            $beginSource ??= file_get_contents(__DIR__
            . '/../../src/Transport/PostgreSql/PostgreSqlObserverReplayBeginRoot.php');
            if (!is_string($advanceSource) || !is_string($beginTransactionSource) || !is_string($beginSource)) {
                return false;
            }
            $runtimeDeliver = $this->astMethod(
                $this->astClass($this->parseSource($runtimeSource), 'ObserverReplayRuntime'),
                'deliver',
            );
            $selectCalls = $this->methodCalls($runtimeDeliver, 'select');
            $advanceCalls = $this->methodCalls($runtimeDeliver, 'advanceSelected');
            $cursorCalls = $this->methodCalls($runtimeDeliver, 'cursorFor');
            $projectCalls = $this->methodCalls($runtimeDeliver, 'project');
            if (
                count($selectCalls) !== 1
                || count($projectCalls) !== 1
                || count($advanceCalls) !== 1
                || count($cursorCalls) !== 1
            ) {
                return false;
            }
            $selectCall = $selectCalls[0];
            if (
                !$this->propertyFetch($selectCall->var, 'this', 'store')
                || !$this->propertyFetch($selectCall->args[0]->value, 'request', 'selector')
                || !$this->propertyFetch($projectCalls[0]->var, 'this', 'projector')
                || count($projectCalls[0]->args) !== 1
                || !$this->variable($projectCalls[0]->args[0]->value, 'record')
            ) {
                return false;
            }
            $batchAssignments = array_values(array_filter(
                new NodeFinder()->findInstanceOf($runtimeDeliver, Assign::class),
                static fn(Assign $assign): bool => (
                    $assign->var instanceof Variable
                    && $assign->var->name === 'batch'
                    && $assign->expr instanceof MethodCall
                    && $assign->expr->name instanceof Identifier
                    && strtolower($assign->expr->name->toString()) === 'select'
                ),
            ));
            if (
                count($batchAssignments) !== 1
                || $batchAssignments[0]->expr->getStartFilePos() !== $selectCall->getStartFilePos()
            ) {
                return false;
            }
            $batchVariableAssignments = array_values(array_filter(
                new NodeFinder()->findInstanceOf($runtimeDeliver, Assign::class),
                fn(Assign $assign): bool => $this->variable($assign->var, 'batch'),
            ));
            if (count($batchVariableAssignments) !== 1) {
                return false;
            }
            if (
                array_filter(
                    new NodeFinder()->findInstanceOf($runtimeDeliver, Assign::class),
                    fn(Assign $assign): bool => (
                        $assign->var instanceof ArrayDimFetch
                        && $this->variable($assign->var->var, 'batch')
                        && $assign->var->dim instanceof String_
                        && $assign->var->dim->value === 'records'
                    ),
                ) !== []
            ) {
                return false;
            }
            $loops = array_values(array_filter(
                new NodeFinder()->findInstanceOf($runtimeDeliver, Foreach_::class),
                fn(Foreach_ $loop): bool => $this->variable($loop->valueVar, 'record'),
            ));
            if (count($loops) !== 1 || !$loops[0]->expr instanceof ArrayDimFetch) {
                return false;
            }
            $loop = $loops[0];
            if (
                !$this->variable($loop->expr->var, 'batch')
                || !$loop->expr->dim instanceof String_
                || $loop->expr->dim->value !== 'records'
            ) {
                return false;
            }
            $advance = $advanceCalls[0];
            if (
                !$this->propertyFetch($advance->var, 'this', 'store')
                || count($advance->args) !== 5
                || !$this->propertyFetch($advance->args[0]->value, 'request', 'checkpoint')
                || !$this->variable($advance->args[1]->value, 'cursor')
                || !$this->propertyFetch($advance->args[2]->value, 'binding', 'auditId')
                || !$this->variable($advance->args[3]->value, 'record')
                || !$this->propertyFetch($advance->args[4]->value, 'request', 'now')
                || $this->methodCalls($runtimeDeliver, 'advance') !== []
                || $this->methodCalls($runtimeDeliver, 'fail') !== []
            ) {
                return false;
            }
            $cursor = $cursorCalls[0];
            if (
                !$this->propertyFetch($cursor->var, 'this', 'store')
                || count($cursor->args) !== 2
                || !$this->propertyFetch($cursor->args[0]->value, 'request', 'selector')
                || !$this->variable($cursor->args[1]->value, 'record')
            ) {
                return false;
            }
            $cursorAssignments = array_values(array_filter(
                new NodeFinder()->findInstanceOf($runtimeDeliver, Assign::class),
                fn(Assign $assign): bool => $this->variable($assign->var, 'cursor'),
            ));
            if (
                count($cursorAssignments) !== 1
                || !$cursorAssignments[0]->expr instanceof MethodCall
                || !$cursorAssignments[0]->expr->name instanceof Identifier
                || strtolower($cursorAssignments[0]->expr->name->toString()) !== 'cursorfor'
                || $cursorAssignments[0]->expr->getStartFilePos() !== $cursor->getStartFilePos()
                || !$this->nodeWithin($cursorAssignments[0], $loop)
                || !$this->nodeWithin($cursor, $loop)
                || !$this->nodeWithin($advance, $loop)
                || !$this->nodeWithin($projectCalls[0], $loop)
                || !(
                    $projectCalls[0]->getStartFilePos() < $cursorAssignments[0]->getStartFilePos()
                    && $cursorAssignments[0]->getStartFilePos() < $advance->getStartFilePos()
                )
            ) {
                return false;
            }
            if (
                array_filter(
                    new NodeFinder()->findInstanceOf($runtimeDeliver, Assign::class),
                    static fn(Assign $assign): bool => (
                        $assign->var instanceof Variable
                        && $assign->var->name === 'record'
                    ),
                ) !== []
            ) {
                return false;
            }
            foreach (new NodeFinder()->findInstanceOf($runtimeDeliver, New_::class) as $new) {
                if ($new->class instanceof Name && $this->resolvedName($new->class) === JournalRecord::class) {
                    return false;
                }
            }

            $storeClass = $this->astClass($this->parseSource($storeSource), 'PostgreSqlObserverReplayStore');
            $selectedRun = $this->staticRun(
                $this->astMethod($storeClass, 'advanceSelected'),
                PostgreSqlObserverReplayAdvanceRoot::class,
            );
            $selectedInput = $this->arrayItems($selectedRun->args[0]->value);
            if (
                !($selectedInput['delivered'] ?? null) instanceof LNumber
                || $selectedInput['delivered']->value !== 1
                || !$this->variable($selectedInput['source'] ?? null, 'source')
                || !$this->propertyFetch($selectedInput['connection'] ?? null, 'this', 'connection')
            ) {
                return false;
            }
            if (!$this->replayNativePhaseOracle($storeSource, $advanceSource, $beginSource, $beginTransactionSource)) {
                return false;
            }
            $selectMethod = $this->astMethod($storeClass, 'select');
            $iterateCalls = $this->methodCalls($selectMethod, 'iterateAssociative');
            $decryptCalls = $this->methodCalls($selectMethod, 'decrypt');
            $decodeCalls = $this->methodCalls($selectMethod, 'decode');
            $selectLoops = new NodeFinder()->findInstanceOf($selectMethod, Foreach_::class);
            if (
                count($iterateCalls) !== 1
                || count($decryptCalls) !== 1
                || count($decodeCalls) !== 1
                || count($selectLoops) !== 1
            ) {
                return false;
            }
            $iterate = $iterateCalls[0];
            $decrypt = $decryptCalls[0];
            $decode = $decodeCalls[0];
            $selectLoop = $selectLoops[0];
            $buildCalls = $this->staticCalls(
                $selectMethod,
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplaySelectionQuery',
                'build',
            );
            $queryAssignments = array_values(array_filter(
                new NodeFinder()->findInstanceOf($selectMethod, Assign::class),
                fn(Assign $assign): bool => (
                    $this->variable($assign->var, 'query')
                    && $assign->expr instanceof StaticCall
                    && count($buildCalls) === 1
                    && $assign->expr->getStartFilePos() === $buildCalls[0]->getStartFilePos()
                ),
            ));
            $paramsAssignments = array_values(array_filter(
                new NodeFinder()->findInstanceOf($selectMethod, Assign::class),
                fn(Assign $assign): bool => (
                    $this->variable($assign->var, 'params') && $this->arrayFetch($assign->expr, 'query', 'params')
                ),
            ));
            $contextCalls = array_values(array_filter(
                new NodeFinder()->findInstanceOf($selectMethod, New_::class),
                fn(New_ $new): bool => (
                    $new->class instanceof Name
                    && $this->resolvedName($new->class)
                    === 'BlackOps\\Internal\\StorageProtection\\StorageProtectionContext'
                ),
            ));
            if (
                !$this->propertyFetch($iterate->var, 'this', 'connection')
                || !$this->propertyFetch($decrypt->var, 'this', 'protection')
                || !$this->variable($decrypt->args[0]->value, 'encoded')
                || !$this->propertyFetch($decode->var, 'this', 'codec')
                || !$this->variable($decode->args[0]->value, 'encoded')
                || !$this->variable($selectLoop->valueVar, 'row')
                || !$selectLoop->expr instanceof MethodCall
                || $selectLoop->expr->getStartFilePos() !== $iterate->getStartFilePos()
                || count($buildCalls) !== 1
                || count($buildCalls[0]->args) !== 4
                || !$this->variable($buildCalls[0]->args[0]->value, 'selector')
                || !$this->variable($buildCalls[0]->args[1]->value, 'limit')
                || !$this->variable($buildCalls[0]->args[2]->value, 'cursor')
                || !$buildCalls[0]->args[3]->value instanceof MethodCall
                || !$this->propertyFetch($buildCalls[0]->args[3]->value->var, 'this', 'schema')
                || !$buildCalls[0]->args[3]->value->name instanceof Identifier
                || $buildCalls[0]->args[3]->value->name->toString() !== 'journalTable'
                || count($queryAssignments) !== 1
                || count($paramsAssignments) !== 1
                || count($iterate->args) !== 2
                || !$this->arrayFetch($iterate->args[0]->value, 'query', 'sql')
                || !$this->variable($iterate->args[1]->value, 'params')
                || count($decrypt->args) !== 2
                || !$decrypt->args[1]->value instanceof New_
                || count($contextCalls) !== 1
                || $decrypt->args[1]->value->getStartFilePos() !== $contextCalls[0]->getStartFilePos()
            ) {
                return false;
            }
            if ($this->normalizedNodeList($selectMethod->stmts) !== $this->normalizedPinnedStoreBody(<<<'PHP'
                    if ($limit < 1 || $limit > 1000) {
                        throw new InvalidArgumentException('Replay batch size must be between 1 and 1000.');
                    }
                    $query = PostgreSqlObserverReplaySelectionQuery::build(
                        $selector,
                        $limit,
                        $cursor,
                        $this->schema->journalTable(),
                    );
                    try {
                        $records = [];
                        /** @var array<string, mixed>|list<mixed> $params */
                        $params = $query['params'];
                        foreach ($this->connection->iterateAssociative($query['sql'], $params) as $row) {
                            $encoded = PostgreSqlBytea::string($row['encoded_record'] ?? null);
                            $tenantType = $row['tenant_type'] ?? null;
                            $tenantId = $row['tenant_id'] ?? null;
                            $tenant = null;
                            if ($tenantType !== null || $tenantId !== null) {
                                if (!is_string($tenantType) || $tenantType === '' || !is_string($tenantId) || $tenantId === '') {
                                    throw new RuntimeException('Canonical journal tenant subject is invalid.');
                                }
                                $tenant = new TenantRef($tenantType, $tenantId);
                            }
                            $encoded = $this->protection->decrypt(
                                $encoded,
                                new StorageProtectionContext(
                                    StoragePurpose::JournalRecord,
                                    (string) $row['record_id'],
                                    (string) $row['operation_id'],
                                    (string) $row['operation_type'],
                                    (int) $row['operation_schema_version'],
                                    $tenant,
                                ),
                            );
                            $record = $this->codec->decode($encoded);
                            $tenant = $record->operation->tenant;
                            if (
                                ($row['tenant_type'] ?? null) !== $tenant?->type()
                                || ($row['tenant_id'] ?? null) !== $tenant?->id()
                            ) {
                                throw new RuntimeException('Canonical journal tenant subject is inconsistent.');
                            }
                            if (
                                (string) $row['record_id'] !== $record->recordId->toString()
                                || (string) $row['operation_id'] !== $record->operation->id->toString()
                                || (string) $row['operation_type'] !== $record->operation->type
                                || (int) $row['schema_version'] !== $record->schemaVersion
                                || (int) $row['operation_schema_version'] !== $record->operation->schemaVersion
                                || ($row['origin_actor_type'] ?? null) !== $record->operation->actorContext?->origin()?->type()
                                || ($row['origin_actor_id'] ?? null) !== $record->operation->actorContext?->origin()?->id()
                            ) {
                                throw new RuntimeException('Canonical journal clear metadata is inconsistent.');
                            }
                            $records[] = $record;
                        }
                    } catch (Throwable $exception) {
                        throw new RuntimeException('Canonical journal replay selection failed.', previous: $exception);
                    }
                    $hasMore = count($records) > $limit;
                    if ($hasMore) {
                        array_pop($records);
                    }
                    return ['records' => $records, 'hasMore' => $hasMore];
                PHP)) {
                return false;
            }
            $cursorMethod = $this->astMethod($storeClass, 'cursorFor');
            $expectedTenantGuard = <<<'PHP'
                if (
                    ($row['tenant_type'] ?? null) !== $tenant?->type()
                    || ($row['tenant_id'] ?? null) !== $tenant?->id()
                ) {
                    throw new RuntimeException('Canonical journal tenant subject is inconsistent.');
                }
                PHP;
            $expectedRecordGuard = <<<'PHP'
                if (
                    (string) $row['record_id'] !== $record->recordId->toString()
                    || (string) $row['operation_id'] !== $record->operation->id->toString()
                    || (string) $row['operation_type'] !== $record->operation->type
                    || (int) $row['schema_version'] !== $record->schemaVersion
                    || (int) $row['operation_schema_version'] !== $record->operation->schemaVersion
                    || ($row['origin_actor_type'] ?? null) !== $record->operation->actorContext?->origin()?->type()
                    || ($row['origin_actor_id'] ?? null) !== $record->operation->actorContext?->origin()?->id()
                ) {
                    throw new RuntimeException('Canonical journal clear metadata is inconsistent.');
                }
                PHP;
            $expectedTenantGuard = $this->normalizedPinnedStoreBody($expectedTenantGuard);
            $expectedRecordGuard = $this->normalizedPinnedStoreBody($expectedRecordGuard);
            $tenantGuards = array_values(array_filter(
                new NodeFinder()->findInstanceOf($selectMethod, If_::class),
                fn(If_ $if): bool => $this->normalizedNode($if) === $expectedTenantGuard,
            ));
            $recordGuards = array_values(array_filter(
                new NodeFinder()->findInstanceOf($selectMethod, If_::class),
                fn(If_ $if): bool => $this->normalizedNode($if) === $expectedRecordGuard,
            ));
            if (count($tenantGuards) !== 1 || count($recordGuards) !== 1) {
                return false;
            }
            if (
                $this->normalizedNode($tenantGuards[0]) !== $expectedTenantGuard
                || $this->normalizedNode($recordGuards[0]) !== $expectedRecordGuard
                || $tenantGuards[0]->getStartFilePos() >= $recordGuards[0]->getStartFilePos()
            ) {
                return false;
            }
            $recordAppends = array_values(array_filter(
                new NodeFinder()->findInstanceOf($selectMethod, Assign::class),
                fn(Assign $assign): bool => (
                    $assign->var instanceof ArrayDimFetch
                    && $this->variable($assign->var->var, 'records')
                    && $assign->var->dim === null
                    && $this->variable($assign->expr, 'record')
                ),
            ));
            if (count($recordAppends) !== 1 || !$this->nodeWithin($recordAppends[0], $selectLoop)) {
                return false;
            }
            $expectedCursorBody = <<<'PHP'
                return match ($selector->kind) {
                    PostgreSqlObserverReplaySelector::OPERATION => $record->sequence . '|' . $record->recordId->toString(),
                    PostgreSqlObserverReplaySelector::TIME => $record->occurredAt->format('Y-m-d H:i:s.uP') . '|' . $record->recordId->toString(),
                    PostgreSqlObserverReplaySelector::RECORD => $record->recordId->toString(),
                    default => throw new InvalidArgumentException('Replay selector kind is invalid.'),
                };
                PHP;
            if (
                $this->normalizedNodeList($cursorMethod->stmts) !== $this->normalizedPinnedStoreBody(
                    $expectedCursorBody,
                )
            ) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function replayNativePhaseOracle(
        string $storeSource,
        string $advanceSource,
        string $beginSource,
        string $beginTransactionSource,
    ): bool {
        try {
            $advanceMethod = $this->astMethod(
                $this->astClass($this->parseSource($advanceSource), 'PostgreSqlObserverReplayAdvanceRoot'),
                'run',
            );
            $advanceBegin = $this->methodCalls($advanceMethod, 'beginTransaction');
            $advanceSql = $this->methodCalls($advanceMethod, 'executeStatement');
            $advanceProgress = $this->methodCalls($advanceMethod, 'progressed');
            $advanceCommit = $this->methodCalls($advanceMethod, 'commit');
            $advanceAssert = $this->methodCalls($advanceMethod, 'assertEntry');
            if (
                count($advanceBegin) !== 1
                || count($advanceSql) !== 2
                || count($advanceProgress) !== 1
                || count($advanceCommit) !== 1
                || count($advanceAssert) !== 1
                || !$this->nativeBefore(
                    $advanceBegin[0],
                    $advanceSql[0],
                    $advanceSql[1],
                    $advanceProgress[0],
                    $advanceCommit[0],
                )
                || $this->normalizedNode($advanceAssert[0]) !== $this->normalizedText(
                    '$hook?->assertEntry($connection, $checkpoint)',
                )
                || $this->enclosingIfs($advanceAssert[0], $advanceMethod) !== []
            ) {
                return false;
            }
            $advanceProgressIfs = array_values(array_filter(
                new NodeFinder()->findInstanceOf($advanceMethod, If_::class),
                fn(If_ $if): bool => $this->nodeWithin($advanceProgress[0], $if),
            ));
            if (
                count($advanceProgressIfs) !== 1
                || $this->normalizedNode($advanceProgressIfs[0]) !== $this->normalizedText(<<<'PHP'
                    if ($source !== null) {
                        $hook?->progressed($connection, $checkpoint, $auditId, $cursor ?? '', $source);
                    }
                    PHP)
            ) {
                return false;
            }
            $advanceGuards = array_map(
                fn(If_ $if): string => $this->normalizedNode($if),
                new NodeFinder()->findInstanceOf($advanceMethod, If_::class),
            );
            if (!in_array($this->normalizedPinnedStoreBody(<<<'PHP'
                if ($affected !== 1) {
                    throw new RuntimeException('Replay checkpoint ownership was lost.');
                }
                PHP), $advanceGuards, true) || !in_array($this->normalizedPinnedStoreBody(<<<'PHP'
                if ($auditAffected !== 1) {
                    throw new RuntimeException('Replay audit ownership was lost.');
                }
                PHP), $advanceGuards, true)) {
                return false;
            }

            $beginMethod = $this->astMethod(
                $this->astClass($this->parseSource($beginSource), 'PostgreSqlObserverReplayBeginRoot'),
                'run',
            );
            $beginAssert = $this->methodCalls($beginMethod, 'assertEntry');
            $beginTransactions = $this->methodCalls($beginMethod, 'beginTransaction');
            $beginCalls = $this->staticCalls($beginMethod, PostgreSqlObserverReplayBeginTransaction::class, 'run');
            if (
                count($beginAssert) !== 1
                || count($beginTransactions) !== 1
                || count($beginCalls) !== 1
                || $this->normalizedNode($beginAssert[0]) !== $this->normalizedText(
                    "\$input['hook']?->assertEntry(\$connection, \$request->checkpoint)",
                )
                || $this->enclosingIfs($beginAssert[0], $beginMethod) !== []
                || !(
                    $beginAssert[0]->getStartFilePos() < $beginTransactions[0]->getStartFilePos()
                    && $beginTransactions[0]->getStartFilePos() < $beginCalls[0]->getStartFilePos()
                )
            ) {
                return false;
            }

            $beginTransactionMethod = $this->astMethod(
                $this->astClass(
                    $this->parseSource($beginTransactionSource),
                    'PostgreSqlObserverReplayBeginTransaction',
                ),
                'run',
            );
            $insertCalls = $this->methodCalls($beginTransactionMethod, 'insert');
            $auditInserts = array_values(array_filter(
                $insertCalls,
                fn(Node $call): bool => (
                    $call instanceof MethodCall
                    && $call->var instanceof ArrayDimFetch
                    && $this->arrayFetch($call->var, 'input', 'auditWriter')
                ),
            ));
            $startedCalls = $this->methodCalls($beginTransactionMethod, 'started');
            $beginCommits = $this->methodCalls($beginTransactionMethod, 'commit');
            if (
                count($insertCalls) !== 2
                || count($auditInserts) !== 1
                || count($startedCalls) !== 1
                || count($beginCommits) !== 1
                || !$this->normalizedAuditInsert($auditInserts[0])
                || $this->normalizedNode($startedCalls[0]) !== $this->normalizedText(
                    "\$input['hook']?->started(\$input['connection'], \$input['request']->checkpoint, \$auditId)",
                )
                || $this->enclosingIfs($startedCalls[0], $beginTransactionMethod) !== []
                || !(
                    $auditInserts[0]->getStartFilePos() < $startedCalls[0]->getStartFilePos()
                    && $startedCalls[0]->getStartFilePos() < $beginCommits[0]->getStartFilePos()
                )
            ) {
                return false;
            }

            $storeClass = $this->astClass($this->parseSource($storeSource), 'PostgreSqlObserverReplayStore');
            if (
                !$this->replayStoreTerminalOracle($storeClass, 'finishInvocation', 'finished')
                || !$this->replayStoreTerminalOracle($storeClass, 'fail', 'failed')
            ) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function replayStoreTerminalOracle(Class_ $storeClass, string $methodName, string $hookName): bool
    {
        $method = $this->astMethod($storeClass, $methodName);
        $assertCalls = $this->methodCalls($method, 'assertEntry');
        $begins = $this->methodCalls($method, 'beginTransaction');
        $sql = $this->methodCalls($method, 'executeStatement');
        $hooks = $this->methodCalls($method, $hookName);
        $commits = $this->methodCalls($method, 'commit');
        $expectedHook = $hookName === 'finished'
            ? '$this->hook?->finished($this->connection, $checkpoint, $auditId, $state)'
            : '$this->hook?->failed($this->connection, $checkpoint, $auditId)';

        return (
            count($assertCalls) === 1
            && count($begins) === 1
            && count($sql) === 2
            && count($hooks) === 1
            && count($commits) === 1
            && $this->normalizedNode($assertCalls[0]) === $this->normalizedText(
                '$this->hook?->assertEntry($this->connection, $checkpoint)',
            )
            && $this->normalizedNode($hooks[0]) === $this->normalizedText($expectedHook)
            && $this->enclosingIfs($assertCalls[0], $method) === []
            && $this->enclosingIfs($hooks[0], $method) === []
            && $this->nativeBefore($begins[0], $sql[0], $sql[1], $hooks[0], $commits[0])
        );
    }

    private function normalizedAuditInsert(Node $call): bool
    {
        if (
            !$call instanceof MethodCall
            || count($call->args) !== 5
            || !$call->var instanceof ArrayDimFetch
            || !$this->arrayFetch($call->var, 'input', 'auditWriter')
            || !$this->arrayFetch($call->args[0]->value, 'input', 'request')
            || !$this->arrayFetch($call->args[1]->value, 'input', 'selectorHash')
            || !$this->arrayFetch($call->args[2]->value, 'input', 'targetHash')
            || !$this->variable($call->args[3]->value, 'auditId')
            || !$this->arrayFetch($call->args[4]->value, 'input', 'now')
        ) {
            return false;
        }

        return true;
    }

    private function nativeBefore(Node ...$nodes): bool
    {
        $positions = array_map(static fn(Node $node): int => $node->getStartFilePos(), $nodes);
        if (count($positions) < 2 || count(array_unique($positions)) !== count($positions)) {
            return false;
        }
        foreach ($positions as $index => $position) {
            if ($index > 0 && $position <= $positions[$index - 1]) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,string> $overrides @return array<string,array<string,int>> */
    private function resolvedReplayStaticCallInventory(array $overrides = []): array
    {
        $inventory = [];
        foreach ($this->installedReplaySources($overrides) as $relative => $source) {
            $nodes = $this->parseSource($source);
            foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
                $owner = $class->namespacedName;
                if (!$owner instanceof Name) {
                    continue;
                }
                $ownerName = ltrim($owner->toString(), '\\');
                foreach ($class->getMethods() as $method) {
                    $this->recordReplayStaticCalls(
                        $inventory,
                        $ownerName . '::' . $method->name->toString(),
                        $method,
                        $ownerName,
                    );
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, Function_::class) as $function) {
                if (!$function->name instanceof Identifier) {
                    continue;
                }
                $this->recordReplayStaticCalls(
                    $inventory,
                    $relative . '::' . $function->name->toString(),
                    $function,
                    null,
                );
            }
            $this->recordReplayFileLevelStaticCalls($inventory, $relative, $nodes);
        }
        ksort($inventory);
        foreach ($inventory as &$operations) {
            ksort($operations);
        }
        unset($operations);

        return $inventory;
    }

    /** @param array<string,string> $overrides @return array<string,int> */
    private function resolvedReplayMethodCallInventory(array $overrides = []): array
    {
        $inventory = [];
        foreach ($this->installedReplaySources($overrides) as $relative => $source) {
            $nodes = $this->parseSource($source);
            foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
                $owner = $class->namespacedName;
                if (!$owner instanceof Name) {
                    continue;
                }
                $ownerName = ltrim($owner->toString(), '\\');
                foreach ($class->getMethods() as $method) {
                    $this->recordReplayAdvanceSelectedCalls(
                        $inventory,
                        $ownerName . '::' . $method->name->toString(),
                        $method,
                    );
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, Function_::class) as $function) {
                if (!$function->name instanceof Identifier) {
                    continue;
                }
                $this->recordReplayAdvanceSelectedCalls(
                    $inventory,
                    $relative . '::' . $function->name->toString(),
                    $function,
                );
            }
            $this->recordReplayFileLevelAdvanceSelectedCalls($inventory, $relative, $nodes);
        }
        ksort($inventory);

        return $inventory;
    }

    /** @param array<string,array<string,int>> $inventory */
    private function recordReplayStaticCalls(array &$inventory, string $owner, Node $callable, ?string $className): void
    {
        foreach (new NodeFinder()->findInstanceOf($callable, StaticCall::class) as $call) {
            $this->recordReplayStaticCall($inventory, $owner, $call, $className);
        }
    }

    /** @param list<Node> $nodes */
    private function recordReplayFileLevelStaticCalls(array &$inventory, string $relative, array $nodes): void
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            if ($this->insideDeclaredCallable($call, $nodes)) {
                continue;
            }
            $this->recordReplayStaticCall($inventory, $relative . '::file', $call, null);
        }
    }

    /** @param array<string,array<string,int>> $inventory */
    private function recordReplayStaticCall(
        array &$inventory,
        string $owner,
        StaticCall $call,
        ?string $className,
    ): void {
        if (!$call->name instanceof Identifier || !$call->class instanceof Name) {
            return;
        }
        $targetClass = $className !== null && in_array(strtolower($call->class->toString()), ['self', 'static'], true)
            ? $className
            : $this->resolvedName($call->class);
        if (!in_array($targetClass, $this->replayHelperTypes(), true)) {
            return;
        }
        $target = $targetClass . '::' . $call->name->toString();
        $inventory[$owner][$target] = ($inventory[$owner][$target] ?? 0) + 1;
    }

    /** @param array<string,int> $inventory */
    private function recordReplayAdvanceSelectedCalls(array &$inventory, string $owner, Node $callable): void
    {
        $calls = [
            ...new NodeFinder()->findInstanceOf($callable, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($callable, NullsafeMethodCall::class),
        ];
        foreach ($calls as $call) {
            if (!$call->name instanceof Identifier || strtolower($call->name->toString()) !== 'advanceselected') {
                continue;
            }
            $inventory[$owner] = ($inventory[$owner] ?? 0) + 1;
        }
    }

    /** @param list<Node> $nodes */
    private function recordReplayFileLevelAdvanceSelectedCalls(array &$inventory, string $relative, array $nodes): void
    {
        $owner = $relative . '::file';
        foreach ([
            ...new NodeFinder()->findInstanceOf($nodes, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($nodes, NullsafeMethodCall::class),
        ] as $call) {
            if (
                $call->name instanceof Identifier
                && strtolower($call->name->toString()) === 'advanceselected'
                && !$this->insideDeclaredCallable($call, $nodes)
            ) {
                $inventory[$owner] = ($inventory[$owner] ?? 0) + 1;
            }
        }
    }

    /** @param list<Node> $nodes */
    private function insideDeclaredCallable(Node $node, array $nodes): bool
    {
        foreach ([
            ...new NodeFinder()->findInstanceOf($nodes, ClassMethod::class),
            ...new NodeFinder()->findInstanceOf($nodes, Function_::class),
        ] as $callable) {
            if ($this->nodeWithin($node, $callable)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,string> $overrides @return array<string,array<string,int>> */
    private function resolvedReplayConstructionInventory(array $overrides = []): array
    {
        $targets = [ObserverReplayAuditHook::class, ObserverReplayAuditWriter::class];
        $inventory = [];
        foreach ($this->installedReplaySources($overrides) as $relative => $source) {
            foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), New_::class) as $new) {
                if (!$new->class instanceof Name) {
                    continue;
                }
                $className = $this->resolvedName($new->class);
                if (!in_array($className, $targets, true)) {
                    continue;
                }
                $inventory[$relative][$className] = ($inventory[$relative][$className] ?? 0) + 1;
            }
        }
        ksort($inventory);
        foreach ($inventory as &$constructors) {
            ksort($constructors);
        }
        unset($constructors);

        return $inventory;
    }

    /** @param array<string,string> $overrides @return array<string,array<string,array<string,int>>> */
    private function resolvedReplayOwnedOperationInventory(array $overrides = []): array
    {
        $targets = [
            'new' => [
                'BlackOps\Core\Audit\AuditRecordActor',
                'BlackOps\Core\Audit\AuditRecordDraft',
                'BlackOps\Core\Audit\AuditRecordEvent',
                'BlackOps\Core\Audit\AuditRecordAction',
                'BlackOps\Core\Audit\AuditRecordResource',
                'BlackOps\Core\Audit\AuditRecordOutcome',
                'BlackOps\Core\Audit\AuditRecordPolicy',
                'BlackOps\Core\Audit\AuditRecordSource',
                'BlackOps\Core\Audit\AuditRecordEvidence',
                'BlackOps\Core\Audit\AuditTypedReference',
                'BlackOps\Core\TenantRef',
                'BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest',
                'DateTimeZone',
            ],
            'static' => [
                'BlackOps\Core\Audit\AuditValidationException::assertSafeString',
                'BlackOps\Core\Identifier\JournalRecordId::fromString',
                'BlackOps\Core\Audit\AuditRecordCorrelation::fromArray',
                'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::catalog',
            ],
            'receiver' => ['usesConnection', 'forDraft', 'deliverInCurrentTransaction'],
        ];
        $ownedTypes = [ObserverReplayAuditHook::class, ObserverReplayAuditWriter::class, ...$this->replayHelperTypes()];
        $sources = $this->installedReplaySources($overrides);
        $ownedFiles = [];
        $parsedSources = [];
        foreach ($sources as $relative => $source) {
            $nodes = $this->parseSource($source);
            $parsedSources[$relative] = $nodes;
            foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
                if (
                    $class->namespacedName instanceof Name
                    && in_array(ltrim($class->namespacedName->toString(), '\\'), $ownedTypes, true)
                ) {
                    $ownedFiles[$relative] = true;
                    break;
                }
            }
        }
        $inventory = [];
        foreach ($parsedSources as $relative => $nodes) {
            if (!isset($ownedFiles[$relative])) {
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
                if (!$class->namespacedName instanceof Name) {
                    continue;
                }
                $className = ltrim($class->namespacedName->toString(), '\\');
                foreach ($class->getMethods() as $method) {
                    $this->recordReplayOwnedOperations(
                        $inventory,
                        $targets,
                        $className . '::' . $method->name->toString(),
                        $method,
                        $className,
                    );
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, Function_::class) as $function) {
                if (!$function->name instanceof Identifier) {
                    continue;
                }
                $this->recordReplayOwnedOperations(
                    $inventory,
                    $targets,
                    $relative . '::' . $function->name->toString(),
                    $function,
                    null,
                );
            }
            $this->recordReplayOwnedFileOperations($inventory, $targets, $relative, $nodes);
        }
        foreach ($targets as $kind => $kindTargets) {
            foreach ($kindTargets as $target) {
                $inventory[$kind][$target] ??= [];
            }
        }
        ksort($inventory);
        foreach ($inventory as &$operations) {
            ksort($operations);
            foreach ($operations as &$owners) {
                ksort($owners);
            }
            unset($owners);
        }
        unset($operations);

        return $inventory;
    }

    /** @param array{new:list<string>,static:list<string>,receiver:list<string>} $targets @param array<string,array<string,array<string,int>>> $inventory */
    private function recordReplayOwnedOperations(
        array &$inventory,
        array $targets,
        string $owner,
        Node $callable,
        ?string $className,
    ): void {
        foreach (new NodeFinder()->findInstanceOf($callable, New_::class) as $new) {
            if (!$new->class instanceof Name) {
                continue;
            }
            $target = $this->replayOwnedClassTarget($this->resolvedName($new->class), $targets['new']);
            if ($target !== null) {
                $inventory['new'][$target][$owner] = ($inventory['new'][$target][$owner] ?? 0) + 1;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($callable, StaticCall::class) as $call) {
            if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                continue;
            }
            $classTarget = $className !== null
            && in_array(strtolower($call->class->toString()), ['self', 'static'], true)
                ? $className
                : $this->resolvedName($call->class);
            $target = $this->replayOwnedStaticTarget($classTarget, $call->name->toString(), $targets['static']);
            if ($target !== null) {
                $inventory['static'][$target][$owner] = ($inventory['static'][$target][$owner] ?? 0) + 1;
            }
        }
        foreach ([
            ...new NodeFinder()->findInstanceOf($callable, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($callable, NullsafeMethodCall::class),
        ] as $call) {
            if (!$call->name instanceof Identifier) {
                continue;
            }
            $target = $this->replayOwnedReceiverTarget($call->name->toString(), $targets['receiver']);
            if ($target !== null) {
                $inventory['receiver'][$target][$owner] = ($inventory['receiver'][$target][$owner] ?? 0) + 1;
            }
        }
    }

    /** @param array{new:list<string>,static:list<string>,receiver:list<string>} $targets @param list<Node> $nodes */
    private function recordReplayOwnedFileOperations(
        array &$inventory,
        array $targets,
        string $relative,
        array $nodes,
    ): void {
        $owner = $relative . '::file';
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if (!$this->insideDeclaredCallable($new, $nodes) && $new->class instanceof Name) {
                $target = $this->replayOwnedClassTarget($this->resolvedName($new->class), $targets['new']);
                if ($target !== null) {
                    $inventory['new'][$target][$owner] = ($inventory['new'][$target][$owner] ?? 0) + 1;
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            if (
                !$this->insideDeclaredCallable($call, $nodes)
                && $call->class instanceof Name
                && $call->name instanceof Identifier
            ) {
                $target = $this->replayOwnedStaticTarget(
                    $this->resolvedName($call->class),
                    $call->name->toString(),
                    $targets['static'],
                );
                if ($target !== null) {
                    $inventory['static'][$target][$owner] = ($inventory['static'][$target][$owner] ?? 0) + 1;
                }
            }
        }
        foreach ([
            ...new NodeFinder()->findInstanceOf($nodes, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($nodes, NullsafeMethodCall::class),
        ] as $call) {
            if (!$this->insideDeclaredCallable($call, $nodes) && $call->name instanceof Identifier) {
                $target = $this->replayOwnedReceiverTarget($call->name->toString(), $targets['receiver']);
                if ($target !== null) {
                    $inventory['receiver'][$target][$owner] = ($inventory['receiver'][$target][$owner] ?? 0) + 1;
                }
            }
        }
    }

    /** @param list<string> $targets */
    private function replayOwnedClassTarget(string $actual, array $targets): ?string
    {
        foreach ($targets as $target) {
            if (strcasecmp($actual, $target) === 0) {
                return $target;
            }
        }

        return null;
    }

    /** @param list<string> $targets */
    private function replayOwnedStaticTarget(string $class, string $method, array $targets): ?string
    {
        foreach ($targets as $target) {
            [$targetClass, $targetMethod] = explode('::', $target, 2);
            if (strcasecmp($class, $targetClass) === 0 && strcasecmp($method, $targetMethod) === 0) {
                return $target;
            }
        }

        return null;
    }

    /** @param list<string> $targets */
    private function replayOwnedReceiverTarget(string $method, array $targets): ?string
    {
        foreach ($targets as $target) {
            if (strcasecmp($method, $target) === 0) {
                return $target;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function replayHelperTypes(): array
    {
        return [
            ObserverReplayAuditFailure::class,
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookGuard',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditHookLineage',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterBinding',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterEmission',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterPolicy',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterCatalog',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterDefinition',
            'BlackOps\\Internal\\Audit\\Replay\\ObserverReplayAuditWriterRecord',
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayAdvanceRoot',
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBeginRoot',
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlObserverReplayBeginTransaction',
        ];
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function installedReplaySources(array $overrides = []): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = 'src/' . str_replace($root . '/', '', $file->getPathname());
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source, $relative);
            $sources[$relative] = $overrides[$relative] ?? $source;
        }
        foreach ($overrides as $relative => $source) {
            $sources[$relative] ??= $source;
        }
        ksort($sources);

        return $sources;
    }

    /** @return list<Node> */
    private function parseSource(string $source): array
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
    private function astClass(array $nodes, string $name): Class_
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
            if ($class->name?->toString() === $name) {
                return $class;
            }
        }
        self::fail($name . ' class is missing from AST.');
    }

    private function astMethod(Class_ $class, string $name): ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() === $name) {
                return $method;
            }
        }
        self::fail($class->name?->toString() . '::' . $name . ' method is missing from AST.');
    }

    /** @return list<MethodCall|NullsafeMethodCall> */
    private function methodCalls(ClassMethod $method, string $name): array
    {
        $finder = new NodeFinder();
        $calls = [
            ...$finder->findInstanceOf($method, MethodCall::class),
            ...$finder->findInstanceOf($method, NullsafeMethodCall::class),
        ];

        return array_values(array_filter(
            $calls,
            static fn(Node $call): bool => (
                ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                && $call->name instanceof Identifier
                && strtolower($call->name->toString()) === strtolower($name)
            ),
        ));
    }

    /** @return list<StaticCall> */
    private function staticCalls(ClassMethod $method, string $className, string $name): array
    {
        return array_values(array_filter(
            new NodeFinder()->findInstanceOf($method, StaticCall::class),
            fn(StaticCall $call): bool => (
                $call->name instanceof Identifier
                && strtolower($call->name->toString()) === strtolower($name)
                && $call->class instanceof Name
                && $this->resolvedName($call->class) === $className
            ),
        ));
    }

    private function staticRun(ClassMethod $method, string $className): StaticCall
    {
        $calls = $this->staticCalls($method, $className, 'run');
        self::assertCount(1, $calls, $className . '::run');

        return $calls[0];
    }

    /** @return array<string, Node> */
    private function arrayItems(Node $node): array
    {
        self::assertInstanceOf(Array_::class, $node);
        $items = [];
        foreach ($node->items as $item) {
            if ($item !== null && $item->key instanceof String_) {
                $items[$item->key->value] = $item->value;
            }
        }

        return $items;
    }

    private function variable(?Node $node, string $name): bool
    {
        return $node instanceof Variable && $node->name === $name;
    }

    private function propertyFetch(?Node $node, string $variable, string $property): bool
    {
        return (
            $node instanceof PropertyFetch
            && $this->variable($node->var, $variable)
            && $node->name instanceof Identifier
            && $node->name->toString() === $property
        );
    }

    private function arrayFetch(?Node $node, string $variable, string $key): bool
    {
        return (
            $node instanceof ArrayDimFetch
            && $this->variable($node->var, $variable)
            && $node->dim instanceof String_
            && $node->dim->value === $key
        );
    }

    private function nodeWithin(Node $node, Node $container): bool
    {
        $nodeStart = $node->getStartFilePos();
        $nodeEnd = $node->getEndFilePos();
        $containerStart = $container->getStartFilePos();
        $containerEnd = $container->getEndFilePos();

        return (
            $nodeStart >= 0
            && $nodeEnd >= $nodeStart
            && $containerStart >= 0
            && $containerEnd >= $containerStart
            && $nodeStart >= $containerStart
            && $nodeEnd <= $containerEnd
        );
    }

    /** @return list<If_> */
    private function enclosingIfs(Node $node, Node $scope): array
    {
        return array_values(array_filter(
            new NodeFinder()->findInstanceOf($scope, If_::class),
            fn(If_ $if): bool => $this->nodeWithin($node, $if),
        ));
    }

    private function normalizedNode(Node $node): string
    {
        return $this->normalizedText(new Standard()->prettyPrint([$node]));
    }

    /** @param list<Node> $nodes */
    private function normalizedNodeList(array $nodes): string
    {
        return $this->normalizedText(new Standard()->prettyPrint($nodes));
    }

    private function normalizedPinnedStoreBody(string $body): string
    {
        $source =
            "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "namespace BlackOps\\Transport\\PostgreSql;\n\n"
            . "use BlackOps\\Core\\TenantRef;\n"
            . "use BlackOps\\Internal\\StorageProtection\\StorageProtectionContext;\n"
            . "use BlackOps\\StorageProtection\\StoragePurpose;\n"
            . "use InvalidArgumentException;\n"
            . "use RuntimeException;\n"
            . "use Throwable;\n\n"
            . "final class PinnedObserverReplayStore\n"
            . "{\n"
            . "    private function pinned(): array\n"
            . "    {\n"
            . $body
            . "\n"
            . "    }\n"
            . "}\n";
        $method = $this->astMethod($this->astClass($this->parseSource($source), 'PinnedObserverReplayStore'), 'pinned');

        return $this->normalizedNodeList($method->stmts);
    }

    private function normalizedText(string $source): string
    {
        $normalized = preg_replace('/\s+/', '', $source);
        self::assertIsString($normalized);

        return $normalized;
    }

    private function resolvedName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return ltrim($resolved instanceof Name ? $resolved->toString() : $name->toString(), '\\');
    }

    /** @return list<string> */
    private function methodNames(ReflectionClass $reflection): array
    {
        $names = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        sort($names);

        return $names;
    }

    /** @param list<ReflectionParameter> $parameters */
    private function assertSensitive(array $parameters, string $label): void
    {
        foreach ($parameters as $parameter) {
            self::assertCount(
                1,
                $parameter->getAttributes(\SensitiveParameter::class),
                $label . ' $' . $parameter->getName(),
            );
        }
    }
}
