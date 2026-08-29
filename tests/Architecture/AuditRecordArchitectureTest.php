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
        $candidateCallsites = [];
        $canonicalCallsites = [];
        $completeCallsites = [];
        $storedHydrationCallsites = [];
        $ingressHydrationCallsites = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php')
                continue;
            $path = $file->getPathname();
            $source = file_get_contents($path);
            self::assertIsString($source);
            $candidateCount = substr_count($source, 'new AuditLogicalRecordCandidate(');
            $canonicalCount = substr_count($source, 'new CanonicalAuditLogicalRecord(');
            $completeCount = substr_count($source, 'new CompleteAuditRecord(');
            $storedCount = substr_count($source, 'AuditStoredRecord::fromArray(');
            $ingressCount = substr_count($source, 'AuditProtectedIngress::fromArray(');
            if ($candidateCount > 0)
                $candidateCallsites[basename($path)] = $candidateCount;
            if ($canonicalCount > 0)
                $canonicalCallsites[basename($path)] = $canonicalCount;
            if ($completeCount > 0)
                $completeCallsites[basename($path)] = $completeCount;
            if ($storedCount > 0)
                $storedHydrationCallsites[basename($path)] = $storedCount;
            if ($ingressCount > 0)
                $ingressHydrationCallsites[basename($path)] = $ingressCount;
        }
        self::assertSame(
            [
                'AuditRecordCodec.php' => 1,
                'AuditRecordFinalizer.php' => 1,
            ],
            $candidateCallsites,
        );
        self::assertSame(['AuditRecordFinalizer.php' => 1], $canonicalCallsites);
        self::assertSame(['AuditRecordCodec.php' => 2], $completeCallsites);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $storedHydrationCallsites);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $ingressHydrationCallsites);
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
            '->protectRecord(' => [],
            '->protectOutbox(' => [],
        ];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php')
                continue;
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            foreach ($counts as $needle => &$files)
                if (($count = substr_count($source, $needle)) > 0)
                    $files[basename($file->getPathname())] = $count;
            unset($files);
        }
        self::assertSame(['AuditIngressEnvelopeCodec.php' => 2], $counts['new AuditIngressEnvelope(']);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $counts['AuditStoredRecord::fromArray(']);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $counts['AuditProtectedIngress::fromArray(']);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $counts['->recordForWrite(']);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $counts['->recordForRead(']);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $counts['->outboxForWrite(']);
        self::assertSame(['AuditProtectedBodyCodec.php' => 1], $counts['->outboxForRead(']);
        self::assertSame([], $counts['->protectRecord(']);
        self::assertSame([], $counts['->protectOutbox(']);
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
