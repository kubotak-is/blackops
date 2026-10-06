<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditHook;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditWriter;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxPhaseHook;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

/**
 * Keeps the generic Outbox adapter a finite native-to-C3 source graph.
 *
 * The checks inspect resolved syntax and declared contracts. Mutation cases
 * keep the guards useful when a change preserves surrounding text.
 */
final class GenericOutboxAuditArchitectureTest extends TestCase
{
    private const string STORE = 'src/Transport/PostgreSql/PostgreSqlOutboxStore.php';
    private const string HOOK = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditHook.php';
    private const string WRITER = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditWriter.php';
    private const string FAILURE = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditFailure.php';
    private const string HOOK_GUARD = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditHookGuard.php';
    private const string ROW = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditRow.php';
    private const string SNAPSHOT = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditSnapshot.php';
    private const string IDENTITY = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditIdentity.php';
    private const string CATALOG = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php';
    private const string PROJECTION = 'src/Internal/Audit/OperationalOutbox/GenericOutboxAuditProjection.php';
    private const string PHASE_HOOK = 'src/Transport/PostgreSql/PostgreSqlOutboxPhaseHook.php';

    /** @var array<string,string> */
    private const array HELPERS = [
        self::FAILURE => 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure',
        self::HOOK_GUARD => 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard',
        self::ROW => 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditRow',
        self::SNAPSHOT => 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditSnapshot',
        self::IDENTITY => 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity',
        self::CATALOG => 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditCatalog',
        self::PROJECTION => 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection',
    ];

    /** @var list<string> */
    private const array O3_SOURCES = [
        self::FAILURE,
        self::HOOK_GUARD,
        self::ROW,
        self::SNAPSHOT,
        self::IDENTITY,
        self::CATALOG,
        self::PROJECTION,
        self::WRITER,
        self::HOOK,
        self::PHASE_HOOK,
        self::STORE,
    ];

    public function testFiniteHelpersHaveExactStatelessSensitiveContracts(): void
    {
        foreach (self::HELPERS as $path => $type) {
            self::assertFileExists(__DIR__ . '/../../' . $path);
            $reflection = new ReflectionClass($type);
            self::assertTrue($reflection->isFinal(), $type);
            self::assertSame([], $reflection->getProperties(), $type);
            self::assertSame([], $reflection->getAttributes(\BlackOps\Core\Attribute\PublicApi::class), $type);
            $this->assertHelperMethods($type, $this->helperContracts()[$type]);
        }

        $hook = new ReflectionClass(GenericOutboxAuditHook::class);
        $writer = new ReflectionClass(GenericOutboxAuditWriter::class);
        self::assertTrue($hook->isFinal());
        self::assertTrue($writer->isFinal());
        self::assertTrue($hook->implementsInterface(PostgreSqlOutboxPhaseHook::class));
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
        self::assertSame(
            [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertOperatorEntry',
                'assertRelayEntry',
                'deadLetterRetryRequested',
                'deadLettered',
                'retryScheduled',
            ],
            $this->declaredPublicMethods($hook),
        );
        foreach ([$hook, $writer] as $reflection) {
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }
                foreach ($method->getParameters() as $parameter) {
                    if ($method->getName() === '__debugInfo' || $method->getName() === '__serialize') {
                        continue;
                    }
                    if ($this->typeName($parameter->getType()) === 'BlackOps\\Core\\Audit\\AuditSourceRuntime') {
                        self::assertCount(0, $parameter->getAttributes(\SensitiveParameter::class));
                        continue;
                    }
                    self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class), $method->getName());
                }
            }
        }

        $phase = new ReflectionClass(PostgreSqlOutboxPhaseHook::class);
        self::assertTrue($phase->isInterface());
        self::assertSame(
            [
                'assertOperatorEntry',
                'assertRelayEntry',
                'deadLetterRetryRequested',
                'deadLettered',
                'retryScheduled',
            ],
            $this->declaredMethods($phase),
        );
        foreach ($phase->getMethods() as $method) {
            self::assertSame('void', $this->typeName($method->getReturnType()), $method->getName());
            foreach ($method->getParameters() as $parameter) {
                self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class));
            }
        }

        $store = new ReflectionClass(PostgreSqlOutboxStore::class)->getConstructor();
        self::assertNotNull($store);
        $phaseParameter = $store->getParameters()[4];
        self::assertSame('phaseHook', $phaseParameter->getName());
        self::assertSame('?' . PostgreSqlOutboxPhaseHook::class, $this->typeName($phaseParameter->getType()));
        self::assertTrue($phaseParameter->allowsNull());
        self::assertTrue($phaseParameter->isDefaultValueAvailable());
        self::assertNull($phaseParameter->getDefaultValue());
        self::assertCount(1, $phaseParameter->getAttributes(\SensitiveParameter::class));
    }

    public function testResolvedOwnershipOrderAndRuntimeRolesAreFinite(): void
    {
        $sources = $this->o3Sources();
        self::assertTrue($this->helperSurfaceOracle($sources));
        self::assertTrue($this->writerGraphOracle($sources[self::WRITER]));
        self::assertTrue($this->hookGraphOracle($sources[self::HOOK]));
        self::assertTrue($this->helperCallOracle($sources));
        $snapshotNodes = $this->parse($sources[self::SNAPSHOT]);
        $snapshotClass = $this->classNode($snapshotNodes);
        $snapshotRead = $snapshotClass === null ? null : $this->methodNodes($snapshotClass)['read'] ?? null;
        $snapshotCalls = $snapshotRead instanceof ClassMethod
            ? $this->callNames($this->methodCalls($snapshotRead))
            : [];
        $snapshotNewTargets = $snapshotRead instanceof ClassMethod
            ? $this->newTargets($this->methodNodesAsNodes($snapshotRead), ['AuditRecordActor', 'AuditTypedReference'])
            : [];
        $snapshotStaticCalls = $snapshotRead instanceof ClassMethod
            ? $this->staticCallNames($this->methodNodesAsNodes($snapshotRead))
            : [];
        self::assertTrue(
            $this->snapshotGraphOracle($sources[self::SNAPSHOT]),
            sprintf(
                'Snapshot graph actual calls=%s; new=%s; static=%s',
                var_export($snapshotCalls, true),
                var_export($snapshotNewTargets, true),
                var_export($snapshotStaticCalls, true),
            ),
        );
        $ownershipFailure = null;
        self::assertTrue(
            $this->ownershipOracle($sources, $ownershipFailure),
            $ownershipFailure ?? 'ownership oracle failed',
        );
        self::assertTrue($this->storeOrderOracle($sources[self::STORE]));
        self::assertTrue($this->valueBindingOracle($sources));

        $identityBoundMutation = $this->replaceFirst(
            $sources[self::IDENTITY],
            '9223372036854775807',
            '9223372036854775808',
        );
        self::assertNotEmpty($this->parse($identityBoundMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::IDENTITY => $identityBoundMutation]));

        $identityComparisonMutation = $this->replaceFirst(
            $sources[self::IDENTITY],
            "strcmp(\$stateVersion, '9223372036854775807') > 0",
            "strcmp(\$stateVersion, '9223372036854775807') < 0",
        );
        self::assertNotEmpty($this->parse($identityComparisonMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::IDENTITY => $identityComparisonMutation]));

        $identityDefaultThrowMutation = $this->replaceFirst(
            $sources[self::IDENTITY],
            'default => throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid),',
            'default => GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid),',
        );
        $identityDefaultThrowNodes = $this->parse($identityDefaultThrowMutation);
        self::assertNotEmpty($identityDefaultThrowNodes);
        $identityDefaultThrowClass = $this->classNode($identityDefaultThrowNodes);
        self::assertNotNull($identityDefaultThrowClass);
        $identityDefaultThrowMethod = $this->methodNodes($identityDefaultThrowClass)['discriminator'] ?? null;
        self::assertNotNull($identityDefaultThrowMethod);
        $identityDefaultThrowMatches = new NodeFinder()->findInstanceOf(
            $identityDefaultThrowMethod->stmts ?? [],
            Node\Expr\Match_::class,
        );
        self::assertCount(1, $identityDefaultThrowMatches);
        $identityDefaultThrowMatch = $identityDefaultThrowMatches[0] ?? null;
        self::assertInstanceOf(Node\Expr\Match_::class, $identityDefaultThrowMatch);
        $identityDefaultThrowArm = null;
        foreach ($identityDefaultThrowMatch->arms as $arm) {
            if ($arm->conds === null) {
                $identityDefaultThrowArm = $arm;
                break;
            }
        }
        self::assertNotNull($identityDefaultThrowArm);
        self::assertInstanceOf(StaticCall::class, $identityDefaultThrowArm->body);
        self::assertFalse($this->valueBindingOracle([...$sources, self::IDENTITY => $identityDefaultThrowMutation]));

        $identityDisabledBoundMutation = $this->replaceFirst(
            $sources[self::IDENTITY],
            "            if (strlen(\$stateVersion) === 19 && strcmp(\$stateVersion, '9223372036854775807') > 0) {",
            "            if (false && strlen(\$stateVersion) === 19 && strcmp(\$stateVersion, '9223372036854775807') > 0) {",
        );
        self::assertNotEmpty($this->parse($identityDisabledBoundMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::IDENTITY => $identityDisabledBoundMutation]));

        $identityCastMutation = $this->insertIntoMethodBody(
            $sources[self::IDENTITY],
            'stateVersion',
            '        $stateVersion = (string) (int) $stateVersion;',
        );
        self::assertNotEmpty($this->parse($identityCastMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::IDENTITY => $identityCastMutation]));

        $rowValidationMutation = $this->replaceFirst(
            $sources[self::ROW],
            'GenericOutboxAuditIdentity::stateVersion($stateVersion)',
            '$stateVersion',
        );
        self::assertNotEmpty($this->parse($rowValidationMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::ROW => $rowValidationMutation]));

        $tupleMutation = $this->replaceFirst(
            $sources[self::CATALOG],
            '$tuple->policyReasonCode',
            '$expected[\'outcome\']',
        );
        self::assertNotEmpty($this->parse($tupleMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::CATALOG => $tupleMutation]));

        $catalogArrayMutation = $this->replaceFirst(
            $sources[self::CATALOG],
            "'actionTypes' => [\$eventType]",
            "'actionTypes' => []",
        );
        self::assertNotEmpty($this->parse($catalogArrayMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::CATALOG => $catalogArrayMutation]));

        $opaqueMutation = $this->replaceFirst(
            $sources[self::PROJECTION],
            "AuditValidationException::assertSafeString(\$value, 1, 256);\n\n            return \$value;",
            "AuditValidationException::assertSafeString(\$value, 1, 256);\n\n            return \$context;",
        );
        self::assertNotEmpty($this->parse($opaqueMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::PROJECTION => $opaqueMutation]));

        $opaqueReassignmentMutation = $this->replaceFirst(
            $sources[self::PROJECTION],
            "            \$value = (string) \$opaqueIds->derive(\$purpose, \$context);\n",
            "            \$value = (string) \$opaqueIds->derive(\$purpose, \$context);\n            \$value = \$context;\n",
        );
        self::assertNotEmpty($this->parse($opaqueReassignmentMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::PROJECTION => $opaqueReassignmentMutation]));

        $writerMaterialMutation = $this->replaceFirst(
            $sources[self::WRITER],
            "            \$source = [\n",
            "            \$actionId = 'tampered';\n            \$source = [\n",
        );
        self::assertNotEmpty($this->parse($writerMaterialMutation));
        self::assertFalse($this->valueBindingOracle([...$sources, self::WRITER => $writerMaterialMutation]));

        $extraDeclaration = $sources[self::HOOK_GUARD] . "\nfinal class UnlistedOutboxHelper {}\n";
        self::assertCount(2, $this->classNodes($this->parse($extraDeclaration)));
        self::assertFalse($this->helperSurfaceOracle([...$sources, self::HOOK_GUARD => $extraDeclaration]));

        $extraPrivateMethodPosition = strrpos($sources[self::HOOK_GUARD], '}');
        self::assertNotFalse($extraPrivateMethodPosition);
        $extraPrivateMethod = substr_replace(
            $sources[self::HOOK_GUARD],
            "\n    private function unlisted(): void {}\n",
            $extraPrivateMethodPosition,
            0,
        );
        self::assertNotEmpty($this->parse($extraPrivateMethod));
        self::assertFalse($this->helperSurfaceOracle([...$sources, self::HOOK_GUARD => $extraPrivateMethod]));

        $rawStatePosition = strrpos($sources[self::HOOK_GUARD], '}');
        self::assertNotFalse($rawStatePosition);
        $rawState = substr_replace(
            $sources[self::HOOK_GUARD],
            "\n    private array \$rawState = [];\n",
            $rawStatePosition,
            0,
        );
        self::assertNotEmpty($this->parse($rawState));
        self::assertFalse($this->helperSurfaceOracle([...$sources, self::HOOK_GUARD => $rawState]));

        $ownerPrivateMethodPosition = strrpos($sources[self::HOOK], '}');
        self::assertNotFalse($ownerPrivateMethodPosition);
        $ownerPrivateMethod = substr_replace(
            $sources[self::HOOK],
            "\n    private function unlisted(): void {}\n",
            $ownerPrivateMethodPosition,
            0,
        );
        $ownerPrivateClass = $this->classNode($this->parse($ownerPrivateMethod));
        self::assertNotNull($ownerPrivateClass);
        $ownerPrivateMethods = $this->methodNodes($ownerPrivateClass);
        self::assertArrayHasKey('unlisted', $ownerPrivateMethods);
        self::assertTrue($ownerPrivateMethods['unlisted']->isPrivate());
        self::assertFalse($this->helperSurfaceOracle([...$sources, self::HOOK => $ownerPrivateMethod]));

        $ownerRawStatePosition = strrpos($sources[self::HOOK], '}');
        self::assertNotFalse($ownerRawStatePosition);
        $ownerRawState = substr_replace(
            $sources[self::HOOK],
            "\n    private array \$rawState = [];\n",
            $ownerRawStatePosition,
            0,
        );
        $ownerRawStateClass = $this->classNode($this->parse($ownerRawState));
        self::assertNotNull($ownerRawStateClass);
        $ownerRawProperties = $this->propertyNodes($ownerRawStateClass);
        self::assertCount(2, $ownerRawProperties);
        self::assertSame('rawState', $ownerRawProperties[1]->props[0]->name->toString());
        self::assertSame('array', $this->astTypeName($ownerRawProperties[1]->type));
        self::assertFalse($this->helperSurfaceOracle([...$sources, self::HOOK => $ownerRawState]));

        $sensitivityMutation = $this->replaceFirst(
            $sources[self::IDENTITY],
            '#[\\SensitiveParameter] string $recordId',
            'string $recordId',
        );
        self::assertNotEmpty($this->parse($sensitivityMutation));
        self::assertFalse($this->helperSurfaceOracle([...$sources, self::IDENTITY => $sensitivityMutation]));

        $nestedHookMutation = $this->replaceFirst(
            $sources[self::HOOK],
            '            $writer->assertConnection($connection, AuditSourceRuntime::Relay);',
            "            if (false) {\n                \$writer->assertConnection(\$connection, AuditSourceRuntime::Relay);\n            }",
        );
        self::assertNotEmpty($this->parse($nestedHookMutation));
        self::assertContains(
            'assertConnection',
            $this->callNames($this->methodCallsFromNodes($this->parse($nestedHookMutation))),
        );
        self::assertCount(5, array_filter(
            $this->callNames($this->methodCallsFromNodes($this->parse($nestedHookMutation))),
            static fn(string $name): bool => $name === 'assertConnection',
        ));
        self::assertFalse($this->hookGraphOracle($nestedHookMutation));

        $foreignHelperMutation = $this->replaceFirst(
            $sources[self::HOOK],
            'GenericOutboxAuditHookGuard::entry($connection)',
            '\\Other\\GenericOutboxAuditHookGuard::entry($connection)',
        );
        self::assertFalse($this->hookGraphOracle($foreignHelperMutation));

        $unlistedDependencyMutation = $this->insertIntoMethodBody(
            $sources[self::HOOK_GUARD],
            'entry',
            "        \$connection->fetchOne('SELECT 1');",
        );
        self::assertContains(
            'fetchOne',
            $this->callNames($this->methodCallsFromNodes($this->parse($unlistedDependencyMutation))),
        );
        self::assertFalse($this->helperCallOracle([...$sources, self::HOOK_GUARD => $unlistedDependencyMutation]));

        $unlistedFunctionMutation = $this->insertIntoMethodBody(
            $sources[self::HOOK_GUARD],
            'entry',
            '        random_int(0, 1);',
        );
        self::assertNotEmpty($this->parse($unlistedFunctionMutation));
        self::assertFalse($this->helperCallOracle([...$sources, self::HOOK_GUARD => $unlistedFunctionMutation]));

        $unsupportedFunctionMutation = $this->insertIntoMethodBody(
            $sources[self::HOOK_GUARD],
            'entry',
            "        \$fn = 'random_int';\n        \$fn(0, 1);",
        );
        self::assertNotEmpty($this->parse($unsupportedFunctionMutation));
        self::assertFalse($this->helperCallOracle([...$sources, self::HOOK_GUARD => $unsupportedFunctionMutation]));

        $wrongOwnerSelfMutation = $this->insertIntoMethodBody(
            $sources[self::IDENTITY],
            'recordId',
            '        self::stateVersion($recordId);',
        );
        self::assertNotEmpty($this->parse($wrongOwnerSelfMutation));
        self::assertFalse($this->helperCallOracle([...$sources, self::IDENTITY => $wrongOwnerSelfMutation]));

        $composerMutation = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Application;
            use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditHook;
            use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditWriter;
            use BlackOps\Transport\PostgreSql\PostgreSqlOutboxPhaseHook;
            function unlistedComposer(GenericOutboxAuditWriter $writer): PostgreSqlOutboxPhaseHook
            {
                return new GenericOutboxAuditHook($writer);
            }
            PHP;
        self::assertFalse($this->ownershipOracle([
            ...$sources,
            'src/Internal/Application/UnlistedOutboxComposer.php' => $composerMutation,
        ]));

        $writerMutation = $this->replaceFirst(
            $sources[self::WRITER],
            'deliverInCurrentTransaction',
            'deliverStandalone',
        );
        self::assertContains(
            'deliverStandalone',
            $this->callNames($this->methodCallsFromNodes($this->parse($writerMutation))),
        );
        self::assertFalse($this->writerGraphOracle($writerMutation));

        $selectorMutation = $this->replaceFirst($sources[self::WRITER], '->forDraft(', '->select(');
        self::assertContains('select', $this->callNames($this->methodCallsFromNodes($this->parse($selectorMutation))));
        self::assertFalse($this->writerGraphOracle($selectorMutation));

        $snapshotMutation = $this->insertIntoMethodBody($sources[self::SNAPSHOT], 'read', '        $clock->now();');
        self::assertContains('now', $this->callNames($this->methodCallsFromNodes($this->parse($snapshotMutation))));
        self::assertFalse($this->ownershipOracle([...$sources, self::SNAPSHOT => $snapshotMutation]));

        $guardMutation = $this->insertIntoMethodBody(
            $sources[self::HOOK_GUARD],
            'entry',
            '        $connection->commit();',
        );
        self::assertContains('commit', $this->callNames($this->methodCallsFromNodes($this->parse($guardMutation))));
        self::assertFalse($this->ownershipOracle([...$sources, self::HOOK_GUARD => $guardMutation]));

        $runtimeMutation = $this->replaceFirst(
            $sources[self::HOOK],
            'assertConnection($connection, AuditSourceRuntime::Relay)',
            'assertConnection($connection, AuditSourceRuntime::Cli)',
        );
        self::assertContains('Cli', $this->runtimeConstants($this->parse($runtimeMutation)));
        self::assertFalse($this->hookGraphOracle($runtimeMutation));

        $rootMutation = $this->replaceFirst(
            $sources[self::STORE],
            '$this->assertActiveRoot();',
            '$this->assertIdleRoot();',
        );
        self::assertContains(
            'assertIdleRoot',
            $this->callNames($this->methodCallsFromNodes($this->parse($rootMutation))),
        );
        self::assertFalse($this->storeOrderOracle($rootMutation));
    }

    public function testNativeRootAndCleanupOrderUsesResolvedStatementsAndSqlFields(): void
    {
        $store = $this->source(self::STORE);
        $storeFailure = null;
        self::assertTrue($this->storeOrderOracle($store, $storeFailure), $storeFailure ?? 'store oracle failed');

        $rowMutation = str_replace(
            'RETURNING record_id::text AS record_id, tenant_type, tenant_id, state_version::text AS state_version',
            'RETURNING operation_id::text AS operation_id',
            $store,
            $count,
        );
        self::assertSame(3, $count);
        self::assertNotEmpty($this->parse($rowMutation));
        self::assertFalse($this->storeOrderOracle($rowMutation));

        $commitMutation = $this->replaceFirst(
            $store,
            '            $this->connection->commit();',
            '            $this->connection->executeStatement(\'SELECT 1\');',
        );
        self::assertContains(
            'executeStatement',
            $this->callNames($this->methodCallsFromNodes($this->parse($commitMutation))),
        );
        self::assertFalse($this->storeOrderOracle($commitMutation));

        $lateAdmissionMutation = $this->replaceFirst(
            $store,
            "            \$this->phaseHook?->assertRelayEntry(\$this->connection);\n            \$attempted = true;\n            \$this->connection->beginTransaction();\n            \$this->assertActiveRoot();",
            "            \$attempted = true;\n            \$this->connection->beginTransaction();\n            \$this->assertActiveRoot();\n            \$this->phaseHook?->assertRelayEntry(\$this->connection);",
        );
        self::assertNotEmpty($this->parse($lateAdmissionMutation));
        self::assertFalse($this->storeOrderOracle($lateAdmissionMutation));

        $manualUuidMutation = $this->replaceFirst(
            $store,
            "'audit_id' => \$retryAuditId",
            "'audit_id' => '00000000-0000-4000-8000-000000000001'",
        );
        self::assertNotEmpty($this->parse($manualUuidMutation));
        self::assertFalse($this->storeOrderOracle($manualUuidMutation));

        $enabledMethodStart = strpos($store, '    private function scheduleRetryEnabled(');
        self::assertNotFalse($enabledMethodStart);
        $enabledMethodEnd = strpos($store, '    private function moveToDeadLetterEnabled(');
        self::assertNotFalse($enabledMethodEnd);
        self::assertLessThan($enabledMethodEnd, $enabledMethodStart);
        $enabledMethodSource = substr($store, $enabledMethodStart, $enabledMethodEnd - $enabledMethodStart);
        self::assertNotEmpty($enabledMethodSource);

        $rowAssignmentMutationMethod = $this->replaceFirst(
            $enabledMethodSource,
            '            $row = $this->connection->fetchAssociative(',
            '            $other = $this->connection->fetchAssociative(',
        );
        $rowAssignmentMutation = substr_replace(
            $store,
            $rowAssignmentMutationMethod,
            $enabledMethodStart,
            $enabledMethodEnd - $enabledMethodStart,
        );
        self::assertNotEmpty($this->parse($rowAssignmentMutation));
        $rowAssignmentClass = $this->classNode($this->parse($rowAssignmentMutation));
        self::assertNotNull($rowAssignmentClass);
        $rowAssignmentMethod = $this->methodNodes($rowAssignmentClass)['scheduleRetryEnabled'] ?? null;
        self::assertNotNull($rowAssignmentMethod);
        $otherFetchAssignments = array_values(array_filter(
            new NodeFinder()->findInstanceOf($rowAssignmentMethod->stmts ?? [], Node\Expr\Assign::class),
            static fn(Node $node): bool => (
                $node instanceof Node\Expr\Assign
                && $node->var instanceof Node\Expr\Variable
                && $node->var->name === 'other'
                && $node->expr instanceof MethodCall
                && $node->expr->name instanceof Identifier
                && $node->expr->name->toString() === 'fetchAssociative'
            ),
        ));
        self::assertCount(1, $otherFetchAssignments);
        self::assertFalse($this->storeOrderOracle($rowAssignmentMutation));

        $rowExitMutation = $this->replaceFirst(
            $store,
            "                throw new \\LogicException();\n            }\n            \$this->phaseHook?->retryScheduled",
            "                \$known = 'row was not returned';\n            }\n            \$this->phaseHook?->retryScheduled",
        );
        self::assertNotEmpty($this->parse($rowExitMutation));
        self::assertFalse($this->storeOrderOracle($rowExitMutation));

        $rowOverwriteMutationMethod = $this->replaceFirst(
            $enabledMethodSource,
            "            if (!is_array(\$row)) {",
            "            \$row = [];\n            if (!is_array(\$row)) {",
        );
        $rowOverwriteMutation = substr_replace(
            $store,
            $rowOverwriteMutationMethod,
            $enabledMethodStart,
            $enabledMethodEnd - $enabledMethodStart,
        );
        self::assertNotEmpty($this->parse($rowOverwriteMutation));
        $rowOverwriteClass = $this->classNode($this->parse($rowOverwriteMutation));
        self::assertNotNull($rowOverwriteClass);
        $rowOverwriteMethod = $this->methodNodes($rowOverwriteClass)['scheduleRetryEnabled'] ?? null;
        self::assertNotNull($rowOverwriteMethod);
        $rowOverwriteAssignments = array_values(array_filter(
            new NodeFinder()->findInstanceOf($rowOverwriteMethod->stmts ?? [], Node\Expr\Assign::class),
            static fn(Node $node): bool => (
                $node instanceof Node\Expr\Assign
                && $node->var instanceof Node\Expr\Variable
                && $node->var->name === 'row'
                && $node->expr instanceof Node\Expr\Array_
            ),
        ));
        self::assertCount(1, $rowOverwriteAssignments);
        self::assertFalse($this->storeOrderOracle($rowOverwriteMutation));

        $attemptMutation = $this->replaceFirst(
            $store,
            "            \$attempted = true;\n            \$this->connection->beginTransaction();",
            "            \$this->connection->beginTransaction();\n            \$attempted = true;",
        );
        self::assertNotEmpty($this->parse($attemptMutation));
        self::assertFalse($this->storeOrderOracle($attemptMutation));

        $attemptOverwriteMutation = $this->replaceFirst(
            $store,
            "            \$this->connection->beginTransaction();\n            \$this->assertActiveRoot();",
            "            \$this->connection->beginTransaction();\n            \$attempted = false;\n            \$this->assertActiveRoot();",
        );
        self::assertNotEmpty($this->parse($attemptOverwriteMutation));
        self::assertFalse($this->storeOrderOracle($attemptOverwriteMutation));

        $uuidBindingMutation = $this->replaceFirst(
            $store,
            '            $retryAuditId = $this->uuid4();',
            '            $generatedAuditId = $this->uuid4();',
        );
        self::assertNotEmpty($this->parse($uuidBindingMutation));
        self::assertFalse($this->storeOrderOracle($uuidBindingMutation));

        $duplicatePhaseMutation = $this->replaceFirst(
            $store,
            '            $this->phaseHook?->retryScheduled($this->connection, $row);',
            "            \$this->phaseHook?->retryScheduled(\$this->connection, \$row);\n            \$this->phaseHook?->retryScheduled(\$this->connection, \$row);",
        );
        self::assertNotEmpty($this->parse($duplicatePhaseMutation));
        self::assertFalse($this->storeOrderOracle($duplicatePhaseMutation));

        $cleanupMutation = $this->replaceFirst($store, "            \$this->connection->close();\n", '');
        self::assertNotEmpty($this->parse($cleanupMutation));
        self::assertFalse($this->storeOrderOracle($cleanupMutation));

        $cleanupLevelMutation = $this->replaceFirst(
            $store,
            '$this->connection->getTransactionNestingLevel() === 1',
            '$this->connection->getTransactionNestingLevel() !== 1',
        );
        self::assertNotEmpty($this->parse($cleanupLevelMutation));
        self::assertFalse($this->storeOrderOracle($cleanupLevelMutation));

        $cleanupConjunctionMutation = $this->replaceFirst(
            $store,
            "                \$this->connection->isAutoCommit()\n                && \$this->connection->isTransactionActive()",
            "                \$this->connection->isAutoCommit()\n                || \$this->connection->isTransactionActive()",
        );
        self::assertNotEmpty($this->parse($cleanupConjunctionMutation));
        self::assertFalse($this->storeOrderOracle($cleanupConjunctionMutation));

        $cleanupAttemptMutation = $this->replaceFirst(
            $store,
            '        $clean = !$attempted || $this->cleanupOwned();',
            '        $clean = $this->cleanupOwned();',
        );
        self::assertNotEmpty($this->parse($cleanupAttemptMutation));
        self::assertFalse($this->storeOrderOracle($cleanupAttemptMutation));

        $silentActivationMutation = $this->insertIntoMethodBody(
            $store,
            'heartbeat',
            '        $this->phaseHook?->assertRelayEntry($this->connection);',
        );
        self::assertNotEmpty($this->parse($silentActivationMutation));
        self::assertFalse($this->storeOrderOracle($silentActivationMutation));

        $nodes = $this->parse($store);
        foreach (['insert', 'claimBatch', 'heartbeat', 'markSent', 'migrate'] as $method) {
            $methodNode = $this->classNode($nodes) === null
                ? null
                : $this->methodNodes($this->classNode($nodes))[$method] ?? null;
            self::assertNotNull($methodNode, $method);
            self::assertSame([], $this->phaseHookCalls($this->methodCalls($methodNode)));
        }
    }

    public function testAliasFqcnAndInertTextAreResolvedWithoutTextualFalsePositives(): void
    {
        $source = <<<'PHP'
            <?php
            namespace Example;
            use BlackOps\Core\Audit\AuditSourceRuntime as Runtime;
            /* Runtime::Wrong; GenericOutboxAuditProjection::occurredAt($clock); */
            $relay = Runtime::Relay;
            $cli = \BlackOps\Core\Audit\AuditSourceRuntime::Cli;
            $text = 'GenericOutboxAuditProjection::occurredAt';
            PHP;
        self::assertSame(['Cli', 'Relay'], $this->runtimeConstants($this->parse($source)));
        self::assertSame([], $this->methodNames($this->parse($source), ['occurredAt', 'Wrong']));

        $mutant = $source . "\nGenericOutboxAuditProjection::occurredAt(\$clock);\n";
        self::assertSame(['occurredAt'], $this->methodNames($this->parse($mutant), ['occurredAt']));
    }

    /** @param array<string, string> $sources */
    private function helperSurfaceOracle(array $sources): bool
    {
        foreach (self::HELPERS as $path => $type) {
            if (!isset($sources[$path])) {
                return false;
            }
            $classes = $this->classNodes($this->parse($sources[$path]));
            $class = $classes[0] ?? null;
            if (
                count($classes) !== 1
                || $class === null
                || $class->name?->toString() !== $this->shortName($type)
                || !$class->isFinal()
            ) {
                return false;
            }
            if ($this->methodNodes($class) === [] || $this->propertyNodes($class) !== []) {
                return false;
            }
            $methods = $this->methodNodes($class);
            $contracts = $this->helperContracts()[$type];
            $actual = array_keys($methods);
            $expected = array_keys($contracts);
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                return false;
            }
            foreach ($contracts as $name => [$visibility, $static, $parameters, $return]) {
                $method = $methods[$name];
                $actualVisibility = $method->isPrivate()
                    ? 'private'
                    : ($method->isProtected() ? 'protected' : 'public');
                if (
                    $actualVisibility !== $visibility
                    || $method->isStatic() !== $static
                    || $this->astTypeName($method->returnType) !== $return
                ) {
                    return false;
                }
                $actualParameters = [];
                foreach ($method->params as $parameter) {
                    $actualParameters[] = $this->astParameterContract($parameter);
                }
                if ($actualParameters !== $parameters) {
                    return false;
                }
            }
        }

        return $this->ownerSurfaceOracle($sources);
    }

    /** @param array<string,string> $sources */
    private function ownerSurfaceOracle(array $sources): bool
    {
        $connection = 'Doctrine\\DBAL\\Connection';
        $runtime = 'BlackOps\\Core\\Audit\\AuditSourceRuntime';
        $writerType = 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditWriter';
        $tenant = '?BlackOps\\Core\\TenantRef';
        $hookMethods = [
            '__clone' => ['public', false, [], 'void'],
            '__construct' => ['public', false, [['writer', $writerType, false, 1]], ''],
            '__debugInfo' => ['public', false, [], 'array'],
            '__serialize' => ['public', false, [], 'array'],
            '__unserialize' => ['public', false, [['data', 'array', false, 1]], 'void'],
            'assertOperatorEntry' => ['public', false, [['connection', $connection, false, 1]], 'void'],
            'assertRelayEntry' => ['public', false, [['connection', $connection, false, 1]], 'void'],
            'deadLetterRetryRequested' => [
                'public',
                false,
                [
                    ['connection',   $connection, false, 1],
                    ['row',          'array',     false, 1],
                    ['retryAuditId', 'string',    false, 1],
                ],
                'void',
            ],
            'deadLettered' => [
                'public',
                false,
                [
                    ['connection', $connection, false, 1],
                    ['row',        'array',     false, 1],
                ],
                'void',
            ],
            'retryScheduled' => [
                'public',
                false,
                [
                    ['connection', $connection, false, 1],
                    ['row',        'array',     false, 1],
                ],
                'void',
            ],
        ];
        $writerMethods = [
            '__clone' => ['public', false, [], 'void'],
            '__construct' => [
                'public',
                false,
                [
                    ['opaqueIds',   'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',                false, 1],
                    ['selector',    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',    false, 1],
                    ['delivery',    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator', false, 1],
                    ['clock',       'Psr\\Clock\\ClockInterface',                                         false, 1],
                    ['actor',       'BlackOps\\Core\\ActorContext',                                       false, 1],
                    ['correlation', 'BlackOps\\Core\\Audit\\AuditRecordCorrelation',                      false, 1],
                    ['runtime',     $runtime,                                                             false, 0],
                    ['buildId',     'string',                                                             false, 1],
                ],
                '',
            ],
            '__debugInfo' => ['public', false, [], 'array'],
            '__serialize' => ['public', false, [], 'array'],
            '__unserialize' => ['public', false, [['data', 'array', false, 1]], 'void'],
            'assertConnection' => [
                'public',
                false,
                [
                    ['connection',      $connection, false, 1],
                    ['expectedRuntime', $runtime,    false, 0],
                ],
                'void',
            ],
            'deadLetterRetryRequested' => [
                'public',
                false,
                [
                    ['recordId',     'string', false, 1],
                    ['retryAuditId', 'string', false, 1],
                    ['tenant',       $tenant,  true,  1],
                ],
                'void',
            ],
            'deadLettered' => [
                'public',
                false,
                [
                    ['recordId',     'string', false, 1],
                    ['stateVersion', 'string', false, 1],
                    ['tenant',       $tenant,  true,  1],
                ],
                'void',
            ],
            'retryScheduled' => [
                'public',
                false,
                [
                    ['recordId',     'string', false, 1],
                    ['stateVersion', 'string', false, 1],
                    ['tenant',       $tenant,  true,  1],
                ],
                'void',
            ],
            'write' => [
                'private',
                false,
                [
                    ['eventType',     'string', false, 1],
                    ['recordId',      'string', false, 1],
                    ['discriminator', 'string', false, 1],
                    ['tenant',        $tenant,  true,  1],
                ],
                'void',
            ],
        ];

        foreach ([
            self::HOOK => [$hookMethods, 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHook'],
            self::WRITER => [$writerMethods, 'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditWriter'],
        ] as $path => [$contracts, $type]) {
            if (!isset($sources[$path])) {
                return false;
            }
            $classes = $this->classNodes($this->parse($sources[$path]));
            if (
                count($classes) !== 1
                || !$classes[0]->isFinal()
                || $classes[0]->name?->toString() !== $this->shortName($type)
            ) {
                return false;
            }
            $methods = $this->methodNodes($classes[0]);
            $actualNames = array_keys($methods);
            $expectedNames = array_keys($contracts);
            sort($actualNames);
            sort($expectedNames);
            if ($actualNames !== $expectedNames) {
                return false;
            }
            foreach ($contracts as $name => [$visibility, $static, $parameters, $return]) {
                $method = $methods[$name];
                $actualVisibility = $method->isPrivate()
                    ? 'private'
                    : ($method->isProtected() ? 'protected' : 'public');
                if (
                    $actualVisibility !== $visibility
                    || $method->isStatic() !== $static
                    || $this->astTypeName($method->returnType) !== $return
                ) {
                    return false;
                }
                $actualParameters = array_map(fn(Node\Param $parameter): array => $this->astParameterContract(
                    $parameter,
                ), $method->params);
                if ($actualParameters !== $parameters) {
                    return false;
                }
            }
            $properties = $this->propertyNodes($classes[0]);
            if (count($properties) !== 1 || count($properties[0]->props) !== 1) {
                return false;
            }
            $property = $properties[0];
            $propertyName = $property->props[0]->name->toString();
            if (
                !$property->isPrivate()
                || $property->isStatic()
                || $propertyName !== 'snapshot'
                || $this->astTypeName($property->type) !== '?SensitiveParameterValue'
                || !$property->props[0]->default instanceof Node\Expr\ConstFetch
                || strtolower($property->props[0]->default->name->toString()) !== 'null'
            ) {
                return false;
            }
        }

        return true;
    }

    private function writerGraphOracle(string $source): bool
    {
        $class = $this->classNode($this->parse($source));
        if ($class === null) {
            return false;
        }
        $write = $this->methodNodes($class)['write'] ?? null;
        if (!$write instanceof ClassMethod) {
            return false;
        }
        $expected = [
            'read',
            'definition',
            'recordId',
            'discriminator',
            'tenantReference',
            'occurredAt',
            'opaqueId',
            'opaqueId',
            'draft',
            'forDraft',
            'deliverInCurrentTransaction',
        ];
        $sequence = array_values(array_filter(
            $this->callNames($this->methodCalls($write)),
            static fn(string $name): bool => in_array($name, $expected, true),
        ));
        if ($sequence !== $expected) {
            return false;
        }
        if (
            $this->methodNames($this->parse($source), ['deliverStandalone', 'appendRequired', 'appendDurable']) !== []
        ) {
            return false;
        }
        $edges = $this->callDescriptors($this->methodCalls($write));
        $expectedEdges = [
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditSnapshot::read($this->snapshot)',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditCatalog::definition($eventType,$values[runtime])',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::recordId($recordId)',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::discriminator($values[runtime],$discriminator)',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::tenantReference($tenant)',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection::occurredAt($values[clock])',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection::opaqueId($values[opaqueIds],BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose::Resource,concat(str:generic-outbox:record:,$recordId))',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection::opaqueId($values[opaqueIds],BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose::Action,concat(concat(concat(concat(concat(str:generic-outbox:,$eventType),str::),$recordId),str::),$discriminator))',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection::draft($definition,$values[actor],$values[correlation],$source,$material)',
            'instance:$values[selector]->forDraft($draft)',
            'instance:$values[delivery]->deliverInCurrentTransaction($plan,$request)',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::remap($failure)',
            'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh(BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure::PolicyInvalid)',
        ];
        if ($edges !== $expectedEdges) {
            return false;
        }
        if (!in_array($expectedEdges[10], $this->directTryCallDescriptors($write), true)) {
            return false;
        }
        $targets = $this->newTargets($this->parse($source), ['AuditDeliveryRequest', 'SensitiveParameterValue']);

        return $targets === ['AuditDeliveryRequest', 'SensitiveParameterValue'];
    }

    private function hookGraphOracle(string $source): bool
    {
        $class = $this->classNode($this->parse($source));
        if ($class === null) {
            return false;
        }
        $expected = [
            'assertRelayEntry' => ['writer', 'assertConnection', 'entry', 'Relay'],
            'assertOperatorEntry' => ['writer', 'assertConnection', 'entry', 'Cli'],
            'retryScheduled' => ['writer', 'assertConnection', 'root', 'read', 'retryScheduled', 'Relay'],
            'deadLettered' => ['writer', 'assertConnection', 'root', 'read', 'deadLettered', 'Relay'],
            'deadLetterRetryRequested' => [
                'writer',
                'assertConnection',
                'root',
                'read',
                'deadLetterRetryRequested',
                'Cli',
            ],
        ];
        foreach ($expected as $method => $expectedCalls) {
            $node = $this->methodNodes($class)[$method] ?? null;
            if (!$node instanceof ClassMethod) {
                return false;
            }
            $actual = $this->callNames($this->methodCalls($node));
            $actual = array_values(array_filter($actual, static fn(string $name): bool => in_array(
                $name,
                [
                    'writer',
                    'assertConnection',
                    'entry',
                    'root',
                    'read',
                    'retryScheduled',
                    'deadLettered',
                    'deadLetterRetryRequested',
                ],
                true,
            )));
            $expectedNames = array_values(array_filter(
                $expectedCalls,
                static fn(string $name): bool => !in_array($name, ['Relay', 'Cli'], true),
            ));
            if ($actual !== $expectedNames) {
                return false;
            }
            $runtime = end($expectedCalls);
            if (!is_string($runtime) || $this->runtimeConstants($this->methodNodesAsNodes($node)) !== [$runtime]) {
                return false;
            }

            $directExpected = match ($method) {
                'assertRelayEntry' => [
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::writer($this->snapshot)',
                    'instance:$writer->assertConnection($connection,BlackOps\\Core\\Audit\\AuditSourceRuntime::Relay)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::entry($connection)',
                ],
                'assertOperatorEntry' => [
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::writer($this->snapshot)',
                    'instance:$writer->assertConnection($connection,BlackOps\\Core\\Audit\\AuditSourceRuntime::Cli)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::entry($connection)',
                ],
                'retryScheduled' => [
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::writer($this->snapshot)',
                    'instance:$writer->assertConnection($connection,BlackOps\\Core\\Audit\\AuditSourceRuntime::Relay)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::root($connection)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditRow::read($row)',
                    'instance:$writer->retryScheduled($value[record_id],$value[state_version],$value[tenant])',
                ],
                'deadLettered' => [
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::writer($this->snapshot)',
                    'instance:$writer->assertConnection($connection,BlackOps\\Core\\Audit\\AuditSourceRuntime::Relay)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::root($connection)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditRow::read($row)',
                    'instance:$writer->deadLettered($value[record_id],$value[state_version],$value[tenant])',
                ],
                'deadLetterRetryRequested' => [
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::writer($this->snapshot)',
                    'instance:$writer->assertConnection($connection,BlackOps\\Core\\Audit\\AuditSourceRuntime::Cli)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard::root($connection)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditRow::read($row)',
                    'instance:$writer->deadLetterRetryRequested($value[record_id],$retryAuditId,$value[tenant])',
                ],
                default => [],
            };
            if ($this->directTryCallDescriptors($node) !== $directExpected) {
                return false;
            }
            $failure = 'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::';
            $allExpected = [
                ...$directExpected,
                $failure . 'remap($failure)',
                $failure
                    . 'fresh(BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure::'
                    . (
                        $method === 'assertRelayEntry' || $method === 'assertOperatorEntry'
                            ? 'TransactionInvalid'
                            : 'PolicyInvalid'
                    )
                    . ')',
            ];
            if ($this->callDescriptors($this->methodCalls($node)) !== $allExpected) {
                return false;
            }
        }

        $assertCalls = $this->methodNames($this->parse($source), ['assertConnection']);

        return $assertCalls === [
            'assertConnection',
            'assertConnection',
            'assertConnection',
            'assertConnection',
            'assertConnection',
        ];
    }

    /** @param array<string,string> $sources */
    private function helperCallOracle(array $sources): bool
    {
        $allowedInstances = [
            self::FAILURE => ['failure' => ['$failure']],
            self::HOOK_GUARD => [
                'getValue' => ['$snapshot'],
                'isAutoCommit' => ['$connection'],
                'isTransactionActive' => ['$connection'],
                'getTransactionNestingLevel' => ['$connection'],
            ],
            self::ROW => [
                'type' => ['$tenant'],
                'id' => ['$tenant'],
            ],
            self::SNAPSHOT => [
                'getValue' => ['$snapshot'],
                'toArray' => ['$actor->principal', '$actor->execution', '$correlation'],
            ],
            self::IDENTITY => [
                'type' => ['$tenant'],
                'id' => ['$tenant'],
            ],
            self::CATALOG => [],
            self::PROJECTION => [
                'execution' => ['$actor'],
                'authorization' => ['$actor'],
                'toArray' => ['$correlation'],
                'now' => ['$clock'],
                'setTimezone' => ['$clock->now()'],
                'format' => ['$clock->now()->setTimezone()'],
                'derive' => ['$opaqueIds'],
                'type' => ['$reference'],
                'id' => ['$reference'],
            ],
        ];
        $allowedStatic = [
            'self' => ['fresh', 'remap', 'tenant', 'catalog', 'matches', 'reference', 'stateVersion', 'retryAuditId'],
            'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure' => ['fresh', 'remap'],
            'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditHookGuard' => ['writer', 'entry', 'root'],
            'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditRow' => ['tenant'],
            'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity' => [
                'recordId',
                'stateVersion',
                'tenantReference',
            ],
            'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditCatalog' => ['catalog', 'matches'],
            'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection' => ['reference'],
            'BlackOps\\Core\\Audit\\AuditValidationException' => ['assertGrammarString', 'assertSafeString'],
            'BlackOps\\Core\\Audit\\AuditRecordCorrelation' => ['fromArray'],
            'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog' => ['catalog'],
        ];
        $allowedNew = [
            self::FAILURE => ['AuditDeliveryException'],
            self::HOOK_GUARD => ['LogicException'],
            self::ROW => ['AuditTypedReference', 'LogicException', 'TenantRef'],
            self::SNAPSHOT => ['AuditRecordActor', 'AuditTypedReference', 'LogicException'],
            self::IDENTITY => ['AuditTypedReference', 'LogicException'],
            self::CATALOG => ['LogicException'],
            self::PROJECTION => [
                'DateTimeZone',
                'AuditRecordActor',
                'AuditRecordAction',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
                'AuditTypedReference',
                'LogicException',
            ],
        ];
        $allowedFunctions = [
            self::FAILURE => [
                '__construct' => [],
                'fresh' => [],
                'remap' => [],
            ],
            self::HOOK_GUARD => [
                '__construct' => [],
                'writer' => ['array_keys', 'is_array'],
                'entry' => [],
                'root' => [],
            ],
            self::ROW => [
                '__construct' => [],
                'read' => ['array_diff', 'array_diff', 'array_keys', 'count', 'is_string', 'is_string'],
                'tenant' => ['is_string', 'is_string', 'trim', 'trim'],
            ],
            self::SNAPSHOT => [
                '__construct' => [],
                'read' => ['array_keys', 'in_array', 'is_array', 'is_string'],
            ],
            self::IDENTITY => [
                '__construct' => [],
                'recordId' => [],
                'stateVersion' => ['strcmp', 'strlen'],
                'retryAuditId' => [],
                'discriminator' => [],
                'tenantReference' => ['trim', 'trim'],
            ],
            self::CATALOG => [
                '__construct' => [],
                'definition' => [],
                'catalog' => [],
                'matches' => ['count'],
            ],
            self::PROJECTION => [
                '__construct' => [],
                'capture' => ['in_array'],
                'occurredAt' => ['preg_match'],
                'opaqueId' => [],
                'draft' => ['array_keys', 'array_keys', 'in_array'],
                'reference' => [],
            ],
        ];
        $allowedStaticByMethod = [
            self::FAILURE => [
                '__construct' => [],
                'fresh' => [],
                'remap' => ['self::fresh', 'self::fresh'],
            ],
            self::HOOK_GUARD => [
                '__construct' => [],
                'writer' => ['BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh'],
                'entry' => ['BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh'],
                'root' => ['BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh'],
            ],
            self::ROW => [
                '__construct' => [],
                'read' => [
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::remap',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::recordId',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::stateVersion',
                    'self::tenant',
                ],
                'tenant' => [],
            ],
            self::SNAPSHOT => [
                '__construct' => [],
                'read' => [
                    'BlackOps\\Core\\Audit\\AuditRecordCorrelation::fromArray',
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertSafeString',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                ],
            ],
            self::IDENTITY => [
                '__construct' => [],
                'recordId' => [
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertGrammarString',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                ],
                'stateVersion' => [
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertGrammarString',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                ],
                'retryAuditId' => [
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertGrammarString',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                ],
                'discriminator' => [
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'self::retryAuditId',
                    'self::stateVersion',
                ],
                'tenantReference' => ['BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh'],
            ],
            self::CATALOG => [
                '__construct' => [],
                'definition' => [
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::remap',
                    'self::catalog',
                    'self::matches',
                ],
                'catalog' => [
                    'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::catalog',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                ],
                'matches' => [],
            ],
            self::PROJECTION => [
                '__construct' => [],
                'capture' => [
                    'BlackOps\\Core\\Audit\\AuditRecordCorrelation::fromArray',
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertSafeString',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'self::reference',
                    'self::reference',
                ],
                'occurredAt' => [
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertSafeString',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                ],
                'opaqueId' => [
                    'BlackOps\\Core\\Audit\\AuditValidationException::assertSafeString',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::remap',
                ],
                'draft' => [
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh',
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::remap',
                ],
                'reference' => [],
            ],
        ];
        $allowedNewByMethod = [
            self::FAILURE => [
                '__construct' => [],
                'fresh' => ['AuditDeliveryException'],
                'remap' => [],
            ],
            self::HOOK_GUARD => [
                '__construct' => [],
                'writer' => ['LogicException', 'LogicException'],
                'entry' => ['LogicException'],
                'root' => ['LogicException'],
            ],
            self::ROW => [
                '__construct' => [],
                'read' => ['LogicException', 'LogicException'],
                'tenant' => ['AuditTypedReference', 'LogicException', 'TenantRef'],
            ],
            self::SNAPSHOT => [
                '__construct' => [],
                'read' => [
                    'AuditRecordActor',
                    'AuditTypedReference',
                    'AuditTypedReference',
                    'LogicException',
                    'LogicException',
                    'LogicException',
                ],
            ],
            self::IDENTITY => [
                '__construct' => [],
                'recordId' => [],
                'stateVersion' => ['LogicException'],
                'retryAuditId' => [],
                'discriminator' => [],
                'tenantReference' => ['AuditTypedReference', 'LogicException'],
            ],
            self::CATALOG => [
                '__construct' => [],
                'definition' => [],
                'catalog' => ['LogicException'],
                'matches' => [],
            ],
            self::PROJECTION => [
                '__construct' => [],
                'capture' => ['AuditRecordActor', 'LogicException', 'LogicException'],
                'occurredAt' => ['DateTimeZone', 'LogicException'],
                'opaqueId' => [],
                'draft' => [
                    'AuditRecordAction',
                    'AuditRecordDraft',
                    'AuditRecordEvent',
                    'AuditRecordEvidence',
                    'AuditRecordOutcome',
                    'AuditRecordPolicy',
                    'AuditRecordResource',
                    'AuditRecordSource',
                    'LogicException',
                    'LogicException',
                ],
                'reference' => ['AuditTypedReference'],
            ],
        ];
        $allowedInstancesByMethod = [
            self::FAILURE => [
                '__construct' => [],
                'fresh' => [],
                'remap' => ['failure' => ['$failure']],
            ],
            self::HOOK_GUARD => [
                '__construct' => [],
                'writer' => ['getValue' => ['$snapshot']],
                'entry' => [
                    'isAutoCommit' => ['$connection'],
                    'isTransactionActive' => ['$connection'],
                    'getTransactionNestingLevel' => ['$connection'],
                ],
                'root' => [
                    'isAutoCommit' => ['$connection'],
                    'isTransactionActive' => ['$connection'],
                    'getTransactionNestingLevel' => ['$connection'],
                ],
            ],
            self::ROW => [
                '__construct' => [],
                'read' => [],
                'tenant' => [],
            ],
            self::SNAPSHOT => [
                '__construct' => [],
                'read' => [
                    'getValue' => ['$snapshot'],
                    'toArray' => ['$actor->principal', '$actor->execution', '$correlation'],
                ],
            ],
            self::IDENTITY => [
                '__construct' => [],
                'recordId' => [],
                'stateVersion' => [],
                'retryAuditId' => [],
                'discriminator' => [],
                'tenantReference' => ['type' => ['$tenant'], 'id' => ['$tenant']],
            ],
            self::CATALOG => [
                '__construct' => [],
                'definition' => [],
                'catalog' => [],
                'matches' => [],
            ],
            self::PROJECTION => [
                '__construct' => [],
                'capture' => [
                    'execution' => ['$actor'],
                    'authorization' => ['$actor'],
                    'toArray' => ['$correlation'],
                ],
                'occurredAt' => [
                    'format' => ['$clock->now()->setTimezone()'],
                    'setTimezone' => ['$clock->now()'],
                    'now' => ['$clock'],
                ],
                'opaqueId' => ['derive' => ['$opaqueIds']],
                'draft' => [],
                'reference' => ['type' => ['$reference'], 'id' => ['$reference']],
            ],
        ];

        foreach (array_keys(self::HELPERS) as $path) {
            if (!isset($sources[$path])) {
                return false;
            }
            $nodes = $this->parse($sources[$path]);
            foreach ($this->methodCallsFromNodes($nodes) as $call) {
                if ($call instanceof StaticCall) {
                    $target = $this->staticTarget($call);
                    if (
                        $target === null
                        || !$call->name instanceof Identifier
                        || !in_array($call->name->toString(), $allowedStatic[$target] ?? [], true)
                    ) {
                        return false;
                    }
                    continue;
                }
                if (!($call instanceof MethodCall || $call instanceof NullsafeMethodCall)) {
                    continue;
                }
                $name = $call->name instanceof Identifier ? $call->name->toString() : null;
                $receiver = $this->exprDescriptor($call->var);
                if ($name === null || !in_array($receiver, $allowedInstances[$path][$name] ?? [], true)) {
                    return false;
                }
            }
            foreach ($this->allNewTargets($nodes) as $target) {
                if (!in_array($target, $allowedNew[$path] ?? [], true)) {
                    return false;
                }
            }
            $class = $this->classNode($nodes);
            if ($class === null) {
                return false;
            }
            foreach ($this->methodNodes($class) as $name => $method) {
                foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], FuncCall::class) as $function) {
                    if ($function instanceof FuncCall && !$function->name instanceof Name) {
                        return false;
                    }
                }
                $actualFunctions = $this->functionNames($this->methodNodesAsNodes($method));
                $expectedFunctions = $allowedFunctions[$path][$name] ?? null;
                if ($expectedFunctions === null) {
                    return false;
                }
                sort($actualFunctions);
                sort($expectedFunctions);
                if ($actualFunctions !== $expectedFunctions) {
                    return false;
                }
                $actualStatic = [];
                foreach ($this->methodCalls($method) as $call) {
                    if (!$call instanceof StaticCall) {
                        continue;
                    }
                    $target = $this->staticTarget($call);
                    if ($target === null || !$call->name instanceof Identifier) {
                        return false;
                    }
                    $actualStatic[] = $target . '::' . $call->name->toString();
                }
                sort($actualStatic);
                $expectedStatic = $allowedStaticByMethod[$path][$name] ?? null;
                if ($expectedStatic === null) {
                    return false;
                }
                sort($expectedStatic);
                if ($actualStatic !== $expectedStatic) {
                    return false;
                }
                $actualNew = $this->allNewTargets($this->methodNodesAsNodes($method));
                $expectedNew = $allowedNewByMethod[$path][$name] ?? null;
                if ($expectedNew === null) {
                    return false;
                }
                sort($expectedNew);
                if ($actualNew !== $expectedNew) {
                    return false;
                }
                $actualInstances = [];
                foreach ($this->methodCalls($method) as $call) {
                    if (!($call instanceof MethodCall || $call instanceof NullsafeMethodCall)) {
                        continue;
                    }
                    if (!$call->name instanceof Identifier) {
                        return false;
                    }
                    $actualInstances[$call->name->toString()][] = $this->exprDescriptor($call->var);
                }
                $expectedInstances = $allowedInstancesByMethod[$path][$name] ?? null;
                if ($expectedInstances === null || $actualInstances !== $expectedInstances) {
                    return false;
                }
            }
        }

        return true;
    }

    private function snapshotGraphOracle(string $source): bool
    {
        $class = $this->classNode($this->parse($source));
        if ($class === null) {
            return false;
        }
        $read = $this->methodNodes($class)['read'] ?? null;
        if (!$read instanceof ClassMethod) {
            return false;
        }
        $calls = $this->methodCalls($read);
        $names = $this->callNames($calls);
        $newTargets = $this->newTargets($this->methodNodesAsNodes($read), ['AuditRecordActor', 'AuditTypedReference']);
        $static = $this->staticCallNames($this->methodNodesAsNodes($read));

        return (
            count(array_values(array_filter($names, static fn(string $name): bool => $name === 'toArray'))) === 3
            && $newTargets === ['AuditRecordActor', 'AuditTypedReference', 'AuditTypedReference']
            && $static === [
                'AuditRecordCorrelation::fromArray',
                'AuditValidationException::assertSafeString',
                'GenericOutboxAuditFailure::fresh',
            ]
            && !array_intersect($names, [
                'now',
                'derive',
                'forDraft',
                'deliverInCurrentTransaction',
                'usesConnection',
                'beginTransaction',
                'commit',
            ])
        );
    }

    /** @param array<string,string> $sources */
    private function ownershipOracle(array $sources, ?string &$failure = null): bool
    {
        $owners = [];
        $allSources = $sources;
        $sourceRoot = realpath(__DIR__ . '/../../src');
        if ($sourceRoot !== false) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                $sourceRoot,
                \FilesystemIterator::SKIP_DOTS,
            ));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $path =
                    'src/'
                    . str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($sourceRoot) + 1));
                if (isset($allSources[$path])) {
                    continue;
                }
                $source = file_get_contents($file->getPathname());
                if (!is_string($source)) {
                    $failure = 'repository source cannot be read: ' . $path;
                    return false;
                }
                $allSources[$path] = $source;
            }
        }
        foreach ($allSources as $path => $source) {
            if (!in_array($path, self::O3_SOURCES, true)) {
                $nodes = $this->parse($source);
                if (
                    array_intersect(
                        $this->allNewTargets($nodes),
                        ['GenericOutboxAuditHook', 'GenericOutboxAuditWriter'],
                    ) !== []
                ) {
                    $failure = 'unlisted construction in ' . $path;
                    return false;
                }
                continue;
            }
            $nodes = $this->parse($source);
            foreach ($this->callNames($this->methodCallsFromNodes($nodes)) as $name) {
                $owners[$name][$path] = ($owners[$name][$path] ?? 0) + 1;
            }
            foreach ($this->functionNames($nodes) as $name) {
                $owners[$name][$path] = ($owners[$name][$path] ?? 0) + 1;
            }
        }
        $only = static fn(array $map, string $path, int $count): bool => $map === [$path => $count];
        foreach ([
            'forDraft' => self::WRITER,
            'deliverInCurrentTransaction' => self::WRITER,
            'usesConnection' => self::WRITER,
            'now' => self::PROJECTION,
            'derive' => self::PROJECTION,
            'random_bytes' => self::STORE,
            'assertConnection' => self::HOOK,
        ] as $name => $path) {
            $count = $name === 'assertConnection' ? 5 : 1;
            if (!$only($owners[$name] ?? [], $path, $count)) {
                $failure = 'owner ' . $name . ': ' . var_export($owners[$name] ?? [], true);
                return false;
            }
        }
        foreach (array_keys(self::HELPERS) as $path) {
            foreach ([
                'beginTransaction',
                'commit',
                'rollBack',
                'close',
                'executeStatement',
                'fetchAssociative',
                'fetchAllAssociative',
                'transactional',
                'random_bytes',
            ] as $name) {
                if (isset($owners[$name][$path])) {
                    $failure = 'forbidden ' . $name . ' in ' . $path;
                    return false;
                }
            }
        }
        foreach (['isAutoCommit', 'isTransactionActive', 'getTransactionNestingLevel'] as $name) {
            if (($owners[$name] ?? []) !== [self::HOOK_GUARD => 2, self::STORE => 3]) {
                $failure = 'transaction owner ' . $name . ': ' . var_export($owners[$name] ?? [], true);
                return false;
            }
        }

        return true;
    }

    private function storeOrderOracle(string $source, ?string &$failure = null): bool
    {
        $nodes = $this->parse($source);
        $class = $this->classNode($nodes);
        if ($class === null) {
            return false;
        }
        if (!$this->cleanupOracle($class)) {
            $failure = 'cleanupOracle';
            return false;
        }
        foreach ([
            'scheduleRetryEnabled' => 'retryScheduled',
            'moveToDeadLetterEnabled' => 'deadLettered',
            'retryDeadLetterEnabled' => 'deadLetterRetryRequested',
        ] as $method => $phase) {
            $node = $this->methodNodes($class)[$method] ?? null;
            if (!$node instanceof ClassMethod) {
                $failure = $method . ': method missing';
                return false;
            }
            $names = $this->callNames($this->methodCalls($node));
            $begin = array_search('beginTransaction', $names, true);
            $phaseIndex = array_search($phase, $names, true);
            if (!is_int($begin) || !is_int($phaseIndex) || $phaseIndex <= $begin) {
                $failure = $method . ': begin/phase order ' . var_export($names, true);
                return false;
            }
            if (($names[$begin + 1] ?? null) !== 'assertActiveRoot') {
                $failure = $method . ': no immediate active root ' . var_export($names, true);
                return false;
            }
            if (($names[$phaseIndex + 1] ?? null) !== 'assertActiveRoot') {
                $failure = $method . ': no post-hook active root ' . var_export($names, true);
                return false;
            }
            $commit = array_search('commit', $names, true);
            $idle = array_search('assertIdleRoot', $names, true);
            if (!is_int($commit) || !is_int($idle) || $commit <= $phaseIndex || $idle <= $commit) {
                $failure = $method . ': commit/idle order ' . var_export($names, true);
                return false;
            }
            $strings = $this->stringValues($this->methodNodesAsNodes($node));
            $returning = array_values(array_filter($strings, static fn(string $value): bool => str_contains(
                $value,
                'RETURNING',
            )));
            if (
                count($returning) !== 1
                || !str_contains(
                    $returning[0],
                    'record_id::text AS record_id, tenant_type, tenant_id, state_version::text AS state_version',
                )
            ) {
                $failure = $method . ': RETURNING SQL';
                return false;
            }
            if (
                $method === 'retryDeadLetterEnabled'
                && !array_filter($strings, static fn(string $value): bool => str_contains($value, 'INSERT INTO'))
            ) {
                $failure = $method . ': missing retry INSERT SQL';
                return false;
            }
            if (!$this->storeMethodOracle($node, $method, $phase)) {
                $failure = $method . ': storeMethodOracle';
                return false;
            }
        }
        foreach (['insert', 'claimBatch', 'heartbeat', 'markSent', 'migrate'] as $method) {
            $node = $this->methodNodes($class)[$method] ?? null;
            if (!$node instanceof ClassMethod || $this->phaseHookCalls($this->methodCalls($node)) !== []) {
                $failure = $method . ': silent phase hook';
                return false;
            }
        }

        return true;
    }

    private function storeMethodOracle(ClassMethod $method, string $methodName, string $phase): bool
    {
        $calls = $this->methodCalls($method);
        $allowedNames = $methodName === 'retryDeadLetterEnabled'
            ? [
                'assertOperatorEntry',
                'beginTransaction',
                'assertActiveRoot',
                'fetchAssociative',
                'executeStatement',
                'deadLetterRetryRequested',
                'commit',
                'assertIdleRoot',
                'failEnabled',
            ]
            : [
                'assertRelayEntry',
                'beginTransaction',
                'assertActiveRoot',
                'fetchAssociative',
                $phase,
                'commit',
                'assertIdleRoot',
                'failEnabled',
            ];
        foreach ($calls as $call) {
            if (
                !($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                || !$call->name instanceof Identifier
            ) {
                continue;
            }
            $name = $call->name->toString();
            $receiver = $this->exprDescriptor($call->var);
            if ($name === 'assertRelayEntry' || $name === 'assertOperatorEntry' || $name === $phase) {
                if ($receiver !== '$this->phaseHook') {
                    return false;
                }
            }
            if ($receiver === '$this->connection') {
                if (!in_array($name, ['beginTransaction', 'fetchAssociative', 'executeStatement', 'commit'], true)) {
                    return false;
                }
            } elseif (in_array($name, ['assertActiveRoot', 'assertIdleRoot', 'failEnabled'], true)) {
                if ($this->exprDescriptor($call->var) !== '$this') {
                    return false;
                }
            } elseif (
                $receiver === '$this'
                && !in_array($name, ['assertActiveRoot', 'assertIdleRoot', 'failEnabled', 'uuid4', 'timestamp'], true)
            ) {
                return false;
            }
        }
        $expectedRelevant = $methodName === 'retryDeadLetterEnabled'
            ? [
                'assertOperatorEntry',
                'beginTransaction',
                'assertActiveRoot',
                'fetchAssociative',
                'executeStatement',
                'fetchAssociative',
                $phase,
                'assertActiveRoot',
                'commit',
                'assertIdleRoot',
                'failEnabled',
            ]
            : [
                'assertRelayEntry',
                'beginTransaction',
                'assertActiveRoot',
                'fetchAssociative',
                $phase,
                'assertActiveRoot',
                'commit',
                'assertIdleRoot',
                'failEnabled',
            ];
        $relevant = array_values(array_filter($this->callNames($calls), static fn(string $name): bool => in_array(
            $name,
            $allowedNames,
            true,
        )));
        if ($relevant !== $expectedRelevant) {
            return false;
        }
        $phaseNodes = array_values(array_filter(
            $calls,
            fn(Node $call): bool => (
                ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                && $call->name instanceof Identifier
                && $this->isThisProperty($call->var, 'phaseHook')
            ),
        ));
        $expectedHookNames = $methodName === 'retryDeadLetterEnabled'
            ? ['assertOperatorEntry', $phase]
            : ['assertRelayEntry', $phase];
        if ($this->phaseHookCalls($calls) !== $expectedHookNames || count($phaseNodes) !== 2) {
            return false;
        }
        $admission = $phaseNodes[0];
        $phaseCall = $phaseNodes[1];
        if (!$admission instanceof NullsafeMethodCall || !$phaseCall instanceof NullsafeMethodCall) {
            return false;
        }
        $admissionArgs = array_map(fn(Node\Arg $arg): string => $this->exprDescriptor($arg->value), $admission->args);
        if ($admissionArgs !== ['$this->connection']) {
            return false;
        }
        $expectedPhaseArgs = $methodName === 'retryDeadLetterEnabled'
            ? ['$this->connection', '$row', '$retryAuditId']
            : ['$this->connection', '$row'];
        $phaseArgs = array_map(fn(Node\Arg $arg): string => $this->exprDescriptor($arg->value), $phaseCall->args);
        if ($phaseArgs !== $expectedPhaseArgs) {
            return false;
        }
        $begin = $this->findCallNode($calls, 'beginTransaction');
        if (!$begin instanceof MethodCall || $this->exprDescriptor($begin->var) !== '$this->connection') {
            return false;
        }
        if ($admission->getStartLine() >= $begin->getStartLine()) {
            return false;
        }
        $activeAfterBegin = null;
        foreach ($calls as $call) {
            if (
                ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                && $call->name instanceof Identifier
                && $call->name->toString() === 'assertActiveRoot'
                && $call->getStartLine() > $begin->getStartLine()
            ) {
                $activeAfterBegin = $call;
                break;
            }
        }
        if (!$activeAfterBegin instanceof MethodCall || $this->exprDescriptor($activeAfterBegin->var) !== '$this') {
            return false;
        }
        foreach ($calls as $call) {
            if (
                !($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                || !$call->name instanceof Identifier
                || $call->getStartLine() <= $begin->getStartLine()
                || $call->getStartLine() >= $activeAfterBegin->getStartLine()
            ) {
                continue;
            }
            if (in_array(
                $call->name->toString(),
                ['fetchAssociative', 'executeStatement', 'uuid4', 'timestamp'],
                true,
            )) {
                return false;
            }
        }
        $attemptedWrites = [];
        $attemptedTrue = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Expr\Assign::class) as $assignment) {
            if (
                $assignment instanceof Node\Expr\Assign
                && $assignment->var instanceof Node\Expr\Variable
                && $assignment->var->name === 'attempted'
            ) {
                $attemptedWrites[] = $assignment;
                if (
                    $assignment->expr instanceof Node\Expr\ConstFetch
                    && strtolower($assignment->expr->name->toString()) === 'true'
                ) {
                    $attemptedTrue[] = $assignment;
                }
            }
        }
        $attemptedFalse = array_values(array_filter(
            $attemptedWrites,
            static fn(Node\Expr\Assign $assignment): bool => (
                $assignment->expr instanceof Node\Expr\ConstFetch
                && strtolower($assignment->expr->name->toString()) === 'false'
            ),
        ));
        if (
            count($attemptedWrites) !== 2
            || count($attemptedTrue) !== 1
            || count($attemptedFalse) !== 1
            || $attemptedFalse[0]->getStartLine() >= $attemptedTrue[0]->getStartLine()
            || $attemptedTrue[0]->getStartLine() >= $begin->getStartLine()
            || array_filter(
                $attemptedWrites,
                fn(Node\Expr\Assign $assignment): bool => $assignment->getStartLine() > $begin->getStartLine(),
            ) !== []
        ) {
            return false;
        }
        $failureMessage = match ($methodName) {
            'scheduleRetryEnabled' => 'Failed to schedule PostgreSQL outbox retry.',
            'moveToDeadLetterEnabled' => 'Failed to dead-letter PostgreSQL outbox record.',
            'retryDeadLetterEnabled' => 'Failed to retry dead-lettered outbox record.',
            default => '',
        };
        $failureCalls = array_values(array_filter(
            $calls,
            fn(Node $call): bool => (
                ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                && $call->name instanceof Identifier
                && $call->name->toString() === 'failEnabled'
                && $this->exprDescriptor($call->var) === '$this'
            ),
        ));
        if (
            count($failureCalls) !== 1
            || $this->callDescriptor($failureCalls[0])
                !== 'instance:$this->failEnabled($attempted,$known,str:' . $failureMessage . ')'
        ) {
            return false;
        }
        $rowWrites = [];
        $rowAssignments = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Expr\Assign::class) as $assignment) {
            if (
                $assignment instanceof Node\Expr\Assign
                && $assignment->var instanceof Node\Expr\Variable
                && $assignment->var->name === 'row'
            ) {
                $rowWrites[] = $assignment;
                if (
                    $assignment->expr instanceof MethodCall
                    && $assignment->expr->name instanceof Identifier
                    && $assignment->expr->name->toString() === 'fetchAssociative'
                ) {
                    $rowAssignments[] = $assignment;
                }
            }
        }
        if (count($rowWrites) !== 1 || count($rowAssignments) !== 1) {
            return false;
        }
        $rowFetch = $rowAssignments[0]->expr;
        if ($this->exprDescriptor($rowFetch->var) !== '$this->connection' || count($rowFetch->args) < 1) {
            return false;
        }
        $rowQuery = $rowFetch->args[0]->value;
        if (!$rowQuery instanceof String_ && !$rowQuery instanceof Encapsed) {
            return false;
        }
        $rowQueryText = implode('', $this->stringValues([$rowQuery]));
        if (!str_contains($rowQueryText, 'RETURNING')) {
            return false;
        }
        if ($rowQuery instanceof Encapsed) {
            $tableCalls = array_values(array_filter(
                $this->methodCallsFromNodes([$rowQuery]),
                fn(Node $call): bool => (
                    ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                    && $call->name instanceof Identifier
                    && $call->name->toString() === 'table'
                    && $this->exprDescriptor($call->var) === '$this->schema'
                ),
            ));
            if (count($tableCalls) !== 1) {
                return false;
            }
        }
        if ($methodName === 'retryDeadLetterEnabled') {
            $selectedAssignments = [];
            foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Expr\Assign::class) as $assignment) {
                if (
                    $assignment instanceof Node\Expr\Assign
                    && $assignment->var instanceof Node\Expr\Variable
                    && $assignment->var->name === 'selected'
                    && $assignment->expr instanceof MethodCall
                    && $assignment->expr->name instanceof Identifier
                    && $assignment->expr->name->toString() === 'fetchAssociative'
                ) {
                    $selectedAssignments[] = $assignment;
                }
            }
            if (
                count($selectedAssignments) !== 1
                || !$this->hasNoRowGuardBefore($method, $phaseCall, 'selected', $selectedAssignments[0]->expr)
            ) {
                return false;
            }
        }
        $direct = $this->directTryCallDescriptors($method);
        if (
            !in_array($this->callDescriptor($admission), $direct, true)
            || !in_array($this->callDescriptor($begin), $direct, true)
            || !in_array($this->callDescriptor($phaseCall), $direct, true)
        ) {
            return false;
        }
        if (!$this->hasNoRowGuardBefore($method, $phaseCall, 'row', $rowFetch)) {
            return false;
        }
        if ($methodName === 'retryDeadLetterEnabled') {
            $uuidCalls = array_values(array_filter(
                $calls,
                fn(Node $call): bool => (
                    ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
                    && $call->name instanceof Identifier
                    && $call->name->toString() === 'uuid4'
                ),
            ));
            $uuidAssignments = [];
            foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Expr\Assign::class) as $assignment) {
                if (
                    $assignment instanceof Node\Expr\Assign
                    && $assignment->var instanceof Node\Expr\Variable
                    && $assignment->var->name === 'retryAuditId'
                ) {
                    $uuidAssignments[] = $assignment;
                }
            }
            if (
                count($uuidCalls) !== 1
                || count($uuidAssignments) !== 1
                || !$uuidAssignments[0]->expr instanceof MethodCall
                || !$uuidAssignments[0]->expr->name instanceof Identifier
                || $uuidAssignments[0]->expr->name->toString() !== 'uuid4'
                || $this->exprDescriptor($uuidAssignments[0]->expr->var) !== '$this'
                || $this->arrayValueBindings($method, 'audit_id') !== ['$retryAuditId']
            ) {
                return false;
            }
        }

        return true;
    }

    private function cleanupOracle(Class_ $class): bool
    {
        $methods = $this->methodNodes($class);
        $failure = $methods['failEnabled'] ?? null;
        $method = $methods['cleanupOwned'] ?? null;
        if (!$failure instanceof ClassMethod || !$method instanceof ClassMethod || $method->params !== []) {
            return false;
        }
        $failureStatements = $this->methodNodesAsNodes($failure);
        if (
            count($failureStatements) !== 2
            || !$failureStatements[0] instanceof Node\Stmt\Expression
            || !$failureStatements[0]->expr instanceof Node\Expr\Assign
            || !$failureStatements[1] instanceof Node\Stmt\Expression
            || !$failureStatements[1]->expr instanceof Node\Expr\Throw_
        ) {
            return false;
        }
        $gate = $failureStatements[0]->expr;
        if (
            !$gate->var instanceof Node\Expr\Variable
            || $gate->var->name !== 'clean'
            || !$gate->expr instanceof Node\Expr\BinaryOp\BooleanOr
            || !$gate->expr->left instanceof Node\Expr\BooleanNot
            || !$gate->expr->left->expr instanceof Node\Expr\Variable
            || $gate->expr->left->expr->name !== 'attempted'
            || !$gate->expr->right instanceof MethodCall
            || !$gate->expr->right->name instanceof Identifier
            || $gate->expr->right->name->toString() !== 'cleanupOwned'
            || $this->exprDescriptor($gate->expr->right->var) !== '$this'
            || $gate->expr->right->args !== []
        ) {
            return false;
        }
        $failureCalls = $this->methodCalls($failure);
        $cleanupCall = $failureCalls[0] ?? null;
        if (
            $this->callNames($failureCalls) !== ['cleanupOwned']
            || count($failureCalls) !== 1
            || !$cleanupCall instanceof MethodCall
            || $this->callDescriptor($cleanupCall) !== 'instance:$this->cleanupOwned()'
        ) {
            return false;
        }

        $calls = $this->methodCalls($method);
        if (
            $this->callNames($calls) !== [
                'isAutoCommit',
                'isTransactionActive',
                'getTransactionNestingLevel',
                'rollBack',
                'close',
            ]
        ) {
            return false;
        }
        $statements = $this->methodNodesAsNodes($method);
        if (
            count($statements) !== 4
            || !$statements[0] instanceof Node\Stmt\Expression
            || !$statements[0]->expr instanceof Node\Expr\Assign
            || !$statements[1] instanceof Node\Stmt\TryCatch
            || !$statements[2] instanceof Node\Stmt\TryCatch
            || !$statements[3] instanceof Node\Stmt\Return_
        ) {
            return false;
        }
        $initialization = $statements[0]->expr;
        if (
            !$initialization->var instanceof Node\Expr\Variable
            || $initialization->var->name !== 'clean'
            || !$initialization->expr instanceof Node\Expr\ConstFetch
            || strtolower($initialization->expr->name->toString()) !== 'true'
        ) {
            return false;
        }
        $rollbackTry = $statements[1];
        $rollbackIf = $rollbackTry->stmts[0] ?? null;
        if (!$rollbackIf instanceof Node\Stmt\If_ || $rollbackIf->else !== null || count($rollbackIf->stmts) !== 1) {
            return false;
        }
        if (
            $this->callNames($this->methodCallsFromNodes([$rollbackIf->cond])) !== [
                'isAutoCommit',
                'isTransactionActive',
                'getTransactionNestingLevel',
            ]
            || !$rollbackIf->cond instanceof Node\Expr\BinaryOp\BooleanAnd
            || !$rollbackIf->cond->left instanceof Node\Expr\BinaryOp\BooleanAnd
            || !$rollbackIf->cond->left->left instanceof MethodCall
            || !$rollbackIf->cond->left->right instanceof MethodCall
            || !$rollbackIf->cond->right instanceof Node\Expr\BinaryOp\Identical
            || !$rollbackIf->cond->left->left->name instanceof Identifier
            || !$rollbackIf->cond->left->right->name instanceof Identifier
            || $rollbackIf->cond->left->left->name->toString() !== 'isAutoCommit'
            || $rollbackIf->cond->left->right->name->toString() !== 'isTransactionActive'
            || $this->exprDescriptor($rollbackIf->cond->left->left->var) !== '$this->connection'
            || $this->exprDescriptor($rollbackIf->cond->left->right->var) !== '$this->connection'
            || new NodeFinder()->findInstanceOf([$rollbackIf->cond], Node\Expr\BooleanNot::class) !== []
        ) {
            return false;
        }
        if (new NodeFinder()->findInstanceOf([$rollbackIf->cond], Node\Expr\BinaryOp\NotIdentical::class) !== []) {
            return false;
        }
        $levelChecks = new NodeFinder()->findInstanceOf([$rollbackIf->cond], Node\Expr\BinaryOp\Identical::class);
        if (count($levelChecks) !== 1 || !$levelChecks[0] instanceof Node\Expr\BinaryOp\Identical) {
            return false;
        }
        $levelCheck = $levelChecks[0];
        if (
            $this->exprDescriptor($levelCheck->left) !== '$this->connection->getTransactionNestingLevel()'
            || $this->exprDescriptor($levelCheck->right) !== 'int:1'
        ) {
            return false;
        }
        $rollback = $rollbackIf->stmts[0];
        if (
            !$rollback instanceof Node\Stmt\Expression
            || !$rollback->expr instanceof MethodCall
            || !$rollback->expr->name instanceof Identifier
            || $rollback->expr->name->toString() !== 'rollBack'
            || $this->exprDescriptor($rollback->expr->var) !== '$this->connection'
        ) {
            return false;
        }
        $closeTry = $statements[2];
        $close = $closeTry->stmts[0] ?? null;
        if (
            !$close instanceof Node\Stmt\Expression
            || !$close->expr instanceof MethodCall
            || !$close->expr->name instanceof Identifier
            || $close->expr->name->toString() !== 'close'
            || $this->exprDescriptor($close->expr->var) !== '$this->connection'
        ) {
            return false;
        }
        $return = $statements[3];

        return (
            $return instanceof Node\Stmt\Return_
            && $return->expr instanceof Node\Expr\Variable
            && $return->expr->name === 'clean'
        );
    }

    /** @param array<string,string> $sources */
    private function valueBindingOracle(array $sources): bool
    {
        $identity = $this->classNode($this->parse($sources[self::IDENTITY]));
        $row = $this->classNode($this->parse($sources[self::ROW]));
        $catalog = $this->classNode($this->parse($sources[self::CATALOG]));
        $projection = $this->classNode($this->parse($sources[self::PROJECTION]));
        $writer = $this->classNode($this->parse($sources[self::WRITER]));
        if ($identity === null || $row === null || $catalog === null || $projection === null || $writer === null) {
            return false;
        }
        $identityMethods = $this->methodNodes($identity);
        $recordId = $identityMethods['recordId'] ?? null;
        $stateVersion = $identityMethods['stateVersion'] ?? null;
        $retryAuditId = $identityMethods['retryAuditId'] ?? null;
        $discriminator = $identityMethods['discriminator'] ?? null;
        if (
            !$recordId instanceof ClassMethod
            || !$stateVersion instanceof ClassMethod
            || !$retryAuditId instanceof ClassMethod
            || !$discriminator instanceof ClassMethod
            || $this->stringValues($this->methodNodesAsNodes($recordId)) !== [
                '/\\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/D',
            ]
            || $this->stringValues($this->methodNodesAsNodes($stateVersion)) !== [
                '/\\A[1-9][0-9]{0,18}\\z/D',
                '9223372036854775807',
            ]
            || $this->stringValues($this->methodNodesAsNodes($retryAuditId)) !== [
                '/\\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/D',
            ]
            || !$this->returnsVariable($recordId, 'recordId')
            || !$this->returnsVariable($stateVersion, 'stateVersion')
            || !$this->returnsVariable($retryAuditId, 'retryAuditId')
        ) {
            return false;
        }
        if (
            $this->staticCallDescriptors(
                $recordId,
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'assertGrammarString',
            ) !== [
                'static:BlackOps\\Core\\Audit\\AuditValidationException::assertGrammarString($recordId,str:/\\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/D,int:36)',
            ]
            || $this->staticCallDescriptors(
                $retryAuditId,
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'assertGrammarString',
            ) !== [
                'static:BlackOps\\Core\\Audit\\AuditValidationException::assertGrammarString($retryAuditId,str:/\\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/D,int:36)',
            ]
        ) {
            return false;
        }
        $stateCalls = $this->callNames($this->methodCalls($stateVersion));
        $stateFunctions = $this->functionNames($this->methodNodesAsNodes($stateVersion));
        if ($stateCalls !== ['assertGrammarString', 'fresh'] || $stateFunctions !== ['strlen', 'strcmp']) {
            return false;
        }
        $lengthChecks = new NodeFinder()->find(
            $stateVersion->stmts ?? [],
            static fn(Node $node): bool => $node instanceof Node\Expr\BinaryOp\Identical,
        );
        $lengthCheck = null;
        foreach ($lengthChecks as $check) {
            if (
                !$check instanceof Node\Expr\BinaryOp\Identical
                || !$check->left instanceof FuncCall
                || !$check->left->name instanceof Name
                || strtolower($check->left->name->toString()) !== 'strlen'
                || count($check->left->args) !== 1
                || !$check->left->args[0]->value instanceof Node\Expr\Variable
                || $check->left->args[0]->value->name !== 'stateVersion'
                || !$check->right instanceof Node\Scalar\LNumber
                || $check->right->value !== 19
            ) {
                continue;
            }
            $lengthCheck = $check;
        }
        $numericChecks = new NodeFinder()->find(
            $stateVersion->stmts ?? [],
            static fn(Node $node): bool => $node instanceof Node\Expr\BinaryOp\Greater,
        );
        $numericCheck = null;
        foreach ($numericChecks as $check) {
            if (
                !$check instanceof Node\Expr\BinaryOp\Greater
                || !$check->left instanceof FuncCall
                || !$check->left->name instanceof Name
                || strtolower($check->left->name->toString()) !== 'strcmp'
                || count($check->left->args) !== 2
                || !$check->left->args[0]->value instanceof Node\Expr\Variable
                || $check->left->args[0]->value->name !== 'stateVersion'
                || !$check->left->args[1]->value instanceof String_
                || $check->left->args[1]->value->value !== '9223372036854775807'
                || !$check->right instanceof Node\Scalar\LNumber
                || $check->right->value !== 0
            ) {
                continue;
            }
            $numericCheck = $check;
        }
        if (
            $lengthCheck === null
            || $numericCheck === null
            || new NodeFinder()->findInstanceOf($stateVersion->stmts ?? [], Node\Expr\Cast::class) !== []
        ) {
            return false;
        }
        $boundIf = null;
        foreach (new NodeFinder()->findInstanceOf($stateVersion->stmts ?? [], Node\Stmt\If_::class) as $if) {
            if (
                !$if instanceof Node\Stmt\If_
                || !$if->cond instanceof Node\Expr\BinaryOp\BooleanAnd
                || $if->cond->left !== $lengthCheck
                || $if->cond->right !== $numericCheck
                || count($if->stmts) !== 1
                || !$if->stmts[0] instanceof Node\Stmt\Expression
                || !$if->stmts[0]->expr instanceof Node\Expr\Throw_
                || !$if->stmts[0]->expr->expr instanceof New_
                || !$if->stmts[0]->expr->expr->class instanceof Name
                || $this->shortName(
                    $this->resolvedName($if->stmts[0]->expr->expr->class) ?? $if->stmts[0]->expr->expr->class->toString(),
                ) !== 'LogicException'
            ) {
                continue;
            }
            $boundIf = $if;
        }
        if (!$boundIf instanceof Node\Stmt\If_) {
            return false;
        }
        $discriminatorCalls = $this->staticCallDescriptors(
            $discriminator,
            'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity',
        );
        if (
            !in_array(
                'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::stateVersion($value)',
                $discriminatorCalls,
                true,
            )
            || !in_array(
                'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::retryAuditId($value)',
                $discriminatorCalls,
                true,
            )
        ) {
            return false;
        }
        if ($this->runtimeConstants($this->methodNodesAsNodes($discriminator)) !== ['Cli', 'Relay']) {
            return false;
        }
        $matchExpressions = new NodeFinder()->findInstanceOf($discriminator->stmts ?? [], Node\Expr\Match_::class);
        if (count($matchExpressions) !== 1 || !$matchExpressions[0] instanceof Node\Expr\Match_) {
            return false;
        }
        $branches = [];
        $defaultBranch = null;
        foreach ($matchExpressions[0]->arms as $arm) {
            if ($arm->conds === null) {
                if ($arm->body instanceof Node\Expr\Throw_ && $arm->body->expr instanceof StaticCall) {
                    $defaultBranch = $this->callDescriptor($arm->body->expr);
                }
                continue;
            }
            if (!$arm->body instanceof StaticCall) {
                continue;
            }
            if (count($arm->conds) !== 1) {
                continue;
            }
            $branches[$this->exprDescriptor($arm->conds[0])] = $this->callDescriptor($arm->body);
        }
        $relayRuntime = 'BlackOps\\Core\\Audit\\AuditSourceRuntime::Relay';
        $cliRuntime = 'BlackOps\\Core\\Audit\\AuditSourceRuntime::Cli';
        if (
            !in_array(
                $branches[$relayRuntime] ?? '',
                [
                    'static:self::stateVersion($value)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::stateVersion($value)',
                ],
                true,
            )
            || !in_array(
                $branches[$cliRuntime] ?? '',
                [
                    'static:self::retryAuditId($value)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::retryAuditId($value)',
                ],
                true,
            )
            || $defaultBranch
                !== 'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditFailure::fresh(BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure::TransactionInvalid)'
        ) {
            return false;
        }

        $rowRead = $this->methodNodes($row)['read'] ?? null;
        if (!$rowRead instanceof ClassMethod) {
            return false;
        }
        $rowReturns = $this->returnArrayMaps($rowRead);
        if (
            count($rowReturns) !== 1
            || $rowReturns[0]['record_id']
                !== 'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::recordId($recordId)'
            || $rowReturns[0]['state_version']
                !== 'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditIdentity::stateVersion($stateVersion)'
            || !in_array(
                $rowReturns[0]['tenant'],
                [
                    'static:self::tenant($tenantType,$tenantId)',
                    'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditRow::tenant($tenantType,$tenantId)',
                ],
                true,
            )
        ) {
            return false;
        }

        $catalogConstants = $this->classStringConstants($catalog);
        if (
            ($catalogConstants['CATALOG_HASH'] ?? null)
                !== '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b'
            || ($catalogConstants['CATALOG_ID'] ?? null) !== 'blackops.framework.operations'
            || ($catalogConstants['RESOURCE_TYPE'] ?? null) !== 'outbox_record'
        ) {
            return false;
        }
        $matches = $this->methodNodes($catalog)['matches'] ?? null;
        if (!$matches instanceof ClassMethod) {
            return false;
        }
        $catalogMaps = $this->identicalArrayMaps($matches);
        if (count($catalogMaps) !== 2) {
            return false;
        }
        if (
            $catalogMaps[0][0] !== [
                'type' => '$definition->type',
                'version' => '$definition->version',
                'evidenceVersion' => '$definition->evidenceVersion',
                'category' => '$definition->category',
                'actionTypes' => '$definition->actionTypes',
                'semanticTuples' => 'count($definition->semanticTuples)',
                'evidenceFields' => '$definition->evidenceFields',
                'maxEvidenceBytes' => '$definition->maxEvidenceBytes',
                'delivery' => '$definition->delivery',
            ]
            || $catalogMaps[0][1] !== [
                'type' => '$eventType',
                'version' => 'int:1',
                'evidenceVersion' => 'int:1',
                'category' => 'BlackOps\\Core\\Audit\\AuditEventCategory::Outbox',
                'actionTypes' => 'array',
                'semanticTuples' => 'int:1',
                'evidenceFields' => 'array',
                'maxEvidenceBytes' => 'int:13',
                'delivery' => 'BlackOps\\Core\\Audit\\AuditDelivery::Durable',
            ]
            || $catalogMaps[1][0] !== [
                'actionType' => '$tuple->actionType',
                'phase' => '$tuple->phase',
                'resourceType' => '$tuple->resourceType',
                'outcomeStatus' => '$tuple->outcomeStatus',
                'outcomeCode' => '$tuple->outcomeCode',
                'policyDecision' => '$tuple->policyDecision',
                'policyReasonCode' => '$tuple->policyReasonCode',
            ]
            || $catalogMaps[1][1] !== [
                'actionType' => '$eventType',
                'phase' => '$expected[phase]',
                'resourceType' => 'self::RESOURCE_TYPE',
                'outcomeStatus' => '$expected[status]',
                'outcomeCode' => '$expected[outcome]',
                'policyDecision' => 'BlackOps\\Core\\Audit\\AuditPolicyDecision::NotApplicable',
                'policyReasonCode' => '$expected[outcome]',
            ]
        ) {
            return false;
        }
        $catalogValueArrays = [];
        foreach (new NodeFinder()->findInstanceOf($matches->stmts ?? [], Node\Expr\Array_::class) as $array) {
            if (!$array instanceof Node\Expr\Array_) {
                continue;
            }
            foreach ($array->items as $item) {
                if (
                    $item === null
                    || !$item->key instanceof String_
                    || !in_array($item->key->value, ['actionTypes', 'evidenceFields'], true)
                    || !$item->value instanceof Node\Expr\Array_
                ) {
                    continue;
                }
                $catalogValueArrays[$item->key->value] = $item->value;
            }
        }
        $actionTypes = $catalogValueArrays['actionTypes'] ?? null;
        $evidenceFields = $catalogValueArrays['evidenceFields'] ?? null;
        if (
            !$actionTypes instanceof Node\Expr\Array_
            || count($actionTypes->items) !== 1
            || $actionTypes->items[0] === null
            || $actionTypes->items[0]->key !== null
            || !$actionTypes->items[0]->value instanceof Node\Expr\Variable
            || $actionTypes->items[0]->value->name !== 'eventType'
            || !$evidenceFields instanceof Node\Expr\Array_
            || $evidenceFields->items !== []
        ) {
            return false;
        }

        $occurredAt = $this->methodNodes($projection)['occurredAt'] ?? null;
        $opaqueId = $this->methodNodes($projection)['opaqueId'] ?? null;
        $capture = $this->methodNodes($projection)['capture'] ?? null;
        $draft = $this->methodNodes($projection)['draft'] ?? null;
        if (
            !$occurredAt instanceof ClassMethod
            || !$opaqueId instanceof ClassMethod
            || !$capture instanceof ClassMethod
            || !$draft instanceof ClassMethod
            || $this->stringValues($this->methodNodesAsNodes($occurredAt)) !== [
                'UTC',
                'Y-m-d\\TH:i:s.u\\Z',
                '/\\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\.[0-9]{6}Z\\z/D',
            ]
            || !$this->returnsVariable($occurredAt, 'value')
            || !$this->returnsVariable($opaqueId, 'value')
            || $this->callNames($this->methodCalls($opaqueId)) !== ['derive', 'assertSafeString', 'remap', 'fresh']
            || $this->staticCallDescriptors(
                $capture,
                'BlackOps\\Core\\Audit\\AuditRecordCorrelation',
                'fromArray',
            ) !== [
                'static:BlackOps\\Core\\Audit\\AuditRecordCorrelation::fromArray($correlation->toArray())',
            ]
            || $this->stringValues($this->methodNodesAsNodes($draft)) !== [
                'runtime',
                'buildId',
                'actionId',
                'resourceId',
                'occurredAt',
                'tenant',
                'runtime',
                'buildId',
                'actionId',
                'resourceId',
                'occurredAt',
                'tenant',
            ]
            || $this->newTargets($this->methodNodesAsNodes($draft), [
                'AuditRecordAction',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
            ]) !== [
                'AuditRecordAction',
                'AuditRecordDraft',
                'AuditRecordEvent',
                'AuditRecordEvidence',
                'AuditRecordOutcome',
                'AuditRecordPolicy',
                'AuditRecordResource',
                'AuditRecordSource',
            ]
        ) {
            return false;
        }
        $opaqueValueAssignments = [];
        foreach (new NodeFinder()->findInstanceOf($opaqueId->stmts ?? [], Node\Expr\Assign::class) as $assignment) {
            if (
                $assignment instanceof Node\Expr\Assign
                && $assignment->var instanceof Node\Expr\Variable
                && $assignment->var->name === 'value'
            ) {
                $opaqueValueAssignments[] = $assignment;
            }
        }
        if (
            count($opaqueValueAssignments) !== 1
            || !$opaqueValueAssignments[0]->expr instanceof Node\Expr\Cast\String_
            || !$opaqueValueAssignments[0]->expr->expr instanceof MethodCall
            || !$opaqueValueAssignments[0]->expr->expr->name instanceof Identifier
            || $opaqueValueAssignments[0]->expr->expr->name->toString() !== 'derive'
            || $this->exprDescriptor($opaqueValueAssignments[0]->expr->expr->var) !== '$opaqueIds'
        ) {
            return false;
        }

        $write = $this->methodNodes($writer)['write'] ?? null;
        if (!$write instanceof ClassMethod) {
            return false;
        }
        if (
            $this->arrayValueBindings($write, 'actionId') !== ['$actionId']
            || $this->arrayValueBindings($write, 'resourceId') !== ['$resourceId']
            || $this->arrayValueBindings($write, 'occurredAt') !== ['$occurredAt']
            || $this->arrayValueBindings($write, 'tenant') !== ['$tenantReference']
            || $this->newDescriptors($write, 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest') !== [
                'new:BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest($draft,concat(str:generic-outbox:,$actionId))',
            ]
        ) {
            return false;
        }
        foreach ([
            'actionId' => 'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection::opaqueId($values[opaqueIds],BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose::Action,concat(concat(concat(concat(concat(str:generic-outbox:,$eventType),str::),$recordId),str::),$discriminator))',
            'resourceId' => 'static:BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditProjection::opaqueId($values[opaqueIds],BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose::Resource,concat(str:generic-outbox:record:,$recordId))',
        ] as $variable => $expectedAssignment) {
            $assignments = [];
            foreach (new NodeFinder()->findInstanceOf($write->stmts ?? [], Node\Expr\Assign::class) as $assignment) {
                if (
                    $assignment instanceof Node\Expr\Assign
                    && $assignment->var instanceof Node\Expr\Variable
                    && $assignment->var->name === $variable
                ) {
                    $assignments[] = $assignment;
                }
            }
            if (
                count($assignments) !== 1
                || !$assignments[0]->expr instanceof StaticCall
                || $this->callDescriptor($assignments[0]->expr) !== $expectedAssignment
            ) {
                return false;
            }
        }

        foreach ([self::IDENTITY, self::CATALOG, self::PROJECTION] as $path) {
            $nodes = $this->parse($sources[$path]);
            if (
                in_array('intval', $this->functionNames($nodes), true)
                || in_array('floatval', $this->functionNames($nodes), true)
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param list<Node> $nodes @return list<Class_> */
    private function classNodes(array $nodes): array
    {
        return array_values(array_filter(
            new NodeFinder()->findInstanceOf($nodes, Class_::class),
            static fn(Node $node): bool => $node instanceof Class_,
        ));
    }

    private function astTypeName(?Node $type): string
    {
        if ($type === null) {
            return '';
        }
        if ($type instanceof Node\NullableType) {
            return '?' . $this->astTypeName($type->type);
        }
        if ($type instanceof Name) {
            return $this->resolvedName($type) ?? $type->toString();
        }
        if ($type instanceof Identifier) {
            return $type->toString();
        }

        return (string) $type;
    }

    /** @return array{0:string,1:string,2:bool,3:int} */
    private function astParameterContract(Node\Param $parameter): array
    {
        $type = $this->astTypeName($parameter->type);

        return [
            $parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name)
                ? $parameter->var->name
                : '',
            $type,
            str_starts_with($type, '?') || $type === 'mixed',
            $this->parameterSensitivity($parameter),
        ];
    }

    private function parameterSensitivity(Node\Param $parameter): int
    {
        $count = 0;
        foreach ($parameter->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (
                    $this->shortName($this->resolvedName($attribute->name) ?? $attribute->name->toString())
                    === 'SensitiveParameter'
                ) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /** @return list<string> */
    private function phaseHookCalls(array $nodes): array
    {
        $names = [];
        foreach ($nodes as $node) {
            if (
                ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
                && $node->name instanceof Identifier
                && $this->isThisProperty($node->var, 'phaseHook')
            ) {
                $names[] = $node->name->toString();
            }
        }

        return $names;
    }

    private function isThisProperty(?Node $node, string $property): bool
    {
        return (
            $node instanceof Node\Expr\PropertyFetch
            && $node->var instanceof Node\Expr\Variable
            && $node->var->name === 'this'
            && $node->name instanceof Identifier
            && $node->name->toString() === $property
        );
    }

    private function staticTarget(StaticCall $call): ?string
    {
        if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
            return null;
        }

        return $this->resolvedName($call->class);
    }

    private function callDescriptor(Node $call): string
    {
        if ($call instanceof StaticCall) {
            $target = $this->staticTarget($call);
            if ($target === null) {
                return 'static:?';
            }

            return (
                'static:'
                . $target
                . '::'
                . $call->name->toString()
                . '('
                . implode(',', array_map(fn(Node\Arg $arg): string => $this->exprDescriptor($arg->value), $call->args))
                . ')'
            );
        }
        if ($call instanceof MethodCall || $call instanceof NullsafeMethodCall) {
            return (
                ($call instanceof NullsafeMethodCall ? 'nullsafe:' : 'instance:')
                . $this->exprDescriptor($call->var)
                . '->'
                . ($call->name instanceof Identifier ? $call->name->toString() : '?')
                . '('
                . implode(',', array_map(fn(Node\Arg $arg): string => $this->exprDescriptor($arg->value), $call->args))
                . ')'
            );
        }

        return 'unknown:' . $call::class;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function callDescriptors(array $nodes): array
    {
        return array_map(fn(Node $node): string => $this->callDescriptor($node), $nodes);
    }

    /** @return list<string> */
    private function directTryCallDescriptors(ClassMethod $method): array
    {
        $try = null;
        foreach ($method->stmts ?? [] as $statement) {
            if ($statement instanceof Node\Stmt\TryCatch) {
                $try = $statement;
                break;
            }
        }
        if (!$try instanceof Node\Stmt\TryCatch) {
            return [];
        }
        $descriptors = [];
        foreach ($try->stmts as $statement) {
            $call = null;
            if ($statement instanceof Node\Stmt\Expression) {
                $call = $statement->expr;
                if ($call instanceof Node\Expr\Assign) {
                    $call = $call->expr;
                }
            }
            if ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall) {
                $descriptors[] = $this->callDescriptor($call);
            }
        }

        return $descriptors;
    }

    private function findCallNode(array $calls, string $name): ?Node
    {
        foreach ($calls as $call) {
            if (
                ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall)
                && $call->name instanceof Identifier
                && $call->name->toString() === $name
            ) {
                return $call;
            }
        }

        return null;
    }

    private function exprDescriptor(?Node $expr): string
    {
        if ($expr === null) {
            return 'null';
        }
        if ($expr instanceof Node\Expr\Variable) {
            return is_string($expr->name) ? '$' . $expr->name : '$?';
        }
        if ($expr instanceof Node\Expr\PropertyFetch) {
            return (
                $this->exprDescriptor($expr->var)
                . '->'
                . ($expr->name instanceof Identifier ? $expr->name->toString() : '?')
            );
        }
        if ($expr instanceof Node\Expr\ArrayDimFetch) {
            return $this->exprDescriptor($expr->var) . '[' . $this->arrayKeyDescriptor($expr->dim) . ']';
        }
        if ($expr instanceof ClassConstFetch) {
            $target = $expr->class instanceof Name
                ? $this->resolvedName($expr->class) ?? $expr->class->toString()
                : '?';

            return $target . '::' . ($expr->name instanceof Identifier ? $expr->name->toString() : '?');
        }
        if ($expr instanceof String_) {
            return 'str:' . $expr->value;
        }
        if ($expr instanceof Node\Scalar\LNumber) {
            return 'int:' . $expr->value;
        }
        if ($expr instanceof Node\Scalar\DNumber) {
            return 'float:' . $expr->value;
        }
        if ($expr instanceof Node\Expr\Array_) {
            return 'array';
        }
        if ($expr instanceof Node\Expr\ConstFetch) {
            return strtolower($expr->name->toString());
        }
        if ($expr instanceof Node\Expr\FuncCall) {
            $name = $expr->name instanceof Name ? $expr->name->toString() : '?';

            return (
                $name
                . '('
                . implode(',', array_map(fn(Node\Arg $arg): string => $this->exprDescriptor($arg->value), $expr->args))
                . ')'
            );
        }
        if ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall || $expr instanceof StaticCall) {
            if ($expr instanceof StaticCall) {
                $target = $this->staticTarget($expr) ?? '?';

                return (
                    'static:'
                    . $target
                    . '::'
                    . ($expr->name instanceof Identifier ? $expr->name->toString() : '?')
                    . '('
                    . implode(',', array_map(
                        fn(Node\Arg $arg): string => $this->exprDescriptor($arg->value),
                        $expr->args,
                    ))
                    . ')'
                );
            }

            return (
                $this->exprDescriptor($expr->var)
                . '->'
                . ($expr->name instanceof Identifier ? $expr->name->toString() : '?')
                . '()'
            );
        }
        if ($expr instanceof New_) {
            $target = $expr->class instanceof Name
                ? $this->resolvedName($expr->class) ?? $expr->class->toString()
                : '?';

            return (
                'new:'
                . $target
                . '('
                . implode(',', array_map(fn(Node\Arg $arg): string => $this->exprDescriptor($arg->value), $expr->args))
                . ')'
            );
        }
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return 'concat(' . $this->exprDescriptor($expr->left) . ',' . $this->exprDescriptor($expr->right) . ')';
        }
        if ($expr instanceof Node\Expr\BooleanNot) {
            return 'not(' . $this->exprDescriptor($expr->expr) . ')';
        }
        if ($expr instanceof Node\Expr\UnaryMinus) {
            return 'minus(' . $this->exprDescriptor($expr->expr) . ')';
        }

        return $expr::class;
    }

    private function arrayKeyDescriptor(?Node $key): string
    {
        if ($key instanceof String_) {
            return $key->value;
        }
        if ($key instanceof Node\Scalar\LNumber) {
            return (string) $key->value;
        }

        return $this->exprDescriptor($key);
    }

    private function hasNoRowGuardBefore(ClassMethod $method, Node $phase, string $variable, ?Node $fetch): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Stmt\If_::class) as $if) {
            if (!$if instanceof Node\Stmt\If_ || $if->getEndLine() >= $phase->getStartLine()) {
                continue;
            }
            if (
                $if->cond instanceof Node\Expr\BooleanNot
                && $if->cond->expr instanceof Node\Expr\FuncCall
                && $if->cond->expr->name instanceof Name
                && strtolower($if->cond->expr->name->toString()) === 'is_array'
                && count($if->cond->expr->args) === 1
                && $if->cond->expr->args[0]->value instanceof Node\Expr\Variable
                && $if->cond->expr->args[0]->value->name === $variable
                && ($fetch === null || $if->getStartLine() > $fetch->getEndLine())
                && $if->stmts !== []
                && end($if->stmts) instanceof Node\Stmt\Expression
            ) {
                $last = end($if->stmts);
                if (
                    $last instanceof Node\Stmt\Expression
                    && $last->expr instanceof Node\Expr\Throw_
                    && $last->expr->expr instanceof New_
                    && $last->expr->expr->class instanceof Name
                    && $this->shortName(
                        $this->resolvedName($last->expr->expr->class) ?? $last->expr->expr->class->toString(),
                    ) === 'LogicException'
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    private function arrayValueBindings(ClassMethod $method, string $key): array
    {
        $values = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Expr\Array_::class) as $array) {
            if (!$array instanceof Node\Expr\Array_) {
                continue;
            }
            foreach ($array->items as $item) {
                if ($item === null || !$item->key instanceof String_ || $item->key->value !== $key) {
                    continue;
                }
                $values[] = $this->exprDescriptor($item->value);
            }
        }

        return $values;
    }

    /** @return list<array<string,string>> */
    private function returnArrayMaps(ClassMethod $method): array
    {
        $maps = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Stmt\Return_::class) as $return) {
            if ($return instanceof Node\Stmt\Return_ && $return->expr instanceof Node\Expr\Array_) {
                $maps[] = $this->arrayMap($return->expr);
            }
        }

        return $maps;
    }

    /** @return array<string,string> */
    private function arrayMap(Node\Expr\Array_ $array): array
    {
        $map = [];
        foreach ($array->items as $item) {
            if ($item !== null && $item->key instanceof String_) {
                $map[$item->key->value] = $this->exprDescriptor($item->value);
            }
        }

        return $map;
    }

    /** @return list<array{0:array<string,string>,1:array<string,string>}> */
    private function identicalArrayMaps(ClassMethod $method): array
    {
        $maps = [];
        foreach (new NodeFinder()->find(
            $method->stmts ?? [],
            static fn(Node $node): bool => (
                $node instanceof Node\Expr\BinaryOp\Identical
                || $node instanceof Node\Expr\BinaryOp\NotIdentical
            ),
        ) as $comparison) {
            if (
                !(
                    $comparison instanceof Node\Expr\BinaryOp\Identical
                    || $comparison instanceof Node\Expr\BinaryOp\NotIdentical
                )
                || !$comparison->left instanceof Node\Expr\Array_
                || !$comparison->right instanceof Node\Expr\Array_
            ) {
                continue;
            }
            $maps[] = [$this->arrayMap($comparison->left), $this->arrayMap($comparison->right)];
        }

        return $maps;
    }

    /** @return array<string,string> */
    private function classStringConstants(Class_ $class): array
    {
        $constants = [];
        foreach ($class->stmts ?? [] as $statement) {
            if (!$statement instanceof Node\Stmt\ClassConst) {
                continue;
            }
            foreach ($statement->consts as $constant) {
                if ($constant->value instanceof String_) {
                    $constants[$constant->name->toString()] = $constant->value->value;
                }
            }
        }

        return $constants;
    }

    private function returnsVariable(ClassMethod $method, string $name): bool
    {
        $returns = new NodeFinder()->findInstanceOf($method->stmts ?? [], Node\Stmt\Return_::class);
        if (count($returns) !== 1 || !$returns[0] instanceof Node\Stmt\Return_) {
            return false;
        }

        return $returns[0]->expr instanceof Node\Expr\Variable && $returns[0]->expr->name === $name;
    }

    /** @return list<string> */
    private function staticCallDescriptors(ClassMethod $method, string $target, ?string $name = null): array
    {
        $result = [];
        foreach ($this->methodCalls($method) as $call) {
            if (!$call instanceof StaticCall) {
                continue;
            }
            $actualTarget = $this->staticTarget($call);
            if (
                $actualTarget !== $target
                && !($actualTarget === 'self' && str_contains($target, 'OperationalOutbox'))
            ) {
                continue;
            }
            if ($name !== null && (!$call->name instanceof Identifier || $call->name->toString() !== $name)) {
                continue;
            }
            $descriptor = $this->callDescriptor($call);
            if ($actualTarget === 'self') {
                $descriptor = str_replace('static:self::', 'static:' . $target . '::', $descriptor);
            }
            $result[] = $descriptor;
        }

        return $result;
    }

    /** @return list<string> */
    private function newDescriptors(ClassMethod $method, string $target): array
    {
        $result = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], New_::class) as $new) {
            if (!$new instanceof New_ || !$new->class instanceof Name) {
                continue;
            }
            $resolved = $this->resolvedName($new->class) ?? $new->class->toString();
            if ($resolved === $target) {
                $result[] = $this->exprDescriptor($new);
            }
        }

        return $result;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function allNewTargets(array $nodes): array
    {
        $targets = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if (!$new instanceof New_ || !$new->class instanceof Name) {
                continue;
            }
            $targets[] = $this->shortName($this->resolvedName($new->class) ?? $new->class->toString());
        }
        sort($targets);

        return $targets;
    }

    private function o3Sources(): array
    {
        $sources = [];
        foreach (self::O3_SOURCES as $path) {
            $sources[$path] = $this->source($path);
        }

        return $sources;
    }

    /** @return array<string,array<string,array{0:string,1:bool,2:list<array{0:string,1:string,2:bool,3:int}>,3:string}>> */
    private function helperContracts(): array
    {
        $failure = 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure';
        $exception = 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException';
        $connection = 'Doctrine\\DBAL\\Connection';
        $tenant = 'BlackOps\\Core\\TenantRef';
        $reference = 'BlackOps\\Core\\Audit\\AuditTypedReference';
        $runtime = 'BlackOps\\Core\\Audit\\AuditSourceRuntime';
        $definition = 'BlackOps\\Core\\Audit\\AuditEventDefinition';
        $correlation = 'BlackOps\\Core\\Audit\\AuditRecordCorrelation';
        $actor = 'BlackOps\\Core\\Audit\\AuditRecordActor';

        return [
            self::HELPERS[self::FAILURE] => [
                '__construct' => ['private', false, [], ''],
                'fresh' => ['public', true, [['failure', $failure, false, 0]], $exception],
                'remap' => ['public', true, [['failure', $exception, false, 1]], $exception],
            ],
            self::HELPERS[self::HOOK_GUARD] => [
                '__construct' => ['private', false, [], ''],
                'writer' => [
                    'public',
                    true,
                    [['snapshot', '?SensitiveParameterValue', true, 1]],
                    'BlackOps\\Internal\\Audit\\OperationalOutbox\\GenericOutboxAuditWriter',
                ],
                'entry' => ['public', true, [['connection', $connection, false, 1]], 'void'],
                'root' => ['public', true, [['connection', $connection, false, 1]], 'void'],
            ],
            self::HELPERS[self::ROW] => [
                '__construct' => ['private', false, [], ''],
                'read' => ['public', true, [['row', 'array', false, 1]], 'array'],
                'tenant' => [
                    'private',
                    true,
                    [
                        ['type', 'mixed', true, 1],
                        ['id',   'mixed', true, 1],
                    ],
                    '?' . $tenant,
                ],
            ],
            self::HELPERS[self::SNAPSHOT] => [
                '__construct' => ['private', false, [], ''],
                'read' => ['public', true, [['snapshot', '?SensitiveParameterValue', true, 1]], 'array'],
            ],
            self::HELPERS[self::IDENTITY] => [
                '__construct' => ['private', false, [], ''],
                'recordId' => ['public', true, [['recordId', 'string', false, 1]], 'string'],
                'stateVersion' => ['public', true, [['stateVersion', 'string', false, 1]], 'string'],
                'retryAuditId' => ['public', true, [['retryAuditId', 'string', false, 1]], 'string'],
                'discriminator' => [
                    'public',
                    true,
                    [
                        ['runtime', $runtime, false, 0],
                        ['value',   'string', false, 1],
                    ],
                    'string',
                ],
                'tenantReference' => ['public', true, [['tenant', '?' . $tenant, true, 1]], '?' . $reference],
            ],
            self::HELPERS[self::CATALOG] => [
                '__construct' => ['private', false, [], ''],
                'definition' => [
                    'public',
                    true,
                    [
                        ['eventType', 'string', false, 1],
                        ['runtime',   $runtime, false, 0],
                    ],
                    $definition,
                ],
                'catalog' => ['private', true, [], 'BlackOps\\Core\\Audit\\AuditEventCatalog'],
                'matches' => [
                    'private',
                    true,
                    [
                        ['definition', $definition, false, 1],
                        ['expected',   'array',     false, 1],
                        ['eventType',  'string',    false, 1],
                    ],
                    'bool',
                ],
            ],
            self::HELPERS[self::PROJECTION] => [
                '__construct' => ['private', false, [], ''],
                'capture' => [
                    'public',
                    true,
                    [
                        ['actor',       'BlackOps\\Core\\ActorContext', false, 1],
                        ['correlation', $correlation,                   false, 1],
                        ['runtime',     $runtime,                       false, 0],
                        ['buildId',     'string',                       false, 1],
                    ],
                    'array',
                ],
                'occurredAt' => [
                    'public',
                    true,
                    [['clock', 'Psr\\Clock\\ClockInterface', false, 1]],
                    'string',
                ],
                'opaqueId' => [
                    'public',
                    true,
                    [
                        ['opaqueIds', 'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver', false, 1],
                        ['purpose',   'BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose',         false, 1],
                        ['context',   'string',                                              false, 1],
                    ],
                    'string',
                ],
                'draft' => [
                    'public',
                    true,
                    [
                        ['definition',  $definition,  false, 1],
                        ['actor',       $actor,       false, 1],
                        ['correlation', $correlation, false, 1],
                        ['source',      'array',      false, 1],
                        ['material',    'array',      false, 1],
                    ],
                    'BlackOps\\Core\\Audit\\AuditRecordDraft',
                ],
                'reference' => [
                    'private',
                    true,
                    [['reference', '?BlackOps\\Core\\ActorRef', true, 1]],
                    '?' . $reference,
                ],
            ],
        ];
    }

    /** @param array<string,array{0:string,1:bool,2:list<array{0:string,1:string,2:bool,3:int}>,3:string}> $contracts */
    private function assertHelperMethods(string $type, array $contracts): void
    {
        $reflection = new ReflectionClass($type);
        $declared = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $type) {
                $declared[$method->getName()] = $method;
            }
        }
        self::assertSame(array_keys($contracts), array_keys($declared), $type);
        foreach ($contracts as $name => [$visibility, $static, $parameters, $return]) {
            $method = $declared[$name];
            self::assertSame(
                $visibility,
                $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public'),
            );
            self::assertSame($static, $method->isStatic(), $type . '::' . $name);
            self::assertSame($return, $this->typeName($method->getReturnType()), $type . '::' . $name);
            $actual = [];
            foreach ($method->getParameters() as $parameter) {
                $actual[] = [
                    $parameter->getName(),
                    $this->typeName($parameter->getType()),
                    $parameter->allowsNull(),
                    count($parameter->getAttributes(\SensitiveParameter::class)),
                ];
            }
            self::assertSame($parameters, $actual, $type . '::' . $name);
        }
    }

    /** @return list<string> */
    private function declaredMethods(ReflectionClass $reflection): array
    {
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);

        return $methods;
    }

    /** @return list<string> */
    private function declaredPublicMethods(ReflectionClass $reflection): array
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

    private function typeName(?ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }
        if (!$type instanceof \ReflectionNamedType) {
            return (string) $type;
        }
        $name = $type->getName();

        return ($type->allowsNull() && $name !== 'mixed' ? '?' : '') . $name;
    }

    private function source(string $relative): string
    {
        $source = file_get_contents(__DIR__ . '/../../' . $relative);
        self::assertIsString($source);

        return $source;
    }

    /** @return list<Node> */
    private function parse(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        self::assertIsArray($nodes);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $resolved = $traverser->traverse($nodes);
        self::assertIsArray($resolved);

        return $resolved;
    }

    /** @param list<Node> $nodes */
    private function classNode(array $nodes): ?Class_
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
            if ($class instanceof Class_) {
                return $class;
            }
        }

        return null;
    }

    /** @return array<string,ClassMethod> */
    private function methodNodes(Class_ $class): array
    {
        $methods = [];
        foreach ($class->getMethods() as $method) {
            $methods[$method->name->toString()] = $method;
        }

        return $methods;
    }

    /** @return list<Node> */
    private function methodNodesAsNodes(ClassMethod $method): array
    {
        return $method->stmts ?? [];
    }

    /** @return list<Node> */
    private function propertyNodes(Class_ $class): array
    {
        return array_values(array_filter(
            $class->stmts ?? [],
            static fn(Node $node): bool => $node instanceof Node\Stmt\Property,
        ));
    }

    /** @return list<Node> */
    private function methodCalls(ClassMethod $method): array
    {
        return $this->methodCallsFromNodes($this->methodNodesAsNodes($method));
    }

    /** @param list<Node> $nodes @return list<Node> */
    private function methodCallsFromNodes(array $nodes): array
    {
        $calls = new NodeFinder()->find(
            $nodes,
            static fn(Node $node): bool => (
                $node instanceof MethodCall
                || $node instanceof NullsafeMethodCall
                || $node instanceof StaticCall
            ),
        );

        return array_values(array_filter($calls, static fn(Node $node): bool => $node instanceof Node));
    }

    /** @param list<Node> $nodes @return list<string> */
    private function callNames(array $nodes): array
    {
        $names = [];
        foreach ($nodes as $node) {
            if (
                ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
                && $node->name instanceof Identifier
            ) {
                $names[] = $node->name->toString();
            }
        }

        return $names;
    }

    /** @param list<Node> $nodes @param list<string> $names @return list<string> */
    private function methodNames(array $nodes, array $names): array
    {
        return array_values(array_filter(
            $this->callNames($this->methodCallsFromNodes($nodes)),
            static fn(string $name): bool => in_array($name, $names, true),
        ));
    }

    /** @param list<Node> $nodes @return list<string> */
    private function staticCallNames(array $nodes): array
    {
        $result = [];
        foreach ($this->methodCallsFromNodes($nodes) as $node) {
            if (!$node instanceof StaticCall || !$node->name instanceof Identifier || !$node->class instanceof Name) {
                continue;
            }
            $class = $this->resolvedName($node->class);
            if ($class !== null) {
                $result[] = $this->shortName($class) . '::' . $node->name->toString();
            }
        }
        sort($result);

        return $result;
    }

    /** @param list<Node> $nodes @param list<string>|null $only @return list<string> */
    private function newTargets(array $nodes, ?array $only = null): array
    {
        $targets = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if (!$new instanceof New_ || !$new->class instanceof Name) {
                continue;
            }
            $target = $this->shortName($this->resolvedName($new->class) ?? $new->class->toString());
            if ($only !== null && !in_array($target, $only, true)) {
                continue;
            }
            $targets[] = $target;
        }
        sort($targets);

        return $targets;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function stringValues(array $nodes): array
    {
        $values = [];
        $finder = new NodeFinder();
        foreach ($finder->find(
            $nodes,
            static fn(Node $node): bool => $node instanceof String_ || $node instanceof Encapsed,
        ) as $node) {
            if ($node instanceof String_) {
                $values[] = $node->value;
                continue;
            }
            $value = '';
            foreach ($node->parts as $part) {
                if ($part instanceof Node\Scalar\EncapsedStringPart) {
                    $value .= $part->value;
                }
            }
            $values[] = $value;
        }

        return $values;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function functionNames(array $nodes): array
    {
        $names = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
            if ($call instanceof FuncCall && $call->name instanceof Name) {
                $names[] = strtolower($call->name->toString());
            }
        }

        return $names;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function runtimeConstants(array $nodes): array
    {
        $values = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, ClassConstFetch::class) as $fetch) {
            if (
                !$fetch instanceof ClassConstFetch
                || !$fetch->class instanceof Name
                || !$fetch->name instanceof Identifier
            ) {
                continue;
            }
            if (
                $this->shortName($this->resolvedName($fetch->class) ?? $fetch->class->toString())
                !== 'AuditSourceRuntime'
            ) {
                continue;
            }
            $values[] = $fetch->name->toString();
        }
        sort($values);

        return $values;
    }

    private function resolvedName(Name $name): ?string
    {
        $resolved = $name->getAttribute('resolvedName');

        return ltrim(($resolved instanceof Name ? $resolved : $name)->toString(), '\\');
    }

    private function shortName(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    private function replaceFirst(string $source, string $search, string $replace): string
    {
        $position = strpos($source, $search);
        self::assertNotFalse($position, $search);

        return substr_replace($source, $replace, $position, strlen($search));
    }

    private function insertIntoMethodBody(string $source, string $method, string $text): string
    {
        $function = strpos($source, 'function ' . $method . '(');
        self::assertNotFalse($function, $method);
        $position = strpos($source, '{', $function);
        self::assertNotFalse($position, $method);

        return substr_replace($source, "\n" . $text, $position + 1, 0);
    }
}
