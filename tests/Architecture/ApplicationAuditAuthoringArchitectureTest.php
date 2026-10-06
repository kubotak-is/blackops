<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Audit\AuditCatalogDefinition;
use BlackOps\Audit\AuditCatalogProvider;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\Authoring\ApplicationAuditCatalogRegistry;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UseItem;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class ApplicationAuditAuthoringArchitectureTest extends TestCase
{
    public function testPublicDeclarationsAndInternalRegistryHaveExactReflectionSurfaces(): void
    {
        $definition = new \ReflectionClass(AuditCatalogDefinition::class);
        self::assertTrue($definition->isFinal());
        self::assertTrue($definition->isReadOnly());
        self::assertCount(1, $definition->getAttributes(PublicApi::class));
        self::assertSame(
            ['catalogId', 'catalogVersion', 'events'],
            array_map(
                static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
                $definition->getConstructor()?->getParameters() ?? [],
            ),
        );
        self::assertSame(
            ['catalogId', 'catalogVersion', 'events'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $definition->getProperties(\ReflectionProperty::IS_PUBLIC),
            ),
        );
        $definitionParameters = $definition->getConstructor()?->getParameters() ?? [];
        self::assertCount(3, $definitionParameters);
        $this->assertParameter($definitionParameters[0], 'catalogId', 'string', false, true);
        $this->assertParameter($definitionParameters[1], 'catalogVersion', 'int', false, false);
        $this->assertParameter($definitionParameters[2], 'events', 'array', false, true);
        self::assertSame('string', $definition->getProperty('catalogId')->getType()?->getName());
        self::assertSame('int', $definition->getProperty('catalogVersion')->getType()?->getName());
        self::assertSame('array', $definition->getProperty('events')->getType()?->getName());

        $provider = new \ReflectionClass(AuditCatalogProvider::class);
        self::assertTrue($provider->isInterface());
        self::assertSame([], $provider->getInterfaceNames());
        self::assertCount(1, $provider->getAttributes(PublicApi::class));
        self::assertSame(
            ['catalogs'],
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $provider->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
        );
        $catalogs = $provider->getMethod('catalogs');
        self::assertCount(0, $catalogs->getParameters());
        self::assertSame('array', $catalogs->getReturnType()?->getName());

        $registry = new \ReflectionClass(ApplicationAuditCatalogRegistry::class);
        self::assertTrue($registry->isFinal());
        self::assertTrue($registry->isReadOnly());
        self::assertSame([], $registry->getAttributes(PublicApi::class));
        self::assertSame(
            ['__construct', 'lookup'],
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $registry->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
        );
        self::assertSame(
            ['catalogs'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $registry->getProperties(),
            ),
        );
        $constructorParameters = $registry->getConstructor()?->getParameters() ?? [];
        self::assertCount(1, $constructorParameters);
        $this->assertParameter($constructorParameters[0], 'provider', AuditCatalogProvider::class, true, true);
        self::assertSame(
            ImmutableAuditEventCatalogLookup::class,
            $registry->getMethod('lookup')->getReturnType()?->getName(),
        );
        self::assertSame(
            ImmutableAuditEventCatalogLookup::class,
            $registry->getProperty('catalogs')->getType()?->getName(),
        );
        foreach (['assemble', 'assertNoReferences', 'assertArrayHasNoReferences'] as $methodName) {
            $method = $registry->getMethod($methodName);
            self::assertTrue($method->isPrivate(), $methodName);
            self::assertCount(1, $method->getParameters());
            self::assertCount(1, $method->getParameters()[0]->getAttributes(\SensitiveParameter::class));
        }
    }

    public function testPublicDeclarationSignaturesAndDocumentationUseOnlyTheClosedGraph(): void
    {
        $expectedImports = [
            AuditCatalogDefinition::class => [
                PublicApi::class,
                AuditEventDefinition::class,
                AuditValidationException::class,
                AuditValidationFailure::class,
            ],
            AuditCatalogProvider::class => [PublicApi::class],
        ];
        foreach ($expectedImports as $class => $imports) {
            $source = file_get_contents($this->publicSourcePath($class));
            self::assertIsString($source);
            $nodes = $this->parse($source);
            $uses = new NodeFinder()->findInstanceOf($nodes, UseItem::class);
            $actual = array_map(fn(UseItem $use): string => $this->resolvedName($use->name), $uses);
            sort($actual);
            $expected = array_map(static fn(string $name): string => ltrim($name, '\\'), $imports);
            sort($expected);
            self::assertSame($expected, $actual, $class);
            foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
                $resolved = $this->resolvedName($name);
                self::assertFalse(str_starts_with($resolved, 'BlackOps\\Internal\\'), $class . ' dependency');
            }
        }

        $providerSource = file_get_contents($this->publicSourcePath(AuditCatalogProvider::class));
        self::assertIsString($providerSource);
        $internalReturn = $this->replaceOnce(
            $providerSource,
            'public function catalogs(): array',
            'public function catalogs(): \\BlackOps\\Internal\\Audit\\AuditJcsCodec',
        );
        $this->assertParses($internalReturn, 'public Internal return type');
        self::assertNotSame(
            [],
            $this->publicSourceViolations($internalReturn, [PublicApi::class]),
            'public Internal return type',
        );
    }

    public function testRegistrySourceHasOnlyTheTrustedAssemblyAndReferenceSeam(): void
    {
        $source = $this->registrySource();
        self::assertSame([], $this->sourceViolations($source));
        self::assertSame([], $this->productionCallerViolations());
    }

    public function testRegistrySourceMutationControlsUseParseValidResolvedSemanticEdges(): void
    {
        $source = $this->registrySource();
        $mutations = [
            'events reference boundary' => $this->replaceOnce(
                $source,
                '$this->assertNoReferences($definition->events);',
                '$this->assertNoReferences($definition->catalogId);',
            ),
            'events reference nonexecuted wrapper' => $this->replaceOnce(
                $source,
                '$this->assertNoReferences($definition->events);',
                'if (false) { $this->assertNoReferences($definition->events); }',
            ),
            'wrong sealed provider object' => $this->replaceOnce(
                $source,
                '$operational = FrameworkOperationalAuditEventCatalog::catalog();',
                '$operational = FrameworkAuditEventCatalog::catalog();',
            ),
            'owner substitution' => $this->replaceOnce(
                $source,
                'AuditSourceOwner::Application',
                'AuditSourceOwner::Framework',
            ),
            'prepared catalog bypass' => $this->replaceOnce($source, '$codec->catalog(', '$codec->preparedCatalog('),
            'forbidden dependency import' => $this->replaceOnce(
                $source,
                'use BlackOps\\Core\\Audit\\AuditEventCatalog;',
                'use BlackOps\\Internal\\Audit\\AuditJcsCodec;',
            ),
            'provider evaluation twice' => $this->replaceOnce(
                $source,
                '$definitions = $provider->catalogs();',
                '$definitions = $provider->catalogs(); $definitions = $provider->catalogs();',
            ),
            'lazy provider evaluation' => $this->replaceOnce(
                $source,
                '$definitions = $provider->catalogs();',
                '$definitions = [$provider];',
            ),
            'extra retained state' => $this->replaceOnce(
                $source,
                '    private ImmutableAuditEventCatalogLookup $catalogs;',
                "    private ImmutableAuditEventCatalogLookup \$catalogs;\n    private ?AuditCatalogProvider \$provider;",
            ),
            'extra public mutator' => $this->replaceOnce(
                $source,
                '    public function lookup():',
                "    public function setLookup(): void {}\n\n    public function lookup():",
            ),
            'activation caller' =>
                $source
                    . "\nif (false) { \\BlackOps\\Internal\\Application\\ApplicationAuditRuntimeComposer::compose(); }\n",
            'registry constructor caller in closure' =>
                $source
                    . "\n\$unused = static function (): void { new \\BlackOps\\Internal\\Audit\\Authoring\\ApplicationAuditCatalogRegistry(null); };\n",
        ];

        foreach ($mutations as $name => $mutation) {
            $this->assertParses($mutation, $name);
            self::assertNotSame([], $this->sourceViolations($mutation), $name);
        }
        foreach (['actionTypes', 'semanticTuples', 'evidenceFields'] as $boundary) {
            $mutation = $this->replaceOnce(
                $source,
                '$this->assertArrayHasNoReferences($event->' . $boundary . ');',
                '$this->assertArrayHasNoReferences($event->type);',
            );
            $this->assertParses($mutation, $boundary . ' reference boundary');
            self::assertNotSame([], $this->sourceViolations($mutation), $boundary . ' reference boundary');
        }
        foreach (['actionTypes', 'semanticTuples', 'evidenceFields'] as $boundary) {
            $mutation = $this->replaceOnce(
                $source,
                '$this->assertArrayHasNoReferences($event->' . $boundary . ');',
                'if (false) { $this->assertArrayHasNoReferences($event->' . $boundary . '); }',
            );
            $this->assertParses($mutation, $boundary . ' reference nonexecuted wrapper');
            self::assertNotSame([], $this->sourceViolations($mutation), $boundary . ' reference nonexecuted wrapper');
        }

        $alias = $this->replaceOnce(
            $this->replaceOnce(
                $source,
                'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog;',
                'use BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog as FrameworkCatalog;',
            ),
            'FrameworkAuditEventCatalog::catalog()',
            'FrameworkCatalog::catalog()',
        );
        self::assertSame([], $this->sourceViolations($alias), 'resolved class aliases remain supported');

        $fqcn = $this->replaceOnce(
            $source,
            'FrameworkOperationalAuditEventCatalog::catalog()',
            '\\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::catalog()',
        );
        self::assertSame([], $this->sourceViolations($fqcn), 'fully qualified calls remain supported');

        $inert =
            $source
            . "\n// ApplicationAuditRuntimeComposer::compose and ApplicationAuditCatalogRegistry\n"
            . "\$text = 'ReflectionReference::fromArrayElement';\n";
        self::assertSame([], $this->sourceViolations($inert), 'inert text is not an executable edge');
    }

    private function sourceViolations(string $source): array
    {
        try {
            $nodes = $this->parse($source);
        } catch (\Throwable) {
            return ['syntax'];
        }

        $violations = $this->registryAstViolations($nodes);
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if ($this->newMatches($new, ApplicationAuditCatalogRegistry::class)) {
                $violations[] = 'registry-constructor-caller';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            if ($this->staticCallMatches(
                $call,
                'BlackOps\\Internal\\Application\\ApplicationAuditRuntimeComposer',
                'compose',
            )) {
                $violations[] = 'activation-caller';
            }
        }

        return array_values(array_unique($violations));
    }

    private function registryAstViolations(array $nodes): array
    {
        $violations = [];
        $classes = new NodeFinder()->findInstanceOf($nodes, Class_::class);
        if (count($classes) !== 1) {
            return ['registry-declaration-inventory'];
        }
        $class = $classes[0];
        if (
            !$class->isFinal()
            || !$class->isReadonly()
            || $class->extends !== null
            || $class->implements !== []
            || !$class->namespacedName instanceof Name
            || !$this->sameSymbol($this->resolvedName($class->namespacedName), ApplicationAuditCatalogRegistry::class)
            || $this->hasAttribute($class, PublicApi::class)
        ) {
            $violations[] = 'registry-declaration';
        }

        $imports = array_map(
            fn(UseItem $use): string => $this->resolvedName($use->name),
            new NodeFinder()->findInstanceOf($nodes, UseItem::class),
        );
        sort($imports);
        $expectedImports = [
            AuditCatalogDefinition::class,
            AuditCatalogProvider::class,
            AuditEventCatalog::class,
            AuditEventDefinition::class,
            AuditSourceOwner::class,
            AuditValidationException::class,
            AuditValidationFailure::class,
            AuditEventCatalogCodec::class,
            FrameworkAuditEventCatalog::class,
            FrameworkOperationalAuditEventCatalog::class,
            ImmutableAuditEventCatalogLookup::class,
        ];
        sort($expectedImports);
        if ($imports !== $expectedImports) {
            $violations[] = 'registry-dependency-inventory';
        }

        $properties = new NodeFinder()->findInstanceOf($class, Property::class);
        if (count($properties) !== 1) {
            $violations[] = 'retained-state-inventory';
        } else {
            $property = $properties[0];
            if (
                !$property->isPrivate()
                || $property->isStatic()
                || count($property->props) !== 1
                || $property->props[0]->name->toString() !== 'catalogs'
                || $this->typeName($property->type) !== ImmutableAuditEventCatalogLookup::class
            ) {
                $violations[] = 'retained-state';
            }
        }

        $methods = [];
        foreach ($class->getMethods() as $method) {
            $methods[$method->name->toString()] = $method;
        }
        $expectedMethods = ['__construct', 'lookup', 'assemble', 'assertNoReferences', 'assertArrayHasNoReferences'];
        if (array_keys($methods) !== $expectedMethods) {
            $violations[] = 'method-inventory';
        }
        foreach (['__construct', 'lookup'] as $name) {
            if (!isset($methods[$name]) || !$methods[$name]->isPublic()) {
                $violations[] = 'public-method-surface';
            }
        }
        foreach (['assemble', 'assertNoReferences', 'assertArrayHasNoReferences'] as $name) {
            if (!isset($methods[$name]) || !$methods[$name]->isPrivate()) {
                $violations[] = 'private-method-surface';
            }
        }

        $constructor = $methods['__construct'] ?? null;
        $assemble = $methods['assemble'] ?? null;
        $referenceCheck = $methods['assertNoReferences'] ?? null;
        $arrayReferenceCheck = $methods['assertArrayHasNoReferences'] ?? null;
        if (!$constructor instanceof ClassMethod || !$assemble instanceof ClassMethod) {
            return array_values(array_unique([...$violations, 'assembly-methods']));
        }
        if (
            count($constructor->params) !== 1
            || !$this->parameterMatches(
                $constructor->params[0] ?? null,
                'provider',
                AuditCatalogProvider::class,
                true,
                true,
            )
            || count($assemble->params) !== 1
            || !$this->parameterMatches(
                $assemble->params[0] ?? null,
                'provider',
                AuditCatalogProvider::class,
                true,
                true,
            )
            || $this->typeName($assemble->returnType) !== ImmutableAuditEventCatalogLookup::class
        ) {
            $violations[] = 'assembly-signature';
        }
        if (
            !$referenceCheck instanceof ClassMethod
            || !$arrayReferenceCheck instanceof ClassMethod
            || count($referenceCheck->params) !== 1
            || count($arrayReferenceCheck->params) !== 1
            || !$this->parameterMatches($referenceCheck->params[0] ?? null, 'events', 'array', false, true)
            || !$this->parameterMatches($arrayReferenceCheck->params[0] ?? null, 'values', 'array', false, true)
        ) {
            $violations[] = 'reference-signature';
        }

        $constructorAssemblyCalls = array_values(array_filter(
            new NodeFinder()->findInstanceOf($constructor, MethodCall::class),
            fn(MethodCall $call): bool => (
                $this->methodCallMatches($call, '$this', 'assemble')
                && count($call->args) === 1
                && $this->variableMatches($call->args[0]->value, 'provider')
            ),
        ));
        if (
            count($constructorAssemblyCalls) !== 1
            || !$this->assignedPropertyExpression(
                $constructor,
                '$this',
                'catalogs',
                $constructorAssemblyCalls[0] ?? null,
            )
        ) {
            $violations[] = 'constructor-capture';
        }

        $constructorCatches = new NodeFinder()->findInstanceOf($constructor, Catch_::class);
        $validationCatches = array_values(array_filter($constructorCatches, fn(Catch_ $catch): bool => $this->catchMatches(
            $catch,
            AuditValidationException::class,
        )));
        $throwableCatches = array_values(array_filter($constructorCatches, fn(Catch_ $catch): bool => $this->catchMatches(
            $catch,
            'Throwable',
        )));
        if (count($validationCatches) !== 1 || count($throwableCatches) !== 1) {
            $violations[] = 'failure-boundary';
        }
        $validationNews = array_values(array_filter(
            new NodeFinder()->findInstanceOf($constructor, New_::class),
            fn(New_ $new): bool => $this->newMatches($new, AuditValidationException::class),
        ));
        $hasFreshFailure = false;
        $hasSemanticFailure = false;
        foreach ($validationNews as $new) {
            if (count($new->args) !== 1) {
                continue;
            }
            $argument = $new->args[0]->value;
            if (
                $argument instanceof MethodCall
                && $this->variableMatches($argument->var, 'failure')
                && $argument->name instanceof Identifier
                && $argument->name->toString() === 'failure'
            ) {
                $hasFreshFailure = true;
            }
            if ($this->classConstMatches($argument, AuditValidationFailure::class, 'SemanticInvalid')) {
                $hasSemanticFailure = true;
            }
        }
        if (count($validationNews) !== 2 || !$hasFreshFailure || !$hasSemanticFailure) {
            $violations[] = 'fresh-failure';
        }

        $assembleCalls = new NodeFinder()->findInstanceOf($assemble, MethodCall::class);
        $providerCalls = array_values(array_filter($assembleCalls, fn(MethodCall $call): bool => $this->methodCallMatches(
            $call,
            '$provider',
            'catalogs',
        )));
        if (
            count($providerCalls) !== 1
            || !$this->assignedExpression($assemble, '$definitions', $providerCalls[0] ?? null)
        ) {
            $violations[] = 'provider-evaluation';
        }
        $listChecks = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, FuncCall::class),
            fn(FuncCall $call): bool => $this->functionCallMatches($call, 'array_is_list', ['$definitions']),
        ));
        if (count($listChecks) !== 1 || !$this->hasCountGreaterThan($assemble, '$definitions', 32)) {
            $violations[] = 'provider-bounds';
        }
        $definitionForeach = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, Foreach_::class),
            static fn(Foreach_ $foreach): bool => (
                $foreach->expr instanceof Variable
                && $foreach->expr->name === 'definitions'
                && $foreach->valueVar instanceof Variable
                && $foreach->valueVar->name === 'definition'
            ),
        ));
        if (count($definitionForeach) !== 2) {
            $violations[] = 'definition-iteration';
        }
        $definitionTypeChecks = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, Instanceof_::class),
            fn(Instanceof_ $check): bool => (
                $this->variableMatches($check->expr, 'definition')
                && $check->class instanceof Name
                && $this->sameSymbol($this->resolvedName($check->class), AuditCatalogDefinition::class)
            ),
        ));
        if (count($definitionTypeChecks) !== 1) {
            $violations[] = 'definition-type-check';
        }

        $frameworkCalls = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, StaticCall::class),
            fn(StaticCall $call): bool => $this->staticCallMatches($call, FrameworkAuditEventCatalog::class, 'catalog'),
        ));
        $operationalCalls = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, StaticCall::class),
            fn(StaticCall $call): bool => $this->staticCallMatches(
                $call,
                FrameworkOperationalAuditEventCatalog::class,
                'catalog',
            ),
        ));
        if (
            count($frameworkCalls) !== 1
            || count($operationalCalls) !== 1
            || !$this->assignedExpression($assemble, '$framework', $frameworkCalls[0] ?? null)
            || !$this->assignedExpression($assemble, '$operational', $operationalCalls[0] ?? null)
        ) {
            $violations[] = 'sealed-capture';
        }

        $codecNews = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, New_::class),
            fn(New_ $new): bool => $this->newMatches($new, AuditEventCatalogCodec::class),
        ));
        if (count($codecNews) !== 1 || !$this->assignedExpression($assemble, '$codec', $codecNews[0] ?? null)) {
            $violations[] = 'codec-construction';
        }
        $initialCatalogs = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, Assign::class),
            fn(Assign $assign): bool => $this->arrayAssignMatches($assign, '$catalogs', ['$framework', '$operational']),
        ));
        if (count($initialCatalogs) !== 1) {
            $violations[] = 'sealed-order';
        }

        $codecCalls = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, MethodCall::class),
            fn(MethodCall $call): bool => $this->methodCallMatches($call, '$codec', 'catalog'),
        ));
        if (count($codecCalls) !== 1 || !$this->codecCallMatches($codecCalls[0] ?? null)) {
            $violations[] = 'application-codec';
        }
        if (count($codecCalls) === 1 && !$this->assignedAppendExpression($assemble, '$catalogs', $codecCalls[0])) {
            $violations[] = 'codec-capture';
        }
        $validationDefinitionForeach = array_values(array_filter(
            $definitionForeach,
            fn(Foreach_ $foreach): bool => (
                count(array_filter($definitionTypeChecks, fn(Instanceof_ $check): bool => $this->containsNode(
                    $foreach,
                    $check,
                ))) === 1
            ),
        ));
        $assemblyDefinitionForeach = array_values(array_filter(
            $definitionForeach,
            fn(Foreach_ $foreach): bool => (
                count(array_filter($codecCalls, fn(MethodCall $call): bool => $this->containsNode($foreach, $call)))
                === 1
            ),
        ));
        if (
            count($validationDefinitionForeach) !== 1
            || count($assemblyDefinitionForeach) !== 1
            || !isset($validationDefinitionForeach[0], $assemblyDefinitionForeach[0])
            || $validationDefinitionForeach[0]->getStartFilePos() >= $assemblyDefinitionForeach[0]->getStartFilePos()
        ) {
            $violations[] = 'definition-iteration-roles';
        }
        $lookupNews = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, New_::class),
            fn(New_ $new): bool => $this->newMatches($new, ImmutableAuditEventCatalogLookup::class),
        ));
        $lookupArgumentCount = count($lookupNews) === 1 ? count($lookupNews[0]->args) : -1;
        $lookupArgument = count($lookupNews) === 1 ? $lookupNews[0]->args[0]->value ?? null : null;
        $lookupNode = $lookupNews[0] ?? null;
        if (
            count($lookupNews) !== 1
            || $lookupArgumentCount !== 1
            || !$this->variableMatches($lookupArgument, 'catalogs')
            || !$lookupNode instanceof New_
            || !$this->returnedExpression($assemble, $lookupNode)
        ) {
            $violations[] = 'lookup-capture';
        }
        if (
            isset($codecCalls[0], $lookupNews[0])
            && $codecCalls[0]->getStartFilePos() >= $lookupNews[0]->getStartFilePos()
        ) {
            $violations[] = 'lookup-order';
        }

        $definitionEvents = array_values(array_filter(
            $assembleCalls,
            fn(MethodCall $call): bool => (
                $this->methodCallMatches($call, '$this', 'assertNoReferences')
                && count($call->args) === 1
                && $this->propertyMatches($call->args[0]->value, '$definition', 'events')
            ),
        ));
        if (
            count($definitionEvents) !== 1
            || isset($codecCalls[0], $definitionEvents[0])
            && $definitionEvents[0]->getStartFilePos() >= $codecCalls[0]->getStartFilePos()
            || isset($assemblyDefinitionForeach[0], $definitionEvents[0])
            && (
                !$this->containsNode($assemblyDefinitionForeach[0], $definitionEvents[0])
                || !$this->directExpressionStatementInForeach($assemblyDefinitionForeach[0], $definitionEvents[0])
            )
        ) {
            $violations[] = 'reference-before-codec';
        }
        $sealedCollisionChecks = array_values(array_filter(
            new NodeFinder()->findInstanceOf($assemble, Node\Expr\BinaryOp\BooleanOr::class),
            fn(Node\Expr\BinaryOp\BooleanOr $check): bool => $this->sealedCollisionMatches($check),
        ));
        if (
            count($sealedCollisionChecks) !== 1
            || isset($sealedCollisionChecks[0], $codecCalls[0])
            && $sealedCollisionChecks[0]->getStartFilePos() >= $codecCalls[0]->getStartFilePos()
        ) {
            $violations[] = 'sealed-id-check';
        }
        $sealedCollision = $sealedCollisionChecks[0] ?? null;
        foreach (['framework', 'operational'] as $receiver) {
            $matches = array_values(array_filter(
                $sealedCollision instanceof Node
                    ? new NodeFinder()->findInstanceOf($sealedCollision, PropertyFetch::class)
                    : [],
                fn(PropertyFetch $fetch): bool => $this->propertyMatches($fetch, '$' . $receiver, 'catalogId'),
            ));
            if (count($matches) !== 1) {
                $violations[] = 'sealed-id-binding:' . $receiver;
            }
        }

        if ($referenceCheck instanceof ClassMethod) {
            $references = array_values(array_filter(
                new NodeFinder()->findInstanceOf($referenceCheck, StaticCall::class),
                fn(StaticCall $call): bool => $this->staticCallMatches(
                    $call,
                    'ReflectionReference',
                    'fromArrayElement',
                ),
            ));
            if (count($references) !== 1 || !$this->referenceCallMatches($references[0] ?? null, 'events')) {
                $violations[] = 'events-reference-check';
            }
            $eventForeach = array_values(array_filter(
                new NodeFinder()->findInstanceOf($referenceCheck, Foreach_::class),
                static fn(Foreach_ $foreach): bool => (
                    $foreach->expr instanceof Variable
                    && $foreach->expr->name === 'events'
                    && $foreach->keyVar instanceof Variable
                    && $foreach->keyVar->name === 'key'
                    && $foreach->valueVar instanceof Variable
                    && $foreach->valueVar->name === 'event'
                ),
            ));
            if (count($eventForeach) !== 1) {
                $violations[] = 'reference-iteration';
            }
            $arrayChecks = array_values(array_filter(
                new NodeFinder()->findInstanceOf($referenceCheck, MethodCall::class),
                fn(MethodCall $call): bool => $this->methodCallMatches($call, '$this', 'assertArrayHasNoReferences'),
            ));
            $checkedProperties = [];
            foreach ($arrayChecks as $call) {
                if (count($call->args) !== 1 || !$call->args[0]->value instanceof PropertyFetch) {
                    continue;
                }
                $property = $call->args[0]->value;
                if (
                    $property->var instanceof Variable
                    && $property->var->name === 'event'
                    && $property->name instanceof Identifier
                ) {
                    $checkedProperties[] = $property->name->toString();
                }
            }
            sort($checkedProperties);
            $expectedProperties = ['actionTypes', 'evidenceFields', 'semanticTuples'];
            sort($expectedProperties);
            if ($checkedProperties !== $expectedProperties) {
                $violations[] = 'nested-reference-checks';
            }
            $directArrayChecks = array_values(array_filter(
                $arrayChecks,
                fn(MethodCall $call): bool => (
                    isset($eventForeach[0]) && $this->directExpressionStatementInForeach($eventForeach[0], $call)
                ),
            ));
            if (count($directArrayChecks) !== 3) {
                $violations[] = 'nested-reference-statements';
            }
        }
        if ($arrayReferenceCheck instanceof ClassMethod) {
            $references = array_values(array_filter(
                new NodeFinder()->findInstanceOf($arrayReferenceCheck, StaticCall::class),
                fn(StaticCall $call): bool => $this->staticCallMatches(
                    $call,
                    'ReflectionReference',
                    'fromArrayElement',
                ),
            ));
            if (count($references) !== 1 || !$this->referenceCallMatches($references[0] ?? null, 'values')) {
                $violations[] = 'nested-reference-boundary';
            }
        }
        $allReferences = array_values(array_filter(
            new NodeFinder()->findInstanceOf($class, StaticCall::class),
            fn(StaticCall $call): bool => $this->staticCallMatches($call, 'ReflectionReference', 'fromArrayElement'),
        ));
        if (count($allReferences) !== 2) {
            $violations[] = 'reference-boundary-count';
        }

        return array_values(array_unique($violations));
    }

    private function productionCallerViolations(): array
    {
        $violations = [];
        $root = dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $root,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            if (!is_string($source)) {
                $violations[] = 'caller-read';
                continue;
            }
            try {
                $nodes = $this->parse($source);
            } catch (\Throwable) {
                $violations[] = 'caller-syntax:' . $file->getPathname();
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
                if ($this->newMatches($new, ApplicationAuditCatalogRegistry::class)) {
                    $violations[] = 'caller:' . $file->getPathname();
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
                if ($this->staticCallMatches(
                    $call,
                    'BlackOps\\Internal\\Application\\ApplicationAuditRuntimeComposer',
                    'compose',
                )) {
                    $violations[] = 'activation:' . $file->getPathname();
                }
            }
        }

        return array_values(array_unique($violations));
    }

    private function publicSourcePath(string $class): string
    {
        return match ($class) {
            AuditCatalogDefinition::class => dirname(__DIR__, 2) . '/src/Audit/AuditCatalogDefinition.php',
            AuditCatalogProvider::class => dirname(__DIR__, 2) . '/src/Audit/AuditCatalogProvider.php',
            default => throw new \LogicException('Unsupported public declaration.'),
        };
    }

    private function registrySource(): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php',
        );
        self::assertIsString($source);

        return $source;
    }

    private function parse(string $source): array
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $nodes = $parser->parse($source);
        if (!is_array($nodes)) {
            throw new \LogicException('The source did not produce a statement list.');
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $traverser->traverse($nodes);
    }

    private function publicSourceViolations(string $source, array $expectedImports): array
    {
        try {
            $nodes = $this->parse($source);
        } catch (\Throwable) {
            return ['syntax'];
        }

        $imports = array_map(
            fn(UseItem $use): string => $this->resolvedName($use->name),
            new NodeFinder()->findInstanceOf($nodes, UseItem::class),
        );
        sort($imports);
        $expected = array_map(static fn(string $name): string => ltrim($name, '\\'), $expectedImports);
        sort($expected);
        $violations = $imports === $expected ? [] : ['public-dependency-inventory'];
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (str_starts_with($this->resolvedName($name), 'BlackOps\\Internal\\')) {
                $violations[] = 'public-internal-dependency';
            }
        }

        return array_values(array_unique($violations));
    }

    private function assertParses(string $source, string $label): void
    {
        try {
            $this->parse($source);
        } catch (\Throwable $exception) {
            self::fail($label . ' mutation was not parse-valid: ' . $exception->getMessage());
        }
    }

    private function replaceOnce(string $source, string $search, string $replacement): string
    {
        $result = str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count, 'Mutation anchor must be unique.');

        return $result;
    }

    private function assertParameter(
        \ReflectionParameter $parameter,
        string $name,
        string $type,
        bool $nullable,
        bool $sensitive,
    ): void {
        self::assertSame($name, $parameter->getName());
        $reflectionType = $parameter->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $reflectionType);
        self::assertSame(ltrim($type, '?\\'), ltrim($reflectionType->getName(), '\\'));
        self::assertSame($nullable, $reflectionType->allowsNull());
        self::assertSame($sensitive, count($parameter->getAttributes(\SensitiveParameter::class)) === 1);
        self::assertFalse($parameter->isDefaultValueAvailable());
    }

    private function hasAttribute(Node $node, string $class): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (
                    $attribute->name instanceof Name
                    && $this->sameSymbol($this->resolvedName($attribute->name), $class)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parameterMatches(
        ?Param $parameter,
        string $name,
        string $type,
        bool $nullable,
        bool $sensitive,
    ): bool {
        return (
            $parameter instanceof Param
            && $parameter->var instanceof Variable
            && $parameter->var->name === $name
            && $this->typeName($parameter->type) === ltrim($type, '\\')
            && $parameter->type instanceof NullableType === $nullable
            && $this->hasAttribute($parameter, 'SensitiveParameter') === $sensitive
        );
    }

    private function typeName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return $this->typeName($type->type);
        }
        if ($type instanceof Name) {
            return $this->resolvedName($type);
        }
        if ($type instanceof Identifier) {
            return strtolower($type->toString());
        }

        return null;
    }

    private function catchMatches(Catch_ $catch, string $type): bool
    {
        return (
            count($catch->types) === 1
            && $catch->types[0] instanceof Name
            && $this->sameSymbol($this->resolvedName($catch->types[0]), $type)
        );
    }

    private function newMatches(?Node $node, string $class): bool
    {
        return (
            $node instanceof New_
            && $node->class instanceof Name
            && $this->sameSymbol($this->resolvedName($node->class), $class)
        );
    }

    private function staticCallMatches(StaticCall $call, string $class, string $method): bool
    {
        return (
            $call->class instanceof Name
            && $this->sameSymbol($this->resolvedName($call->class), $class)
            && $call->name instanceof Identifier
            && $this->sameSymbol($call->name->toString(), $method)
        );
    }

    private function methodCallMatches(MethodCall $call, string $receiver, string $method): bool
    {
        return (
            $this->variableMatches($call->var, ltrim($receiver, '$'))
            && $call->name instanceof Identifier
            && $this->sameSymbol($call->name->toString(), $method)
        );
    }

    private function functionCallMatches(FuncCall $call, string $function, array $arguments): bool
    {
        if (!$call->name instanceof Name || strtolower($call->name->toString()) !== strtolower($function)) {
            return false;
        }
        if (count($call->args) !== count($arguments)) {
            return false;
        }
        foreach ($call->args as $index => $argument) {
            if (!$this->variableMatches($argument->value, ltrim($arguments[$index], '$'))) {
                return false;
            }
        }

        return true;
    }

    private function variableMatches(?Node $node, string $name): bool
    {
        return $node instanceof Variable && $node->name === ltrim($name, '$');
    }

    private function containsNode(?Node $scope, ?Node $candidate): bool
    {
        return (
            $scope !== null
            && $candidate !== null
            && $candidate->getStartFilePos() >= $scope->getStartFilePos()
            && $candidate->getEndFilePos() <= $scope->getEndFilePos()
        );
    }

    private function directExpressionStatementInForeach(Foreach_ $foreach, ?Node $expression): bool
    {
        if ($expression === null) {
            return false;
        }
        foreach ($foreach->stmts as $statement) {
            if ($statement instanceof Expression && $statement->expr === $expression) {
                return true;
            }
        }

        return false;
    }

    private function propertyMatches(?Node $node, string $receiver, string $property): bool
    {
        return (
            $node instanceof PropertyFetch
            && $this->variableMatches($node->var, ltrim($receiver, '$'))
            && $node->name instanceof Identifier
            && $node->name->toString() === $property
        );
    }

    private function assignedExpression(Node $scope, string $variable, ?Node $expression): bool
    {
        if ($expression === null) {
            return false;
        }
        foreach (new NodeFinder()->findInstanceOf($scope, Assign::class) as $assign) {
            if ($this->variableMatches($assign->var, ltrim($variable, '$')) && $assign->expr === $expression) {
                return true;
            }
        }

        return false;
    }

    private function assignedPropertyExpression(
        Node $scope,
        string $receiver,
        string $property,
        ?Node $expression,
    ): bool {
        if ($expression === null) {
            return false;
        }
        foreach (new NodeFinder()->findInstanceOf($scope, Assign::class) as $assign) {
            if (
                $assign->var instanceof PropertyFetch
                && $this->propertyMatches($assign->var, $receiver, $property)
                && $assign->expr === $expression
            ) {
                return true;
            }
        }

        return false;
    }

    private function assignedAppendExpression(Node $scope, string $variable, Node $expression): bool
    {
        foreach (new NodeFinder()->findInstanceOf($scope, Assign::class) as $assign) {
            if (
                !$assign->var instanceof ArrayDimFetch
                || !$this->variableMatches($assign->var->var, ltrim($variable, '$'))
                || $assign->var->dim !== null
                || $assign->expr !== $expression
            ) {
                continue;
            }
            return true;
        }

        return false;
    }

    private function arrayAssignMatches(Assign $assign, string $variable, array $values): bool
    {
        if (!$this->variableMatches($assign->var, ltrim($variable, '$')) || !$assign->expr instanceof Array_) {
            return false;
        }
        if (count($assign->expr->items) !== count($values)) {
            return false;
        }
        foreach ($assign->expr->items as $index => $item) {
            if ($item === null || !$this->variableMatches($item->value, ltrim($values[$index], '$'))) {
                return false;
            }
        }

        return true;
    }

    private function returnedExpression(Node $scope, Node $expression): bool
    {
        foreach (new NodeFinder()->findInstanceOf($scope, Return_::class) as $return) {
            if ($return->expr === $expression) {
                return true;
            }
        }

        return false;
    }

    private function sealedCollisionMatches(?Node $node): bool
    {
        if (!$node instanceof Node\Expr\BinaryOp\BooleanOr) {
            return false;
        }
        $remaining = ['framework', 'operational'];
        foreach ([$node->left, $node->right] as $comparison) {
            if (!$comparison instanceof Node\Expr\BinaryOp\Identical) {
                return false;
            }
            foreach ($remaining as $index => $receiver) {
                if ($this->catalogIdComparisonMatches($comparison, $receiver)) {
                    unset($remaining[$index]);
                    continue 2;
                }
            }
            return false;
        }

        return $remaining === [];
    }

    private function catalogIdComparisonMatches(Node\Expr\BinaryOp\Identical $comparison, string $receiver): bool
    {
        return (
            $this->propertyMatches($comparison->left, '$definition', 'catalogId')
            && $this->propertyMatches($comparison->right, '$' . $receiver, 'catalogId')
            || $this->propertyMatches($comparison->right, '$definition', 'catalogId')
            && $this->propertyMatches($comparison->left, '$' . $receiver, 'catalogId')
        );
    }

    private function codecCallMatches(?MethodCall $call): bool
    {
        if (!$call instanceof MethodCall || count($call->args) !== 4 || !$this->variableMatches($call->var, 'codec')) {
            return false;
        }

        return (
            $call->name instanceof Identifier
            && $call->name->toString() === 'catalog'
            && $this->propertyMatches($call->args[0]->value, '$definition', 'catalogId')
            && $this->propertyMatches($call->args[1]->value, '$definition', 'catalogVersion')
            && $this->classConstMatches($call->args[2]->value, AuditSourceOwner::class, 'Application')
            && $this->propertyMatches($call->args[3]->value, '$definition', 'events')
        );
    }

    private function classConstMatches(?Node $node, string $class, string $constant): bool
    {
        return (
            $node instanceof ClassConstFetch
            && $node->class instanceof Name
            && $this->sameSymbol($this->resolvedName($node->class), $class)
            && $node->name instanceof Identifier
            && $node->name->toString() === $constant
        );
    }

    private function referenceCallMatches(?StaticCall $call, string $values): bool
    {
        return (
            $call instanceof StaticCall
            && count($call->args) === 2
            && $this->staticCallMatches($call, 'ReflectionReference', 'fromArrayElement')
            && $this->variableMatches($call->args[0]->value, $values)
            && $this->variableMatches($call->args[1]->value, 'key')
        );
    }

    private function hasCountGreaterThan(Node $scope, string $variable, int $limit): bool
    {
        foreach (new NodeFinder()->findInstanceOf($scope, Node\Expr\BinaryOp\Greater::class) as $comparison) {
            if (
                !$comparison->left instanceof FuncCall
                || !$comparison->right instanceof Node\Scalar\Int_
                || $comparison->right->value !== $limit
                || count($comparison->left->args) !== 1
                || !$comparison->left->name instanceof Name
                || strtolower($comparison->left->name->toString()) !== 'count'
                || !$this->variableMatches($comparison->left->args[0]->value, ltrim($variable, '$'))
            ) {
                continue;
            }
            return true;
        }
        return false;
    }

    private function resolvedName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return $resolved instanceof Name ? ltrim($resolved->toString(), '\\') : ltrim($name->toString(), '\\');
    }

    private function sameSymbol(string $left, string $right): bool
    {
        return strcasecmp(ltrim($left, '\\'), ltrim($right, '\\')) === 0;
    }
}
