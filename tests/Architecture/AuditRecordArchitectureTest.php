<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Projection\ObservedAuditRecordProjector;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelope;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditLogicalRecord;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordIntegrity;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\Audit\Record\ValidatedAuditLogicalRecord;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelope;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\StorageProtection\StoragePurpose;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:no-insecure-comparison
 */
final class AuditRecordArchitectureTest extends TestCase
{
    public function testA2TypesHaveNoPublicApiMarker(): void
    {
        $paths = [
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditAttemptKind.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditSourceRuntime.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditRecord*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditProjection*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/Audit/Record/*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/Audit/Protection/*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/Audit/Projection/*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/Audit/Outbox/*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/Audit/OperationalOutbox/*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/StorageProtection/BopdBinaryEnvelope*.php') ?: []),
        ];
        foreach ($paths as $path) {
            $namespace = str_contains($path, '/src/Core/') ? 'BlackOps\\Core\\Audit\\' : 'BlackOps\\Internal\\Audit\\';
            $class = $namespace . basename($path, '.php');
            if (str_contains($path, '/src/Internal/Audit/Record/'))
                $class = 'BlackOps\\Internal\\Audit\\Record\\' . basename($path, '.php');
            if (str_contains($path, '/src/Internal/Audit/Protection/'))
                $class = 'BlackOps\\Internal\\Audit\\Protection\\' . basename($path, '.php');
            if (str_contains($path, '/src/Internal/Audit/Projection/'))
                $class = 'BlackOps\\Internal\\Audit\\Projection\\' . basename($path, '.php');
            if (str_contains($path, '/src/Internal/Audit/Outbox/'))
                $class = 'BlackOps\\Internal\\Audit\\Outbox\\' . basename($path, '.php');
            if (str_contains($path, '/src/Internal/Audit/OperationalOutbox/'))
                $class = 'BlackOps\\Internal\\Audit\\OperationalOutbox\\' . basename($path, '.php');
            if (str_contains($path, '/src/Internal/StorageProtection/'))
                $class = 'BlackOps\\Internal\\StorageProtection\\' . basename($path, '.php');
            self::assertSame([], new \ReflectionClass($class)->getAttributes(PublicApi::class), $class);
        }
    }

    public function testGenericOutboxAdapterRemainsInternalAndFinite(): void
    {
        $hook = new \ReflectionClass(\BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditHook::class);
        $writer = new \ReflectionClass(\BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditWriter::class);
        $phaseHook = new \ReflectionClass(\BlackOps\Transport\PostgreSql\PostgreSqlOutboxPhaseHook::class);
        $store = new \ReflectionClass(\BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore::class);
        foreach ([$hook, $writer, $phaseHook, $store] as $reflection) {
            self::assertSame([], $reflection->getAttributes(PublicApi::class), $reflection->getName());
        }
        self::assertTrue($hook->isFinal());
        self::assertTrue($writer->isFinal());
        self::assertTrue($phaseHook->isInterface());
        $phaseMethods = array_map(
            static fn(\ReflectionMethod $method): string => $method->getName(),
            $phaseHook->getMethods(),
        );
        sort($phaseMethods);
        self::assertSame(
            ['assertOperatorEntry', 'assertRelayEntry', 'deadLetterRetryRequested', 'deadLettered', 'retryScheduled'],
            $phaseMethods,
        );
        self::assertSame(
            [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertConnection',
                'deadLetterRetryRequested',
                'deadLettered',
                'retryScheduled',
            ],
            $this->declaredPublicMethods($writer),
        );
        $constructor = $store->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(
            \BlackOps\Transport\PostgreSql\PostgreSqlOutboxPhaseHook::class,
            $constructor->getParameters()[4]->getType()?->getName(),
        );
        self::assertTrue($constructor->getParameters()[4]->allowsNull());
        self::assertNull($constructor->getParameters()[4]->getDefaultValue());
    }

    /** @return list<string> */
    private function declaredPublicMethods(\ReflectionClass $reflection): array
    {
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName() && $method->isPublic()) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);

        return $methods;
    }

    public function testLogicalBoundaryHasInterfaceOnlyAndValidatedResultDoesNotImplementIt(): void
    {
        self::assertTrue(interface_exists(AuditLogicalRecord::class));
        self::assertFalse(is_a(ValidatedAuditLogicalRecord::class, AuditLogicalRecord::class, true));
        $implementations = [];
        foreach (glob(__DIR__ . '/../../src/Internal/Audit/Record/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            $class = 'BlackOps\\Internal\\Audit\\Record\\' . basename($path, '.php');
            if (!interface_exists($class) && is_a($class, AuditLogicalRecord::class, true))
                $implementations[] = $class;
        }
        sort($implementations);
        self::assertSame(
            [
                AuditLogicalRecordCandidate::class,
                CanonicalAuditLogicalRecord::class,
            ],
            $implementations,
        );
        $source = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Record/AuditLogicalRecord.php');
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, 'function toArray'));
    }

    public function testLogicalAndCanonicalConstructionCallsitesAreBounded(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $callsites = $this->constructionCallsitesFromSources($this->sourceFiles($sourceRoot));
        self::assertSame(
            [
                'Internal/Audit/Record/AuditRecordCodec.php' => 1,
                'Internal/Audit/Record/AuditRecordFinalizer.php' => 1,
                'Internal/Audit/Relay/AuditIngressCanonicalizer.php' => 1,
            ],
            $callsites['candidate'],
        );
        self::assertSame(
            [
                'Internal/Audit/Record/AuditRecordFinalizer.php' => 1,
                'Internal/Audit/Relay/AuditIngressCanonicalizer.php' => 1,
            ],
            $callsites['canonical'],
        );
        self::assertSame(['Internal/Audit/Record/AuditRecordCodec.php' => 2], $callsites['complete']);
        self::assertSame(
            [
                'Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1,
                'Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php' => 1,
            ],
            $callsites['stored'],
        );
        self::assertSame(
            [
                'Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php' => 1,
                'Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1,
            ],
            $callsites['ingress'],
        );

        $sources = $this->sourceFiles($sourceRoot);
        $sources['External/AuditRecordCodec.php'] = '<?php namespace BlackOps\\Internal\\Audit\\Record; new AuditLogicalRecordCandidate([]);';
        $mutated = $this->constructionCallsitesFromSources($sources);
        self::assertSame(
            [
                'External/AuditRecordCodec.php' => 1,
                'Internal/Audit/Record/AuditRecordCodec.php' => 1,
                'Internal/Audit/Record/AuditRecordFinalizer.php' => 1,
                'Internal/Audit/Relay/AuditIngressCanonicalizer.php' => 1,
            ],
            $mutated['candidate'],
            'same-basename outside sources must not overwrite an authorized owner',
        );
    }

    public function testConstructionInventoryHandlesQualifiedNamesAndRejectsDynamicForms(): void
    {
        $candidatePath = __DIR__ . '/../../src/Internal/Audit/Record/AuditRecordFinalizer.php';
        $candidateSource = file_get_contents($candidatePath);
        self::assertIsString($candidateSource);
        $qualifiedCandidate = str_replace(
            'new AuditLogicalRecordCandidate(',
            'new \\BlackOps\\Internal\\Audit\\Record\\AuditLogicalRecordCandidate(',
            $candidateSource,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        self::assertSame(1, $this->constructorCalls($qualifiedCandidate, AuditLogicalRecordCandidate::class));
        $removedCandidate = str_replace(
            'new AuditLogicalRecordCandidate(',
            'new RemovedCandidate(',
            $candidateSource,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        self::assertSame(0, $this->constructorCalls($removedCandidate, AuditLogicalRecordCandidate::class));
        $addedCandidate =
            $candidateSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditLogicalRecordCandidate([]);\n";
        self::assertSame(2, $this->constructorCalls($addedCandidate, AuditLogicalRecordCandidate::class));
        self::assertSame(1, $this->constructorCalls(
            $candidateSource . "\n// new \\BlackOps\\Internal\\Audit\\Record\\AuditLogicalRecordCandidate([]);\n",
            AuditLogicalRecordCandidate::class,
        ));
        $aliasCandidate = "<?php\nuse \\BlackOps\\Internal\\Audit\\Record\\AuditLogicalRecordCandidate as Candidate;\nnew Candidate([]);";
        self::assertSame(1, $this->constructorCalls($aliasCandidate, AuditLogicalRecordCandidate::class));
        $otherCandidate = str_replace(
            'new AuditLogicalRecordCandidate(',
            'new \\Other\\AuditLogicalRecordCandidate(',
            $candidateSource,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        self::assertSame(0, $this->constructorCalls($otherCandidate, AuditLogicalRecordCandidate::class));
        try {
            $this->constructorCalls($candidateSource . "\nnew \$factory([]);\n", AuditLogicalRecordCandidate::class);
            self::fail('dynamic constructors must be rejected');
        } catch (\LogicException $exception) {
            self::assertSame('Dynamic constructor form is unsupported.', $exception->getMessage());
        }

        $hydratorPath = __DIR__ . '/../../src/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php';
        $hydratorSource = file_get_contents($hydratorPath);
        self::assertIsString($hydratorSource);
        $qualifiedFactory = str_replace(
            'AuditStoredRecord::fromArray(',
            '\\BlackOps\\Internal\\Audit\\Protection\\AuditStoredRecord::fromArray(',
            $hydratorSource,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        self::assertSame(1, $this->staticMethodCalls($qualifiedFactory, AuditStoredRecord::class, 'fromArray'));
        $removedFactory = str_replace(
            'AuditStoredRecord::fromArray(',
            'RemovedRecord::fromArray(',
            $hydratorSource,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        self::assertSame(0, $this->staticMethodCalls($removedFactory, AuditStoredRecord::class, 'fromArray'));
        $addedFactory =
            $hydratorSource . "\n\\BlackOps\\Internal\\Audit\\Protection\\AuditStoredRecord::fromArray([]);\n";
        self::assertSame(2, $this->staticMethodCalls($addedFactory, AuditStoredRecord::class, 'fromArray'));
        $aliasFactory = "<?php\nuse \\BlackOps\\Internal\\Audit\\Protection\\AuditStoredRecord as Stored;\nStored::fromArray([]);";
        self::assertSame(1, $this->staticMethodCalls($aliasFactory, AuditStoredRecord::class, 'fromArray'));
        $otherAliasFactory = "<?php\nuse Other\\AuditStoredRecord as Stored;\nStored::fromArray([]);";
        self::assertSame(0, $this->staticMethodCalls($otherAliasFactory, AuditStoredRecord::class, 'fromArray'));
        self::assertSame(1, $this->staticMethodCalls(
            $hydratorSource . "\n// \\BlackOps\\Internal\\Audit\\Protection\\AuditStoredRecord::fromArray([]);\n",
            AuditStoredRecord::class,
            'fromArray',
        ));
        self::assertSame(1, $this->staticMethodCalls(
            $hydratorSource . "\nself::\$instance;\n",
            AuditStoredRecord::class,
            'fromArray',
        ));
        self::assertSame(1, $this->staticMethodCalls(
            $hydratorSource . "\nAuditStoredRecord::\$instance;\n",
            AuditStoredRecord::class,
            'fromArray',
        ));
        self::assertSame(1, $this->staticMethodCalls(
            $aliasFactory . "\nStored::\$instance;\n",
            AuditStoredRecord::class,
            'fromArray',
        ));
        foreach ([
            $hydratorSource . "\n" . '$factory::fromArray([]);' . "\n",
            $hydratorSource . "\nAuditStoredRecord::\$method();\n",
            $hydratorSource . "\nAuditStoredRecord::{\$method}();\n",
        ] as $dynamicSource) {
            try {
                $this->staticMethodCalls($dynamicSource, AuditStoredRecord::class, 'fromArray');
                self::fail('dynamic static calls must be rejected');
            } catch (\LogicException $exception) {
                self::assertSame('Dynamic static-call form is unsupported.', $exception->getMessage());
            }
        }
    }

    public function testB1PurposeAndPublicStorageBoundaryRemainDistinct(): void
    {
        self::assertSame(
            ['audit_record', 'audit_outbox_record'],
            array_map(
                static fn(AuditProtectionPurpose $purpose): string => $purpose->value,
                AuditProtectionPurpose::cases(),
            ),
        );
        self::assertSame(
            [
                'journal_record',
                'deferred_payload',
                'deferred_context',
                'outcome_payload',
                'outbox_payload',
                'outbox_context',
                'dead_letter_reason',
                'idempotency_response',
                'idempotency_result',
            ],
            array_map(static fn(StoragePurpose $purpose): string => $purpose->value, StoragePurpose::cases()),
        );
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $files = [
            $sourceRoot . '/Internal/StorageProtection/BopdEnvelopeCodec.php',
            $sourceRoot . '/Internal/StorageProtection/BopdBinaryEnvelope.php',
            $sourceRoot . '/Internal/StorageProtection/BopdBinaryEnvelopeCodec.php',
            ...glob($sourceRoot . '/Internal/Audit/Record/*.php'),
            ...glob($sourceRoot . '/Internal/Audit/Protection/*.php'),
        ];
        foreach ($files as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression(
                '/(?:Application|Container|PDO|Migration|Jsonl|PublicApi|StoragePurpose)/',
                $source,
                $path,
            );
        }
    }

    public function testB1ConstructionAndPublicCallableSurfacesAreClosed(): void
    {
        $constructors = [
            [AuditLogicalRecordCandidate::class, true, ['array'], [false]],
            [AuditRecordStamp::class, true, ['string', 'string'], [false, false]],
            [CanonicalAuditLogicalRecord::class, true, [ValidatedAuditLogicalRecord::class], [false]],
            [AuditRecordFinalizer::class, true, [AuditRecordValidator::class, AuditRecordCodec::class], [false, false]],
            [AuditPartitionIdDeriver::class, true, [\BlackOps\Internal\Audit\AuditJcsCodec::class], [false]],
            [AuditChainPosition::class, true, ['string', 'string', 'int', 'string'], [false, false, false, true]],
            [
                AuditRecordIntegrity::class,
                true,
                ['string', 'string', 'string', 'int', 'string', 'string', 'string'],
                [false, false, false, false, true, false, false],
            ],
            [
                CompleteAuditRecord::class,
                true,
                ['array', AuditEventDefinition::class, AuditRecordIntegrity::class, 'string'],
                [false, false, false, false],
            ],
            [
                AuditStoredRecord::class,
                false,
                [
                    'int',
                    'string',
                    'string',
                    'string',
                    AuditTypedReference::class,
                    'string',
                    'int',
                    'string',
                    'string',
                    'string',
                    'string',
                    'string',
                    'string',
                ],
                [false, false, false, false, true, false, false, true, false, false, false, false, false],
            ],
            [
                AuditIngressEnvelope::class,
                true,
                [
                    'int',
                    'string',
                    'array',
                    'string',
                    'string',
                    'int',
                    'string',
                    'string',
                    'string',
                    AuditTypedReference::class,
                ],
                [false, false, false, false, false, false, false, false, false, true],
            ],
            [
                AuditProtectedIngress::class,
                false,
                ['string', 'string', AuditTypedReference::class, 'int', 'string', 'string', 'string'],
                [false, false, true, false, false, false, false],
            ],
            [
                AuditIngressEnvelopeCodec::class,
                true,
                [
                    AuditRecordCodec::class,
                    AuditPartitionIdDeriver::class,
                    \BlackOps\Internal\Audit\AuditJcsCodec::class,
                ],
                [false, false, false],
            ],
            [
                AuditProtectedBodyCodec::class,
                true,
                [
                    BopdBinaryEnvelopeCodec::class,
                    AuditProtectionKeySource::class,
                    \BlackOps\Internal\StorageProtection\NonceSource::class,
                    AuditAssociatedData::class,
                    AuditRecordCodec::class,
                    AuditIngressEnvelopeCodec::class,
                ],
                [false, false, false, false, false, false],
            ],
            [BopdBinaryEnvelope::class, true, ['string', 'string', 'string', 'string'], [false, false, false, false]],
            [AuditProtectionException::class, false, [], []],
        ];
        foreach ($constructors as [$class, $public, $types, $nullable])
            $this->assertConstructor($class, $public, $types, $nullable);

        $surfaces = [
            BopdBinaryEnvelope::class => [
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'ciphertext',
                'keyId',
                'nonce',
                'tag',
            ],
            BopdBinaryEnvelopeCodec::class => ['keyIdFromHeader', 'open', 'parse', 'seal'],
            AuditLogicalRecordCandidate::class => ['__construct', 'toArray'],
            CanonicalAuditLogicalRecord::class => ['__construct', 'definition', 'toArray'],
            AuditRecordFinalizer::class => ['__construct', 'finalize'],
            AuditPartitionIdDeriver::class => ['__construct', 'derive'],
            AuditChainPosition::class => ['__construct'],
            AuditRecordIntegrity::class => ['__construct', 'toArray'],
            CompleteAuditRecord::class => [
                '__construct',
                'bytes',
                'definition',
                'integrity',
                'logicalSnapshot',
                'toArray',
            ],
            AuditProtectionKeySource::class => ['activeKey', 'key'],
            AuditProtectionException::class => ['failure', 'unavailable'],
            AuditAssociatedData::class => ['outboxForRead', 'outboxForWrite', 'recordForRead', 'recordForWrite'],
            AuditStoredRecord::class => [
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'fromArray',
                'protectedBody',
                'toArray',
            ],
            AuditIngressEnvelope::class => [
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'idempotencyKey',
                'toArray',
            ],
            AuditIngressEnvelopeCodec::class => ['__construct', 'create', 'decode', 'encode', 'idempotencyKeyHash'],
            AuditProtectedIngress::class => [
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'fromArray',
                'protectedBody',
                'toArray',
            ],
            AuditProtectedBodyCodec::class => [
                '__construct',
                'protectOutbox',
                'protectRecord',
                'unprotectOutbox',
                'unprotectRecord',
            ],
        ];
        foreach ($surfaces as $class => $expected) {
            $actual = [];
            foreach (new \ReflectionClass($class)->getMethods(\ReflectionMethod::IS_PUBLIC) as $method)
                if ($method->getDeclaringClass()->getName() === $class)
                    $actual[] = $method->getName();
            sort($actual);
            sort($expected);
            self::assertSame($expected, $actual, $class);
        }
    }

    public function testB1ProtectedConstructionAndHydrationCallsitesHaveOneOwner(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $counts = [
            'new AuditIngressEnvelope(' => [],
            'AuditStoredRecord::fromArray(' => [],
            'AuditProtectedIngress::fromArray(' => [],
            '->recordForWrite(' => [],
            '->recordForRead(' => [],
            '->outboxForWrite(' => [],
            '->outboxForRead(' => [],
        ];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php')
                continue;
            $path = realpath($file->getPathname());
            self::assertNotFalse($path);
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $relativePath = $this->relativeSourcePath($sourceRoot, $path);
            $semanticCounts = [
                'new AuditIngressEnvelope(' => $this->constructorCalls($source, AuditIngressEnvelope::class),
                'AuditStoredRecord::fromArray(' => $this->staticMethodCalls(
                    $source,
                    AuditStoredRecord::class,
                    'fromArray',
                ),
                'AuditProtectedIngress::fromArray(' => $this->staticMethodCalls(
                    $source,
                    AuditProtectedIngress::class,
                    'fromArray',
                ),
                '->recordForWrite(' => $this->methodCalls($source)['recordForWrite'] ?? 0,
                '->recordForRead(' => $this->methodCalls($source)['recordForRead'] ?? 0,
                '->outboxForWrite(' => $this->methodCalls($source)['outboxForWrite'] ?? 0,
                '->outboxForRead(' => $this->methodCalls($source)['outboxForRead'] ?? 0,
            ];
            foreach ($semanticCounts as $needle => $count)
                if ($count > 0)
                    $counts[$needle][$relativePath] = $count;
        }
        foreach ($counts as &$files)
            ksort($files);
        unset($files);
        self::assertSame(
            ['Internal/Audit/Protection/AuditIngressEnvelopeCodec.php' => 2],
            $counts['new AuditIngressEnvelope('],
        );
        self::assertSame(
            [
                'Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1,
                'Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php' => 1,
            ],
            $counts['AuditStoredRecord::fromArray('],
        );
        self::assertSame(
            [
                'Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php' => 1,
                'Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1,
            ],
            $counts['AuditProtectedIngress::fromArray('],
        );
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->recordForWrite(']);
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->recordForRead(']);
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->outboxForWrite(']);
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->outboxForRead(']);
        $protected = $this->protectedCallsites($sourceRoot);
        self::assertSame(['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1], $protected['protectRecord']);
        self::assertSame(['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1], $protected['unprotectRecord']);
        self::assertSame(['Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1], $protected['protectOutbox']);
        self::assertSame(
            [
                'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1,
                'Internal/Audit/Relay/AuditIngressCanonicalizer.php' => 1,
            ],
            $protected['unprotectOutbox'],
        );
        self::assertSame(0, $this->dynamicInvocationCountInTree($sourceRoot));
    }

    public function testProtectedCallsiteInventoryRejectsRemovalAdditionDynamicAndOutsideMutations(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $storePath = $sourceRoot . '/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php';
        $source = file_get_contents($storePath);
        self::assertIsString($source);
        $actual = $this->methodCalls($source);

        $commentSpoof = $source . "\n// ->protectRecord(\n// ->unprotectOutbox(\n";
        self::assertSame($actual, $this->methodCalls($commentSpoof));

        $added = $source . "\n\$synthetic->unprotectRecord();\n";
        self::assertSame(($actual['unprotectRecord'] ?? 0) + 1, $this->methodCalls($added)['unprotectRecord'] ?? 0);

        $replacementCount = 1;
        $removed = str_replace('->unprotectRecord(', '->removedCall(', $source, $replacementCount);
        self::assertSame(0, $this->methodCalls($removed)['unprotectRecord'] ?? 0);

        $replacementCount = 1;
        $dynamic = str_replace('->unprotectRecord(', '->{$method}(', $source, $replacementCount);
        self::assertSame(0, $this->methodCalls($dynamic)['unprotectRecord'] ?? 0);
        self::assertGreaterThan(0, $this->dynamicInvocationCount($dynamic));

        foreach (['protectRecord', 'unprotectRecord', 'protectOutbox', 'unprotectOutbox'] as $method) {
            foreach ([
                "\$codec->\$method();",
                "\$codec?->\$method();",
                "\$codec->{" . '$method' . '}();',
            ] as $dynamicCall) {
                $dynamic = $source . "\n" . $dynamicCall . "\n";
                self::assertSame(
                    $actual[$method] ?? 0,
                    $this->methodCalls($dynamic)[$method] ?? 0,
                    'dynamic protected calls must not become direct callers',
                );
                self::assertGreaterThan(0, $this->dynamicInvocationCount($dynamic));
            }
        }

        $sources = $this->sourceFiles($sourceRoot);
        $sources['External/PostgreSqlAuditRecordStore.php'] = '<?php $codec->unprotectRecord();';
        $combined = $this->protectedCallsitesFromSources($sources);
        self::assertSame(
            [
                'External/PostgreSqlAuditRecordStore.php' => 1,
                'Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1,
            ],
            $combined['unprotectRecord'],
        );
        self::assertNotSame(
            ['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1],
            $combined['unprotectRecord'],
            'same-basename outside sources must not overwrite the authorized owner',
        );
    }

    public function testValidatorAndProjectorExposeOnlyTheBoundedA2Seams(): void
    {
        $validator = new \ReflectionClass(AuditRecordValidator::class);
        $validateDraft = $validator->getMethod('validateDraft');
        self::assertSame(
            [AuditRecordDraft::class],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $validateDraft->getParameters()),
        );
        self::assertSame('void', $validateDraft->getReturnType()?->getName());
        $validateLogical = $validator->getMethod('validateLogical');
        self::assertSame(
            [AuditLogicalRecord::class],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $validateLogical->getParameters()),
        );
        self::assertSame(ValidatedAuditLogicalRecord::class, $validateLogical->getReturnType()?->getName());

        $project = new \ReflectionClass(ObservedAuditRecordProjector::class)->getMethod('project');
        self::assertSame(
            [AuditLogicalRecord::class],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $project->getParameters()),
        );
        self::assertSame('string', $project->getReturnType()?->getName());
        self::assertSame(
            [],
            array_filter(
                new \ReflectionClass(ObservedAuditRecordProjector::class)->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn(\ReflectionMethod $method): bool => (
                    $method->getName() !== '__construct'
                    && $method->getName() !== 'project'
                ),
            ),
        );
    }

    public function testCorrelationUsesPrivateConstructionAndOneFactorySignature(): void
    {
        $reflection = new \ReflectionClass(AuditRecordCorrelation::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertSame(
            ['fromArray', 'toArray'],
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
        );
        $factory = $reflection->getMethod('fromArray');
        self::assertSame(
            ['array'],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $factory->getParameters()),
        );
        self::assertSame(AuditRecordCorrelation::class, $factory->getReturnType()?->getName());
    }

    public function testValidatedResultHasOnlyItsDetachedSnapshotAndDefinition(): void
    {
        $reflection = new \ReflectionClass(ValidatedAuditLogicalRecord::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['snapshot', 'definition'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
        self::assertSame(
            ['__construct'],
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
        );
    }

    public function testA2DoesNotAddRuntimeOrPublicIntegrationFiles(): void
    {
        self::assertSame([], glob(__DIR__ . '/../../src/Application/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Http/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Console/Audit/*') ?: []);
    }

    public function testA2ProductTypesHaveNoSourceRegistrationOutsideTheirAllowedDirectories(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $allowedDirectories = array_filter([
            realpath($sourceRoot . '/Core/Audit'),
            realpath($sourceRoot . '/Internal/Audit/Record'),
            realpath($sourceRoot . '/Internal/Audit/Projection'),
            realpath($sourceRoot . '/Internal/Audit/Protection'),
            realpath($sourceRoot . '/Internal/Audit/Storage'),
            realpath($sourceRoot . '/Internal/Audit/Outbox'),
            realpath($sourceRoot . '/Internal/StorageProtection'),
        ]);
        $deliveryDirectory = realpath($sourceRoot . '/Internal/Audit/Delivery');
        self::assertNotFalse($deliveryDirectory);
        $productTypes = $this->a2ProductTypes();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php')
                continue;
            $path = $file->getPathname();
            $normalized = realpath($path);
            self::assertNotFalse($normalized);
            foreach ($allowedDirectories as $directory) {
                if ($normalized === $directory || str_starts_with($normalized, $directory . DIRECTORY_SEPARATOR))
                    continue 2;
            }
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertSame(
                [],
                $this->a2DeliveryViolations($normalized, $source, $deliveryDirectory, $productTypes),
                $path,
            );
            self::assertStringNotContainsString('observed_v1', $source, $path);
        }

        $requestPath = $sourceRoot . '/Internal/Audit/Orchestration/AuditDeliveryRequest.php';
        $requestSource = file_get_contents($requestPath);
        self::assertIsString($requestSource);
        $mutated = $requestSource . "\nnew AuditRecordFinalizer();\n";
        self::assertNotEmpty(
            $this->a2DeliveryViolations($requestPath, $mutated, $deliveryDirectory, $productTypes),
            'an unlisted A2 product reference in an allowlisted C3 path must be rejected',
        );

        $finalizer = 'BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer';
        $sinkSources = [
            "class_exists('{$finalizer}');",
            "\\class_exists('{$finalizer}');",
            '$one = \'' . $finalizer . '\'; interface_exists($one);',
            '$one = \'' . $finalizer . '\'; $two = $one; enum_exists($two);',
            "is_a('{$finalizer}', \\stdClass::class, true);",
            '$one = \'' . $finalizer . '\'; $two = $one; is_subclass_of($two, \\stdClass::class, true);',
        ];
        foreach ($sinkSources as $sinkSource) {
            self::assertSame(
                ['AuditRecordFinalizer'],
                $this->a2ProductReferences('<?php ' . $sinkSource, $productTypes),
                'consumed class-string sinks must resolve A2 product targets',
            );
        }
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2ProductReferences(
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Record;\nnew AuditRecordFinalizer();",
                $productTypes,
            ),
            'same-namespace executable references must remain consumed',
        );
        self::assertSame(
            [],
            $this->a2ProductReferences(
                "<?php\n\$decoy = '{$finalizer}';\nclass_exists('BlackOps\\\\Other\\\\NotAnAuditRecord');",
                $productTypes,
            ),
            'unconsumed and unrelated strings must remain decoys',
        );
        self::assertSame([], $this->a2ProductReferences(<<<'PHP'
            <?php
            class_exists('AuditRecordFinalizer');
            PHP, $productTypes), 'a bare short class string is not an exact namespaced target');
        self::assertSame([], $this->a2ProductReferences(<<<'PHP'
            <?php
            class_exists('Vendor\\AuditRecordFinalizer');
            PHP, $productTypes), 'a same-basename class string from another namespace is a decoy');
        $consumedMutation = $requestSource . <<<'PHP'

            $class = 'BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer';
            $one = $class;
            $two = $one;
            class_exists($two);
            PHP;
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations($requestPath, $consumedMutation, $deliveryDirectory, $productTypes),
            'an allowlisted C3 path using a consumed two-hop class string must be rejected',
        );

        $d2WriterPath = $sourceRoot . '/Internal/Audit/Read/AuditReadEventWriter.php';
        $d2WriterSource = file_get_contents($d2WriterPath);
        self::assertIsString($d2WriterSource);
        $d2DependencyMutation = $d2WriterSource . "\nnew AuditRecordFinalizer();\n";
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations($d2WriterPath, $d2DependencyMutation, $deliveryDirectory, $productTypes),
            'D2 per-file allowance must not permit an unrelated A2 dependency',
        );
        $d2ExtraPath = $sourceRoot . '/Internal/Audit/Read/D2Extra.php';
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $d2ExtraPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Read;\nnew AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'an extra D2 directory file must not inherit a directory-wide A2 exemption',
        );

        $authenticationWriterPath =
            $sourceRoot . '/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php';
        $authenticationWriterSource = file_get_contents($authenticationWriterPath);
        self::assertIsString($authenticationWriterSource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations(
                $authenticationWriterPath,
                $authenticationWriterSource,
                $deliveryDirectory,
                $productTypes,
            ),
            'the authentication writer may use only its explicitly allowed A2 record types',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $authenticationWriterPath,
                $authenticationWriterSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the authentication writer allowance must reject an unrelated A2 dependency',
        );
        $authenticationSiblingPath = $sourceRoot . '/Internal/Audit/Authentication/Sibling.php';
        self::assertSame(
            ['AuditRecordDraft'],
            $this->a2DeliveryViolations(
                $authenticationSiblingPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Authentication;\nnew \\BlackOps\\Core\\Audit\\AuditRecordDraft();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'an authentication sibling must not inherit the exact writer allowance',
        );

        $authorizationEvaluationPath = $sourceRoot . '/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php';
        $authorizationEvaluationSource = file_get_contents($authorizationEvaluationPath);
        self::assertIsString($authorizationEvaluationSource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations(
                $authorizationEvaluationPath,
                $authorizationEvaluationSource,
                $deliveryDirectory,
                $productTypes,
            ),
            'the authorization evaluation may use its explicitly allowed policy record type',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $authorizationEvaluationPath,
                $authorizationEvaluationSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the authorization evaluation allowance must reject an unrelated A2 dependency',
        );
        $authorizationSiblingPath = $sourceRoot . '/Internal/Audit/Authorization/Sibling.php';
        self::assertSame(['AuditRecordPolicy'], $this->a2DeliveryViolations($authorizationSiblingPath, <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Authorization;

            new \BlackOps\Core\Audit\AuditRecordPolicy(
                'authorization',
                1,
                \BlackOps\Core\Audit\AuditPolicyDecision::Allow,
                'allowed',
                \BlackOps\Core\Audit\AuditDelivery::Required,
            );
            PHP, $deliveryDirectory, $productTypes), 'an authorization sibling must not inherit the exact evaluation allowance');

        $authorizationFactoryPath = $sourceRoot . '/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php';
        $authorizationFactorySource = file_get_contents($authorizationFactoryPath);
        self::assertIsString($authorizationFactorySource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations(
                $authorizationFactoryPath,
                $authorizationFactorySource,
                $deliveryDirectory,
                $productTypes,
            ),
            'the authorization factory may use only its explicitly allowed A2 record types',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $authorizationFactoryPath,
                $authorizationFactorySource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the authorization factory allowance must reject an unrelated A2 dependency',
        );
        $authorizationFactorySiblingPath = $sourceRoot . '/Internal/Audit/Authorization/FactorySibling.php';
        self::assertSame(
            ['AuditRecordDraft'],
            $this->a2DeliveryViolations(
                $authorizationFactorySiblingPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Authorization;\nnew \\BlackOps\\Core\\Audit\\AuditRecordDraft();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'an authorization sibling must not inherit the exact factory allowance',
        );

        $authorizationAttemptPath = $sourceRoot . '/Internal/Audit/Authorization/AuthorizationAuditAttempt.php';
        $authorizationAttemptSource = file_get_contents($authorizationAttemptPath);
        self::assertIsString($authorizationAttemptSource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations(
                $authorizationAttemptPath,
                $authorizationAttemptSource,
                $deliveryDirectory,
                $productTypes,
            ),
            'the authorization attempt may inspect only its explicitly allowed policy record type',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $authorizationAttemptPath,
                $authorizationAttemptSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the authorization attempt allowance must reject an unrelated A2 dependency',
        );
        $authorizationAttemptSiblingPath = $sourceRoot . '/Internal/Audit/Authorization/AttemptSibling.php';
        self::assertSame(
            ['AuditRecordPolicy'],
            $this->a2DeliveryViolations(
                $authorizationAttemptSiblingPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Authorization;\nnew \\BlackOps\\Core\\Audit\\AuditRecordPolicy();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'an authorization sibling must not inherit the exact attempt allowance',
        );

        $retentionWriterPath = $sourceRoot . '/Internal/Audit/Retention/RetentionHoldAuditWriter.php';
        $retentionWriterSource = file_get_contents($retentionWriterPath);
        self::assertIsString($retentionWriterSource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations(
                $retentionWriterPath,
                $retentionWriterSource,
                $deliveryDirectory,
                $productTypes,
            ),
            'the retention writer may use only its explicitly allowed A2 record types',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $retentionWriterPath,
                $retentionWriterSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the retention writer allowance must reject an unrelated A2 dependency',
        );
        $retentionWriterSiblingPath = $sourceRoot . '/Internal/Audit/Retention/WriterSibling.php';
        self::assertSame(
            ['AuditRecordPolicy'],
            $this->a2DeliveryViolations(
                $retentionWriterSiblingPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Retention;\nnew \\BlackOps\\Core\\Audit\\AuditRecordPolicy();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'a retention sibling must not inherit the exact writer allowance',
        );

        $purgeWriterPath = $sourceRoot . '/Internal/Audit/Retention/RetentionPurgeAuditWriter.php';
        $purgeWriterSource = file_get_contents($purgeWriterPath);
        self::assertIsString($purgeWriterSource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations($purgeWriterPath, $purgeWriterSource, $deliveryDirectory, $productTypes),
            'the purge writer may use only its explicitly allowed A2 record types',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $purgeWriterPath,
                $purgeWriterSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the purge writer allowance must reject an unrelated A2 dependency',
        );
        $purgeWriterSiblingPath = $sourceRoot . '/Internal/Audit/Retention/PurgeWriterSibling.php';
        self::assertSame(
            ['AuditRecordPolicy'],
            $this->a2DeliveryViolations(
                $purgeWriterSiblingPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Retention;\nnew \\BlackOps\\Core\\Audit\\AuditRecordPolicy();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'a purge retention sibling must not inherit the exact writer allowance',
        );

        $rotationWriterPath = $sourceRoot . '/Internal/Audit/Rotation/StorageRotationAuditWriter.php';
        $rotationWriterSource = file_get_contents($rotationWriterPath);
        self::assertIsString($rotationWriterSource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations($rotationWriterPath, $rotationWriterSource, $deliveryDirectory, $productTypes),
            'the rotation writer may use only its explicitly allowed A2 record types',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $rotationWriterPath,
                $rotationWriterSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the rotation writer allowance must reject an unrelated A2 dependency',
        );
        $rotationWriterSiblingPath = $sourceRoot . '/Internal/Audit/Rotation/WriterSibling.php';
        self::assertSame(
            ['AuditRecordPolicy'],
            $this->a2DeliveryViolations(
                $rotationWriterSiblingPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Rotation;\nnew \\BlackOps\\Core\\Audit\\AuditRecordPolicy();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'a rotation sibling must not inherit the exact rotation writer allowance',
        );

        $replayWriterPath = $sourceRoot . '/Internal/Audit/Replay/ObserverReplayAuditWriter.php';
        $replayWriterSource = file_get_contents($replayWriterPath);
        self::assertIsString($replayWriterSource);
        self::assertSame(
            [],
            $this->a2DeliveryViolations($replayWriterPath, $replayWriterSource, $deliveryDirectory, $productTypes),
            'the replay writer may use only its explicitly allowed A2 record types',
        );
        self::assertSame(
            ['AuditRecordFinalizer'],
            $this->a2DeliveryViolations(
                $replayWriterPath,
                $replayWriterSource . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'the replay writer allowance must reject an unrelated A2 dependency',
        );
        $replayWriterSiblingPath = $sourceRoot . '/Internal/Audit/Replay/WriterSibling.php';
        self::assertSame(
            ['AuditRecordPolicy'],
            $this->a2DeliveryViolations(
                $replayWriterSiblingPath,
                "<?php\nnamespace BlackOps\\Internal\\Audit\\Replay;\nnew \\BlackOps\\Core\\Audit\\AuditRecordPolicy();\n",
                $deliveryDirectory,
                $productTypes,
            ),
            'a replay sibling must not inherit the exact writer allowance',
        );
    }

    public function testO3A2ConsumptionMapIsExactAndRejectsUnlistedSiblings(): void
    {
        $map = $this->o3A2AllowedReferences();
        foreach ($map as $relative => $expected) {
            $source = file_get_contents(__DIR__ . '/../../src/' . $relative);
            self::assertIsString($source);
            self::assertSame($expected, $this->o3A2ProductReferences($source), $relative);
        }

        $alias = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\OperationalOutbox;
            use BlackOps\Core\Audit\AuditRecordActor as Actor;
            new Actor(null, $execution);
            PHP;
        self::assertSame(['AuditRecordActor'], $this->o3A2ProductReferences($alias));
        self::assertSame([], $this->o3A2ProductReferences(<<<'PHP'
            <?php
            /* new \BlackOps\Core\Audit\AuditRecordActor(null, $execution); */
            $text = '\BlackOps\Core\Audit\AuditRecordActor';
            PHP));

        $sibling = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\OperationalOutbox;
            new \BlackOps\Core\Audit\AuditRecordActor(null, $execution);
            PHP;
        $relative = 'Internal/Audit/OperationalOutbox/UnlistedSibling.php';
        $violations = array_values(array_diff($this->o3A2ProductReferences($sibling), $map[$relative] ?? []));
        self::assertSame(['AuditRecordActor'], $violations);

        $rowRelative = 'Internal/Audit/OperationalOutbox/GenericOutboxAuditRow.php';
        $rowSource = file_get_contents(__DIR__ . '/../../src/' . $rowRelative);
        self::assertIsString($rowSource);
        $rowMutation = $rowSource . <<<'PHP'

            new \BlackOps\Core\Audit\AuditRecordActor(null, $reference);
            PHP;
        self::assertSame(
            ['AuditRecordActor', 'AuditTypedReference'],
            $this->o3A2ProductReferences($rowMutation),
            'the allowed Row source must resolve an injected unrelated A2 type',
        );
        self::assertSame(
            ['AuditRecordActor'],
            array_values(array_diff($this->o3A2ProductReferences($rowMutation), $map[$rowRelative])),
            'the per-file Row allowance must reject the injected unrelated A2 type',
        );
    }

    public function testA2DeliveryTimestampExceptionIsNarrowAndMutationResistant(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $deliveryDirectory = realpath($sourceRoot . '/Internal/Audit/Delivery');
        self::assertNotFalse($deliveryDirectory);
        $productTypes = $this->a2ProductTypes();

        $positive = '<?php use BlackOps\\Core\\Audit\\AuditRecordTime; AuditRecordTime::assertTimestamp($value);';
        $negative = str_replace('AuditRecordTime', 'AuditRecordDraft', $positive);
        self::assertSame(
            [],
            $this->a2DeliveryViolations(
                $deliveryDirectory . '/SyntheticAllowed.php',
                $positive,
                $deliveryDirectory,
                $productTypes,
            ),
        );
        self::assertSame(
            ['AuditRecordDraft'],
            $this->a2DeliveryViolations(
                $deliveryDirectory . '/SyntheticOtherA2.php',
                $negative,
                $deliveryDirectory,
                $productTypes,
            ),
        );
        self::assertSame(
            ['AuditRecordTime'],
            $this->a2DeliveryViolations(
                $sourceRoot . '/Internal/Audit/Other/SyntheticTimestamp.php',
                $positive,
                $deliveryDirectory,
                $productTypes,
            ),
        );
        $positiveAlias = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Delivery;
            use BlackOps\Core\Audit\AuditRecordTime as Time;
            final class SyntheticTimestamp
            {
                private static function allowed(): void
                {
                    Time::assertTimestamp($value);
                }
            }
            PHP;
        self::assertSame(['allowed' => 1], $this->auditTimestampCallInventory($positiveAlias));
        $positiveFqcn = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Delivery;
            final class SyntheticTimestamp
            {
                private static function allowed(): void
                {
                    \BlackOps\Core\Audit\AuditRecordTime::assertTimestamp($value);
                }
            }
            PHP;
        self::assertSame(['allowed' => 1], $this->auditTimestampCallInventory($positiveFqcn));
        $positiveCase = str_replace('assertTimestamp', 'ASSERTTIMESTAMP', $positiveAlias, $replacements);
        self::assertSame(1, $replacements);
        self::assertSame(['allowed' => 1], $this->auditTimestampCallInventory($positiveCase));
    }

    public function testReplayHelperA2EdgesAndArtifactTimestampOwnersAreExact(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $deliveryDirectory = realpath($sourceRoot . '/Internal/Audit/Delivery');
        self::assertNotFalse($deliveryDirectory);
        $productTypes = $this->a2ProductTypes();
        $helpers = [
            'Internal/Audit/Replay/ObserverReplayAuditWriter.php' => ['AuditRecordCorrelation'],
            'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php' => [],
            'Internal/Audit/Replay/ObserverReplayAuditWriterBinding.php' => [
                'AuditRecordActor',
                'AuditRecordCorrelation',
            ],
            'Internal/Audit/Replay/ObserverReplayAuditWriterRecord.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
        ];
        foreach ($helpers as $relative => $expected) {
            $path = $sourceRoot . '/' . $relative;
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertSame($expected, $this->a2ProductReferences($source, $productTypes), $relative);
            self::assertSame([], $this->a2DeliveryViolations($path, $source, $deliveryDirectory, $productTypes));
            $mutated = $source . "\nnew \\BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer();\n";
            self::assertSame(
                ['AuditRecordFinalizer'],
                $this->a2DeliveryViolations($path, $mutated, $deliveryDirectory, $productTypes),
                $relative . ' must reject an unlisted record dependency',
            );
        }

        $expectedTimestampOwners = [
            'Internal/Audit/Artifact/AuditArtifactShape.php' => [
                'anchorUnsigned' => 2,
                'checkpointUnsigned' => 2,
            ],
            'Internal/Audit/Artifact/AuditTrustSnapshotShape.php' => ['snapshot' => 3],
            'Internal/Audit/Artifact/AuditTrustEntryRelation.php' => ['assert' => 5],
            'Internal/Audit/Artifact/AuditTrustLookup.php' => ['assertSignedAt' => 2],
        ];
        foreach ($expectedTimestampOwners as $relative => $expected) {
            $path = $sourceRoot . '/' . $relative;
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertSame($expected, $this->auditTimestampCallInventory($source), $relative);
        }
        $artifact = $sourceRoot . '/Internal/Audit/Artifact/AuditArtifactShape.php';
        $source = file_get_contents($artifact);
        self::assertIsString($source);
        $caseMutation = str_replace(
            'AuditRecordTime::assertTimestamp(',
            'AuditRecordTime::ASSERTTIMESTAMP(',
            $source,
            $replacements,
        );
        self::assertSame(4, $replacements);
        self::assertSame(
            $expectedTimestampOwners['Internal/Audit/Artifact/AuditArtifactShape.php'],
            $this->auditTimestampCallInventory($caseMutation),
        );
        $mutated = str_replace(
            "AuditRecordTime::assertTimestamp(\$normalized['sealedAt']);",
            "AuditRecordTime::assertTimestamp(\$normalized['sealedAt']); AuditRecordTime::assertTimestamp(\$extra);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $expectedTimestampOwners['Internal/Audit/Artifact/AuditArtifactShape.php'],
            $this->auditTimestampCallInventory($mutated),
        );
        $wrongOwner = str_replace(
            "AuditRecordTime::assertTimestamp(\$normalized['sealedAt']);",
            "AuditRecordTime::assertTimestamp(\$normalized['sealedAt']);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $wrongOwner .= "\nfinal class SyntheticTimestampOwner { private function unrelated(): void { AuditRecordTime::ASSERTTIMESTAMP('extra'); } }";
        self::assertNotSame(
            $expectedTimestampOwners['Internal/Audit/Artifact/AuditArtifactShape.php'],
            $this->auditTimestampCallInventory($wrongOwner),
        );
        $additionalMethodCall = str_replace(
            "        \$checkpointHash = self::digest(\$value['checkpointHash']);",
            "        AuditRecordTime::assertTimestamp(\$value['signedAt']);\n        \$checkpointHash = self::digest(\$value['checkpointHash']);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $expectedTimestampOwners['Internal/Audit/Artifact/AuditArtifactShape.php'],
            $this->auditTimestampCallInventory($additionalMethodCall),
        );
    }

    public function testD1OperationalCatalogSurfaceAndDependenciesAreClosed(): void
    {
        $class = new \ReflectionClass(FrameworkOperationalAuditEventCatalog::class);
        self::assertTrue($class->isFinal());
        self::assertFalse($class->isReadOnly());
        self::assertFalse($class->getParentClass());
        self::assertSame([], $class->getInterfaceNames());
        self::assertSame([], $class->getTraits());
        self::assertSame(
            ['__construct', 'catalog', 'event'],
            array_values(array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    $class->getMethods(),
                    static fn(\ReflectionMethod $method): bool => $method
                        ->getDeclaringClass()
                        ->getName() === $class->getName(),
                ),
            )),
        );
        self::assertSame(
            ['instance'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $class->getProperties(),
            ),
        );
        $property = $class->getProperty('instance');
        self::assertTrue($property->isPrivate());
        self::assertTrue($property->isStatic());
        self::assertSame(AuditEventCatalog::class, $property->getType()?->getName());
        self::assertTrue($property->getType()?->allowsNull());
        self::assertNull($property->getDefaultValue());

        $constructor = $class->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertFalse($constructor->isStatic());
        self::assertFalse($constructor->isFinal());
        self::assertSame([], $constructor->getAttributes());
        self::assertSame([], $constructor->getParameters());

        $public = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        self::assertSame(
            ['catalog'],
            array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $public),
        );
        $catalog = $class->getMethod('catalog');
        self::assertTrue($catalog->isStatic());
        self::assertFalse($catalog->isFinal());
        self::assertFalse($catalog->isAbstract());
        self::assertSame([], $catalog->getAttributes());
        self::assertSame([], $catalog->getParameters());
        self::assertSame(AuditEventCatalog::class, $catalog->getReturnType()?->getName());

        $event = $class->getMethod('event');
        self::assertTrue($event->isPrivate());
        self::assertTrue($event->isStatic());
        self::assertFalse($event->isFinal());
        self::assertFalse($event->isAbstract());
        self::assertSame([], $event->getAttributes());
        $expectedEventParameters = [
            ['type',     'string'],
            ['phase',    AuditActionPhase::class],
            ['resource', 'string'],
            ['status',   AuditOutcomeStatus::class],
            ['outcome',  'string'],
            ['policy',   AuditPolicyDecision::class],
            ['reason',   'string'],
            ['delivery', AuditDelivery::class],
        ];
        self::assertCount(count($expectedEventParameters), $event->getParameters());
        foreach ($expectedEventParameters as $index => [$name, $type]) {
            $parameter = $event->getParameters()[$index];
            self::assertSame($name, $parameter->getName());
            self::assertSame($type, $parameter->getType()?->getName());
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isOptional());
            self::assertFalse($parameter->isDefaultValueAvailable());
            self::assertSame([], $parameter->getAttributes());
        }
        self::assertSame(AuditEventDefinition::class, $event->getReturnType()?->getName());

        $providerPath = realpath(__DIR__
        . '/../../src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php');
        self::assertNotFalse($providerPath);
        $providerSource = file_get_contents($providerPath);
        self::assertIsString($providerSource);
        self::assertTrue($this->operationalProviderSurfaceShape($providerSource));
        self::assertTrue($this->operationalProviderDependencyShape($providerSource));
        self::assertFalse($this->operationalProviderDependencyShape(str_replace(
            'return new AuditEventDefinition(',
            "new ImmutableAuditEventCatalogLookup([]);\n        return new AuditEventDefinition(",
            $providerSource,
            $replacementCount,
        )));
        self::assertSame(1, $replacementCount);

        $surfaceMutations = [
            'class parent' => $this->replaceProviderSourceOnce(
                $providerSource,
                "final class FrameworkOperationalAuditEventCatalog\n{",
                "final class FrameworkOperationalAuditEventCatalog extends \\stdClass\n{",
            ),
            'class interface' => $this->replaceProviderSourceOnce(
                $providerSource,
                "final class FrameworkOperationalAuditEventCatalog\n{",
                "final class FrameworkOperationalAuditEventCatalog implements \\Countable\n{",
            ),
            'class attribute' => $this->replaceProviderSourceOnce(
                $providerSource,
                "final class FrameworkOperationalAuditEventCatalog\n{",
                "#[\\AllowDynamicProperties]\nfinal class FrameworkOperationalAuditEventCatalog\n{",
            ),
            'class readonly modifier' => $this->replaceProviderSourceOnce(
                $providerSource,
                "final class FrameworkOperationalAuditEventCatalog\n{",
                "final readonly class FrameworkOperationalAuditEventCatalog\n{",
            ),
            'extra interface declaration' => $this->replaceProviderSourceOnce(
                $providerSource,
                "final class FrameworkOperationalAuditEventCatalog\n{",
                "interface ExtraOperationalCatalogInterface {}\n\nfinal class FrameworkOperationalAuditEventCatalog\n{",
            ),
            'extra trait declaration and use' => $this->replaceProviderSourceOnce(
                $this->replaceProviderSourceOnce(
                    $providerSource,
                    "final class FrameworkOperationalAuditEventCatalog\n{",
                    "trait ExtraOperationalCatalogTrait {}\n\nfinal class FrameworkOperationalAuditEventCatalog\n{",
                ),
                "    private static ?AuditEventCatalog \$instance = null;\n",
                "    use ExtraOperationalCatalogTrait;\n\n    private static ?AuditEventCatalog \$instance = null;\n",
            ),
            'extra enum declaration' => $this->replaceProviderSourceOnce(
                $providerSource,
                "final class FrameworkOperationalAuditEventCatalog\n{",
                "enum ExtraOperationalCatalogEnum { case CaseValue; }\n\nfinal class FrameworkOperationalAuditEventCatalog\n{",
            ),
            'extra class constant' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private static ?AuditEventCatalog \$instance = null;\n",
                "    private const EXTRA = true;\n\n    private static ?AuditEventCatalog \$instance = null;\n",
            ),
            'extra property' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private static ?AuditEventCatalog \$instance = null;\n",
                "    private string \$extra = 'extra';\n\n    private static ?AuditEventCatalog \$instance = null;\n",
            ),
            'property attribute' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private static ?AuditEventCatalog \$instance = null;\n",
                "    #[\\UnlistedOperationalCatalogAttribute]\n    private static ?AuditEventCatalog \$instance = null;\n",
            ),
            'property readonly modifier' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private static ?AuditEventCatalog \$instance = null;\n",
                "    private readonly AuditEventCatalog \$instance;\n",
            ),
            'property type' => $this->replaceProviderSourceOnce(
                $providerSource,
                'private static ?AuditEventCatalog $instance',
                'private static AuditEventCatalog $instance',
            ),
            'property visibility' => $this->replaceProviderSourceOnce(
                $providerSource,
                'private static ?AuditEventCatalog $instance',
                'protected static ?AuditEventCatalog $instance',
            ),
            'property staticness' => $this->replaceProviderSourceOnce(
                $providerSource,
                'private static ?AuditEventCatalog $instance',
                'private ?AuditEventCatalog $instance',
            ),
            'property default' => $this->replaceProviderSourceOnce(
                $providerSource,
                'private static ?AuditEventCatalog $instance = null',
                'private static ?AuditEventCatalog $instance = false',
            ),
            'catalog staticness' => $this->replaceProviderSourceOnce(
                $providerSource,
                'public static function catalog(',
                'public function catalog(',
            ),
            'event staticness' => $this->replaceProviderSourceOnce(
                $providerSource,
                'private static function event(',
                'private function event(',
            ),
            'event method attribute' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private static function event(\n",
                "    #[\\Deprecated]\n    private static function event(\n",
            ),
            'missing event callable' => $this->replaceProviderSourceOnce(
                $providerSource,
                'private static function event(',
                'private static function removedEvent(',
            ),
            'extra callable' => $this->replaceProviderSourceOnce(
                $providerSource,
                '    private static function event(',
                "    private function extraCallable(): void {}\n\n    private static function event(",
            ),
            'top-level extra callable' => $providerSource . "\nfunction extraCallable(): void {}\n",
            'top-level extra constant' => $providerSource . "\nconst EXTRA_OPERATIONAL_CATALOG = true;\n",
            'top-level extra declaration' => $providerSource . "\ninterface ExtraOperationalCatalog {}\n",
            'event parameter name' => $this->replaceProviderSourceOnce(
                $providerSource,
                "        string \$type,\n",
                "        string \$renamed,\n",
            ),
            'event parameter type' => $this->replaceProviderSourceOnce(
                $providerSource,
                "        AuditActionPhase \$phase,\n",
                "        ?AuditActionPhase \$phase,\n",
            ),
            'event parameter default' => $this->replaceProviderSourceOnce(
                $providerSource,
                "        AuditDelivery \$delivery,\n",
                "        AuditDelivery \$delivery = AuditDelivery::Durable,\n",
            ),
            'event parameter attribute' => $this->replaceProviderSourceOnce(
                $providerSource,
                "        string \$type,\n",
                "        #[\\SensitiveParameter] string \$type,\n",
            ),
            'event return type' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    ): AuditEventDefinition {\n",
                "    ): ?AuditEventDefinition {\n",
            ),
            'constructor visibility' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private function __construct() {}\n",
                "    public function __construct() {}\n",
            ),
            'second declaration' => $providerSource . "\nfinal class AnotherOperationalCatalog {}\n",
        ];
        foreach ($surfaceMutations as $label => $mutation)
            $this->assertValidProviderMutation($label, $mutation, false);

        $interfaceMutation = $this->replaceProviderSourceOnce(
            $surfaceMutations['class interface'],
            "    private function __construct() {}\n",
            "    public function count(): int { return 0; }\n\n    private function __construct() {}\n",
        );
        $this->assertValidProviderMutation('interface with implementation', $interfaceMutation, false);

        $dependencyMutations = [
            'extra import' => $this->replaceProviderSourceOnce(
                $providerSource,
                "namespace BlackOps\\Internal\\Audit\\Catalog;\n",
                "namespace BlackOps\\Internal\\Audit\\Catalog;\n\nuse BlackOps\\Core\\Audit\\AuditRecordDraft;\n",
            ),
            'aliased import' => $this->replaceProviderSourceOnce(
                $providerSource,
                "use BlackOps\\Core\\Audit\\AuditDelivery;\n",
                "use BlackOps\\Core\\Audit\\AuditDelivery as Delivery;\n",
            ),
            'group import' => $this->replaceProviderSourceOnce(
                $providerSource,
                "use BlackOps\\Core\\Audit\\AuditActionPhase;\nuse BlackOps\\Core\\Audit\\AuditDelivery;\n",
                "use BlackOps\\Core\\Audit\\{AuditActionPhase, AuditDelivery};\n",
            ),
            'fully qualified dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'AuditEventCategory::from(',
                '\\BlackOps\\Core\\Audit\\AuditEventCategory::from(',
            ),
            'same namespace dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "new ImmutableAuditEventCatalogLookup([]);\n        return new AuditEventDefinition(",
            ),
            'unlisted dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "new \\stdClass();\n        return new AuditEventDefinition(",
            ),
            'unlisted trait dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                "final class FrameworkOperationalAuditEventCatalog\n{\n",
                "final class FrameworkOperationalAuditEventCatalog\n{\n    use UnlistedOperationalCatalogTrait;\n",
            ),
            'unlisted environment function dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "getenv('BLACKOPS_UNLISTED');\n        return new AuditEventDefinition(",
            ),
            'unlisted filesystem function dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "file_get_contents('/tmp/blackops-unlisted');\n        return new AuditEventDefinition(",
            ),
            'unlisted random function dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "random_bytes(1);\n        return new AuditEventDefinition(",
            ),
            'unlisted class lookup function dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "class_exists('BlackOps\\\\Other\\\\Unrelated');\n        return new AuditEventDefinition(",
            ),
            'dynamic function dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$function = 'explode';\n        \$function('x', 'y');\n        return new AuditEventDefinition(",
            ),
            'require dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "require '/tmp/blackops-unlisted';\n        return new AuditEventDefinition(",
            ),
            'include dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "include '/tmp/blackops-unlisted';\n        return new AuditEventDefinition(",
            ),
            'eval dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "eval('return null;');\n        return new AuditEventDefinition(",
            ),
            'exit dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "exit('unlisted');\n        return new AuditEventDefinition(",
            ),
            'GLOBALS superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$GLOBALS['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'server superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_SERVER['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'GET superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_GET['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'POST superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_POST['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'FILES superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_FILES['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'COOKIE superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_COOKIE['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'SESSION superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_SESSION['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'REQUEST superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_REQUEST['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
            'ENV superglobal dependency' => $this->replaceProviderSourceOnce(
                $providerSource,
                'return new AuditEventDefinition(',
                "\$_ENV['BLACKOPS_UNLISTED'];\n        return new AuditEventDefinition(",
            ),
        ];
        foreach ($dependencyMutations as $label => $mutation)
            $this->assertValidProviderMutation($label, $mutation, true);

        $topLevelExecutableMutations = [
            'filesystem function' => $providerSource . "\nfile_put_contents('/tmp/blackops-d1-never', 'x');\n",
            'environment function' => $providerSource . "\ngetenv('BLACKOPS_UNLISTED');\n",
            'random function' => $providerSource . "\nrandom_bytes(1);\n",
            'class lookup' => $providerSource . "\nclass_exists('BlackOps\\\\Other\\\\Unrelated');\n",
            'dynamic function' => $providerSource . "\n\$unlistedFunction = 'strlen';\n\$unlistedFunction('x');\n",
            'include' => $providerSource . "\ninclude '/tmp/blackops-unlisted';\n",
            'require' => $providerSource . "\nrequire '/tmp/blackops-unlisted';\n",
            'eval' => $providerSource . "\neval('return null;');\n",
            'exit' => $providerSource . "\nexit('unlisted');\n",
            'environment superglobal' => $providerSource . "\n\$_ENV['BLACKOPS_UNLISTED'];\n",
            'server superglobal' => $providerSource . "\n\$_SERVER['BLACKOPS_UNLISTED'];\n",
            'throw' => $providerSource . "\nthrow new \\RuntimeException('unlisted');\n",
            'construction' => $providerSource . "\nnew \\stdClass();\n",
            'assignment' => $providerSource . "\n\$unlistedValue = 1;\n",
        ];
        foreach ($topLevelExecutableMutations as $label => $mutation) {
            $this->assertValidProviderMutation('top-level ' . $label . ' surface', $mutation, false);
            $this->assertValidProviderMutation('top-level ' . $label . ' dependency', $mutation, true);
        }

        $allowedClassConstant = $this->replaceProviderSourceOnce(
            $providerSource,
            '        if (self::$instance instanceof AuditEventCatalog) {',
            "        AuditActionPhase::class;\n        if (self::\$instance instanceof AuditEventCatalog) {",
        );
        $this->assertValidProviderMutation('allowed Core enum class constant', $allowedClassConstant, true, true);
        $decoySource = $this->replaceProviderSourceOnce(
            $providerSource,
            '        if (self::$instance instanceof AuditEventCatalog) {',
            "        // ImmutableAuditEventCatalogLookup is only a comment.\n        'BlackOps\\\\Other\\\\Unrelated';\n        if (self::\$instance instanceof AuditEventCatalog) {",
        );
        $this->assertValidProviderMutation('comments strings and unrelated class decoys', $decoySource, true, true);
        $this->assertValidProviderMutation('comments strings and unrelated surface decoys', $decoySource, false, true);

        $headerMutations = [
            'extra ticks declaration' => $this->replaceProviderSourceOnce(
                $providerSource,
                "declare(strict_types=1);\n",
                "declare(strict_types=1);\ndeclare(ticks=1);\n",
            ),
            'strict declaration replacement' => $this->replaceProviderSourceOnce(
                $providerSource,
                'declare(strict_types=1);',
                'declare(ticks=1);',
            ),
            'strict declaration omission' => $this->replaceProviderSourceOnce(
                $providerSource,
                "declare(strict_types=1);\n\n",
                '',
            ),
            'strict declaration value' => $this->replaceProviderSourceOnce(
                $providerSource,
                'declare(strict_types=1);',
                'declare(strict_types=0);',
            ),
            'strict declaration block' => $this->replaceProviderSourceOnce(
                $providerSource,
                'declare(strict_types=1);',
                'declare(strict_types=1, ticks=1);',
            ),
        ];
        foreach ($headerMutations as $label => $mutation) {
            $this->assertValidProviderMutation($label . ' surface', $mutation, false);
            $this->assertValidProviderMutation($label . ' dependency', $mutation, true);
        }
        $headerComment = $this->replaceProviderSourceOnce(
            $providerSource,
            "<?php\n",
            "<?php\n// a header comment is inert\n",
        );
        $this->assertValidProviderMutation('header comment surface', $headerComment, false, true);
        $this->assertValidProviderMutation('header comment dependency', $headerComment, true, true);

        $providerPath = 'Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php';
        self::assertSame(
            [],
            $this->operationalProviderReferenceInventory([$providerPath => $providerSource]),
            'self::event() and self::$instance are legitimate provider-local references',
        );
        $providerWithUnrelatedClass = $providerSource . <<<'PHP'

            final class UnrelatedOperationalCatalog
            {
                private static function probe(): void
                {
                    self::catalog();
                    static::catalog();
                }
            }
            PHP;
        self::assertSame(
            [],
            $this->operationalProviderReferenceInventory([
                'provider-with-unrelated-class' => $providerWithUnrelatedClass,
            ]),
            'self/static calls in another class are not provider references',
        );
        $referenceMutations = [
            'dead-branch self catalog call' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private function __construct() {}\n",
                "    private function __construct() { if (false) { self::catalog(); } }\n",
            ),
            'dead-branch static catalog call' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private function __construct() {}\n",
                "    private function __construct() { if (false) { static::catalog(); } }\n",
            ),
            'parenthesized self catalog call' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private function __construct() {}\n",
                "    private function __construct() { if (false) { (self::catalog)(); } }\n",
            ),
            'parenthesized static catalog call' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private function __construct() {}\n",
                "    private function __construct() { if (false) { (static::catalog)(); } }\n",
            ),
            'class constant plus self catalog call' => $this->replaceProviderSourceOnce(
                $providerSource,
                "    private function __construct() {}\n",
                "    private function __construct() { if (AuditActionPhase::class === '') { self::catalog(); } }\n",
            ),
        ];
        foreach ($referenceMutations as $label => $mutation) {
            try {
                token_get_all($mutation, TOKEN_PARSE);
            } catch (\ParseError $exception) {
                self::fail($label . ' reference mutation is not valid PHP: ' . $exception->getMessage());
            }
            $references = $this->operationalProviderReferenceInventory([$providerPath => $mutation]);
            self::assertCount(1, $references, $label . ' must be consumed by the reference scanner');
            self::assertSame(FrameworkOperationalAuditEventCatalog::class, $references[0]['target'], $label);
            self::assertSame('catalog', $references[0]['method'], $label);
            self::assertSame(str_contains($label, 'self') ? 'self' : 'static', $references[0]['receiver'], $label);
            self::assertSame(
                str_contains($label, 'parenthesized') ? 'dynamic' : (str_contains($label, 'self') ? 'self' : 'static'),
                $references[0]['kind'],
                $label,
            );
            self::assertSame([$providerPath => 1], $this->operationalProviderCallsites([$providerPath => $mutation]));
        }
    }

    public function testD1OperationalProviderReferenceAndNonactivationAreExact(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $sources = $this->sourceFiles($sourceRoot);
        self::assertSame(
            [
                'Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php' => 1,
                'Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php' => 1,
                'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php' => 1,
                'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php' => 1,
                'Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 1,
                'Internal/Audit/Retention/RetentionPurgeAuditWriter.php' => 1,
                'Internal/Audit/Rotation/StorageRotationAuditWriter.php' => 1,
            ],
            $this->operationalProviderCallsites($sources),
        );
        $lookupPath = 'Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php';
        self::assertSame(
            [
                [
                    'path' => 'Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => $lookupPath,
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'same_namespace',
                ],
                [
                    'path' => 'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Retention/RetentionHoldAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Retention/RetentionPurgeAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Rotation/StorageRotationAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
            ],
            $this->operationalProviderReferenceInventory($sources),
        );
        self::assertTrue($this->exactOperationalProviderReferenceShape($sources[$lookupPath]));
        $movedProviderCall = str_replace(
            '&& $catalog !== FrameworkOperationalAuditEventCatalog::catalog()',
            '&& $catalog !== FrameworkAuditEventCatalog::catalog()',
            str_replace(
                '        $identities = [];',
                "        \$identities = [];\n        FrameworkOperationalAuditEventCatalog::catalog();",
                $sources[$lookupPath],
            ),
        );
        self::assertFalse(
            $this->exactOperationalProviderReferenceShape($movedProviderCall),
            'a provider call outside the Framework-owner guard is not trusted',
        );
        self::assertTrue($this->exactOperationalProviderReferenceShape(
            $sources[$lookupPath] . "\n// FrameworkOperationalAuditEventCatalog::catalog();\n",
        ));
        self::assertTrue($this->exactOperationalProviderReferenceShape(
            $sources[$lookupPath] . "\n'FrameworkOperationalAuditEventCatalog::catalog()';\n",
        ));

        $registryPath = 'Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php';
        $registrySource = $sources[$registryPath];
        self::assertTrue($this->exactOperationalProviderAssemblyReferenceShape($registrySource));
        self::assertTrue($this->exactOperationalProviderAssemblyReferenceShape($registrySource
        . "\n// FrameworkOperationalAuditEventCatalog::catalog();\n"));
        self::assertTrue($this->exactOperationalProviderAssemblyReferenceShape($registrySource
        . "\n'FrameworkOperationalAuditEventCatalog::catalog()';\n"));
        $registryProviderAssignment = '        $operational = FrameworkOperationalAuditEventCatalog::catalog();';
        $registryMutations = [
            'registry provider call outside assembly' => $this->replaceProviderSourceOnce(
                $this->replaceProviderSourceOnce($registrySource, $registryProviderAssignment . "\n", ''),
                "    public function lookup(): ImmutableAuditEventCatalogLookup\n    {\n",
                "    public function lookup(): ImmutableAuditEventCatalogLookup\n    {\n"
                . $registryProviderAssignment
                . "\n",
            ),
            'registry provider call nested in branch' => $this->replaceProviderSourceOnce(
                $registrySource,
                $registryProviderAssignment,
                "        if (true) {\n            \$operational = FrameworkOperationalAuditEventCatalog::catalog();\n        }",
            ),
            'registry provider call in unbraced branch' => $this->replaceProviderSourceOnce(
                $registrySource,
                $registryProviderAssignment,
                "        if (false)\n            \$operational = FrameworkOperationalAuditEventCatalog::catalog();",
            ),
            'registry provider assignment owner changed' => $this->replaceProviderSourceOnce(
                $registrySource,
                $registryProviderAssignment,
                '        $framework = FrameworkOperationalAuditEventCatalog::catalog();',
            ),
            'registry provider target changed' => $this->replaceProviderSourceOnce(
                $registrySource,
                $registryProviderAssignment,
                '        $operational = FrameworkAuditEventCatalog::catalog();',
            ),
        ];
        foreach ($registryMutations as $label => $mutation) {
            try {
                token_get_all($mutation, TOKEN_PARSE);
            } catch (\ParseError $exception) {
                self::fail($label . ' reference mutation is not valid PHP: ' . $exception->getMessage());
            }
            self::assertFalse($this->exactOperationalProviderAssemblyReferenceShape($mutation), $label);
        }

        $providerPath = 'Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php';
        foreach ($sources as $path => $source) {
            if (
                $path === $providerPath
                || $path === $lookupPath
                || $path === $registryPath
                || $path === 'Internal/Audit/Retention/RetentionHoldAuditWriter.php'
                || $path === 'Internal/Audit/Retention/RetentionPurgeAuditWriter.php'
                || $path === 'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php'
                || $path === 'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php'
                || $path === 'Internal/Audit/Rotation/StorageRotationAuditWriter.php'
            )
                continue;
            self::assertStringNotContainsString(
                'FrameworkOperationalAuditEventCatalog',
                $this->phpCodeWithoutCommentsAndStrings($source),
                $path,
            );
        }
        self::assertSame([], glob(__DIR__ . '/../../src/Application/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Http/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Console/Audit/*') ?: []);

        $lookupSource = $sources[$lookupPath];
        $mutations = [
            'alias' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                'OperationalCatalog::catalog()',
                str_replace(
                    'namespace BlackOps\\Internal\\Audit\\Catalog;',
                    "namespace BlackOps\\Internal\\Audit\\Catalog;\nuse BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog as OperationalCatalog;",
                    $lookupSource,
                ),
            ),
            'group use' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                'OperationalCatalog::catalog()',
                str_replace(
                    'namespace BlackOps\\Internal\\Audit\\Catalog;',
                    "namespace BlackOps\\Internal\\Audit\\Catalog;\nuse BlackOps\\Internal\\Audit\\Catalog\\{FrameworkOperationalAuditEventCatalog as OperationalCatalog};",
                    $lookupSource,
                ),
            ),
            'FQCN' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                '\\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::catalog()',
                $lookupSource,
            ),
            'ordinary import' => str_replace(
                'namespace BlackOps\\Internal\\Audit\\Catalog;',
                "namespace BlackOps\\Internal\\Audit\\Catalog;\nuse BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog;",
                $lookupSource,
            ),
            'class string' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                'class_exists(FrameworkOperationalAuditEventCatalog::class)',
                $lookupSource,
            ),
            'reflection' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                "new \\ReflectionMethod(FrameworkOperationalAuditEventCatalog::class, 'catalog')->invoke(null)",
                $lookupSource,
            ),
            'callable array' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                "call_user_func([FrameworkOperationalAuditEventCatalog::class, 'catalog'])",
                $lookupSource,
            ),
            'dynamic receiver' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                '$provider::catalog()',
                $lookupSource,
            ),
            'parenthesized call' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                '(FrameworkOperationalAuditEventCatalog::catalog)()',
                $lookupSource,
            ),
            'one-hop consumed class string' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                '(static function (): bool { $class = FrameworkOperationalAuditEventCatalog::class; return class_exists($class); })()',
                $lookupSource,
            ),
            'two-hop consumed class string' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                "(static function (): bool { \$class = 'BlackOps\\\\Internal\\\\Audit\\\\Catalog\\\\FrameworkOperationalAuditEventCatalog'; \$one = \$class; \$two = \$one; return class_exists(\$two); })()",
                $lookupSource,
            ),
            'second declaration call' => $lookupSource . "\nFrameworkOperationalAuditEventCatalog::catalog();\n",
            'foreign namespace' => str_replace(
                'namespace BlackOps\\Internal\\Audit\\Catalog;',
                'namespace BlackOps\\Internal\\Audit\\Other;',
                $lookupSource,
            ),
            'missing reference' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                'FrameworkAuditEventCatalog::catalog()',
                $lookupSource,
            ),
            'wrong construction' => str_replace(
                'FrameworkOperationalAuditEventCatalog::catalog()',
                'new ImmutableAuditEventCatalogLookup([])',
                $lookupSource,
            ),
        ];
        foreach ($mutations as $label => $mutation) {
            try {
                token_get_all($mutation, TOKEN_PARSE);
            } catch (\ParseError $exception) {
                self::fail($label . ' reference mutation is not valid PHP: ' . $exception->getMessage());
            }
            self::assertFalse($this->exactOperationalProviderReferenceShape($mutation), $label);
        }

        $extraCaller = $sources;
        $extraCaller['External/OperationalCatalogCaller.php'] = '<?php \\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::catalog();';
        self::assertSame(
            [
                'External/OperationalCatalogCaller.php' => 1,
                'Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php' => 1,
                'Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php' => 1,
                'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php' => 1,
                'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php' => 1,
                'Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 1,
                'Internal/Audit/Retention/RetentionPurgeAuditWriter.php' => 1,
                'Internal/Audit/Rotation/StorageRotationAuditWriter.php' => 1,
            ],
            $this->operationalProviderCallsites($extraCaller),
        );
        self::assertSame(
            [
                [
                    'path' => 'External/OperationalCatalogCaller.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => '\\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'fqcn',
                ],
                [
                    'path' => 'Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => $lookupPath,
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'same_namespace',
                ],
                [
                    'path' => 'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Retention/RetentionHoldAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Retention/RetentionPurgeAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Rotation/StorageRotationAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
            ],
            $this->operationalProviderReferenceInventory($extraCaller),
        );
        $indirectCallers = [
            'External/OperationalCatalogClassStringLiteral.php' => <<<'PHP'
                <?php
                class_exists('BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog');
                PHP,
            'External/OperationalCatalogClassStringOneHop.php' => <<<'PHP'
                <?php
                namespace External;
                $class = \BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class;
                $one = $class;
                class_exists($one);
                PHP,
            'External/OperationalCatalogClassStringTwoHop.php' => <<<'PHP'
                <?php
                $class = 'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog';
                $one = $class;
                $two = $one;
                class_exists($two);
                PHP,
            'External/OperationalCatalogReflection.php' => <<<'PHP'
                <?php
                namespace External;
                new \ReflectionClass(\BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class);
                PHP,
            'External/OperationalCatalogReflectionMethodOneHop.php' => <<<'PHP'
                <?php
                namespace External;
                $class = \BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class;
                new \ReflectionMethod($class, 'catalog');
                PHP,
            'External/OperationalCatalogCallable.php' => <<<'PHP'
                <?php
                namespace External;
                call_user_func([\BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class, 'catalog']);
                PHP,
            'External/OperationalCatalogCallableLongLiteral.php' => <<<'PHP'
                <?php
                namespace External;
                call_user_func(array(
                    'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog',
                    'catalog',
                ));
                PHP,
            'External/OperationalCatalogCallableLongClassString.php' => <<<'PHP'
                <?php
                namespace External;
                call_user_func(array(
                    \BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class,
                    'catalog',
                ));
                PHP,
            'External/OperationalCatalogCallableLongVariable.php' => <<<'PHP'
                <?php
                namespace External;
                $class = \BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class;
                call_user_func(array($class, 'catalog'));
                PHP,
            'External/OperationalCatalogCallableTwoHop.php' => <<<'PHP'
                <?php
                namespace External;
                $class = 'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog';
                $one = $class;
                $callable = [$one, 'catalog'];
                call_user_func($callable);
                PHP,
            'External/OperationalCatalogDynamicReceiver.php' => <<<'PHP'
                <?php
                namespace External;
                $class = \BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class;
                $class::catalog();
                PHP,
            'External/OperationalCatalogDynamicReceiverTwoHop.php' => <<<'PHP'
                <?php
                namespace External;
                $class = 'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog';
                $one = $class;
                $two = $one;
                $two::catalog();
                PHP,
            'External/OperationalCatalogDynamicNew.php' => <<<'PHP'
                <?php
                namespace External;
                $class = \BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog::class;
                new $class();
                PHP,
        ];
        foreach ($indirectCallers as $path => $source) {
            try {
                token_get_all($source, TOKEN_PARSE);
            } catch (\ParseError $exception) {
                self::fail($path . ' indirect caller fixture is not valid PHP: ' . $exception->getMessage());
            }
        }
        $indirectInventory = $this->operationalProviderReferenceInventory($indirectCallers);
        self::assertCount(count($indirectCallers), $indirectInventory);
        $indirectPaths = array_keys($indirectCallers);
        sort($indirectPaths);
        self::assertSame(array_values(array_map(static fn(string $path): array => [
            'path' => $path,
            'target' => FrameworkOperationalAuditEventCatalog::class,
            'receiver' => match (true) {
                str_contains($path, 'Reflection') => str_contains($path, 'Method')
                    ? 'ReflectionMethod'
                    : 'ReflectionClass',
                str_contains($path, 'Callable') => 'callable-array',
                str_contains($path, 'DynamicNew') => '$class',
                str_contains($path, 'DynamicReceiver') => str_contains($path, 'TwoHop') ? '$two' : '$class',
                default => 'class_exists',
            },
            'method' => match (true) {
                str_contains($path, 'Reflection') => '__construct',
                str_contains($path, 'Callable') => 'catalog',
                str_contains($path, 'DynamicNew') => '__construct',
                str_contains($path, 'DynamicReceiver') => 'catalog',
                default => 'class_exists',
            },
            'kind' => match (true) {
                str_contains($path, 'Reflection') => 'reflection',
                str_contains($path, 'Callable') => 'callable',
                str_contains($path, 'DynamicReceiver') || str_contains($path, 'DynamicNew') => 'dynamic',
                default => 'class_string',
            },
        ], $indirectPaths)), $indirectInventory);
        self::assertSame(array_fill_keys($indirectPaths, 1), $this->operationalProviderCallsites($indirectCallers));
        self::assertSame(
            [],
            $this->operationalProviderReferenceInventory([
                'External/OperationalCatalogDecoys.php' => <<<'PHP'
                    <?php
                    // class_exists('BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog');
                    $unused = 'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog';
                    $unrelated = new \stdClass();
                    PHP,
            ]),
            'unconsumed strings and comments are not provider references',
        );
        $directDynamic = <<<'PHP'
            <?php
            $suffix = 'dynamic';
            $class = 'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog' . $suffix;
            class_exists($class);
            PHP;
        try {
            token_get_all($directDynamic, TOKEN_PARSE);
        } catch (\ParseError $exception) {
            self::fail('direct dynamic class-string fixture is not valid PHP: ' . $exception->getMessage());
        }
        self::assertSame(
            [],
            $this->operationalProviderReferenceInventory([
                'External/OperationalCatalogDirectDynamic.php' => $directDynamic,
            ]),
            'a literal class-string prefix concatenated with a runtime suffix is not a consumed provider identity',
        );
        $knownConcat = <<<'PHP'
            <?php
            $prefix = 'BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEvent';
            $suffix = 'Catalog';
            $class = $prefix . $suffix;
            class_exists($class);
            PHP;
        try {
            token_get_all($knownConcat, TOKEN_PARSE);
        } catch (\ParseError $exception) {
            self::fail('known concatenated class-string fixture is not valid PHP: ' . $exception->getMessage());
        }
        self::assertSame(
            [[
                'path' => 'External/OperationalCatalogKnownConcat.php',
                'target' => FrameworkOperationalAuditEventCatalog::class,
                'receiver' => 'class_exists',
                'method' => 'class_exists',
                'kind' => 'dynamic',
            ]],
            $this->operationalProviderReferenceInventory([
                'External/OperationalCatalogKnownConcat.php' => $knownConcat,
            ]),
            'a known literal concatenation resolving to the provider is a dynamic reference',
        );
        $wholePathCaller = $sources;
        $wholePathCaller['Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php'] = '<?php \\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::catalog();';
        self::assertSame(
            [
                'Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php' => 1,
                'Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php' => 1,
                'Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php' => 1,
                'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php' => 1,
                'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php' => 1,
                'Internal/Audit/Retention/RetentionHoldAuditWriter.php' => 1,
                'Internal/Audit/Retention/RetentionPurgeAuditWriter.php' => 1,
                'Internal/Audit/Rotation/StorageRotationAuditWriter.php' => 1,
            ],
            $this->operationalProviderCallsites($wholePathCaller),
            'the provider path is scanned as a normal source and cannot be exempted wholesale',
        );
        self::assertSame(
            [
                [
                    'path' => 'Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => '\\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'fqcn',
                ],
                [
                    'path' => 'Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'same_namespace',
                ],
                [
                    'path' => 'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Retention/RetentionHoldAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Retention/RetentionPurgeAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
                [
                    'path' => 'Internal/Audit/Rotation/StorageRotationAuditWriter.php',
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => 'FrameworkOperationalAuditEventCatalog',
                    'method' => 'catalog',
                    'kind' => 'alias',
                ],
            ],
            $this->operationalProviderReferenceInventory($wholePathCaller),
        );
    }

    private function replaceProviderSourceOnce(string $source, string $search, string $replacement): string
    {
        $result = str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count, 'provider mutation did not have one target');
        self::assertIsString($result);

        return $result;
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    private function assertValidProviderMutation(
        string $label,
        string $source,
        bool $dependency,
        bool $expected = false,
    ): void {
        try {
            token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError $exception) {
            self::fail($label . ' is not a valid PHP fixture: ' . $exception->getMessage());
        }
        self::assertSame(
            $expected,
            $dependency
                ? $this->operationalProviderDependencyShape($source)
                : $this->operationalProviderSurfaceShape($source),
            $label . ' scanner result was unexpected',
        );
    }

    private function operationalProviderSurfaceShape(string $source): bool
    {
        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError) {
            return false;
        }
        if ($this->namespaceName($tokens) !== 'BlackOps\\Internal\\Audit\\Catalog')
            return false;

        $classIndexes = [];
        foreach ($tokens as $index => $token) {
            if (
                is_array($token)
                && $token[0] === T_CLASS
                && ($nameIndex = $this->nextSignificantToken($tokens, $index + 1)) !== null
                && is_array($tokens[$nameIndex])
                && $tokens[$nameIndex][0] === T_STRING
            )
                $classIndexes[] = $index;
        }
        if (count($classIndexes) !== 1)
            return false;
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_INTERFACE, T_TRAIT, T_ENUM], true))
                return false;
            if (defined('T_ATTRIBUTE') && is_array($token) && $token[0] === T_ATTRIBUTE)
                return false;
        }
        $classIndex = $classIndexes[0];
        $modifier = $this->previousSignificantToken($tokens, $classIndex - 1);
        if ($modifier === null || !is_array($tokens[$modifier]) || $tokens[$modifier][0] !== T_FINAL)
            return false;
        $beforeFinal = $this->previousSignificantToken($tokens, $modifier - 1);
        if (
            $beforeFinal !== null
            && is_array($tokens[$beforeFinal])
            && in_array($tokens[$beforeFinal][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_ABSTRACT, T_READONLY], true)
        )
            return false;
        $className = $this->nextSignificantToken($tokens, $classIndex + 1);
        if (
            $className === null
            || !is_array($tokens[$className])
            || $tokens[$className][0] !== T_STRING
            || $tokens[$className][1] !== 'FrameworkOperationalAuditEventCatalog'
        )
            return false;
        $opening = $this->nextSignificantToken($tokens, $className + 1);
        while ($opening !== null && $tokens[$opening] !== '{') {
            if (is_array($tokens[$opening]) && in_array($tokens[$opening][0], [T_EXTENDS, T_IMPLEMENTS], true))
                return false;
            $opening = $this->nextSignificantToken($tokens, $opening + 1);
        }
        if ($opening === null)
            return false;
        $closing = $this->matchingBrace($tokens, $opening);
        if ($closing === null)
            return false;
        if (!$this->operationalProviderTopLevelShape($tokens, $classIndex, $opening, $closing))
            return false;
        $namespaceDeclarations = 0;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE)
                $namespaceDeclarations++;
            if ($index < $opening || $index > $closing) {
                if (
                    is_array($token)
                    && in_array($token[0], [T_FUNCTION, T_CONST, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
                    && !($token[0] === T_CLASS && $index === $classIndex)
                )
                    return false;
            }
        }
        if ($namespaceDeclarations !== 1)
            return false;

        $methods = [];
        $properties = [];
        $depth = 1;
        for ($index = $opening + 1; $index < $closing; $index++) {
            $token = $tokens[$index];
            if ($token === '{') {
                $depth++;
                continue;
            }
            if ($token === '}') {
                $depth--;
                continue;
            }
            if ($depth !== 1 || !is_array($token))
                continue;
            if ($token[0] === T_USE || $token[0] === T_CONST || defined('T_ATTRIBUTE') && $token[0] === T_ATTRIBUTE)
                return false;
            if ($token[0] === T_FUNCTION) {
                $nameIndex = $this->nextSignificantToken($tokens, $index + 1);
                if ($nameIndex !== null && $tokens[$nameIndex] === '&')
                    $nameIndex = $this->nextSignificantToken($tokens, $nameIndex + 1);
                if ($nameIndex === null || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING)
                    return false;
                $parameterOpening = $this->nextSignificantToken($tokens, $nameIndex + 1);
                if ($parameterOpening === null || $tokens[$parameterOpening] !== '(')
                    return false;
                $parameterClosing = $this->matchingParenthesis($tokens, $parameterOpening);
                if ($parameterClosing === null)
                    return false;
                $bodyOpening = $this->nextSignificantToken($tokens, $parameterClosing + 1);
                if ($bodyOpening === null)
                    return false;
                $returnType = null;
                if ($tokens[$bodyOpening] === ':') {
                    $returnTokens = [];
                    for ($returnIndex = $bodyOpening + 1; $returnIndex < $closing; $returnIndex++) {
                        if ($tokens[$returnIndex] === '{' || $tokens[$returnIndex] === ';') {
                            $bodyOpening = $returnIndex;
                            break;
                        }
                        $returnTokens[] = $tokens[$returnIndex];
                    }
                    $returnType = [
                        'type' => $this->providerTypeFromTokens($returnTokens, $tokens),
                        'nullable' => $this->providerTypeIsNullable($returnTokens),
                    ];
                }
                if ($tokens[$bodyOpening] !== '{')
                    return false;
                $methods[] = [
                    'name' => $tokens[$nameIndex][1],
                    'modifiers' => $this->providerMethodModifiers($tokens, $index, $opening),
                    'parameters' => $this->providerParameterDescriptors($tokens, $parameterOpening, $parameterClosing),
                    'return' => $returnType,
                ];
                $bodyClosing = $this->matchingBrace($tokens, $bodyOpening);
                if ($bodyClosing === null)
                    return false;
                $index = $bodyClosing;
                continue;
            }
            if ($token[0] === T_VARIABLE) {
                $semicolon = $this->nextSignificantToken($tokens, $index + 1);
                while ($semicolon !== null && $tokens[$semicolon] !== ';')
                    $semicolon = $this->nextSignificantToken($tokens, $semicolon + 1);
                if ($semicolon === null)
                    return false;
                $properties[] = $this->providerPropertyDescriptor($tokens, $index, $semicolon, $opening);
                $index = $semicolon;
            }
        }

        usort($methods, static fn(array $left, array $right): int => $left['name'] <=> $right['name']);
        usort($properties, static fn(array $left, array $right): int => $left['name'] <=> $right['name']);

        if ($this->operationalProviderReferenceInventory(['provider' => $source]) !== [])
            return false;

        return (
            $methods === [
                [
                    'name' => '__construct',
                    'modifiers' => ['private'],
                    'parameters' => [],
                    'return' => null,
                ],
                [
                    'name' => 'catalog',
                    'modifiers' => ['public', 'static'],
                    'parameters' => [],
                    'return' => ['type' => AuditEventCatalog::class, 'nullable' => false],
                ],
                [
                    'name' => 'event',
                    'modifiers' => ['private', 'static'],
                    'parameters' => [
                        [
                            'name' => 'type',
                            'type' => 'string',
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                        [
                            'name' => 'phase',
                            'type' => AuditActionPhase::class,
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                        [
                            'name' => 'resource',
                            'type' => 'string',
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                        [
                            'name' => 'status',
                            'type' => AuditOutcomeStatus::class,
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                        [
                            'name' => 'outcome',
                            'type' => 'string',
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                        [
                            'name' => 'policy',
                            'type' => AuditPolicyDecision::class,
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                        [
                            'name' => 'reason',
                            'type' => 'string',
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                        [
                            'name' => 'delivery',
                            'type' => AuditDelivery::class,
                            'nullable' => false,
                            'default' => null,
                            'attributes' => [],
                        ],
                    ],
                    'return' => ['type' => AuditEventDefinition::class, 'nullable' => false],
                ],
            ]
            && $properties === [[
                'name' => 'instance',
                'visibility' => 'private',
                'static' => true,
                'type' => AuditEventCatalog::class,
                'nullable' => true,
                'default' => 'null',
                'attributes' => [],
            ]]
        );
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return list<string> */
    private function providerMethodModifiers(array $tokens, int $functionIndex, int $classOpening): array
    {
        $modifiers = [];
        $skippedPreviousBody = false;
        for ($index = $functionIndex - 1; $index > $classOpening; $index--) {
            $token = $tokens[$index];
            if (!is_array($token) || in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
                continue;
            if ($token === '}' && !$skippedPreviousBody) {
                $skippedPreviousBody = true;
                continue;
            }
            $modifier =
                [
                    T_PUBLIC => 'public',
                    T_PROTECTED => 'protected',
                    T_PRIVATE => 'private',
                    T_STATIC => 'static',
                    T_FINAL => 'final',
                    T_ABSTRACT => 'abstract',
                ][$token[0]] ?? null;
            if ($modifier === null)
                break;
            $modifiers[] = $modifier;
        }
        sort($modifiers);

        return $modifiers;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function providerPropertyDescriptor(
        array $tokens,
        int $variableIndex,
        int $semicolon,
        int $classOpening,
    ): array {
        $start = $variableIndex;
        while ($start > ($classOpening + 1)) {
            $previous = $this->previousSignificantToken($tokens, $start - 1);
            if ($previous === null || in_array($tokens[$previous], [';', '{', '}'], true))
                break;
            $start = $previous;
        }
        $visibility = null;
        $static = false;
        $typeTokens = [];
        $default = null;
        $seenVariable = false;
        for ($index = $start; $index < $semicolon; $index++) {
            $token = $tokens[$index];
            if ($token === '=') {
                $defaultTokens = [];
                for ($defaultIndex = $index + 1; $defaultIndex < $semicolon; $defaultIndex++)
                    $defaultTokens[] = $tokens[$defaultIndex];
                $default = $this->providerTokenText($defaultTokens);
                break;
            }
            if (!is_array($token)) {
                if (!$seenVariable)
                    $typeTokens[] = $token;
                continue;
            }
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
                continue;
            if (
                defined('T_ATTRIBUTE') && $token[0] === T_ATTRIBUTE
                || defined('T_READONLY') && $token[0] === T_READONLY
            )
                return ['invalid' => true];
            if (in_array($token[0], [T_PRIVATE, T_PROTECTED, T_PUBLIC], true)) {
                $visibility = strtolower($token[1]);
                continue;
            }
            if ($token[0] === T_STATIC) {
                $static = true;
                continue;
            }
            if ($token[0] === T_VARIABLE) {
                if ($seenVariable)
                    return ['invalid' => true];
                $seenVariable = true;
                $name = substr($token[1], 1);
                continue;
            }
            if (!$seenVariable)
                $typeTokens[] = $token;
        }

        return [
            'name' => $name ?? '',
            'visibility' => $visibility,
            'static' => $static,
            'type' => $this->providerTypeFromTokens($typeTokens, $tokens),
            'nullable' => $this->providerTypeIsNullable($typeTokens),
            'default' => $default,
            'attributes' => [],
        ];
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return list<array{name:string,type:?string,nullable:bool,default:?string,attributes:list<string>}> */
    private function providerParameterDescriptors(array $tokens, int $opening, int $closing): array
    {
        $parameters = [];
        $part = [];
        $depth = 0;
        $parts = [];
        for ($index = $opening + 1; $index < $closing; $index++) {
            $token = $tokens[$index];
            if (in_array($token, ['(', '[', '{'], true))
                $depth++;
            if (in_array($token, [')', ']', '}'], true))
                $depth--;
            if ($token === ',' && $depth === 0) {
                $parts[] = $part;
                $part = [];
                continue;
            }
            $part[] = $token;
        }
        if ($part !== [] && $this->providerTokenText($part) !== '')
            $parts[] = $part;
        foreach ($parts as $part) {
            $name = '';
            $variableIndex = null;
            foreach ($part as $index => $token)
                if (is_array($token) && $token[0] === T_VARIABLE) {
                    $name = substr($token[1], 1);
                    $variableIndex = $index;
                }
            if ($variableIndex === null)
                return [[
                    'name' => '',
                    'type' => null,
                    'nullable' => false,
                    'default' => null,
                    'attributes' => ['invalid'],
                ]];
            $typeTokens = array_slice($part, 0, $variableIndex);
            $default = null;
            foreach ($part as $index => $token) {
                if ($index <= $variableIndex || $token !== '=')
                    continue;
                $default = $this->providerTokenText(array_slice($part, $index + 1));
                break;
            }
            $attributes = [];
            foreach ($typeTokens as $token)
                if ($token === '#[' || is_array($token) && defined('T_ATTRIBUTE') && $token[0] === T_ATTRIBUTE)
                    $attributes[] = 'attribute';
            $parameters[] = [
                'name' => $name,
                'type' => $this->providerTypeFromTokens($typeTokens, $tokens),
                'nullable' => $this->providerTypeIsNullable($typeTokens),
                'default' => $default,
                'attributes' => $attributes,
            ];
        }

        return $parameters;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $typeTokens @param list<array{0:int,1:string,2:int}|string> $allTokens */
    private function providerTypeFromTokens(array $typeTokens, array $allTokens): ?string
    {
        $names = [];
        foreach ($typeTokens as $token) {
            if (!is_array($token) || !$this->isNameToken($token))
                continue;
            if (in_array(
                $token[1],
                ['string', 'int', 'bool', 'float', 'array', 'mixed', 'object', 'iterable', 'callable', 'void', 'never'],
                true,
            ))
                $names[] = $token[1];
            else
                $names[] = $this->resolveClassName(
                    $token,
                    $this->namespaceName($allTokens),
                    $this->importAliases($allTokens),
                );
        }
        if ($names === [])
            return null;
        if (count($names) !== 1)
            return implode('|', $names);
        $name = $names[0];
        return $name;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function providerTypeIsNullable(array $tokens): bool
    {
        foreach ($tokens as $token)
            if ($token === '?')
                return true;

        return false;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function providerTokenText(array $tokens): string
    {
        $text = '';
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
                continue;
            $text .= is_array($token) ? $token[1] : $token;
        }

        return $text;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function matchingBrace(array $tokens, int $opening): ?int
    {
        $depth = 0;
        for ($index = $opening, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index] === '{')
                $depth++;
            elseif ($tokens[$index] === '}') {
                $depth--;
                if ($depth === 0)
                    return $index;
            }
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function operationalProviderTopLevelShape(
        array $tokens,
        int $classIndex,
        int $classOpening,
        int $classClosing,
    ): bool {
        $statement = null;
        $statementStart = null;
        $declares = [];
        $namespaces = [];
        $imports = [];
        for ($index = 0; $index < $classIndex; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true))
                continue;
            if ($statement === null) {
                if (is_array($token) && $token[0] === T_FINAL)
                    continue;
                if (!is_array($token) || !in_array($token[0], [T_DECLARE, T_NAMESPACE, T_USE], true))
                    return false;
                $statement = $token[0];
                $statementStart = $index;
                continue;
            }
            if ($token === ';') {
                if ($statementStart === null)
                    return false;
                $text = $this->providerTokenText(array_slice($tokens, $statementStart, $index - $statementStart + 1));
                if ($statement === T_DECLARE)
                    $declares[] = $text;
                elseif ($statement === T_NAMESPACE)
                    $namespaces[] = $text;
                else
                    $imports[] = $text;
                $statement = null;
                $statementStart = null;
                continue;
            }
            if (
                is_array($token)
                && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION, T_CONST], true)
            )
                return false;
        }
        if ($statement !== null)
            return false;
        sort($imports);
        if ($declares !== ['declare(strict_types=1);'])
            return false;
        if ($namespaces !== ['namespaceBlackOps\\Internal\\Audit\\Catalog;'])
            return false;
        if (
            $imports !== [
                'useBlackOps\\Core\\Audit\\AuditActionPhase;',
                'useBlackOps\\Core\\Audit\\AuditDelivery;',
                'useBlackOps\\Core\\Audit\\AuditEventCatalog;',
                'useBlackOps\\Core\\Audit\\AuditEventCategory;',
                'useBlackOps\\Core\\Audit\\AuditEventDefinition;',
                'useBlackOps\\Core\\Audit\\AuditOutcomeStatus;',
                'useBlackOps\\Core\\Audit\\AuditPolicyDecision;',
                'useBlackOps\\Core\\Audit\\AuditSemanticTuple;',
                'useBlackOps\\Core\\Audit\\AuditSourceOwner;',
            ]
        )
            return false;

        for ($index = $classClosing + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
                continue;
            return false;
        }

        return $classOpening < $classClosing;
    }

    private function operationalProviderDependencyShape(string $source): bool
    {
        $expectedImports = [
            'use BlackOps\\Core\\Audit\\AuditActionPhase;',
            'use BlackOps\\Core\\Audit\\AuditDelivery;',
            'use BlackOps\\Core\\Audit\\AuditEventCatalog;',
            'use BlackOps\\Core\\Audit\\AuditEventCategory;',
            'use BlackOps\\Core\\Audit\\AuditEventDefinition;',
            'use BlackOps\\Core\\Audit\\AuditOutcomeStatus;',
            'use BlackOps\\Core\\Audit\\AuditPolicyDecision;',
            'use BlackOps\\Core\\Audit\\AuditSemanticTuple;',
            'use BlackOps\\Core\\Audit\\AuditSourceOwner;',
        ];
        preg_match_all('/^use[ \\t]+[^;]+;[ \\t]*$/m', $source, $matches);
        $actualImports = $matches[0] ?? [];
        sort($expectedImports);
        sort($actualImports);
        if ($expectedImports !== $actualImports)
            return false;

        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError) {
            return false;
        }
        if ($this->namespaceName($tokens) !== 'BlackOps\\Internal\\Audit\\Catalog')
            return false;
        $classIndex = null;
        foreach ($tokens as $index => $token) {
            if (
                is_array($token)
                && $token[0] === T_CLASS
                && ($nameIndex = $this->nextSignificantToken($tokens, $index + 1)) !== null
                && is_array($tokens[$nameIndex])
                && $tokens[$nameIndex][0] === T_STRING
            ) {
                $classIndex = $index;
                break;
            }
        }
        if ($classIndex === null)
            return false;
        $className = $this->nextSignificantToken($tokens, $classIndex + 1);
        $opening = $className === null ? null : $this->nextSignificantToken($tokens, $className + 1);
        while ($opening !== null && $tokens[$opening] !== '{')
            $opening = $this->nextSignificantToken($tokens, $opening + 1);
        if ($opening === null)
            return false;
        $closing = $this->matchingBrace($tokens, $opening);
        if ($closing === null)
            return false;
        if (!$this->operationalProviderTopLevelShape($tokens, $classIndex, $opening, $closing))
            return false;

        $aliases = $this->importAliases($tokens);
        $allowedClasses = [
            'BlackOps\\Core\\Audit\\AuditActionPhase',
            'BlackOps\\Core\\Audit\\AuditDelivery',
            'BlackOps\\Core\\Audit\\AuditEventCatalog',
            'BlackOps\\Core\\Audit\\AuditEventCategory',
            'BlackOps\\Core\\Audit\\AuditEventDefinition',
            'BlackOps\\Core\\Audit\\AuditOutcomeStatus',
            'BlackOps\\Core\\Audit\\AuditPolicyDecision',
            'BlackOps\\Core\\Audit\\AuditSemanticTuple',
            'BlackOps\\Core\\Audit\\AuditSourceOwner',
            'BlackOps\\Internal\\Audit\\Catalog\\AuditEventCatalogCodec',
        ];
        $allowedShortClasses = array_map(static fn(string $class): string => substr(
            $class,
            strrpos($class, '\\') + 1,
        ), $allowedClasses);
        $newTargets = [];
        $classReferences = [];
        for ($index = $opening + 1; $index < $closing; $index++) {
            $token = $tokens[$index];
            if (!is_array($token))
                continue;
            if (in_array(
                $token[0],
                [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE],
                true,
            ))
                continue;
            if (
                defined('T_NAME_QUALIFIED')
                && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
            )
                return false;
            if ($token[0] === T_USE)
                return false;
            if ($token[0] === T_NEW) {
                $nameIndex = $this->nextSignificantToken($tokens, $index + 1);
                if ($nameIndex === null || !is_array($tokens[$nameIndex]) || !$this->isNameToken($tokens[$nameIndex]))
                    return false;
                $newTarget = $this->resolveClassName($tokens[$nameIndex], $this->namespaceName($tokens), $aliases);
                if (!in_array(
                    $newTarget,
                    [
                        'BlackOps\\Internal\\Audit\\Catalog\\AuditEventCatalogCodec',
                        'BlackOps\\Core\\Audit\\AuditEventDefinition',
                        'BlackOps\\Core\\Audit\\AuditSemanticTuple',
                    ],
                    true,
                ))
                    return false;
                $newTargets[] = $newTarget;
                continue;
            }
            if (!$this->isNameToken($token))
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null || !is_array($tokens[$next]) || $tokens[$next][0] !== T_DOUBLE_COLON)
                continue;
            $resolved = $token[1] === 'self'
                ? 'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog'
                : $this->resolveClassName($token, $this->namespaceName($tokens), $aliases);
            if (
                !in_array($resolved, $allowedClasses, true)
                && $resolved !== 'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog'
            )
                return false;
            $classReferences[] = $resolved;
            $member = $this->nextSignificantToken($tokens, $next + 1);
            if ($member !== null && $tokens[$member] === '{')
                return false;
        }
        for ($index = $opening + 1; $index < $closing; $index++) {
            $token = $tokens[$index];
            if (
                !is_array($token)
                || in_array(
                    $token[0],
                    [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE],
                    true,
                )
            )
                continue;
            if ($token[0] !== T_STRING)
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null || !is_array($tokens[$next]) || $tokens[$next][0] !== T_VARIABLE)
                continue;
            $resolved = in_array(
                $token[1],
                ['string', 'int', 'bool', 'float', 'array', 'mixed', 'object', 'iterable', 'callable', 'void', 'never'],
                true,
            )
                ? $token[1]
                : $this->resolveClassName($token, $this->namespaceName($tokens), $aliases);
            if (
                !in_array($resolved, $allowedClasses, true)
                && !in_array(
                    $resolved,
                    [
                        'string',
                        'int',
                        'bool',
                        'float',
                        'array',
                        'mixed',
                        'object',
                        'iterable',
                        'callable',
                        'void',
                        'never',
                    ],
                    true,
                )
            )
                return false;
        }
        foreach ($allowedShortClasses as $shortClass) {
            $found = false;
            for ($index = $opening + 1; $index < $closing; $index++) {
                $token = $tokens[$index];
                if (is_array($token) && $token[0] === T_STRING && $token[1] === $shortClass) {
                    $found = true;
                    break;
                }
            }
            if (!$found)
                return false;
        }
        if (
            $this->operationalProviderFunctionCalls($tokens, $opening, $closing) !== [
                'explode' => 1,
                'usort' => 1,
            ]
        )
            return false;
        if ($this->operationalProviderReferenceInventory(['provider' => $source]) !== [])
            return false;
        $valid =
            $newTargets === [
                'BlackOps\\Internal\\Audit\\Catalog\\AuditEventCatalogCodec',
                'BlackOps\\Core\\Audit\\AuditEventDefinition',
                'BlackOps\\Core\\Audit\\AuditSemanticTuple',
            ]
            && count(array_filter(
                $classReferences,
                static fn(string $class): bool => (
                    $class === 'BlackOps\\Core\\Audit\\AuditActionPhase'
                    || $class === 'BlackOps\\Core\\Audit\\AuditDelivery'
                    || $class === 'BlackOps\\Core\\Audit\\AuditEventCatalog'
                    || $class === 'BlackOps\\Core\\Audit\\AuditEventCategory'
                    || $class === 'BlackOps\\Core\\Audit\\AuditOutcomeStatus'
                    || $class === 'BlackOps\\Core\\Audit\\AuditPolicyDecision'
                    || $class === 'BlackOps\\Core\\Audit\\AuditSourceOwner'
                    || $class === 'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog'
                ),
            )) > 0;
        return $valid;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array<string,int>|null */
    private function operationalProviderFunctionCalls(array $tokens, int $opening, int $closing): ?array
    {
        $calls = [];
        $forbiddenTokens = array_values(array_filter(
            [
                defined('T_REQUIRE') ? T_REQUIRE : null,
                defined('T_REQUIRE_ONCE') ? T_REQUIRE_ONCE : null,
                defined('T_INCLUDE') ? T_INCLUDE : null,
                defined('T_INCLUDE_ONCE') ? T_INCLUDE_ONCE : null,
                defined('T_EVAL') ? T_EVAL : null,
                defined('T_EXIT') ? T_EXIT : null,
            ],
            static fn(?int $token): bool => $token !== null,
        ));
        $forbiddenSuperglobals = [
            '$GLOBALS',
            '$_SERVER',
            '$_GET',
            '$_POST',
            '$_FILES',
            '$_COOKIE',
            '$_SESSION',
            '$_REQUEST',
            '$_ENV',
        ];
        for ($index = $opening + 1; $index < $closing; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_VARIABLE) {
                if (in_array($token[1], $forbiddenSuperglobals, true))
                    return null;
                $next = $this->nextSignificantToken($tokens, $index + 1);
                if ($next !== null && $tokens[$next] === '(')
                    return null;
            }
            if (is_array($token) && in_array($token[0], $forbiddenTokens, true))
                return null;
            if (!is_array($token) || !$this->isNameToken($token))
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null || $tokens[$next] !== '(')
                continue;
            $previous = $this->previousSignificantToken($tokens, $index - 1);
            if (
                $previous !== null
                && is_array($tokens[$previous])
                && (
                    $tokens[$previous][0] === T_FUNCTION
                    || $tokens[$previous][0] === T_NEW
                    || $tokens[$previous][0] === T_DOUBLE_COLON
                    || $tokens[$previous][0] === T_OBJECT_OPERATOR
                    || defined('T_NULLSAFE_OBJECT_OPERATOR')
                    && $tokens[$previous][0] === T_NULLSAFE_OBJECT_OPERATOR
                )
            )
                continue;
            $name = ltrim($token[1], '\\');
            $calls[$name] = ($calls[$name] ?? 0) + 1;
        }
        ksort($calls);

        return $calls;
    }

    /** @param array<string,string> $sources @return array<string,int> */
    private function operationalProviderCallsites(array $sources): array
    {
        $callsites = [];
        foreach ($this->operationalProviderReferenceInventory($sources) as $reference)
            $callsites[$reference['path']] = ($callsites[$reference['path']] ?? 0) + 1;
        ksort($callsites);

        return $callsites;
    }

    /** @param array<string,string> $sources @return list<array{path:string,target:string,receiver:string,method:string,kind:string}> */
    private function operationalProviderReferenceInventory(array $sources): array
    {
        $references = [];
        foreach ($sources as $path => $source) {
            try {
                $tokens = token_get_all($source, TOKEN_PARSE);
            } catch (\ParseError) {
                continue;
            }
            $namespace = $this->namespaceName($tokens);
            $aliases = $this->importAliases($tokens);
            $classRanges = $this->operationalProviderClassRanges($tokens, $namespace, $aliases);
            $assignments = $this->classStringAssignments($tokens);
            $dynamicAssignments = $this->classStringConcatenationVariables($tokens, $assignments);
            foreach ($tokens as $index => $token) {
                $isScopeReceiver =
                    is_array($token)
                    && (
                        $token[0] === T_STATIC
                        || $token[0] === T_STRING
                        && in_array(strtolower($token[1]), ['self', 'static'], true)
                    );
                if (!is_array($token) || !$this->isNameToken($token) && !$isScopeReceiver)
                    continue;
                $doubleColon = $this->nextSignificantToken($tokens, $index + 1);
                if (
                    $doubleColon === null
                    || !is_array($tokens[$doubleColon])
                    || $tokens[$doubleColon][0] !== T_DOUBLE_COLON
                )
                    continue;
                $method = $this->nextSignificantToken($tokens, $doubleColon + 1);
                $opening = $method === null ? null : $this->nextSignificantToken($tokens, $method + 1);
                if (
                    $method === null
                    || !is_array($tokens[$method])
                    || $tokens[$method][0] !== T_STRING
                    || $tokens[$method][1] !== 'catalog'
                    || $opening === null
                    || $tokens[$opening] !== '('
                )
                    continue;
                $scopeReceiver = strtolower($token[1]);
                $target = in_array($scopeReceiver, ['self', 'static'], true)
                    ? $this->operationalProviderScopeTarget($index, $classRanges)
                    : $this->resolveClassName($token, $namespace, $aliases);
                if ($target !== FrameworkOperationalAuditEventCatalog::class)
                    continue;
                $kind = 'short';
                if ($scopeReceiver === 'self')
                    $kind = 'self';
                elseif ($scopeReceiver === 'static')
                    $kind = 'static';
                elseif ($token[0] === T_NAME_FULLY_QUALIFIED)
                    $kind = 'fqcn';
                elseif (defined('T_NAME_QUALIFIED') && $token[0] === T_NAME_QUALIFIED)
                    $kind = 'qualified';
                elseif (defined('T_NAME_RELATIVE') && $token[0] === T_NAME_RELATIVE)
                    $kind = 'relative';
                elseif (array_key_exists($token[1], $aliases))
                    $kind = 'alias';
                elseif ($namespace === 'BlackOps\\Internal\\Audit\\Catalog')
                    $kind = 'same_namespace';
                $references[] = [
                    'path' => $path,
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => $token[1],
                    'method' => 'catalog',
                    'kind' => $kind,
                ];
            }
            foreach ($tokens as $index => $token) {
                if ($token !== '(')
                    continue;
                $receiver = $this->nextSignificantToken($tokens, $index + 1);
                if ($receiver === null || !is_array($tokens[$receiver]))
                    continue;
                $receiverToken = $tokens[$receiver];
                $isScopeReceiver =
                    $receiverToken[0] === T_STATIC
                    || $receiverToken[0] === T_STRING
                    && in_array(strtolower($receiverToken[1]), ['self', 'static'], true);
                if (!$this->isNameToken($receiverToken) && !$isScopeReceiver)
                    continue;
                $doubleColon = $this->nextSignificantToken($tokens, $receiver + 1);
                $method = $doubleColon === null ? null : $this->nextSignificantToken($tokens, $doubleColon + 1);
                $closing = $method === null ? null : $this->nextSignificantToken($tokens, $method + 1);
                if (
                    $doubleColon === null
                    || !is_array($tokens[$doubleColon])
                    || $tokens[$doubleColon][0] !== T_DOUBLE_COLON
                    || $method === null
                    || !is_array($tokens[$method])
                    || $tokens[$method][0] !== T_STRING
                    || $tokens[$method][1] !== 'catalog'
                    || $closing === null
                    || $tokens[$closing] !== ')'
                )
                    continue;
                $invocation = $this->nextSignificantToken($tokens, $closing + 1);
                while ($invocation !== null && $tokens[$invocation] === ')')
                    $invocation = $this->nextSignificantToken($tokens, $invocation + 1);
                if ($invocation === null || $tokens[$invocation] !== '(')
                    continue;
                $scopeReceiver = strtolower($receiverToken[1]);
                $target = in_array($scopeReceiver, ['self', 'static'], true)
                    ? $this->operationalProviderScopeTarget($receiver, $classRanges)
                    : $this->resolveClassName($receiverToken, $namespace, $aliases);
                if ($target !== FrameworkOperationalAuditEventCatalog::class)
                    continue;
                $references[] = [
                    'path' => $path,
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => $receiverToken[1],
                    'method' => 'catalog',
                    'kind' => 'dynamic',
                ];
            }
            foreach ($this->operationalProviderIndirectReferences(
                $tokens,
                $namespace,
                $aliases,
                $assignments,
                $dynamicAssignments,
            ) as $reference) {
                $references[] = [
                    'path' => $path,
                    'target' => FrameworkOperationalAuditEventCatalog::class,
                    'receiver' => $reference['receiver'],
                    'method' => $reference['method'],
                    'kind' => $reference['kind'],
                ];
            }
        }
        usort(
            $references,
            static fn(array $left, array $right): int => (
                [$left['path'], $left['kind'], $left['receiver'], $left['method']] <=> [
                    $right['path'],
                    $right['kind'],
                    $right['receiver'],
                    $right['method'],
                ]
            ),
        );

        return $references;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     * @param array<string,string> $aliases
     * @return list<array{opening:int,closing:int,target:?string}>
     */
    private function operationalProviderClassRanges(array $tokens, string $namespace, array $aliases): array
    {
        $ranges = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CLASS)
                continue;
            $previous = $this->previousSignificantToken($tokens, $index - 1);
            if ($previous !== null && is_array($tokens[$previous]) && $tokens[$previous][0] === T_DOUBLE_COLON)
                continue;
            $nameIndex = $this->nextSignificantToken($tokens, $index + 1);
            $target = null;
            if ($nameIndex !== null && is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_STRING)
                $target = $this->resolveClassName($tokens[$nameIndex], $namespace, $aliases);
            $opening = $nameIndex;
            while ($opening !== null && $tokens[$opening] !== '{')
                $opening = $this->nextSignificantToken($tokens, $opening + 1);
            if ($opening === null)
                continue;
            $closing = $this->matchingBrace($tokens, $opening);
            if ($closing === null)
                continue;
            $ranges[] = ['opening' => $opening, 'closing' => $closing, 'target' => $target];
        }

        return $ranges;
    }

    /**
     * @param list<array{opening:int,closing:int,target:?string}> $classRanges
     */
    private function operationalProviderScopeTarget(int $index, array $classRanges): ?string
    {
        $current = null;
        foreach ($classRanges as $range) {
            if ($index <= $range['opening'] || $index >= $range['closing'])
                continue;
            if ($current === null || $range['opening'] > $current['opening'])
                $current = $range;
        }

        return $current['target'] ?? null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     * @param array<string,string> $aliases
     * @param array<string,string|array{0:string}|null> $assignments
     * @param array<string,bool> $dynamicAssignments
     * @return list<array{receiver:string,method:string,kind:string}>
     */
    private function operationalProviderIndirectReferences(
        array $tokens,
        string $namespace,
        array $aliases,
        array $assignments,
        array $dynamicAssignments,
    ): array {
        $references = [];
        $sinks = [
            'class_exists',
            'interface_exists',
            'enum_exists',
            'is_a',
            'is_subclass_of',
        ];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !$this->isNameToken($token))
                continue;
            $name = ltrim($token[1], '\\');
            $opening = $this->nextSignificantToken($tokens, $index + 1);
            if ($opening === null || $tokens[$opening] !== '(')
                continue;
            $closing = $this->matchingParenthesis($tokens, $opening);
            if ($closing === null)
                continue;
            if (in_array($name, $sinks, true)) {
                $argument = $this->nextSignificantToken($tokens, $opening + 1);
                if (
                    $argument !== null
                    && $argument < $closing
                    && $this->operationalProviderClassStringArgument(
                        $tokens,
                        $argument,
                        $namespace,
                        $aliases,
                        $assignments,
                    )
                ) {
                    $references[] = [
                        'receiver' => $name,
                        'method' => $name,
                        'kind' => is_array($tokens[$argument])
                        && $tokens[$argument][0] === T_VARIABLE
                        && isset($dynamicAssignments[$tokens[$argument][1]])
                            ? 'dynamic'
                            : 'class_string',
                    ];
                }
            }
        }
        foreach ($tokens as $index => $token) {
            $opening = null;
            $closing = null;
            if ($token === '[') {
                $opening = $index;
                $closing = $this->matchingSquareBracket($tokens, $opening);
            } elseif (is_array($token) && $token[0] === T_ARRAY) {
                $opening = $this->nextSignificantToken($tokens, $index + 1);
                if ($opening === null || $tokens[$opening] !== '(')
                    continue;
                $closing = $this->matchingParenthesis($tokens, $opening);
            } else {
                continue;
            }
            if ($closing === null)
                continue;
            $first = $this->nextSignificantToken($tokens, $opening + 1);
            if ($first === null || $first >= $closing)
                continue;
            $comma = $this->nextSignificantToken($tokens, $first + 1);
            while ($comma !== null && $comma < $closing && $tokens[$comma] !== ',')
                $comma = $this->nextSignificantToken($tokens, $comma + 1);
            if ($comma === null || $comma >= $closing)
                continue;
            $second = $this->nextSignificantToken($tokens, $comma + 1);
            if (
                $second === null
                || $second >= $closing
                || !$this->operationalProviderClassStringArgument($tokens, $first, $namespace, $aliases, $assignments)
                || !$this->operationalCatalogMethodLiteral($tokens, $second, $assignments)
            )
                continue;
            $references[] = [
                'receiver' => 'callable-array',
                'method' => 'catalog',
                'kind' => is_array($tokens[$first])
                && $tokens[$first][0] === T_VARIABLE
                && isset($dynamicAssignments[$tokens[$first][1]])
                    ? 'dynamic'
                    : 'callable',
            ];
        }
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_NEW)
                continue;
            $classIndex = $this->nextSignificantToken($tokens, $index + 1);
            if ($classIndex === null || !is_array($tokens[$classIndex]))
                continue;
            if ($tokens[$classIndex][0] === T_VARIABLE) {
                $opening = $this->nextSignificantToken($tokens, $classIndex + 1);
                if (
                    $opening !== null
                    && $tokens[$opening] === '('
                    && $this->resolveClassStringVariable($tokens[$classIndex][1], $assignments)
                        === FrameworkOperationalAuditEventCatalog::class
                ) {
                    $references[] = [
                        'receiver' => $tokens[$classIndex][1],
                        'method' => '__construct',
                        'kind' => 'dynamic',
                    ];
                }
                continue;
            }
            if (!$this->isNameToken($tokens[$classIndex]))
                continue;
            $reflectionName = $this->resolveClassName($tokens[$classIndex], $namespace, $aliases);
            if (!in_array($reflectionName, ['ReflectionClass', 'ReflectionMethod'], true))
                continue;
            $opening = $this->nextSignificantToken($tokens, $classIndex + 1);
            if ($opening === null || $tokens[$opening] !== '(')
                continue;
            $argument = $this->nextSignificantToken($tokens, $opening + 1);
            if (
                $argument === null
                || !$this->operationalProviderClassStringArgument(
                    $tokens,
                    $argument,
                    $namespace,
                    $aliases,
                    $assignments,
                )
            )
                continue;
            $references[] = [
                'receiver' => $reflectionName,
                'method' => '__construct',
                'kind' => is_array($tokens[$argument])
                && $tokens[$argument][0] === T_VARIABLE
                && isset($dynamicAssignments[$tokens[$argument][1]])
                    ? 'dynamic'
                    : 'reflection',
            ];
        }
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE)
                continue;
            $doubleColon = $this->nextSignificantToken($tokens, $index + 1);
            $method = $doubleColon === null ? null : $this->nextSignificantToken($tokens, $doubleColon + 1);
            $opening = $method === null ? null : $this->nextSignificantToken($tokens, $method + 1);
            if (
                $doubleColon === null
                || !is_array($tokens[$doubleColon])
                || $tokens[$doubleColon][0] !== T_DOUBLE_COLON
                || $method === null
                || !is_array($tokens[$method])
                || $tokens[$method][0] !== T_STRING
                || $tokens[$method][1] !== 'catalog'
                || $opening === null
                || $tokens[$opening] !== '('
                || $this->resolveClassStringVariable($token[1], $assignments)
                    !== FrameworkOperationalAuditEventCatalog::class
            )
                continue;
            $references[] = [
                'receiver' => $token[1],
                'method' => 'catalog',
                'kind' => 'dynamic',
            ];
        }
        foreach ($tokens as $index => $token) {
            if ($token !== '(')
                continue;
            $receiver = $this->nextSignificantToken($tokens, $index + 1);
            $closing = $receiver === null ? null : $this->nextSignificantToken($tokens, $receiver + 1);
            $doubleColon = $closing === null ? null : $this->nextSignificantToken($tokens, $closing + 1);
            $method = $doubleColon === null ? null : $this->nextSignificantToken($tokens, $doubleColon + 1);
            $opening = $method === null ? null : $this->nextSignificantToken($tokens, $method + 1);
            if (
                $receiver === null
                || !is_array($tokens[$receiver])
                || $tokens[$receiver][0] !== T_VARIABLE
                || $closing === null
                || $tokens[$closing] !== ')'
                || $doubleColon === null
                || $tokens[$doubleColon] !== T_DOUBLE_COLON
                || $method === null
                || !is_array($tokens[$method])
                || $tokens[$method][0] !== T_STRING
                || $tokens[$method][1] !== 'catalog'
                || $opening === null
                || $tokens[$opening] !== '('
                || $this->resolveClassStringVariable($tokens[$receiver][1], $assignments)
                    !== FrameworkOperationalAuditEventCatalog::class
            )
                continue;
            $references[] = [
                'receiver' => $tokens[$receiver][1],
                'method' => 'catalog',
                'kind' => 'dynamic',
            ];
        }

        return $references;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array<string,string> $aliases @param array<string,string|array{0:string}|null> $assignments */
    private function operationalProviderClassStringArgument(
        array $tokens,
        int $index,
        string $namespace,
        array $aliases,
        array $assignments,
    ): bool {
        $value = $this->classStringValue($tokens, $index, $namespace, $aliases);
        if (is_array($value))
            $value = $this->resolveClassStringVariable($value[0], $assignments);
        if (!is_string($value))
            return false;

        return $this->normalizeClassString($value) === FrameworkOperationalAuditEventCatalog::class;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array<string,string|array{0:string}|null> $assignments */
    private function operationalCatalogMethodLiteral(array $tokens, int $index, array $assignments): bool
    {
        $token = $tokens[$index] ?? null;
        if (is_array($token) && $token[0] === T_VARIABLE) {
            $value = $this->resolveClassStringVariable($token[1], $assignments);
            return $value === 'catalog';
        }
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING)
            return false;

        return $this->decodeClassStringLiteral($token[1]) === 'catalog';
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function matchingSquareBracket(array $tokens, int $opening): ?int
    {
        $depth = 0;
        for ($index = $opening, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index] === '[')
                $depth++;
            elseif ($tokens[$index] === ']') {
                $depth--;
                if ($depth === 0)
                    return $index;
            }
        }

        return null;
    }

    private function exactOperationalProviderAssemblyReferenceShape(string $source): bool
    {
        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError) {
            return false;
        }
        if ($this->namespaceName($tokens) !== 'BlackOps\\Internal\\Audit\\Authoring')
            return false;
        $references = $this->operationalProviderReferenceInventory(['registry' => $source]);
        if (
            count($references) !== 1
            || $references[0]['kind'] !== 'alias'
            || $references[0]['receiver'] !== 'FrameworkOperationalAuditEventCatalog'
        )
            return false;
        if (!$this->exactOperationalProviderAssemblyDirectStatement($source))
            return false;

        $classIndex = null;
        $classOpening = null;
        foreach ($tokens as $index => $token) {
            if (
                !is_array($token)
                || $token[0] !== T_CLASS
                || ($nameIndex = $this->nextSignificantToken($tokens, $index + 1)) === null
                || !is_array($tokens[$nameIndex])
                || $tokens[$nameIndex][0] !== T_STRING
                || $tokens[$nameIndex][1] !== 'ApplicationAuditCatalogRegistry'
            )
                continue;
            if ($classIndex !== null)
                return false;
            $classIndex = $index;
            $classOpening = $this->nextSignificantToken($tokens, $nameIndex + 1);
            while ($classOpening !== null && $tokens[$classOpening] !== '{')
                $classOpening = $this->nextSignificantToken($tokens, $classOpening + 1);
        }
        if ($classIndex === null || $classOpening === null)
            return false;
        $classClosing = $this->matchingBrace($tokens, $classOpening);
        if ($classClosing === null)
            return false;

        $assembleOpening = null;
        $assembleClosing = null;
        $assembleCount = 0;
        for ($index = $classOpening + 1; $index < $classClosing; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || $token[0] !== T_FUNCTION)
                continue;
            $nameIndex = $this->nextSignificantToken($tokens, $index + 1);
            if ($nameIndex !== null && $tokens[$nameIndex] === '&')
                $nameIndex = $this->nextSignificantToken($tokens, $nameIndex + 1);
            if (
                $nameIndex === null
                || !is_array($tokens[$nameIndex])
                || $tokens[$nameIndex][0] !== T_STRING
                || $tokens[$nameIndex][1] !== 'assemble'
            )
                continue;
            $assembleCount++;
            $modifier = $this->previousSignificantToken($tokens, $index - 1);
            if ($modifier === null || !is_array($tokens[$modifier]) || $tokens[$modifier][0] !== T_PRIVATE)
                return false;
            $parameterOpening = $this->nextSignificantToken($tokens, $nameIndex + 1);
            if ($parameterOpening === null || $tokens[$parameterOpening] !== '(')
                return false;
            $parameterClosing = $this->matchingParenthesis($tokens, $parameterOpening);
            if ($parameterClosing === null)
                return false;
            $bodyOpening = $this->nextSignificantToken($tokens, $parameterClosing + 1);
            while ($bodyOpening !== null && $tokens[$bodyOpening] !== '{' && $tokens[$bodyOpening] !== ';')
                $bodyOpening = $this->nextSignificantToken($tokens, $bodyOpening + 1);
            if ($bodyOpening === null || $tokens[$bodyOpening] !== '{')
                return false;
            $bodyClosing = $this->matchingBrace($tokens, $bodyOpening);
            if ($bodyClosing === null)
                return false;
            $assembleOpening = $bodyOpening;
            $assembleClosing = $bodyClosing;
            $index = $bodyClosing;
        }
        if ($assembleCount !== 1 || $assembleOpening === null || $assembleClosing === null)
            return false;

        $namespace = $this->namespaceName($tokens);
        $aliases = $this->importAliases($tokens);
        $providerIndex = null;
        $providerOpening = null;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !$this->isNameToken($token))
                continue;
            $doubleColon = $this->nextSignificantToken($tokens, $index + 1);
            $method = $doubleColon === null ? null : $this->nextSignificantToken($tokens, $doubleColon + 1);
            $opening = $method === null ? null : $this->nextSignificantToken($tokens, $method + 1);
            if (
                $doubleColon === null
                || !is_array($tokens[$doubleColon])
                || $tokens[$doubleColon][0] !== T_DOUBLE_COLON
                || $method === null
                || !is_array($tokens[$method])
                || $tokens[$method][0] !== T_STRING
                || $tokens[$method][1] !== 'catalog'
                || $opening === null
                || $tokens[$opening] !== '('
                || $this->resolveClassName($token, $namespace, $aliases)
                    !== FrameworkOperationalAuditEventCatalog::class
            )
                continue;
            if ($providerIndex !== null)
                return false;
            $providerIndex = $index;
            $providerOpening = $opening;
        }
        if ($providerIndex === null || $providerOpening === null)
            return false;
        if ($providerIndex <= $assembleOpening || $providerIndex >= $assembleClosing)
            return false;

        $equals = $this->previousSignificantToken($tokens, $providerIndex - 1);
        $variable = $equals === null ? null : $this->previousSignificantToken($tokens, $equals - 1);
        if (
            $equals === null
            || $tokens[$equals] !== '='
            || $variable === null
            || !is_array($tokens[$variable])
            || $tokens[$variable][0] !== T_VARIABLE
            || $tokens[$variable][1] !== '$operational'
        )
            return false;
        $closing = $this->nextSignificantToken($tokens, $providerOpening + 1);
        if ($closing === null || $tokens[$closing] !== ')')
            return false;
        $semicolon = $this->nextSignificantToken($tokens, $closing + 1);

        return $semicolon !== null && $tokens[$semicolon] === ';';
    }

    private function exactOperationalProviderAssemblyDirectStatement(string $source): bool
    {
        try {
            $nodes = new ParserFactory()
                ->createForNewestSupportedVersion()
                ->parse($source);
            if (!is_array($nodes))
                return false;
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
            $nodes = $traverser->traverse($nodes);
        } catch (\Throwable) {
            return false;
        }

        $registryClass = null;
        foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
            if (
                !$class instanceof Class_
                || $class->name === null
                || $class->name->toString() !== 'ApplicationAuditCatalogRegistry'
            )
                continue;
            if ($registryClass !== null)
                return false;
            $registryClass = $class;
        }
        if (!$registryClass instanceof Class_)
            return false;

        $assembleMethods = [];
        foreach ($registryClass->stmts as $statement) {
            if (
                $statement instanceof ClassMethod
                && $statement->name instanceof Identifier
                && $statement->name->toString() === 'assemble'
            )
                $assembleMethods[] = $statement;
        }
        if (count($assembleMethods) !== 1 || !$assembleMethods[0]->isPrivate() || $assembleMethods[0]->stmts === null)
            return false;

        $directCalls = 0;
        foreach ($assembleMethods[0]->stmts as $statement) {
            if (!$statement instanceof Expression || !$statement->expr instanceof Assign)
                continue;
            $assignment = $statement->expr;
            if (!$assignment->var instanceof Variable || $assignment->var->name !== 'operational')
                continue;
            if (!$assignment->expr instanceof StaticCall)
                return false;
            $call = $assignment->expr;
            if (
                !$call->class instanceof Name
                || !$call->name instanceof Identifier
                || $call->name->toString() !== 'catalog'
                || $call->args !== []
            )
                return false;
            $resolvedName = $call->class->getAttribute('resolvedName');
            if (
                !$resolvedName instanceof Name
                || ltrim($resolvedName->toString(), '\\') !== FrameworkOperationalAuditEventCatalog::class
            )
                return false;
            $directCalls++;
        }

        return $directCalls === 1;
    }

    private function exactOperationalProviderReferenceShape(string $source): bool
    {
        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError) {
            return false;
        }
        if ($this->namespaceName($tokens) !== 'BlackOps\\Internal\\Audit\\Catalog')
            return false;
        $expectedImport = 'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog;';
        if (substr_count($source, $expectedImport) !== 0)
            return false;
        if (preg_match('/^use[ \\t]+[^;]*FrameworkOperationalAuditEventCatalog[^;]*;[ \\t]*$/m', $source) === 1)
            return false;
        $references = $this->operationalProviderReferenceInventory(['lookup' => $source]);
        if (count($references) !== 1 || $references[0]['kind'] !== 'same_namespace')
            return false;
        if ($this->dynamicInvocationCount($source) !== 0)
            return false;
        if (!$this->operationalProviderTrustedContextShape($source))
            return false;

        return $this->staticMethodCalls($source, FrameworkOperationalAuditEventCatalog::class, 'catalog') === 1;
    }

    private function operationalProviderTrustedContextShape(string $source): bool
    {
        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError) {
            return false;
        }
        $namespace = $this->namespaceName($tokens);
        $aliases = $this->importAliases($tokens);
        $target = null;
        $oldProvider = null;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !$this->isNameToken($token))
                continue;
            $doubleColon = $this->nextSignificantToken($tokens, $index + 1);
            $method = $doubleColon === null ? null : $this->nextSignificantToken($tokens, $doubleColon + 1);
            $opening = $method === null ? null : $this->nextSignificantToken($tokens, $method + 1);
            if (
                $doubleColon === null
                || !is_array($tokens[$doubleColon])
                || $tokens[$doubleColon][0] !== T_DOUBLE_COLON
                || $method === null
                || !is_array($tokens[$method])
                || $tokens[$method][0] !== T_STRING
                || $tokens[$method][1] !== 'catalog'
                || $opening === null
                || $tokens[$opening] !== '('
            )
                continue;
            $resolved = $this->resolveClassName($token, $namespace, $aliases);
            if ($resolved === FrameworkOperationalAuditEventCatalog::class)
                $target = $index;
            if ($resolved === FrameworkAuditEventCatalog::class)
                $oldProvider = $index;
        }
        if ($target === null || $oldProvider === null)
            return false;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_IF)
                continue;
            $opening = $this->nextSignificantToken($tokens, $index + 1);
            if ($opening === null || $tokens[$opening] !== '(')
                continue;
            $closing = $this->matchingParenthesis($tokens, $opening);
            if (
                $closing === null
                || $target <= $opening
                || $target >= $closing
                || $oldProvider <= $opening
                || $oldProvider >= $closing
            )
                continue;
            $hasOwnerGuard = false;
            for ($cursor = $opening + 1; $cursor < $closing; $cursor++) {
                if (!is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_IS_IDENTICAL)
                    continue;
                $owner = $this->previousSignificantToken($tokens, $cursor - 1);
                if (
                    $owner === null
                    || !is_array($tokens[$owner])
                    || $tokens[$owner][0] !== T_STRING
                    || $tokens[$owner][1] !== 'owner'
                )
                    continue;
                $ownerOperator = $this->previousSignificantToken($tokens, $owner - 1);
                $ownerObject = $ownerOperator === null
                    ? null
                    : $this->previousSignificantToken($tokens, $ownerOperator - 1);
                if (
                    $ownerOperator === null
                    || !is_array($tokens[$ownerOperator])
                    || $tokens[$ownerOperator][0] !== T_OBJECT_OPERATOR
                    || $ownerObject === null
                    || !is_array($tokens[$ownerObject])
                    || $tokens[$ownerObject][0] !== T_VARIABLE
                    || $tokens[$ownerObject][1] !== '$catalog'
                )
                    continue;
                $ownerClass = $this->nextSignificantToken($tokens, $cursor + 1);
                $ownerSeparator = $ownerClass === null ? null : $this->nextSignificantToken($tokens, $ownerClass + 1);
                $ownerValue = $ownerSeparator === null
                    ? null
                    : $this->nextSignificantToken($tokens, $ownerSeparator + 1);
                if (
                    $ownerClass === null
                    || !is_array($tokens[$ownerClass])
                    || !$this->isNameToken($tokens[$ownerClass])
                )
                    continue;
                $ownerClassName = $this->resolveClassName($tokens[$ownerClass], $namespace, $aliases);
                if (
                    $ownerClassName === AuditSourceOwner::class
                    && $ownerSeparator !== null
                    && is_array($tokens[$ownerSeparator])
                    && $tokens[$ownerSeparator][0] === T_DOUBLE_COLON
                    && $ownerValue !== null
                    && is_array($tokens[$ownerValue])
                    && $tokens[$ownerValue][0] === T_STRING
                    && $tokens[$ownerValue][1] === 'Framework'
                )
                    $hasOwnerGuard = true;
            }
            if (!$hasOwnerGuard)
                return false;
            if ($hasOwnerGuard)
                return true;
        }

        return false;
    }

    /** Remove comments and string literals while retaining executable tokens. */
    private function phpCodeWithoutCommentsAndStrings(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (
                is_array($token)
                && in_array(
                    $token[0],
                    [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE],
                    true,
                )
            )
                continue;
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /** @return array<string,int> */
    private function auditTimestampCallInventory(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $namespace = $this->namespaceName($tokens);
        $aliases = $this->importAliases($tokens);
        $inventory = [];
        $method = null;
        $methodBrace = null;
        $braceDepth = 0;
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_FUNCTION && $methodBrace === null) {
                $name = $this->nextSignificantToken($tokens, $index + 1);
                if ($name !== null && is_array($tokens[$name]) && $tokens[$name][0] === T_STRING) {
                    $method = $tokens[$name][1];
                }
                continue;
            }
            if ($token === '{') {
                ++$braceDepth;
                if ($method !== null && $methodBrace === null) {
                    $methodBrace = $braceDepth;
                }
                continue;
            }
            if ($token === '}') {
                if ($methodBrace === $braceDepth) {
                    $method = null;
                    $methodBrace = null;
                }
                --$braceDepth;
                continue;
            }
            if (
                $method === null
                || !is_array($token)
                || !$this->isNameToken($token)
                || $this->resolveClassName($token, $namespace, $aliases) !== AuditRecordTime::class
            ) {
                continue;
            }
            $separator = $this->nextSignificantToken($tokens, $index + 1);
            $call = $separator === null ? null : $this->nextSignificantToken($tokens, $separator + 1);
            $opening = $call === null ? null : $this->nextSignificantToken($tokens, $call + 1);
            if (
                $separator !== null
                && is_array($tokens[$separator])
                && $tokens[$separator][0] === T_DOUBLE_COLON
                && $call !== null
                && is_array($tokens[$call])
                && $tokens[$call][0] === T_STRING
                && strtolower($tokens[$call][1]) === 'asserttimestamp'
                && $opening !== null
                && $tokens[$opening] === '('
            ) {
                $inventory[$method] = ($inventory[$method] ?? 0) + 1;
            }
        }
        ksort($inventory);

        return $inventory;
    }

    /** @return list<string> */
    private function a2ProductTypes(): array
    {
        return [
            'AuditAttemptKind',
            'AuditSourceRuntime',
            'AuditRecordAttempt',
            'AuditRecordEvent',
            'AuditRecordAction',
            'AuditRecordActor',
            'AuditRecordResource',
            'AuditRecordOutcome',
            'AuditRecordPolicy',
            'AuditRecordSource',
            'AuditRecordCorrelation',
            'AuditRecordEvidence',
            'AuditRecordTime',
            'AuditRecordDraft',
            'AuditProjectionFailure',
            'AuditProjectionException',
            'AuditLogicalRecord',
            'ValidatedAuditLogicalRecord',
            'AuditRecordValidator',
            'AuditProjectionByteGuard',
            'ObservedAuditRecordProjector',
            'AuditRecordFinalizer',
            'AuditRecordCodec',
            'AuditLogicalRecordCandidate',
            'CanonicalAuditLogicalRecord',
            'AuditRecordStamp',
            'AuditPartitionIdDeriver',
            'AuditChainPosition',
            'AuditRecordIntegrity',
            'CompleteAuditRecord',
            'AuditProtectionPurpose',
            'AuditProtectionKeySource',
            'AuditProtectionFailure',
            'AuditProtectionException',
            'AuditAssociatedData',
            'AuditStoredRecord',
            'AuditIngressEnvelope',
            'AuditIngressEnvelopeCodec',
            'AuditProtectedIngress',
            'AuditProtectedBodyCodec',
        ];
    }

    /** @return array<string,list<string>> */
    private function o3A2AllowedReferences(): array
    {
        return [
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditFailure.php' => [],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditHookGuard.php' => [],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditRow.php' => ['AuditTypedReference'],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditSnapshot.php' => [
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditSourceRuntime',
                'AuditTypedReference',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditIdentity.php' => [
                'AuditSourceRuntime',
                'AuditTypedReference',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php' => ['AuditSourceRuntime'],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditProjection.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
                'AuditTypedReference',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditWriter.php' => [
                'AuditRecordCorrelation',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditHook.php' => ['AuditSourceRuntime'],
        ];
    }

    /** @return list<string> */
    private function o3A2ProductReferences(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        self::assertIsArray($nodes);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $nodes = $traverser->traverse($nodes);
        $targets = $this->o3A2ProductTargets();
        $references = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (!$name instanceof Name) {
                continue;
            }
            $resolved = $name->getAttribute('resolvedName');
            $resolvedName = $resolved instanceof Name ? $resolved->toString() : $name->toString();
            $resolvedName = ltrim($resolvedName, '\\');
            if (isset($targets[$resolvedName])) {
                $references[] = $targets[$resolvedName];
            }
        }
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @return array<string,string> */
    private function o3A2ProductTargets(): array
    {
        $targets = [];
        foreach ([
            'AuditRecordAction',
            'AuditRecordActor',
            'AuditRecordCorrelation',
            'AuditRecordDraft',
            'AuditRecordEvent',
            'AuditRecordEvidence',
            'AuditRecordOutcome',
            'AuditRecordPolicy',
            'AuditRecordResource',
            'AuditRecordSource',
            'AuditSourceRuntime',
            'AuditTypedReference',
        ] as $type) {
            $targets['BlackOps\\Core\\Audit\\' . $type] = $type;
        }

        return $targets;
    }

    /** @return list<string> */
    private function a2DeliveryViolations(
        string $path,
        string $source,
        string $deliveryDirectory,
        array $productTypes,
    ): array {
        $isDelivery = $path === $deliveryDirectory || str_starts_with($path, $deliveryDirectory . DIRECTORY_SEPARATOR);
        $relative = '';
        $marker = DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $markerPosition = strpos($path, $marker);
        if ($markerPosition !== false) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, $markerPosition + strlen($marker)));
        }
        $allowed = $this->a2C3AllowedProductReferences()[$relative] ?? [];
        $references = $this->a2ProductReferences($source, $productTypes);
        $violations = [];
        foreach ($references as $productType) {
            if ($isDelivery && $productType === 'AuditRecordTime') {
                continue;
            }
            if (!in_array($productType, $allowed, true)) {
                $violations[] = $productType;
            }
        }
        return $violations;
    }

    /** @return array<string,list<string>> */
    private function a2C3AllowedProductReferences(): array
    {
        return [
            'Audit/AuditOpaqueIdKeyProvider.php' => [],
            'Audit/AuditProtectionKeyProvider.php' => [],
            'Internal/Audit/Key/ApplicationAuditProtectionKeySource.php' => [
                'AuditPartitionIdDeriver',
                'AuditProtectionException',
                'AuditProtectionKeySource',
                'AuditProtectionPurpose',
            ],
            'Internal/Audit/Orchestration/AuditDeliveryCoordinator.php' => [],
            'Internal/Audit/Orchestration/AuditDeliveryException.php' => [],
            'Internal/Audit/Orchestration/AuditDeliveryFailure.php' => [],
            'Internal/Audit/Orchestration/AuditDeliveryPlan.php' => [],
            'Internal/Audit/Orchestration/AuditDeliveryRequest.php' => ['AuditRecordDraft'],
            'Internal/Audit/Orchestration/AuditDeliverySelector.php' => [
                'AuditRecordDraft',
                'AuditRecordValidator',
            ],
            'Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php' => [],
            'Internal/Audit/Orchestration/AuditProtectedReadGate.php' => ['AuditRecordDraft'],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditFailure.php' => [],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditHookGuard.php' => [],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditRow.php' => ['AuditTypedReference'],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditSnapshot.php' => [
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditSourceRuntime',
                'AuditTypedReference',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditIdentity.php' => [
                'AuditSourceRuntime',
                'AuditTypedReference',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php' => ['AuditSourceRuntime'],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditProjection.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
                'AuditTypedReference',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditWriter.php' => [
                'AuditRecordCorrelation',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/OperationalOutbox/GenericOutboxAuditHook.php' => ['AuditSourceRuntime'],
            'Internal/Audit/Replay/ObserverReplayAuditWriter.php' => ['AuditRecordCorrelation'],
            'Internal/Audit/Replay/ObserverReplayAuditWriterBinding.php' => [
                'AuditRecordActor',
                'AuditRecordCorrelation',
            ],
            'Internal/Audit/Replay/ObserverReplayAuditWriterRecord.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/Artifact/AuditArtifactShape.php' => ['AuditRecordTime'],
            'Internal/Audit/Artifact/AuditTrustSnapshotShape.php' => ['AuditRecordTime'],
            'Internal/Audit/Artifact/AuditTrustEntryRelation.php' => ['AuditRecordTime'],
            'Internal/Audit/Artifact/AuditTrustLookup.php' => ['AuditRecordTime'],
            'Internal/Audit/Read/AuditReadEventWriter.php' => ['AuditRecordDraft'],
            'Internal/Audit/Read/OperationReadAuditSession.php' => ['AuditRecordDraft'],
            'Internal/Audit/Read/OperationReadAuditSessionFactory.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/Retention/RetentionHoldAuditWriter.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/Retention/RetentionPurgeAuditWriter.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/Rotation/StorageRotationAuditWriter.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/Replay/ObserverReplayAuditWriter.php' => ['AuditRecordCorrelation'],
            'Internal/Audit/Authorization/AuthorizationAuditEvaluation.php' => [
                'AuditRecordPolicy',
            ],
            'Internal/Audit/Authorization/AuthorizationAuditEventFactory.php' => [
                'AuditRecordAction',
                'AuditRecordActor',
                'AuditRecordCorrelation',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditSourceRuntime',
            ],
            'Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => [
                'AuditRecordPolicy',
            ],
            'Internal/Audit/Read/OperationReadExecutionSource.php' => [],
            'Internal/Audit/Read/OperationReadNonceSource.php' => [],
            'Internal/Audit/Read/RandomOperationReadNonceSource.php' => [],
            'Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php' => [],
            'Internal/Diagnostics/OperationDiagnosticsAuthorizer.php' => [],
            'Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php' => [],
            'Internal/Application/ApplicationAuditConfiguration.php' => [],
            'Internal/Application/ApplicationAuditRuntime.php' => [],
            'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => [
                'AuditProtectionException',
            ],
            'Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => [
                'CanonicalAuditLogicalRecord',
            ],
            'Internal/Audit/Relay/AuditRelayPersistence.php' => [
                'CanonicalAuditLogicalRecord',
            ],
            'Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                'AuditAssociatedData',
                'AuditIngressEnvelopeCodec',
                'AuditPartitionIdDeriver',
                'AuditProtectedBodyCodec',
                'AuditRecordCodec',
                'AuditRecordFinalizer',
                'AuditRecordValidator',
            ],
            'Internal/Audit/Relay/AuditIngressCanonicalizer.php' => [
                'AuditLogicalRecordCandidate',
                'AuditProtectedBodyCodec',
                'AuditProtectionException',
                'AuditRecordValidator',
                'CanonicalAuditLogicalRecord',
            ],
        ];
    }

    /** @param list<string> $productTypes @return list<string> */
    private function a2ProductReferences(string $source, array $productTypes): array
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (
                is_array($token)
                && in_array(
                    $token[0],
                    [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE],
                    true,
                )
            ) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        $references = [];
        foreach ($productTypes as $productType) {
            if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($productType, '/') . '(?![A-Za-z0-9_])/', $code) === 1) {
                $references[] = $productType;
            }
        }
        $references = array_merge($references, $this->consumedClassStringReferences($source, $productTypes));
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @param list<string> $productTypes @return list<string> */
    private function consumedClassStringReferences(string $source, array $productTypes): array
    {
        $tokens = token_get_all($source);
        $assignments = $this->classStringAssignments($tokens);
        $sinks = ['class_exists', 'interface_exists', 'enum_exists', 'is_a', 'is_subclass_of'];
        $references = [];
        foreach ($tokens as $index => $token) {
            if (
                !is_array($token)
                || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)
                || !in_array(ltrim($token[1], '\\'), $sinks, true)
            ) {
                continue;
            }
            $opening = $this->nextSignificantToken($tokens, $index + 1);
            if ($opening === null || $tokens[$opening] !== '(') {
                continue;
            }
            $closing = $this->matchingParenthesis($tokens, $opening);
            if ($closing === null) {
                continue;
            }
            for ($argument = $opening + 1; $argument < $closing; $argument++) {
                $value = $this->classStringValue(
                    $tokens,
                    $argument,
                    $this->namespaceName($tokens),
                    $this->importAliases($tokens),
                );
                if (is_array($value)) {
                    $value = $this->resolveClassStringVariable($value[0], $assignments);
                }
                if (!is_string($value)) {
                    continue;
                }
                $normalized = $this->normalizeClassString($value);
                foreach ($this->a2ClassStringTargets() as $productType => $target) {
                    if (in_array($productType, $productTypes, true) && $normalized === $target) {
                        $references[] = $productType;
                    }
                }
            }
        }
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    /** @return array<string,class-string> */
    private function a2ClassStringTargets(): array
    {
        $targets = [];
        foreach ([
            'AuditAttemptKind',
            'AuditSourceRuntime',
            'AuditRecordAttempt',
            'AuditRecordEvent',
            'AuditRecordAction',
            'AuditRecordActor',
            'AuditRecordResource',
            'AuditRecordOutcome',
            'AuditRecordPolicy',
            'AuditRecordSource',
            'AuditRecordCorrelation',
            'AuditRecordEvidence',
            'AuditRecordTime',
            'AuditRecordDraft',
            'AuditProjectionFailure',
            'AuditProjectionException',
        ] as $type) {
            $targets[$type] = 'BlackOps\\Core\\Audit\\' . $type;
        }
        foreach ([
            'AuditLogicalRecord',
            'ValidatedAuditLogicalRecord',
            'AuditRecordValidator',
            'AuditRecordFinalizer',
            'AuditRecordCodec',
            'AuditLogicalRecordCandidate',
            'CanonicalAuditLogicalRecord',
            'AuditRecordStamp',
            'AuditPartitionIdDeriver',
            'AuditChainPosition',
            'AuditRecordIntegrity',
            'CompleteAuditRecord',
        ] as $type) {
            $targets[$type] = 'BlackOps\\Internal\\Audit\\Record\\' . $type;
        }
        foreach (['AuditProjectionByteGuard', 'ObservedAuditRecordProjector'] as $type) {
            $targets[$type] = 'BlackOps\\Internal\\Audit\\Projection\\' . $type;
        }
        foreach ([
            'AuditProtectionPurpose',
            'AuditProtectionKeySource',
            'AuditProtectionFailure',
            'AuditProtectionException',
            'AuditAssociatedData',
            'AuditStoredRecord',
            'AuditIngressEnvelope',
            'AuditIngressEnvelopeCodec',
            'AuditProtectedIngress',
            'AuditProtectedBodyCodec',
        ] as $type) {
            $targets[$type] = 'BlackOps\\Internal\\Audit\\Protection\\' . $type;
        }

        return $targets;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array<string,string|array{0:string}|null> */
    private function classStringAssignments(array $tokens): array
    {
        $assignments = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }
            $equals = $this->nextSignificantToken($tokens, $index + 1);
            if ($equals === null || $tokens[$equals] !== '=') {
                continue;
            }
            $valueIndex = $this->nextSignificantToken($tokens, $equals + 1);
            if ($valueIndex === null) {
                continue;
            }
            $assignments[$token[1]] = $this->classStringAssignmentValue(
                $tokens,
                $valueIndex,
                $this->namespaceName($tokens),
                $this->importAliases($tokens),
                $assignments,
            );
        }

        return $assignments;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array<string,string> $aliases @param array<string,string|array{0:string}|null> $assignments */
    private function classStringAssignmentValue(
        array $tokens,
        int $index,
        string $namespace,
        array $aliases,
        array $assignments,
    ): string|array|null {
        $end = $this->classStringStatementEnd($tokens, $index);
        if ($end === null)
            return $this->classStringValue($tokens, $index, $namespace, $aliases);
        $hasConcatenation = false;
        for ($cursor = $index; $cursor < $end; $cursor++) {
            if ($tokens[$cursor] === '.') {
                $hasConcatenation = true;
                break;
            }
        }
        if (!$hasConcatenation)
            return $this->classStringValue($tokens, $index, $namespace, $aliases);

        $pieces = [];
        $cursor = $index;
        while ($cursor !== null && $cursor < $end) {
            $piece = $this->classStringValue($tokens, $cursor, $namespace, $aliases, true);
            if (is_array($piece))
                $piece = $this->resolveClassStringVariable($piece[0], $assignments);
            if (!is_string($piece))
                return null;
            $pieces[] = $piece;
            $next = $this->classStringExpressionEnd($tokens, $cursor);
            if ($next === null || $next >= $end)
                break;
            if ($tokens[$next] !== '.')
                return null;
            $cursor = $this->nextSignificantToken($tokens, $next + 1);
        }
        if ($cursor === null || $cursor >= $end)
            return null;

        return implode('', $pieces);
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array<string,string|array{0:string}|null> $assignments @return array<string,bool> */
    private function classStringConcatenationVariables(array $tokens, array $assignments): array
    {
        $dynamic = [];
        $descriptors = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE)
                continue;
            $equals = $this->nextSignificantToken($tokens, $index + 1);
            if ($equals === null || $tokens[$equals] !== '=')
                continue;
            $value = $this->nextSignificantToken($tokens, $equals + 1);
            $end = $value === null ? null : $this->classStringStatementEnd($tokens, $value);
            if ($value === null || $end === null)
                continue;
            $hasConcat = false;
            for ($cursor = $value; $cursor < $end; $cursor++)
                if ($tokens[$cursor] === '.') {
                    $hasConcat = true;
                    break;
                }
            $descriptors[$token[1]] = [
                'value' => $value,
                'end' => $end,
                'concat' => $hasConcat,
            ];
        }
        do {
            $changed = false;
            foreach ($descriptors as $variable => $descriptor) {
                if (isset($dynamic[$variable]))
                    continue;
                if ($descriptor['concat']) {
                    $resolved = $this->resolveClassStringVariable($variable, $assignments);
                    if ($resolved === FrameworkOperationalAuditEventCatalog::class) {
                        $dynamic[$variable] = true;
                        $changed = true;
                    }
                    continue;
                }
                $value = $tokens[$descriptor['value']] ?? null;
                if (is_array($value) && $value[0] === T_VARIABLE && isset($dynamic[$value[1]])) {
                    $dynamic[$variable] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        return $dynamic;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function classStringStatementEnd(array $tokens, int $index): ?int
    {
        for ($cursor = $index, $count = count($tokens); $cursor < $count; $cursor++)
            if ($tokens[$cursor] === ';')
                return $cursor;

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function classStringExpressionEnd(array $tokens, int $index): ?int
    {
        $token = $tokens[$index] ?? null;
        if (
            is_array($token)
            && ($token[0] === T_START_HEREDOC || defined('T_START_NOWDOC') && $token[0] === T_START_NOWDOC)
        ) {
            for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
                $part = $tokens[$cursor];
                if (
                    is_array($part)
                    && ($part[0] === T_END_HEREDOC || defined('T_END_NOWDOC') && $part[0] === T_END_NOWDOC)
                )
                    return $this->nextSignificantToken($tokens, $cursor + 1);
            }
            return null;
        }
        if (is_array($token) && $this->isNameToken($token)) {
            $separator = $this->nextSignificantToken($tokens, $index + 1);
            if ($separator !== null && is_array($tokens[$separator]) && $tokens[$separator][0] === T_DOUBLE_COLON) {
                $keyword = $this->nextSignificantToken($tokens, $separator + 1);
                return $keyword === null ? null : $this->nextSignificantToken($tokens, $keyword + 1);
            }
        }

        return $this->nextSignificantToken($tokens, $index + 1);
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return string|array{0:string}|null */
    /** @param array<string,string> $aliases */
    private function classStringValue(
        array $tokens,
        int $index,
        string $namespace = '',
        array $aliases = [],
        bool $allowConcatenation = false,
    ): string|array|null {
        $token = $tokens[$index] ?? null;
        if (!is_array($token)) {
            return null;
        }
        $next = $this->nextSignificantToken($tokens, $index + 1);
        if (!$allowConcatenation && $next !== null && $tokens[$next] === '.')
            return null;
        if ($token[0] === T_VARIABLE) {
            return [$token[1]];
        }
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            return $this->decodeClassStringLiteral($token[1]);
        }
        if ($this->isNameToken($token)) {
            $doubleColon = $this->nextSignificantToken($tokens, $index + 1);
            $classKeyword = $doubleColon === null ? null : $this->nextSignificantToken($tokens, $doubleColon + 1);
            if (
                $doubleColon !== null
                && is_array($tokens[$doubleColon])
                && $tokens[$doubleColon][0] === T_DOUBLE_COLON
                && $classKeyword !== null
                && is_array($tokens[$classKeyword])
                && $tokens[$classKeyword][0] === T_STRING
                && $tokens[$classKeyword][1] === 'class'
            ) {
                return $this->resolveClassName($token, $namespace, $aliases);
            }
        }
        if ($token[0] === T_START_HEREDOC || defined('T_START_NOWDOC') && $token[0] === T_START_NOWDOC) {
            $value = '';
            for ($index++; $index < count($tokens); $index++) {
                $part = $tokens[$index];
                if (
                    is_array($part)
                    && ($part[0] === T_END_HEREDOC || defined('T_END_NOWDOC') && $part[0] === T_END_NOWDOC)
                ) {
                    break;
                }
                if (is_array($part) && $part[0] === T_ENCAPSED_AND_WHITESPACE) {
                    $value .= $part[1];
                }
            }

            return trim($value);
        }

        return null;
    }

    private function decodeClassStringLiteral(string $literal): string
    {
        $quote = $literal[0] ?? '';
        $value = substr($literal, 1, -1);
        if ($quote === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $value);
        }

        return str_replace(['\\\\', '\\"'], ['\\', '"'], $value);
    }

    /** @param array<string,string|array{0:string}|null> $assignments */
    private function resolveClassStringVariable(string $variable, array $assignments, int $depth = 0): ?string
    {
        if ($depth > 2 || !array_key_exists($variable, $assignments)) {
            return null;
        }
        $value = $assignments[$variable];
        if (is_array($value)) {
            return $this->resolveClassStringVariable($value[0], $assignments, $depth + 1);
        }

        return is_string($value) ? $value : null;
    }

    private function normalizeClassString(string $value): string
    {
        return trim($value, " \t\r\n\0\x0B\\");
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function matchingParenthesis(array $tokens, int $opening): ?int
    {
        $depth = 0;
        for ($index = $opening, $count = count($tokens); $index < $count; $index++) {
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

    /** @return array<string,array<string,int>> */
    private function protectedCallsites(string $sourceRoot): array
    {
        return $this->protectedCallsitesFromSources($this->sourceFiles($sourceRoot));
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function protectedCallsitesFromSources(array $sources): array
    {
        $counts = [
            'protectRecord' => [],
            'unprotectRecord' => [],
            'protectOutbox' => [],
            'unprotectOutbox' => [],
        ];
        foreach ($sources as $file => $source) {
            foreach ($counts as $method => &$files) {
                $count = $this->methodCalls($source)[$method] ?? 0;
                if ($count > 0)
                    $files[$file] = $count;
            }
            unset($files);
        }
        foreach ($counts as &$files)
            ksort($files);
        unset($files);

        return $counts;
    }

    /** @return array<string,string> */
    private function sourceFiles(string $sourceRoot): array
    {
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php')
                continue;
            $path = realpath($file->getPathname());
            self::assertNotFalse($path);
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources[$this->relativeSourcePath($sourceRoot, $path)] = $source;
        }
        ksort($sources);

        return $sources;
    }

    private function relativeSourcePath(string $sourceRoot, string $path): string
    {
        self::assertTrue($path === $sourceRoot || str_starts_with($path, $sourceRoot . DIRECTORY_SEPARATOR));

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($sourceRoot) + 1));
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function constructionCallsitesFromSources(array $sources): array
    {
        $counts = [
            'candidate' => [],
            'canonical' => [],
            'complete' => [],
            'stored' => [],
            'ingress' => [],
        ];
        foreach ($sources as $path => $source) {
            $calls = [
                'candidate' => $this->constructorCalls($source, AuditLogicalRecordCandidate::class),
                'canonical' => $this->constructorCalls($source, CanonicalAuditLogicalRecord::class),
                'complete' => $this->constructorCalls($source, CompleteAuditRecord::class),
                'stored' => $this->staticMethodCalls($source, AuditStoredRecord::class, 'fromArray'),
                'ingress' => $this->staticMethodCalls($source, AuditProtectedIngress::class, 'fromArray'),
            ];
            foreach ($calls as $name => $count) {
                if ($count > 0)
                    $counts[$name][$path] = $count;
            }
        }
        foreach ($counts as &$files)
            ksort($files);
        unset($files);

        return $counts;
    }

    private function constructorCalls(string $source, string $class): int
    {
        $tokens = token_get_all($source);
        $namespace = $this->namespaceName($tokens);
        $aliases = $this->importAliases($tokens);
        $count = 0;
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || $token[0] !== T_NEW)
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null)
                throw new \LogicException('Unsupported constructor form.');
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE)
                throw new \LogicException('Dynamic constructor form is unsupported.');
            if (!is_array($tokens[$next]) || !$this->isNameToken($tokens[$next]))
                continue;
            if (!$this->matchesClass($tokens[$next], $class, $namespace, $aliases))
                continue;
            $next = $this->nextSignificantToken($tokens, $next + 1);
            if ($next !== null && $tokens[$next] === '(')
                $count++;
        }

        return $count;
    }

    private function staticMethodCalls(string $source, string $class, string $method): int
    {
        $tokens = token_get_all($source);
        $namespace = $this->namespaceName($tokens);
        $aliases = $this->importAliases($tokens);
        $this->rejectDynamicStaticCalls($tokens, $class, $method, $namespace, $aliases);
        $count = 0;
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || !$this->matchesClass($token, $class, $namespace, $aliases))
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null || !is_array($tokens[$next]) || $tokens[$next][0] !== T_DOUBLE_COLON)
                continue;
            $next = $this->nextSignificantToken($tokens, $next + 1);
            if ($next === null)
                throw new \LogicException('Unsupported static-call form.');
            if ($tokens[$next] === '{') {
                $closing = $next;
                do {
                    $closing = $this->nextSignificantToken($tokens, $closing + 1);
                } while ($closing !== null && $tokens[$closing] !== '}');
                $opening = $closing === null ? null : $this->nextSignificantToken($tokens, $closing + 1);
                if ($opening !== null && $tokens[$opening] === '(')
                    throw new \LogicException('Dynamic static-call form is unsupported.');
                continue;
            }
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                $opening = $this->nextSignificantToken($tokens, $next + 1);
                if ($opening !== null && $tokens[$opening] === '(')
                    throw new \LogicException('Dynamic static-call form is unsupported.');
                continue;
            }
            if (!is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING)
                throw new \LogicException('Dynamic static-call form is unsupported.');
            if ($tokens[$next][1] !== $method)
                continue;
            $next = $this->nextSignificantToken($tokens, $next + 1);
            if ($next !== null && $tokens[$next] === '(')
                $count++;
        }

        return $count;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function namespaceName(array $tokens): string
    {
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_NAMESPACE)
                continue;
            $name = $this->nextSignificantToken($tokens, $index + 1);
            if ($name !== null && is_array($tokens[$name]) && $this->isNameToken($tokens[$name]))
                return trim($tokens[$name][1], '\\');
            return '';
        }

        return '';
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array<string,string> */
    private function importAliases(array $tokens): array
    {
        $aliases = [];
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || $token[0] !== T_USE)
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if (
                $next === null
                || $tokens[$next] === '('
                || is_array($tokens[$next]) && in_array($tokens[$next][0], [T_FUNCTION, T_CONST], true)
            )
                continue;
            $name = null;
            $alias = null;
            for ($part = $next; $part < $total; $part++) {
                $current = $tokens[$part];
                if ($current === ';' || $current === ',') {
                    if ($name !== null)
                        $aliases[$alias ?? $this->terminalClassName($name)] = trim($name, '\\');
                    $name = null;
                    $alias = null;
                    if ($current === ';')
                        break;
                    continue;
                }
                if (is_array($current) && $current[0] === T_AS) {
                    $part = $this->nextSignificantToken($tokens, $part + 1) ?? $part;
                    if ($part < $total && is_array($tokens[$part]) && $tokens[$part][0] === T_STRING)
                        $alias = $tokens[$part][1];
                    continue;
                }
                if ($name === null && is_array($current) && $this->isNameToken($current))
                    $name = $current[1];
            }
            $index = $part;
        }

        return $aliases;
    }

    /** @param array{0:int,1:string,2:int}|string $token @param array<string,string> $aliases */
    private function matchesClass(
        #[\SensitiveParameter]
        array|string $token,
        string $class,
        string $namespace,
        array $aliases,
    ): bool {
        if (!is_array($token) || !$this->isNameToken($token))
            return false;
        return $this->resolveClassName($token, $namespace, $aliases) === ltrim($class, '\\');
    }

    /** @param array{0:int,1:string,2:int} $token @param array<string,string> $aliases */
    private function resolveClassName(#[\SensitiveParameter] array $token, string $namespace, array $aliases): string
    {
        $name = $token[1];
        if ($token[0] === T_NAME_FULLY_QUALIFIED)
            return trim($name, '\\');
        if ($token[0] === T_NAME_RELATIVE)
            return $namespace === '' ? substr($name, 10) : $namespace . '\\' . substr($name, 10);
        $parts = explode('\\', $name);
        if (isset($aliases[$parts[0]])) {
            $parts[0] = $aliases[$parts[0]];
            return implode('\\', $parts);
        }

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @param array<string,string> $aliases */
    private function rejectDynamicStaticCalls(
        array $tokens,
        string $class,
        string $method,
        string $namespace,
        array $aliases,
    ): void {
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_VARIABLE)
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null || !is_array($tokens[$next]) || $tokens[$next][0] !== T_DOUBLE_COLON)
                continue;
            $methodToken = $this->nextSignificantToken($tokens, $next + 1);
            if ($methodToken === null)
                continue;
            if (is_string($tokens[$methodToken]) && strcmp($tokens[$methodToken], '{') === 0) {
                $closing = $methodToken;
                do {
                    $closing = $this->nextSignificantToken($tokens, $closing + 1);
                } while ($closing !== null && $tokens[$closing] !== '}');
                $opening = $closing === null ? null : $this->nextSignificantToken($tokens, $closing + 1);
                if ($opening !== null && $tokens[$opening] === '(')
                    throw new \LogicException('Dynamic static-call form is unsupported.');
                continue;
            }
            if (is_array($tokens[$methodToken]) && $tokens[$methodToken][0] === T_VARIABLE) {
                $opening = $this->nextSignificantToken($tokens, $methodToken + 1);
                if ($opening !== null && $tokens[$opening] === '(')
                    throw new \LogicException('Dynamic static-call form is unsupported.');
                continue;
            }
            if (!is_array($tokens[$methodToken]) || $tokens[$methodToken][0] !== T_STRING)
                continue;
            $opening = $this->nextSignificantToken($tokens, $methodToken + 1);
            if ($opening !== null && $tokens[$opening] === '(' && $tokens[$methodToken][1] === $method)
                throw new \LogicException('Dynamic static-call form is unsupported.');
        }
        for ($index = 0; $index < $total; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_DOUBLE_COLON)
                continue;
            $previous = $index === 0 ? null : $this->previousSignificantToken($tokens, $index - 1);
            if ($previous === null || !$this->matchesClass($tokens[$previous], $class, $namespace, $aliases))
                continue;
            $next = $this->nextSignificantToken($tokens, $index + 1);
            if ($next === null || $tokens[$next] !== '{')
                continue;
            do {
                $next = $this->nextSignificantToken($tokens, $next + 1);
            } while ($next !== null && $tokens[$next] !== '}');
            $opening = $next === null ? null : $this->nextSignificantToken($tokens, $next + 1);
            if ($opening !== null && $tokens[$opening] === '(')
                throw new \LogicException('Dynamic static-call form is unsupported.');
        }
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function previousSignificantToken(array $tokens, int $index): ?int
    {
        while ($index >= 0) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
                return $index;
            $index--;
        }

        return null;
    }

    private function isNameToken(#[\SensitiveParameter] array $token): bool
    {
        $types = [T_STRING];
        foreach (['T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE'] as $constant) {
            if (defined($constant))
                $types[] = constant($constant);
        }

        return in_array($token[0], $types, true);
    }

    private function terminalClassName(string $name): string
    {
        $parts = explode('\\', trim($name, '\\'));

        return $parts[count($parts) - 1];
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function nextSignificantToken(array $tokens, int $index): ?int
    {
        $total = count($tokens);
        while ($index < $total) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
                return $index;
            $index++;
        }

        return null;
    }

    /** @return array<string,int> */
    private function methodCalls(string $source): array
    {
        $counts = [];
        $operators = [T_OBJECT_OPERATOR];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR'))
            $operators[] = T_NULLSAFE_OBJECT_OPERATOR;
        $tokens = token_get_all($source);
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], $operators, true))
                continue;
            $next = $index + 1;
            while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE)
                $next++;
            if ($next >= $total || !is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING)
                continue;
            $method = $tokens[$next][1];
            $next++;
            while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE)
                $next++;
            if ($next < $total && $tokens[$next] === '(')
                $counts[$method] = ($counts[$method] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    private function dynamicInvocationCount(string $source): int
    {
        $count = 0;
        $operators = [T_OBJECT_OPERATOR, T_DOUBLE_COLON];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR'))
            $operators[] = T_NULLSAFE_OBJECT_OPERATOR;
        $tokens = token_get_all($source);
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], $operators, true))
                continue;
            $next = $index + 1;
            while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE)
                $next++;
            if ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                $next++;
                while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE)
                    $next++;
                if ($next < $total && $tokens[$next] === '(')
                    $count++;
                continue;
            }
            if ($next < $total && $tokens[$next] === '{') {
                $next++;
                while ($next < $total && $tokens[$next] !== '}')
                    $next++;
                $next++;
                while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE)
                    $next++;
                if ($next < $total && $tokens[$next] === '(')
                    $count++;
            }
        }

        return $count;
    }

    private function dynamicInvocationCountInTree(string $sourceRoot): int
    {
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php')
                continue;
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $count += $this->dynamicInvocationCount($source);
        }

        return $count;
    }

    /** @param list<string> $types @param list<bool> $nullable */
    private function assertConstructor(string $class, bool $public, array $types, array $nullable): void
    {
        $constructor = new \ReflectionClass($class)->getConstructor();
        self::assertNotNull($constructor, $class);
        self::assertSame($public, $constructor->isPublic(), $class);
        self::assertSame(!$public, $constructor->isPrivate(), $class);
        self::assertSame(
            $types,
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $constructor->getParameters()),
            $class,
        );
        self::assertSame(
            $nullable,
            array_map(
                static fn(\ReflectionParameter $parameter): bool => $parameter->allowsNull(),
                $constructor->getParameters(),
            ),
            $class,
        );
    }
}
