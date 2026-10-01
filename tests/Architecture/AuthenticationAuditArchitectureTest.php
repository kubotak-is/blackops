<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Internal\Audit\Authentication\AuditedHttpAuthenticator;
use BlackOps\Internal\Audit\Authentication\AuthenticationAuditEventWriter;
use BlackOps\Internal\Audit\Authentication\DurableAuthenticationAuditEventWriter;
use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_ as StringNode;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Return_;
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
 * Checks the latent HTTP authentication result audit graph at its Internal boundary.
 *
 * The source oracle parses only the three adapter files for graph rules. A
 * separate production scan keeps the graph closed until an explicit parent
 * composition selects a caller.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuthenticationAuditArchitectureTest extends TestCase
{
    public function testExactDeclarationsPropertiesAndSensitiveSurfaces(): void
    {
        $writer = new ReflectionClass(AuthenticationAuditEventWriter::class);
        self::assertTrue($writer->isInterface());
        self::assertSame([], $writer->getAttributes(\BlackOps\Core\Attribute\PublicApi::class));
        self::assertSame(['write'], $this->declaredMethodNames($writer));
        $this->assertMethodContract(
            $writer->getMethod('write'),
            'void',
            [['result', 'AuthenticationResult', true, false]],
        );

        $this->assertConcreteDeclaration(
            new ReflectionClass(DurableAuthenticationAuditEventWriter::class),
            [AuthenticationAuditEventWriter::class],
            ['__construct', 'write', '__debugInfo', '__serialize', '__unserialize'],
        );
        $durable = new ReflectionClass(DurableAuthenticationAuditEventWriter::class);
        $this->assertSnapshotProperty($durable);
        $this->assertMethodContract(
            $durable->getMethod('__construct'),
            null,
            [
                ['delivery',   'AuditDeliveryCoordinator', true, false],
                ['opaqueIds',  'HmacAuditOpaqueIdDeriver', true, false],
                ['executions', 'Uuidv7Generator',          true, false],
                ['clock',      'ClockInterface',           true, false],
                ['buildId',    'string',                   true, false],
            ],
        );
        $this->assertMethodContract(
            $durable->getMethod('write'),
            'void',
            [['result', 'AuthenticationResult', true, false]],
        );
        $this->assertHiddenMethods($durable);

        $this->assertConcreteDeclaration(
            new ReflectionClass(AuditedHttpAuthenticator::class),
            [HttpAuthenticator::class],
            ['__construct', 'authenticate', '__debugInfo', '__serialize', '__unserialize'],
        );
        $adapter = new ReflectionClass(AuditedHttpAuthenticator::class);
        $this->assertSnapshotProperty($adapter);
        $this->assertMethodContract(
            $adapter->getMethod('__construct'),
            null,
            [
                ['authenticator', 'HttpAuthenticator',              true, false],
                ['writer',        'AuthenticationAuditEventWriter', true, false],
                ['connection',    'Connection',                     true, false],
            ],
        );
        $this->assertMethodContract(
            $adapter->getMethod('authenticate'),
            'AuthenticationResult',
            [['request', 'ServerRequestInterface', true, false]],
        );
        $this->assertHiddenMethods($adapter);
    }

    public function testTokenAwareBoundariesAndCallFlowsAreExact(): void
    {
        self::assertSame([], $this->architectureViolations($this->adapterSources()));
    }

    public function testBoundedMutationsRejectArchitectureDriftAndIgnoreInertText(): void
    {
        $sources = $this->adapterSources();
        $inert = $sources;
        $inert['src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php'] .= <<<'PHP'

            // $request->getHeaderLine('Authorization'); $delivery->deliverInCurrentTransaction($request);
            $text = 'AuthenticationResult::code provider token body';
            PHP;
        self::assertSame([], $this->architectureViolations($inert));

        $interfacePath = 'src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php';
        $writerPath = 'src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php';
        $adapterPath = 'src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php';
        $mutations = [
            'interface result loses sensitive marker' => [
                $interfacePath => $this->replaceOnce(
                    $sources[$interfacePath],
                    '#[\\SensitiveParameter] AuthenticationResult $result',
                    'AuthenticationResult $result',
                ),
            ],
            'durable remap exception loses sensitive marker' => [
                $writerPath => $this->removeSensitiveParameterAttribute(
                    $sources[$writerPath],
                    'remap',
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                ),
            ],
            'durable implementation loses final modifier' => [
                $writerPath => $this->replaceOnce(
                    $sources[$writerPath],
                    'final class DurableAuthenticationAuditEventWriter',
                    'class DurableAuthenticationAuditEventWriter',
                ),
            ],
            'durable delivery changes to mutation mode' => [
                $writerPath => $this->replaceOnce(
                    $sources[$writerPath],
                    'deliverStandalone(',
                    'deliverInCurrentTransaction(',
                ),
            ],
            'durable writer reads the invalid result code' => [
                $writerPath => $this->insertBefore(
                    $sources[$writerPath],
                    '$state = $this->state($result);',
                    "\$result->code();\n            ",
                ),
            ],
            'durable writer reaches a store directly' => [
                $writerPath => $this->insertBefore(
                    $sources[$writerPath],
                    '$delivery->deliverStandalone($request);',
                    "\$records->appendRequired(\$request);\n            ",
                ),
            ],
            'adapter returns before audit delivery' => [
                $adapterPath => $this->insertBefore(
                    $sources[$adapterPath],
                    "try {\n            \$writer->write(\$result);",
                    "return \$result;\n        ",
                ),
            ],
            'adapter writes the anonymous result' => [
                $adapterPath => $this->replaceOnce(
                    $sources[$adapterPath],
                    "if (\$state === self::ANONYMOUS) {\n            return \$result;",
                    "if (\$state === self::ANONYMOUS) {\n            \$writer->write(\$result);\n            return \$result;",
                ),
            ],
            'adapter reads a request header' => [
                $adapterPath => $this->insertBefore(
                    $sources[$adapterPath],
                    '$result = $authenticator->authenticate($request);',
                    "\$request->getHeaderLine('Authorization');\n            ",
                ),
            ],
            'adapter remap exception loses sensitive marker' => [
                $adapterPath => $this->removeSensitiveParameterAttribute(
                    $sources[$adapterPath],
                    'remap',
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                ),
            ],
            'adapter loses final modifier' => [
                $adapterPath => $this->replaceOnce(
                    $sources[$adapterPath],
                    'final class AuditedHttpAuthenticator',
                    'class AuditedHttpAuthenticator',
                ),
            ],
            'adapter stores extra state' => [
                $adapterPath => $this->replaceOnce(
                    $sources[$adapterPath],
                    'private \\SensitiveParameterValue $snapshot;',
                    "private \\SensitiveParameterValue \$snapshot;\n    private string \$unexpected;",
                ),
            ],
        ];
        $expectedViolations = [
            'interface result loses sensitive marker' => 'sensitive:' . $interfacePath . ':write',
            'durable remap exception loses sensitive marker' => 'sensitive:' . $writerPath . ':remap',
            'durable implementation loses final modifier' => 'closed-class:' . $writerPath,
            'durable delivery changes to mutation mode' => 'writer-current-transaction-delivery',
            'durable writer reads the invalid result code' => 'writer-code-or-provider-inference',
            'durable writer reaches a store directly' => 'writer-code-or-provider-inference',
            'adapter returns before audit delivery' => 'adapter-release-order',
            'adapter writes the anonymous result' => 'adapter-single-writer',
            'adapter reads a request header' => 'adapter-request-or-code-read',
            'adapter remap exception loses sensitive marker' => 'sensitive:' . $adapterPath . ':remap',
            'adapter loses final modifier' => 'closed-class:' . $adapterPath,
            'adapter stores extra state' => 'snapshot:' . $adapterPath,
        ];
        foreach ($mutations as $name => $mutation) {
            $mutated = [...$sources, ...$mutation];
            self::assertNotSame($sources, $mutated, $name);
            $violations = $this->architectureViolations($mutated);
            self::assertSame(
                [],
                array_values(array_filter($violations, static fn(string $violation): bool => str_starts_with(
                    $violation,
                    'syntax:',
                ))),
                $name . ' must remain valid PHP',
            );
            self::assertArrayHasKey($name, $expectedViolations);
            self::assertContains($expectedViolations[$name], $violations, $name);
        }
    }

    public function testProductionGraphIsClosedAndUnactivated(): void
    {
        $sources = $this->productionSources();
        $targets = [
            'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter',
            'BlackOps\\Internal\\Audit\\Authentication\\DurableAuthenticationAuditEventWriter',
            'BlackOps\\Internal\\Audit\\Authentication\\AuditedHttpAuthenticator',
        ];
        $violations = [];
        $implementations = [];
        foreach ($sources as $path => $source) {
            $nodes = $this->parse($source);
            foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
                foreach ($class->implements as $interface) {
                    if ($this->terminalName($interface) === 'AuthenticationAuditEventWriter') {
                        $implementations[] = $path;
                    }
                }
            }
            if (in_array($path, array_keys($this->adapterSources()), true)) {
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
                if (
                    $this->resolvedNodeName($new->class) !== null
                    && in_array($this->resolvedNodeName($new->class), $targets, true)
                ) {
                    $violations[] = 'constructor:' . $path;
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
                if (in_array($this->resolvedNodeName($name), $targets, true)) {
                    $violations[] = 'reference:' . $path;
                }
            }
        }
        sort($implementations);
        if ($implementations !== ['src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php']) {
            $violations[] = 'sole-implementation';
        }
        self::assertSame([], array_values(array_unique($violations)));
    }

    /** @param array<string,string> $sources @return list<string> */
    private function architectureViolations(array $sources): array
    {
        $expectedPaths = array_keys($this->adapterDefinitions());
        $actualPaths = array_keys($sources);
        sort($expectedPaths);
        sort($actualPaths);
        $violations = $expectedPaths === $actualPaths ? [] : ['source-path-inventory'];
        $nodes = [];
        foreach ($this->adapterDefinitions() as $path => $definition) {
            if (!isset($sources[$path])) {
                $violations[] = 'missing:' . $path;
                continue;
            }
            try {
                $nodes[$path] = $this->parse($sources[$path]);
            } catch (\Throwable) {
                $violations[] = 'syntax:' . $path;
            }
            if (!isset($nodes[$path])) {
                continue;
            }
            $classLike = $this->classLikeNode($nodes[$path]);
            if ($classLike === null || $this->terminalName($classLike->name) !== $definition['name']) {
                $violations[] = 'declaration:' . $path;
                continue;
            }
            if ($definition['kind'] === 'interface' && !$classLike instanceof Interface_) {
                $violations[] = 'interface:' . $path;
            }
            if ($definition['kind'] === 'class' && !$classLike instanceof Class_) {
                $violations[] = 'class:' . $path;
            }
            if ($this->hasPublicApiAttribute($classLike)) {
                $violations[] = 'public-api:' . $path;
            }
            if ($classLike instanceof Class_) {
                if (!$classLike->isFinal() || $classLike->extends !== null) {
                    $violations[] = 'closed-class:' . $path;
                }
                $interfaces = array_map(fn(Name $name): string => $this->terminalName($name), $classLike->implements);
                if ($interfaces !== $definition['implements']) {
                    $violations[] = 'implements:' . $path;
                }
                if ($definition['kind'] === 'class' && !$this->hasOnlySnapshotProperty($classLike)) {
                    $violations[] = 'snapshot:' . $path;
                }
            }
            $methods = [];
            foreach ($classLike->getMethods() as $method) {
                if (!$method->isPublic() && !$method->isProtected()) {
                    continue;
                }
                $methods[$method->name->toString()] = $method->isPublic() ? 'public' : 'other';
            }
            ksort($methods);
            $expectedMethods = $definition['methods'];
            ksort($expectedMethods);
            if ($methods !== $expectedMethods) {
                $violations[] = 'methods:' . $path;
            }
            $violations = [...$violations, ...$this->sensitiveSourceViolations($classLike, $path)];
        }
        if (
            isset($nodes[$writerPath = 'src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php'])
        ) {
            $violations = [...$violations, ...$this->writerViolations($nodes[$writerPath])];
        }
        if (isset($nodes[$adapterPath = 'src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php'])) {
            $violations = [...$violations, ...$this->adapterViolations($nodes[$adapterPath])];
        }

        return array_values(array_unique($violations));
    }

    /** @return array<string,array{kind:string,name:string,implements:list<string>,methods:array<string,string>}> */
    private function adapterDefinitions(): array
    {
        return [
            'src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php' => [
                'kind' => 'interface',
                'name' => 'AuthenticationAuditEventWriter',
                'implements' => [],
                'methods' => ['write' => 'public'],
            ],
            'src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php' => [
                'kind' => 'class',
                'name' => 'DurableAuthenticationAuditEventWriter',
                'implements' => ['AuthenticationAuditEventWriter'],
                'methods' => [
                    '__construct' => 'public',
                    'write' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                ],
            ],
            'src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php' => [
                'kind' => 'class',
                'name' => 'AuditedHttpAuthenticator',
                'implements' => ['HttpAuthenticator'],
                'methods' => [
                    '__construct' => 'public',
                    'authenticate' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                ],
            ],
        ];
    }

    /** @return list<string> */
    private function sensitiveSourceViolations(Node $classLike, string $path): array
    {
        $violations = [];
        foreach ($classLike->getMethods() as $method) {
            foreach ($method->params as $parameter) {
                $count = 0;
                foreach ($parameter->attrGroups as $group) {
                    foreach ($group->attrs as $attribute) {
                        if ($this->terminalName($attribute->name) === 'SensitiveParameter') {
                            $count++;
                        }
                    }
                }
                if ($this->parameterNeedsSensitivity($parameter->var->name, $parameter->type) && $count !== 1) {
                    $violations[] = 'sensitive:' . $path . ':' . $method->name->toString();
                }
            }
        }
        return $violations;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function writerViolations(array $nodes): array
    {
        $violations = [];
        $method = $this->methodNode($nodes, 'write');
        $constructor = $this->methodNode($nodes, '__construct');
        if ($method === null || $constructor === null) {
            return ['writer-methods'];
        }
        if ($this->snapshotKeys($nodes) !== ['delivery', 'opaqueIds', 'executions', 'clock', 'buildId']) {
            $violations[] = 'writer-snapshot-keys';
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'writer-constructor-io';
        }
        $calls = $this->methodCalls($method);
        $allCalls = $this->methodCalls($nodes);
        $deliveryCalls = $this->callsNamed($calls, 'deliverStandalone');
        if (count($deliveryCalls) !== 1 || $this->expressionLabel($deliveryCalls[0]->var) !== '$delivery') {
            $violations[] = 'writer-standalone-delivery';
        }
        if ($this->callsNamed($calls, 'deliverInCurrentTransaction') !== []) {
            $violations[] = 'writer-current-transaction-delivery';
        }
        if (
            $this->countNew($nodes, 'AuditDeliveryRequest') !== 1
            || $this->countNew($nodes, 'AuditRecordDraft') !== 1
        ) {
            $violations[] = 'writer-draft-request-count';
        }
        if ($this->countCallsFor($allCalls, '$executions', 'generate') !== 1) {
            $violations[] = 'writer-single-execution-id';
        }
        if ($this->countCallsFor($allCalls, '$clock', 'now') !== 1) {
            $violations[] = 'writer-single-clock';
        }
        $opaqueCalls = array_values(array_filter(
            $allCalls,
            fn(MethodCall $call): bool => (
                $this->expressionLabel($call->var) === '$opaqueIds'
                && $call->name instanceof Identifier
                && in_array($call->name->toString(), ['derive', 'deriveScalar'], true)
            ),
        ));
        if (count($opaqueCalls) !== 2 || !$this->opaquePurposesAreActionAndResource($opaqueCalls)) {
            $violations[] = 'writer-purpose-separated-opaque-ids';
        }
        if (!$this->callsNamed($allCalls, 'isAnonymous')) {
            $violations[] = 'writer-anonymous-rejection';
        }
        foreach ($allCalls as $call) {
            if (!$call->name instanceof Identifier) {
                continue;
            }
            if (in_array(strtolower($call->name->toString()), $this->forbiddenMethodNames(), true)) {
                $violations[] = 'writer-forbidden-call:' . $call->name->toString();
            }
        }
        if (
            $this->containsResultCodeRead($nodes)
            || $this->containsForbiddenType($nodes)
            || $this->hasForbiddenReceiver($allCalls, ['$store', '$records', '$outbox', '$relay', '$transaction'])
        ) {
            $violations[] = 'writer-code-or-provider-inference';
        }
        if (!$this->requiredStringsPresent($nodes, [
            'security.authentication.succeeded',
            'security.authentication.invalid',
            'http_authentication',
            'authentication',
            'http-authentication',
            'authentication_invalid',
        ])) {
            $violations[] = 'writer-fixed-draft-values';
        }
        if ($this->hasRawThrowableThrow($nodes)) {
            $violations[] = 'writer-raw-throwable';
        }
        if (!$this->hiddenStateIsClosed($nodes)) {
            $violations[] = 'writer-hidden-state';
        }

        return $violations;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function adapterViolations(array $nodes): array
    {
        $violations = [];
        $method = $this->methodNode($nodes, 'authenticate');
        $constructor = $this->methodNode($nodes, '__construct');
        if ($method === null || $constructor === null) {
            return ['adapter-methods'];
        }
        if ($this->snapshotKeys($nodes) !== ['authenticator', 'writer', 'connection']) {
            $violations[] = 'adapter-snapshot-keys';
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'adapter-constructor-io';
        }
        $calls = $this->methodCalls($method);
        $delegateCalls = array_values(array_filter(
            $calls,
            fn(MethodCall $call): bool => (
                $this->expressionLabel($call->var) === '$authenticator'
                && $call->name instanceof Identifier
                && $call->name->toString() === 'authenticate'
            ),
        ));
        if (count($delegateCalls) !== 1 || !$this->argumentsMatch($delegateCalls[0]->args, [['variable', 'request']])) {
            $violations[] = 'adapter-single-delegate';
        }
        $writerCalls = array_values(array_filter(
            $calls,
            fn(MethodCall $call): bool => (
                $this->expressionLabel($call->var) === '$writer'
                && $call->name instanceof Identifier
                && $call->name->toString() === 'write'
            ),
        ));
        if (
            count($writerCalls) !== 2
            || array_filter($writerCalls, fn(MethodCall $call): bool => !$this->argumentsMatch($call->args, [[
                'variable',
                'result',
            ]])) !== []
        ) {
            $violations[] = 'adapter-single-writer';
        }
        $anonymous = $this->callsNamed($this->methodCalls($nodes), 'isAnonymous');
        if (count($anonymous) !== 1 || !$this->stateBranchReturnsSameResult($method, 'ANONYMOUS')) {
            $violations[] = 'adapter-anonymous-bypass';
        }
        if ($this->containsRequestAccess($method) || $this->containsResultCodeRead($method)) {
            $violations[] = 'adapter-request-or-code-read';
        }
        if (
            $this->containsForbiddenType($nodes)
            || $this->containsTypeName($nodes, 'Uuidv7Generator')
            || $this->hasForbiddenMethod($calls)
        ) {
            $violations[] = 'adapter-forbidden-dependency';
        }
        if (
            $delegateCalls !== []
            && $writerCalls !== []
            && !$this->releaseOrderIsExact($method, $delegateCalls[0], $writerCalls)
        ) {
            $violations[] = 'adapter-release-order';
        }
        if (!$this->hasAtLeastThreeIdleChecks($method)) {
            $violations[] = 'adapter-idle-checks';
        }
        if ($this->hasRawThrowableThrow($nodes)) {
            $violations[] = 'adapter-raw-throwable';
        }
        if (!$this->hiddenStateIsClosed($nodes)) {
            $violations[] = 'adapter-hidden-state';
        }

        return $violations;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function snapshotKeys(array $nodes): ?array
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if ($this->terminalName($new->class) !== 'SensitiveParameterValue' || count($new->args) !== 1) {
                continue;
            }
            $value = $new->args[0]->value;
            if (!$value instanceof Array_) {
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

    private function hasOnlySnapshotProperty(Class_ $class): bool
    {
        $properties = $class->getProperties();
        if (count($properties) !== 1 || !$properties[0]->isPrivate()) {
            return false;
        }
        if (count($properties[0]->props) !== 1 || $properties[0]->props[0]->name->toString() !== 'snapshot') {
            return false;
        }

        return $this->terminalName($properties[0]->type) === 'SensitiveParameterValue';
    }

    private function assertSnapshotProperty(ReflectionClass $class): void
    {
        self::assertSame(['snapshot'], $this->declaredPropertyNames($class), $class->getName());
        $snapshot = $class->getProperty('snapshot');
        self::assertTrue($snapshot->isPrivate(), $class->getName());
        self::assertFalse($snapshot->isPromoted(), $class->getName());
        self::assertSame('SensitiveParameterValue', $snapshot->getType()?->getName(), $class->getName());
    }

    /** @param list<string> $interfaces @param array<string,string> $methods */
    private function assertConcreteDeclaration(ReflectionClass $class, array $interfaces, array $methods): void
    {
        self::assertTrue($class->isFinal(), $class->getName());
        self::assertSame([], $class->getAttributes(\BlackOps\Core\Attribute\PublicApi::class), $class->getName());
        self::assertSame($interfaces, $class->getInterfaceNames(), $class->getName());
        $actual = [];
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            if (!$method->isPublic() && !$method->isProtected()) {
                continue;
            }
            $actual[$method->getName()] = $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'other');
        }
        $expected = array_fill_keys($methods, 'public');
        ksort($actual);
        ksort($expected);
        self::assertSame($expected, $actual, $class->getName());
    }

    private function assertHiddenMethods(ReflectionClass $class): void
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

    private function parameterNeedsSensitivity(string $name, Node|string|null $type): bool
    {
        return (
            in_array(
                $name,
                [
                    'result',
                    'request',
                    'delivery',
                    'opaqueIds',
                    'executions',
                    'clock',
                    'buildId',
                    'authenticator',
                    'writer',
                    'connection',
                    'executionId',
                    'actor',
                    'tenant',
                    'actionId',
                    'resourceId',
                    'occurredAt',
                    'data',
                    'key',
                ],
                true,
            )
            || in_array($this->resolvedNodeName($type), $this->sensitiveParameterTypes(), true)
            || in_array(
                $this->terminalName($type),
                array_map(static fn(string $type): string => basename(str_replace(
                    '\\',
                    '/',
                    $type,
                )), $this->sensitiveParameterTypes()),
                true,
            )
        );
    }

    /** @return list<string> */
    private function sensitiveParameterTypes(): array
    {
        return [
            'BlackOps\\Http\\Authentication\\AuthenticationResult',
            'Psr\\Http\\Message\\ServerRequestInterface',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
            'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
            'BlackOps\\Identifier\\Uuidv7Generator',
            'Psr\\Clock\\ClockInterface',
            'BlackOps\\Http\\Authentication\\HttpAuthenticator',
            'BlackOps\\Internal\\Audit\\Authentication\\AuthenticationAuditEventWriter',
            'Doctrine\\DBAL\\Connection',
            'BlackOps\\Core\\ActorRef',
            'BlackOps\\Core\\TenantRef',
            'BlackOps\\Core\\Audit\\AuditRecordActor',
            'BlackOps\\Core\\Audit\\AuditTypedReference',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
        ];
    }

    /** @return list<string> */
    private function declaredMethodNames(ReflectionClass $class): array
    {
        $names = [];
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $class->getName()) {
                $names[] = $method->getName();
            }
        }
        sort($names);

        return $names;
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

    /** @param list<Node> $nodes */
    private function classLikeNode(array $nodes): Class_|Interface_|null
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $node) {
            if ($node instanceof Class_) {
                return $node;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, Interface_::class) as $node) {
            if ($node instanceof Interface_) {
                return $node;
            }
        }

        return null;
    }

    /** @param list<Node> $nodes */
    private function methodNode(array $nodes, string $name): ?ClassMethod
    {
        $class = $this->classLikeNode($nodes);
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
    private function methodCalls(Node|array $scope): array
    {
        $calls = new NodeFinder()->findInstanceOf($scope, MethodCall::class);

        return array_values(array_filter($calls, static fn(Node $node): bool => $node instanceof MethodCall));
    }

    /** @param list<MethodCall> $calls */
    private function callsNamed(array $calls, string $name): array
    {
        return array_values(array_filter(
            $calls,
            static fn(MethodCall $call): bool => $call->name instanceof Identifier && $call->name->toString() === $name,
        ));
    }

    /** @param list<MethodCall> $calls */
    private function countCallsFor(array $calls, string $receiver, string $name): int
    {
        return count(array_filter(
            $calls,
            fn(MethodCall $call): bool => (
                $this->expressionLabel($call->var) === $receiver
                && $call->name instanceof Identifier
                && $call->name->toString() === $name
            ),
        ));
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

    /** @param list<array> $arguments */
    private function argumentsMatch(array $arguments, array $expected): bool
    {
        if (count($arguments) !== count($expected)) {
            return false;
        }
        foreach ($arguments as $index => $argument) {
            $shape = $expected[$index];
            $value = $argument->value;
            if ($shape[0] === 'variable' && (!$value instanceof Variable || $value->name !== $shape[1])) {
                return false;
            }
        }

        return true;
    }

    private function countNew(Node|array $scope, string $class): int
    {
        $count = 0;
        foreach (new NodeFinder()->findInstanceOf($scope, New_::class) as $new) {
            if ($this->terminalName($new->class) === $class) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<MethodCall> $calls */
    private function opaquePurposesAreActionAndResource(array $calls): bool
    {
        $purposes = [];
        foreach ($calls as $call) {
            if ($call->args === [] || !$call->args[0]->value instanceof ClassConstFetch) {
                return false;
            }
            $purpose = $call->args[0]->value;
            if (
                !$purpose->name instanceof Identifier
                || $this->terminalName($purpose->class) !== 'AuditOpaqueIdPurpose'
            ) {
                return false;
            }
            $purposes[] = $purpose->name->toString();
        }
        sort($purposes);

        return $purposes === ['Action', 'Resource'];
    }

    /** @param list<Node> $nodes */
    private function containsResultCodeRead(Node|array $nodes): bool
    {
        foreach ($this->methodCalls($nodes) as $call) {
            if ($call->name instanceof Identifier && strtolower($call->name->toString()) === 'code') {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes */
    private function containsRequestAccess(Node $nodes): bool
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, MethodCall::class) as $call) {
            if ($this->expressionLabel($call->var) === '$request') {
                return true;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, PropertyFetch::class) as $fetch) {
            if ($this->expressionLabel($fetch->var) === '$request') {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes */
    private function containsForbiddenType(array $nodes): bool
    {
        $forbidden = [
            'OperationId',
            'PostgreSqlAuditRecordStore',
            'PostgreSqlAuditOutboxStore',
            'AuditOutboxRelayRuntime',
            'AuditMutationTransactionCoordinator',
            'OperationTransactionCoordinator',
            'AuthenticationProvider',
        ];
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (in_array($this->terminalName($name), $forbidden, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes */
    private function containsTypeName(array $nodes, string $type): bool
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if ($this->terminalName($name) === $type) {
                return true;
            }
        }

        return false;
    }

    /** @param list<MethodCall> $calls */
    private function hasForbiddenMethod(array $calls): bool
    {
        foreach ($calls as $call) {
            if (
                $call->name instanceof Identifier
                && in_array(strtolower($call->name->toString()), $this->forbiddenMethodNames(), true)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param list<MethodCall> $calls @param list<string> $receivers */
    private function hasForbiddenReceiver(array $calls, array $receivers): bool
    {
        foreach ($calls as $call) {
            if (in_array($this->expressionLabel($call->var), $receivers, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function forbiddenMethodNames(): array
    {
        return [
            'begintransaction',
            'commit',
            'rollback',
            'savepoint',
            'deliverincurrenttransaction',
            'appendrequired',
            'appenddurable',
            'relay',
            'select',
            'fordraft',
            'requested',
            'terminal',
            'handle',
            'createresponse',
            'getheader',
            'getheaderline',
            'getheaders',
            'getbody',
            'getparsedbody',
            'getattribute',
            'getattributes',
            'provider',
            'getprovider',
            'credentials',
            'token',
            'gettoken',
        ];
    }

    private function hasAtLeastThreeIdleChecks(ClassMethod $method): bool
    {
        $calls = $this->methodCalls($method);
        $helperChecks = $this->countCallsFor($calls, '$this', 'assertIdle');
        if ($helperChecks >= 3) {
            return true;
        }
        $direct = 0;
        foreach (['isAutoCommit', 'isTransactionActive', 'getTransactionNestingLevel'] as $name) {
            $direct += $this->countCallsFor($calls, '$connection', $name);
        }

        return $direct >= 3;
    }

    private function stateBranchReturnsSameResult(ClassMethod $method, string $state): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, If_::class) as $if) {
            if (
                !$if->cond instanceof Node\Expr\BinaryOp\Identical
                || $this->expressionLabel($if->cond->left) !== '$state'
                || !$if->cond->right instanceof ClassConstFetch
                || $this->terminalName($if->cond->right->class) !== 'self'
                || !$if->cond->right->name instanceof Identifier
                || $if->cond->right->name->toString() !== $state
            ) {
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($if->stmts, Return_::class) as $return) {
                if ($return->expr instanceof Variable && $return->expr->name === 'result') {
                    return true;
                }
            }
        }

        return false;
    }

    private function containsNodeAt(Node $scope, int $position): bool
    {
        return $scope->getStartFilePos() <= $position && $scope->getEndFilePos() >= $position;
    }

    /** @param list<MethodCall> $writers */
    private function releaseOrderIsExact(ClassMethod $method, MethodCall $delegate, array $writers): bool
    {
        $resultReturns = [];
        foreach (new NodeFinder()->findInstanceOf($method, Return_::class) as $return) {
            if ($return->expr instanceof Variable && $return->expr->name === 'result') {
                $resultReturns[] = $return;
            }
        }
        if ($resultReturns === []) {
            return false;
        }
        foreach ($resultReturns as $return) {
            if ($return->getStartFilePos() < $delegate->getStartFilePos()) {
                return false;
            }
            if (
                $this->returnHasNoPriorWriter($return, $writers)
                && !$this->returnIsAnonymous($method, $return)
                && !$this->returnIsInvalid($method, $return)
            ) {
                return false;
            }
            if (
                !$this->returnIsAnonymous($method, $return)
                && !$this->returnIsInvalid($method, $return)
                && !$this->hasIdleCheckBetween($method, $writers[count($writers) - 1], $return)
            ) {
                return false;
            }
        }

        return true;
    }

    private function hasIdleCheckBetween(ClassMethod $method, MethodCall $writer, Return_ $return): bool
    {
        foreach ($this->methodCalls($method) as $call) {
            if (
                $call->getStartFilePos() > $writer->getStartFilePos()
                && $call->getStartFilePos() < $return->getStartFilePos()
                && $this->expressionLabel($call->var) === '$this'
                && $call->name instanceof Identifier
                && $call->name->toString() === 'assertIdle'
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param list<MethodCall> $writers */
    private function returnHasNoPriorWriter(Return_ $return, array $writers): bool
    {
        foreach ($writers as $writer) {
            if ($writer->getStartFilePos() < $return->getStartFilePos()) {
                return false;
            }
        }

        return true;
    }

    private function returnIsAnonymous(ClassMethod $method, Return_ $return): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, If_::class) as $if) {
            if (!$this->containsNodeAt($if, $return->getStartFilePos())) {
                continue;
            }
            if (
                $if->cond instanceof Node\Expr\BinaryOp\Identical
                && $this->expressionLabel($if->cond->left) === '$state'
                && $if->cond->right instanceof ClassConstFetch
                && $this->terminalName($if->cond->right->class) === 'self'
                && $if->cond->right->name instanceof Identifier
                && $if->cond->right->name->toString() === 'ANONYMOUS'
            ) {
                return true;
            }
            foreach ($this->methodCalls($if->cond) as $call) {
                if ($call->name instanceof Identifier && $call->name->toString() === 'isAnonymous') {
                    return true;
                }
            }
        }

        return false;
    }

    private function returnIsInvalid(ClassMethod $method, Return_ $return): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, If_::class) as $if) {
            if (!$this->containsNodeAt($if, $return->getStartFilePos())) {
                continue;
            }
            if (
                !$if->cond instanceof Node\Expr\BinaryOp\Identical
                || $this->expressionLabel($if->cond->left) !== '$state'
                || !$if->cond->right instanceof ClassConstFetch
                || $this->terminalName($if->cond->right->class) !== 'self'
                || !$if->cond->right->name instanceof Identifier
                || $if->cond->right->name->toString() !== 'INVALID'
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** @param list<Node> $nodes */
    private function requiredStringsPresent(array $nodes, array $expected): bool
    {
        $actual = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, StringNode::class) as $string) {
            $actual[$string->value] = true;
        }
        foreach ($expected as $value) {
            if (!isset($actual[$value])) {
                return false;
            }
        }

        return true;
    }

    /** @param list<Node> $nodes */
    private function hasRawThrowableThrow(array $nodes): bool
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Throw_::class) as $throw) {
            if ($throw->expr instanceof Variable) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes */
    private function hiddenStateIsClosed(array $nodes): bool
    {
        foreach (['__debugInfo', '__serialize'] as $name) {
            $method = $this->methodNode($nodes, $name);
            if ($method === null || !$this->returnsEmptyArray($method)) {
                return false;
            }
        }
        $unserialize = $this->methodNode($nodes, '__unserialize');
        if ($unserialize === null) {
            return false;
        }
        $throws = new NodeFinder()->findInstanceOf($unserialize, Throw_::class);

        return count($throws) === 1 && $this->isStorageUnavailableThrow($throws[0]);
    }

    private function isStorageUnavailableThrow(Throw_ $throw): bool
    {
        if ($throw->expr instanceof New_) {
            return (
                $this->terminalName($throw->expr->class) === 'AuditDeliveryException'
                && $throw->expr->args !== []
                && $throw->expr->args[0]->value instanceof ClassConstFetch
                && $this->terminalName($throw->expr->args[0]->value->class) === 'AuditDeliveryFailure'
                && $throw->expr->args[0]->value->name instanceof Identifier
                && $throw->expr->args[0]->value->name->toString() === 'StorageUnavailable'
            );
        }
        if (!$throw->expr instanceof Node\Expr\StaticCall) {
            return false;
        }

        return (
            $this->terminalName($throw->expr->class) === 'self'
            && $throw->expr->name instanceof Identifier
            && $throw->expr->name->toString() === 'failure'
            && $throw->expr->args !== []
            && $throw->expr->args[0]->value instanceof ClassConstFetch
            && $this->terminalName($throw->expr->args[0]->value->class) === 'AuditDeliveryFailure'
            && $throw->expr->args[0]->value->name instanceof Identifier
            && $throw->expr->args[0]->value->name->toString() === 'StorageUnavailable'
        );
    }

    private function returnsEmptyArray(ClassMethod $method): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, Return_::class) as $return) {
            if ($return->expr instanceof Array_ && $return->expr->items === []) {
                return true;
            }
        }

        return false;
    }

    private function hasPublicApiAttribute(Class_|Interface_ $class): bool
    {
        foreach (new NodeFinder()->findInstanceOf($class, Node\Attribute::class) as $attribute) {
            if ($this->terminalName($attribute->name) === 'PublicApi') {
                return true;
            }
        }

        return false;
    }

    private function resolvedNodeName(Node|string|null $name): ?string
    {
        if (!$name instanceof Name) {
            return is_string($name) ? ltrim($name, '\\') : null;
        }
        $resolved = $name->getAttribute('resolvedName');
        if ($resolved instanceof Name) {
            return ltrim($resolved->toString(), '\\');
        }

        return ltrim($name->toString(), '\\');
    }

    /** @return array<string,string> */
    private function adapterSources(): array
    {
        $sources = [];
        foreach (array_keys($this->adapterDefinitions()) as $path) {
            $source = @file_get_contents(__DIR__ . '/../../' . $path);
            self::assertIsString($source, $path);
            $sources[$path] = $source;
        }

        return $sources;
    }

    /** @return array<string,string> */
    private function productionSources(): array
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
            self::assertIsString($source);
            $sources[$relative] = $source;
        }
        ksort($sources);

        return $sources;
    }

    private function replaceOnce(string $source, string $search, string $replacement): string
    {
        $result = str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count, $search);

        return $result;
    }

    private function removeSensitiveParameterAttribute(string $source, string $methodName, string $type): string
    {
        $method = $this->methodNode($this->parse($source), $methodName);
        self::assertNotNull($method, $methodName);
        foreach ($method->params as $parameter) {
            if ($this->resolvedNodeName($parameter->type) !== $type) {
                continue;
            }
            foreach ($parameter->attrGroups as $group) {
                foreach ($group->attrs as $attribute) {
                    if ($this->terminalName($attribute->name) !== 'SensitiveParameter') {
                        continue;
                    }
                    $start = $group->getStartFilePos();
                    $end = $group->getEndFilePos();
                    self::assertGreaterThanOrEqual(0, $start);
                    self::assertGreaterThanOrEqual($start, $end);

                    return substr($source, 0, $start) . substr($source, $end + 1);
                }
            }
        }
        self::fail('SensitiveParameter attribute not found for ' . $methodName . ' ' . $type);

        return $source;
    }

    private function insertBefore(string $source, string $needle, string $insertion): string
    {
        $position = strpos($source, $needle);
        self::assertNotFalse($position, $needle);

        return substr($source, 0, $position) . $insertion . substr($source, $position);
    }
}
