<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\AuditLookupResult;
use BlackOps\Internal\Audit\Storage\AuditSegmentCloseResult;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuditStoreArchitectureTest extends TestCase
{
    /** @return list<class-string> */
    private function types(): array
    {
        return [
            AuditStoreFailure::class,
            AuditStoreException::class,
            AuditAppendResult::class,
            AuditLookupResult::class,
            AuditSegmentCloseResult::class,
            PostgreSqlAuditSchema::class,
            PostgreSqlAuditStoredRecordHydrator::class,
            PostgreSqlAuditRecordStore::class,
        ];
    }

    public function testB2StorageDirectoryInventoryDependenciesAndCallablesAreExact(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../..');
        $directory = realpath(__DIR__ . '/../../src/Internal/Audit/Storage');
        self::assertNotFalse($sourceRoot);
        self::assertNotFalse($directory);
        $expectedTypes = [
            'src/Internal/Audit/Storage/AuditAppendResult.php' => [AuditAppendResult::class],
            'src/Internal/Audit/Storage/AuditLookupResult.php' => [AuditLookupResult::class],
            'src/Internal/Audit/Storage/AuditSegmentCloseResult.php' => [AuditSegmentCloseResult::class],
            'src/Internal/Audit/Storage/AuditStoreException.php' => [AuditStoreException::class],
            'src/Internal/Audit/Storage/AuditStoreFailure.php' => [AuditStoreFailure::class],
            'src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => [PostgreSqlAuditRecordStore::class],
            'src/Internal/Audit/Storage/PostgreSqlAuditSchema.php' => [PostgreSqlAuditSchema::class],
            'src/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php' => [
                PostgreSqlAuditStoredRecordHydrator::class,
            ],
        ];
        ksort($expectedTypes);
        $actualTypes = $this->storageInventory($sourceRoot, $directory);
        self::assertSame($expectedTypes, $actualTypes);

        [$fixtureRoot, $fixtureStorage] = $this->createStorageFixture($directory);
        try {
            self::assertSame($expectedTypes, $this->storageInventory($fixtureRoot, $fixtureStorage));

            $nestedDirectory = $fixtureStorage . '/Hidden';
            self::assertTrue(mkdir($nestedDirectory, 0700, true));
            $nestedPath = $nestedDirectory . '/ExtraStore.php';
            $written = file_put_contents($nestedPath, <<<'PHP'
                <?php

                namespace BlackOps\Internal\Audit\Storage\Hidden;

                final class ExtraStore
                {
                }

                enum ExtraState: string
                {
                    case Present = 'present';
                }
                PHP);
            self::assertIsInt($written);

            $mutatedTypes = $this->storageInventory($fixtureRoot, $fixtureStorage);
            $nestedRelativePath = 'src/Internal/Audit/Storage/Hidden/ExtraStore.php';
            self::assertSame(
                [
                    'BlackOps\\Internal\\Audit\\Storage\\Hidden\\ExtraStore',
                    'BlackOps\\Internal\\Audit\\Storage\\Hidden\\ExtraState',
                ],
                $mutatedTypes[$nestedRelativePath] ?? null,
            );
            self::assertNotSame(
                $expectedTypes,
                $mutatedTypes,
                'a nested extra type and every declaration in its file must be visible to the inventory',
            );
        } finally {
            $this->removeTemporaryTree($fixtureRoot);
        }

        $expectedDependencies = [
            'AuditAppendResult.php' => [
                'BlackOps\\Internal\\Audit\\Protection\\AuditStoredRecord',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            'AuditLookupResult.php' => [
                'BlackOps\\Internal\\Audit\\Record\\CompleteAuditRecord',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            'AuditSegmentCloseResult.php' => [
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            'AuditStoreException.php' => [
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
            ],
            'AuditStoreFailure.php' => [],
            'PostgreSqlAuditRecordStore.php' => [
                'BlackOps\\Core\\Audit\\AuditDelivery',
                'BlackOps\\Core\\Audit\\AuditRecordDraft',
                'BlackOps\\Core\\Audit\\AuditRecordTime',
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException',
                'BlackOps\\Internal\\Audit\\Protection\\AuditStoredRecord',
                'BlackOps\\Internal\\Audit\\Record\\AuditChainPosition',
                'BlackOps\\Internal\\Audit\\Record\\AuditPartitionIdDeriver',
                'BlackOps\\Internal\\Audit\\Record\\AuditRecordCodec',
                'BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer',
                'BlackOps\\Internal\\Audit\\Record\\AuditRecordStamp',
                'BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator',
                'BlackOps\\Internal\\Audit\\Record\\CanonicalAuditLogicalRecord',
                'BlackOps\\Internal\\Audit\\Record\\CompleteAuditRecord',
                'BlackOps\\Internal\\Identifier\\Uuidv7Generator',
                'Doctrine\\DBAL\\Connection',
                'Doctrine\\DBAL\\ParameterType',
                'Psr\\Clock\\ClockInterface',
                'BlackOps\\Internal\\Audit\\Storage\\AuditAppendResult',
                'BlackOps\\Internal\\Audit\\Storage\\AuditLookupResult',
                'BlackOps\\Internal\\Audit\\Storage\\AuditSegmentCloseResult',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditSchema',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditStoredRecordHydrator',
                'DateTimeImmutable',
                'DateTimeZone',
                'SensitiveParameter',
                'Throwable',
            ],
            'PostgreSqlAuditSchema.php' => ['BlackOps\\Internal\\Migration\\PostgreSqlMigrationSchema'],
            'PostgreSqlAuditStoredRecordHydrator.php' => [
                'BlackOps\\Core\\Audit\\AuditRecordTime',
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Internal\\Audit\\Protection\\AuditStoredRecord',
                'BlackOps\\Internal\\Audit\\Record\\AuditPartitionIdDeriver',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlBytea',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
        ];
        foreach ($expectedDependencies as $file => $expected) {
            sort($expected);
            $source = file_get_contents($directory . '/' . $file);
            self::assertIsString($source);
            self::assertSame($expected, $this->semanticDependencies($source), $file);
        }

        $expectedConstructors = [
            AuditStoreFailure::class => [null, []],
            AuditStoreException::class => ['public', [AuditStoreFailure::class]],
            AuditAppendResult::class => ['public', [AuditStoredRecord::class, 'bool']],
            AuditLookupResult::class => ['private', ['bool', '?' . CompleteAuditRecord::class]],
            AuditSegmentCloseResult::class => [
                'public',
                [
                    'string',
                    'string',
                    'string',
                    'int',
                    'int',
                    '?string',
                    'string',
                    'string',
                    'string',
                    'bool',
                ],
            ],
            PostgreSqlAuditSchema::class => ['public', ['string']],
            PostgreSqlAuditStoredRecordHydrator::class => ['public', [AuditPartitionIdDeriver::class]],
            PostgreSqlAuditRecordStore::class => [
                'public',
                [
                    Connection::class,
                    PostgreSqlAuditSchema::class,
                    ClockInterface::class,
                    Uuidv7Generator::class,
                    AuditRecordValidator::class,
                    AuditRecordFinalizer::class,
                    AuditPartitionIdDeriver::class,
                    AuditRecordCodec::class,
                    AuditProtectedBodyCodec::class,
                    PostgreSqlAuditStoredRecordHydrator::class,
                ],
            ],
        ];
        foreach ($expectedConstructors as $type => [$visibility, $parameters]) {
            $constructor = new \ReflectionClass($type)->getConstructor();
            if ($visibility === null) {
                self::assertNull($constructor, $type);
                continue;
            }
            self::assertNotNull($constructor, $type);
            self::assertSame($visibility === 'public', $constructor->isPublic(), $type);
            self::assertSame($visibility === 'private', $constructor->isPrivate(), $type);
            self::assertSame($parameters, $this->parameterTypes($constructor), $type);
        }

        $expectedCallables = [
            AuditStoreFailure::class => ['cases', 'from', 'tryFrom'],
            AuditStoreException::class => ['__construct', 'code', 'failure'],
            AuditAppendResult::class => [
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'inserted',
                'record',
            ],
            AuditLookupResult::class => [
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'fromRecord',
                'isFound',
                'notFound',
                'record',
            ],
            AuditSegmentCloseResult::class => [
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'changed',
                'closeFencingToken',
                'closedAt',
                'closedSegmentId',
                'finalSequence',
                'firstSequence',
                'headHash',
                'partitionId',
                'predecessorRecordHash',
                'successorSegmentId',
            ],
            PostgreSqlAuditSchema::class => [
                '__construct',
                'partitionStateTable',
                'recordsTable',
                'schemaName',
                'segmentsTable',
                'statements',
            ],
            PostgreSqlAuditStoredRecordHydrator::class => ['__construct', 'hydrate'],
            PostgreSqlAuditRecordStore::class => [
                '__construct',
                'appendDurable',
                'appendRequired',
                'closeSegment',
                'find',
            ],
        ];
        foreach ($expectedCallables as $type => $expected) {
            $actual = $this->declaredPublicMethods(new \ReflectionClass($type));
            sort($expected);
            self::assertSame($expected, $actual, $type);
        }

        $expectedCallableMatrix = $this->expectedB2CallableMatrix();
        $actualCallableMatrix = [];
        foreach ($this->types() as $type) {
            $actualCallableMatrix[$type] = $this->declaredCallableMatrix($type);
        }
        ksort($expectedCallableMatrix);
        ksort($actualCallableMatrix);
        self::assertSame($expectedCallableMatrix, $actualCallableMatrix);
    }

    public function testB2SemanticDependenciesAndIndirectCallGuardsAreMutationResistant(): void
    {
        $synthetic = <<<'PHP'
            <?php

            namespace BlackOps\Synthetic;

            use Vendor\ImportedDependency as ImportedAlias;
            use Vendor\ImportedTrait as ImportedTraitAlias;

            #[\Vendor\AttributeDependency]
            final class DependencyFixture
            {
                use ImportedTraitAlias;
                use \Vendor\FullyQualifiedTrait;
                use namespace\RelativeTrait;

                private \Vendor\PropertyDependency $property;

                public function resolve(
                    ImportedAlias $parameter,
                    \Vendor\ParameterDependency $second,
                ): \Vendor\ReturnDependency {
                    $created = new \Vendor\ConstructedDependency();
                    if ($created instanceof namespace\InstanceofDependency) {
                        return \Vendor\StaticDependency::from($second);
                    }

                    return \Vendor\ReturnDependency::from($parameter);
                }
            }
            PHP;
        $dependencies = $this->semanticDependencies($synthetic);
        foreach ([
            'Vendor\\ImportedDependency',
            'Vendor\\AttributeDependency',
            'Vendor\\PropertyDependency',
            'Vendor\\ParameterDependency',
            'Vendor\\ReturnDependency',
            'Vendor\\ConstructedDependency',
            'BlackOps\\Synthetic\\InstanceofDependency',
            'Vendor\\StaticDependency',
            'Vendor\\ImportedTrait',
            'Vendor\\FullyQualifiedTrait',
            'BlackOps\\Synthetic\\RelativeTrait',
        ] as $dependency) {
            self::assertContains($dependency, $dependencies, $dependency);
        }

        $withoutImport = str_replace("use Vendor\\ImportedDependency as ImportedAlias;\n", '', $synthetic);
        self::assertContains(
            'BlackOps\\Synthetic\\ImportedAlias',
            $this->semanticDependencies($withoutImport),
            'an unresolved imported alias must resolve relative to the namespace',
        );

        $withoutProperty = str_replace(
            'private \\Vendor\\PropertyDependency $property;',
            'private string $property;',
            $synthetic,
        );
        self::assertNotSame(
            $dependencies,
            $this->semanticDependencies($withoutProperty),
            'an FQCN property dependency must be part of the semantic map',
        );

        $unusedImport = str_replace(
            "use Vendor\\ImportedDependency as ImportedAlias;\n",
            "use Vendor\\ImportedDependency as ImportedAlias;\nuse Vendor\\UnusedDependency;\n",
            $synthetic,
        );
        self::assertSame(
            $dependencies,
            $this->semanticDependencies($unusedImport),
            'an unused import must not become a semantic dependency',
        );

        $withoutImportedType = str_replace('ImportedAlias $parameter', 'string $parameter', $synthetic);
        self::assertNotSame(
            $dependencies,
            $this->semanticDependencies($withoutImportedType),
            'removing the imported type use must remove that dependency even when its import remains',
        );
        self::assertNotContains('Vendor\\ImportedDependency', $this->semanticDependencies($withoutImportedType));

        $withoutTraitUse = str_replace("use ImportedTraitAlias;\n", '', $synthetic, $traitUseCount);
        self::assertSame(1, $traitUseCount);
        $withoutTraitDependencies = $this->semanticDependencies($withoutTraitUse);
        self::assertNotContains(
            'Vendor\\ImportedTrait',
            $withoutTraitDependencies,
            'a retained namespace import must not become a trait dependency when its trait use is removed',
        );
        self::assertContains('Vendor\\FullyQualifiedTrait', $withoutTraitDependencies);
        self::assertContains('BlackOps\\Synthetic\\RelativeTrait', $withoutTraitDependencies);

        $withoutTraitImport = str_replace(
            "use Vendor\\ImportedTrait as ImportedTraitAlias;\n",
            '',
            $synthetic,
            $traitImportCount,
        );
        self::assertSame(1, $traitImportCount);
        self::assertContains(
            'BlackOps\\Synthetic\\ImportedTraitAlias',
            $this->semanticDependencies($withoutTraitImport),
            'a trait alias without its namespace import must resolve in the declaring namespace',
        );

        $mutatedTraitUse = str_replace(
            'use namespace\\RelativeTrait;',
            'use namespace\\MutatedTrait;',
            $synthetic,
            $mutatedTraitCount,
        );
        self::assertSame(1, $mutatedTraitCount);
        $mutatedTraitDependencies = $this->semanticDependencies($mutatedTraitUse);
        self::assertNotSame($dependencies, $mutatedTraitDependencies);
        self::assertContains('BlackOps\\Synthetic\\MutatedTrait', $mutatedTraitDependencies);
        self::assertNotContains('BlackOps\\Synthetic\\RelativeTrait', $mutatedTraitDependencies);

        $indirect = <<<'PHP'
            <?php

            $callable = [$dependency, 'resolve'];
            call_user_func($callable, $value);
            call_user_func_array($callable, [$value]);
            $reflection = new \ReflectionMethod($dependency, 'resolve');
            $reflection->invoke($dependency, $value);
            $dependency($value);
            PHP;
        $indirectMutations = [
            $indirect,
            str_replace("[\$dependency, 'resolve']", "array(\$dependency, 'resolve')", $indirect),
            $indirect . "\n\$dependency?->{\$method}(\$value);\n",
            $indirect . "\nClosure::fromCallable([\$dependency, 'resolve']);\n",
            $indirect . "\n[\\Vendor\\Dependency, 'resolve'];\n",
            $indirect . "\narray('\\Vendor\\Dependency', 'resolve');\n",
            $indirect . "\n[\\Vendor\\Dependency::class, 'resolve'];\n",
            $indirect . "\narray(\\Vendor\\Dependency::class, 'resolve');\n",
            $indirect . "\n\$variableMethod = [\$dependency, \$method];\n\$variableMethod(\$value);\n",
            $indirect . "\n\$variableMethod = array(\$dependency, \$method);\n\$variableMethod(\$value);\n",
        ];
        foreach ($indirectMutations as $mutation) {
            self::assertNotEmpty($this->indirectInvocationViolations($mutation));
        }
        foreach ([
            '<?php ($callable)($value);',
            '<?php ((($callable)))($value);',
        ] as $parenthesizedCall) {
            self::assertContains('variable-call', $this->indirectInvocationViolations($parenthesizedCall));
        }
        self::assertSame(
            [],
            $this->indirectInvocationViolations('<?php ($value + 1);'),
            'ordinary parenthesized grouping must not become a callable violation',
        );

        foreach ([
            "<?php [\\Vendor\\Dependency::class, 'resolve'];",
            "<?php array(\\Vendor\\Dependency::class, 'resolve');",
            "<?php ['\\Vendor\\Dependency', 'resolve'];",
            "<?php array('\\Vendor\\Dependency', 'resolve');",
        ] as $callableArray) {
            self::assertContains('callable-array', $this->indirectInvocationViolations($callableArray));
        }
        foreach ([
            '<?php $callable = [$dependency, $method]; $callable($value);',
            '<?php $callable = array($dependency, $method); $callable($value);',
        ] as $variableMethodSink) {
            self::assertContains('variable-call', $this->indirectInvocationViolations($variableMethodSink));
        }

        $storageRoot = realpath(__DIR__ . '/../../src/Internal/Audit/Storage');
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($storageRoot);
        self::assertNotFalse($sourceRoot);
        $sources = $this->sourceFiles($sourceRoot);
        $storagePrefix = $this->relativeSourcePath($sourceRoot, $storageRoot) . '/';
        self::assertSame([], $this->b2AwareIndirectInvocationViolationsFromSources($sources, $storagePrefix));

        $outsideCanary = <<<'PHP'
            <?php

            $class = 'BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore';
            $callable = [$class, 'find'];
            call_user_func($callable, $store);
            $reflection = new ReflectionClass($class);
            $reflection->getMethod('find')->invoke($store);
            PHP;
        $mutatedSources = $sources;
        $mutatedSources['External/B2ClassStringCanary.php'] = $outsideCanary;
        $canaryViolations = $this->b2AwareIndirectInvocationViolationsFromSources($mutatedSources, $storagePrefix);
        self::assertArrayHasKey('External/B2ClassStringCanary.php', $canaryViolations);
        foreach ([
            'class-string:PostgreSqlAuditRecordStore',
            'callable-array',
            'call_user_func',
            'ReflectionClass',
        ] as $violation) {
            self::assertContains($violation, $canaryViolations['External/B2ClassStringCanary.php']);
        }
        self::assertContains('External/B2ClassStringCanary.php:PostgreSqlAuditRecordStore', $this->outsideStorageReferencesFromSources(
            $mutatedSources,
            $storagePrefix,
        ));

        $nowdocCanary = <<<'PHP'
            <?php

            $class = <<<'B2_CLASS'
            BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore
            B2_CLASS;
            $callable = [$class, 'find'];
            call_user_func($callable, $store);
            $reflection = new ReflectionClass($class);
            $reflection->getMethod('find')->invoke($store);
            PHP;
        $heredocCanary = <<<'PHP'
            <?php

            $class = <<<B2_CLASS
            BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore
            B2_CLASS;
            $callable = [$class, 'find'];
            call_user_func($callable, $store);
            $reflection = new ReflectionClass($class);
            $reflection->getMethod('find')->invoke($store);
            PHP;
        $mutatedSources['External/B2NowdocClassStringCanary.php'] = $nowdocCanary;
        $mutatedSources['External/B2HeredocClassStringCanary.php'] = $heredocCanary;
        $canaryViolations = $this->b2AwareIndirectInvocationViolationsFromSources($mutatedSources, $storagePrefix);
        foreach (['External/B2NowdocClassStringCanary.php', 'External/B2HeredocClassStringCanary.php'] as $path) {
            self::assertArrayHasKey($path, $canaryViolations);
            foreach ([
                'class-string:PostgreSqlAuditRecordStore',
                'callable-array',
                'call_user_func',
                'ReflectionClass',
            ] as $violation) {
                self::assertContains($violation, $canaryViolations[$path]);
            }
            self::assertContains($path . ':PostgreSqlAuditRecordStore', $this->outsideStorageReferencesFromSources(
                $mutatedSources,
                $storagePrefix,
            ));
        }

        foreach ([
            "<?php // BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore",
            "<?php \$text = 'prefix:BlackOps\\\\Internal\\\\Audit\\\\Storage\\\\PostgreSqlAuditRecordStore';",
            <<<'PHP'
                <?php

                $text = <<<'B2_TEXT'
                prefix:BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore:suffix
                B2_TEXT;
                PHP,
        ] as $falsePositive) {
            self::assertSame([], $this->b2StorageCodeReferences($falsePositive));
            self::assertSame([], $this->b2StorageClassStringReferences($falsePositive));
        }
    }

    public function testB1ProtectedCallGraphRejectsIndirectCallsAcrossAllSource(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        $storageRoot = realpath(__DIR__ . '/../../src/Internal/Audit/Storage');
        self::assertNotFalse($sourceRoot);
        self::assertNotFalse($storageRoot);
        $sources = $this->sourceFiles($sourceRoot);

        $direct = $this->protectedDirectCallsitesFromSources($sources);
        self::assertSame(
            [
                'protectRecord' => ['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1],
                'unprotectRecord' => ['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php' => 1],
                'protectOutbox' => ['Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1],
                'unprotectOutbox' => ['Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php' => 1],
            ],
            $direct,
        );
        self::assertSame([], $this->protectedIndirectCallsitesFromSources($sources));

        $canary = <<<'PHP'
            <?php

            $callable = [$codec, 'protectRecord'];
            call_user_func($callable, $value);
            call_user_func_array([$codec, 'unprotectRecord'], [$value]);
            $reflection = new \ReflectionMethod($codec, 'protectOutbox');
            $reflection->invoke($codec, $value);
            $class = new \ReflectionClass($codec);
            $class->getMethod('unprotectOutbox')->invoke($codec, $value);
            $method = 'protectRecord';
            $codec->$method($value);
            $codec?->{'unprotectRecord'}($value);
            PHP;
        $mutatedSources = $sources;
        $mutatedSources['External/ProtectedCallCanary.php'] = $canary;
        $violations = $this->protectedIndirectCallsitesFromSources($mutatedSources);
        self::assertArrayHasKey('External/ProtectedCallCanary.php', $violations);
        foreach (['protectRecord', 'unprotectRecord', 'protectOutbox', 'unprotectOutbox'] as $method) {
            self::assertContains($method, $violations['External/ProtectedCallCanary.php']);
        }
        self::assertSame(
            $direct,
            $this->protectedDirectCallsitesFromSources($mutatedSources),
            'indirect outside-Storage calls must not change the authorized direct graph',
        );

        $storePath = $storageRoot . '/PostgreSqlAuditRecordStore.php';
        $storeSource = $sources['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php'];
        $dynamicStore = str_replace('->unprotectRecord(', '->{$method}(', $storeSource, $replacementCount);
        self::assertSame(1, $replacementCount);
        $mutatedSources['Internal/Audit/Storage/PostgreSqlAuditRecordStore.php'] = $dynamicStore;
        self::assertNotSame(
            $direct,
            $this->protectedDirectCallsitesFromSources($mutatedSources),
            'a protected direct call cannot be replaced by a dynamic call',
        );
        self::assertContains(
            'unprotectRecord',
            $this->protectedIndirectInvocationViolations($dynamicStore),
            $storePath,
        );

        $outboxPath = 'Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php';
        $outboxSource = $sources[$outboxPath];

        foreach (['protectOutbox', 'unprotectOutbox'] as $method) {
            $missing = $sources;
            $missing[$outboxPath] = str_replace(
                '->' . $method . '(',
                '->removedCall(',
                $outboxSource,
                $replacementCount,
            );
            self::assertSame(1, $replacementCount, 'missing ' . $method);
            self::assertNotSame(
                $direct,
                $this->protectedDirectCallsitesFromSources($missing),
                'removing the C2 caller must fail the exact direct graph: ' . $method,
            );

            $extra = $sources;
            $extra[$outboxPath] = $outboxSource . "\n\$codec->{$method}(\$value);\n";
            $extraDirect = $this->protectedDirectCallsitesFromSources($extra);
            self::assertNotSame($direct, $extraDirect, 'extra C2 caller: ' . $method);
            self::assertSame(2, $extraDirect[$method][$outboxPath] ?? null, 'extra C2 caller: ' . $method);

            $renamed = $sources;
            $renamed[$outboxPath] = str_replace(
                '->' . $method . '(',
                '->renamedProtectedCall(',
                $outboxSource,
                $replacementCount,
            );
            self::assertSame(1, $replacementCount, 'renamed ' . $method);
            self::assertNotSame($direct, $this->protectedDirectCallsitesFromSources($renamed), 'renamed ' . $method);

            $indirectOutbox = $sources;
            $indirectOutbox[$outboxPath] = str_replace(
                '->' . $method . '(',
                '->{$method}(',
                $outboxSource,
                $replacementCount,
            );
            self::assertSame(1, $replacementCount, 'indirect ' . $method);
            self::assertNotSame($direct, $this->protectedDirectCallsitesFromSources($indirectOutbox));
            self::assertContains(
                $method,
                $this->protectedIndirectInvocationViolations($indirectOutbox[$outboxPath]),
                'indirect ' . $method,
            );

            $outsideC2 = $sources;
            $outsideC2['External/ProtectedOutboxCaller.php'] = '<?php $codec->' . $method . '($value);';
            $outsideDirect = $this->protectedDirectCallsitesFromSources($outsideC2);
            self::assertNotSame($direct, $outsideDirect, 'outside-C2 caller: ' . $method);
            self::assertSame(1, $outsideDirect[$method]['External/ProtectedOutboxCaller.php'] ?? null);
        }
    }

    public function testB2PublicApiReflectionMatrixIsExactAndMutationResistant(): void
    {
        $expected = $this->expectedB2ApiMatrix();
        $actual = [];
        foreach ($this->types() as $type) {
            $actual[$type] = $this->publicApiMatrix($type);
        }
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);

        $attributeSource = $this->sourceForType(AuditAppendResult::class);
        $attributeMutation = str_replace(
            '#[\\SensitiveParameter] AuditStoredRecord $record',
            'AuditStoredRecord $record',
            $attributeSource,
            $attributeCount,
        );
        self::assertSame(1, $attributeCount);
        self::assertNotSame(
            $actual[AuditAppendResult::class],
            $this->publicApiMatrixFromSource($attributeMutation, AuditAppendResult::class),
            'removing SensitiveParameter from the declaration must fail the matrix',
        );

        $returnSource = $this->sourceForType(PostgreSqlAuditStoredRecordHydrator::class);
        $returnMutation = str_replace('): AuditStoredRecord', '): array', $returnSource, $returnCount);
        self::assertSame(1, $returnCount);
        self::assertNotSame(
            $actual[PostgreSqlAuditStoredRecordHydrator::class],
            $this->publicApiMatrixFromSource($returnMutation, PostgreSqlAuditStoredRecordHydrator::class),
            'changing a declared return type must fail the matrix',
        );

        $defaultSource = $this->sourceForType(PostgreSqlAuditSchema::class);
        $defaultMutation = str_replace(
            'string $schema = \'blackops\'',
            'string $schema = \'other_schema\'',
            $defaultSource,
            $defaultCount,
        );
        self::assertSame(1, $defaultCount);
        self::assertNotSame(
            $actual[PostgreSqlAuditSchema::class],
            $this->publicApiMatrixFromSource($defaultMutation, PostgreSqlAuditSchema::class),
            'changing a declared default value must fail the matrix',
        );

        $parentSource = $this->sourceForType(AuditStoreException::class);
        $parentMutation = str_replace('extends RuntimeException', 'extends \\Exception', $parentSource, $parentCount);
        self::assertSame(1, $parentCount);
        self::assertNotSame(
            $actual[AuditStoreException::class],
            $this->publicApiMatrixFromSource($parentMutation, AuditStoreException::class),
            'changing the effective parent must fail the matrix',
        );

        $modifierSource = $this->sourceForType(AuditAppendResult::class);
        $modifierMutation = str_replace(
            'final readonly class AuditAppendResult',
            'final class AuditAppendResult',
            $modifierSource,
            $modifierCount,
        );
        self::assertSame(1, $modifierCount);
        self::assertNotSame(
            $actual[AuditAppendResult::class],
            $this->publicApiMatrixFromSource($modifierMutation, AuditAppendResult::class),
            'changing a class modifier must fail the matrix',
        );

        $finalSource = $this->sourceForType(AuditLookupResult::class);
        $finalMutation = str_replace(
            'final readonly class AuditLookupResult',
            'readonly class AuditLookupResult',
            $finalSource,
            $finalCount,
        );
        self::assertSame(1, $finalCount);
        self::assertNotSame(
            $actual[AuditLookupResult::class],
            $this->publicApiMatrixFromSource($finalMutation, AuditLookupResult::class),
            'changing final on a class must fail the matrix',
        );

        $storeType = PostgreSqlAuditRecordStore::class;
        $storeSource = $this->sourceForType($storeType);
        $storeCallables = $this->declaredCallableMatrix($storeType);
        $privateAttributeMutations = [
            'readCloseState' => [
                'private function readCloseState(#[\\SensitiveParameter] string $partitionId): array',
                'private function readCloseState(string $partitionId): array',
            ],
            'writeClose' => [
                <<<'PHP'
                    private function writeClose(
                            #[\SensitiveParameter]
                            array $state,
                    PHP,
                <<<'PHP'
                    private function writeClose(
                            array $state,
                    PHP,
            ],
            'encodeBytes' => [
                'private function encodeBytes(#[\\SensitiveParameter] string $value): string',
                'private function encodeBytes(string $value): string',
            ],
        ];
        foreach ($privateAttributeMutations as $methodName => [$needle, $replacement]) {
            $mutatedSource = str_replace($needle, $replacement, $storeSource, $count);
            self::assertSame(1, $count, $methodName);
            self::assertNotSame(
                $storeCallables,
                $this->declaredCallableMatrixFromSource($mutatedSource, $storeType),
                "removing SensitiveParameter from {$methodName} must fail the complete callable matrix",
            );
        }
    }

    public function testB2DeclaredPropertyInventoryIsExactAndMutationResistant(): void
    {
        $expected = $this->expectedB2PropertyMatrix();
        $actual = [];
        foreach ($this->types() as $type) {
            $actual[$type] = $this->declaredPropertyMatrix($type);
        }
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);

        $resultSource = $this->sourceForType(AuditAppendResult::class);
        $publicProperty = str_replace(
            'private \\SensitiveParameterValue $snapshot;',
            'public \\SensitiveParameterValue $snapshot;',
            $resultSource,
            $propertyCount,
        );
        self::assertSame(1, $propertyCount);
        self::assertNotSame(
            $actual[AuditAppendResult::class],
            $this->declaredPropertyMatrixFromSource($publicProperty, AuditAppendResult::class),
            'a private result snapshot must not become public',
        );

        $exceptionSource = $this->sourceForType(AuditStoreException::class);
        $publicPromotedProperty = str_replace(
            'private AuditStoreFailure $storeFailure',
            'public AuditStoreFailure $storeFailure',
            $exceptionSource,
            $promotionCount,
        );
        self::assertSame(1, $promotionCount);
        self::assertNotSame(
            $actual[AuditStoreException::class],
            $this->declaredPropertyMatrixFromSource($publicPromotedProperty, AuditStoreException::class),
            'a private promoted exception property must not become public',
        );

        $storeSource = $this->sourceForType(PostgreSqlAuditRecordStore::class);
        $storePublicPromotion = str_replace(
            'private Connection $connection',
            'public Connection $connection',
            $storeSource,
            $storePromotionCount,
        );
        self::assertSame(1, $storePromotionCount);
        self::assertNotSame(
            $actual[PostgreSqlAuditRecordStore::class],
            $this->declaredPropertyMatrixFromSource($storePublicPromotion, PostgreSqlAuditRecordStore::class),
            'a private promoted store dependency must not become public',
        );

        $storeUnpromoted = str_replace(
            'private Connection $connection',
            'Connection $connection',
            $storeSource,
            $unpromotionCount,
        );
        self::assertSame(1, $unpromotionCount);
        self::assertNotSame(
            $actual[PostgreSqlAuditRecordStore::class],
            $this->declaredPropertyMatrixFromSource($storeUnpromoted, PostgreSqlAuditRecordStore::class),
            'removing promotion must remove the declared store property',
        );
    }

    public function testB2TypesRemainInternalAndHaveNoPublicApiMarker(): void
    {
        foreach ($this->types() as $type) {
            $reflection = new \ReflectionClass($type);
            self::assertStringStartsWith('BlackOps\\Internal\\Audit\\Storage', $reflection->getName());
            self::assertSame([], $reflection->getAttributes(PublicApi::class), $type);
        }

        foreach ([
            AuditAppendResult::class,
            AuditLookupResult::class,
            AuditSegmentCloseResult::class,
            PostgreSqlAuditSchema::class,
            PostgreSqlAuditStoredRecordHydrator::class,
        ] as $type) {
            $reflection = new \ReflectionClass($type);
            self::assertTrue($reflection->isFinal(), $type);
            self::assertTrue($reflection->isReadOnly(), $type);
        }
        self::assertTrue(new \ReflectionClass(AuditStoreException::class)->isFinal());
        self::assertTrue(new \ReflectionClass(PostgreSqlAuditRecordStore::class)->isFinal());
    }

    public function testStoreConstructorAndCallableSurfaceAreExact(): void
    {
        $store = new \ReflectionClass(PostgreSqlAuditRecordStore::class);
        $constructor = $store->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        self::assertSame(
            [
                Connection::class,
                PostgreSqlAuditSchema::class,
                ClockInterface::class,
                Uuidv7Generator::class,
                AuditRecordValidator::class,
                AuditRecordFinalizer::class,
                AuditPartitionIdDeriver::class,
                AuditRecordCodec::class,
                AuditProtectedBodyCodec::class,
                PostgreSqlAuditStoredRecordHydrator::class,
            ],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $constructor->getParameters()),
        );

        $expected = [
            '__construct',
            'appendDurable',
            'appendRequired',
            'closeSegment',
            'find',
        ];
        $actual = [];
        foreach ($store->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === PostgreSqlAuditRecordStore::class) {
                $actual[] = $method->getName();
            }
        }
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);

        $methods = [
            'appendRequired' => [AuditRecordDraft::class, AuditAppendResult::class],
            'appendDurable' => [CanonicalAuditLogicalRecord::class, AuditAppendResult::class],
            'find' => ['string', AuditTypedReference::class, 'string', AuditLookupResult::class],
            'closeSegment' => ['string', 'string', AuditSegmentCloseResult::class],
        ];
        foreach ($methods as $name => $shape) {
            $method = $store->getMethod($name);
            self::assertSame(array_pop($shape), $method->getReturnType()?->getName(), $name);
            self::assertSame(
                $shape,
                array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                    ->getType()
                    ?->getName(), $method->getParameters()),
                $name,
            );
            foreach ($method->getParameters() as $parameter) {
                self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class), $name);
            }
        }
    }

    public function testHydratorAndSchemaSurfacesAreClosed(): void
    {
        $hydrator = new \ReflectionClass(PostgreSqlAuditStoredRecordHydrator::class);
        self::assertSame(['__construct', 'hydrate'], $this->declaredPublicMethods($hydrator));
        $hydrate = $hydrator->getMethod('hydrate');
        self::assertSame('array', $hydrate->getParameters()[0]->getType()?->getName());
        self::assertCount(1, $hydrate->getParameters()[0]->getAttributes(\SensitiveParameter::class));

        $schema = new \ReflectionClass(PostgreSqlAuditSchema::class);
        self::assertSame(
            ['__construct', 'partitionStateTable', 'recordsTable', 'schemaName', 'segmentsTable', 'statements'],
            $this->declaredPublicMethods($schema),
        );
        self::assertSame(
            ['string'],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $schema->getConstructor()?->getParameters() ?? []),
        );
    }

    /** @return array<string,array<string,mixed>> */
    private function expectedB2ApiMatrix(): array
    {
        return [
            AuditStoreFailure::class => [
                'kind' => 'enum',
                'parent' => null,
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => false],
                'backingType' => 'string',
                'cases' => [
                    'ResourceUnavailable' => 'resource_unavailable',
                    'IdempotencyConflict' => 'idempotency_conflict',
                    'SegmentConflict' => 'segment_conflict',
                    'StorageUnavailable' => 'storage_unavailable',
                    'IntegrityInvalid' => 'integrity_invalid',
                ],
                'constructor' => null,
                'methods' => [
                    'cases' => $this->apiMethod('public', true, 'array', false, []),
                    'from' => $this->apiMethod('public', true, 'static', false, [$this->apiParameter(
                        'value',
                        'int|string',
                    )]),
                    'tryFrom' => $this->apiMethod('public', true, 'static', true, [$this->apiParameter(
                        'value',
                        'int|string',
                    )]),
                ],
            ],
            AuditStoreException::class => [
                'kind' => 'class',
                'parent' => 'RuntimeException',
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => false],
                'backingType' => null,
                'cases' => [],
                'constructor' => $this->apiMethod('public', false, null, false, [$this->apiParameter(
                    'storeFailure',
                    AuditStoreFailure::class,
                )]),
                'methods' => [
                    'code' => $this->apiMethod('public', false, 'string', false, []),
                    'failure' => $this->apiMethod('public', false, AuditStoreFailure::class, false, []),
                ],
            ],
            AuditAppendResult::class => [
                'kind' => 'class',
                'parent' => null,
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => true],
                'backingType' => null,
                'cases' => [],
                'constructor' => $this->apiMethod('public', false, null, false, [
                    $this->apiParameter('record', AuditStoredRecord::class, false, 1),
                    $this->apiParameter('inserted', 'bool'),
                ]),
                'methods' => [
                    '__debugInfo' => $this->apiMethod('public', false, 'array', false, []),
                    '__serialize' => $this->apiMethod('public', false, 'array', false, []),
                    '__unserialize' => $this->apiMethod('public', false, 'void', false, [$this->apiParameter(
                        'data',
                        'array',
                    )]),
                    'inserted' => $this->apiMethod('public', false, 'bool', false, []),
                    'record' => $this->apiMethod('public', false, AuditStoredRecord::class, false, []),
                ],
            ],
            AuditLookupResult::class => [
                'kind' => 'class',
                'parent' => null,
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => true],
                'backingType' => null,
                'cases' => [],
                'constructor' => $this->apiMethod('private', false, null, false, [
                    $this->apiParameter('found', 'bool'),
                    $this->apiParameter('record', CompleteAuditRecord::class, true, 1),
                ]),
                'methods' => [
                    '__debugInfo' => $this->apiMethod('public', false, 'array', false, []),
                    '__serialize' => $this->apiMethod('public', false, 'array', false, []),
                    '__unserialize' => $this->apiMethod('public', false, 'void', false, [$this->apiParameter(
                        'data',
                        'array',
                    )]),
                    'fromRecord' => $this->apiMethod('public', true, 'self', false, [$this->apiParameter(
                        'record',
                        CompleteAuditRecord::class,
                        false,
                        1,
                    )]),
                    'isFound' => $this->apiMethod('public', false, 'bool', false, []),
                    'notFound' => $this->apiMethod('public', true, 'self', false, []),
                    'record' => $this->apiMethod('public', false, CompleteAuditRecord::class, true, []),
                ],
            ],
            AuditSegmentCloseResult::class => [
                'kind' => 'class',
                'parent' => null,
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => true],
                'backingType' => null,
                'cases' => [],
                'constructor' => $this->apiMethod('public', false, null, false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('closedSegmentId', 'string', false, 1),
                    $this->apiParameter('successorSegmentId', 'string', false, 1),
                    $this->apiParameter('firstSequence', 'int'),
                    $this->apiParameter('finalSequence', 'int'),
                    $this->apiParameter('predecessorRecordHash', 'string', true, 1),
                    $this->apiParameter('headHash', 'string', false, 1),
                    $this->apiParameter('closeFencingToken', 'string', false, 1),
                    $this->apiParameter('closedAt', 'string'),
                    $this->apiParameter('changed', 'bool'),
                ]),
                'methods' => [
                    '__debugInfo' => $this->apiMethod('public', false, 'array', false, []),
                    '__serialize' => $this->apiMethod('public', false, 'array', false, []),
                    '__unserialize' => $this->apiMethod('public', false, 'void', false, [$this->apiParameter(
                        'data',
                        'array',
                    )]),
                    'changed' => $this->apiMethod('public', false, 'bool', false, []),
                    'closeFencingToken' => $this->apiMethod('public', false, 'string', false, []),
                    'closedAt' => $this->apiMethod('public', false, 'string', false, []),
                    'closedSegmentId' => $this->apiMethod('public', false, 'string', false, []),
                    'finalSequence' => $this->apiMethod('public', false, 'int', false, []),
                    'firstSequence' => $this->apiMethod('public', false, 'int', false, []),
                    'headHash' => $this->apiMethod('public', false, 'string', false, []),
                    'partitionId' => $this->apiMethod('public', false, 'string', false, []),
                    'predecessorRecordHash' => $this->apiMethod('public', false, 'string', true, []),
                    'successorSegmentId' => $this->apiMethod('public', false, 'string', false, []),
                ],
            ],
            PostgreSqlAuditSchema::class => [
                'kind' => 'class',
                'parent' => null,
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => true],
                'backingType' => null,
                'cases' => [],
                'constructor' => $this->apiMethod('public', false, null, false, [$this->apiParameter(
                    'schema',
                    'string',
                    false,
                    0,
                    true,
                    'blackops',
                )]),
                'methods' => [
                    'partitionStateTable' => $this->apiMethod('public', false, 'string', false, []),
                    'recordsTable' => $this->apiMethod('public', false, 'string', false, []),
                    'schemaName' => $this->apiMethod('public', false, 'string', false, []),
                    'segmentsTable' => $this->apiMethod('public', false, 'string', false, []),
                    'statements' => $this->apiMethod('public', false, 'array', false, []),
                ],
            ],
            PostgreSqlAuditStoredRecordHydrator::class => [
                'kind' => 'class',
                'parent' => null,
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => true],
                'backingType' => null,
                'cases' => [],
                'constructor' => $this->apiMethod('public', false, null, false, [$this->apiParameter(
                    'partitions',
                    AuditPartitionIdDeriver::class,
                )]),
                'methods' => [
                    'hydrate' => $this->apiMethod(
                        'public',
                        false,
                        AuditStoredRecord::class,
                        false,
                        [$this->apiParameter('row', 'array', false, 1)],
                    ),
                ],
            ],
            PostgreSqlAuditRecordStore::class => [
                'kind' => 'class',
                'parent' => null,
                'modifiers' => ['abstract' => false, 'final' => true, 'readonly' => false],
                'backingType' => null,
                'cases' => [],
                'constructor' => $this->apiMethod('public', false, null, false, [
                    $this->apiParameter('connection', Connection::class),
                    $this->apiParameter('schema', PostgreSqlAuditSchema::class),
                    $this->apiParameter('clock', ClockInterface::class),
                    $this->apiParameter('uuids', Uuidv7Generator::class),
                    $this->apiParameter('validator', AuditRecordValidator::class),
                    $this->apiParameter('finalizer', AuditRecordFinalizer::class),
                    $this->apiParameter('partitions', AuditPartitionIdDeriver::class),
                    $this->apiParameter('records', AuditRecordCodec::class),
                    $this->apiParameter('protection', AuditProtectedBodyCodec::class),
                    $this->apiParameter('hydrator', PostgreSqlAuditStoredRecordHydrator::class),
                ]),
                'methods' => [
                    'appendDurable' => $this->apiMethod(
                        'public',
                        false,
                        AuditAppendResult::class,
                        false,
                        [$this->apiParameter('logical', CanonicalAuditLogicalRecord::class, false, 1)],
                    ),
                    'appendRequired' => $this->apiMethod(
                        'public',
                        false,
                        AuditAppendResult::class,
                        false,
                        [$this->apiParameter('draft', AuditRecordDraft::class, false, 1)],
                    ),
                    'closeSegment' => $this->apiMethod('public', false, AuditSegmentCloseResult::class, false, [
                        $this->apiParameter('partitionId', 'string', false, 1),
                        $this->apiParameter('expectedSegmentId', 'string', false, 1),
                    ]),
                    'find' => $this->apiMethod('public', false, AuditLookupResult::class, false, [
                        $this->apiParameter('recordId', 'string', false, 1),
                        $this->apiParameter('expectedTenant', AuditTypedReference::class, true, 1),
                        $this->apiParameter('expectedPartitionId', 'string', false, 1),
                    ]),
                ],
            ],
        ];
    }

    /** @return array<string,array{constructor:array<string,mixed>|null,methods:array<string,array<string,mixed>>}> */
    private function expectedB2CallableMatrix(): array
    {
        $api = $this->expectedB2ApiMatrix();
        $privateMethods = [
            AuditAppendResult::class => [
                'value' => $this->apiMethod('private', false, 'mixed', true, [
                    $this->apiParameter('key', 'string'),
                ]),
            ],
            AuditLookupResult::class => [
                'value' => $this->apiMethod('private', false, 'mixed', true, [
                    $this->apiParameter('key', 'string'),
                ]),
            ],
            AuditSegmentCloseResult::class => [
                'stringValue' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('key', 'string'),
                ]),
                'intValue' => $this->apiMethod('private', false, 'int', false, [
                    $this->apiParameter('key', 'string'),
                ]),
                'value' => $this->apiMethod('private', false, 'mixed', true, [
                    $this->apiParameter('key', 'string'),
                ]),
            ],
            AuditStoreException::class => [],
            AuditStoreFailure::class => [],
            PostgreSqlAuditSchema::class => [
                'segmentsStatement' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('table', 'string'),
                ]),
                'partitionStateStatement' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('table', 'string'),
                    $this->apiParameter('segments', 'string'),
                ]),
                'recordsStatement' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('table', 'string'),
                    $this->apiParameter('segments', 'string'),
                ]),
            ],
            PostgreSqlAuditStoredRecordHydrator::class => [
                'integer' => $this->apiMethod('private', true, 'int', false, [
                    $this->apiParameter('value', 'mixed', true),
                    $this->apiParameter('minimum', 'int'),
                    $this->apiParameter('maximum', 'int'),
                ]),
                'string' => $this->apiMethod('private', true, 'string', false, [
                    $this->apiParameter('value', 'mixed', true),
                ]),
                'nullableString' => $this->apiMethod('private', true, 'string', true, [
                    $this->apiParameter('value', 'mixed', true),
                ]),
                'tenant' => $this->apiMethod('private', true, AuditTypedReference::class, true, [
                    $this->apiParameter('type', 'mixed', true),
                    $this->apiParameter('id', 'mixed', true),
                ]),
            ],
            PostgreSqlAuditRecordStore::class => [
                'validateRequiredDraft' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('draft', AuditRecordDraft::class, false, 1),
                ]),
                'validateDurableLogical' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('logical', CanonicalAuditLogicalRecord::class, false, 1),
                ]),
                'durableTenant' => $this->apiMethod('private', false, AuditTypedReference::class, true, [
                    $this->apiParameter('snapshot', 'array', false, 1),
                ]),
                'lookupRecordIdentity' => $this->apiMethod('private', false, 'array', true, [
                    $this->apiParameter('recordId', 'string', false, 1),
                ]),
                'lookupSameRecord' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('recordId', 'string', false, 1),
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'verifiedStored' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('row', 'array', false, 1),
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'sealComplete' => $this->apiMethod('private', false, AuditStoredRecord::class, false, [
                    $this->apiParameter('complete', CompleteAuditRecord::class, false, 1),
                ]),
                'partitionForTenant' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                ]),
                'enterTransaction' => $this->apiMethod('private', false, 'bool', false, []),
                'lockPartition' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'lockRecord' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('recordId', 'string', false, 1),
                ]),
                'readState' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                    $this->apiParameter('allowPersistedTenant', 'bool', false, 0, true, false),
                ]),
                'readCloseState' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'readMissingState' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'stateValues' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('row', 'array', false, 1),
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                    $this->apiParameter('allowPersistedTenant', 'bool', false, 0, true, false),
                ]),
                'readAppendBoundary' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('state', 'array', false, 1),
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                ]),
                'readSegment' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('segmentId', 'string', false, 1),
                    $this->apiParameter('missingIsConflict', 'bool', false, 0, true, false),
                ]),
                'assertOpenSegment' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('segment', 'array', false, 1),
                ]),
                'readBoundary' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('sequence', 'int', false, 1),
                ]),
                'readMinimum' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('segmentId', 'string', false, 1),
                ]),
                'assertCloseInput' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('expectedSegmentId', 'string', false, 1),
                ]),
                'closedSegmentResult' => $this->apiMethod('private', false, AuditSegmentCloseResult::class, false, [
                    $this->apiParameter('state', 'array', false, 1),
                    $this->apiParameter('segment', 'array', false, 1),
                    $this->apiParameter('current', 'array', false, 1),
                ]),
                'assertCurrentSegmentRows' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('state', 'array', false, 1),
                    $this->apiParameter('segment', 'array', false, 1),
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'assertClosedRows' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('segment', 'array', false, 1),
                ]),
                'closeAt' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('openedAt', 'string', false, 1),
                    $this->apiParameter('lastAssignedAt', 'string', false, 1),
                ]),
                'closeToken' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('partitionId', 'string', false, 1),
                    $this->apiParameter('closedSegmentId', 'string', false, 1),
                    $this->apiParameter('finalSequence', 'int'),
                    $this->apiParameter('headHash', 'string', false, 1),
                    $this->apiParameter('successorSegmentId', 'string', false, 1),
                    $this->apiParameter('closedAt', 'string', false, 1),
                ]),
                'encodeString' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('value', 'string', false, 1),
                ]),
                'encodeBytes' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('value', 'string', false, 1),
                ]),
                'encodeInteger' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('value', 'int', false, 1),
                ]),
                'writeClose' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('state', 'array', false, 1),
                    $this->apiParameter('segment', 'array', false, 1),
                    $this->apiParameter('successorSegmentId', 'string', false, 1),
                    $this->apiParameter('closedAt', 'string', false, 1),
                    $this->apiParameter('token', 'string', false, 1),
                ]),
                'closeSql' => $this->apiMethod('private', false, 'string', false, []),
                'assertAppendCapacity' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('state', 'array', false, 1),
                    $this->apiParameter('boundary', 'array', false, 1),
                ]),
                'canonicalNow' => $this->apiMethod('private', false, 'string', false, []),
                'assignedAt' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('state', 'array', false, 1),
                    $this->apiParameter('canonicalNow', 'string', false, 1),
                ]),
                'date' => $this->apiMethod('private', false, 'DateTimeImmutable', false, [
                    $this->apiParameter('value', 'string', false, 1),
                ]),
                'generateUuid' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('time', 'DateTimeImmutable', false, 1),
                ]),
                'assertSegmentAvailable' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('segmentId', 'string', false, 1),
                ]),
                'assertRecordAvailable' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('recordId', 'string', false, 1),
                ]),
                'finalizeRequired' => $this->apiMethod('private', false, CanonicalAuditLogicalRecord::class, false, [
                    $this->apiParameter('draft', AuditRecordDraft::class, false, 1),
                    $this->apiParameter('stamp', AuditRecordStamp::class, false, 1),
                ]),
                'storedRow' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('stored', AuditStoredRecord::class, false, 1),
                    $this->apiParameter('complete', CompleteAuditRecord::class, false, 1),
                ]),
                'writeRequired' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('state', 'array', false, 1),
                    $this->apiParameter('boundary', 'array', false, 1),
                    $this->apiParameter('row', 'array', false, 1),
                    $this->apiParameter('assignedAt', 'string', false, 1),
                ]),
                'writeSql' => $this->apiMethod('private', false, 'string', false, []),
                'rollbackOwned' => $this->apiMethod('private', false, 'bool', false, []),
                'quarantine' => $this->apiMethod('private', false, 'void', false, []),
                'safeFailure' => $this->apiMethod('private', false, AuditStoreException::class, false, [
                    $this->apiParameter('failure', 'Throwable', false, 1),
                    $this->apiParameter(
                        'fallback',
                        AuditStoreFailure::class,
                        false,
                        0,
                        true,
                        AuditStoreFailure::StorageUnavailable,
                    ),
                ]),
                'assertPartitionValue' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('actual', 'string', false, 1),
                    $this->apiParameter('expected', 'string', false, 1),
                ]),
                'assertTenantColumns' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('type', 'string', true, 1),
                    $this->apiParameter('id', 'string', true, 1),
                    $this->apiParameter('expected', AuditTypedReference::class, true, 1),
                ]),
                'integer' => $this->apiMethod('private', false, 'int', false, [
                    $this->apiParameter('value', 'mixed', true, 1),
                    $this->apiParameter('minimum', 'int'),
                    $this->apiParameter('maximum', 'int'),
                ]),
                'string' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('value', 'mixed', true, 1),
                ]),
                'nullableString' => $this->apiMethod('private', false, 'string', true, [
                    $this->apiParameter('value', 'mixed', true, 1),
                ]),
                'nullableHash' => $this->apiMethod('private', false, 'string', true, [
                    $this->apiParameter('value', 'mixed', true, 1),
                ]),
                'hash' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('value', 'mixed', true, 1),
                ]),
                'uuid' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('value', 'mixed', true, 1),
                ]),
                'assertAutoCommit' => $this->apiMethod('private', false, 'void', false, []),
                'assertRecordId' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('recordId', 'string', false, 1),
                ]),
                'assertPartitionBinding' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'findSql' => $this->apiMethod('private', false, 'string', false, [
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                ]),
                'findParameters' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('recordId', 'string', false, 1),
                    $this->apiParameter('tenant', AuditTypedReference::class, true, 1),
                    $this->apiParameter('partitionId', 'string', false, 1),
                ]),
                'hydratorRow' => $this->apiMethod('private', false, 'array', false, [
                    $this->apiParameter('row', 'array', false, 1),
                ]),
                'assertQueryIndexes' => $this->apiMethod('private', false, 'void', false, [
                    $this->apiParameter('logical', 'array', false, 1),
                    $this->apiParameter('row', 'array', false, 1),
                ]),
            ],
        ];

        $expected = [];
        foreach ($this->types() as $type) {
            $methods = $api[$type]['methods'];
            foreach ($privateMethods[$type] as $name => $method) {
                $methods[$name] = $method;
            }
            ksort($methods);
            $expected[$type] = [
                'constructor' => $api[$type]['constructor'],
                'methods' => $methods,
            ];
        }
        ksort($expected);

        return $expected;
    }

    /** @return array<string,array<string,array<string,mixed>>> */
    private function expectedB2PropertyMatrix(): array
    {
        $property = static function (
            string $visibility,
            string $type,
            bool $static,
            bool $readonly,
            bool $promoted,
        ): array {
            return [
                'visibility' => $visibility,
                'type' => $type,
                'static' => $static,
                'readonly' => $readonly,
                'promotion' => $promoted,
            ];
        };

        $expected = [
            AuditAppendResult::class => [
                'snapshot' => $property('private', 'SensitiveParameterValue', false, true, false),
            ],
            AuditLookupResult::class => [
                'snapshot' => $property('private', 'SensitiveParameterValue', false, true, false),
            ],
            AuditSegmentCloseResult::class => [
                'snapshot' => $property('private', 'SensitiveParameterValue', false, true, false),
            ],
            AuditStoreException::class => [
                'storeFailure' => $property('private', AuditStoreFailure::class, false, false, true),
            ],
            AuditStoreFailure::class => [
                'name' => $property('public', 'string', false, true, false),
                'value' => $property('public', 'string', false, true, false),
            ],
            PostgreSqlAuditRecordStore::class => [
                'connection' => $property('private', Connection::class, false, false, true),
                'clock' => $property('private', ClockInterface::class, false, false, true),
                'finalizer' => $property('private', AuditRecordFinalizer::class, false, false, true),
                'hydrator' => $property('private', PostgreSqlAuditStoredRecordHydrator::class, false, false, true),
                'partitions' => $property('private', AuditPartitionIdDeriver::class, false, false, true),
                'protection' => $property('private', AuditProtectedBodyCodec::class, false, false, true),
                'quarantined' => $property('private', 'bool', false, false, false),
                'records' => $property('private', AuditRecordCodec::class, false, false, true),
                'schema' => $property('private', PostgreSqlAuditSchema::class, false, false, true),
                'uuids' => $property('private', Uuidv7Generator::class, false, false, true),
                'validator' => $property('private', AuditRecordValidator::class, false, false, true),
            ],
            PostgreSqlAuditSchema::class => [
                'schema' => $property(
                    'private',
                    'BlackOps\\Internal\\Migration\\PostgreSqlMigrationSchema',
                    false,
                    true,
                    false,
                ),
            ],
            PostgreSqlAuditStoredRecordHydrator::class => [
                'partitions' => $property('private', AuditPartitionIdDeriver::class, false, true, true),
            ],
        ];

        foreach ($expected as &$properties) {
            ksort($properties);
        }
        unset($properties);

        return $expected;
    }

    /** @return array<string,mixed> */
    private function apiMethod(
        string $visibility,
        bool $static,
        ?string $returnType,
        bool $returnNullable,
        array $parameters,
    ): array {
        return [
            'visibility' => $visibility,
            'static' => $static,
            'return' => [
                'type' => $returnType,
                'nullable' => $returnNullable,
            ],
            'parameters' => $parameters,
            'returnsReference' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function apiParameter(
        string $name,
        string $type,
        bool $nullable = false,
        int $sensitive = 0,
        bool $defaultAvailable = false,
        mixed $default = null,
    ): array {
        return [
            'name' => $name,
            'type' => $type,
            'nullable' => $nullable,
            'sensitive' => $sensitive,
            'variadic' => false,
            'byReference' => false,
            'default' => [
                'available' => $defaultAvailable,
                'value' => $default,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function publicApiMatrix(string $type): array
    {
        $reflection = new \ReflectionClass($type);
        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $type || $method->isConstructor()) {
                continue;
            }
            $methods[$method->getName()] = $this->reflectionCallable($method);
        }
        ksort($methods);

        $cases = [];
        $backingType = null;
        if ($reflection->isEnum()) {
            $enum = new \ReflectionEnum($type);
            $backingType = $enum->getBackingType()?->getName();
            foreach ($enum->getCases() as $case) {
                $cases[$case->getName()] = $case->getBackingValue();
            }
        }

        $parent = $reflection->getParentClass();

        return [
            'kind' => $reflection->isEnum() ? 'enum' : 'class',
            'parent' => $parent === false ? null : $parent->getName(),
            'modifiers' => [
                'abstract' => $reflection->isAbstract(),
                'final' => $reflection->isFinal(),
                'readonly' => $reflection->isReadOnly(),
            ],
            'backingType' => $backingType,
            'cases' => $cases,
            'constructor' => ($constructor = $reflection->getConstructor()) === null
                ? null
                : $this->reflectionCallable($constructor),
            'methods' => $methods,
        ];
    }

    /** @return array{constructor:array<string,mixed>|null,methods:array<string,array<string,mixed>>} */
    private function declaredCallableMatrix(string $type): array
    {
        $reflection = new \ReflectionClass($type);
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $type || $method->isConstructor()) {
                continue;
            }
            $methods[$method->getName()] = $this->reflectionCallable($method);
        }
        ksort($methods);

        return [
            'constructor' => ($constructor = $reflection->getConstructor()) === null
                ? null
                : $this->reflectionCallable($constructor),
            'methods' => $methods,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function declaredPropertyMatrix(string $type): array
    {
        $properties = [];
        $reflection = new \ReflectionClass($type);
        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $type) {
                continue;
            }
            $properties[$property->getName()] = [
                'visibility' => $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private'),
                'type' => ($propertyType = $property->getType()) === null
                    ? null
                    : $this->reflectionTypeName($propertyType),
                'static' => $property->isStatic(),
                'readonly' => $property->isReadOnly(),
                'promotion' => $property->isPromoted(),
            ];
        }
        ksort($properties);

        return $properties;
    }

    private function sourceForType(string $type): string
    {
        $path = new \ReflectionClass($type)->getFileName();
        self::assertIsString($path, $type);
        $source = file_get_contents($path);
        self::assertIsString($source, $type);

        return $source;
    }

    /** @return array<string,mixed> */
    private function publicApiMatrixFromSource(
        string $source,
        string $originalType,
        bool $includePrivate = false,
    ): array {
        static $sequence = 0;
        $sequence++;
        $syntheticName = '__AuditStoreArchitectureMatrix' . $sequence;
        $separator = strrpos($originalType, '\\');
        self::assertIsInt($separator);
        $namespace = substr($originalType, 0, $separator);
        $shortName = substr($originalType, $separator + 1);
        $pattern = '/\\b(class|enum|interface)\\s+' . preg_quote($shortName, '/') . '\\b/';
        $renamed = preg_replace_callback(
            $pattern,
            static fn(array $match): string => $match[1] . ' ' . $syntheticName,
            $source,
            1,
            $count,
        );
        self::assertIsString($renamed);
        self::assertSame(1, $count, $originalType);
        $code = preg_replace('/\\A<\\?php\\s*/', '', $renamed, 1);
        self::assertIsString($code);
        eval($code);

        return $includePrivate
            ? $this->declaredCallableMatrix($namespace . '\\' . $syntheticName)
            : $this->publicApiMatrix($namespace . '\\' . $syntheticName);
    }

    /** @return array{constructor:array<string,mixed>|null,methods:array<string,array<string,mixed>>} */
    private function declaredCallableMatrixFromSource(string $source, string $originalType): array
    {
        return $this->publicApiMatrixFromSource($source, $originalType, true);
    }

    /** @return array<string,array<string,mixed>> */
    private function declaredPropertyMatrixFromSource(string $source, string $originalType): array
    {
        static $sequence = 0;
        $sequence++;
        $syntheticName = '__AuditStoreArchitectureProperties' . $sequence;
        $separator = strrpos($originalType, '\\');
        self::assertIsInt($separator);
        $namespace = substr($originalType, 0, $separator);
        $shortName = substr($originalType, $separator + 1);
        $renamed = preg_replace_callback(
            '/\\b(class|enum|interface)\\s+' . preg_quote($shortName, '/') . '\\b/',
            static fn(array $match): string => $match[1] . ' ' . $syntheticName,
            $source,
            1,
            $count,
        );
        self::assertIsString($renamed);
        self::assertSame(1, $count, $originalType);
        $code = preg_replace('/\\A<\\?php\\s*/', '', $renamed, 1);
        self::assertIsString($code);
        eval($code);

        return $this->declaredPropertyMatrix($namespace . '\\' . $syntheticName);
    }

    /** @return array<string,mixed> */
    private function reflectionCallable(\ReflectionMethod $method): array
    {
        $return = $method->getReturnType();
        $returnType = $return === null ? null : $this->reflectionTypeName($return);
        if ($return instanceof \ReflectionNamedType && $return->getName() === $method->getDeclaringClass()->getName()) {
            $returnType = 'self';
        }

        return [
            'visibility' => $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private'),
            'static' => $method->isStatic(),
            'return' => [
                'type' => $returnType,
                'nullable' => $return?->allowsNull() ?? false,
            ],
            'parameters' => array_map(fn(\ReflectionParameter $parameter): array => $this->reflectionParameter(
                $parameter,
            ), $method->getParameters()),
            'returnsReference' => $method->returnsReference(),
        ];
    }

    /** @return array<string,mixed> */
    private function reflectionParameter(\ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        return [
            'name' => $parameter->getName(),
            'type' => $type === null ? null : $this->reflectionTypeName($type),
            'nullable' => $parameter->allowsNull(),
            'sensitive' => count($parameter->getAttributes(\SensitiveParameter::class)),
            'variadic' => $parameter->isVariadic(),
            'byReference' => $parameter->isPassedByReference(),
            'default' => [
                'available' => $parameter->isDefaultValueAvailable(),
                'value' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ],
        ];
    }

    private function reflectionTypeName(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }
        if ($type instanceof \ReflectionUnionType) {
            $names = array_map(static fn(\ReflectionType $member): string => $member instanceof \ReflectionNamedType
                ? $member->getName()
                : (string) $member, $type->getTypes());
            sort($names);

            return implode('|', $names);
        }
        if ($type instanceof \ReflectionIntersectionType) {
            $names = array_map(static fn(\ReflectionType $member): string => $member instanceof \ReflectionNamedType
                ? $member->getName()
                : (string) $member, $type->getTypes());
            sort($names);

            return implode('&', $names);
        }

        return (string) $type;
    }

    public function testStoreTransactionRejectionPrecedesTheOnlyHydratorCall(): void
    {
        $path = __DIR__ . '/../../src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php';
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, '->hydrate('));

        $durable = $this->methodSource($source, 'appendDurable');
        $find = $this->methodSource($source, 'find');
        $verified = $this->methodSource($source, 'verifiedStored');
        $this->assertOrderedSource($durable, [
            '$this->assertAutoCommit();',
            '$this->enterTransaction();',
            '$this->verifiedStored(',
        ]);
        $this->assertOrderedSource($find, [
            '$this->assertAutoCommit();',
            '$this->verifiedStored(',
        ]);
        self::assertSame(1, substr_count($verified, '->hydrate('));
        self::assertSame(1, substr_count($verified, '->hydratorRow('));
        $enter = $this->methodSource($source, 'enterTransaction');
        $this->assertOrderedSource($enter, [
            '$this->connection->isTransactionActive();',
            '$this->connection->getTransactionNestingLevel();',
            '$this->connection->beginTransaction();',
        ]);

        $graph = [
            'hydrate' => substr_count($source, '->hydrate('),
            'appendDurable' => [
                'autoCommit' => strpos($durable, '$this->assertAutoCommit();'),
                'transaction' => strpos($durable, '$this->enterTransaction();'),
                'hydrate' => strpos($durable, '$this->verifiedStored('),
            ],
            'find' => [
                'autoCommit' => strpos($find, '$this->assertAutoCommit();'),
                'hydrate' => strpos($find, '$this->verifiedStored('),
            ],
        ];
        self::assertIsInt($graph['appendDurable']['autoCommit']);
        self::assertIsInt($graph['appendDurable']['transaction']);
        self::assertIsInt($graph['appendDurable']['hydrate']);
        self::assertIsInt($graph['find']['autoCommit']);
        self::assertIsInt($graph['find']['hydrate']);

        $guardRemoved = str_replace('$this->assertAutoCommit();', '', $durable, $replacementCount);
        self::assertSame(1, $replacementCount);
        $mutatedSource = str_replace($durable, $guardRemoved, $source, $sourceCount);
        self::assertSame(1, $sourceCount);
        self::assertNotSame($graph, $this->hydratorCallGraph($mutatedSource));

        $transactionMoved = str_replace(
            '$this->enterTransaction();',
            '$this->verifiedStored($row, $tenant, $partitionId);' . "\n        " . '$this->enterTransaction();',
            $durable,
            $transactionCount,
        );
        self::assertSame(1, $transactionCount);
        $mutatedSource = str_replace($durable, $transactionMoved, $source, $sourceCount);
        self::assertSame(1, $sourceCount);
        self::assertNotSame($graph, $this->hydratorCallGraph($mutatedSource));
    }

    public function testStoreDependencyAndActivationBoundaryAreStaticAndBounded(): void
    {
        $path = __DIR__ . '/../../src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php';
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, '->unprotectRecord('));
        self::assertSame(1, substr_count($source, '->protectRecord('));
        self::assertStringNotContainsString('BlackOps\\Identifier\\Uuidv7Generator', $source);
        self::assertStringNotContainsString('PublicApi', $source);
        self::assertStringNotContainsString('PDO', $source);
        self::assertStringNotContainsString('StoragePurpose', $source);
        self::assertStringNotContainsString('Application\\', $source);
        self::assertStringNotContainsString('Container\\', $source);
        self::assertSame([], glob(__DIR__ . '/../../src/Application/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Http/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Console/Audit/*') ?: []);

        $writeSql = $this->methodSource($source, 'writeSql');

        foreach ([
            'state_version = state_version + 1',
            'AND state_version = CAST(:segment_version AS bigint)',
            'AND state_version = CAST(:state_version AS bigint)',
            "ELSE 'audit_store_abort' END AS bigint",
            'SELECT count(*) FROM new_record',
            'SELECT count(*) FROM advanced_segment',
            'SELECT count(*) FROM advanced_state',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $writeSql, $fragment);
        }
        self::assertSame(2, substr_count($writeSql, 'state_version = state_version + 1'));
        self::assertSame(1, substr_count($writeSql, "ELSE 'audit_store_abort' END AS bigint"));
        foreach ([
            'CAST(:schema_version AS smallint)' => 2,
            'CAST(:segment_id AS uuid)' => 4,
            'CAST(:active_segment_id AS uuid)' => 1,
            'CAST(:record_id AS uuid)' => 2,
            'CAST(:next_sequence AS bigint)' => 6,
            'CAST(:assigned_at AS timestamptz)' => 3,
            'CAST(:recorded_at AS timestamptz)' => 2,
            'CAST(:protected_body AS bytea)' => 2,
            'CAST(:bootstrap AS integer)' => 8,
            'CAST(:segment_version AS bigint)' => 1,
            'CAST(:state_version AS bigint)' => 1,
        ] as $cast => $count) {
            self::assertSame($count, substr_count($writeSql, $cast), $cast);
        }
        $versionMutation = str_replace(
            'state_version = state_version + 1',
            'state_version = state_version + 2',
            $writeSql,
        );
        self::assertSame(0, substr_count($versionMutation, 'state_version = state_version + 1'));
        $fenceMutation = str_replace("ELSE 'audit_store_abort' END AS bigint", 'ELSE 1 END AS bigint', $writeSql);
        self::assertSame(0, substr_count($fenceMutation, "ELSE 'audit_store_abort' END AS bigint"));
        $lockOffset = strpos($source, '$this->lockPartition(');
        $stateOffset = strpos($source, '$this->readState(');
        self::assertIsInt($lockOffset);
        self::assertIsInt($stateOffset);
        self::assertLessThan($stateOffset, $lockOffset);
    }

    public function testDurablePipelineAndTwoStageRecordQueriesRemainExplicit(): void
    {
        $path = __DIR__ . '/../../src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php';
        $source = file_get_contents($path);
        self::assertIsString($source);
        $durable = $this->methodSource($source, 'appendDurable');
        $this->assertOrderedSource($durable, [
            '$this->lockPartition(',
            '$this->readState(',
            '$this->readAppendBoundary(',
            '$this->lockRecord(',
            '$this->lookupRecordIdentity(',
            '$this->lookupSameRecord(',
            '$this->verifiedStored(',
            '$this->writeRequired(',
        ]);
        self::assertStringContainsString('$this->assertAppendCapacity(', $durable);
        self::assertLessThan(
            strpos($durable, '$this->assertAppendCapacity('),
            strpos($durable, '$this->lookupRecordIdentity('),
            'capacity must be deferred until the identity probe reports absence',
        );
        self::assertSame(1, substr_count($durable, '$this->readState('));
        self::assertSame(1, substr_count($durable, '$this->readAppendBoundary('));
        self::assertSame(1, substr_count($durable, '$this->lockRecord('));
        self::assertSame(1, substr_count($durable, '$this->lookupRecordIdentity('));

        $identity = $this->methodSource($source, 'lookupRecordIdentity');
        self::assertSame('SELECT record_id::text AS record_id, partition_id', $this->firstSelect($identity));
        foreach (['protected_body', 'content_hash', 'action_type', 'resource_type', 'resource_id'] as $column) {
            self::assertStringNotContainsString($column, $this->firstSelect($identity), $column);
        }
        self::assertStringContainsString('WHERE record_id = CAST(:record_id AS uuid)', $this->normalized($identity));
        self::assertStringContainsString("['record_id' => ParameterType::STRING]", $identity);

        $same = $this->methodSource($source, 'lookupSameRecord');
        self::assertSame(
            $this->normalized($this->firstSelect($this->methodSource($source, 'findSql'))),
            $this->normalized($this->firstSelect($same)),
            'same-partition lookup must preserve exact17 order',
        );
        $sameSql = $this->normalized($same);
        self::assertStringContainsString(
            'WHERE record_id = CAST(:record_id AS uuid) AND partition_id = :partition_id',
            $sameSql,
        );
        self::assertStringContainsString("['record_id' => \$recordId, 'partition_id' => \$partitionId]", $same);
        self::assertStringContainsString(
            "['record_id' => ParameterType::STRING, 'partition_id' => ParameterType::STRING]",
            $same,
        );
        $sameSelect = $this->firstSelect($same);
        $findSelect = $this->firstSelect($this->methodSource($source, 'findSql'));
        self::assertSame(
            $this->normalized($findSelect),
            $this->normalized($sameSelect),
            'same-partition lookup must preserve exact17 order',
        );

        $identityMutation = str_replace(
            'SELECT record_id::text AS record_id, partition_id',
            'SELECT record_id::text AS record_id, partition_id, protected_body',
            $identity,
        );
        self::assertNotSame($this->firstSelect($identity), $this->firstSelect($identityMutation));
        $sameMutation = str_replace('AND partition_id = :partition_id', 'AND partition_id = :wrong_partition', $same);
        self::assertNotSame($sameSql, $this->normalized($sameMutation));

        $close = $this->methodSource($source, 'closeSegment');
        self::assertStringContainsString('$this->assertAutoCommit();', $close);
        self::assertStringContainsString('$this->assertCloseInput(', $close);
        self::assertSame(2, substr_count($close, '$this->readSegment('));
        self::assertSame(1, substr_count($close, '$this->assertCurrentSegmentRows('));
        self::assertStringContainsString('$segment = $current[\'segmentId\'] === $expectedSegmentId', $close);
        $normalizedClose = $this->normalized($close);
        self::assertStringContainsString('? $current : $this->readSegment(', $normalizedClose);
        self::assertStringContainsString("if (\$segment['finalSequence'] === null)", $normalizedClose);
        self::assertStringNotContainsString('$this->assertCloseRows(', $close);
        $closeWithoutFinalGuard = str_replace(
            "if (\$segment['finalSequence'] === null)",
            'if (false)',
            $normalizedClose,
        );
        self::assertNotSame($normalizedClose, $closeWithoutFinalGuard);
        self::assertStringNotContainsString("if (\$segment['finalSequence'] === null)", $closeWithoutFinalGuard);
        $closeWithoutReuse = str_replace('? $current : $this->readSegment(', '? $this->readSegment(', $normalizedClose);
        self::assertNotSame($normalizedClose, $closeWithoutReuse);
        self::assertStringNotContainsString('? $current : $this->readSegment(', $closeWithoutReuse);
        $this->assertOrderedSource($close, [
            '$this->assertCloseInput(',
            '$this->enterTransaction(',
            '$this->lockPartition(',
            '$this->readCloseState(',
            '$this->readSegment(',
            '$this->assertCurrentSegmentRows(',
            '$this->assertOpenSegment(',
            '$this->closeAt(',
            '$this->generateUuid(',
            '$this->assertSegmentAvailable(',
            '$this->closeToken(',
            '$this->writeClose(',
        ]);
        $firstReadSegment = strpos($close, '$this->readSegment(');
        self::assertIsInt($firstReadSegment);
        $secondReadSegment = strpos($close, '$this->readSegment(', $firstReadSegment + 1);
        self::assertIsInt($secondReadSegment);
        self::assertGreaterThan($firstReadSegment, $secondReadSegment);
        $currentRowsCall = strpos($close, '$this->assertCurrentSegmentRows(');
        self::assertIsInt($currentRowsCall);
        self::assertGreaterThan($currentRowsCall, $secondReadSegment);
        $closeSql = $this->methodSource($source, 'closeSql');
        $normalizedCloseSql = $this->normalized($closeSql);
        $this->assertOrderedSource($closeSql, [
            'WITH closed_segment AS',
            'successor_segment AS',
            'advanced_state AS',
            'SELECT committed FROM fence',
        ]);
        foreach ([
            'CAST(:closed_at AS timestamptz)',
            'CAST(:successor_segment_id AS uuid)',
            'CAST(:closed_segment_id AS uuid)',
            'CAST(:final_sequence AS bigint)',
            'CAST(:segment_version AS bigint)',
            'CAST(:state_version AS bigint)',
            "ELSE 'audit_store_abort' END AS bigint",
        ] as $fragment) {
            self::assertStringContainsString($fragment, $closeSql, $fragment);
        }
        foreach (['closed_segment AS', 'successor_segment AS', 'advanced_state AS'] as $cte) {
            self::assertSame(1, substr_count($closeSql, $cte), $cte);
        }
        $closedSegmentStart = strpos($normalizedCloseSql, 'WITH closed_segment AS');
        self::assertIsInt($closedSegmentStart);
        $successorSegmentStart = strpos($normalizedCloseSql, 'successor_segment AS', $closedSegmentStart);
        self::assertIsInt($successorSegmentStart);
        $closedSegment = substr($normalizedCloseSql, $closedSegmentStart, $successorSegmentStart - $closedSegmentStart);
        foreach ([
            'WHERE partition_id = :partition_id',
            'AND segment_id = CAST(:closed_segment_id AS uuid)',
            "AND state = 'open'",
            'AND final_sequence = CAST(:final_sequence AS bigint)',
            'AND head_record_hash = :head_record_hash',
            'AND state_version = CAST(:segment_version AS bigint)',
        ] as $closedGuard) {
            self::assertSame(1, substr_count($closedSegment, $closedGuard), $closedGuard);
        }
        foreach ([
            'WHERE partition_id = :partition_id',
            'AND head_record_hash = :head_record_hash',
        ] as $closedMutationGuard) {
            $closedMutation = str_replace($closedMutationGuard, '', $closedSegment);
            self::assertNotSame($closedSegment, $closedMutation, $closedMutationGuard);
            self::assertStringNotContainsString($closedMutationGuard, $closedMutation, $closedMutationGuard);
        }
        foreach ([
            '(SELECT count(*) FROM closed_segment) = 1',
            '(SELECT count(*) FROM successor_segment) = 1',
            '(SELECT count(*) FROM advanced_state) = 1',
        ] as $countGuard) {
            self::assertSame(1, substr_count($normalizedCloseSql, $countGuard), $countGuard);
            $countMutation = str_replace($countGuard, str_replace('= 1', '= 0', $countGuard), $normalizedCloseSql);
            self::assertNotSame($normalizedCloseSql, $countMutation, $countGuard);
            self::assertStringNotContainsString($countGuard, $countMutation, $countGuard);
        }
        self::assertSame(2, substr_count($closeSql, 'state_version = state_version + 1'));
        self::assertStringContainsString("AND state = 'open'", $closeSql);
        self::assertStringContainsString('AND segment_id = CAST(:closed_segment_id AS uuid)', $closeSql);
        self::assertStringContainsString('AND final_sequence = CAST(:final_sequence AS bigint)', $closeSql);
        self::assertStringContainsString('AND head_record_hash = :head_record_hash', $closeSql);
        self::assertStringContainsString('AND state_version = CAST(:segment_version AS bigint)', $closeSql);
        self::assertStringContainsString('AND active_segment_id = CAST(:closed_segment_id AS uuid)', $closeSql);
        self::assertStringContainsString('AND last_sequence = CAST(:final_sequence AS bigint)', $closeSql);
        self::assertStringContainsString('AND state_version = CAST(:state_version AS bigint)', $closeSql);
        $advancedStateStart = strpos($normalizedCloseSql, 'advanced_state AS');
        self::assertIsInt($advancedStateStart);
        $advancedState = substr($normalizedCloseSql, $advancedStateStart);
        foreach ([
            'WHERE partition_id = :partition_id',
            'AND active_segment_id = CAST(:closed_segment_id AS uuid)',
            'AND last_sequence = CAST(:final_sequence AS bigint)',
            'AND head_record_hash = :head_record_hash',
            'AND state_version = CAST(:state_version AS bigint)',
            'AND EXISTS (SELECT 1 FROM successor_segment)',
        ] as $advancedGuard) {
            self::assertSame(1, substr_count($advancedState, $advancedGuard), $advancedGuard);
        }
        $headMutation = str_replace(
            'AND head_record_hash = :head_record_hash',
            'AND head_record_hash = :wrong_head_record_hash',
            $advancedState,
        );
        self::assertNotSame($advancedState, $headMutation);
        self::assertStringNotContainsString('AND head_record_hash = :head_record_hash', $headMutation);
        $existsMutation = str_replace('AND EXISTS (SELECT 1 FROM successor_segment)', '', $advancedState);
        self::assertNotSame($advancedState, $existsMutation);
        self::assertStringNotContainsString('AND EXISTS (SELECT 1 FROM successor_segment)', $existsMutation);
        self::assertStringContainsString(
            ':head_record_hash, CAST(:closed_at AS timestamptz), NULL, NULL, NULL, 1',
            $this->normalized($closeSql),
        );
        self::assertNotSame($closeSql, str_replace(
            'AND state_version = CAST(:state_version AS bigint)',
            'AND state_version = CAST(:segment_version AS bigint)',
            $closeSql,
        ));
        self::assertNotSame($closeSql, str_replace(
            'state_version = state_version + 1',
            'state_version = state_version + 2',
            $closeSql,
        ));
        $writeClose = $this->methodSource($source, 'writeClose');
        foreach (['predecessor_record_hash', 'first_sequence', 'opened_at'] as $deadParameter) {
            self::assertStringNotContainsString("'{$deadParameter}'", $writeClose, $deadParameter);
        }
        self::assertStringNotContainsString('sealed', $closeSql);

        $closedResult = $this->methodSource($source, 'closedSegmentResult');
        self::assertSame(1, substr_count($closedResult, '$this->readSegment('));
        self::assertSame(2, substr_count($closedResult, '$this->assertClosedRows('));
        self::assertSame(2, substr_count($closedResult, 'hash_equals('));
        self::assertStringNotContainsString('$this->assertCurrentSegmentRows(', $closedResult);
        $normalizedClosedResult = $this->normalized($closedResult);
        $this->assertOrderedSource($normalizedClosedResult, [
            '$segmentSuccessorId = $segment[\'successorSegmentId\'];',
            '$segmentToken = $segment[\'closeFencingToken\'];',
            '|| $segmentSuccessorId === null',
            '$successor = $current[\'segmentId\'] === $segmentSuccessorId',
            '$this->readSegment($state[\'partitionId\'], $segmentSuccessorId)',
        ]);
        self::assertStringContainsString(
            '$successor = $current[\'segmentId\'] === $segmentSuccessorId ? $current : $this->readSegment(',
            $normalizedClosedResult,
        );
        $closedChainWalk = str_replace('$this->readSegment(', '$this->closedSegmentResult(', $closedResult);
        self::assertNotSame($closedResult, $closedChainWalk);
        self::assertStringNotContainsString('$this->readSegment(', $closedChainWalk);
        self::assertStringContainsString('hash_equals(', $closedResult);
        $hashMutation = str_replace('hash_equals(', 'strcmp(', $closedResult);
        self::assertNotSame($closedResult, $hashMutation);
        self::assertStringNotContainsString('hash_equals(', $hashMutation);
        $currentRows = $this->methodSource($source, 'assertCurrentSegmentRows');
        foreach ([
            '$endpoint[\'sequence\'] !== $state[\'lastSequence\']',
            '$endpoint[\'recordHash\'] !== $state[\'headRecordHash\']',
            '$segment[\'firstSequence\'] !== ($state[\'lastSequence\'] + 1)',
            '$segment[\'predecessorRecordHash\'] !== $state[\'headRecordHash\']',
            '$endpoint[\'segmentId\'] === $segment[\'segmentId\']',
        ] as $currentGuard) {
            self::assertStringContainsString($currentGuard, $currentRows, $currentGuard);
            $currentMutation = str_replace($currentGuard, '', $currentRows);
            self::assertNotSame($currentRows, $currentMutation, $currentGuard);
            self::assertStringNotContainsString($currentGuard, $currentMutation, $currentGuard);
        }
    }

    private function methodSource(string $source, string $name): string
    {
        $tokens = token_get_all($source);
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
                continue;
            }
            $next = $index + 1;
            while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if ($next >= $total || !is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) {
                continue;
            }
            if ($tokens[$next][1] !== $name) {
                continue;
            }
            $result = '';
            $depth = 0;
            $opened = false;
            for ($body = $index; $body < $total; $body++) {
                $token = $tokens[$body];
                $text = is_array($token) ? $token[1] : $token;
                $result .= $text;
                if (is_string($text) && strcmp($text, '{') === 0) {
                    $opened = true;
                    $depth++;
                } elseif (is_string($text) && strcmp($text, '}') === 0 && $opened) {
                    $depth--;
                    if ($depth === 0) {
                        return $result;
                    }
                }
            }
        }

        self::fail('Function not found: ' . $name);
    }

    /** @return array{hydrate:int,appendDurable:array{autoCommit:int|false,transaction:int|false,hydrate:int|false},find:array{autoCommit:int|false,hydrate:int|false}} */
    private function hydratorCallGraph(string $source): array
    {
        $durable = $this->methodSource($source, 'appendDurable');
        $find = $this->methodSource($source, 'find');

        return [
            'hydrate' => substr_count($source, '->hydrate('),
            'appendDurable' => [
                'autoCommit' => strpos($durable, '$this->assertAutoCommit();'),
                'transaction' => strpos($durable, '$this->enterTransaction();'),
                'hydrate' => strpos($durable, '$this->verifiedStored('),
            ],
            'find' => [
                'autoCommit' => strpos($find, '$this->assertAutoCommit();'),
                'hydrate' => strpos($find, '$this->verifiedStored('),
            ],
        ];
    }

    private function firstSelect(string $source): string
    {
        $start = strpos($source, 'SELECT ');
        self::assertIsInt($start);
        $end = strpos($source, ' FROM', $start);
        self::assertIsInt($end);

        return trim(substr($source, $start, $end - $start));
    }

    private function normalized(string $source): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($source));
        self::assertIsString($normalized);

        return $normalized;
    }

    /** @param list<string> $needles */
    private function assertOrderedSource(string $source, array $needles): void
    {
        $offset = -1;
        foreach ($needles as $needle) {
            $next = strpos($source, $needle);
            self::assertIsInt($next, $needle);
            self::assertGreaterThan($offset, $next, $needle);
            $offset = $next;
        }
    }

    public function testStorePipelineAndMutationBoundaryRejectUnexpectedCallsOrReferences(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        $storageRoot = realpath(__DIR__ . '/../../src/Internal/Audit/Storage');
        self::assertNotFalse($sourceRoot);
        self::assertNotFalse($storageRoot);
        $storePath = $storageRoot . '/PostgreSqlAuditRecordStore.php';
        $storeSource = file_get_contents($storePath);
        self::assertIsString($storeSource);

        self::assertSame(
            [
                'assertAppendCapacity' => 2,
                'assertAutoCommit' => 4,
                'assertCloseInput' => 1,
                'assertClosedRows' => 2,
                'assertCurrentSegmentRows' => 1,
                'assertOpenSegment' => 4,
                'assertPartitionBinding' => 1,
                'assertPartitionValue' => 2,
                'assertQueryIndexes' => 1,
                'assertRecordAvailable' => 1,
                'assertRecordId' => 4,
                'assertSegmentAvailable' => 3,
                'assertTenantColumns' => 1,
                'assignedAt' => 2,
                'beginTransaction' => 1,
                'canonicalNow' => 2,
                'close' => 1,
                'closeAt' => 1,
                'closeSql' => 1,
                'closeToken' => count(['first', 'second', 'third']),
                'closedSegmentResult' => 1,
                'commit' => 3,
                'complete' => 2,
                'contentHash' => 1,
                'date' => 3,
                'definition' => 2,
                'derive' => 2,
                'durableTenant' => 1,
                'encodeBytes' => 1,
                'encodeInteger' => 1,
                'encodeString' => 5,
                'enterTransaction' => 3,
                'fetchAllAssociative' => 7,
                'fetchAssociative' => 3,
                'fetchOne' => 4,
                'finalize' => 1,
                'finalizeRequired' => 1,
                'findParameters' => 1,
                'findSql' => 1,
                'format' => 1,
                'generate' => 1,
                'generateUuid' => 4,
                'getTransactionNestingLevel' => 1,
                'hash' => 2,
                'hydrate' => 1,
                'hydratorRow' => 1,
                'integer' => 9,
                'integrity' => 1,
                'isAutoCommit' => 1,
                'isTransactionActive' => 1,
                'lockPartition' => 3,
                'lockRecord' => 2,
                'logicalSnapshot' => 2,
                'lookupRecordIdentity' => 1,
                'lookupSameRecord' => 1,
                'now' => 1,
                'nullableHash' => 5,
                'nullableString' => 3,
                'partitionForTenant' => 3,
                'partitionStateTable' => 3,
                'protectRecord' => 1,
                'quarantine' => 7,
                'readAppendBoundary' => 2,
                'readBoundary' => 6,
                'readCloseState' => 1,
                'readMinimum' => 3,
                'readMissingState' => 1,
                'readSegment' => 4,
                'readState' => 3,
                'recordsTable' => 8,
                'rollBack' => 3,
                'rollbackOwned' => 4,
                'safeFailure' => 21,
                'schemaName' => 2,
                'sealComplete' => 2,
                'segmentsTable' => 5,
                'setTimezone' => 1,
                'stateValues' => 1,
                'storedRow' => 2,
                'string' => 11,
                'toArray' => 3,
                'unprotectRecord' => 1,
                'uuid' => 5,
                'validateDraft' => 1,
                'validateDurableLogical' => 1,
                'validateRequiredDraft' => 1,
                'verifiedStored' => 2,
                'writeClose' => 1,
                'writeRequired' => 2,
                'writeSql' => 1,
            ],
            $this->methodCalls($storeSource),
        );

        foreach ([
            'validateLogical',
            'transactional',
            'setAutoCommit',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
        ] as $method) {
            self::assertSame(0, $this->methodCalls($storeSource)[$method] ?? 0, $method);
        }

        $notFound = strpos($storeSource, 'return AuditLookupResult::notFound();');
        $hydrate = strpos($storeSource, '->hydrate(');
        $unprotect = strpos($storeSource, '->unprotectRecord(');
        self::assertIsInt($notFound);
        self::assertIsInt($hydrate);
        self::assertIsInt($unprotect);
        self::assertLessThan($hydrate, $notFound);
        self::assertLessThan($unprotect, $notFound);

        foreach ($this->storagePhpPaths($storageRoot) as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertSame(0, $this->dynamicInvocationCount($source), $this->relativeSourcePath($sourceRoot, $path));
        }
        self::assertSame([], $this->outsideStorageReferences($sourceRoot, $storageRoot));

        $commentSpoof = $storeSource . "\n// ->unprotectRecord(\n// ->protectRecord(\n";
        self::assertSame(
            $this->methodCalls($storeSource),
            $this->methodCalls($commentSpoof),
            'comments must not alter the callable inventory',
        );

        $added = $storeSource . "\n\$synthetic->unprotectRecord();\n";
        self::assertSame(
            ($this->methodCalls($storeSource)['unprotectRecord'] ?? 0) + 1,
            $this->methodCalls($added)['unprotectRecord'] ?? 0,
            'an added direct caller must fail the exact inventory',
        );

        $replacementCount = 1;
        $removed = str_replace('->unprotectRecord(', '->removedCall(', $storeSource, $replacementCount);
        self::assertSame(
            0,
            $this->methodCalls($removed)['unprotectRecord'] ?? 0,
            'a removed caller must fail the exact inventory',
        );

        $replacementCount = 1;
        $dynamic = str_replace('->unprotectRecord(', '->{$method}(', $storeSource, $replacementCount);
        self::assertSame(0, $this->methodCalls($dynamic)['unprotectRecord'] ?? 0);
        self::assertGreaterThan(0, $this->dynamicInvocationCount($dynamic));

        foreach ([
            "\$dependency->\$method();",
            "\$dependency?->\$method();",
        ] as $dynamicCall) {
            $dynamic = $storeSource . "\n" . $dynamicCall . "\n";
            self::assertSame(
                $this->methodCalls($storeSource)['unprotectRecord'],
                $this->methodCalls($dynamic)['unprotectRecord'],
                'variable and nullsafe dynamic calls must not become direct callers',
            );
            self::assertGreaterThan(0, $this->dynamicInvocationCount($dynamic));
        }
    }

    /** @return array<string,int> */
    private function methodCalls(string $source): array
    {
        $counts = [];
        $operators = [T_OBJECT_OPERATOR];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $operators[] = T_NULLSAFE_OBJECT_OPERATOR;
        }
        $tokens = token_get_all($source);
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], $operators, true)) {
                continue;
            }
            $next = $index + 1;
            while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if ($next >= $total || !is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) {
                continue;
            }
            $method = $tokens[$next][1];
            $next++;
            while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if ($next < $total && $tokens[$next] === '(') {
                $counts[$method] = ($counts[$method] ?? 0) + 1;
            }
        }
        ksort($counts);

        return $counts;
    }

    /** @return array<string,string> */
    private function sourceFiles(string $sourceRoot): array
    {
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $path = realpath($file->getPathname());
            self::assertIsString($path);
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources[$this->relativeSourcePath($sourceRoot, $path)] = $source;
        }
        ksort($sources);

        return $sources;
    }

    /** @return array<string,class-string> */
    private function b2StorageTypes(): array
    {
        return [
            'AuditAppendResult' => AuditAppendResult::class,
            'AuditLookupResult' => AuditLookupResult::class,
            'AuditSegmentCloseResult' => AuditSegmentCloseResult::class,
            'AuditStoreException' => AuditStoreException::class,
            'AuditStoreFailure' => AuditStoreFailure::class,
            'PostgreSqlAuditRecordStore' => PostgreSqlAuditRecordStore::class,
            'PostgreSqlAuditSchema' => PostgreSqlAuditSchema::class,
            'PostgreSqlAuditStoredRecordHydrator' => PostgreSqlAuditStoredRecordHydrator::class,
        ];
    }

    /**
     * @param array<string,string> $sources
     * @return array<string,list<string>>
     */
    private function b2AwareIndirectInvocationViolationsFromSources(array $sources, string $storagePrefix): array
    {
        $violations = [];
        foreach ($sources as $path => $source) {
            $codeReferences = $this->b2StorageCodeReferences($source);
            $classStringReferences = $this->b2StorageClassStringReferences($source);
            $outsideStorage = !str_starts_with($path, $storagePrefix);
            if ($outsideStorage && $codeReferences === [] && $classStringReferences === []) {
                continue;
            }

            $fileViolations = $this->indirectInvocationViolations($source);
            if ($outsideStorage) {
                foreach ($codeReferences as $type) {
                    $fileViolations[] = 'outside-reference:' . $type;
                }
                foreach ($classStringReferences as $type) {
                    $fileViolations[] = 'class-string:' . $type;
                }
            }
            sort($fileViolations);
            $fileViolations = array_values(array_unique($fileViolations));
            if ($fileViolations !== []) {
                $violations[$path] = $fileViolations;
            }
        }
        ksort($violations);

        return $violations;
    }

    /** @return list<string> */
    private function b2StorageCodeReferences(string $source): array
    {
        $code = $this->codeWithoutCommentsAndStrings($source);
        $references = [];
        foreach (array_keys($this->b2StorageTypes()) as $type) {
            if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($type, '/') . '(?![A-Za-z0-9_])/', $code) === 1) {
                $references[] = $type;
            }
        }
        sort($references);

        return $references;
    }

    /** @return list<string> */
    private function b2StorageClassStringReferences(string $source): array
    {
        $types = $this->b2StorageTypes();
        $references = [];
        $inHeredoc = false;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_START_HEREDOC) {
                $inHeredoc = true;
                continue;
            }
            if (is_array($token) && $token[0] === T_END_HEREDOC) {
                $inHeredoc = false;
                continue;
            }
            if (
                !is_array($token)
                || $token[0] !== T_CONSTANT_ENCAPSED_STRING && (!$inHeredoc || $token[0] !== T_ENCAPSED_AND_WHITESPACE)
            ) {
                continue;
            }
            $value = $token[0] === T_CONSTANT_ENCAPSED_STRING ? trim($token[1], "'\"") : trim($token[1]);
            $value = ltrim(str_replace('\\\\', '\\', $value), '\\');
            foreach ($types as $shortName => $type) {
                if ($value === ltrim($type, '\\')) {
                    $references[] = $shortName;
                }
            }
        }
        sort($references);

        return array_values(array_unique($references));
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function protectedDirectCallsitesFromSources(array $sources): array
    {
        $counts = [
            'protectRecord' => [],
            'unprotectRecord' => [],
            'protectOutbox' => [],
            'unprotectOutbox' => [],
        ];
        foreach ($sources as $path => $source) {
            $calls = $this->methodCalls($source);
            foreach ($counts as $method => &$files) {
                $count = $calls[$method] ?? 0;
                if ($count > 0) {
                    $files[$path] = $count;
                }
            }
            unset($files);
        }
        foreach ($counts as &$files) {
            ksort($files);
        }
        unset($files);

        return $counts;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function protectedIndirectCallsitesFromSources(array $sources): array
    {
        $violations = [];
        foreach ($sources as $path => $source) {
            $methods = $this->protectedIndirectInvocationViolations($source);
            if ($methods !== []) {
                $violations[$path] = $methods;
            }
        }

        ksort($violations);

        return $violations;
    }

    /** @return list<string> */
    private function protectedIndirectInvocationViolations(string $source): array
    {
        $tokens = token_get_all($source);
        $protected = ['protectRecord', 'unprotectRecord', 'protectOutbox', 'unprotectOutbox'];
        $variableMethods = [];
        $violations = [];
        $add = static function (string $method) use (&$violations, $protected): void {
            if (in_array($method, $protected, true)) {
                $violations[] = $method;
            }
        };
        $addAll = static function () use (&$violations, $protected): void {
            foreach ($protected as $method) {
                $violations[] = $method;
            }
        };
        $stringValue = fn(array $parts): ?string => $this->singleStringTokenValue($parts);
        $methodFromParts = static function (array $parts, array $variables) use ($stringValue): ?string {
            $value = $stringValue($parts);
            if ($value !== null) {
                return $value;
            }
            $significant = [];
            foreach ($parts as $part) {
                if (is_array($part) && in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $significant[] = $part;
            }
            if (count($significant) === 1 && is_array($significant[0]) && $significant[0][0] === T_VARIABLE) {
                return $variables[$significant[0][1]] ?? null;
            }

            return null;
        };
        for ($index = 0; $index < count($tokens); $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_VARIABLE) {
                continue;
            }
            $equals = $this->nextSemanticToken($tokens, $index + 1);
            if ($equals === null || $tokens[$equals] !== '=') {
                continue;
            }
            $value = $this->nextSemanticToken($tokens, $equals + 1);
            if ($value === null || !is_array($tokens[$value]) || $tokens[$value][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $candidate = $this->singleStringTokenValue([$tokens[$value]]);
            if ($candidate !== null && in_array($candidate, $protected, true)) {
                $variableMethods[$tokens[$index][1]] = $candidate;
            }
        }

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token === '[') {
                $closing = $this->matchingSemanticToken($tokens, $index, '[', ']');
                if ($closing === null) {
                    continue;
                }
                $parts = $this->topLevelArrayParts($tokens, $index + 1, $closing);
                if (count($parts) === 2 && !$this->containsToken($parts[0], '=>')) {
                    $candidate = $methodFromParts($parts[1], $variableMethods);
                    if ($candidate !== null) {
                        $add($candidate);
                    }
                }
                continue;
            }
            if (
                is_array($token)
                && ($token[0] === T_ARRAY || $token[0] === T_STRING && strtolower($token[1]) === 'array')
            ) {
                $open = $this->nextSemanticToken($tokens, $index + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $closing = $this->matchingSemanticToken($tokens, $open, '(', ')');
                if ($closing === null) {
                    continue;
                }
                $parts = $this->topLevelArrayParts($tokens, $open + 1, $closing);
                if (count($parts) === 2 && !$this->containsToken($parts[0], '=>')) {
                    $candidate = $methodFromParts($parts[1], $variableMethods);
                    if ($candidate !== null) {
                        $add($candidate);
                    }
                }
                continue;
            }
            if (!is_array($token) || !$this->isNameToken($token)) {
                continue;
            }
            $next = $this->nextSemanticToken($tokens, $index + 1);
            if ($next === null || $tokens[$next] !== '(') {
                continue;
            }
            $closing = $this->matchingSemanticToken($tokens, $next, '(', ')');
            if ($closing === null) {
                continue;
            }
            $name = trim($token[1], '\\');
            if (preg_match('/(?:^|\\\\)Reflection[A-Za-z0-9_]+$/i', $name) === 1) {
                foreach ($this->topLevelArrayParts($tokens, $next + 1, $closing) as $part) {
                    $candidate = $methodFromParts($part, $variableMethods);
                    if ($candidate !== null) {
                        $add($candidate);
                    }
                }
            }
            if (in_array(
                strtolower($name),
                [
                    'call_user_func',
                    'call_user_func_array',
                    'forward_static_call',
                    'forward_static_call_array',
                ],
                true,
            )) {
                $parts = $this->topLevelArrayParts($tokens, $next + 1, $closing);
                $candidate = $parts === [] ? null : $methodFromParts($parts[0], $variableMethods);
                if ($candidate !== null) {
                    $add($candidate);
                } elseif ($parts !== [] && $this->containsTokenType($parts[0], T_VARIABLE)) {
                    $addAll();
                }
            }
        }

        $objectOperators = [T_OBJECT_OPERATOR];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $objectOperators[] = T_NULLSAFE_OBJECT_OPERATOR;
        }
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], $objectOperators, true)) {
                continue;
            }
            $next = $this->nextSemanticToken($tokens, $index + 1);
            if ($next === null) {
                continue;
            }
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_STRING && $tokens[$next][1] === 'getMethod') {
                $open = $this->nextSemanticToken($tokens, $next + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $closing = $this->matchingSemanticToken($tokens, $open, '(', ')');
                if ($closing === null) {
                    continue;
                }
                $parts = $this->topLevelArrayParts($tokens, $open + 1, $closing);
                $candidate = $parts === [] ? null : $methodFromParts($parts[0], $variableMethods);
                if ($candidate !== null) {
                    $add($candidate);
                }
                continue;
            }
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                $call = $this->nextSemanticToken($tokens, $next + 1);
                if ($call !== null && $tokens[$call] === '(') {
                    $method = $variableMethods[$tokens[$next][1]] ?? null;
                    if ($method === null) {
                        $addAll();
                    } else {
                        $add($method);
                    }
                }
                continue;
            }
            if ($tokens[$next] !== '{') {
                continue;
            }
            $closing = $this->matchingSemanticToken($tokens, $next, '{', '}');
            $call = $closing === null ? null : $this->nextSemanticToken($tokens, $closing + 1);
            if ($call === null || $tokens[$call] !== '(') {
                continue;
            }
            $inside = $this->topLevelArrayParts($tokens, $next + 1, $closing);
            $candidate = $methodFromParts($inside[0] ?? [], $variableMethods);
            if ($candidate !== null) {
                $add($candidate);
            } else {
                $addAll();
            }
        }

        $staticOperators = [T_DOUBLE_COLON];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], $staticOperators, true)) {
                continue;
            }
            $next = $this->nextSemanticToken($tokens, $index + 1);
            if ($next === null || $tokens[$next] !== '{') {
                continue;
            }
            $closing = $this->matchingSemanticToken($tokens, $next, '{', '}');
            $call = $closing === null ? null : $this->nextSemanticToken($tokens, $closing + 1);
            if ($call !== null && $tokens[$call] === '(') {
                $addAll();
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }

    private function dynamicInvocationCount(string $source): int
    {
        $count = 0;
        $operators = [T_OBJECT_OPERATOR, T_DOUBLE_COLON];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $operators[] = T_NULLSAFE_OBJECT_OPERATOR;
        }
        $tokens = token_get_all($source);
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], $operators, true)) {
                continue;
            }
            $next = $index + 1;
            while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                $next++;
                while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                    $next++;
                }
                if ($next < $total && $tokens[$next] === '(') {
                    $count++;
                }
                continue;
            }
            if ($next < $total && $tokens[$next] === '{') {
                $next++;
                while ($next < $total && $tokens[$next] !== '}') {
                    $next++;
                }
                $next++;
                while ($next < $total && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                    $next++;
                }
                if ($next < $total && $tokens[$next] === '(') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @return list<string> */
    private function indirectInvocationViolations(string $source): array
    {
        $tokens = token_get_all($source);
        $violations = [];
        $forbiddenFunctions = [
            'array_filter',
            'array_map',
            'array_reduce',
            'array_walk',
            'array_walk_recursive',
            'call_user_func',
            'call_user_func_array',
            'forward_static_call',
            'forward_static_call_array',
            'is_callable',
            'iterator_apply',
            'preg_filter',
            'preg_replace_callback',
            'register_shutdown_function',
            'uasort',
            'uksort',
            'usort',
        ];
        $forbiddenMethods = [
            'bind',
            'bindTo',
            'call',
            'fromCallable',
            'getClosure',
            'getConstructor',
            'getFunction',
            'getMethod',
            'getProperty',
            'invoke',
            'invokeArgs',
        ];
        $objectOperators = [T_OBJECT_OPERATOR];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $objectOperators[] = T_NULLSAFE_OBJECT_OPERATOR;
        }
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token === '(' && $this->isParenthesizedVariableCall($tokens, $index)) {
                $violations[] = 'variable-call';
                continue;
            }
            if (is_array($token) && $token[0] === T_VARIABLE) {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                if ($next !== null && $tokens[$next] === '(') {
                    $violations[] = 'variable-call';
                }
                continue;
            }
            if (is_array($token) && $this->isNameToken($token)) {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                $functionName = strtolower(trim($token[1], '\\'));
                if ($next !== null && $tokens[$next] === '(' && in_array($functionName, $forbiddenFunctions, true)) {
                    $violations[] = $functionName;
                }
                $name = trim($token[1], '\\');
                if (preg_match('/(?:^|\\\\)Reflection[A-Za-z0-9_]+$/i', $name) === 1) {
                    $previous = $this->previousSemanticToken($tokens, $index - 1);
                    if ($previous !== null && is_array($tokens[$previous]) && $tokens[$previous][0] === T_NEW) {
                        $violations[] = $name;
                    }
                }
            }
            if (is_array($token) && in_array($token[0], $objectOperators, true)) {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                if (
                    $next !== null
                    && is_array($tokens[$next])
                    && $tokens[$next][0] === T_STRING
                    && in_array($tokens[$next][1], $forbiddenMethods, true)
                ) {
                    $violations[] = 'object-' . $tokens[$next][1];
                }
                if ($next !== null && $tokens[$next] === '{') {
                    $closing = $this->matchingSemanticToken($tokens, $next, '{', '}');
                    $call = $closing === null ? null : $this->nextSemanticToken($tokens, $closing + 1);
                    if ($call !== null && $tokens[$call] === '(') {
                        $violations[] = 'dynamic-object-call';
                    }
                }
            }
            if (is_array($token) && $token[0] === T_DOUBLE_COLON) {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                if (
                    $next !== null
                    && is_array($tokens[$next])
                    && $tokens[$next][0] === T_STRING
                    && in_array($tokens[$next][1], $forbiddenMethods, true)
                ) {
                    $violations[] = 'static-' . $tokens[$next][1];
                }
                if ($next !== null && $tokens[$next] === '{') {
                    $closing = $this->matchingSemanticToken($tokens, $next, '{', '}');
                    $call = $closing === null ? null : $this->nextSemanticToken($tokens, $closing + 1);
                    if ($call !== null && $tokens[$call] === '(') {
                        $violations[] = 'dynamic-static-call';
                    }
                }
            }
            if ($token === '[') {
                $closing = $this->matchingSemanticToken($tokens, $index, '[', ']');
                if ($closing === null) {
                    continue;
                }
                $parts = $this->topLevelArrayParts($tokens, $index + 1, $closing);
                if (count($parts) !== 2 || $this->containsToken($parts[0], '=>')) {
                    continue;
                }
                $first = $parts[0];
                $second = $parts[1];
                $firstHasVariable = $this->containsTokenType($first, T_VARIABLE);
                $firstHasClassReference = $this->containsToken($first, '::');
                $firstString = $this->singleStringTokenValue($first);
                $secondString = $this->singleStringTokenValue($second);
                if (
                    $secondString !== null
                    && (
                        $firstHasVariable
                        || $firstHasClassReference
                        || $firstString !== null
                        && $this->isClassStringLiteral($firstString)
                    )
                ) {
                    $violations[] = 'callable-array';
                }
                continue;
            }
            if (
                is_array($token)
                && ($token[0] === T_ARRAY || $token[0] === T_STRING && strtolower($token[1]) === 'array')
            ) {
                $open = $this->nextSemanticToken($tokens, $index + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $closing = $this->matchingSemanticToken($tokens, $open, '(', ')');
                if ($closing === null) {
                    continue;
                }
                $parts = $this->topLevelArrayParts($tokens, $open + 1, $closing);
                if (count($parts) !== 2 || $this->containsToken($parts[0], '=>')) {
                    continue;
                }
                $first = $parts[0];
                $second = $parts[1];
                $firstHasVariable = $this->containsTokenType($first, T_VARIABLE);
                $firstHasClassReference = $this->containsToken($first, '::');
                $firstString = $this->singleStringTokenValue($first);
                $secondString = $this->singleStringTokenValue($second);
                if (
                    $secondString !== null
                    && (
                        $firstHasVariable
                        || $firstHasClassReference
                        || $firstString !== null
                        && $this->isClassStringLiteral($firstString)
                    )
                ) {
                    $violations[] = 'callable-array';
                }
            }
        }
        sort($violations);

        return array_values(array_unique($violations));
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function isParenthesizedVariableCall(array $tokens, int $open): bool
    {
        $close = $this->matchingSemanticToken($tokens, $open, '(', ')');
        if ($close === null) {
            return false;
        }
        $call = $this->nextSemanticToken($tokens, $close + 1);
        if ($call === null || $tokens[$call] !== '(') {
            return false;
        }

        $start = $this->nextSemanticToken($tokens, $open + 1);
        $end = $this->previousSemanticToken($tokens, $close - 1);
        while ($start !== null && $end !== null && $start < $end && $tokens[$start] === '(') {
            $nestedClose = $this->matchingSemanticToken($tokens, $start, '(', ')');
            if ($nestedClose !== $end) {
                break;
            }
            $start = $this->nextSemanticToken($tokens, $start + 1);
            $end = $this->previousSemanticToken($tokens, $end - 1);
        }

        return $start !== null && $start === $end && is_array($tokens[$start]) && $tokens[$start][0] === T_VARIABLE;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     * @return list<list<array{0:int,1:string,2:int}|string>>
     */
    private function topLevelArrayParts(array $tokens, int $start, int $end): array
    {
        $parts = [];
        $part = [];
        $depth = 0;
        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token === '[' || $token === '(' || $token === '{') {
                $depth++;
            } elseif ($token === ']' || $token === ')' || $token === '}') {
                $depth--;
            }
            if ($depth === 0 && $token === ',') {
                $parts[] = $part;
                $part = [];
                continue;
            }
            $part[] = $token;
        }
        if ($part !== [] || $parts !== []) {
            $parts[] = $part;
        }

        return $parts;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function containsToken(array $tokens, string $needle): bool
    {
        foreach ($tokens as $token) {
            if ($token === $needle || is_array($token) && $token[1] === $needle) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function containsTokenType(array $tokens, int $type): bool
    {
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === $type) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function singleStringTokenValue(array $tokens): ?string
    {
        $significant = [];
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $significant[] = $token;
        }
        if (count($significant) !== 1 || !is_array($significant[0])) {
            return null;
        }
        if ($significant[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        return trim($significant[0][1], "'\"");
    }

    private function isClassStringLiteral(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $value = ltrim($value, '\\');

        return (
            $value !== ''
            && (str_contains($value, '\\') || preg_match('/\A[A-Z]/', $value) === 1)
            && preg_match(
                '/\A[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*\z/D',
                $value,
            ) === 1
        );
    }

    /** @return list<string> */
    private function storagePhpPaths(string $storageRoot): array
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $storageRoot,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $path = realpath($file->getPathname());
            self::assertIsString($path);
            $paths[] = $path;
        }
        sort($paths);

        return array_values($paths);
    }

    /** @return array{0:string,1:string} */
    private function createStorageFixture(string $storageRoot): array
    {
        $root = tempnam(sys_get_temp_dir(), 'blackops-audit-storage-');
        self::assertIsString($root);
        self::assertTrue(unlink($root));
        self::assertTrue(mkdir($root, 0700));

        $fixtureStorage = $root . '/src/Internal/Audit/Storage';
        self::assertTrue(mkdir($fixtureStorage, 0700, true));
        foreach ($this->storagePhpPaths($storageRoot) as $path) {
            self::assertTrue(copy($path, $fixtureStorage . '/' . basename($path)));
        }

        return [$root, $fixtureStorage];
    }

    private function removeTemporaryTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }

    /** @return array<string,list<class-string>> */
    private function storageInventory(string $sourceRoot, string $storageRoot): array
    {
        $inventory = [];
        foreach ($this->storagePhpPaths($storageRoot) as $path) {
            $inventory[$this->relativeSourcePath($sourceRoot, $path)] = $this->typeDeclaredBy($path);
        }
        ksort($inventory);

        return $inventory;
    }

    /** @return list<string> */
    private function outsideStorageReferences(string $sourceRoot, string $storageRoot): array
    {
        $storagePrefix = $this->relativeSourcePath($sourceRoot, $storageRoot) . '/';

        return $this->outsideStorageReferencesFromSources($this->sourceFiles($sourceRoot), $storagePrefix);
    }

    /**
     * @param array<string,string> $sources
     * @return list<string>
     */
    private function outsideStorageReferencesFromSources(array $sources, string $storagePrefix): array
    {
        $references = [];
        foreach ($sources as $path => $source) {
            if (str_starts_with($path, $storagePrefix)) {
                continue;
            }
            $types = array_merge(
                $this->b2StorageCodeReferences($source),
                $this->b2StorageClassStringReferences($source),
            );
            foreach (array_values(array_unique($types)) as $type) {
                $references[] = $path . ':' . $type;
            }
        }
        sort($references);

        return $references;
    }

    private function relativeSourcePath(string $sourceRoot, string $path): string
    {
        self::assertTrue($path === $sourceRoot || str_starts_with($path, $sourceRoot . DIRECTORY_SEPARATOR));

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($sourceRoot) + 1));
    }

    private function codeWithoutCommentsAndStrings(string $source): string
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

        return $code;
    }

    /** @return list<class-string> */
    private function typeDeclaredBy(string $path): array
    {
        $source = file_get_contents($path);
        self::assertIsString($source);
        $namespace = '';
        $tokens = token_get_all($source);
        $declared = [];
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_NAMESPACE) {
                $name = '';
                for ($next = $index + 1; $next < $total; $next++) {
                    $part = $tokens[$next];
                    if ($part === ';' || $part === '{') {
                        break;
                    }
                    if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $name .= $part[1];
                    } elseif ($part === '\\') {
                        $name .= '\\';
                    }
                }
                $namespace = $name;
                continue;
            }
            $declarationTokens = [T_CLASS, T_ENUM, T_INTERFACE];
            if (defined('T_TRAIT')) {
                $declarationTokens[] = T_TRAIT;
            }
            if (!in_array($token[0], $declarationTokens, true)) {
                continue;
            }
            $next = $this->nextSemanticToken($tokens, $index + 1);
            if ($next === null || !is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) {
                continue;
            }
            $declared[] = $namespace === '' ? $tokens[$next][1] : $namespace . '\\' . $tokens[$next][1];
        }

        return $declared;
    }

    /** @return list<string> */
    private function semanticDependencies(string $source): array
    {
        $tokens = token_get_all($source);
        $namespace = $this->semanticNamespace($tokens);
        $aliases = $this->semanticImportAliases($tokens);
        $classBody = $this->semanticClassBodyFlags($tokens);
        $declared = $this->semanticDeclaredType($tokens, $namespace);
        $dependencies = [];

        $add = function (mixed $token) use (&$dependencies, $namespace, $aliases, $declared): void {
            if (!is_array($token) || !$this->isNameToken($token)) {
                return;
            }
            $name = $this->resolveSemanticName($token, $namespace, $aliases);
            if ($name !== null && $name !== $declared) {
                $dependencies[$name] = true;
            }
        };
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_USE) {
                if ($classBody[$index] ?? false) {
                    $this->addTraitUseDependencies($tokens, $index, $add);
                }
                continue;
            }
            if (in_array($token[0], [T_NEW, T_INSTANCEOF, T_EXTENDS], true)) {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                if ($next !== null) {
                    $add($tokens[$next]);
                }
                continue;
            }
            if ($token[0] === T_IMPLEMENTS) {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                while ($next !== null && $tokens[$next] !== '{' && $tokens[$next] !== ';') {
                    $add($tokens[$next]);
                    $next = $this->nextSemanticToken($tokens, $next + 1);
                }
                continue;
            }
            if ($token[0] === T_CATCH) {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                while ($next !== null && $tokens[$next] !== ')') {
                    $add($tokens[$next]);
                    $next = $this->nextSemanticToken($tokens, $next + 1);
                }
                continue;
            }
            if ($token[0] === T_DOUBLE_COLON) {
                $previous = $this->previousSemanticToken($tokens, $index - 1);
                if ($previous !== null) {
                    $add($tokens[$previous]);
                }
                continue;
            }
            if ($token[0] === T_FUNCTION) {
                $this->addFunctionSignatureDependencies($tokens, $index, $add);
                continue;
            }
            if ($token[0] === T_ATTRIBUTE || $token[1] === '#[') {
                $this->addAttributeDependencies($tokens, $index, $add);
            }
        }
        $this->addPropertyDependencies($tokens, $add);

        $result = array_keys($dependencies);
        sort($result);

        return $result;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array<int,bool> */
    private function semanticClassBodyFlags(array $tokens): array
    {
        $flags = [];
        $scopes = [];
        $classLike = [T_CLASS, T_ENUM, T_INTERFACE];
        if (defined('T_TRAIT')) {
            $classLike[] = T_TRAIT;
        }
        $pendingClassBody = false;
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            $flags[$index] = in_array(true, $scopes, true);
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], $classLike, true)) {
                $pendingClassBody = true;
                continue;
            }
            if ($token === '{') {
                $scopes[] = $pendingClassBody;
                $pendingClassBody = false;
                continue;
            }
            if ($token === '}') {
                array_pop($scopes);
                continue;
            }
            if ($token === ';') {
                $pendingClassBody = false;
            }
        }

        return $flags;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function addTraitUseDependencies(array $tokens, int $useIndex, callable $add): void
    {
        $start = $this->nextSemanticToken($tokens, $useIndex + 1);
        if ($start === null || $tokens[$start] === '(') {
            return;
        }
        $partStart = $start;
        $depth = 0;
        for ($index = $start; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token === '{' && $depth === 0 || $token === ';' && $depth === 0) {
                $this->addSemanticNameRange($tokens, $partStart, $index, $add);

                return;
            }
            if ($token === ',' && $depth === 0) {
                $this->addSemanticNameRange($tokens, $partStart, $index, $add);
                $partStart = $index + 1;
                continue;
            }
            if ($token === '(' || $token === '[' || $token === '{') {
                $depth++;
            } elseif ($token === ')' || $token === ']' || $token === '}') {
                $depth--;
            }
        }
        $this->addSemanticNameRange($tokens, $partStart, count($tokens), $add);
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function addSemanticNameRange(array $tokens, int $start, int $end, callable $add): void
    {
        $token = $this->semanticNameToken($tokens, $start, $end);
        if ($token !== null) {
            $add($token);
        }
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array{0:int,1:string,2:int}|null */
    private function semanticNameToken(array $tokens, int $start, int $end): ?array
    {
        $index = $this->nextSemanticToken($tokens, $start);
        if ($index === null || $index >= $end || !is_array($tokens[$index]) || !$this->isNameToken($tokens[$index])) {
            return null;
        }
        $name = $tokens[$index][1];
        $type = $tokens[$index][0];
        $index++;
        while ($index < $end) {
            $separator = $this->nextSemanticToken($tokens, $index);
            if ($separator === null || $separator >= $end) {
                break;
            }
            $token = $tokens[$separator];
            if ($token === '\\' || is_array($token) && defined('T_NS_SEPARATOR') && $token[0] === T_NS_SEPARATOR) {
                $name .= '\\';
                $index = $separator + 1;
                $part = $this->nextSemanticToken($tokens, $index);
                if (
                    $part === null
                    || $part >= $end
                    || !is_array($tokens[$part])
                    || !$this->isNameToken($tokens[$part])
                ) {
                    break;
                }
                $name .= $tokens[$part][1];
                $index = $part + 1;
                continue;
            }
            break;
        }

        return [$type, $name, $tokens[$this->nextSemanticToken($tokens, $start)][2] ?? 0];
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function semanticNamespace(array $tokens): string
    {
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_NAMESPACE) {
                continue;
            }
            $name = '';
            for ($next = $index + 1; $next < $total; $next++) {
                $part = $tokens[$next];
                if ($part === ';' || $part === '{') {
                    break;
                }
                if (is_array($part) && $this->isNameToken($part)) {
                    $name .= $part[1];
                } elseif ($part === '\\') {
                    $name .= '\\';
                }
            }

            return trim($name, '\\');
        }

        return '';
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function semanticDeclaredType(array $tokens, string $namespace): string
    {
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            if (!is_array($tokens[$index]) || !in_array($tokens[$index][0], [T_CLASS, T_ENUM, T_INTERFACE], true)) {
                continue;
            }
            $next = $this->nextSemanticToken($tokens, $index + 1);
            if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                return $namespace === '' ? $tokens[$next][1] : $namespace . '\\' . $tokens[$next][1];
            }
        }

        return '';
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return array<string,string> */
    private function semanticImportAliases(array $tokens): array
    {
        $aliases = [];
        $classBody = $this->semanticClassBodyFlags($tokens);
        $total = count($tokens);
        for ($index = 0; $index < $total; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_USE) {
                continue;
            }
            if ($classBody[$index] ?? false) {
                continue;
            }
            $next = $this->nextSemanticToken($tokens, $index + 1);
            if (
                $next === null
                || $tokens[$next] === '('
                || is_array($tokens[$next]) && in_array($tokens[$next][0], [T_FUNCTION, T_CONST], true)
            ) {
                continue;
            }
            $name = '';
            $prefix = '';
            $alias = null;
            $readingAlias = false;
            $braceDepth = 0;
            for ($part = $next; $part < $total; $part++) {
                $current = $tokens[$part];
                if ($current === ';') {
                    $this->recordSemanticImport($name, $prefix, $alias, $aliases);
                    break;
                }
                if ($current === '{') {
                    $prefix = trim($prefix . $name, '\\') . '\\';
                    $name = '';
                    $alias = null;
                    $readingAlias = false;
                    $braceDepth++;
                    continue;
                }
                if ($current === '}') {
                    $this->recordSemanticImport($name, $prefix, $alias, $aliases);
                    $name = '';
                    $alias = null;
                    $readingAlias = false;
                    $braceDepth--;
                    continue;
                }
                if ($current === ',') {
                    $this->recordSemanticImport($name, $prefix, $alias, $aliases);
                    $name = '';
                    $alias = null;
                    $readingAlias = false;
                    continue;
                }
                if (is_array($current) && $current[0] === T_AS) {
                    $aliasIndex = $this->nextSemanticToken($tokens, $part + 1);
                    if ($aliasIndex !== null && is_array($tokens[$aliasIndex])) {
                        $alias = $tokens[$aliasIndex][1];
                        $part = $aliasIndex;
                    }
                    $readingAlias = true;
                    continue;
                }
                if (!$readingAlias && is_array($current) && $this->isNameToken($current)) {
                    $name .= $current[1];
                } elseif (!$readingAlias && $current === '\\') {
                    $name .= '\\';
                }
            }
            if ($braceDepth > 0) {
                $this->recordSemanticImport($name, $prefix, $alias, $aliases);
            }
        }

        return $aliases;
    }

    /** @param array<string,string> $aliases */
    private function recordSemanticImport(string $name, string $prefix, ?string $alias, array &$aliases): void
    {
        $target = trim($prefix . $name, '\\');
        if ($target === '') {
            return;
        }
        $aliasName = $alias ?? $this->terminalSemanticName($target);
        $aliases[strtolower($aliasName)] = $target;
    }

    /** @param array{0:int,1:string,2:int} $token @param array<string,string> $aliases */
    private function resolveSemanticName(array $token, string $namespace, array $aliases): ?string
    {
        $name = $token[1];
        $lower = strtolower(trim($name, '\\'));
        if (in_array(
            $lower,
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
                'resource',
                'self',
                'static',
                'string',
                'true',
                'void',
                'parent',
            ],
            true,
        )) {
            return null;
        }
        if ($token[0] === T_NAME_FULLY_QUALIFIED || str_starts_with($name, '\\')) {
            return trim($name, '\\');
        }
        if ($token[0] === T_NAME_RELATIVE || str_starts_with(strtolower($name), 'namespace\\')) {
            $relative = substr($name, strlen('namespace\\'));

            return $namespace === '' ? $relative : $namespace . '\\' . $relative;
        }
        $parts = explode('\\', $name);
        $first = strtolower($parts[0]);
        if (isset($aliases[$first])) {
            $parts[0] = $aliases[$first];

            return implode('\\', $parts);
        }

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    private function terminalSemanticName(string $name): string
    {
        $parts = explode('\\', trim($name, '\\'));

        return $parts[count($parts) - 1];
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function addFunctionSignatureDependencies(array $tokens, int $functionIndex, callable $add): void
    {
        $open = $this->nextSemanticToken($tokens, $functionIndex + 1);
        while ($open !== null && $tokens[$open] !== '(' && $tokens[$open] !== '{' && $tokens[$open] !== ';') {
            $open = $this->nextSemanticToken($tokens, $open + 1);
        }
        if ($open === null || $tokens[$open] !== '(') {
            return;
        }
        $close = $this->matchingSemanticToken($tokens, $open, '(', ')');
        if ($close === null) {
            return;
        }
        $parameterStart = $open + 1;
        $depth = 0;
        for ($index = $parameterStart; $index < $close; $index++) {
            $token = $tokens[$index];
            if ($token === '(' || $token === '[' || $token === '{') {
                $depth++;
            } elseif ($token === ')' || $token === ']' || $token === '}') {
                $depth--;
            }
            if ($depth === 0 && $token === ',') {
                $parameterStart = $index + 1;
                continue;
            }
            if (is_array($token) && $token[0] === T_VARIABLE) {
                for ($parameter = $parameterStart; $parameter < $index; $parameter++) {
                    $add($tokens[$parameter]);
                }
                $parameterStart = $index + 1;
            }
        }
        $next = $this->nextSemanticToken($tokens, $close + 1);
        if ($next === null || $tokens[$next] !== ':') {
            return;
        }
        for ($index = $next + 1; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token === '{' || $token === ';' || $token === '=>' || $token === ',') {
                break;
            }
            $add($token);
        }
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function addAttributeDependencies(array $tokens, int $attributeIndex, callable $add): void
    {
        $combinedOpening = is_array($tokens[$attributeIndex]) && $tokens[$attributeIndex][1] === '#[';
        $open = $combinedOpening ? $attributeIndex : $this->nextSemanticToken($tokens, $attributeIndex + 1);
        if ($open === null || !$combinedOpening && $tokens[$open] !== '[') {
            return;
        }
        $squareDepth = 0;
        $parenthesisDepth = 0;
        $braceDepth = 0;
        for ($index = $open + 1; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token === '[') {
                $squareDepth++;
            } elseif ($token === ']') {
                if ($squareDepth === 0) {
                    return;
                }
                $squareDepth--;
            } elseif ($token === '(') {
                $parenthesisDepth++;
            } elseif ($token === ')') {
                $parenthesisDepth--;
            } elseif ($token === '{') {
                $braceDepth++;
            } elseif ($token === '}') {
                $braceDepth--;
            } elseif ($squareDepth === 0 && $parenthesisDepth === 0 && $braceDepth === 0 && $index === ($open + 1)) {
                $add($token);
            } elseif ($squareDepth === 0 && $parenthesisDepth === 0 && $braceDepth === 0 && $token === ',') {
                $next = $this->nextSemanticToken($tokens, $index + 1);
                if ($next !== null) {
                    $add($tokens[$next]);
                }
            }
        }
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function addPropertyDependencies(array $tokens, callable $add): void
    {
        $visibility = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }
            $start = $index - 1;
            $hasVisibility = false;
            while ($start >= 0) {
                $previous = $tokens[$start];
                if ($previous === ';' || $previous === '{' || $previous === '}' || $previous === '(') {
                    break;
                }
                if (is_array($previous) && in_array($previous[0], $visibility, true)) {
                    $hasVisibility = true;
                }
                if (is_array($previous) && $previous[0] === T_FUNCTION) {
                    break;
                }
                $start--;
            }
            if (!$hasVisibility) {
                continue;
            }
            for ($type = $start + 1; $type < $index; $type++) {
                $add($tokens[$type]);
            }
        }
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function matchingSemanticToken(array $tokens, int $open, string $opening, string $closing): ?int
    {
        $depth = 0;
        for ($index = $open; $index < count($tokens); $index++) {
            if ($tokens[$index] === $opening) {
                $depth++;
            } elseif ($tokens[$index] === $closing) {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function nextSemanticToken(array $tokens, int $index): ?int
    {
        while ($index < count($tokens)) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
            $index++;
        }

        return null;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function previousSemanticToken(array $tokens, int $index): ?int
    {
        while ($index >= 0) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
            $index--;
        }

        return null;
    }

    /** @param array{0:int,1:string,2:int} $token */
    private function isNameToken(array $token): bool
    {
        $types = [T_STRING];
        foreach (['T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE'] as $constant) {
            if (defined($constant)) {
                $types[] = constant($constant);
            }
        }

        return in_array($token[0], $types, true);
    }

    /** @return list<string> */
    private function parameterTypes(?\ReflectionMethod $method): array
    {
        if ($method === null) {
            return [];
        }

        return array_map(static function (\ReflectionParameter $parameter): string {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType) {
                return (string) $type;
            }

            return $type->allowsNull() && $type->getName() !== 'mixed' ? '?' . $type->getName() : $type->getName();
        }, $method->getParameters());
    }

    /** @param \ReflectionClass<object> $reflection @return list<string> */
    private function declaredPublicMethods(\ReflectionClass $reflection): array
    {
        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);

        return $methods;
    }
}
