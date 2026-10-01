<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditAttempt;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditCoordinator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluation;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEventFactory;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyBinding;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyRegistry;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_ as StringNode;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * Checks the unactivated authorization audit graph at its Internal boundary.
 *
 * The source oracle parses the four candidate files and resolves imports before
 * checking the small constructor, result-factory, and delegate graph. The
 * production scan keeps the graph uncalled until an explicitly reviewed
 * composition adds an activation edge.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuthorizationAuditArchitectureTest extends TestCase
{
    public function testExactDeclarationsPropertiesAndSensitiveSurfaces(): void
    {
        $this->assertDeclaration(
            new ReflectionClass(AuthorizationAuditPolicyBinding::class),
            true,
            [
                '__construct' => 'public',
                '__debuginfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
            ],
        );
        $this->assertProperties(
            new ReflectionClass(AuthorizationAuditPolicyBinding::class),
            [
                'operationTypeId' => ['public', true, 'string'],
                'operationClass' => ['public', true, 'string'],
                'policyClass' => ['public', true, 'string'],
                'policyId' => ['public', true, 'string'],
                'policyVersion' => ['public', true, 'int'],
                'delivery' => ['public', true, 'AuditDelivery'],
            ],
        );
        $binding = new ReflectionClass(AuthorizationAuditPolicyBinding::class);
        $this->assertMethodContract(
            $binding->getMethod('__construct'),
            null,
            [
                ['metadata',      'OperationMetadata', true,  false],
                ['policyId',      'string',            true,  false],
                ['policyVersion', 'int',               true,  false],
                ['delivery',      'AuditDelivery',     false, false],
            ],
        );
        $this->assertHiddenMethods($binding);

        $this->assertDeclaration(
            new ReflectionClass(AuthorizationAuditPolicyRegistry::class),
            true,
            [
                '__construct' => 'public',
                '__debuginfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'assertbinding' => 'private',
                'resolve' => 'public',
            ],
        );
        $registry = new ReflectionClass(AuthorizationAuditPolicyRegistry::class);
        $this->assertProperties($registry, ['bindings' => ['private', true, 'array']]);
        $this->assertMethodContract(
            $registry->getMethod('__construct'),
            null,
            [
                ['bindings', 'array', true, false],
            ],
        );
        $this->assertMethodContract(
            $registry->getMethod('resolve'),
            'AuthorizationAuditPolicyBinding',
            [['metadata', 'OperationMetadata', true, false]],
        );
        $this->assertMethodContract(
            $registry->getMethod('assertBinding'),
            'void',
            [['binding', 'AuthorizationAuditPolicyBinding', true, false]],
        );
        $this->assertHiddenMethods($registry);

        $this->assertDeclaration(
            new ReflectionClass(AuthorizationAuditEvaluation::class),
            true,
            [
                '__construct' => 'private',
                '__debuginfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'fromdecision' => 'public',
                'haspolicy' => 'public',
                'isallowed' => 'public',
                'isdenied' => 'public',
                'nopolicy' => 'public',
                'policy' => 'public',
                'rejection' => 'public',
            ],
        );
        $evaluation = new ReflectionClass(AuthorizationAuditEvaluation::class);
        $this->assertProperties($evaluation, [
            'state' => ['private', true, 'int'],
            'rejection' => ['private', true, '?RejectionReason'],
            'policy' => ['private', true, '?AuditRecordPolicy'],
        ]);
        $this->assertMethodContract(
            $evaluation->getMethod('__construct'),
            null,
            [
                ['state',     'int',                false, false],
                ['rejection', '?RejectionReason',   true,  false],
                ['policy',    '?AuditRecordPolicy', false, false],
            ],
        );
        $this->assertMethodContract($evaluation->getMethod('noPolicy'), 'self', []);
        $this->assertMethodContract(
            $evaluation->getMethod('fromDecision'),
            'self',
            [
                ['binding',   'AuthorizationAuditPolicyBinding', true, false],
                ['rejection', '?RejectionReason',                true, false],
            ],
        );
        self::assertTrue($evaluation->getMethod('noPolicy')->isStatic());
        self::assertTrue($evaluation->getMethod('fromDecision')->isStatic());
        foreach (['hasPolicy', 'isAllowed', 'isDenied'] as $method) {
            $this->assertMethodContract($evaluation->getMethod($method), 'bool', []);
        }
        $this->assertMethodContract($evaluation->getMethod('rejection'), '?RejectionReason', []);
        $this->assertMethodContract($evaluation->getMethod('policy'), '?AuditRecordPolicy', []);
        $this->assertHiddenMethods($evaluation);

        $this->assertDeclaration(
            new ReflectionClass(AuthorizationAuditEvaluator::class),
            false,
            [
                '__construct' => 'public',
                '__debuginfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'evaluate' => 'public',
                'value' => 'private',
            ],
        );
        $evaluator = new ReflectionClass(AuthorizationAuditEvaluator::class);
        $this->assertProperties($evaluator, ['snapshot' => ['private', false, 'SensitiveParameterValue']]);
        $this->assertMethodContract(
            $evaluator->getMethod('__construct'),
            null,
            [
                ['evaluator', 'AuthorizationEvaluator',           true, false],
                ['policies',  'AuthorizationAuditPolicyRegistry', true, false],
            ],
        );
        $this->assertMethodContract(
            $evaluator->getMethod('evaluate'),
            'AuthorizationAuditEvaluation',
            [
                ['metadata', 'OperationMetadata', true, false],
                ['envelope', 'OperationEnvelope', true, false],
            ],
        );
        $this->assertMethodContract(
            $evaluator->getMethod('value'),
            'mixed',
            [
                ['key', 'string', true, false],
            ],
        );
        $this->assertHiddenMethods($evaluator);

        $this->assertDeclaration(
            new ReflectionClass(AuthorizationAuditEventFactory::class),
            false,
            [
                '__construct' => 'public',
                '__debuginfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'actionuuid' => 'private',
                'actor' => 'private',
                'buildid' => 'private',
                'catalog' => 'private',
                'correlation' => 'private',
                'create' => 'public',
                'definition' => 'private',
                'draft' => 'private',
                'executionfallback' => 'private',
                'failure' => 'private',
                'noPolicy' => 'private',
                'opaqueid' => 'private',
                'operationid' => 'private',
                'policy' => 'private',
                'reference' => 'private',
                'remap' => 'private',
                'runtime' => 'private',
                'tenant' => 'private',
                'value' => 'private',
                'occurredat' => 'private',
            ],
        );
        $factory = new ReflectionClass(AuthorizationAuditEventFactory::class);
        $this->assertProperties($factory, ['snapshot' => ['private', false, 'SensitiveParameterValue']]);
        $this->assertMethodContract(
            $factory->getMethod('__construct'),
            null,
            [
                ['opaqueIds',         'HmacAuditOpaqueIdDeriver', true,  false],
                ['identifiers',       'Uuidv7Generator',          true,  false],
                ['clock',             'ClockInterface',           true,  false],
                ['runtime',           'AuditSourceRuntime',       false, false],
                ['buildId',           'string',                   true,  false],
                ['executionFallback', 'ActorRef',                 true,  false],
            ],
        );
        $this->assertMethodContract(
            $factory->getMethod('create'),
            '?AuditDeliveryRequest',
            [
                ['evaluation', 'AuthorizationAuditEvaluation', true, false],
                ['context',    'ExecutionContext',             true, false],
            ],
        );
        $this->assertHiddenMethods($factory);

        $this->assertDeclaration(
            new ReflectionClass(AuthorizationAuditCoordinator::class),
            false,
            [
                '__construct' => 'public',
                '__debuginfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'evaluate' => 'public',
                'failure' => 'private',
                'remap' => 'private',
                'value' => 'private',
            ],
        );
        $coordinator = new ReflectionClass(AuthorizationAuditCoordinator::class);
        $this->assertProperties($coordinator, ['snapshot' => ['private', false, 'SensitiveParameterValue']]);
        $this->assertMethodContract(
            $coordinator->getMethod('__construct'),
            null,
            [
                ['evaluator',  'AuthorizationAuditEvaluator',    true, false],
                ['factory',    'AuthorizationAuditEventFactory', true, false],
                ['selector',   'AuditDeliverySelector',          true, false],
                ['delivery',   'AuditDeliveryCoordinator',       true, false],
                ['connection', 'Connection',                     true, false],
            ],
        );
        $this->assertMethodContract(
            $coordinator->getMethod('evaluate'),
            'AuthorizationAuditAttempt',
            [
                ['metadata', 'OperationMetadata', true, false],
                ['envelope', 'OperationEnvelope', true, false],
            ],
        );
        $this->assertHiddenMethods($coordinator);

        $this->assertDeclaration(
            new ReflectionClass(AuthorizationAuditAttempt::class),
            false,
            [
                '__construct' => 'private',
                '__clone' => 'public',
                '__debuginfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'assertmutation' => 'public',
                'assertprojectionagreement' => 'private',
                'deliverinownedtransaction' => 'public',
                'deliverinmutationtransaction' => 'public',
                'deliverstandalone' => 'public',
                'consume' => 'private',
                'deliveryvalues' => 'private',
                'evaluation' => 'public',
                'failure' => 'private',
                'prepare' => 'public',
                'remap' => 'private',
                'value' => 'private',
            ],
            true,
        );
        $attempt = new ReflectionClass(AuthorizationAuditAttempt::class);
        $this->assertProperties($attempt, [
            'consumed' => ['private', false, 'bool'],
            'snapshot' => ['private', false, 'SensitiveParameterValue'],
        ]);
        $this->assertMethodContract(
            $attempt->getMethod('__construct'),
            null,
            [
                ['evaluation', 'AuthorizationAuditEvaluation', true, false],
                ['request',    '?AuditDeliveryRequest',        true, false],
                ['plan',       '?AuditDeliveryPlan',           true, false],
                ['delivery',   'AuditDeliveryCoordinator',     true, false],
                ['connection', 'Connection',                   true, false],
            ],
        );
        $this->assertMethodContract(
            $attempt->getMethod('prepare'),
            'self',
            [
                ['evaluation', 'AuthorizationAuditEvaluation', true, false],
                ['request',    '?AuditDeliveryRequest',        true, false],
                ['plan',       '?AuditDeliveryPlan',           true, false],
                ['delivery',   'AuditDeliveryCoordinator',     true, false],
                ['connection', 'Connection',                   true, false],
            ],
        );
        self::assertTrue($attempt->getMethod('prepare')->isStatic());
        self::assertTrue($attempt->getMethod('prepare')->isPublic());
        self::assertTrue($attempt->getMethod('assertProjectionAgreement')->isStatic());
        self::assertFalse($attempt->getMethod('assertProjectionAgreement')->isPublic());
        $this->assertMethodContract(
            $attempt->getMethod('assertProjectionAgreement'),
            'void',
            [
                ['evaluation', 'AuthorizationAuditEvaluation', true, false],
                ['request',    '?AuditDeliveryRequest',        true, false],
                ['plan',       '?AuditDeliveryPlan',           true, false],
            ],
        );
        $this->assertMethodContract($attempt->getMethod('__clone'), 'void', []);
        $this->assertMethodContract($attempt->getMethod('evaluation'), 'AuthorizationAuditEvaluation', []);
        $this->assertMethodContract(
            $attempt->getMethod('assertMutation'),
            'void',
            [
                ['metadata',     'OperationMetadata',                true, false],
                ['transactions', '?OperationTransactionCoordinator', true, false],
            ],
        );
        $this->assertMethodContract($attempt->getMethod('deliverStandalone'), 'void', []);
        $this->assertMethodContract($attempt->getMethod('deliverInMutationTransaction'), 'void', []);
        $this->assertMethodContract(
            $attempt->getMethod('deliverInOwnedTransaction'),
            'void',
            [['connection', 'Connection', true, false]],
        );
        $this->assertHiddenMethods($attempt);
    }

    public function testResolvedConstructorReferenceAndResultFactoryGraphIsClosed(): void
    {
        $sources = $this->architectureSources();
        $authorizationDirectory = realpath(__DIR__ . '/../../src/Internal/Audit/Authorization');
        self::assertNotFalse($authorizationDirectory);
        $actualFiles = $this->relativePhpFiles($authorizationDirectory);
        $prefix = 'src/Internal/Audit/Authorization/';
        $expectedFiles = array_map(static fn(string $path): string => substr(
            $path,
            strlen($prefix),
        ), array_keys($this->sourceDefinitions()));
        sort($actualFiles);
        sort($expectedFiles);
        self::assertSame($expectedFiles, $actualFiles);
        self::assertSame([], $this->sourceViolations($sources));
        self::assertSame([], $this->productionActivationViolations());
    }

    public function testNativeRuntimeEdgesAreBoundedAndRejectUnauthorizedTargetCalls(): void
    {
        $paths = [
            'src/Internal/Execution/InlineDispatcher.php',
            'src/Internal/Execution/DeferredAcceptanceOrchestrator.php',
            'src/Internal/Execution/DeferredWorkerRuntime.php',
        ];
        foreach ($paths as $path) {
            $source = file_get_contents(__DIR__ . '/../../' . $path);
            self::assertIsString($source, $path);
            self::assertSame([], $this->runtimeEdgeViolations($this->parse($source), substr($path, 4)), $path);
        }

        $inlinePath = $paths[0];
        $inline = file_get_contents(__DIR__ . '/../../' . $inlinePath);
        self::assertIsString($inline);
        $wrongMethod = $this->replaceOnce(
            $inline,
            '$this->authorizationAudit->evaluate($metadata, $receivedEnvelope)',
            '$this->authorizationAudit->prepare($metadata, $receivedEnvelope)',
        );
        self::assertNotEmpty(
            $this->runtimeEdgeViolations($this->parse($wrongMethod), 'Internal/Execution/InlineDispatcher.php'),
            'a runtime coordinator call outside evaluate must be rejected',
        );

        $workerPath = $paths[2];
        $worker = file_get_contents(__DIR__ . '/../../' . $workerPath);
        self::assertIsString($worker);
        $nullsafeWrongMethod = $this->replaceOnce(
            $worker,
            '$authorizationAttempt?->evaluation()->isDenied()',
            '$authorizationAttempt?->forbidden()->isDenied()',
        );
        self::assertNotEmpty(
            $this->runtimeEdgeViolations(
                $this->parse($nullsafeWrongMethod),
                'Internal/Execution/DeferredWorkerRuntime.php',
            ),
            'a nullsafe attempt call outside the declared methods must be rejected',
        );

        $wrongEvaluationResultMethod = $this->replaceOnce(
            $worker,
            '$authorizationAttempt->evaluation()->rejection()',
            '$authorizationAttempt->evaluation()->fromDecision()',
        );
        self::assertNotEmpty(
            $this->runtimeEdgeViolations(
                $this->parse($wrongEvaluationResultMethod),
                'Internal/Execution/DeferredWorkerRuntime.php',
            ),
            'an attempt evaluation result factory call must be rejected',
        );

        $dynamicEvaluationResultMethod = $this->replaceOnce(
            $worker,
            '$authorizationAttempt->evaluation()->rejection()',
            '$authorizationAttempt->evaluation()->{$method}()',
        );
        self::assertNotEmpty(
            $this->runtimeEdgeViolations(
                $this->parse($dynamicEvaluationResultMethod),
                'Internal/Execution/DeferredWorkerRuntime.php',
            ),
            'a dynamic attempt evaluation result method must be rejected',
        );

        $staticConstruction =
            $inline . "\nAuthorizationAuditAttempt::prepare(\$evaluation, null, null, \$delivery, \$connection);\n";
        self::assertNotEmpty(
            $this->runtimeEdgeViolations($this->parse($staticConstruction), 'Internal/Execution/InlineDispatcher.php'),
            'a runtime must not construct or invoke the attempt factory directly',
        );
    }

    public function testCanonicalComposerConstructorInventoryRejectsDependencyInjection(): void
    {
        self::assertSame([], $this->canonicalComposerViolations());

        $httpPath = 'Internal/Application/ApplicationHttpRuntimeComposer.php';
        $httpSource = file_get_contents(__DIR__ . '/../../src/' . $httpPath);
        self::assertIsString($httpSource);
        $namedInjection = $this->replaceOnce(
            $httpSource,
            "                authorization: \$operation->authorization,\n",
            "                authorizationAudit: \$container->get(\$someId),\n"
            . "                authorization: \$operation->authorization,\n",
        );
        self::assertNotEmpty($this->parse($namedInjection));
        self::assertContains('canonical-constructor-inventory:' . $httpPath, $this->canonicalComposerViolations([
            $httpPath => $namedInjection,
        ]));

        $workerPath = 'Internal/Application/ApplicationWorkerComposer.php';
        $workerSource = file_get_contents(__DIR__ . '/../../src/' . $workerPath);
        self::assertIsString($workerSource);
        $positionalInjection = $this->replaceOnce(
            $workerSource,
            "            \$signals,\n            connections:",
            "            \$signals,\n" . "            \$container->get(\$someId),\n" . '            connections:',
        );
        self::assertNotEmpty($this->parse($positionalInjection));
        self::assertContains('canonical-constructor-inventory:' . $workerPath, $this->canonicalComposerViolations([
            $workerPath => $positionalInjection,
        ]));

        $unpackedInjection = $this->replaceOnce(
            $workerSource,
            "            \$signals,\n            connections:",
            "            ...[\$signals, 'authorizationAudit' => \$container->get(\$someId)],\n"
            . '            connections:',
        );
        self::assertNotEmpty($this->parse($unpackedInjection));
        self::assertContains('canonical-unpacked-argument:' . $workerPath, $this->canonicalComposerViolations([
            $workerPath => $unpackedInjection,
        ]));
    }

    public function testAuthorizationInventoryCountsNestedPhpFiles(): void
    {
        $fixtureRoot = tempnam(sys_get_temp_dir(), 'blackops-authorization-inventory-');
        self::assertIsString($fixtureRoot);
        self::assertTrue(unlink($fixtureRoot));
        self::assertTrue(mkdir($fixtureRoot, 0700));
        $expectedFiles = [
            'AuthorizationAuditEvaluation.php',
            'AuthorizationAuditEvaluator.php',
            'AuthorizationAuditPolicyBinding.php',
            'AuthorizationAuditPolicyRegistry.php',
        ];

        try {
            foreach ($expectedFiles as $file) {
                self::assertNotFalse(file_put_contents($fixtureRoot . '/' . $file, "<?php\n"));
            }
            self::assertSame($expectedFiles, $this->relativePhpFiles($fixtureRoot));

            $nestedDirectory = $fixtureRoot . '/Nested';
            self::assertTrue(mkdir($nestedDirectory, 0700));
            self::assertNotFalse(file_put_contents($nestedDirectory . '/sibling.php', "<?php\n"));
            $expectedWithNested = [...$expectedFiles, 'Nested/sibling.php'];
            sort($expectedWithNested);
            self::assertSame($expectedWithNested, $this->relativePhpFiles($fixtureRoot));
        } finally {
            $this->removeTemporaryTree($fixtureRoot);
        }
    }

    public function testSyntaxValidAliasFqcnAndMethodCaseMutationsAreRejected(): void
    {
        $sources = $this->architectureSources();
        self::assertSame([], $this->sourceViolations($sources));
        $path = 'src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php';
        $source = $sources[$path];
        $registryPath = 'src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php';
        $registrySource = $sources[$registryPath];
        $factoryPath = 'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php';
        $factorySource = $sources[$factoryPath];
        $coordinatorPath = 'src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php';
        $coordinatorSource = $sources[$coordinatorPath];
        $attemptPath = 'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php';
        $attemptSource = $sources[$attemptPath];
        $extraDeclarationPath = 'src/Internal/Audit/Authorization/AuthorizationAuditPolicyBinding.php';
        $mutations = [
            'aliased forbidden payload type' => [
                'source' => $this->insertBeforeFinalBrace(
                    $this->replaceOnce(
                        $source,
                        "namespace BlackOps\\Internal\\Audit\\Authorization;\n",
                        "namespace BlackOps\\Internal\\Audit\\Authorization;\n\nuse BlackOps\\Core\\OperationValue as Payload;\n",
                    ),
                    <<<'PHP'
                            private function invalidPayload(Payload $payload): void {}
                        PHP,
                ),
                'violation' => 'forbidden-type:' . $path . ':operationvalue',
            ],
            'fully-qualified forbidden context type' => [
                'source' => $this->insertBeforeFinalBrace($source, <<<'PHP'
                        private function invalidContext(\BlackOps\Core\ExecutionContext $context): void {}
                    PHP),
                'violation' => 'forbidden-type:' . $path . ':executioncontext',
            ],
            'method case adds a second delegate' => [
                'source' => $this->replaceOnce($source, <<<'PHP'
                                $rejection = $evaluator->evaluate($metadata, $envelope);
                    PHP, "            \$rejection = \$evaluator->evaluate(\$metadata, \$envelope);\n"
                . "            \$evaluator->EVALUATE(\$metadata, \$envelope);\n"),
                'violation' => 'evaluator-single-delegate',
            ],
            'factory method case adds a second result' => [
                'source' => $this->replaceOnce($source, <<<'PHP'
                            return AuthorizationAuditEvaluation::fromDecision($binding, $rejection);
                    PHP, "        AuthorizationAuditEvaluation::FROMDECISION(\$binding, \$rejection);\n"
                . "        return AuthorizationAuditEvaluation::fromDecision(\$binding, \$rejection);\n"),
                'violation' => 'evaluator-decision-factory',
            ],
            'other registry constructor instance work' => [
                'path' => $registryPath,
                'source' => $this->replaceOnce(
                    $registrySource,
                    "                \$this->assertBinding(\$binding);\n",
                    "                \$this->assertBinding(\$binding);\n"
                    . "                \$this->resolve(\$binding);\n",
                ),
                'violation' => 'registry-constructor-work',
            ],
            'dynamic registry constructor instance work' => [
                'path' => $registryPath,
                'source' => $this->replaceOnce(
                    $registrySource,
                    "                \$this->assertBinding(\$binding);\n",
                    "                \$this->assertBinding(\$binding);\n"
                    . "                \$this->{\$method}(\$binding);\n",
                ),
                'violation' => 'registry-constructor-work',
            ],
            'other factory constructor instance work' => [
                'path' => $factoryPath,
                'source' => $this->replaceOnce(
                    $factorySource,
                    "        \$this->snapshot = new \\SensitiveParameterValue([\n",
                    "        \$executionFallback->toString();\n"
                    . "        \$this->snapshot = new \\SensitiveParameterValue([\n",
                ),
                'violation' => 'factory-constructor-work',
            ],
            'nullsafe evaluator instance work' => [
                'path' => $path,
                'source' => $this->replaceOnce(
                    $source,
                    "            if (get_class(\$envelope->definition()) !== \$metadata->definition) {\n",
                    "            \$envelope?->value();\n"
                    . "            if (get_class(\$envelope->definition()) !== \$metadata->definition) {\n",
                ),
                'violation' => 'nullsafe-method-call:' . $path,
            ],
            'nullsafe registry instance work' => [
                'path' => $registryPath,
                'source' => $this->replaceOnce(
                    $registrySource,
                    "                \$this->assertBinding(\$binding);\n",
                    "                \$this->assertBinding(\$binding);\n"
                    . "                \$this?->resolve(\$binding);\n",
                ),
                'violation' => 'nullsafe-method-call:' . $registryPath,
            ],
            'extra class-like declaration' => [
                'path' => $extraDeclarationPath,
                'source' => $sources[$extraDeclarationPath] . "\nfinal class UnexpectedAuthorizationAuditSibling {}\n",
                'violation' => 'declaration:' . $extraDeclarationPath,
            ],
            'fully-qualified forbidden delivery dependency' => [
                'path' => $factoryPath,
                'source' => $this->insertBeforeFinalBrace($factorySource, <<<'PHP'
                        private function invalidDelivery(\BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator $delivery): void {}
                    PHP),
                'violation' => 'forbidden-type:' . $factoryPath . ':auditdeliverycoordinator',
            ],
            'aliased forbidden storage dependency' => [
                'path' => $coordinatorPath,
                'source' => $this->insertBeforeFinalBrace(
                    $this->replaceOnce(
                        $coordinatorSource,
                        "namespace BlackOps\\Internal\\Audit\\Authorization;\n",
                        "namespace BlackOps\\Internal\\Audit\\Authorization;\n\nuse PDO as DatabaseHandle;\n",
                    ),
                    <<<'PHP'
                            private function invalidStorage(DatabaseHandle $storage): void {}
                        PHP,
                ),
                'violation' => 'forbidden-type:' . $coordinatorPath . ':pdo',
            ],
            'case-insensitive duplicate attempt factory' => [
                'path' => $coordinatorPath,
                'source' => $this->replaceOnce(
                    $coordinatorSource,
                    "        return AuthorizationAuditAttempt::prepare(\$evaluation, \$request, \$plan, \$delivery, \$connection);\n",
                    "        AuthorizationAuditAttempt::PREPARE(\$evaluation, \$request, \$plan, \$delivery, \$connection);\n"
                    . "        return AuthorizationAuditAttempt::prepare(\$evaluation, \$request, \$plan, \$delivery, \$connection);\n",
                ),
                'violation' => 'coordinator-attempt-factory',
            ],
            'nullsafe attempt delivery' => [
                'path' => $attemptPath,
                'source' => $this->replaceOnce(
                    $attemptSource,
                    "        \$request = \$values['request'];\n"
                    . "        if (!\$request instanceof AuditDeliveryRequest) {\n"
                    . "            throw self::failure(AuditDeliveryFailure::StorageUnavailable);\n"
                    . "        }\n"
                    . "        try {\n"
                    . "            if (!\$values['delivery']->usesConnection(\$values['connection'])) {\n",
                    "        \$request = \$values['request'];\n"
                    . "        if (!\$request instanceof AuditDeliveryRequest) {\n"
                    . "            throw self::failure(AuditDeliveryFailure::StorageUnavailable);\n"
                    . "        }\n"
                    . "        try {\n"
                    . "            if (!\$values['delivery']?->usesConnection(\$values['connection'])) {\n",
                ),
                'violation' => 'nullsafe-method-call:' . $attemptPath,
            ],
        ];
        foreach ($mutations as $name => $mutation) {
            $mutated = $mutation['source'];
            try {
                $this->parse($mutated);
            } catch (\Throwable $exception) {
                self::fail($name . ' must remain syntax-valid: ' . $exception->getMessage());
            }
            $mutatedSources = [...$sources, $mutation['path'] ?? $path => $mutated];
            self::assertContains($mutation['violation'], $this->sourceViolations($mutatedSources), $name);
        }
    }

    /** @param array<string,string> $sources @return list<string> */
    private function sourceViolations(array $sources): array
    {
        $definitions = $this->sourceDefinitions();
        $expectedPaths = array_keys($definitions);
        $actualPaths = array_keys($sources);
        sort($expectedPaths);
        sort($actualPaths);
        $violations = $expectedPaths === $actualPaths ? [] : ['source-path-inventory'];
        $nodes = [];
        foreach ($definitions as $path => $definition) {
            if (!isset($sources[$path])) {
                $violations[] = 'missing:' . $path;
                continue;
            }
            try {
                $nodes[$path] = $this->parse($sources[$path]);
            } catch (\Throwable) {
                $violations[] = 'syntax:' . $path;
                continue;
            }
            $class = $this->classNode($nodes[$path]);
            if (count(new NodeFinder()->findInstanceOf($nodes[$path], ClassLike::class)) !== 1) {
                $violations[] = 'declaration:' . $path;
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($nodes[$path], NullsafeMethodCall::class) as $call) {
                if (!$this->allowedNullsafeCall($path, $call)) {
                    $violations[] = 'nullsafe-method-call:' . $path;
                }
            }
            $namespaceName = $class?->namespacedName;
            $expectedName = 'BlackOps\\Internal\\Audit\\Authorization\\' . $definition['class'];
            if (
                $class === null
                || !$this->sameSymbol($this->terminalName($class->name), $definition['class'])
                || !$namespaceName instanceof Name
                || !$this->sameSymbol($this->resolvedName($namespaceName), $expectedName)
            ) {
                $violations[] = 'declaration:' . $path;
                continue;
            }
            if (
                !$class->isFinal()
                || $class->extends !== null
                || $class->implements !== []
                || $class->isReadonly() !== $definition['readonly']
            ) {
                $violations[] = 'closed-class:' . $path;
            }
            if ($this->hasPublicApiAttribute($class)) {
                $violations[] = 'public-api:' . $path;
            }
            if ($this->methodInventory($class) !== $this->normalizeInventory($definition['methods'])) {
                $violations[] = 'methods:' . $path;
            }
            $violations = [...$violations, ...$this->sensitiveParameterViolations($class, $path)];
            $references = $this->targetReferences($class);
            $expectedReferences = $definition['references'];
            sort($references);
            sort($expectedReferences);
            if ($references !== $expectedReferences) {
                $violations[] = 'references:' . $path;
            }
            $violations = [...$violations, ...$this->forbiddenSurfaceViolations($nodes[$path], $path)];
        }

        if (isset($nodes['src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php'])) {
            $violations = [
                ...$violations,
                ...$this->registryViolations(
                    $nodes['src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php'],
                ),
            ];
        }
        if (isset($nodes['src/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php'])) {
            $violations = [
                ...$violations,
                ...$this->evaluationViolations(
                    $nodes['src/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php'],
                ),
            ];
        }
        if (isset($nodes['src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php'])) {
            $violations = [
                ...$violations,
                ...$this->evaluatorViolations(
                    $nodes['src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php'],
                ),
            ];
        }
        if (isset($nodes['src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php'])) {
            $violations = [
                ...$violations,
                ...$this->factoryViolations(
                    $nodes['src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php'],
                ),
            ];
        }
        if (isset($nodes['src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php'])) {
            $violations = [
                ...$violations,
                ...$this->coordinatorViolations(
                    $nodes['src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php'],
                ),
            ];
        }
        if (isset($nodes['src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php'])) {
            $violations = [
                ...$violations,
                ...$this->attemptViolations($nodes['src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php']),
            ];
        }

        return array_values(array_unique($violations));
    }

    private function allowedNullsafeCall(string $path, NullsafeMethodCall $call): bool
    {
        if ($path !== 'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php') {
            return false;
        }
        if (!$call->name instanceof Identifier || !$call->var instanceof Variable) {
            return false;
        }

        return (
            $call->var->name === 'actorContext'
            && in_array(strtolower($call->name->toString()), ['authorization', 'execution'], true)
            || $call->var->name === 'causationId'
            && strtolower($call->name->toString()) === 'tostring'
        );
    }

    /** @return list<string> */
    private function registryViolations(array $nodes): array
    {
        $violations = [];
        $constructor = $this->methodNode($nodes, '__construct');
        $resolve = $this->methodNode($nodes, 'resolve');
        if ($constructor === null || $resolve === null) {
            return ['registry-methods'];
        }
        $constructorCalls = $this->methodCalls($constructor);
        $bindingValidationCalls = array_values(array_filter(
            $constructorCalls,
            fn(MethodCall $call): bool => (
                $this->receiver($call->var) === '$this'
                && $this->methodName($call) === 'assertbinding'
                && $this->argumentsMatch($call->args, [['variable', 'binding']])
            ),
        ));
        if (count($constructorCalls) !== 1 || count($bindingValidationCalls) !== 1) {
            $violations[] = 'registry-constructor-work';
        }
        if ($this->countCalls($resolve, '$this', 'bindings') !== 0) {
            $violations[] = 'registry-invalid-property-call';
        }
        if ($this->countPropertyFetches($resolve, '$this', 'bindings') < 1) {
            $violations[] = 'registry-does-not-resolve-bindings';
        }
        return $violations;
    }

    /** @return list<string> */
    private function evaluationViolations(array $nodes): array
    {
        $violations = [];
        $factory = $this->methodNode($nodes, 'fromDecision');
        $noPolicy = $this->methodNode($nodes, 'noPolicy');
        if ($factory === null || $noPolicy === null) {
            return ['evaluation-methods'];
        }
        if ($this->countNew($factory, 'AuditRecordPolicy') !== 1) {
            $violations[] = 'evaluation-policy-construction';
        }
        if ($this->countStaticCalls($factory, 'AuditRecordPolicy', 'fromArray') !== 0) {
            $violations[] = 'evaluation-policy-factory';
        }
        if ($this->countForbiddenMethodCalls($nodes) !== 0) {
            $violations[] = 'evaluation-forbidden-call';
        }
        return $violations;
    }

    /** @return list<string> */
    private function evaluatorViolations(array $nodes): array
    {
        $violations = [];
        $class = $this->classNode($nodes);
        $constructor = $this->methodNode($nodes, '__construct');
        $evaluate = $this->methodNode($nodes, 'evaluate');
        if ($class === null || $constructor === null || $evaluate === null) {
            return ['evaluator-methods'];
        }
        if ($this->snapshotKeys($constructor) !== ['evaluator', 'policies']) {
            $violations[] = 'evaluator-snapshot-keys';
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'evaluator-constructor-work';
        }
        $envelopeCalls = array_values(array_filter(
            $this->methodCalls($evaluate),
            fn(MethodCall $call): bool => $this->receiver($call->var) === '$envelope',
        ));
        $metadataCalls = array_values(array_filter(
            $this->methodCalls($evaluate),
            fn(MethodCall $call): bool => $this->receiver($call->var) === '$metadata',
        ));
        $definitionCalls = array_values(array_filter(
            $envelopeCalls,
            fn(MethodCall $call): bool => $this->methodName($call) === 'definition',
        ));
        if (count($definitionCalls) !== 1 || count($envelopeCalls) !== 1) {
            $violations[] = 'evaluator-envelope-access';
        }
        $resolveCalls = $this->matchingCalls($evaluate, '$policies', 'resolve');
        $policyCalls = array_values(array_filter(
            $this->methodCalls($evaluate),
            fn(MethodCall $call): bool => $this->receiver($call->var) === '$policies',
        ));
        if (count($policyCalls) !== 1 || count($resolveCalls) !== 1 || !$this->argumentsMatch($resolveCalls[0]->args, [[
            'variable',
            'metadata',
        ]])) {
            $violations[] = 'evaluator-single-resolution';
        }
        $delegateCalls = $this->matchingCalls($evaluate, '$evaluator', 'evaluate');
        $evaluatorCalls = array_values(array_filter(
            $this->methodCalls($evaluate),
            fn(MethodCall $call): bool => $this->receiver($call->var) === '$evaluator',
        ));
        if (
            count($evaluatorCalls) !== 1
            || count($delegateCalls) !== 1
            || !$this->argumentsMatch($delegateCalls[0]->args, [
                ['variable', 'metadata'],
                ['variable', 'envelope'],
            ])
        ) {
            $violations[] = 'evaluator-single-delegate';
        }
        if ($this->countStaticCalls($evaluate, 'AuthorizationAuditEvaluation', 'noPolicy') !== 1) {
            $violations[] = 'evaluator-no-policy-factory';
        }
        if ($this->countStaticCalls($evaluate, 'AuthorizationAuditEvaluation', 'fromDecision') !== 1) {
            $violations[] = 'evaluator-decision-factory';
        }
        $snapshotReads = $this->matchingCalls($evaluate, '$this', 'value');
        $thisCalls = array_values(array_filter(
            $this->methodCalls($evaluate),
            fn(MethodCall $call): bool => $this->receiver($call->var) === '$this',
        ));
        if (count($thisCalls) !== 2 || count($snapshotReads) !== 2 || !$this->argumentsMatch($snapshotReads[0]->args, [[
            'string',
            'evaluator',
        ]]) || !$this->argumentsMatch($snapshotReads[1]->args, [['string', 'policies']])) {
            $violations[] = 'evaluator-snapshot-reads';
        }
        if ($metadataCalls !== []) {
            $violations[] = 'evaluator-metadata-work';
        }
        if (
            $definitionCalls !== []
            && $resolveCalls !== []
            && $definitionCalls[0]->getStartFilePos() >= $resolveCalls[0]->getStartFilePos()
        ) {
            $violations[] = 'evaluator-definition-order';
        }
        if (
            $resolveCalls !== []
            && $delegateCalls !== []
            && $resolveCalls[0]->getStartFilePos() >= $delegateCalls[0]->getStartFilePos()
        ) {
            $violations[] = 'evaluator-resolution-order';
        }
        if ($this->countForbiddenMethodCalls($nodes) !== 0) {
            $violations[] = 'evaluator-forbidden-call';
        }
        if ($this->countNew($constructor, 'SensitiveParameterValue') !== 1) {
            $violations[] = 'evaluator-snapshot-construction';
        }
        return $violations;
    }

    /** @return list<string> */
    private function factoryViolations(array $nodes): array
    {
        $violations = [];
        $constructor = $this->methodNode($nodes, '__construct');
        $create = $this->methodNode($nodes, 'create');
        $actionUuid = $this->methodNode($nodes, 'actionUuid');
        $occurredAt = $this->methodNode($nodes, 'occurredAt');
        $catalog = $this->methodNode($nodes, 'catalog');
        $opaqueId = $this->methodNode($nodes, 'opaqueId');
        if (
            $constructor === null
            || $create === null
            || $actionUuid === null
            || $occurredAt === null
            || $catalog === null
            || $opaqueId === null
        ) {
            return ['factory-methods'];
        }
        if (
            $this->snapshotKeys($constructor) !== [
                'opaqueIds',
                'identifiers',
                'clock',
                'runtime',
                'buildId',
                'executionFallback',
            ]
        ) {
            $violations[] = 'factory-snapshot-keys';
        }
        $fallbackValidationCalls = array_values(array_filter(
            $this->methodCalls($constructor),
            fn(MethodCall $call): bool => (
                $this->receiver($call->var) === '$executionFallback'
                && in_array($this->methodName($call), ['type', 'id'], true)
                && $call->args === []
            ),
        ));
        if (
            count($fallbackValidationCalls) !== 2
            || array_map(fn(MethodCall $call): string => $this->methodName($call), $fallbackValidationCalls) !== [
                'type',
                'id',
            ]
            || count($this->methodCalls($constructor)) !== count($fallbackValidationCalls)
        ) {
            $violations[] = 'factory-constructor-work';
        }
        $noPolicy = $this->matchingCalls($create, '$this', 'noPolicy');
        $actionUuidCalls = $this->matchingCalls($create, '$this', 'actionUuid');
        if (count($noPolicy) !== 1 || count($actionUuidCalls) !== 1) {
            $violations[] = 'factory-policy-gate';
        } elseif ($noPolicy[0]->getStartFilePos() >= $actionUuidCalls[0]->getStartFilePos()) {
            $violations[] = 'factory-policy-order';
        }
        if ($this->countCalls($actionUuid, '$identifiers', 'generate') !== 1) {
            $violations[] = 'factory-uuid-source';
        }
        if ($this->countCalls($occurredAt, '$clock', 'now') !== 1) {
            $violations[] = 'factory-clock-source';
        }
        if (
            $this->countStaticCalls(
                $catalog,
                'BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog',
                'catalog',
            ) !== 1
        ) {
            $violations[] = 'factory-catalog-source';
        }
        if ($this->countCalls($opaqueId, '$opaqueIds', 'derive') !== 1) {
            $violations[] = 'factory-opaque-source';
        }
        if ($this->countNew($create, 'AuditDeliveryRequest') !== 1) {
            $violations[] = 'factory-request-construction';
        }
        foreach ($this->methodCalls($nodes) as $call) {
            $receiver = $this->receiver($call->var);
            if (
                in_array($receiver, ['$delivery', '$connection', '$transactions', '$selector', '$handler'], true)
                || $this->methodName($call) === 'append'
                || $this->methodName($call) === 'begintransaction'
                || $this->methodName($call) === 'rollback'
                || $this->methodName($call) === 'commit'
            ) {
                $violations[] = 'factory-forbidden-boundary-call';
                break;
            }
        }
        if (
            $this->countForbiddenMethodCallsForPath(
                $nodes,
                'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php',
            ) !== 0
        ) {
            $violations[] = 'factory-forbidden-call';
        }

        return $violations;
    }

    /** @return list<string> */
    private function coordinatorViolations(array $nodes): array
    {
        $violations = [];
        $constructor = $this->methodNode($nodes, '__construct');
        $evaluate = $this->methodNode($nodes, 'evaluate');
        if ($constructor === null || $evaluate === null) {
            return ['coordinator-methods'];
        }
        if ($this->snapshotKeys($constructor) !== ['evaluator', 'factory', 'selector', 'delivery', 'connection']) {
            $violations[] = 'coordinator-snapshot-keys';
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'coordinator-constructor-work';
        }
        $values = $this->matchingCalls($evaluate, '$this', 'value');
        $expectedKeys = ['evaluator', 'factory', 'selector', 'delivery', 'connection'];
        if (count($values) !== count($expectedKeys)) {
            $violations[] = 'coordinator-snapshot-reads';
        } else {
            foreach ($values as $index => $value) {
                if (!$this->argumentsMatch($value->args, [['string', $expectedKeys[$index]]])) {
                    $violations[] = 'coordinator-snapshot-reads';
                    break;
                }
            }
        }
        $evaluatorCalls = $this->matchingCalls($evaluate, '$evaluator', 'evaluate');
        if (count($evaluatorCalls) !== 1 || !$this->argumentsMatch($evaluatorCalls[0]->args, [
            ['variable', 'metadata'],
            ['variable', 'envelope'],
        ])) {
            $violations[] = 'coordinator-evaluator-edge';
        }
        $contextCalls = $this->matchingCalls($evaluate, '$envelope', 'context');
        $factoryCalls = $this->matchingCalls($evaluate, '$factory', 'create');
        if (
            count($contextCalls) !== 1
            || count($factoryCalls) !== 1
            || count($factoryCalls) !== 1
            || count($factoryCalls[0]->args) !== 2
            || !$this->isVariable($factoryCalls[0]->args[0]->value ?? null, 'evaluation')
            || !$this->isMethodCall($factoryCalls[0]->args[1]->value ?? null, '$envelope', 'context')
        ) {
            $violations[] = 'coordinator-factory-edge';
        }
        $draftCalls = $this->matchingCalls($evaluate, '$request', 'draft');
        $forDraftCalls = $this->matchingCalls($evaluate, '$selector', 'forDraft');
        $validateCalls = $this->matchingCalls($evaluate, '$selector', 'validate');
        if (
            count($draftCalls) !== 1
            || count($forDraftCalls) !== 1
            || count($validateCalls) !== 1
            || !$this->isMethodCall($forDraftCalls[0]->args[0]->value ?? null, '$request', 'draft')
            || !$this->argumentsMatch($validateCalls[0]->args, [
                ['variable', 'plan'],
                ['variable', 'request'],
            ])
        ) {
            $violations[] = 'coordinator-selector-edge';
        }
        if ($this->countStaticCalls($evaluate, 'AuthorizationAuditAttempt', 'prepare') !== 1) {
            $violations[] = 'coordinator-attempt-factory';
        }
        $prepare = array_values(array_filter(
            new NodeFinder()->findInstanceOf($evaluate, StaticCall::class),
            fn(StaticCall $call): bool => (
                $call->name instanceof Identifier
                && strtolower($call->name->toString()) === 'prepare'
                && (
                    $this->sameSymbol(
                        $this->resolvedName($call->class),
                        'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditAttempt',
                    )
                    || strtolower($this->terminalName($call->class)) === 'authorizationauditattempt'
                )
            ),
        ));
        if (count($prepare) !== 1 || !$this->argumentsMatch($prepare[0]->args, [
            ['variable', 'evaluation'],
            ['variable', 'request'],
            ['variable', 'plan'],
            ['variable', 'delivery'],
            ['variable', 'connection'],
        ])) {
            $violations[] = 'coordinator-attempt-factory';
        }
        if ($this->countNew($evaluate, 'AuthorizationAuditAttempt') !== 0) {
            $violations[] = 'coordinator-attempt-construction';
        }
        if (
            $this->countForbiddenMethodCallsForPath(
                $nodes,
                'src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php',
            ) !== 0
        ) {
            $violations[] = 'coordinator-forbidden-call';
        }

        return $violations;
    }

    /** @return list<string> */
    private function attemptViolations(array $nodes): array
    {
        $violations = [];
        $constructor = $this->methodNode($nodes, '__construct');
        $prepare = $this->methodNode($nodes, 'prepare');
        $mutation = $this->methodNode($nodes, 'assertMutation');
        $standalone = $this->methodNode($nodes, 'deliverStandalone');
        $current = $this->methodNode($nodes, 'deliverInMutationTransaction');
        $owned = $this->methodNode($nodes, 'deliverInOwnedTransaction');
        $values = $this->methodNode($nodes, 'deliveryValues');
        $evaluation = $this->methodNode($nodes, 'evaluation');
        $agreement = $this->methodNode($nodes, 'assertProjectionAgreement');
        if (
            $constructor === null
            || $prepare === null
            || $mutation === null
            || $standalone === null
            || $current === null
            || $owned === null
            || $values === null
            || $evaluation === null
            || $agreement === null
        ) {
            return ['attempt-methods'];
        }
        if ($this->snapshotKeys($constructor) !== ['evaluation', 'request', 'plan', 'delivery', 'connection']) {
            $violations[] = 'attempt-snapshot-keys';
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'attempt-constructor-work';
        }
        if (
            $this->countStaticCalls($prepare, 'self', 'assertProjectionAgreement') !== 1
            || $this->countCalls($agreement, '$evaluation', 'hasPolicy') !== 1
            || $this->countCalls($agreement, '$evaluation', 'policy') !== 2
            || $this->countCalls($agreement, '$evaluation', 'rejection') !== 1
            || $this->countCalls($agreement, '$request', 'draft') !== 1
            || $this->countCalls($agreement, '$plan', 'delivery') !== 1
            || $this->countNew($prepare, 'self') !== 1
        ) {
            $violations[] = 'attempt-preparation-graph';
        }
        $evaluationReads = $this->matchingCalls($evaluation, '$this', 'value');
        if (count($evaluationReads) !== 3 || !$this->argumentsMatch($evaluationReads[0]->args, [[
            'string',
            'evaluation',
        ]]) || !$this->argumentsMatch($evaluationReads[1]->args, [[
            'string',
            'request',
        ]]) || !$this->argumentsMatch($evaluationReads[2]->args, [[
            'string',
            'plan',
        ]]) || $this->countStaticCalls($evaluation, 'self', 'assertProjectionAgreement') !== 1) {
            $violations[] = 'attempt-evaluation-access';
        }
        $mutationValues = $this->matchingCalls($mutation, '$this', 'value');
        if (
            $this->countCalls($mutation, '$this', 'evaluation') !== 1
            || $this->countCalls($mutation, '$this', 'deliveryValues') !== 0
            || count($mutationValues) !== 2
            || !$this->argumentsMatch($mutationValues[0]->args, [['string', 'delivery']])
            || !$this->argumentsMatch($mutationValues[1]->args, [['string', 'connection']])
            || $this->countCalls($mutation, '$transactions', 'isTransactional') !== 1
            || $this->countCalls($mutation, '$transactions', 'sharesFrameworkConnection') !== 1
            || $this->countCalls($mutation, '$transactions', 'usesFrameworkConnection') !== 1
            || $this->countCalls($mutation, '$delivery', 'usesConnection') !== 1
            || $this->countCalls($mutation, '$connection', 'isAutoCommit') !== 1
            || $this->countCalls($mutation, '$connection', 'isTransactionActive') !== 1
            || $this->countCalls($mutation, '$connection', 'getTransactionNestingLevel') !== 1
        ) {
            $violations[] = 'attempt-mutation-graph';
        }
        if (
            $this->countCalls($standalone, '$this', 'deliveryValues') !== 1
            || $this->countCalls($standalone, '$this', 'consume') !== 1
            || $this->countArrayCalls($standalone, 'values', 'delivery', 'usesConnection') !== 1
            || $this->countArrayCalls($standalone, 'values', 'delivery', 'deliverStandalone') !== 1
        ) {
            $violations[] = 'attempt-standalone-edge';
        }
        if (
            $this->countCalls($current, '$this', 'deliveryValues') !== 1
            || $this->countCalls($current, '$this', 'consume') !== 1
            || $this->countArrayCalls($current, 'values', 'delivery', 'usesConnection') !== 1
            || $this->countArrayCalls($current, 'values', 'delivery', 'deliverInCurrentTransaction') !== 1
        ) {
            $violations[] = 'attempt-current-edge';
        }
        if (
            $this->countCalls($owned, '$this', 'deliveryValues') !== 1
            || $this->countCalls($owned, '$this', 'consume') !== 1
            || $this->countArrayCalls($owned, 'values', 'delivery', 'usesConnection') !== 1
            || $this->countArrayCalls($owned, 'values', 'delivery', 'deliverInCurrentTransaction') !== 1
        ) {
            $violations[] = 'attempt-owned-edge';
        }
        if (
            $this->countCalls($values, '$this', 'value') !== 5
            || $this->countStaticCalls($values, 'self', 'assertProjectionAgreement') !== 1
        ) {
            $violations[] = 'attempt-snapshot-reads';
        }
        if (
            $this->countForbiddenMethodCallsForPath(
                $nodes,
                'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php',
            ) !== 0
        ) {
            $violations[] = 'attempt-forbidden-call';
        }
        foreach ($this->methodCalls($nodes) as $call) {
            if (in_array($this->methodName($call), ['evaluate', 'create', 'fromdecision', 'nopolicy'], true)) {
                $violations[] = 'attempt-forbidden-boundary-call';
                break;
            }
        }

        return $violations;
    }

    private function countForbiddenMethodCallsForPath(Node|array $nodes, string $path): int
    {
        $allowed = match ($path) {
            'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php' => ['generate', 'now'],
            'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => [
                'deliverstandalone',
                'deliverincurrenttransaction',
            ],
            default => [],
        };

        return count(array_filter(
            $this->methodCalls($nodes),
            fn(MethodCall $call): bool => (
                in_array($this->methodName($call), $this->forbiddenTypesMethods(), true)
                && !in_array($this->methodName($call), $allowed, true)
            ),
        ));
    }

    /** @return list<string> */
    private function forbiddenTypesMethods(): array
    {
        return [
            'append',
            'appenddurable',
            'appendrequired',
            'begintransaction',
            'commit',
            'deliver',
            'deliverincurrenttransaction',
            'deliverstandalone',
            'generate',
            'now',
            'rollback',
        ];
    }

    /** @param list<Node> $nodes @return list<string> */
    private function forbiddenSurfaceViolations(array $nodes, string $path): array
    {
        $violations = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            $terminal = strtolower($this->terminalName($name));
            if (
                in_array($terminal, $this->forbiddenTypes(), true)
                && !$this->allowedSurfaceType($path, $this->resolvedName($name))
            ) {
                $violations[] = 'forbidden-type:' . $path . ':' . $terminal;
            }
        }
        if ($this->countForbiddenMethodCallsForPath($nodes, $path) !== 0) {
            $violations[] = 'forbidden-method:' . $path;
        }
        return $violations;
    }

    private function allowedSurfaceType(string $path, ?string $name): bool
    {
        if ($name === null) {
            return false;
        }
        $allowed = match ($path) {
            'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php' => [
                'BlackOps\\Core\\ExecutionContext',
                'BlackOps\\Core\\Identifier\\OperationId',
                'BlackOps\\Core\\Audit\\AuditRecordDraft',
                'BlackOps\\Identifier\\Uuidv7Generator',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
                'Psr\\Clock\\ClockInterface',
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php' => [
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
                'BlackOps\\Core\\ExecutionContext',
                'Doctrine\\DBAL\\Connection',
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => [
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
                'BlackOps\\Internal\\Transaction\\OperationTransactionCoordinator',
                'Doctrine\\DBAL\\Connection',
            ],
            default => [],
        };

        foreach ($allowed as $candidate) {
            if ($this->sameSymbol($name, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function productionActivationViolations(): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($root);
        $targetDirectory = realpath($root . '/Internal/Audit/Authorization');
        self::assertNotFalse($targetDirectory);
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = realpath($file->getPathname());
            self::assertNotFalse($path);
            if ($path === $targetDirectory || str_starts_with($path, $targetDirectory . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $source = file_get_contents($path);
            self::assertIsString($source);
            try {
                $nodes = $this->parse($source);
            } catch (\Throwable) {
                $violations[] = 'syntax:' . $path;
                continue;
            }
            $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            $runtimeTargets = $this->runtimeAllowedTargets($relative);
            foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
                $resolved = $this->resolvedName($name);
                foreach ($this->targetTypes() as $target) {
                    if ($this->sameSymbol($resolved, $target)) {
                        if ($runtimeTargets === [] || !$this->targetInList($target, $runtimeTargets)) {
                            $violations[] = 'activation:' . $path;
                        }
                    }
                }
            }
            if ($runtimeTargets !== []) {
                $violations = [
                    ...$violations,
                    ...$this->runtimeEdgeViolations($nodes, $relative),
                ];
            }
        }
        $violations = [...$violations, ...$this->canonicalComposerViolations()];

        return array_values(array_unique($violations));
    }

    /** @return array<string,list<array{class:string,args:list<string|null>}>> */
    private function canonicalConstructorSites(): array
    {
        return [
            'Internal/Application/ApplicationHttpRuntimeComposer.php' => [
                [
                    'class' => 'BlackOps\\Internal\\Execution\\DeferredAcceptanceOrchestrator',
                    'args' => [
                        null,
                        null,
                        null,
                        null,
                        'authorization',
                        'scope',
                        'idempotency',
                        'idempotencyRetention',
                        'idempotencyRecovery',
                    ],
                ],
            ],
            'Internal/Application/ApplicationScheduledOperationRuntimeComposer.php' => [
                [
                    'class' => 'BlackOps\\Internal\\Execution\\InlineDispatcher',
                    'args' => [
                        null,
                        null,
                        null,
                        null,
                        null,
                        'observations',
                        'scope',
                        'authorization',
                        'transactions',
                        'scheduledOccurrences',
                        'clock',
                    ],
                ],
                [
                    'class' => 'BlackOps\\Internal\\Execution\\DeferredAcceptanceOrchestrator',
                    'args' => [null, null, null, null, 'authorization', 'scope', 'scheduledOccurrences'],
                ],
            ],
            'Internal/Application/ApplicationOperationConsoleRuntimeComposer.php' => [
                [
                    'class' => 'BlackOps\\Internal\\Execution\\InlineDispatcher',
                    'args' => [null, null, null, null, null, 'observations', 'scope', 'authorization', 'transactions'],
                ],
                [
                    'class' => 'BlackOps\\Internal\\Execution\\DeferredAcceptanceOrchestrator',
                    'args' => [null, null, null, null, 'authorization', 'scope'],
                ],
            ],
            'Internal/Application/ApplicationWorkerComposer.php' => [
                [
                    'class' => 'BlackOps\\Internal\\Execution\\DeferredWorkerRuntime',
                    'args' => [null, null, null, 'connections', 'metrics'],
                ],
            ],
            'Internal/Runtime/ProductionRuntimeComposer.php' => [
                [
                    'class' => 'BlackOps\\Internal\\Execution\\InlineDispatcher',
                    'args' => [
                        null,
                        null,
                        null,
                        null,
                        null,
                        'observations',
                        'scope',
                        'authorization',
                        'transactions',
                        'idempotency',
                        'idempotencyRetention',
                        'idempotencyRecovery',
                    ],
                ],
            ],
        ];
    }

    /** @param array<string,string> $overrides @return list<string> */
    private function canonicalComposerViolations(array $overrides = []): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($root);
        $violations = [];
        foreach ($this->canonicalConstructorSites() as $relative => $expected) {
            $source = $overrides[$relative] ?? file_get_contents($root . '/' . $relative);
            if (!is_string($source)) {
                $violations[] = 'canonical-source:' . $relative;
                continue;
            }
            try {
                $nodes = $this->parse($source);
            } catch (\Throwable) {
                $violations[] = 'canonical-syntax:' . $relative;
                continue;
            }
            $actual = [];
            foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
                $resolved = $this->resolvedName($new->class);
                foreach ($expected as $site) {
                    if (!$this->sameSymbol($resolved, $site['class'])) {
                        continue;
                    }
                    if (array_filter($new->args, static fn(Node\Arg $argument): bool => $argument->unpack) !== []) {
                        $violations[] = 'canonical-unpacked-argument:' . $relative;
                    }
                    $actual[] = [
                        'class' => $site['class'],
                        'args' => array_map(fn(Node\Arg $argument): ?string => $argument->name instanceof Identifier
                            ? $argument->name->toString()
                            : null, $new->args),
                    ];
                    break;
                }
            }
            if ($actual !== $expected) {
                $violations[] = 'canonical-constructor-inventory:' . $relative;
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function runtimeAllowedTargets(string $relative): array
    {
        return match ($relative) {
            'Internal/Execution/InlineDispatcher.php' => [
                'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditAttempt',
                'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditCoordinator',
            ],
            'Internal/Execution/DeferredAcceptanceOrchestrator.php' => [
                'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditCoordinator',
            ],
            'Internal/Execution/DeferredWorkerRuntime.php' => [
                'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditAttempt',
                'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditCoordinator',
            ],
            default => [],
        };
    }

    /** @param list<string> $targets */
    private function targetInList(string $target, array $targets): bool
    {
        foreach ($targets as $candidate) {
            if ($this->sameSymbol($target, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function runtimeEdgeViolations(array $nodes, string $relative): array
    {
        $violations = [];
        $allowed = [
            'coordinator' => ['evaluate'],
            'attempt' => [
                'assertmutation',
                'deliverinownedtransaction',
                'deliverinmutationtransaction',
                'deliverstandalone',
                'evaluation',
            ],
        ];
        $finder = new NodeFinder();
        $calls = [
            ...$finder->findInstanceOf($nodes, MethodCall::class),
            ...$finder->findInstanceOf($nodes, NullsafeMethodCall::class),
        ];
        foreach ($calls as $call) {
            $kind = null;
            if (
                $call->var instanceof Node\Expr\PropertyFetch
                && $this->isThisPropertyNode($call->var, 'authorizationAudit')
            ) {
                $kind = 'coordinator';
            } elseif ($call->var instanceof Variable && $call->var->name === 'authorizationAttempt') {
                $kind = 'attempt';
            }
            if ($kind === null) {
                $evaluationResultMethod = $this->evaluationResultMethod($call);
                if (
                    $evaluationResultMethod !== null
                    && !in_array(
                        $evaluationResultMethod,
                        ['haspolicy', 'isallowed', 'isdenied', 'rejection', 'policy'],
                        true,
                    )
                ) {
                    $violations[] = 'runtime-edge:' . $relative . ':attempt:' . $evaluationResultMethod;
                }
                continue;
            }
            $name = $call->name instanceof Identifier ? strtolower($call->name->toString()) : '';
            if (!in_array($name, $allowed[$kind], true)) {
                $violations[] = 'runtime-edge:' . $relative . ':' . $kind . ':' . ($name === '' ? 'dynamic' : $name);
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            foreach ($this->targetTypes() as $target) {
                if ($this->sameSymbol($this->resolvedName($call->class), $target)) {
                    $violations[] = 'runtime-static-edge:' . $relative;
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            foreach ($this->targetTypes() as $target) {
                if ($this->sameSymbol($this->resolvedName($new->class), $target)) {
                    $violations[] = 'runtime-construction:' . $relative;
                }
            }
        }

        return array_values(array_unique($violations));
    }

    private function isThisPropertyNode(Node\Expr\PropertyFetch $fetch, string $property): bool
    {
        return (
            $fetch->var instanceof Variable
            && $fetch->var->name === 'this'
            && $fetch->name instanceof Identifier
            && strtolower($fetch->name->toString()) === strtolower($property)
        );
    }

    private function evaluationResultMethod(MethodCall|NullsafeMethodCall $call): ?string
    {
        if (
            !$call->var instanceof MethodCall && !$call->var instanceof NullsafeMethodCall
            || !$call->var->name instanceof Identifier
            || strtolower($call->var->name->toString()) !== 'evaluation'
            || !$call->var->var instanceof Variable
            || $call->var->var->name !== 'authorizationAttempt'
        ) {
            return null;
        }

        return $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
    }

    /** @return array<string,array{class:string,readonly:bool,methods:array<string,string>,references:list<string>}> */
    private function sourceDefinitions(): array
    {
        return [
            'src/Internal/Audit/Authorization/AuthorizationAuditPolicyBinding.php' => [
                'class' => 'AuthorizationAuditPolicyBinding',
                'readonly' => true,
                'methods' => [
                    '__construct' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                ],
                'references' => [],
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php' => [
                'class' => 'AuthorizationAuditPolicyRegistry',
                'readonly' => true,
                'methods' => [
                    '__construct' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'assertBinding' => 'private',
                    'resolve' => 'public',
                ],
                'references' => ['AuthorizationAuditPolicyBinding'],
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php' => [
                'class' => 'AuthorizationAuditEvaluation',
                'readonly' => true,
                'methods' => [
                    '__construct' => 'private',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'fromDecision' => 'public',
                    'hasPolicy' => 'public',
                    'isAllowed' => 'public',
                    'isDenied' => 'public',
                    'noPolicy' => 'public',
                    'policy' => 'public',
                    'rejection' => 'public',
                ],
                'references' => ['AuthorizationAuditPolicyBinding'],
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php' => [
                'class' => 'AuthorizationAuditEvaluator',
                'readonly' => false,
                'methods' => [
                    '__construct' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'evaluate' => 'public',
                    'value' => 'private',
                ],
                'references' => ['AuthorizationAuditEvaluation', 'AuthorizationAuditPolicyRegistry'],
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php' => [
                'class' => 'AuthorizationAuditEventFactory',
                'readonly' => false,
                'methods' => [
                    '__construct' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'actionUuid' => 'private',
                    'actor' => 'private',
                    'buildId' => 'private',
                    'catalog' => 'private',
                    'correlation' => 'private',
                    'create' => 'public',
                    'definition' => 'private',
                    'draft' => 'private',
                    'executionFallback' => 'private',
                    'failure' => 'private',
                    'noPolicy' => 'private',
                    'opaqueId' => 'private',
                    'operationId' => 'private',
                    'occurredAt' => 'private',
                    'policy' => 'private',
                    'reference' => 'private',
                    'remap' => 'private',
                    'runtime' => 'private',
                    'tenant' => 'private',
                    'value' => 'private',
                ],
                'references' => ['AuthorizationAuditEvaluation'],
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php' => [
                'class' => 'AuthorizationAuditCoordinator',
                'readonly' => false,
                'methods' => [
                    '__construct' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'evaluate' => 'public',
                    'failure' => 'private',
                    'remap' => 'private',
                    'value' => 'private',
                ],
                'references' => [
                    'AuthorizationAuditAttempt',
                    'AuthorizationAuditEvaluation',
                    'AuthorizationAuditEventFactory',
                    'AuthorizationAuditEvaluator',
                ],
            ],
            'src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php' => [
                'class' => 'AuthorizationAuditAttempt',
                'readonly' => false,
                'methods' => [
                    '__construct' => 'private',
                    '__clone' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'assertMutation' => 'public',
                    'assertProjectionAgreement' => 'private',
                    'consume' => 'private',
                    'deliverInOwnedTransaction' => 'public',
                    'deliverInMutationTransaction' => 'public',
                    'deliverStandalone' => 'public',
                    'deliveryValues' => 'private',
                    'evaluation' => 'public',
                    'failure' => 'private',
                    'prepare' => 'public',
                    'remap' => 'private',
                    'value' => 'private',
                ],
                'references' => ['AuthorizationAuditEvaluation'],
            ],
        ];
    }

    /** @return array<string,string> */
    private function architectureSources(): array
    {
        $sources = [];
        foreach (array_keys($this->sourceDefinitions()) as $path) {
            $source = file_get_contents(__DIR__ . '/../../' . $path);
            self::assertIsString($source, $path);
            $sources[$path] = $source;
        }
        return $sources;
    }

    /** @return list<string> */
    private function relativePhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $directory,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($directory) + 1));
        }
        sort($files);
        return $files;
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

    /** @param array<string,string> $expected @return array<string,string> */
    private function normalizeInventory(array $expected): array
    {
        $normalized = [];
        foreach ($expected as $name => $visibility) {
            $normalized[strtolower($name)] = $visibility;
        }
        ksort($normalized);
        return $normalized;
    }

    /** @return array<string,string> */
    private function methodInventory(Class_ $class): array
    {
        $methods = [];
        foreach ($class->getMethods() as $method) {
            $methods[strtolower($method->name->toString())] = $method->isPublic()
                ? 'public'
                : ($method->isPrivate() ? 'private' : 'other');
        }
        ksort($methods);
        return $methods;
    }

    /** @param array<string,array{0:string,1:bool,2:string}> $expected */
    private function assertProperties(ReflectionClass $class, array $expected): void
    {
        $actual = [];
        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            $actual[$property->getName()] = [
                $property->isPublic() ? 'public' : ($property->isPrivate() ? 'private' : 'other'),
                $property->isReadOnly(),
                $this->reflectionTypeName($property->getType()),
            ];
            self::assertFalse($property->isStatic(), $class->getName() . '::$' . $property->getName());
        }
        ksort($actual);
        ksort($expected);
        foreach ($expected as $name => $descriptor) {
            $expected[$name][2] = strtolower($descriptor[2]);
        }
        foreach ($actual as $name => $descriptor) {
            $actual[$name][2] = strtolower($descriptor[2] ?? '');
        }
        self::assertSame($expected, $actual, $class->getName());
    }

    /** @param array<string,string> $methods */
    private function assertDeclaration(
        ReflectionClass $class,
        bool $readonly,
        array $methods,
        bool $allowClone = false,
    ): void {
        self::assertTrue($class->isFinal(), $class->getName());
        self::assertSame($readonly, $class->isReadOnly(), $class->getName());
        self::assertSame($allowClone, $class->hasMethod('__clone'), $class->getName());
        self::assertSame([], $class->getAttributes(\BlackOps\Core\Attribute\PublicApi::class), $class->getName());
        $actual = [];
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            $actual[strtolower($method->getName())] = $method->isPublic()
                ? 'public'
                : ($method->isPrivate() ? 'private' : 'other');
        }
        $expected = $this->normalizeInventory($methods);
        ksort($actual);
        self::assertSame($expected, $actual, $class->getName());
    }

    /** @param list<array{0:string,1:string,2:bool,3:bool}> $parameters */
    private function assertMethodContract(ReflectionMethod $method, ?string $returnType, array $parameters): void
    {
        if ($returnType === null) {
            self::assertFalse($method->hasReturnType(), $method->getName());
        } else {
            self::assertTrue(
                $this->sameType($this->reflectionTypeName($method->getReturnType()), $returnType, $method),
                $method->getName(),
            );
        }
        self::assertCount(count($parameters), $method->getParameters(), $method->getName());
        foreach ($parameters as $index => [$name, $type, $sensitive, $default]) {
            $parameter = $method->getParameters()[$index];
            self::assertSame($name, $parameter->getName(), $method->getName());
            self::assertTrue(
                $this->sameType($this->reflectionTypeName($parameter->getType()), $type),
                $method->getName(),
            );
            self::assertSame($sensitive, $this->isSensitive($parameter), $method->getName());
            self::assertSame($default, $parameter->isDefaultValueAvailable(), $method->getName());
            self::assertFalse($parameter->isVariadic(), $method->getName());
            self::assertFalse($parameter->isPassedByReference(), $method->getName());
        }
    }

    private function assertHiddenMethods(ReflectionClass $class): void
    {
        $this->assertMethodContract($class->getMethod('__debugInfo'), 'array', []);
        $this->assertMethodContract($class->getMethod('__serialize'), 'array', []);
        $this->assertMethodContract(
            $class->getMethod('__unserialize'),
            'void',
            [
                ['data', 'array', true, false],
            ],
        );
    }

    private function reflectionTypeName(?ReflectionType $type): ?string
    {
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }
        $name = basename(str_replace('\\', '/', $type->getName()));
        return ($type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' : '') . $name;
    }

    private function isSensitive(ReflectionParameter $parameter): bool
    {
        return count($parameter->getAttributes(\SensitiveParameter::class)) === 1;
    }

    private function sameType(?string $actual, string $expected, ?ReflectionMethod $method = null): bool
    {
        if ($actual === null) {
            return false;
        }
        if (strtolower($expected) === 'self' && $method !== null) {
            $declaringClass = basename(str_replace('\\', '/', $method->getDeclaringClass()->getName()));
            return in_array(strtolower($actual), ['self', strtolower($declaringClass)], true);
        }

        return strtolower($actual) === strtolower($expected);
    }

    /** @return list<string> */
    private function sensitiveParameterViolations(Class_ $class, string $path): array
    {
        $violations = [];
        $sensitiveNames = [
            'binding',
            'bindings',
            'buildId',
            'clock',
            'connection',
            'context',
            'data',
            'envelope',
            'evaluator',
            'evaluation',
            'executionFallback',
            'factory',
            'identifiers',
            'key',
            'metadata',
            'opaqueIds',
            'plan',
            'policies',
            'policyId',
            'policyVersion',
            'rejection',
            'request',
            'selector',
            'transactions',
        ];
        $sensitiveTypes = [
            'actorref',
            'auditdeliverycoordinator',
            'auditdeliveryplan',
            'auditdeliveryrequest',
            'authorizationauditpolicybinding',
            'authorizationauditpolicyregistry',
            'authorizationevaluator',
            'clockinterface',
            'connection',
            'operationenvelope',
            'operationmetadata',
            'operationtransactioncoordinator',
            'rejectionreason',
            'throwable',
            'uuidv7generator',
        ];
        foreach ($class->getMethods() as $method) {
            foreach ($method->params as $parameter) {
                $type = strtolower($this->terminalName($parameter->type));
                $name = is_string($parameter->var->name) ? $parameter->var->name : '';
                if (!in_array($name, $sensitiveNames, true) && !in_array($type, $sensitiveTypes, true)) {
                    continue;
                }
                $count = 0;
                foreach ($parameter->attrGroups as $group) {
                    foreach ($group->attrs as $attribute) {
                        if (strtolower($this->terminalName($attribute->name)) === 'sensitiveparameter') {
                            $count++;
                        }
                    }
                }
                if ($count !== 1) {
                    $violations[] = 'sensitive:' . $path . ':' . $method->name->toString() . ':' . $name;
                }
            }
        }
        return $violations;
    }

    /** @param list<Node> $nodes @return list<Node> */
    private function methodCalls(Node|array $nodes): array
    {
        $calls = new NodeFinder()->findInstanceOf($nodes, MethodCall::class);
        return array_values(array_filter($calls, static fn(Node $node): bool => $node instanceof MethodCall));
    }

    /** @return list<MethodCall> */
    private function matchingCalls(Node|array $scope, string $receiver, string $method): array
    {
        return array_values(array_filter(
            $this->methodCalls($scope),
            fn(MethodCall $call): bool => (
                $this->receiver($call->var) === $receiver
                && $this->methodName($call) === strtolower($method)
            ),
        ));
    }

    private function countCalls(Node|array $scope, string $receiver, string $method): int
    {
        return count($this->matchingCalls($scope, $receiver, $method));
    }

    private function countArrayCalls(Node|array $scope, string $receiver, string $key, string $method): int
    {
        $count = 0;
        foreach (new NodeFinder()->findInstanceOf($scope, MethodCall::class) as $call) {
            if (
                !$call->var instanceof Node\Expr\ArrayDimFetch
                || !$call->var->var instanceof Variable
                || $call->var->var->name !== $receiver
                || !$call->var->dim instanceof StringNode
                || $call->var->dim->value !== $key
                || !$call->name instanceof Identifier
                || strtolower($call->name->toString()) !== strtolower($method)
            ) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function countPropertyFetches(Node|array $scope, string $receiver, string $property): int
    {
        $count = 0;
        foreach (new NodeFinder()->findInstanceOf($scope, Node\Expr\PropertyFetch::class) as $fetch) {
            if (
                $this->receiver($fetch->var) === $receiver
                && $fetch->name instanceof Identifier
                && $fetch->name->toString() === $property
            ) {
                $count++;
            }
        }
        return $count;
    }

    private function methodName(MethodCall $call): string
    {
        return $call->name instanceof Identifier ? strtolower($call->name->toString()) : '';
    }

    private function isVariable(?Node $expression, string $name): bool
    {
        return $expression instanceof Variable && $expression->name === $name;
    }

    private function isMethodCall(?Node $expression, string $receiver, string $method): bool
    {
        return (
            $expression instanceof MethodCall
            && $this->receiver($expression->var) === $receiver
            && $this->methodName($expression) === strtolower($method)
        );
    }

    private function receiver(?Node $expression): ?string
    {
        return $expression instanceof Variable && is_string($expression->name) ? '$' . $expression->name : null;
    }

    /** @param list<Node\Arg> $arguments @param list<array{0:string,1?:string}> $expected */
    private function argumentsMatch(array $arguments, array $expected): bool
    {
        if (count($arguments) !== count($expected)) {
            return false;
        }
        foreach ($arguments as $index => $argument) {
            $shape = $expected[$index];
            if (
                $shape[0] === 'variable'
                && (!$argument->value instanceof Variable || $argument->value->name !== $shape[1])
            ) {
                return false;
            }
            if (
                $shape[0] === 'string'
                && (!$argument->value instanceof StringNode || $argument->value->value !== $shape[1])
            ) {
                return false;
            }
        }
        return true;
    }

    private function countStaticCalls(Node|array $scope, string $class, string $method): int
    {
        $count = 0;
        foreach (new NodeFinder()->findInstanceOf($scope, StaticCall::class) as $call) {
            if (
                (
                    $this->sameSymbol($this->resolvedName($call->class), $class)
                    || strtolower($this->terminalName($call->class)) === strtolower($class)
                )
                && $call->name instanceof Identifier
                && strtolower($call->name->toString()) === strtolower($method)
            ) {
                $count++;
            }
        }
        return $count;
    }

    private function countNew(Node|array $scope, string $class): int
    {
        $count = 0;
        foreach (new NodeFinder()->findInstanceOf($scope, New_::class) as $new) {
            if (
                $this->sameSymbol($this->resolvedName($new->class), $class)
                || strtolower($this->terminalName($new->class)) === strtolower($class)
            ) {
                $count++;
            }
        }
        return $count;
    }

    private function countForbiddenMethodCalls(Node|array $scope): int
    {
        $forbidden = [
            'append',
            'appenddurable',
            'appendrequired',
            'begintransaction',
            'deliver',
            'deliverincurrenttransaction',
            'deliverstandalone',
            'generate',
            'now',
            'rollback',
            'commit',
        ];
        return count(array_filter($this->methodCalls($scope), fn(MethodCall $call): bool => in_array(
            $this->methodName($call),
            $forbidden,
            true,
        )));
    }

    /** @return list<string> */
    private function targetReferences(Class_ $class): array
    {
        $references = [];
        foreach (new NodeFinder()->findInstanceOf($class, Name::class) as $name) {
            foreach ($this->targetTypes() as $target) {
                if ($this->sameSymbol($this->resolvedName($name), $target)) {
                    $references[] = $this->terminalName($target);
                }
            }
        }
        $references = array_values(array_unique($references));
        sort($references);
        return $references;
    }

    /** @return list<string> */
    private function targetTypes(): array
    {
        return [
            'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditAttempt',
            'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditCoordinator',
            'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditEvaluation',
            'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditEvaluator',
            'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditEventFactory',
            'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditPolicyBinding',
            'BlackOps\\Internal\\Audit\\Authorization\\AuthorizationAuditPolicyRegistry',
        ];
    }

    /** @return list<string> */
    private function forbiddenTypes(): array
    {
        return [
            'auditdeliverycoordinator',
            'auditdeliveryplan',
            'auditdeliveryrequest',
            'auditmutationtransactioncoordinator',
            'auditoutboxrelayruntime',
            'auditrecorddraft',
            'auditrecordfinalizer',
            'auditrecordvalidator',
            'auditorchestrator',
            'auditrelaypersistence',
            'auditstoreexception',
            'auditstorefailure',
            'authorizationrequest',
            'clockinterface',
            'connection',
            'executioncontext',
            'handlerinvoker',
            'handlerresolver',
            'operationhandler',
            'operationid',
            'operationvalue',
            'pdo',
            'postgresqlauditrecordstore',
            'serverrequestinterface',
            'uuidv7generator',
            'validateduuidv7generator',
        ];
    }

    private function snapshotKeys(ClassMethod $constructor): ?array
    {
        foreach (new NodeFinder()->findInstanceOf($constructor, New_::class) as $new) {
            if (strtolower($this->terminalName($new->class)) !== 'sensitiveparametervalue' || count($new->args) !== 1) {
                continue;
            }
            $value = $new->args[0]->value;
            if (!$value instanceof Node\Expr\Array_) {
                return null;
            }
            $keys = [];
            foreach ($value->items as $item) {
                if ($item === null || !$item->key instanceof StringNode) {
                    return null;
                }
                $keys[] = $item->key->value;
            }
            return $keys;
        }
        return null;
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

    /** @param list<Node> $nodes */
    private function methodNode(array $nodes, string $name): ?ClassMethod
    {
        $class = $this->classNode($nodes);
        if ($class === null) {
            return null;
        }
        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === strtolower($name)) {
                return $method;
            }
        }
        return null;
    }

    /** @return list<Node> */
    private function parse(string $source): array
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $nodes = $parser->parse($source);
        if (!is_array($nodes)) {
            return [];
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        return $traverser->traverse($nodes);
    }

    private function hasPublicApiAttribute(Class_ $class): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (strtolower($this->terminalName($attribute->name)) === 'publicapi') {
                    return true;
                }
            }
        }
        return false;
    }

    private function terminalName(Node|string|null $name): string
    {
        if (is_string($name)) {
            return basename(str_replace('\\', '/', $name));
        }
        if ($name instanceof Name) {
            return $name->getLast();
        }
        return $name instanceof Identifier ? $name->toString() : '';
    }

    private function resolvedName(Node|string|null $name): ?string
    {
        if (!$name instanceof Name) {
            return is_string($name) ? ltrim($name, '\\') : null;
        }
        $resolved = $name->getAttribute('resolvedName');
        if ($resolved instanceof Name) {
            return ltrim($resolved->toString(), '\\');
        }
        if (is_string($resolved)) {
            return ltrim($resolved, '\\');
        }
        return ltrim($name->toString(), '\\');
    }

    private function sameSymbol(?string $actual, string $expected): bool
    {
        return $actual !== null && strtolower(ltrim($actual, '\\')) === strtolower(ltrim($expected, '\\'));
    }

    private function replaceOnce(string $source, string $search, string $replacement): string
    {
        $count = 0;
        $result = str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count, 'mutation anchor must be present exactly once');
        return $result;
    }

    private function insertBeforeFinalBrace(string $source, string $insertion): string
    {
        $position = strrpos($source, '}');
        self::assertNotFalse($position, 'class closing brace must be present');
        return substr($source, 0, $position) . "\n" . $insertion . substr($source, $position);
    }
}
