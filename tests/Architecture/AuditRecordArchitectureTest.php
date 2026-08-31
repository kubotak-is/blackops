<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditTypedReference;
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
use PHPUnit\Framework\TestCase;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
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
            if (str_contains($path, '/src/Internal/StorageProtection/'))
                $class = 'BlackOps\\Internal\\StorageProtection\\' . basename($path, '.php');
            self::assertSame([], new \ReflectionClass($class)->getAttributes(PublicApi::class), $class);
        }
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
            ],
            $callsites['candidate'],
        );
        self::assertSame(['Internal/Audit/Record/AuditRecordFinalizer.php' => 1], $callsites['canonical']);
        self::assertSame(['Internal/Audit/Record/AuditRecordCodec.php' => 2], $callsites['complete']);
        self::assertSame(
            [
                'Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1,
                'Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php' => 1,
            ],
            $callsites['stored'],
        );
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $callsites['ingress']);

        $sources = $this->sourceFiles($sourceRoot);
        $sources['External/AuditRecordCodec.php'] = '<?php namespace BlackOps\\Internal\\Audit\\Record; new AuditLogicalRecordCandidate([]);';
        $mutated = $this->constructionCallsitesFromSources($sources);
        self::assertSame(
            [
                'External/AuditRecordCodec.php' => 1,
                'Internal/Audit/Record/AuditRecordCodec.php' => 1,
                'Internal/Audit/Record/AuditRecordFinalizer.php' => 1,
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
            ['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1],
            $counts['AuditProtectedIngress::fromArray('],
        );
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->recordForWrite(']);
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->recordForRead(']);
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->outboxForWrite(']);
        self::assertSame(['Internal/Audit/Protection/AuditProtectedBodyCodec.php' => 1], $counts['->outboxForRead(']);
        $protected = $this->protectedCallsites($sourceRoot);
        self::assertSame(['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1], $protected['protectRecord']);
        self::assertSame(['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1], $protected['unprotectRecord']);
        self::assertSame([], $protected['protectOutbox']);
        self::assertSame([], $protected['unprotectOutbox']);
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
            realpath($sourceRoot . '/Internal/StorageProtection'),
        ]);
        $productTypes = [
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
            foreach ($productTypes as $productType)
                self::assertStringNotContainsString($productType, $source, $path);
            self::assertStringNotContainsString('observed_v1', $source, $path);
        }
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
