<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Application\ApplicationAuditRuntime;
use BlackOps\Internal\Application\ApplicationAuditRuntimeComposer;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxState;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Relay\AuditIngressCanonicalizer;
use BlackOps\Internal\Audit\Relay\AuditOutboxRelayRuntime;
use BlackOps\Internal\Audit\Relay\AuditRelayException;
use BlackOps\Internal\Audit\Relay\AuditRelayFailure;
use BlackOps\Internal\Audit\Relay\AuditRelayOutcome;
use BlackOps\Internal\Audit\Relay\AuditRelayPersistence;
use BlackOps\Internal\Audit\Relay\PostgreSqlAuditRelayPersistence;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:no-insecure-comparison
 * @mago-expect lint:too-many-methods
 */
final class AuditRelayArchitectureTest extends TestCase
{
    public function testExactSevenFileInventoryDeclarationsAndModifiers(): void
    {
        $sources = $this->relaySources();
        self::assertSame(array_keys(self::expectedFiles()), array_keys($sources));
        self::assertSame(self::expectedDeclarations(), $this->declarationInventory($sources));

        $runtime = new ReflectionClass(AuditOutboxRelayRuntime::class);
        self::assertTrue($runtime->isFinal());
        self::assertTrue($runtime->isReadOnly());
        self::assertSame([], $runtime->getInterfaces() === [] ? [] : array_keys($runtime->getInterfaces()));
        $adapter = new ReflectionClass(PostgreSqlAuditRelayPersistence::class);
        self::assertTrue($adapter->isFinal());
        self::assertTrue($adapter->isReadOnly());
        self::assertSame([AuditRelayPersistence::class], array_keys($adapter->getInterfaces()));
        self::assertTrue(new ReflectionClass(AuditIngressCanonicalizer::class)->isFinal());
        self::assertTrue(new ReflectionClass(AuditIngressCanonicalizer::class)->isReadOnly());
        self::assertTrue(new ReflectionClass(AuditRelayException::class)->isFinal());
        self::assertFalse(new ReflectionClass(AuditRelayException::class)->isReadOnly());
        self::assertSame(
            \RuntimeException::class,
            new ReflectionClass(AuditRelayException::class)->getParentClass()?->getName(),
        );
        self::assertTrue(new ReflectionClass(AuditRelayFailure::class)->isEnum());
        self::assertTrue(new ReflectionClass(AuditRelayOutcome::class)->isEnum());
        self::assertSame([], new ReflectionClass(AuditRelayException::class)->getAttributes());
        self::assertSame([], new ReflectionClass(AuditRelayFailure::class)->getAttributes());
        self::assertSame([], new ReflectionClass(AuditRelayOutcome::class)->getAttributes());

        self::assertSame(
            [
                'TransactionInvalid' => 'transaction_invalid',
                'ResourceUnavailable' => 'resource_unavailable',
                'StorageUnavailable' => 'storage_unavailable',
            ],
            $this->enumCases(AuditRelayFailure::class),
        );
        self::assertSame(
            [
                'Idle' => 'idle',
                'RecoveredPending' => 'recovered_pending',
                'RecoveryPoisoned' => 'recovery_poisoned',
                'RelayedInserted' => 'relayed_inserted',
                'RelayedExisting' => 'relayed_existing',
                'Retried' => 'retried',
                'Poisoned' => 'poisoned',
                'Stale' => 'stale',
            ],
            $this->enumCases(AuditRelayOutcome::class),
        );
        self::assertSame(['MAX_SAFE_INTEGER' => 9_007_199_254_740_991], $runtime->getConstants());

        $extra = $sources;
        $extra['src/Internal/Audit/Relay/ExtraRelay.php'] = <<<'PHP'
            <?php

            namespace BlackOps\Internal\Audit\Relay;

            final class ExtraRelay {}
            PHP;
        self::assertContains('inventory', $this->inventoryViolations($extra));

        $extraType = $sources;
        $extraType['src/Internal/Audit/Relay/AuditRelayOutcome.php'] .= <<<'PHP'

            final class UnconsumedRelayType {}
            PHP;
        self::assertContains('declaration', $this->inventoryViolations($extraType));

        $wrongDeclaration = $sources;
        $wrongDeclaration['src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php'] = str_replace(
            'final readonly class PostgreSqlAuditRelayPersistence implements AuditRelayPersistence',
            'final readonly class PostgreSqlAuditRelayPersistence implements WrongPersistence',
            $wrongDeclaration['src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains('declaration', $this->inventoryViolations($wrongDeclaration));
    }

    public function testCallablePropertyAndSensitiveParameterMapsAreExact(): void
    {
        foreach ($this->expectedCallables() as $class => $expected) {
            self::assertSame($expected, $this->callableInventory($class), $class);
        }
        foreach ($this->expectedProperties() as $class => $expected) {
            self::assertSame($expected, $this->propertyInventory($class), $class);
        }

        self::assertSame([], $this->sourceCallableViolations($this->relaySources()));
        self::assertSame([], $this->sourcePropertyViolations($this->relaySources()));
        $mutated = $this->relaySources();
        $mutated['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'public function relayOne(): AuditRelayOutcome',
            'protected function relayOne(): AuditRelayOutcome',
            $mutated['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:relayOne',
            $this->sourceCallableViolations($mutated),
        );

        $propertyMutation = $this->relaySources();
        $propertyMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'private AuditRelayPersistence $persistence',
            'protected AuditRelayPersistence $persistence',
            $propertyMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'property:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:persistence',
            $this->sourcePropertyViolations($propertyMutation),
        );

        $returnMutation = $this->relaySources();
        $returnMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'public function recoverOne(): AuditRelayOutcome',
            'public function recoverOne(): AuditRelayFailure',
            $returnMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:recoverOne',
            $this->sourceCallableViolations($returnMutation),
        );

        $parameterTypeMutation = $this->relaySources();
        $parameterTypeMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'private function claimDetails(#[\\SensitiveParameter] AuditOutboxLookupResult $result)',
            'private function claimDetails(#[\\SensitiveParameter] string $result)',
            $parameterTypeMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:claimDetails',
            $this->sourceCallableViolations($parameterTypeMutation),
        );

        $nullabilityMutation = $this->relaySources();
        $nullabilityMutation['src/Internal/Audit/Relay/AuditRelayPersistence.php'] = str_replace(
            "public function poison(\n        #[\\SensitiveParameter]\n        string \$recordId,\n        #[\\SensitiveParameter]\n        ?AuditTypedReference \$expectedTenant",
            "public function poison(\n        #[\\SensitiveParameter]\n        string \$recordId,\n        #[\\SensitiveParameter]\n        AuditTypedReference \$expectedTenant",
            $nullabilityMutation['src/Internal/Audit/Relay/AuditRelayPersistence.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditRelayPersistence.php:poison',
            $this->sourceCallableViolations($nullabilityMutation),
        );

        $defaultMutation = $this->relaySources();
        $defaultMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'bool $retryable): AuditRelayOutcome',
            'bool $retryable = false): AuditRelayOutcome',
            $defaultMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:settleSenderFailure',
            $this->sourceCallableViolations($defaultMutation),
        );

        $sensitiveMutation = $this->relaySources();
        $sensitiveMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'private function claimDetails(#[\\SensitiveParameter] AuditOutboxLookupResult $result)',
            'private function claimDetails(AuditOutboxLookupResult $result)',
            $sensitiveMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:claimDetails',
            $this->sourceCallableViolations($sensitiveMutation),
        );

        $extraCallable = $this->relaySources();
        $extraCallable['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            '    public function recoverOne(): AuditRelayOutcome',
            "    private function unexpectedCallable(): void {}\n\n    public function recoverOne(): AuditRelayOutcome",
            $extraCallable['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertIsArray(token_get_all(
            $extraCallable['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            TOKEN_PARSE,
        ));
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:unexpectedCallable',
            $this->sourceCallableViolations($extraCallable),
        );

        $extraProperty = $this->relaySources();
        $extraProperty['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            "final readonly class AuditOutboxRelayRuntime\n{",
            "final readonly class AuditOutboxRelayRuntime\n{\n    private string \$unexpectedProperty;",
            $extraProperty['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertIsArray(token_get_all(
            $extraProperty['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            TOKEN_PARSE,
        ));
        self::assertContains(
            'property:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:unexpectedProperty',
            $this->sourcePropertyViolations($extraProperty),
        );
    }

    public function testDependenciesReferencesAndActivationMapsAreExact(): void
    {
        self::assertSame($this->expectedDependencies(), $this->dependencyInventory($this->relaySources()));
        self::assertSame([], $this->dependencyViolations($this->relaySources()));

        $sources = $this->allSourceFiles();
        $expectedRelayPaths = array_map(static fn(string $path): string => substr(
            $path,
            4,
        ), array_keys(self::expectedFiles()));
        sort($expectedRelayPaths);
        $actualRelayDynamic = [];
        foreach ($sources as $path => $source) {
            if (!str_starts_with($path, 'Internal/Audit/Relay/')) {
                continue;
            }
            $actualRelayDynamic[$path] = $this->dynamicInvocationViolations($source);
        }
        ksort($actualRelayDynamic);
        self::assertSame($expectedRelayPaths, array_keys($actualRelayDynamic));
        self::assertSame(array_fill_keys($expectedRelayPaths, []), $actualRelayDynamic);
        self::assertSame([], $this->targetAwareIndirectInvocationViolations($sources));
        self::assertSame([], $this->relayReferenceViolations($sources));

        $alias = <<<'PHP'
            <?php

            namespace BlackOps\External;

            use BlackOps\Internal\Audit\Relay\AuditRelayOutcome as Outcome;

            Outcome::Idle;
            PHP;
        self::assertStringContainsString(
            'outside-reference',
            $this->relayReferenceViolations(['External/Alias.php' => $alias])[0],
        );
        $group = <<<'PHP'
            <?php

            namespace BlackOps\External;

            use BlackOps\Internal\Audit\Relay\{AuditRelayFailure};

            AuditRelayFailure::StorageUnavailable;
            PHP;
        self::assertStringContainsString(
            'outside-reference',
            $this->relayReferenceViolations(['External/Group.php' => $group])[0],
        );
        $fqcn = <<<'PHP'
            <?php

            \BlackOps\Internal\Audit\Relay\AuditRelayFailure::StorageUnavailable;
            PHP;
        self::assertStringContainsString(
            'outside-reference',
            $this->relayReferenceViolations(['External/Fqcn.php' => $fqcn])[0],
        );
        $sameNamespace = <<<'PHP'
            <?php

            namespace BlackOps\Internal\Audit\Relay;

            AuditRelayOutcome::Idle;
            PHP;
        self::assertStringContainsString(
            'outside-reference',
            $this->relayReferenceViolations(['External/Same.php' => $sameNamespace])[0],
        );

        $activation = $sources;
        $activation['Internal/Unexpected/Caller.php'] = <<<'PHP'
            <?php

            use BlackOps\Internal\Audit\Relay\AuditOutboxRelayRuntime;

            $runtime->relayOne();
            new AuditOutboxRelayRuntime();
            PHP;
        self::assertNotEmpty($this->relayReferenceViolations($activation));
        self::assertNotEmpty($this->methodCallViolations($activation));

        $qualifiedDependency = $this->relaySources();
        $qualifiedDependency['src/Internal/Audit/Relay/AuditIngressCanonicalizer.php'] .= <<<'PHP'

            new Vendor\UnexpectedRelayDependency();
            PHP;
        self::assertContains(
            'dependency-qualified:src/Internal/Audit/Relay/AuditIngressCanonicalizer.php',
            $this->dependencyViolations($qualifiedDependency),
        );

        $fullyQualifiedDependency = $this->relaySources();
        $fullyQualifiedDependency['src/Internal/Audit/Relay/AuditIngressCanonicalizer.php'] .= <<<'PHP'

            new \Vendor\UnexpectedRelayDependency();
            PHP;
        self::assertContains(
            'dependency-fqcn:src/Internal/Audit/Relay/AuditIngressCanonicalizer.php',
            $this->dependencyViolations($fullyQualifiedDependency),
        );

        $workerMutation = $sources;
        $workerMutation['Internal/Execution/DeferredWorkerLoop.php'] .= <<<'PHP'

            $this->relay->relayOne();
            $this->other->recoverOne();
            PHP;
        $workerActivation = $this->applicationActivationViolations($workerMutation);
        self::assertContains('activation:relayOne:Internal/Execution/DeferredWorkerLoop.php', $workerActivation);
        self::assertContains('activation:recoverOne:Internal/Execution/DeferredWorkerLoop.php', $workerActivation);

        $classStrings = [
            'External/Literal.php' => <<<'PHP'
                <?php

                class_exists('BlackOps\Internal\Audit\Relay\AuditRelayOutcome');
                PHP,
            'External/OneHop.php' => <<<'PHP'
                <?php

                $one = 'BlackOps\Internal\Audit\Relay\AuditRelayFailure';
                $two = $one;
                new \ReflectionClass($two);
                PHP,
            'External/TwoHop.php' => <<<'PHP'
                <?php

                $one = <<<'RELAY_CLASS'
                BlackOps\Internal\Audit\Relay\AuditRelayException
                RELAY_CLASS;
                $two = $one;
                $three = $two;
                interface_exists($three);
                PHP,
            'External/Nowdoc.php' => <<<'PHP'
                <?php

                $one = <<<'RELAY_CLASS'
                BlackOps\Internal\Audit\Relay\AuditRelayOutcome
                RELAY_CLASS;
                $two = $one;
                class_exists($two);
                PHP,
        ];
        foreach ($classStrings as $path => $source) {
            self::assertIsArray(token_get_all($source, TOKEN_PARSE), $path);
            self::assertNotEmpty($this->classStringViolations([$path => $source]), $path);
        }
        $reflection = <<<'PHP'
            <?php

            new \ReflectionMethod('BlackOps\Internal\Audit\Relay\AuditRelayFailure', 'from');
            PHP;
        self::assertNotEmpty($this->classStringViolations(['External/Reflection.php' => $reflection]));

        $parenthesized = <<<'PHP'
            <?php

            use BlackOps\Internal\Audit\Relay\AuditRelayOutcome;

            class_exists((AuditRelayOutcome::class));
            PHP;
        self::assertSame(
            ['class-string:External/Parenthesized.php'],
            $this->classStringViolations(['External/Parenthesized.php' => $parenthesized]),
        );

        $dynamic = <<<'PHP'
            <?php

            $prefix = 'BlackOps\Internal\Audit\Relay\\';
            $class = $prefix . $suffix;
            class_exists($class);
            PHP;
        self::assertSame(
            ['dynamic-class-string:External/Dynamic.php'],
            $this->classStringViolations(['External/Dynamic.php' => $dynamic]),
        );

        $directDynamic = <<<'PHP'
            <?php

            $class = 'BlackOps\Internal\Audit\Relay\\' . $suffix;
            class_exists($class);
            PHP;
        self::assertIsArray(token_get_all($directDynamic, TOKEN_PARSE));
        self::assertSame(
            ['dynamic-class-string:External/DirectDynamic.php'],
            $this->classStringViolations(['External/DirectDynamic.php' => $directDynamic]),
        );

        $targetCallable = <<<'PHP'
            <?php

            $callable = [$relay, 'relayOne'];
            call_user_func($callable);
            PHP;
        self::assertIsArray(token_get_all($targetCallable, TOKEN_PARSE));
        self::assertContains('indirect:External/TargetCallable.php:callable-array', $this->targetAwareIndirectInvocationViolations([
            'External/TargetCallable.php' => $targetCallable,
        ]));

        $targetReflection = <<<'PHP'
            <?php

            $reflection = new \ReflectionMethod($relay, 'relayOne');
            $reflection->invoke($relay);
            PHP;
        self::assertIsArray(token_get_all($targetReflection, TOKEN_PARSE));
        self::assertContains('indirect:External/TargetReflection.php:reflection', $this->targetAwareIndirectInvocationViolations([
            'External/TargetReflection.php' => $targetReflection,
        ]));

        $targetParenthesized = <<<'PHP'
            <?php

            $callable = [$relay, 'relayOne'];
            (($callable))();
            PHP;
        self::assertIsArray(token_get_all($targetParenthesized, TOKEN_PARSE));
        self::assertContains('indirect:External/TargetParenthesized.php:parenthesized-call', $this->targetAwareIndirectInvocationViolations([
            'External/TargetParenthesized.php' => $targetParenthesized,
        ]));

        $targetClassString = <<<'PHP'
            <?php

            $class = 'BlackOps\Internal\Audit\Relay\AuditRelayFailure';
            $reflection = new \ReflectionClass($class);
            $reflection->newInstanceWithoutConstructor();
            PHP;
        self::assertIsArray(token_get_all($targetClassString, TOKEN_PARSE));
        self::assertContains('indirect:External/TargetClassString.php:class-string', $this->targetAwareIndirectInvocationViolations([
            'External/TargetClassString.php' => $targetClassString,
        ]));
    }

    public function testConstructionDirectCallsAndApplicationGraphAreExact(): void
    {
        $sources = $this->allSourceFiles();
        self::assertSame($this->expectedConstructors(), $this->constructorInventory($sources));
        self::assertSame($this->expectedMethodCalls(), $this->methodCallInventory($sources));
        self::assertSame($this->expectedMethodReceivers(), $this->methodReceiverInventory($sources));

        $composer = $sources['Internal/Application/ApplicationAuditRuntimeComposer.php'];
        self::assertSame(1, substr_count($composer, 'new AuditIngressCanonicalizer($body, $validator)'));
        self::assertSame(1, substr_count($composer, 'new PostgreSqlAuditRelayPersistence($outboxStore, $recordStore)'));
        self::assertSame(1, substr_count(
            $composer,
            "new AuditOutboxRelayRuntime(\n            \$operations->connection,\n            \$relayPersistence,\n            \$canonicalizer,\n            \$audit->outbox(),\n        )",
        ));
        self::assertSame(1, substr_count($composer, 'ApplicationAuditRuntime::fromComposition('));
        self::assertStringContainsString('$relay,', $composer);
        self::assertSame([], $this->applicationActivationViolations($sources));

        $validateRemoved = $sources;
        $validateRemoved['Internal/Audit/Relay/AuditIngressCanonicalizer.php'] = str_replace(
            '$validated = $this->validator->validateLogical($candidate);',
            '$validated = $candidate;',
            $validateRemoved['Internal/Audit/Relay/AuditIngressCanonicalizer.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'validateLogical|inventory',
            $this->methodCallViolations($validateRemoved),
            'The canonicalizer validator call must be required by the authority-owned call map.',
        );

        $validateExtra = $sources;
        $validateExtra['Internal/Audit/Relay/AuditIngressCanonicalizer.php'] = str_replace(
            '$validated = $this->validator->validateLogical($candidate);',
            '$validated = $this->validator->validateLogical($candidate);'
            . "\n        \$this->validator->validateLogical(\$candidate);",
            $validateExtra['Internal/Audit/Relay/AuditIngressCanonicalizer.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertIsArray(token_get_all(
            $validateExtra['Internal/Audit/Relay/AuditIngressCanonicalizer.php'],
            TOKEN_PARSE,
        ));
        self::assertContains(
            'validateLogical|Internal/Audit/Relay/AuditIngressCanonicalizer.php',
            $this->methodCallViolations($validateExtra),
        );

        $validateAlias = $sources;
        $validateAlias['External/ValidatorAlias.php'] = <<<'PHP'
            <?php

            $validatorAlias->validateLogical($candidate);
            PHP;
        self::assertContains(
            'validateLogical|External/ValidatorAlias.php|receiver',
            $this->methodCallViolations($validateAlias),
        );

        $removed = $sources;
        $removed['Internal/Application/ApplicationAuditRuntimeComposer.php'] = str_replace(
            'new AuditOutboxRelayRuntime(',
            'new RemovedRelayRuntime(',
            $removed['Internal/Application/ApplicationAuditRuntimeComposer.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'constructor:BlackOps\\Internal\\Audit\\Relay\\AuditOutboxRelayRuntime',
            $this->constructorViolations($removed),
        );

        $extraCaller = $sources;
        $extraCaller['External/RelayCaller.php'] = <<<'PHP'
            <?php

            $relay->relayOne();
            PHP;
        self::assertContains('relayOne|External/RelayCaller.php', $this->methodCallViolations($extraCaller));

        $unknownReceiver = $sources;
        $unknownReceiver['Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'return $this->settleRelay($claim, $inserted);',
            '$this->unknown->retry();' . "\n        return \$this->settleRelay(\$claim, \$inserted);",
            $unknownReceiver['Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'retry|Internal/Audit/Relay/AuditOutboxRelayRuntime.php|receiver',
            $this->methodCallViolations($unknownReceiver),
        );
    }

    public function testAuthorityMapRejectsCandidateSelfBaselineAndSharedWholePathBypass(): void
    {
        $renamedCallable = $this->relaySources();
        $renamedCallable['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            'public function relayOne(): AuditRelayOutcome',
            'public function relayRemoved(): AuditRelayOutcome',
            $renamedCallable['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains(
            'callable:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:relayOne',
            $this->sourceCallableViolations($renamedCallable),
            'The expected callable map must remain authority-owned when the candidate is mutated.',
        );

        $shared = $this->sharedArchitectureSources();
        self::assertSame([], $this->sharedWholePathBypassViolations($shared));
        $bypass = $shared;
        $bypass['tests/Architecture/AuditDeliveryArchitectureTest.php'] = str_replace(
            "            if (!\$isC3Owner) {\n                continue;\n            }",
            "            if (str_starts_with(\$path, 'src/Internal/Audit/Relay/')) {\n                continue;\n            }\n            if (!\$isC3Owner) {\n                continue;\n            }",
            $bypass['tests/Architecture/AuditDeliveryArchitectureTest.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertIsArray(token_get_all(
            $bypass['tests/Architecture/AuditDeliveryArchitectureTest.php'],
            TOKEN_PARSE,
        ));
        self::assertNotEmpty($this->sharedWholePathBypassViolations($bypass));
    }

    public function testMutationScannerRulesIgnoreDecoysAndRejectForbiddenCapabilities(): void
    {
        self::assertSame([], $this->forbiddenViolations($this->relaySources()));
        $forbidden = $this->relaySources();
        $forbidden['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            "final readonly class AuditOutboxRelayRuntime\n{",
            "final readonly class AuditOutboxRelayRuntime\n{\n    private function forbiddenTransactionProbe(): void\n    {\n        \$this->connection->beginTransaction();\n    }",
            $forbidden['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertIsArray(token_get_all(
            $forbidden['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            TOKEN_PARSE,
        ));
        self::assertContains(
            'forbidden:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:beginTransaction',
            $this->forbiddenViolations($forbidden),
        );

        $forbiddenCapabilities = $this->relaySources();
        $forbiddenCapabilities['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] = str_replace(
            "final readonly class AuditOutboxRelayRuntime\n{",
            "final readonly class AuditOutboxRelayRuntime\n{\n    private function forbiddenCapabilityProbe(): void\n    {\n        \$this->connection->executeQuery('SELECT 1');\n        \$this->connection->executeStatement('SELECT 1');\n        \$this->connection->fetchOne('SELECT 1');\n        \$this->connection->fetchAssociative('SELECT 1');\n        \$this->connection->prepare('SELECT 1');\n        \$this->connection->query('SELECT 1');\n        time();\n        microtime(true);\n        \$this->activeKey();\n        \$this->key();\n        \$this->nonce();\n        uuid();\n    }",
            $forbiddenCapabilities['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertIsArray(token_get_all(
            $forbiddenCapabilities['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'],
            TOKEN_PARSE,
        ));
        foreach ([
            'executeQuery(',
            'executeStatement(',
            'fetchOne(',
            'fetchAssociative(',
            'prepare(',
            'query(',
            'time(',
            'microtime(',
            'activeKey(',
            'key(',
            'nonce(',
            'uuid(',
        ] as $capability) {
            self::assertContains(
                'forbidden:src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:' . $capability,
                $this->forbiddenViolations($forbiddenCapabilities),
            );
        }

        $decoys = <<<'PHP'
            <?php

            // beginTransaction
            $comment = 'beginTransaction';
            $short = 'AuditRelayOutcome';
            $ordinary = "new AuditRelayFailure()";
            PHP;
        self::assertSame([], $this->forbiddenViolations(['External/Decoys.php' => $decoys]));
        self::assertSame([], $this->relayReferenceViolations(['External/Decoys.php' => $decoys]));

        $referenceMutation = $this->relaySources();
        $referenceMutation['src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php'] .= <<<'PHP'

            AuditRelayOutcome::Idle;
            PHP;
        self::assertContains(
            'src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php:outside-reference',
            $this->relayReferenceViolations($referenceMutation),
        );

        $indirect = <<<'PHP'
            <?php

            $callable = [$relay, 'relayOne'];
            call_user_func($callable);
            call_user_func_array($callable, []);
            $reflection = new \ReflectionMethod($relay, 'relayOne');
            $reflection->invoke($relay);
            $relay->{$method}();
            (($callable))();
            PHP;
        $indirectViolations = $this->dynamicInvocationViolations($indirect);
        self::assertContains('callable-array', $indirectViolations);
        self::assertContains('variable-call', $indirectViolations);
        self::assertContains('dynamic-call', $indirectViolations);

        $dynamicNew = <<<'PHP'
            <?php

            $class = $factory;
            new $class();
            PHP;
        self::assertContains('dynamic-new', $this->dynamicInvocationViolations($dynamicNew));

        self::assertSame([], $this->dynamicInvocationViolations("<?php (\$value + 1);"));
        $wholePath = $this->relaySources();
        $wholePath['src/Internal/Audit/Relay/Unexpected.php'] = <<<'PHP'
            <?php

            namespace BlackOps\Internal\Audit\Relay;

            final class Unexpected {}
            PHP;
        self::assertNotEmpty($this->inventoryViolations($wholePath));
    }

    /** @return array<string,class-string> */
    private static function expectedFiles(): array
    {
        return [
            'src/Internal/Audit/Relay/AuditIngressCanonicalizer.php' => AuditIngressCanonicalizer::class,
            'src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => AuditOutboxRelayRuntime::class,
            'src/Internal/Audit/Relay/AuditRelayException.php' => AuditRelayException::class,
            'src/Internal/Audit/Relay/AuditRelayFailure.php' => AuditRelayFailure::class,
            'src/Internal/Audit/Relay/AuditRelayOutcome.php' => AuditRelayOutcome::class,
            'src/Internal/Audit/Relay/AuditRelayPersistence.php' => AuditRelayPersistence::class,
            'src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => PostgreSqlAuditRelayPersistence::class,
        ];
    }

    /** @return array<string,array{name:class-string,kind:string,modifiers:list<string>,parent:?class-string,interfaces:list<class-string>,backing:?string}> */
    private static function expectedDeclarations(): array
    {
        return [
            'src/Internal/Audit/Relay/AuditIngressCanonicalizer.php' => [
                'name' => AuditIngressCanonicalizer::class,
                'kind' => 'class',
                'modifiers' => ['final', 'readonly'],
                'parent' => null,
                'interfaces' => [],
                'backing' => null,
            ],
            'src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => [
                'name' => AuditOutboxRelayRuntime::class,
                'kind' => 'class',
                'modifiers' => ['final', 'readonly'],
                'parent' => null,
                'interfaces' => [],
                'backing' => null,
            ],
            'src/Internal/Audit/Relay/AuditRelayException.php' => [
                'name' => AuditRelayException::class,
                'kind' => 'class',
                'modifiers' => ['final'],
                'parent' => \RuntimeException::class,
                'interfaces' => [],
                'backing' => null,
            ],
            'src/Internal/Audit/Relay/AuditRelayFailure.php' => [
                'name' => AuditRelayFailure::class,
                'kind' => 'enum',
                'modifiers' => [],
                'parent' => null,
                'interfaces' => [],
                'backing' => 'string',
            ],
            'src/Internal/Audit/Relay/AuditRelayOutcome.php' => [
                'name' => AuditRelayOutcome::class,
                'kind' => 'enum',
                'modifiers' => [],
                'parent' => null,
                'interfaces' => [],
                'backing' => 'string',
            ],
            'src/Internal/Audit/Relay/AuditRelayPersistence.php' => [
                'name' => AuditRelayPersistence::class,
                'kind' => 'interface',
                'modifiers' => [],
                'parent' => null,
                'interfaces' => [],
                'backing' => null,
            ],
            'src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => [
                'name' => PostgreSqlAuditRelayPersistence::class,
                'kind' => 'class',
                'modifiers' => ['final', 'readonly'],
                'parent' => null,
                'interfaces' => [AuditRelayPersistence::class],
                'backing' => null,
            ],
        ];
    }

    /** @return array<string,array<string,array<string,mixed>>> */
    private function expectedCallables(): array
    {
        $enum = [
            'cases' => $this->method('public', true, 'array', []),
            'from' => $this->method('public', true, 'static', [$this->parameter('value', 'string|int')]),
            'tryFrom' => $this->method('public', true, '?static', [$this->parameter('value', 'string|int')]),
        ];
        $exception = [
            '__construct' => $this->method('public', false, null, [$this->parameter(
                'failure',
                AuditRelayFailure::class,
            )]),
            'failure' => $this->method('public', false, AuditRelayFailure::class, []),
            'code' => $this->method('public', false, 'string', []),
            '__debugInfo' => $this->method('public', false, 'array', []),
            '__serialize' => $this->method('public', false, 'array', []),
            '__unserialize' => $this->method('public', false, 'void', [$this->parameter('data', 'array', false, 1)]),
        ];
        $persistence = [
            'recoverNextExpired' => $this->method('public', false, AuditOutboxLookupResult::class, [], true),
            'claimNext' => $this->method('public', false, AuditOutboxLookupResult::class, [], true),
            'appendCanonical' => $this->method(
                'public',
                false,
                AuditAppendResult::class,
                [$this->parameter('logical', CanonicalAuditLogicalRecord::class, false, 1)],
                true,
            ),
            'retry' => $this->settlementMethod(true),
            'relay' => $this->settlementMethod(true),
            'poison' => $this->settlementMethod(true),
        ];
        $adapter = $persistence;
        foreach ($adapter as &$signature) {
            $signature['abstract'] = false;
        }
        unset($signature);
        $adapter = [
            '__construct' => $this->method('public', false, null, [
                $this->parameter('outbox', PostgreSqlAuditOutboxStore::class),
                $this->parameter('records', 'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore'),
            ]),
            ...$adapter,
        ];
        $canonicalizer = [
            '__construct' => $this->method('public', false, null, [
                $this->parameter('protection', AuditProtectedBodyCodec::class),
                $this->parameter('validator', AuditRecordValidator::class),
            ]),
            'canonicalize' => $this->method('public', false, CanonicalAuditLogicalRecord::class, [$this->parameter(
                'record',
                AuditOutboxRecord::class,
                false,
                1,
            )]),
            'hasBindings' => $this->method('private', false, 'bool', [
                $this->parameter('bindings', 'array', false, 1),
                $this->parameter('envelope', 'array', false, 1),
            ]),
        ];
        $runtime = [
            '__construct' => $this->method('public', false, null, [
                $this->parameter('connection', Connection::class),
                $this->parameter('persistence', AuditRelayPersistence::class),
                $this->parameter('canonicalizer', AuditIngressCanonicalizer::class),
                $this->parameter('configuration', AuditOutboxConfiguration::class),
            ]),
            'recoverOne' => $this->method('public', false, AuditRelayOutcome::class, []),
            'relayOne' => $this->method('public', false, AuditRelayOutcome::class, []),
            'claimDetails' => $this->method('private', false, '?array', [
                $this->parameter('result', AuditOutboxLookupResult::class, false, 1),
            ]),
            'recoveryOutcome' => $this->method('private', false, AuditRelayOutcome::class, [
                $this->parameter('result', AuditOutboxLookupResult::class, false, 1),
            ]),
            'committedAppendInserted' => $this->method('private', false, 'bool', [
                $this->parameter('claim', 'array', false, 1),
                $this->parameter('append', AuditAppendResult::class, false, 1),
            ]),
            'settleSenderFailure' => $this->method('private', false, AuditRelayOutcome::class, [
                $this->parameter('claim', 'array', false, 1),
                $this->parameter('retryable', 'bool'),
            ]),
            'isRetryable' => $this->method('private', false, 'bool', [
                $this->parameter('failure', AuditStoreException::class, false, 1),
            ]),
            'settleRetry' => $this->method('private', false, AuditRelayOutcome::class, [
                $this->parameter('claim', 'array', false, 1),
            ]),
            'settlePoison' => $this->method('private', false, AuditRelayOutcome::class, [
                $this->parameter('claim', 'array', false, 1),
            ]),
            'settleRelay' => $this->method('private', false, AuditRelayOutcome::class, [
                $this->parameter('claim', 'array', false, 1),
                $this->parameter('inserted', 'bool'),
            ]),
            'assertSettlementState' => $this->method('private', false, 'void', [
                $this->parameter('record', AuditOutboxRecord::class, false, 1),
                $this->parameter('expected', AuditOutboxState::class),
            ]),
            'assertConnectionState' => $this->method('private', false, 'void', []),
            'lookupFailure' => $this->method('private', false, AuditRelayOutcome::class, [
                $this->parameter('failure', AuditOutboxStoreException::class, false, 1),
            ]),
            'settlementFailure' => $this->method('private', false, AuditRelayOutcome::class, [
                $this->parameter('failure', AuditOutboxStoreException::class, false, 1),
            ]),
            'tenant' => $this->method('private', false, '?' . AuditTypedReference::class, [
                $this->parameter('value', 'mixed', true, 1),
            ]),
            'isUuidV7' => $this->method('private', true, 'bool', [
                $this->parameter('value', 'string', false, 1),
            ]),
            'isPartition' => $this->method('private', true, 'bool', [
                $this->parameter('value', 'string', false, 1),
            ]),
            'isHash' => $this->method('private', true, 'bool', [
                $this->parameter('value', 'string', false, 1),
            ]),
        ];

        $result = [
            AuditRelayFailure::class => $enum,
            AuditRelayOutcome::class => $enum,
            AuditRelayException::class => $exception,
            AuditRelayPersistence::class => $persistence,
            PostgreSqlAuditRelayPersistence::class => $adapter,
            AuditIngressCanonicalizer::class => $canonicalizer,
            AuditOutboxRelayRuntime::class => $runtime,
        ];
        foreach ($result as &$methods) {
            ksort($methods);
        }
        unset($methods);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function expectedProperties(): array
    {
        $none = [];
        $enumProperties = [
            'name' => $this->property('public', true, false, 'string'),
            'value' => $this->property('public', true, false, 'string'),
        ];
        $result = [
            AuditRelayFailure::class => $enumProperties,
            AuditRelayOutcome::class => $enumProperties,
            AuditRelayException::class => [
                'failure' => $this->property('private', true, true, AuditRelayFailure::class),
            ],
            AuditRelayPersistence::class => $none,
            PostgreSqlAuditRelayPersistence::class => [
                'outbox' => $this->property('private', true, true, PostgreSqlAuditOutboxStore::class),
                'records' => $this->property(
                    'private',
                    true,
                    true,
                    'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore',
                ),
            ],
            AuditIngressCanonicalizer::class => [
                'protection' => $this->property('private', true, true, AuditProtectedBodyCodec::class),
                'validator' => $this->property('private', true, true, AuditRecordValidator::class),
            ],
            AuditOutboxRelayRuntime::class => [
                'connection' => $this->property('private', true, true, Connection::class),
                'persistence' => $this->property('private', true, true, AuditRelayPersistence::class),
                'canonicalizer' => $this->property('private', true, true, AuditIngressCanonicalizer::class),
                'configuration' => $this->property('private', true, true, AuditOutboxConfiguration::class),
            ],
        ];
        foreach ($result as &$properties) {
            ksort($properties);
        }
        unset($properties);

        return $result;
    }

    /** @return array<string,list<string>> */
    private function expectedDependencies(): array
    {
        return [
            'src/Internal/Audit/Relay/AuditIngressCanonicalizer.php' => [
                'use BlackOps\\Core\\Audit\\AuditDelivery;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException;',
                'use BlackOps\\Internal\\Audit\\Record\\AuditLogicalRecordCandidate;',
                'use BlackOps\\Internal\\Audit\\Record\\AuditRecordValidator;',
                'use BlackOps\\Internal\\Audit\\Record\\CanonicalAuditLogicalRecord;',
            ],
            'src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => [
                'use BlackOps\\Core\\Audit\\AuditTypedReference;',
                'use BlackOps\\Core\\Audit\\AuditValidationException;',
                'use BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration;',
                'use BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxState;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxLookupResult;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreException;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxStoreFailure;',
                'use BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException;',
                'use BlackOps\\Internal\\Audit\\Storage\\AuditAppendResult;',
                'use BlackOps\\Internal\\Audit\\Storage\\AuditStoreException;',
                'use BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure;',
                'use Doctrine\\DBAL\\Connection;',
            ],
            'src/Internal/Audit/Relay/AuditRelayException.php' => ['use RuntimeException;'],
            'src/Internal/Audit/Relay/AuditRelayFailure.php' => [],
            'src/Internal/Audit/Relay/AuditRelayOutcome.php' => [],
            'src/Internal/Audit/Relay/AuditRelayPersistence.php' => [
                'use BlackOps\\Core\\Audit\\AuditTypedReference;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxLookupResult;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord;',
                'use BlackOps\\Internal\\Audit\\Record\\CanonicalAuditLogicalRecord;',
                'use BlackOps\\Internal\\Audit\\Storage\\AuditAppendResult;',
            ],
            'src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => [
                'use BlackOps\\Core\\Audit\\AuditTypedReference;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxLookupResult;',
                'use BlackOps\\Internal\\Audit\\Outbox\\AuditOutboxRecord;',
                'use BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore;',
                'use BlackOps\\Internal\\Audit\\Record\\CanonicalAuditLogicalRecord;',
                'use BlackOps\\Internal\\Audit\\Storage\\AuditAppendResult;',
                'use BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore;',
            ],
        ];
    }

    /** @return array<string,array<string,int>> */
    private function expectedConstructors(): array
    {
        return [
            AuditIngressCanonicalizer::class => ['Internal/Application/ApplicationAuditRuntimeComposer.php' => 1],
            AuditOutboxRelayRuntime::class => ['Internal/Application/ApplicationAuditRuntimeComposer.php' => 1],
            AuditRelayException::class => [
                'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 17,
                'Internal/Audit/Relay/AuditRelayException.php' => 1,
            ],
            PostgreSqlAuditRelayPersistence::class => ['Internal/Application/ApplicationAuditRuntimeComposer.php' => 1],
        ];
    }

    /** @return array<string,array<string,int>> */
    private function expectedMethodCalls(): array
    {
        return [
            'canonicalize' => ['Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 1],
            'validateLogical' => ['Internal/Audit/Relay/AuditIngressCanonicalizer.php' => 1],
            'recoverNextExpired' => [
                'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 1,
                'Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => 1,
            ],
            'claimNext' => [
                'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 1,
                'Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => 1,
            ],
            'appendCanonical' => ['Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 1],
            'retry' => [
                'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 1,
                'Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => 1,
            ],
            'relay' => [
                'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 1,
                'Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => 1,
            ],
            'poison' => [
                'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => 1,
                'Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => 1,
            ],
            'recoverOne' => [],
            'relayOne' => [],
        ];
    }

    /** @return array<string,list<string>> */
    private function expectedMethodReceivers(): array
    {
        $result = [
            'canonicalize|src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => ['$this->canonicalizer'],
            'validateLogical|src/Internal/Audit/Relay/AuditIngressCanonicalizer.php' => ['$this->validator'],
            'recoverNextExpired|src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => ['$this->persistence'],
            'recoverNextExpired|src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => ['$this->outbox'],
            'claimNext|src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => ['$this->persistence'],
            'claimNext|src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => ['$this->outbox'],
            'appendCanonical|src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => ['$this->persistence'],
            'retry|src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => ['$this->persistence'],
            'retry|src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => ['$this->outbox'],
            'relay|src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => ['$this->persistence'],
            'relay|src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => ['$this->outbox'],
            'poison|src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => ['$this->persistence'],
            'poison|src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => ['$this->outbox'],
        ];
        ksort($result);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function method(
        string $visibility,
        bool $static,
        ?string $return,
        array $parameters,
        bool $abstract = false,
    ): array {
        return [
            'visibility' => $visibility,
            'static' => $static,
            'abstract' => $abstract,
            'final' => false,
            'return' => $this->type($return, $return !== null && str_starts_with($return, '?')),
            'parameters' => $parameters,
        ];
    }

    /** @return array<string,mixed> */
    private function parameter(
        string $name,
        string $type,
        bool $nullable = false,
        int $sensitive = 0,
        bool $optional = false,
    ): array {
        return [
            'name' => $name,
            'type' => ltrim($type, '?'),
            'nullable' => $nullable,
            'optional' => $optional,
            'variadic' => false,
            'byReference' => false,
            'default' => null,
            'sensitive' => $sensitive,
        ];
    }

    /** @return array<string,mixed> */
    private function type(?string $name, bool $nullable = false): array
    {
        return ['type' => $name === null ? null : ltrim($name, '?'), 'nullable' => $nullable];
    }

    /** @return array<string,mixed> */
    private function property(string $visibility, bool $readonly, bool $promoted, string $type): array
    {
        return [
            'visibility' => $visibility,
            'readonly' => $readonly,
            'promoted' => $promoted,
            'type' => ltrim($type, '?'),
            'nullable' => str_starts_with($type, '?'),
            'sensitive' => 0,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function settlementMethod(bool $abstract): array
    {
        return $this->method(
            'public',
            false,
            AuditOutboxRecord::class,
            [
                $this->parameter('recordId', 'string', false, 1),
                $this->parameter('expectedTenant', AuditTypedReference::class, true, 1),
                $this->parameter('expectedPartitionId', 'string', false, 1),
                $this->parameter('expectedVersion', 'int', false, 1),
                $this->parameter('expectedToken', 'string', false, 1),
            ],
            $abstract,
        );
    }

    /** @return array<string,string> */
    private function relaySources(): array
    {
        $root = $this->repositoryRoot() . '/src/Internal/Audit/Relay';
        $sources = [];
        foreach (glob($root . '/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources['src/Internal/Audit/Relay/' . basename($path)] = $source;
        }
        ksort($sources);
        return $sources;
    }

    /** @return array<string,string> */
    private function sharedArchitectureSources(): array
    {
        $paths = [
            'tests/Architecture/AuditOutboxStateArchitectureTest.php',
            'tests/Architecture/AuditOutboxStorageArchitectureTest.php',
            'tests/Architecture/AuditRecordArchitectureTest.php',
            'tests/Architecture/AuditStoreArchitectureTest.php',
            'tests/Architecture/AuditDeliveryArchitectureTest.php',
        ];
        $sources = [];
        foreach ($paths as $path) {
            $source = file_get_contents($this->repositoryRoot() . '/' . $path);
            self::assertIsString($source);
            $sources[$path] = $source;
        }
        return $sources;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function sharedWholePathBypassViolations(array $sources): array
    {
        $violations = [];
        foreach ($sources as $path => $source) {
            $matched = preg_match(
                '/str_starts_with\s*\(\s*\$path\s*,\s*[\'\"]src\/Internal\/Audit\/Relay\/[\'\"]\s*\)/',
                $source,
                $match,
                PREG_OFFSET_CAPTURE,
            );
            if ($matched !== 1) {
                continue;
            }
            $offset = $match[0][1] + strlen($match[0][0]);
            if (preg_match('/\bcontinue\s*;/', substr($source, $offset, 320)) === 1) {
                $violations[] = 'shared-whole-path:' . $path;
            }
        }
        return $violations;
    }

    /** @return array<string,string> */
    private function allSourceFiles(): array
    {
        $root = $this->repositoryRoot() . '/src';
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $sources[str_replace($root . '/', '', $file->getPathname())] = $source;
        }
        ksort($sources);
        return $sources;
    }

    private function repositoryRoot(): string
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertIsString($root);
        return $root;
    }

    /** @param array<string,string> $sources @return array<string,array{name:class-string,kind:string,modifiers:list<string>,parent:?class-string,interfaces:list<class-string>,backing:?string}> */
    private function declarationInventory(array $sources): array
    {
        $inventory = [];
        foreach ($sources as $path => $source) {
            $declarations = $this->sourceDeclarations($source);
            if (count($declarations) !== 1) {
                $inventory[$path] = $declarations[0] ?? [
                    'name' => '',
                    'kind' => '',
                    'modifiers' => [],
                    'parent' => null,
                    'interfaces' => [],
                    'backing' => null,
                ];
                continue;
            }
            $inventory[$path] = $declarations[0];
        }
        ksort($inventory);
        return $inventory;
    }

    /** @return list<array{name:class-string,kind:string,modifiers:list<string>,parent:?class-string,interfaces:list<class-string>,backing:?string}> */
    private function sourceDeclarations(string $source): array
    {
        $code = $this->codeWithoutCommentsAndStrings($source);
        preg_match('/\bnamespace\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*;/m', $code, $namespaceMatch);
        $namespace = $namespaceMatch[1] ?? '';
        preg_match_all(
            '/(?<modifiers>(?:(?:final|readonly)\s+)*)'
            . '(?<kind>class|interface|enum)\s+'
            . '(?<name>[A-Za-z_][A-Za-z0-9_]*)'
            . '(?:\s*:\s*(?<backing>string|int))?'
            . '(?:\s+extends\s+(?<parent>[A-Za-z_][A-Za-z0-9_\\\\]*))?'
            . '(?:\s+implements\s+(?<interfaces>[A-Za-z_][A-Za-z0-9_\\\\]*(?:\s*,\s*[A-Za-z_][A-Za-z0-9_\\\\]*)*))?/',
            $code,
            $matches,
            PREG_SET_ORDER,
        );
        $context = $this->nameContext($source);
        $result = [];
        foreach ($matches as $match) {
            $modifiers = trim((string) $match['modifiers']);
            $modifiers = $modifiers === '' ? [] : preg_split('/\s+/', $modifiers);
            self::assertIsArray($modifiers);
            $interfaces = [];
            if (($match['interfaces'] ?? '') !== '') {
                foreach (preg_split('/\s*,\s*/', $match['interfaces']) ?: [] as $interface) {
                    $interfaces[] = $this->resolveName($interface, $context['namespace'], $context['aliases']);
                }
            }
            $result[] = [
                'name' => $this->qualify((string) $match['name'], $namespace),
                'kind' => $match['kind'],
                'modifiers' => $modifiers,
                'parent' => ($match['parent'] ?? '') === ''
                    ? null
                    : $this->resolveName($match['parent'], $context['namespace'], $context['aliases']),
                'interfaces' => $interfaces,
                'backing' => ($match['backing'] ?? '') === '' ? null : $match['backing'],
            ];
        }
        return $result;
    }

    /** @param class-string $class @return array<string,array<string,mixed>> */
    private function callableInventory(string $class): array
    {
        $inventory = [];
        foreach (new ReflectionClass($class)->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $inventory[$method->getName()] = [
                'visibility' => $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private'),
                'static' => $method->isStatic(),
                'abstract' => $method->isAbstract(),
                'final' => $method->isFinal(),
                'return' => $this->reflectionType($method->getReturnType()),
                'parameters' => array_map(fn(ReflectionParameter $parameter): array => $this->reflectionParameter(
                    $parameter,
                ), $method->getParameters()),
            ];
        }
        ksort($inventory);
        return $inventory;
    }

    /** @param class-string $class @return array<string,array<string,mixed>> */
    private function propertyInventory(string $class): array
    {
        $inventory = [];
        foreach (new ReflectionClass($class)->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $type = $property->getType();
            $inventory[$property->getName()] = [
                'visibility' => $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private'),
                'readonly' => $property->isReadOnly(),
                'promoted' => $property->isPromoted(),
                'type' => $type instanceof ReflectionNamedType
                    ? $type->getName()
                    : ($type === null ? null : (string) $type),
                'nullable' => $type?->allowsNull() ?? false,
                'sensitive' => count($property->getAttributes(\SensitiveParameter::class)),
            ];
        }
        ksort($inventory);
        return $inventory;
    }

    /** @return array<string,mixed> */
    private function reflectionParameter(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        return [
            'name' => $parameter->getName(),
            'type' => $type instanceof ReflectionNamedType
                ? $type->getName()
                : ($type === null ? null : (string) $type),
            'nullable' => $type?->allowsNull() ?? false,
            'optional' => $parameter->isOptional(),
            'variadic' => $parameter->isVariadic(),
            'byReference' => $parameter->isPassedByReference(),
            'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            'sensitive' => count($parameter->getAttributes(\SensitiveParameter::class)),
        ];
    }

    /** @return array<string,mixed> */
    private function reflectionType(?ReflectionType $type): array
    {
        return [
            'type' => $type instanceof ReflectionNamedType
                ? $type->getName()
                : ($type === null ? null : (string) $type),
            'nullable' => $type?->allowsNull() ?? false,
        ];
    }

    /** @param class-string $class @return array<string,string> */
    private function enumCases(string $class): array
    {
        $result = [];
        foreach (new ReflectionEnum($class)->getCases() as $case) {
            $result[$case->getName()] = $case->getBackingValue();
        }
        return $result;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function inventoryViolations(array $sources): array
    {
        $violations = [];
        $expected = self::expectedDeclarations();
        $expectedPaths = array_keys($expected);
        $actualPaths = array_keys($sources);
        sort($actualPaths);
        if ($actualPaths !== $expectedPaths) {
            $violations[] = 'inventory';
        }
        foreach ($sources as $path => $source) {
            $declarations = $this->sourceDeclarations($source);
            if (count($declarations) !== 1 || ($expected[$path] ?? null) !== ($declarations[0] ?? null)) {
                $violations[] = 'declaration';
            }
        }
        return array_values(array_unique($violations));
    }

    /** @param array<string,string> $sources @return list<string> */
    private function sourceCallableViolations(array $sources): array
    {
        $violations = [];
        foreach (self::expectedFiles() as $path => $class) {
            $actual = $this->sourceCallableMap($sources[$path] ?? '');
            $expected = $this->expectedCallables()[$class] ?? [];
            $sourceExpected = in_array($class, [AuditRelayFailure::class, AuditRelayOutcome::class], true)
                ? []
                : $expected;
            $actualNames = array_keys($actual);
            $expectedNames = array_keys($sourceExpected);
            sort($actualNames);
            sort($expectedNames);
            if ($actualNames !== $expectedNames) {
                foreach (array_unique(array_merge($actualNames, $expectedNames)) as $name) {
                    if (in_array($name, $actualNames, true) !== in_array($name, $expectedNames, true)) {
                        $violations[] = 'callable:' . $path . ':' . $name;
                    }
                }
            }
            foreach ($sourceExpected as $name => $signature) {
                if (
                    ($actual[$name] ?? null) !== [
                        'visibility' => $signature['visibility'],
                        'static' => $signature['static'],
                    ]
                ) {
                    $violations[] = 'callable:' . $path . ':' . $name;
                }
            }
            $actualSignatures = $this->sourceCallableSignatureMap($sources[$path] ?? '');
            foreach ($sourceExpected as $name => $signature) {
                $expectedSignature = [
                    'return' => $this->sourceType($signature['return']),
                    'parameters' => array_map(fn(array $parameter): array => $this->sourceParameter(
                        $parameter,
                    ), $signature['parameters']),
                ];
                if (($actualSignatures[$name] ?? null) !== $expectedSignature) {
                    $violations[] = 'callable:' . $path . ':' . $name;
                }
            }
        }
        return array_values(array_unique($violations));
    }

    /** @return array<string,array{visibility:string,static:bool}> */
    private function sourceCallableMap(string $source): array
    {
        $code = $this->codeWithoutCommentsAndStrings($source);
        preg_match_all(
            '/(?<visibility>public|protected|private)\s+(?<static>static\s+)?function\s+(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*\(/',
            $code,
            $matches,
            PREG_SET_ORDER,
        );
        $result = [];
        foreach ($matches as $match) {
            $result[$match['name']] = [
                'visibility' => $match['visibility'],
                'static' => ($match['static'] ?? '') !== '',
            ];
        }
        return $result;
    }

    /** @return array<string,array{return:?string,parameters:list<array<string,mixed>>}> */
    private function sourceCallableSignatureMap(string $source): array
    {
        $tokens = token_get_all($source);
        $result = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $name = $this->nextToken($tokens, $index + 1);
            if ($name !== null && $tokens[$name] === '&') {
                $name = $this->nextToken($tokens, $name + 1);
            }
            if ($name === null || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING) {
                continue;
            }
            $open = $this->nextToken($tokens, $name + 1);
            if ($open === null || $tokens[$open] !== '(') {
                continue;
            }
            $close = $this->matchingToken($tokens, $open, '(', ')');
            if ($close === null) {
                continue;
            }
            $return = null;
            $returnStart = $this->nextToken($tokens, $close + 1);
            if ($returnStart !== null && $tokens[$returnStart] === ':') {
                $parts = [];
                for (
                    $cursor = $this->nextToken($tokens, $returnStart + 1);
                    $cursor !== null;
                    $cursor = $this->nextToken($tokens, $cursor + 1)
                ) {
                    if ($tokens[$cursor] === '{' || $tokens[$cursor] === ';') {
                        break;
                    }
                    if (
                        is_array($tokens[$cursor])
                        && in_array($tokens[$cursor][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                    ) {
                        continue;
                    }
                    $parts[] = is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
                }
                $return = implode('', $parts);
            }
            $result[$tokens[$name][1]] = [
                'return' => $return,
                'parameters' => $this->sourceParameters(array_slice($tokens, $open + 1, $close - $open - 1)),
            ];
        }

        ksort($result);

        return $result;
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens @return list<array<string,mixed>> */
    private function sourceParameters(array $tokens): array
    {
        $segments = [];
        $segment = [];
        $depth = ['(' => 0, '[' => 0, '{' => 0];
        $attributeDepth = 0;
        foreach ($tokens as $token) {
            if (is_array($token) && defined('T_ATTRIBUTE') && $token[0] === T_ATTRIBUTE) {
                $attributeDepth = 1;
                $segment[] = $token;
                continue;
            }
            if ($attributeDepth > 0) {
                $segment[] = $token;
                if ($token === ']') {
                    $attributeDepth = 0;
                }
                continue;
            }
            if ($token === '(') {
                ++$depth['('];
            } elseif ($token === ')') {
                --$depth['('];
            } elseif ($token === '[') {
                ++$depth['['];
            } elseif ($token === ']') {
                --$depth['['];
            } elseif ($token === '{') {
                ++$depth['{'];
            } elseif ($token === '}') {
                --$depth['{'];
            }
            if ($token === ',' && $depth === ['(' => 0, '[' => 0, '{' => 0]) {
                if ($segment !== []) {
                    $segments[] = $segment;
                }
                $segment = [];
                continue;
            }
            $segment[] = $token;
        }
        if ($segment !== []) {
            $segments[] = $segment;
        }

        $result = [];
        foreach ($segments as $segment) {
            $variable = null;
            foreach ($segment as $index => $token) {
                if (is_array($token) && $token[0] === T_VARIABLE) {
                    $variable = $index;
                    break;
                }
            }
            if ($variable === null || !is_array($segment[$variable])) {
                continue;
            }
            $before = array_slice($segment, 0, $variable);
            $after = array_slice($segment, $variable + 1);
            $sensitive = 0;
            $typeParts = [];
            $attributeDepth = 0;
            foreach ($before as $token) {
                if (is_array($token) && defined('T_ATTRIBUTE') && $token[0] === T_ATTRIBUTE) {
                    $attributeDepth = 1;
                    continue;
                }
                if ($attributeDepth > 0) {
                    if (
                        is_array($token)
                        && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
                        && ltrim($token[1], '\\') === 'SensitiveParameter'
                    ) {
                        ++$sensitive;
                    }
                    if ($token === ']') {
                        $attributeDepth = 0;
                    }
                    continue;
                }
                if ($token === '[' && $attributeDepth > 0) {
                    ++$attributeDepth;
                    continue;
                }
                if ($token === ']' && $attributeDepth > 0) {
                    --$attributeDepth;
                    continue;
                }
                if ($token === '#') {
                    $attributeDepth = 1;
                    continue;
                }
                if ($attributeDepth > 0) {
                    if (
                        is_array($token)
                        && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
                        && ltrim($token[1], '\\') === 'SensitiveParameter'
                    ) {
                        ++$sensitive;
                    }
                    continue;
                }
                if (
                    is_array($token)
                    && in_array(
                        $token[0],
                        [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY],
                        true,
                    )
                ) {
                    continue;
                }
                if ($token !== '&' && $token !== '...') {
                    $typeParts[] = is_array($token) ? $token[1] : $token;
                }
            }
            $type = implode('', $typeParts);
            $nullable =
                $type === 'mixed'
                || str_starts_with($type, '?')
                || str_contains($type, '|null')
                || str_contains($type, 'null|');
            $type = ltrim($type, '?');
            $optional = false;
            $default = null;
            $equals = null;
            foreach ($after as $index => $token) {
                if ($token === '=') {
                    $equals = $index;
                    break;
                }
            }
            if ($equals !== null) {
                $optional = true;
                $defaultParts = [];
                foreach (array_slice($after, $equals + 1) as $token) {
                    if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $defaultParts[] = is_array($token) ? $token[1] : $token;
                }
                $default = implode('', $defaultParts);
            }
            $result[] = [
                'name' => ltrim($segment[$variable][1], '$'),
                'type' => $type,
                'nullable' => $nullable,
                'optional' => $optional,
                'variadic' => in_array('...', $before, true),
                'byReference' => in_array('&', $before, true),
                'default' => $default,
                'sensitive' => $sensitive,
            ];
        }

        return $result;
    }

    /** @param array<string,mixed> $type */
    private function sourceType(array $type): ?string
    {
        $name = $type['type'];
        if ($name === null) {
            return null;
        }
        if (is_string($name) && str_contains($name, '\\')) {
            $name = basename(str_replace('\\', '/', $name));
        }

        return $type['nullable'] ? '?' . $name : $name;
    }

    /** @param array<string,mixed> $parameter @return array<string,mixed> */
    private function sourceParameter(array $parameter): array
    {
        $type = $parameter['type'];
        if (is_string($type) && str_contains($type, '\\')) {
            $type = basename(str_replace('\\', '/', $type));
        }

        return [
            'name' => ltrim($parameter['name'], '$'),
            'type' => $type,
            'nullable' => $parameter['nullable'],
            'optional' => $parameter['optional'],
            'variadic' => $parameter['variadic'],
            'byReference' => $parameter['byReference'],
            'default' => $parameter['default'],
            'sensitive' => $parameter['sensitive'],
        ];
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function matchingToken(array $tokens, int $start, string $open, string $close): ?int
    {
        $depth = 0;
        for ($index = $start; $index < count($tokens); ++$index) {
            if ($tokens[$index] === $open) {
                ++$depth;
            } elseif ($tokens[$index] === $close) {
                --$depth;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function sourcePropertyViolations(array $sources): array
    {
        $violations = [];
        foreach (self::expectedFiles() as $path => $class) {
            $expected = in_array($class, [AuditRelayFailure::class, AuditRelayOutcome::class], true)
                ? []
                : $this->expectedProperties()[$class] ?? [];
            $actual = $this->sourcePropertyMap($sources[$path] ?? '');
            $actualNames = array_keys($actual);
            $expectedNames = array_keys($expected);
            sort($actualNames);
            sort($expectedNames);
            if ($actualNames !== $expectedNames) {
                foreach (array_unique(array_merge($actualNames, $expectedNames)) as $name) {
                    if (in_array($name, $actualNames, true) !== in_array($name, $expectedNames, true)) {
                        $violations[] = 'property:' . $path . ':' . $name;
                    }
                }
            }
            foreach ($expected as $name => $property) {
                if (
                    ($actual[$name] ?? null) !== [
                        'visibility' => $property['visibility'],
                        'readonly' => $property['readonly'],
                    ]
                ) {
                    $violations[] = 'property:' . $path . ':' . $name;
                }
            }
        }
        return array_values(array_unique($violations));
    }

    /** @return array<string,array{visibility:string,readonly:bool}> */
    private function sourcePropertyMap(string $source): array
    {
        $code = $this->codeWithoutCommentsAndStrings($source);
        $classReadonly = preg_match('/\breadonly\s+class\b/', $code) === 1;
        preg_match_all(
            '/(?<visibility>public|protected|private)\s+(?<readonly>readonly\s+)?(?:[?A-Za-z_][A-Za-z0-9_\\\\|]*)\s+\$(?<name>[A-Za-z_][A-Za-z0-9_]*)/',
            $code,
            $matches,
            PREG_SET_ORDER,
        );
        $result = [];
        foreach ($matches as $match) {
            $result[$match['name']] = [
                'visibility' => $match['visibility'],
                'readonly' => ($match['readonly'] ?? '') !== '' || $classReadonly,
            ];
        }
        return $result;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function dependencyInventory(array $sources): array
    {
        $result = [];
        foreach ($sources as $path => $source) {
            $code = $this->codeWithoutComments($source);
            $declaration =
                preg_split('/\b(?:final\s+readonly\s+|final\s+|readonly\s+)?(?:class|interface|enum)\b/', $code, 2)[0]
                ?? $code;
            preg_match_all('/\buse\s+[^;]+;/', $declaration, $matches);
            $result[$path] = array_map(
                static fn(string $value): string => preg_replace('/\s+/', ' ', trim($value)) ?? trim($value),
                $matches[0],
            );
        }
        ksort($result);
        return $result;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function dependencyViolations(array $sources): array
    {
        $violations = [];
        $actualInventory = $this->dependencyInventory($sources);
        foreach ($this->expectedDependencies() as $path => $expected) {
            if (($actualInventory[$path] ?? null) !== $expected) {
                $violations[] = 'dependency:' . $path;
            }
        }
        $fullyQualified = [
            'src/Internal/Audit/Relay/AuditIngressCanonicalizer.php' => ['\\SensitiveParameter'],
            'src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => [
                '\\SensitiveParameter',
                '\\Throwable',
                '\\RuntimeException',
            ],
            'src/Internal/Audit/Relay/AuditRelayException.php' => ['\\SensitiveParameter'],
            'src/Internal/Audit/Relay/AuditRelayFailure.php' => [],
            'src/Internal/Audit/Relay/AuditRelayOutcome.php' => [],
            'src/Internal/Audit/Relay/AuditRelayPersistence.php' => ['\\SensitiveParameter'],
            'src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => ['\\SensitiveParameter'],
        ];
        foreach ($fullyQualified as $path => $expected) {
            $actual = [];
            $tokens = token_get_all($sources[$path] ?? '');
            $inUse = false;
            foreach ($tokens as $index => $token) {
                if (is_array($token) && $token[0] === T_USE) {
                    $next = $this->nextToken($tokens, $index + 1);
                    $inUse = $next !== null && $tokens[$next] !== '(';
                    continue;
                }
                if ($inUse && $token === ';') {
                    $inUse = false;
                    continue;
                }
                if (!$inUse && is_array($token) && $token[0] === T_NAME_FULLY_QUALIFIED) {
                    $actual[] = $token[1];
                }
            }
            $actual = array_values(array_unique($actual));
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'dependency-fqcn:' . $path;
            }

            $qualified = [];
            foreach ($tokens as $index => $token) {
                if (
                    !$inUse
                    && is_array($token)
                    && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_RELATIVE], true)
                    && (
                        $this->previousToken($tokens, $index - 1) === null
                        || !is_array($tokens[$this->previousToken($tokens, $index - 1)])
                        || !in_array($tokens[$this->previousToken($tokens, $index - 1)][0], [T_NAMESPACE, T_USE], true)
                    )
                ) {
                    $qualified[] = $token[1];
                }
            }
            if ($qualified !== []) {
                $violations[] = 'dependency-qualified:' . $path;
            }
        }
        return $violations;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function relayReferenceViolations(array $sources): array
    {
        $expected = $this->expectedRelayReferenceInventory();
        $violations = [];
        foreach ($sources as $path => $source) {
            if ($this->relayReferenceInventory($source) !== ($expected[$path] ?? [])) {
                $violations[] = $path . ':outside-reference';
            }
            if ($this->classStringViolations([$path => $source]) !== []) {
                $violations[] = $path . ':class-string';
            }
        }
        sort($violations);

        return array_values(array_unique($violations));
    }

    /** @return array<string,array<string,array<string,int>>> */
    private function expectedRelayReferenceInventory(): array
    {
        return [
            'Internal/Application/ApplicationAuditRuntime.php' => [
                AuditOutboxRelayRuntime::class => ['import' => 1, 'reference' => 3],
            ],
            'Internal/Application/ApplicationAuditRuntimeComposer.php' => [
                AuditIngressCanonicalizer::class => ['constructor' => 1, 'import' => 1],
                AuditOutboxRelayRuntime::class => ['constructor' => 1, 'import' => 1],
                PostgreSqlAuditRelayPersistence::class => ['constructor' => 1, 'import' => 1],
            ],
            'Internal/Audit/Relay/AuditIngressCanonicalizer.php' => [
                AuditIngressCanonicalizer::class => ['declaration' => 1],
            ],
            'Internal/Audit/Relay/AuditOutboxRelayRuntime.php' => [
                AuditIngressCanonicalizer::class => ['reference' => 1],
                AuditOutboxRelayRuntime::class => ['declaration' => 1],
                AuditRelayException::class => ['constructor' => 17, 'reference' => 2],
                AuditRelayFailure::class => ['static' => 17],
                AuditRelayOutcome::class => ['reference' => 9, 'static' => 10],
                AuditRelayPersistence::class => ['reference' => 1],
            ],
            'Internal/Audit/Relay/AuditRelayException.php' => [
                AuditRelayException::class => ['declaration' => 1],
                AuditRelayFailure::class => ['reference' => 2, 'static' => 1],
            ],
            'Internal/Audit/Relay/AuditRelayFailure.php' => [
                AuditRelayFailure::class => ['declaration' => 1],
            ],
            'Internal/Audit/Relay/AuditRelayOutcome.php' => [
                AuditRelayOutcome::class => ['declaration' => 1],
            ],
            'Internal/Audit/Relay/AuditRelayPersistence.php' => [
                AuditRelayPersistence::class => ['declaration' => 1],
            ],
            'Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php' => [
                AuditRelayPersistence::class => ['implementation' => 1],
                PostgreSqlAuditRelayPersistence::class => ['declaration' => 1],
            ],
        ];
    }

    /** @return array<string,array<string,array<string,int>>> */
    private function relayReferenceInventory(string $source): array
    {
        $targets = array_values(self::expectedFiles());
        $context = $this->nameContext($source);
        $tokens = token_get_all($source);
        $inventory = [];
        $inUse = false;
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_USE) {
                $next = $this->nextToken($tokens, $index + 1);
                $inUse = $next !== null && $tokens[$next] !== '(';
                if (!$inUse) {
                    continue;
                }
            }
            if ($inUse && $token === ';') {
                $inUse = false;
                continue;
            }
            if (
                !is_array($token)
                || !in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
            ) {
                continue;
            }
            $resolved = $inUse
                ? ltrim($token[1], '\\')
                : $this->resolveName($token[1], $context['namespace'], $context['aliases']);
            if (!in_array($resolved, $targets, true)) {
                continue;
            }
            $previous = $this->previousToken($tokens, $index - 1);
            $next = $this->nextToken($tokens, $index + 1);
            $kind = $inUse ? 'import' : 'reference';
            if (!$inUse && $previous !== null && is_array($tokens[$previous])) {
                $kind = match ($tokens[$previous][0]) {
                    T_NEW => 'constructor',
                    T_CLASS, T_INTERFACE, T_ENUM => 'declaration',
                    T_IMPLEMENTS => 'implementation',
                    T_EXTENDS => 'parent',
                    default => 'reference',
                };
            }
            if (
                !$inUse
                && $next !== null
                && ($tokens[$next] === '::' || is_array($tokens[$next]) && $tokens[$next][0] === T_DOUBLE_COLON)
            ) {
                $afterStatic = $this->nextToken($tokens, $next + 1);
                $kind =
                    $afterStatic !== null
                    && (
                        $tokens[$afterStatic] === 'class'
                        || is_array($tokens[$afterStatic])
                        && $tokens[$afterStatic][0] === T_CLASS
                    )
                        ? 'class-string'
                        : 'static';
            }
            $inventory[$resolved][$kind] = ($inventory[$resolved][$kind] ?? 0) + 1;
        }
        ksort($inventory);
        foreach ($inventory as &$kinds) {
            ksort($kinds);
        }
        unset($kinds);

        return $inventory;
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function constructorInventory(array $sources): array
    {
        $targets = array_keys($this->expectedConstructors());
        $inventory = array_fill_keys($targets, []);
        foreach ($sources as $path => $source) {
            $context = $this->nameContext($source);
            $tokens = token_get_all($source);
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || $token[0] !== T_NEW) {
                    continue;
                }
                $nameIndex = $this->nextToken($tokens, $index + 1);
                if ($nameIndex === null || !is_array($tokens[$nameIndex])) {
                    continue;
                }
                $raw = $tokens[$nameIndex][1];
                if ($tokens[$nameIndex][0] === T_VARIABLE || $raw === 'class') {
                    continue;
                }
                $resolved = $raw === 'self'
                    ? $this->sourceDeclarations($source)[0]['name'] ?? ''
                    : $this->resolveName($raw, $context['namespace'], $context['aliases']);
                if (in_array($resolved, $targets, true)) {
                    $inventory[$resolved][$path] = ($inventory[$resolved][$path] ?? 0) + 1;
                }
            }
        }
        foreach ($inventory as &$files) {
            ksort($files);
        }
        unset($files);
        return $inventory;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function constructorViolations(array $sources): array
    {
        $actual = $this->constructorInventory($sources);
        $violations = [];
        foreach ($this->expectedConstructors() as $target => $expected) {
            if (($actual[$target] ?? []) !== $expected) {
                $violations[] = 'constructor:' . $target;
            }
        }
        return $violations;
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function methodCallInventory(array $sources): array
    {
        $methods = array_keys($this->expectedMethodCalls());
        $inventory = array_fill_keys($methods, []);
        foreach ($sources as $path => $source) {
            $tokens = token_get_all($source);
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || !in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                    continue;
                }
                $name = $this->nextToken($tokens, $index + 1);
                if ($name === null || !is_array($tokens[$name]) || !in_array($tokens[$name][1], $methods, true)) {
                    continue;
                }
                $open = $this->nextToken($tokens, $name + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $method = $tokens[$name][1];
                $receiver = $this->receiver($tokens, $index);
                $receiverKey = $method . '|src/' . $path;
                if (!in_array($receiver, $this->expectedMethodReceivers()[$receiverKey] ?? [], true)) {
                    continue;
                }
                $inventory[$method][$path] = ($inventory[$method][$path] ?? 0) + 1;
            }
        }
        foreach ($inventory as &$files) {
            ksort($files);
        }
        unset($files);
        return $inventory;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function methodCallViolations(array $sources): array
    {
        $actual = $this->methodCallInventory($sources);
        $expected = $this->expectedMethodCalls();
        $violations = [];
        foreach ($expected as $method => $files) {
            if (($actual[$method] ?? []) !== $files) {
                foreach (array_keys($actual[$method] ?? []) as $path) {
                    $violations[] = $method . '|' . $path;
                }
                $violations[] = $method . '|inventory';
            }
        }
        foreach ($sources as $path => $source) {
            foreach ($this->objectMethodCalls($source) as $call) {
                if (!in_array($call['method'], array_keys($expected), true)) {
                    continue;
                }
                if (in_array($call['method'], ['recoverOne', 'relayOne'], true)) {
                    if (
                        $path === 'Internal/Execution/DeferredWorkerLoop.php'
                        && $call['receiver'] === '$this->recovery'
                        && $call['method'] === 'recoverOne'
                    ) {
                        continue;
                    }
                    $violations[] = $call['method'] . '|' . $path;
                    continue;
                }
                $key = $call['method'] . '|src/' . $path;
                if (!in_array($call['receiver'], $this->expectedMethodReceivers()[$key] ?? [], true)) {
                    $violations[] = $call['method'] . '|' . $path . '|receiver';
                }
            }
        }
        return array_values(array_unique($violations));
    }

    /** @return list<array{method:string,receiver:?string}> */
    private function objectMethodCalls(string $source): array
    {
        $tokens = token_get_all($source);
        $calls = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                continue;
            }
            $name = $this->nextToken($tokens, $index + 1);
            if ($name === null || !is_array($tokens[$name])) {
                continue;
            }
            $open = $this->nextToken($tokens, $name + 1);
            if ($open !== null && $tokens[$open] === '(') {
                $calls[] = [
                    'method' => $tokens[$name][1],
                    'receiver' => $this->receiver($tokens, $index),
                ];
            }
        }
        return $calls;
    }

    private function hasObjectMethodCall(string $source, string $method): bool
    {
        foreach ($this->objectMethodCalls($source) as $call) {
            if ($call['method'] === $method) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function methodReceiverInventory(array $sources): array
    {
        $methods = array_keys($this->expectedMethodCalls());
        $result = [];
        foreach ($sources as $path => $source) {
            $tokens = token_get_all($source);
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || !in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                    continue;
                }
                $name = $this->nextToken($tokens, $index + 1);
                if ($name === null || !is_array($tokens[$name]) || !in_array($tokens[$name][1], $methods, true)) {
                    continue;
                }
                $open = $this->nextToken($tokens, $name + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $key = $tokens[$name][1] . '|src/' . $path;
                $receiver = $this->receiver($tokens, $index);
                if (!in_array($receiver, $this->expectedMethodReceivers()[$key] ?? [], true)) {
                    continue;
                }
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

    /** @param array<string,string> $sources @return list<string> */
    private function applicationActivationViolations(array $sources): array
    {
        $violations = [];
        foreach ($sources as $path => $source) {
            $code = $this->codeWithoutCommentsAndStrings($source);
            foreach (['audit:relay', 'audit:outbox:relay', 'AuditRelayCommand', 'AuditRelayScheduler'] as $needle) {
                if (str_contains($code, $needle)) {
                    $violations[] = 'activation:' . $path . ':' . $needle;
                }
            }
        }
        foreach ($sources as $path => $source) {
            foreach ($this->objectMethodCalls($source) as $call) {
                if (
                    $path === 'Internal/Execution/DeferredWorkerLoop.php'
                    && $call['method'] === 'recoverOne'
                    && $call['receiver'] === '$this->recovery'
                ) {
                    continue;
                }
                if (in_array($call['method'], ['recoverOne', 'relayOne'], true)) {
                    $violations[] = 'activation:' . $call['method'] . ':' . $path;
                }
            }
        }
        return $violations;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function forbiddenViolations(array $sources): array
    {
        $forbidden = [
            'beginTransaction',
            'commit(',
            'rollBack(',
            'SAVEPOINT',
            'savepoint',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'executeQuery(',
            'executeStatement(',
            'fetchOne(',
            'fetchAssociative(',
            'fetchAllAssociative(',
            'fetchFirstColumn(',
            'fetchNumeric(',
            'fetchAllNumeric(',
            'fetchAllKeyValue(',
            'fetchAllAssociativeIndexed(',
            'iterateAssociative(',
            'iterateNumeric(',
            'iterateKeyValue(',
            'executeCacheQuery(',
            'prepare(',
            'query(',
            'insert(',
            'update(',
            'delete(',
            'time(',
            'microtime(',
            'uuid(',
            'uuid7(',
            'activeKey(',
            'key(',
            'nonce(',
            'ClockInterface',
            'Uuidv7Generator',
            'random_bytes',
            'RandomNonceSource',
            'getenv(',
            'putenv(',
            'ContainerInterface',
            'ResponseEmitter',
            'eval(',
            'include ',
            'require ',
            'sleep(',
        ];
        $violations = [];
        foreach ($sources as $path => $source) {
            $code = $this->codeWithoutCommentsAndStrings($source);
            foreach ($forbidden as $needle) {
                if (str_contains($code, $needle)) {
                    $violations[] = 'forbidden:' . $path . ':' . $needle;
                }
            }
        }
        sort($violations);
        return array_values(array_unique($violations));
    }

    /** @param array<string,string> $sources @return list<string> */
    private function classStringViolations(array $sources): array
    {
        $targets = array_values(self::expectedFiles());
        $violations = [];
        foreach ($sources as $path => $source) {
            $tokens = token_get_all($source);
            $values = [];
            $assignments = [];
            $dynamic = [];
            $targetFlow = [];
            for ($index = 0; $index < count($tokens); $index++) {
                $token = $tokens[$index];
                if (!is_array($token) || $token[0] !== T_VARIABLE) {
                    continue;
                }
                $equals = $this->nextToken($tokens, $index + 1);
                if ($equals === null || $tokens[$equals] !== '=') {
                    continue;
                }
                $valueIndex = $this->nextToken($tokens, $equals + 1);
                if ($valueIndex === null) {
                    continue;
                }
                $value = $this->stringTokenValue($tokens, $valueIndex);
                if ($value !== null) {
                    $nextValue = $this->nextToken($tokens, $valueIndex + 1);
                    if ($nextValue !== null && $tokens[$nextValue] === '.') {
                        $dynamic[$token[1]] = true;
                        if (str_contains($value, 'BlackOps\\Internal\\Audit\\Relay\\')) {
                            $targetFlow[$token[1]] = true;
                        }
                        continue;
                    }
                    $values[$token[1]] = $value;
                    $assignments[$token[1]] = $value;
                    if (str_contains($value, 'BlackOps\\Internal\\Audit\\Relay\\')) {
                        $targetFlow[$token[1]] = true;
                    }
                    continue;
                }
                if (is_array($tokens[$valueIndex]) && $tokens[$valueIndex][0] === T_VARIABLE) {
                    $nextValue = $this->nextToken($tokens, $valueIndex + 1);
                    if ($nextValue !== null && $tokens[$nextValue] === '.') {
                        $dynamic[$token[1]] = true;
                        $expression = '';
                        for ($cursor = $valueIndex; $cursor < count($tokens); $cursor++) {
                            if ($tokens[$cursor] === ';') {
                                break;
                            }
                            $expression .= is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
                        }
                        if (
                            str_contains($expression, 'BlackOps\\Internal\\Audit\\Relay\\')
                            || isset($targetFlow[$tokens[$valueIndex][1]])
                        ) {
                            $targetFlow[$token[1]] = true;
                        }
                        continue;
                    }
                    $assignments[$token[1]] = $assignments[$tokens[$valueIndex][1]] ?? null;
                    if (isset($targetFlow[$tokens[$valueIndex][1]])) {
                        $targetFlow[$token[1]] = true;
                    }
                    continue;
                }
                $dynamic[$token[1]] = true;
            }
            foreach ($tokens as $index => $token) {
                if (
                    !is_array($token)
                    || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)
                ) {
                    continue;
                }
                if (!in_array(
                    strtolower(ltrim($token[1], '\\')),
                    [
                        'class_exists',
                        'interface_exists',
                        'enum_exists',
                        'is_a',
                        'is_subclass_of',
                        'reflectionclass',
                        'reflectionmethod',
                    ],
                    true,
                )) {
                    continue;
                }
                $open = $this->nextToken($tokens, $index + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $argument = $this->nextToken($tokens, $open + 1);
                if ($argument === null) {
                    continue;
                }
                $value = $this->stringTokenValue($tokens, $argument);
                if ($value === null && is_array($tokens[$argument]) && $tokens[$argument][0] === T_VARIABLE) {
                    $variable = $tokens[$argument][1];
                    if (($assignments[$variable] ?? null) !== null) {
                        $value = $assignments[$variable];
                    } elseif (isset($dynamic[$variable]) && isset($targetFlow[$variable])) {
                        $violations[] = 'dynamic-class-string:' . $path;
                        continue;
                    }
                }
                if (
                    $value !== null
                    && in_array(
                        ltrim($value, '\\'),
                        array_map(static fn(string $target): string => ltrim($target, '\\'), $targets),
                        true,
                    )
                ) {
                    $violations[] = 'class-string:' . $path;
                }
            }
            foreach ($tokens as $index => $token) {
                if ($token !== '::' && !(is_array($token) && $token[0] === T_DOUBLE_COLON)) {
                    continue;
                }
                $next = $this->nextToken($tokens, $index + 1);
                $previous = $this->previousToken($tokens, $index - 1);
                if (
                    $next === null
                    || !($tokens[$next] === 'class' || is_array($tokens[$next]) && $tokens[$next][0] === T_CLASS)
                    || $previous === null
                    || !is_array($tokens[$previous])
                ) {
                    continue;
                }
                $resolved = $this->resolveName(
                    $tokens[$previous][1],
                    $this->nameContext($source)['namespace'],
                    $this->nameContext($source)['aliases'],
                );
                if (in_array($resolved, $targets, true)) {
                    $violations[] = 'class-string:' . $path;
                }
            }
        }
        sort($violations);
        return array_values(array_unique($violations));
    }

    /** @param array<string,string> $sources @return list<string> */
    private function targetAwareIndirectInvocationViolations(array $sources): array
    {
        $targetMethods = [
            'canonicalize',
            'validateLogical',
            'recoverNextExpired',
            'claimNext',
            'appendCanonical',
            'retry',
            'relay',
            'poison',
            'recoverOne',
            'relayOne',
        ];
        $methodPattern =
            '(?:'
            . implode('|', array_map(static fn(string $method): string => preg_quote($method, '/'), $targetMethods))
            . ')';
        $violations = [];
        foreach ($sources as $path => $source) {
            $code = $this->codeWithoutComments($source);
            $methodLiteral = preg_match('/[\'\"]' . $methodPattern . '[\'\"]/', $code) === 1;
            if ($methodLiteral) {
                if (
                    preg_match('/\[\s*[^,\]]+\s*,\s*[\'\"]' . $methodPattern . '[\'\"]\s*\]/s', $code) === 1
                    || preg_match('/\bcall_user_func(?:_array)?\s*\(/', $code) === 1
                ) {
                    $violations[] = 'indirect:' . $path . ':callable-array';
                }
                if (preg_match('/\bReflectionMethod\s*\([^)]*[\'\"]' . $methodPattern . '[\'\"]/', $code) === 1) {
                    $violations[] = 'indirect:' . $path . ':reflection';
                }
                if (
                    preg_match(
                        '/\(\s*\(*\s*\$[A-Za-z_][A-Za-z0-9_]*\s*\)*\s*\)\s*\(/',
                        $this->codeWithoutCommentsAndStrings($source),
                    ) === 1
                ) {
                    $violations[] = 'indirect:' . $path . ':parenthesized-call';
                }
                if (in_array('dynamic-call', $this->dynamicInvocationViolations($source), true)) {
                    $violations[] = 'indirect:' . $path . ':dynamic-call';
                }
            }

            $classString = $this->classStringViolations([$path => $source]);
            if (
                $classString !== []
                && preg_match(
                    '/\b(?:ReflectionClass|ReflectionMethod|class_exists|interface_exists|enum_exists|is_a|is_subclass_of)\s*\(/',
                    $code,
                ) === 1
            ) {
                $violations[] = 'indirect:' . $path . ':class-string';
            }
        }

        sort($violations);
        return array_values(array_unique($violations));
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function stringTokenValue(array $tokens, int $index): ?string
    {
        if (!is_array($tokens[$index])) {
            return null;
        }
        if ($tokens[$index][0] === T_CONSTANT_ENCAPSED_STRING) {
            return str_replace(['\\\\', "\\'", '\\"'], ['\\', "'", '"'], substr($tokens[$index][1], 1, -1));
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
        return trim($value);
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function receiver(array $tokens, int $operator): ?string
    {
        $name = $this->previousToken($tokens, $operator - 1);
        if ($name === null || !is_array($tokens[$name]) || !in_array($tokens[$name][0], [T_STRING, T_VARIABLE], true)) {
            return null;
        }
        $parts = [$tokens[$name][1]];
        $cursor = $this->previousToken($tokens, $name - 1);
        while (
            $cursor !== null
            && is_array($tokens[$cursor])
            && in_array($tokens[$cursor][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)
        ) {
            $base = $this->previousToken($tokens, $cursor - 1);
            if (
                $base === null
                || !is_array($tokens[$base])
                || !in_array($tokens[$base][0], [T_STRING, T_VARIABLE], true)
            ) {
                return null;
            }
            array_unshift($parts, $tokens[$base][1]);
            $cursor = $this->previousToken($tokens, $base - 1);
        }
        return implode('->', $parts);
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function nextToken(array $tokens, int $index): ?int
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
    private function previousToken(array $tokens, int $index): ?int
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

    /** @return list<string> */
    private function dynamicInvocationViolations(string $source): array
    {
        $tokens = token_get_all($source);
        $violations = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                $next = $this->nextToken($tokens, $index + 1);
                if ($next !== null && $tokens[$next] === '{') {
                    $violations[] = 'dynamic-call';
                }
            }
            if ($token[0] === T_VARIABLE) {
                $next = $this->nextToken($tokens, $index + 1);
                if ($next !== null && $tokens[$next] === '(') {
                    $violations[] = 'variable-call';
                }
            }
            if (
                $token[0] === T_STRING
                && in_array(strtolower($token[1]), ['call_user_func', 'call_user_func_array'], true)
            ) {
                $violations[] = 'callable-array';
            }
            if ($token[0] === T_NEW) {
                $next = $this->nextToken($tokens, $index + 1);
                if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_VARIABLE) {
                    $violations[] = 'dynamic-new';
                }
            }
        }
        $code = $this->codeWithoutCommentsAndStrings($source);
        if (
            preg_match('/\[\s*\$[A-Za-z_][A-Za-z0-9_]*\s*,/', $code) === 1
            || preg_match('/\[\s*[^\]]+::class\s*,/', $code) === 1
        ) {
            $violations[] = 'callable-array';
        }
        if (preg_match('/\(\s*\(*\s*\$[A-Za-z_][A-Za-z0-9_]*\s*\)*\s*\)\s*\(/', $code) === 1) {
            $violations[] = 'variable-call';
        }
        sort($violations);
        return array_values(array_unique($violations));
    }

    /** @return array{namespace:string,aliases:array<string,string>} */
    private function nameContext(string $source): array
    {
        $code = $this->codeWithoutComments($source);
        preg_match('/\bnamespace\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*;/', $code, $namespaceMatch);
        $namespace = $namespaceMatch[1] ?? '';
        $declarationPosition = strlen($code);
        foreach (['class', 'interface', 'enum', 'trait'] as $keyword) {
            $position = strpos($code, $keyword);
            if ($position !== false) {
                $declarationPosition = min($declarationPosition, $position);
            }
        }
        $header = substr($code, 0, $declarationPosition);
        preg_match_all('/\buse\s+([^;]+);/', $header, $matches);
        $aliases = [];
        foreach ($matches[1] ?? [] as $statement) {
            $statement = trim(preg_replace('/\s+/', ' ', $statement) ?? $statement);
            if (str_contains($statement, '{')) {
                [$prefix, $members] = explode('{', $statement, 2);
                $prefix = rtrim(trim($prefix), '\\') . '\\';
                foreach (explode('}', $members, 2)[0] !== ''
                    ? explode(',', explode('}', $members, 2)[0])
                    : [] as $member) {
                    $member = trim($member);
                    if ($member === '') {
                        continue;
                    }
                    $parts = preg_split('/\s+as\s+/i', $member) ?: [];
                    $full = $prefix . trim($parts[0]);
                    $alias = trim($parts[1] ?? basename(str_replace('\\', '/', $full)));
                    $aliases[$alias] = $full;
                }
                continue;
            }
            $parts = preg_split('/\s+as\s+/i', $statement) ?: [];
            $full = trim($parts[0], '\\');
            $alias = trim($parts[1] ?? basename(str_replace('\\', '/', $full)));
            $aliases[$alias] = $full;
        }
        return ['namespace' => $namespace, 'aliases' => $aliases];
    }

    /** @param array<string,string> $aliases */
    private function resolveName(string $raw, string $namespace, array $aliases): string
    {
        $raw = trim($raw);
        if ($raw === '' || in_array(strtolower($raw), ['self', 'static', 'parent'], true)) {
            return '';
        }
        if ($raw[0] === '\\') {
            return ltrim($raw, '\\');
        }
        if (str_starts_with($raw, 'namespace\\')) {
            return $this->qualify(substr($raw, strlen('namespace\\')), $namespace);
        }
        $first = explode('\\', $raw, 2)[0];
        if (isset($aliases[$first])) {
            return $aliases[$first] . (str_contains($raw, '\\') ? '\\' . substr($raw, strlen($first) + 1) : '');
        }
        return $this->qualify($raw, $namespace);
    }

    private function qualify(string $name, string $namespace): string
    {
        return $namespace === '' ? ltrim($name, '\\') : $namespace . '\\' . ltrim($name, '\\');
    }

    private function codeWithoutComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }

    private function codeWithoutCommentsAndStrings(string $source): string
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
                    ],
                    true,
                )
            ) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }
}
