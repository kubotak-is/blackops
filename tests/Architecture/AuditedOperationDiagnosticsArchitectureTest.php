<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Internal\Diagnostics\AuditedOperationDiagnosticsQuery;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsReadExecutionSource;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\Unset_;
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
 * Checks the latent global Diagnostics Audit adapters at their Internal boundary.
 *
 * The oracle parses only the two adapter files. Mutations stay bounded and
 * method-local so runtime tests remain the primary branch and data proof.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuditedOperationDiagnosticsArchitectureTest extends TestCase
{
    public function testExactDeclarationsPropertiesAndSensitiveSurfaces(): void
    {
        $this->assertRuntimeDeclaration(
            new ReflectionClass(AuditedOperationDiagnosticsQuery::class),
            null,
            [
                '__construct' => 'public',
                'find' => 'public',
                '__debugInfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'assertIdle' => 'private',
                'value' => 'private',
            ],
        );
        $this->assertRuntimeDeclaration(
            new ReflectionClass(OperationDiagnosticsReadExecutionSource::class),
            \BlackOps\Internal\Audit\Read\OperationReadExecutionSource::class,
            [
                '__construct' => 'public',
                'current' => 'public',
                '__debugInfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'value' => 'private',
            ],
        );

        $query = new ReflectionClass(AuditedOperationDiagnosticsQuery::class);
        $this->assertMethodContract(
            $query->getMethod('__construct'),
            null,
            [
                ['journal',    'CanonicalJournalReader',           true, false],
                ['outcomes',   'OutcomeReader',                    true, false],
                ['sources',    'DiagnosticsSourceReader',          true, false],
                ['authorizer', 'OperationDiagnosticsAuthorizer',   true, false],
                ['sessions',   'OperationReadAuditSessionFactory', true, false],
                ['connection', 'Connection',                       true, false],
            ],
        );
        $this->assertMethodContract(
            $query->getMethod('find'),
            'OperationDiagnosticsResult',
            [
                ['operationId',   'OperationId', true, false],
                ['currentActor',  '?ActorRef',   true, false],
                ['currentTenant', '?TenantRef',  true, false],
            ],
        );
        $this->assertMethodContract(
            $query->getMethod('assertIdle'),
            'void',
            [['connection', 'Connection', true, false]],
        );
        $this->assertMethodContract($query->getMethod('value'), 'mixed', [['key', 'string', true, false]]);
        $this->assertCommonHiddenMethods($query);

        $execution = new ReflectionClass(OperationDiagnosticsReadExecutionSource::class);
        $this->assertMethodContract(
            $execution->getMethod('__construct'),
            null,
            [['identifiers', 'Uuidv7Generator', true, false]],
        );
        $this->assertMethodContract($execution->getMethod('current'), 'AuditTypedReference', []);
        $this->assertCommonHiddenMethods($execution);
        $this->assertMethodContract($execution->getMethod('value'), 'mixed', [['key', 'string', true, false]]);
    }

    public function testTokenAwareBoundariesAndCallFlowsAreExact(): void
    {
        self::assertSame([], $this->architectureViolations($this->adapterSources()));
    }

    public function testBoundedMutationsRejectArchitectureDriftAndIgnoreInertText(): void
    {
        $sources = $this->adapterSources();
        $inert = $sources;
        $inert['src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php'] .= <<<'PHP'

            // $session->open(); $session->retry(); OperationDiagnosticsException::$diagnosticsCode
            $text = 'AuditReadPurpose::Status OperationDiagnosticsReadExecutionSource';
            PHP;
        self::assertSame([], $this->architectureViolations($inert));

        $queryPath = 'src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php';
        $executionPath = 'src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php';
        $query = $sources[$queryPath];
        $mutations = [
            'diagnostics-purpose-is-not-fixed' => [
                $queryPath => $this->replaceOnce($query, 'AuditReadPurpose::Diagnostics', 'AuditReadPurpose::Status'),
            ],
            'diagnostics-begin-is-repeated' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    '            $session = $sessions->begin(AuditReadPurpose::Diagnostics, $operationId, $currentActor, $currentTenant);',
                    "            \$session = \$sessions->begin(AuditReadPurpose::Diagnostics, \$operationId, \$currentActor, \$currentTenant);\n"
                    . '            $sessions->begin(AuditReadPurpose::Diagnostics, $operationId, $currentActor, $currentTenant);',
                ),
            ],
            'global-scope-guard-is-bypassed' => [
                $queryPath => $this->replaceOnce($query, 'if ($currentTenant !== null)', 'if (false)'),
            ],
            'post-authorizer-idle-check-is-removed' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    "        \$this->assertIdle(\$connection);\n\n        if (\$failure === null && \$result === null) {",
                    "        if (\$failure === null && \$result === null) {",
                ),
            ],
            'allow-decision-is-bypassed' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    'if ($decision !== AuditReadAuthorizationDecision::Allow)',
                    'if (true)',
                ),
            ],
            'unexpected-result-loses-integrity-classification' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    "                    \$failure = DiagnosticsFailureCode::IntegrityFailed;",
                    "                    \$failure = DiagnosticsFailureCode::StorageFailed;",
                ),
            ],
            'failure-retains-original-exception' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    '$failure = $exception->diagnosticsCode;',
                    '$failure = $exception;',
                ),
            ],
            'failure-exception-is-not-released' => [
                $queryPath => $this->replaceOnce($query, "                unset(\$exception);\n", ''),
            ],
            'terminal-is-repeated' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    '                $session->unavailable();',
                    "                \$session->unavailable();\n                \$session->unavailable();",
                ),
            ],
            'direct-session-open-is-added' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    "        try {\n            if (\$result instanceof OperationDiagnosticsFound)",
                    "        \$session->open();\n\n        try {\n            if (\$result instanceof OperationDiagnosticsFound)",
                ),
            ],
            'direct-writer-delivery-is-added' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    "        try {\n            if (\$result instanceof OperationDiagnosticsFound)",
                    "        \$writer->deliverStandalone();\n\n        try {\n            if (\$result instanceof OperationDiagnosticsFound)",
                ),
            ],
            'retry-is-added' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    "                \$session->unavailable();\n            }\n        } catch (Throwable) {",
                    "                \$session->unavailable();\n            }\n        } catch (Throwable) {\n            \$session->unavailable();",
                ),
            ],
            'snapshot-retains-extra-state' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    '    private \\SensitiveParameterValue $snapshot;',
                    "    private \\SensitiveParameterValue \$snapshot;\n    private string \$unexpected;",
                ),
            ],
            'query-parameter-loses-sensitivity' => [
                $queryPath => $this->replaceOnce(
                    $query,
                    "        #[\\SensitiveParameter]\n        OperationId \$operationId,",
                    "        OperationId \$operationId,",
                ),
            ],
            'execution-purpose-changes' => [
                $executionPath => $this->replaceOnce(
                    $sources[$executionPath],
                    "'operation_diagnostics_read'",
                    "'operation_journal_read'",
                ),
            ],
            'execution-wrapper-is-removed' => [
                $executionPath => $this->replaceOnce(
                    $sources[$executionPath],
                    'new ValidatedUuidv7Generator($identifiers)',
                    '$identifiers',
                ),
            ],
        ];
        foreach ($mutations as $name => $mutation) {
            $this->assertMutationSyntax($mutation, $name);
            self::assertNotEmpty($this->architectureViolations([...$sources, ...$mutation]), $name);
        }
    }

    /** @param array<string,string> $sources @return list<string> */
    private function architectureViolations(array $sources): array
    {
        $expectedPaths = [
            'src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php',
            'src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php',
        ];
        $actualPaths = array_keys($sources);
        sort($expectedPaths);
        sort($actualPaths);
        $violations = $expectedPaths === $actualPaths ? [] : ['source-path-inventory'];

        $declarations = [
            $expectedPaths[0] => [
                'class' => 'AuditedOperationDiagnosticsQuery',
                'interface' => null,
                'methods' => [
                    '__construct' => 'public',
                    'find' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'assertIdle' => 'private',
                    'value' => 'private',
                ],
            ],
            $expectedPaths[1] => [
                'class' => 'OperationDiagnosticsReadExecutionSource',
                'interface' => 'OperationReadExecutionSource',
                'methods' => [
                    '__construct' => 'public',
                    'current' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'value' => 'private',
                ],
            ],
        ];
        foreach ($declarations as $path => $expected) {
            if (!isset($sources[$path])) {
                $violations[] = 'missing:' . $path;
                continue;
            }
            $class = $this->classNode($sources[$path]);
            if ($class === null || $this->terminalName($class->name) !== $expected['class']) {
                $violations[] = 'class:' . $path;
                continue;
            }
            $interfaces = array_map(fn(Name $interface): string => $this->terminalName($interface), $class->implements);
            if ($interfaces !== ($expected['interface'] === null ? [] : [$expected['interface']])) {
                $violations[] = 'interface:' . $path;
            }
            if (!$class->isFinal() || !$class->isReadonly() || $class->extends !== null) {
                $violations[] = 'readonly-final:' . $path;
            }
            if ($this->hasPublicApiAttribute($class)) {
                $violations[] = 'public-api:' . $path;
            }
            $methodInventory = [];
            foreach ($class->getMethods() as $method) {
                $methodInventory[$method->name->toString()] = $method->isPublic()
                    ? 'public'
                    : ($method->isPrivate() ? 'private' : 'other');
            }
            ksort($methodInventory);
            $expectedMethods = $expected['methods'];
            ksort($expectedMethods);
            if ($methodInventory !== $expectedMethods) {
                $violations[] = 'methods:' . $path;
            }
            if (!$this->hasOnlyPrivateSnapshot($class)) {
                $violations[] = 'snapshot:' . $path;
            }
            $violations = [...$violations, ...$this->sensitiveParameterViolations($class, $path)];
        }

        if (isset($sources[$expectedPaths[0]])) {
            $violations = [...$violations, ...$this->queryViolations($expectedPaths[0], $sources[$expectedPaths[0]])];
        }
        if (isset($sources[$expectedPaths[1]])) {
            $violations = [
                ...$violations,
                ...$this->executionViolations($expectedPaths[1], $sources[$expectedPaths[1]]),
            ];
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function queryViolations(string $path, string $source): array
    {
        $violations = [];
        $class = $this->classNode($source);
        $constructor = $this->methodNode($source, '__construct');
        $method = $this->methodNode($source, 'find');
        $value = $this->methodNode($source, 'value');
        if ($class === null || $constructor === null || $method === null || $value === null) {
            return ['methods:' . $path];
        }
        if ($this->newTypeInventory($constructor) !== ['OperationDiagnosticsQuery', 'SensitiveParameterValue']) {
            $violations[] = 'construction:' . $path;
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'constructor-I/O:' . $path;
        }
        if (!str_contains($source, "array_keys(\$value) !== ['query', 'authorizer', 'sessions', 'connection']")) {
            $violations[] = 'snapshot-keys:' . $path;
        }
        if ($this->countInstanceof($value, 'OperationDiagnosticsQuery') !== 1) {
            $violations[] = 'snapshot-query-type:' . $path;
        }
        $calls = $this->methodCalls($method);
        foreach (['connection', 'sessions', 'authorizer', 'query'] as $key) {
            if ($this->countCalls($calls, '$this', 'value', [['string', $key]]) !== 1) {
                $violations[] = 'snapshot-access:' . $path . ':' . $key;
            }
        }
        $begin = $this->matchingCalls($calls, '$sessions', 'begin');
        if (count($begin) !== 1 || !$this->beginCallMatches($begin[0])) {
            $violations[] = 'fixed-purpose-or-begin:' . $path;
        }
        $authorizer = $this->matchingCalls($calls, '$authorizer', 'decide');
        if (count($authorizer) !== 1 || !$this->authorizationCallMatches($authorizer[0])) {
            $violations[] = 'authorization-call:' . $path;
        }
        $query = $this->matchingCalls($calls, '$query', 'find');
        if (count($query) !== 1 || !$this->argumentsMatch($query[0]->args, [['variable', 'operationId']])) {
            $violations[] = 'aggregate-call:' . $path;
        }
        if ($this->countNew($method, 'OperationDiagnosticsAuthorizationRequest', [
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
        ]) !== 1) {
            $violations[] = 'request-shape:' . $path;
        }
        if (!$this->globalScopeGuardIsExact($method, $authorizer, $query)) {
            $violations[] = 'global-scope-guard:' . $path;
        }
        if (!$this->authorizationDecisionIsExact($method, $query)) {
            $violations[] = 'authorization-decision:' . $path;
        }
        $idle = $this->matchingCalls($calls, '$this', 'assertIdle');
        if (count($idle) !== 5) {
            $violations[] = 'idle-check-count:' . $path;
        }
        $accessed = $this->matchingCalls($calls, '$session', 'accessed');
        $unavailable = $this->matchingCalls($calls, '$session', 'unavailable');
        if (count($accessed) !== 1 || count($unavailable) !== 1) {
            $violations[] = 'terminal-count:' . $path;
        }
        if (!$this->phaseOrderIsExact($begin, $authorizer, $query, $idle, $accessed, $unavailable)) {
            $violations[] = 'phase-order:' . $path;
        }
        if (!$this->terminalTryIsExact($method, $accessed, $unavailable, $query)) {
            $violations[] = 'terminal-boundary:' . $path;
        }
        if (!$this->unexpectedResultClassificationIsExact($method)) {
            $violations[] = 'unexpected-result-classification:' . $path;
        }
        if (!$this->failureRetentionIsEnumOnly($method)) {
            $violations[] = 'enum-only-failure-retention:' . $path;
        }
        if (!$this->freshFailureFactoriesFollowTerminal($method, $accessed, $unavailable)) {
            $violations[] = 'fresh-failure-after-terminal:' . $path;
        }
        if (!$this->releaseFollowsTerminal($method, $accessed, $unavailable)) {
            $violations[] = 'terminal-before-release:' . $path;
        }
        if ($this->hasForbiddenCall($source)) {
            $violations[] = 'forbidden-call:' . $path;
        }
        if ($this->countNew($method, 'OperationReadAuditSessionFactory', []) !== 0) {
            $violations[] = 'factory-construction:' . $path;
        }
        if ($this->containsNamedType($source, ['AuditReadEventWriter'])) {
            $violations[] = 'writer-reference:' . $path;
        }
        if (
            !$this->methodReturnsEmptyArray($source, '__debugInfo')
            || !$this->methodReturnsEmptyArray($source, '__serialize')
            || !$this->methodThrowsFreshStorageFailure($source, '__unserialize')
        ) {
            $violations[] = 'hidden-state:' . $path;
        }
        if (!$this->valueHasExactSnapshotChecks($value)) {
            $violations[] = 'snapshot-validation:' . $path;
        }
        return $violations;
    }

    /** @return list<string> */
    private function executionViolations(string $path, string $source): array
    {
        $violations = [];
        $constructor = $this->methodNode($source, '__construct');
        $current = $this->methodNode($source, 'current');
        $value = $this->methodNode($source, 'value');
        if ($constructor === null || $current === null || $value === null) {
            return ['methods:' . $path];
        }
        if ($this->newTypeInventory($constructor) !== ['SensitiveParameterValue', 'ValidatedUuidv7Generator']) {
            $violations[] = 'validated-generator:' . $path;
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'execution-constructor-I/O:' . $path;
        }
        $calls = $this->methodCalls($current);
        if ($this->countCalls($calls, '$this', 'value', [[
            'string',
            'identifiers',
        ]]) !== 1 || count($this->matchingCalls($calls, '$identifiers', 'generate')) !== 1) {
            $violations[] = 'single-generation:' . $path;
        }
        $typedReferences = [];
        foreach (new NodeFinder()->findInstanceOf($current, New_::class) as $new) {
            if ($this->terminalName($new->class) === 'AuditTypedReference') {
                $typedReferences[] = $new;
            }
        }
        if (count($typedReferences) !== 1 || !$this->argumentsMatch($typedReferences[0]->args, [
            ['string', 'operation_diagnostics_read'],
            ['method-call', '$identifiers', 'generate'],
        ])) {
            $violations[] = 'fixed-execution-purpose:' . $path;
        }
        if ($this->newTypeInventory($current) !== ['AuditDeliveryException', 'AuditTypedReference', 'LogicException']) {
            $violations[] = 'current-construction:' . $path;
        }
        if ($this->hasForbiddenCall($source)) {
            $violations[] = 'execution-forbidden-call:' . $path;
        }
        if ($this->containsNamedType($source, ['OperationId', 'ActorRef', 'TenantRef', 'AuditReadPurpose'])) {
            $violations[] = 'ambient-execution-identity:' . $path;
        }
        if (
            !$this->methodReturnsEmptyArray($source, '__debugInfo')
            || !$this->methodReturnsEmptyArray($source, '__serialize')
            || !$this->methodThrowsResourceUnavailable($source, 'current')
            || !$this->methodThrowsResourceUnavailable($source, '__unserialize')
        ) {
            $violations[] = 'execution-hidden-state:' . $path;
        }
        if (
            !str_contains($source, "array_keys(\$value) !== ['identifiers']")
            || !$this->valueHasExactExecutionSnapshotChecks($value)
        ) {
            $violations[] = 'execution-snapshot:' . $path;
        }
        return $violations;
    }

    /** @return array<string,string> */
    private function adapterSources(): array
    {
        $paths = [
            'src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php',
            'src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php',
        ];
        $sources = [];
        foreach ($paths as $path) {
            $source = @file_get_contents(__DIR__ . '/../../' . $path);
            self::assertIsString($source, $path);
            $sources[$path] = $source;
        }
        return $sources;
    }

    private function assertRuntimeDeclaration(ReflectionClass $class, ?string $interface, array $methods): void
    {
        self::assertTrue($class->isFinal(), $class->getName());
        self::assertTrue($class->isReadOnly(), $class->getName());
        self::assertSame($interface === null ? [] : [$interface], $class->getInterfaceNames(), $class->getName());
        self::assertSame([], $class->getAttributes(\BlackOps\Core\Attribute\PublicApi::class), $class->getName());
        self::assertSame(['snapshot'], $this->declaredPropertyNames($class), $class->getName());
        $snapshot = $class->getProperty('snapshot');
        self::assertTrue($snapshot->isPrivate(), $class->getName());
        self::assertFalse($snapshot->isPromoted(), $class->getName());
        self::assertSame('SensitiveParameterValue', $snapshot->getType()?->getName(), $class->getName());
        $actualMethods = [];
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $class->getName()) {
                $actualMethods[$method->getName()] = $method->isPublic()
                    ? 'public'
                    : ($method->isPrivate() ? 'private' : 'other');
            }
        }
        ksort($actualMethods);
        ksort($methods);
        self::assertSame($methods, $actualMethods, $class->getName());
    }

    private function assertCommonHiddenMethods(ReflectionClass $class): void
    {
        $this->assertMethodContract($class->getMethod('__debugInfo'), 'array', []);
        $this->assertMethodContract($class->getMethod('__serialize'), 'array', []);
        $this->assertMethodContract($class->getMethod('__unserialize'), 'void', [['data', 'array', true, false]]);
    }

    /** @param list<array{0:string,1:string,2:bool,3:bool}> $parameters */
    private function assertMethodContract(ReflectionMethod $method, ?string $returnType, array $parameters): void
    {
        if ($returnType === null) {
            self::assertFalse($method->hasReturnType(), $method->getName());
        } else {
            self::assertSame($returnType, $this->reflectionTypeName($method->getReturnType()), $method->getName());
        }
        self::assertCount(count($parameters), $method->getParameters(), $method->getName());
        foreach ($parameters as $index => [$name, $type, $sensitive, $default]) {
            $parameter = $method->getParameters()[$index];
            self::assertSame($name, $parameter->getName(), $method->getName());
            self::assertSame($type, $this->reflectionTypeName($parameter->getType()), $method->getName());
            self::assertSame($sensitive, $this->isSensitive($parameter), $method->getName());
            self::assertSame($default, $parameter->isDefaultValueAvailable(), $method->getName());
            self::assertFalse($parameter->isVariadic(), $method->getName());
            self::assertFalse($parameter->isPassedByReference(), $method->getName());
        }
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

    /** @return list<string> */
    private function declaredPropertyNames(ReflectionClass $class): array
    {
        $names = [];
        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $class->getName()) {
                $names[] = $property->getName();
            }
        }
        sort($names);
        return $names;
    }

    private function classNode(string $source): ?Class_
    {
        foreach (new NodeFinder()->findInstanceOf($this->parse($source), Class_::class) as $node) {
            if ($node instanceof Class_) {
                return $node;
            }
        }
        return null;
    }

    private function methodNode(string $source, string $name): ?ClassMethod
    {
        $class = $this->classNode($source);
        if ($class === null) {
            return null;
        }
        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() === $name) {
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

    /** @return list<MethodCall> */
    private function methodCalls(Node $scope): array
    {
        $calls = new NodeFinder()->findInstanceOf($scope, MethodCall::class);
        return array_values(array_filter($calls, static fn(Node $node): bool => $node instanceof MethodCall));
    }

    /** @param list<MethodCall> $calls @param list<array> $arguments */
    private function countCalls(array $calls, string $receiver, string $name, array $arguments): int
    {
        $count = 0;
        foreach ($this->matchingCalls($calls, $receiver, $name) as $call) {
            if ($this->argumentsMatch($call->args, $arguments)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param list<MethodCall> $calls @return list<MethodCall> */
    private function matchingCalls(array $calls, string $receiver, string $name): array
    {
        return array_values(array_filter(
            $calls,
            fn(MethodCall $call): bool => (
                $this->expressionLabel($call->var) === $receiver
                && $call->name instanceof Identifier
                && $call->name->toString() === $name
            ),
        ));
    }

    private function beginCallMatches(MethodCall $call): bool
    {
        return $this->argumentsMatch($call->args, [
            ['class-constant', 'AuditReadPurpose', 'Diagnostics'],
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
        ]);
    }

    /** @param list<Node\Arg> $args @param list<array> $expected */
    private function argumentsMatch(array $args, array $expected): bool
    {
        if (count($args) !== count($expected)) {
            return false;
        }
        foreach ($args as $index => $argument) {
            $shape = $expected[$index];
            $value = $argument->value;
            if ($shape[0] === 'variable' && (!$value instanceof Variable || $value->name !== $shape[1])) {
                return false;
            }
            if ($shape[0] === 'string' && (!$value instanceof Node\Scalar\String_ || $value->value !== $shape[1])) {
                return false;
            }
            if (
                $shape[0] === 'class-constant'
                && (
                    !$value instanceof ClassConstFetch
                    || $this->terminalName($value->class) !== $shape[1]
                    || !$value->name instanceof Identifier
                    || $value->name->toString() !== $shape[2]
                )
            ) {
                return false;
            }
            if (
                $shape[0] === 'method-call'
                && (
                    !$value instanceof MethodCall
                    || $this->expressionLabel($value->var) !== $shape[1]
                    || !$value->name instanceof Identifier
                    || $value->name->toString() !== $shape[2]
                )
            ) {
                return false;
            }
        }
        return true;
    }

    private function expressionLabel(?Node $expression): ?string
    {
        return $expression instanceof Variable && is_string($expression->name) ? '$' . $expression->name : null;
    }

    private function terminalName(Node|string|null $name): string
    {
        if (is_string($name)) {
            return $name;
        }
        if ($name instanceof Name) {
            return $name->getLast();
        }
        return $name instanceof Identifier ? $name->toString() : '';
    }

    private function newTypeInventory(Node $scope): array
    {
        $types = [];
        foreach (new NodeFinder()->findInstanceOf($scope, New_::class) as $new) {
            $types[] = $this->terminalName($new->class);
        }
        sort($types);
        return $types;
    }

    /** @param list<array> $arguments */
    private function countNew(Node $scope, string $class, array $arguments): int
    {
        $count = 0;
        foreach (new NodeFinder()->findInstanceOf($scope, New_::class) as $new) {
            if ($this->terminalName($new->class) === $class && $this->argumentsMatch($new->args, $arguments)) {
                $count++;
            }
        }
        return $count;
    }

    private function countInstanceof(Node $scope, string $class): int
    {
        $count = 0;
        foreach (new NodeFinder()->findInstanceOf($scope, Instanceof_::class) as $instanceof) {
            if ($this->terminalName($instanceof->class) === $class) {
                $count++;
            }
        }
        return $count;
    }

    private function hasForbiddenCall(string $source): bool
    {
        $class = $this->classNode($source);
        if ($class === null) {
            return false;
        }
        $forbidden = [
            'begintransaction',
            'commit',
            'rollback',
            'savepoint',
            'retry',
            'open',
            'terminal',
            'requested',
            'deliverstandalone',
        ];
        foreach ($this->methodCalls($class) as $call) {
            if (!$call->name instanceof Identifier || in_array(strtolower($call->name->toString()), $forbidden, true)) {
                return true;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($class, StaticCall::class) as $call) {
            if (
                $this->terminalName($call->class) === 'OperationReadAuditSession'
                && $call->name instanceof Identifier
                && strtolower($call->name->toString()) === 'open'
            ) {
                return true;
            }
        }
        return false;
    }

    private function hasOnlyPrivateSnapshot(Class_ $class): bool
    {
        if (count($class->stmts) < 1) {
            return false;
        }
        foreach ($class->stmts as $statement) {
            if (!$statement instanceof Node\Stmt\Property && !$statement instanceof ClassMethod) {
                return false;
            }
        }
        $properties = $class->getProperties();
        return (
            count($properties) === 1
            && $properties[0]->isPrivate()
            && count($properties[0]->props) === 1
            && $properties[0]->props[0]->name->toString() === 'snapshot'
            && $this->terminalName($properties[0]->type) === 'SensitiveParameterValue'
        );
    }

    private function hasPublicApiAttribute(Class_ $class): bool
    {
        foreach (new NodeFinder()->findInstanceOf($class, Node\Attribute::class) as $attribute) {
            if ($this->terminalName($attribute->name) === 'PublicApi') {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private function sensitiveParameterViolations(Class_ $class, string $path): array
    {
        $violations = [];
        foreach ($class->getMethods() as $method) {
            foreach ($method->params as $parameter) {
                $count = 0;
                foreach ($parameter->attrGroups as $group) {
                    foreach ($group->attrs as $attribute) {
                        if ($this->terminalName($attribute->name) === 'SensitiveParameter') {
                            $count++;
                        }
                    }
                }
                if ($count !== 1) {
                    $violations[] = 'sensitive-parameter:' . $path . ':' . $method->name->toString();
                }
            }
        }
        return $violations;
    }

    /** @param list<MethodCall> $authorizer @param list<MethodCall> $query */
    private function globalScopeGuardIsExact(ClassMethod $method, array $authorizer, array $query): bool
    {
        $guards = [];
        foreach (new NodeFinder()->findInstanceOf($method, If_::class) as $if) {
            if (
                $if->cond instanceof NotIdentical
                && $if->cond->left instanceof Variable
                && $if->cond->left->name === 'currentTenant'
                && $this->isNullConstant($if->cond->right)
            ) {
                $guards[] = $if;
            }
        }
        if (count($guards) !== 1 || $guards[0]->else === null || count($authorizer) !== 1 || count($query) !== 1) {
            return false;
        }
        $guard = $guards[0];
        $unavailable = new NodeFinder()->findInstanceOf($guard->stmts, New_::class);
        $hasUnavailable = false;
        foreach ($unavailable as $new) {
            if ($this->terminalName($new->class) === 'OperationDiagnosticsUnavailable') {
                $hasUnavailable = true;
            }
        }
        $elseCalls = $this->methodCalls($guard->else);
        return (
            $hasUnavailable
            && $this->containsMatchingCall($elseCalls, $authorizer[0])
            && $guard->getStartFilePos() < $authorizer[0]->getStartFilePos()
            && $guard->getStartFilePos() < $query[0]->getStartFilePos()
        );
    }

    private function authorizationCallMatches(MethodCall $call): bool
    {
        if (!$call->name instanceof Identifier || $call->name->toString() !== 'decide' || count($call->args) !== 1) {
            return false;
        }
        $argument = $call->args[0]->value;
        return (
            $argument instanceof New_
            && $this->terminalName($argument->class) === 'OperationDiagnosticsAuthorizationRequest'
            && $this->argumentsMatch($argument->args, [
                ['variable', 'operationId'],
                ['variable', 'currentActor'],
                ['variable', 'currentTenant'],
            ])
        );
    }

    private function isNullConstant(Node $expression): bool
    {
        return $expression instanceof ConstFetch && strtolower($this->terminalName($expression->name)) === 'null';
    }

    /** @param list<MethodCall> $query */
    private function authorizationDecisionIsExact(ClassMethod $method, array $query): bool
    {
        if ($query === []) {
            return false;
        }
        $matches = [];
        foreach (new NodeFinder()->findInstanceOf($method, If_::class) as $if) {
            if (
                !$if->cond instanceof NotIdentical
                || $this->expressionLabel($if->cond->left) !== '$decision'
                || !$if->cond->right instanceof ClassConstFetch
                || $this->terminalName($if->cond->right->class) !== 'AuditReadAuthorizationDecision'
                || !$if->cond->right->name instanceof Identifier
                || $if->cond->right->name->toString() !== 'Allow'
            ) {
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($if->stmts, New_::class) as $new) {
                if ($this->terminalName($new->class) === 'OperationDiagnosticsUnavailable') {
                    $matches[] = $if;
                    break;
                }
            }
        }
        return count($matches) === 1 && $matches[0]->getStartFilePos() < $query[0]->getStartFilePos();
    }

    private function containsMatchingCall(array $calls, ?MethodCall $expected): bool
    {
        if ($expected === null) {
            return false;
        }
        foreach ($calls as $call) {
            if ($call->getStartFilePos() === $expected->getStartFilePos()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<MethodCall> $begin
     * @param list<MethodCall> $authorizer
     * @param list<MethodCall> $query
     * @param list<MethodCall> $idle
     * @param list<MethodCall> $accessed
     * @param list<MethodCall> $unavailable
     */
    private function phaseOrderIsExact(
        array $begin,
        array $authorizer,
        array $query,
        array $idle,
        array $accessed,
        array $unavailable,
    ): bool {
        if (
            count($begin) !== 1
            || count($authorizer) !== 1
            || count($query) !== 1
            || count($idle) !== 5
            || count($accessed) !== 1
            || count($unavailable) !== 1
        ) {
            return false;
        }
        $beginPosition = $begin[0]->getStartFilePos();
        $authorizerPosition = $authorizer[0]->getStartFilePos();
        $queryPosition = $query[0]->getStartFilePos();
        $terminalPosition = max($accessed[0]->getStartFilePos(), $unavailable[0]->getStartFilePos());
        $idlePositions = array_map(static fn(MethodCall $call): int => $call->getStartFilePos(), $idle);
        sort($idlePositions);
        return (
            $idlePositions[0] < $beginPosition
            && $idlePositions[1] > $beginPosition
            && $idlePositions[1] < $authorizerPosition
            && $idlePositions[2] > $authorizerPosition
            && $idlePositions[2] < $queryPosition
            && $idlePositions[3] > $queryPosition
            && $idlePositions[3] < $terminalPosition
            && $idlePositions[4] > $terminalPosition
        );
    }

    /** @param list<MethodCall> $accessed @param list<MethodCall> $unavailable @param list<MethodCall> $query */
    private function terminalTryIsExact(ClassMethod $method, array $accessed, array $unavailable, array $query): bool
    {
        if (count($accessed) !== 1 || count($unavailable) !== 1 || count($query) !== 1) {
            return false;
        }
        $terminalTry = null;
        $queryTry = null;
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], TryCatch::class) as $try) {
            $calls = $this->methodCalls($try);
            if (
                $this->containsMatchingCall($calls, $accessed[0])
                || $this->containsMatchingCall($calls, $unavailable[0])
            ) {
                $terminalTry = $try;
            }
            if ($this->containsMatchingCall($calls, $query[0])) {
                $queryTry = $try;
            }
        }
        if ($terminalTry === null || $queryTry === null || $terminalTry === $queryTry) {
            return false;
        }
        return (
            count($terminalTry->catches) === 1
            && $this->catchIsThrowable($terminalTry->catches[0])
            && $terminalTry->finally === null
            && $terminalTry->getStartFilePos() > $queryTry->getStartFilePos()
        );
    }

    private function unexpectedResultClassificationIsExact(ClassMethod $method): bool
    {
        $matches = 0;
        foreach (new NodeFinder()->findInstanceOf($method, If_::class) as $if) {
            if (!$if->cond instanceof BooleanOr || $if->else === null) {
                continue;
            }
            $types = [];
            foreach ([$if->cond->left, $if->cond->right] as $condition) {
                if (
                    !$condition instanceof Instanceof_
                    || $this->expressionLabel($condition->expr) !== '$candidate'
                    || !in_array(
                        $this->terminalName($condition->class),
                        ['OperationDiagnosticsFound', 'OperationDiagnosticsUnavailable'],
                        true,
                    )
                ) {
                    $types = [];
                    break;
                }
                $types[] = $this->terminalName($condition->class);
            }
            sort($types);
            $expected = ['OperationDiagnosticsFound', 'OperationDiagnosticsUnavailable'];
            sort($expected);
            if ($types !== $expected || !$this->hasAssignment($if->stmts, '$result', '$candidate')) {
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($if->else, Assign::class) as $assignment) {
                if (
                    $this->expressionLabel($assignment->var) === '$failure'
                    && $assignment->expr instanceof ClassConstFetch
                    && $this->terminalName($assignment->expr->class) === 'DiagnosticsFailureCode'
                    && $assignment->expr->name instanceof Identifier
                    && $assignment->expr->name->toString() === 'IntegrityFailed'
                ) {
                    $matches++;
                }
            }
        }
        return $matches === 1;
    }

    private function failureRetentionIsEnumOnly(ClassMethod $method): bool
    {
        $codeReads = 0;
        $codeReadPosition = null;
        $exceptionCatch = null;
        foreach (new NodeFinder()->findInstanceOf($method, Catch_::class) as $catch) {
            if (
                count($catch->types) === 1
                && $this->terminalName($catch->types[0]) === 'OperationDiagnosticsException'
            ) {
                $exceptionCatch = $catch;
            }
        }
        if ($exceptionCatch === null) {
            return false;
        }
        foreach (new NodeFinder()->findInstanceOf($exceptionCatch, PropertyFetch::class) as $fetch) {
            if (
                $this->expressionLabel($fetch->var) === '$exception'
                && $fetch->name instanceof Identifier
                && $fetch->name->toString() === 'diagnosticsCode'
            ) {
                $codeReads++;
                $codeReadPosition = $fetch->getStartFilePos();
            }
        }
        foreach (new NodeFinder()->findInstanceOf($method, Assign::class) as $assignment) {
            if (
                $this->expressionLabel($assignment->var) === '$failure'
                && $this->expressionLabel($assignment->expr) === '$exception'
            ) {
                return false;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($method, Throw_::class) as $throw) {
            if ($this->expressionLabel($throw->expr) === '$exception') {
                return false;
            }
        }
        $exceptionUnsets = [];
        foreach (new NodeFinder()->findInstanceOf($exceptionCatch, Unset_::class) as $unset) {
            foreach ($unset->vars as $variable) {
                if ($this->expressionLabel($variable) === '$exception') {
                    $exceptionUnsets[] = $unset;
                }
            }
        }
        return (
            $codeReads === 1
            && $codeReadPosition !== null
            && count($exceptionUnsets) === 1
            && $exceptionUnsets[0]->getStartFilePos() > $codeReadPosition
        );
    }

    /** @param list<MethodCall> $accessed @param list<MethodCall> $unavailable */
    private function freshFailureFactoriesFollowTerminal(ClassMethod $method, array $accessed, array $unavailable): bool
    {
        if ($accessed === [] || $unavailable === []) {
            return false;
        }
        $terminal = max($accessed[0]->getStartFilePos(), $unavailable[0]->getStartFilePos());
        $found = [];
        foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
            if (
                $this->terminalName($call->class) === 'OperationDiagnosticsException'
                && $call->name instanceof Identifier
                && in_array($call->name->toString(), ['storageFailed', 'decodeFailed', 'integrityFailed'], true)
                && $call->getStartFilePos() > $terminal
            ) {
                $found[$call->name->toString()] = true;
            }
        }
        return count(array_diff(['storageFailed', 'decodeFailed', 'integrityFailed'], array_keys($found))) === 0;
    }

    /** @param list<MethodCall> $accessed @param list<MethodCall> $unavailable */
    private function releaseFollowsTerminal(ClassMethod $method, array $accessed, array $unavailable): bool
    {
        if ($accessed === [] || $unavailable === []) {
            return false;
        }
        $terminal = max($accessed[0]->getStartFilePos(), $unavailable[0]->getStartFilePos());
        foreach (new NodeFinder()->findInstanceOf($method, Return_::class) as $return) {
            if ($this->expressionLabel($return->expr) === '$result' && $return->getStartFilePos() <= $terminal) {
                return false;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($method, Throw_::class) as $throw) {
            if ($throw->expr instanceof Node\Expr\Match_ && $throw->getStartFilePos() <= $terminal) {
                return false;
            }
        }
        return true;
    }

    private function valueHasExactSnapshotChecks(ClassMethod $value): bool
    {
        return (
            $this->countInstanceof($value, 'OperationDiagnosticsQuery') === 1
            && $this->countInstanceof($value, 'OperationDiagnosticsAuthorizer') === 1
            && $this->countInstanceof($value, 'OperationReadAuditSessionFactory') === 1
            && $this->countInstanceof($value, 'Connection') === 1
        );
    }

    private function valueHasExactExecutionSnapshotChecks(ClassMethod $value): bool
    {
        return $this->countInstanceof($value, 'ValidatedUuidv7Generator') === 1;
    }

    private function containsNamedType(string $source, array $names): bool
    {
        foreach (new NodeFinder()->findInstanceOf($this->parse($source), Name::class) as $name) {
            if (in_array($this->terminalName($name), $names, true)) {
                return true;
            }
        }
        return false;
    }

    private function methodThrowsFreshStorageFailure(string $source, string $name): bool
    {
        $method = $this->methodNode($source, $name);
        if ($method === null) {
            return false;
        }
        foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
            if (
                $this->terminalName($call->class) === 'OperationDiagnosticsException'
                && $call->name instanceof Identifier
                && $call->name->toString() === 'storageFailed'
            ) {
                return true;
            }
        }
        return false;
    }

    private function methodThrowsResourceUnavailable(string $source, string $name): bool
    {
        $method = $this->methodNode($source, $name);
        if ($method === null) {
            return false;
        }
        foreach (new NodeFinder()->findInstanceOf($method, ClassConstFetch::class) as $fetch) {
            if (
                $this->terminalName($fetch->class) === 'AuditDeliveryFailure'
                && $fetch->name instanceof Identifier
                && $fetch->name->toString() === 'ResourceUnavailable'
            ) {
                return true;
            }
        }
        return false;
    }

    private function methodReturnsEmptyArray(string $source, string $name): bool
    {
        $method = $this->methodNode($source, $name);
        if ($method === null || count($method->stmts ?? []) !== 1 || !$method->stmts[0] instanceof Return_) {
            return false;
        }
        $return = $method->stmts[0];
        return $return->expr instanceof Node\Expr\Array_ && $return->expr->items === [];
    }

    /** @param list<Node> $statements */
    private function hasAssignment(array $statements, string $variable, string $value): bool
    {
        foreach (new NodeFinder()->findInstanceOf($statements, Assign::class) as $assignment) {
            if (
                $this->expressionLabel($assignment->var) === $variable
                && $this->expressionLabel($assignment->expr) === $value
            ) {
                return true;
            }
        }
        return false;
    }

    private function catchIsThrowable(Node $catch): bool
    {
        return (
            $catch instanceof Catch_
            && count($catch->types) === 1
            && $this->terminalName($catch->types[0]) === 'Throwable'
            && $catch->var === null
        );
    }

    private function assertMutationSyntax(array $candidate, string $name): void
    {
        foreach ($candidate as $path => $source) {
            self::assertStringStartsWith('<?php', $source, $name . ':' . $path);
            try {
                \PhpToken::tokenize($source, TOKEN_PARSE);
            } catch (\ParseError $error) {
                self::fail($name . ':' . $path . ' parse error: ' . $error->getMessage());
            }
        }
    }

    private function replaceOnce(string $source, string $needle, string $replacement): string
    {
        self::assertSame(1, substr_count($source, $needle), 'mutation marker count for ' . $needle);
        return str_replace($needle, $replacement, $source);
    }
}
