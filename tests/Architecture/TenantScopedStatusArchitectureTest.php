<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Status\OperationStatusDetailResult;
use BlackOps\Internal\Status\OperationStatusSource;
use BlackOps\Internal\Status\OperationStatusSubject;
use BlackOps\Internal\Status\PostgreSqlOperationStatusSource;
use BlackOps\Internal\Status\TenantBoundOperationStatusSource;
use BlackOps\Internal\Status\TenantScopedOperationStatusSource;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusDeferredState;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReader;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusSubject;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
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
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class TenantScopedStatusArchitectureTest extends TestCase
{
    public function testScopedDeclarationsAndCallableSurfacesAreExact(): void
    {
        $interface = new ReflectionClass(TenantScopedOperationStatusSource::class);
        self::assertTrue($interface->isInterface());
        self::assertSame([OperationStatusSource::class], $interface->getInterfaceNames());
        self::assertSame(['findSubjectForTenant', 'readDetailForTenant'], $this->declaredMethods($interface));
        self::assertSame([], $this->declaredProperties($interface));

        $bound = new ReflectionClass(TenantBoundOperationStatusSource::class);
        self::assertTrue($bound->isFinal());
        self::assertTrue($bound->isReadOnly());
        self::assertSame([OperationStatusSource::class], $bound->getInterfaceNames());
        self::assertSame(
            ['__construct', '__debugInfo', '__serialize', '__unserialize', 'findSubject', 'readDetail', 'values'],
            $this->declaredMethods($bound),
        );
        self::assertSame(['snapshot'], $this->declaredProperties($bound));
        $snapshot = $bound->getProperty('snapshot');
        self::assertTrue($snapshot->isPrivate());
        self::assertFalse($snapshot->isStatic());
        self::assertFalse($snapshot->isPromoted());
        self::assertSame('SensitiveParameterValue', $snapshot->getType()?->getName());

        $source = new ReflectionClass(PostgreSqlOperationStatusSource::class);
        self::assertTrue($source->isFinal());
        self::assertTrue($source->isReadOnly());
        self::assertSame(
            [OperationStatusSource::class, TenantScopedOperationStatusSource::class],
            $source->getInterfaceNames(),
        );
        self::assertSame(
            ['__construct', 'findSubject', 'findSubjectForTenant', 'readDetail', 'readDetailForTenant'],
            $this->declaredMethods($source, ReflectionMethod::IS_PUBLIC),
        );

        $reader = new ReflectionClass(PostgreSqlStatusReader::class);
        self::assertTrue($reader->isFinal());
        self::assertTrue($reader->isReadOnly());
        self::assertSame(
            [
                '__construct',
                'deadLetterExists',
                'deadLetterExistsForTenant',
                'deferredState',
                'deferredStateForTenant',
                'findSubject',
                'findSubjectForTenant',
                'outcomeExists',
                'outcomeExistsForTenant',
                'purgeTargets',
                'purgeTargetsForTenant',
            ],
            $this->declaredMethods($reader, ReflectionMethod::IS_PUBLIC),
        );

        $this->assertMethod(
            $interface->getMethod('findSubjectForTenant'),
            'public',
            '?' . OperationStatusSubject::class,
            [
                ['operationId', OperationId::class, 1],
                ['tenant', '?' . TenantRef::class, 1],
            ],
        );
        $this->assertMethod(
            $interface->getMethod('readDetailForTenant'),
            'public',
            OperationStatusDetailResult::class,
            [
                ['subject', OperationStatusSubject::class, 1],
                ['tenant', '?' . TenantRef::class, 1],
            ],
        );

        $this->assertMethod(
            $bound->getMethod('__construct'),
            'public',
            null,
            [
                ['source', TenantScopedOperationStatusSource::class, 1],
                ['tenant', '?' . TenantRef::class, 1],
            ],
        );
        $this->assertMethod(
            $bound->getMethod('findSubject'),
            'public',
            '?' . OperationStatusSubject::class,
            [['operationId', OperationId::class, 1]],
        );
        $this->assertMethod(
            $bound->getMethod('readDetail'),
            'public',
            OperationStatusDetailResult::class,
            [['subject', OperationStatusSubject::class, 1]],
        );
        $this->assertMethod($bound->getMethod('__debugInfo'), 'public', 'array', []);
        $this->assertMethod($bound->getMethod('__serialize'), 'public', 'array', []);
        $this->assertMethod($bound->getMethod('__unserialize'), 'public', 'void', [['data', 'array', 1]]);
        $this->assertMethod($bound->getMethod('values'), 'private', 'array', []);

        $this->assertMethod(
            $reader->getMethod('deferredStateForTenant'),
            'public',
            '?' . PostgreSqlStatusDeferredState::class,
            [
                ['operationId', OperationId::class, 1],
                ['tenant', '?' . TenantRef::class, 1],
            ],
        );
        $this->assertMethod(
            $reader->getMethod('outcomeExistsForTenant'),
            'public',
            'bool',
            [
                ['operationId', OperationId::class, 1],
                ['tenant', '?' . TenantRef::class, 1],
            ],
        );
        $this->assertMethod(
            $reader->getMethod('deadLetterExistsForTenant'),
            'public',
            'bool',
            [
                ['operationId', OperationId::class, 1],
                ['tenant', '?' . TenantRef::class, 1],
            ],
        );
        $this->assertMethod(
            $reader->getMethod('purgeTargetsForTenant'),
            'public',
            'array',
            [
                ['operationId', OperationId::class, 1],
                ['tenant', '?' . TenantRef::class, 1],
            ],
        );
        $this->assertMethod(
            $reader->getMethod('findSubjectForTenant'),
            'public',
            '?' . PostgreSqlStatusSubject::class,
            [
                ['operationId', OperationId::class, 0],
                ['tenant', '?' . TenantRef::class, 0],
            ],
        );
    }

    public function testSensitivePrivateScopeMatrixAndBoundSnapshotContractAreExact(): void
    {
        $source = new ReflectionClass(PostgreSqlOperationStatusSource::class);
        foreach ([
            'readDetailSnapshot' => [1, 1],
            'deferred' => [1, 1, 1, 1],
            'deferredOutcome' => [1, 1, 1, 1],
            'deferredState' => [1, 1],
            'purgeTargets' => [1, 1],
            'records' => [1, 1],
            'outcomeExists' => [1, 1],
            'deadLetterExists' => [1, 1],
            'outcome' => [1, 1],
            'readSubject' => [1, 1],
        ] as $methodName => $expected) {
            $method = $source->getMethod($methodName);
            self::assertTrue($method->isPrivate(), $methodName);
            $actual = array_map(
                static fn(ReflectionParameter $parameter): int => count($parameter->getAttributes(\SensitiveParameter::class)),
                $method->getParameters(),
            );
            self::assertSame($expected, $actual, $methodName);
        }

        $reader = new ReflectionClass(PostgreSqlStatusReader::class);
        foreach ([
            'deferredStateForScope',
            'purgeTargetsForScope',
            'transportSubject',
            'journalSubject',
            'rowExists',
        ] as $name) {
            $method = $reader->getMethod($name);
            $parameters = $method->getParameters();
            $operationIndex = $name === 'rowExists' ? 1 : 0;
            self::assertSame(1, count($parameters[$operationIndex]->getAttributes(\SensitiveParameter::class)), $name);
            self::assertSame(
                1,
                count($parameters[$operationIndex + 1]->getAttributes(\SensitiveParameter::class)),
                $name,
            );
        }

        $boundSource = $this->readSource('src/Internal/Status/TenantBoundOperationStatusSource.php');
        self::assertStringContainsString(
            '/** @return array{source: TenantScopedOperationStatusSource, tenant: TenantRef|null} */',
            $boundSource,
        );
        self::assertStringContainsString('private \\SensitiveParameterValue $snapshot;', $boundSource);
        self::assertStringNotContainsString('function __clone', $boundSource);
        self::assertSame([], $this->publicApiReferences($this->statusSources()));
    }

    public function testScopeSQLSnapshotAndLegacyCompositionOracleIsGreen(): void
    {
        $sources = $this->statusSources();
        self::assertSame([], $this->sourceViolations($sources));
        self::assertSame([], $this->productionCallerViolations($this->productionSources()));

        $source = $sources['src/Internal/Status/PostgreSqlOperationStatusSource.php'];
        $findSubject = $this->methodBody($source, 'findSubject');
        $readDetail = $this->methodBody($source, 'readDetail');
        self::assertIsString($findSubject);
        self::assertIsString($readDetail);
        self::assertStringContainsString('readSubject($operationId, null)', $findSubject);
        self::assertStringContainsString('readDetailSnapshot($subject, null)', $readDetail);

        $reader = $sources['src/Transport/PostgreSql/PostgreSqlStatusReader.php'];
        self::assertStringContainsString(
            'transportSubject($operationId)',
            $this->methodBody($reader, 'findSubject') ?? '',
        );
        self::assertStringContainsString(
            'journalSubject($operationId)',
            $this->methodBody($reader, 'findSubject') ?? '',
        );
        self::assertStringNotContainsString(
            'IS NOT DISTINCT FROM :tenant_type',
            $this->methodBody($reader, 'findSubject') ?? '',
        );

        $inertSources = $sources;
        $inertSources['src/Internal/Status/TenantScopedOperationStatusSource.php'] .= <<<'PHP'

            // interface TenantScopedOperationStatusSource extends OperationStatusSource
            $inertInterfaceText = 'public function unexpected(): void {}';
            PHP;
        $inertSources['src/Transport/PostgreSql/PostgreSqlStatusReader.php'] .= <<<'PHP'

            // operation_id = :operation_id
            $inertReaderText = 'tenant_type IS NOT DISTINCT FROM :tenant_type';
            PHP;
        $inertSources['src/Internal/Status/TenantBoundOperationStatusSource.php'] .= <<<'PHP'

            // PublicApi
            $inertText = 'PublicApi';
            PHP;
        $this->assertSyntaxValid($inertSources, 'inert architecture text');
        self::assertSame([], $this->sourceViolations($inertSources));
        self::assertSame([], $this->publicApiReferences($inertSources));
    }

    public function testSyntaxValidMutationsAreRejectedByTheArchitectureOracle(): void
    {
        $sources = $this->statusSources();
        $interface = $sources['src/Internal/Status/TenantScopedOperationStatusSource.php'];
        $source = $sources['src/Internal/Status/PostgreSqlOperationStatusSource.php'];
        $reader = $sources['src/Transport/PostgreSql/PostgreSqlStatusReader.php'];
        $bound = $sources['src/Internal/Status/TenantBoundOperationStatusSource.php'];
        $productionSources = $this->productionSources();

        $mutations = [
            'scoped null falls back to legacy marker' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    "\$scope = ['tenant' => \$tenant];",
                    '$scope = null;',
                    $source,
                ),
            ],
            'scoped interface inheritance is removed' => [
                ...$sources,
                'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->replaceOnce(
                    'interface TenantScopedOperationStatusSource extends OperationStatusSource',
                    'interface TenantScopedOperationStatusSource',
                    $interface,
                ),
            ],
            'scoped interface gains an extra parent interface' => [
                ...$sources,
                'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->replaceOnce(
                    'interface TenantScopedOperationStatusSource extends OperationStatusSource',
                    'interface TenantScopedOperationStatusSource extends OperationStatusSource, \\Stringable',
                    $interface,
                ),
            ],
            'scoped interface method is renamed' => [
                ...$sources,
                'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->replaceOnce(
                    'public function readDetailForTenant(',
                    'public function readDetailForTenantRemoved(',
                    $interface,
                ),
            ],
            'scoped interface gains an extra method' => [
                ...$sources,
                'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->insertBeforeFinalBrace(
                    $interface,
                    "\n    public function unexpected(): void;\n",
                ),
            ],
            'scoped interface parameter type changes' => [
                ...$sources,
                'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->replaceOnce(
                    '?TenantRef $tenant',
                    'string $tenant',
                    $interface,
                ),
            ],
            'scoped interface loses a sensitive attribute' => [
                ...$sources,
                'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->replaceOnce(
                    '#[\\SensitiveParameter]' . "\n        OperationId " . '$operationId',
                    'OperationId $operationId',
                    $interface,
                ),
            ],
            'scoped interface declaration is hidden in a comment' => [
                ...$sources,
                'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->replaceOnce(
                    'interface TenantScopedOperationStatusSource extends OperationStatusSource',
                    '/* interface TenantScopedOperationStatusSource extends OperationStatusSource */'
                    . ' interface TenantScopedOperationStatusSource',
                    $interface,
                ),
            ],
            'tenant type predicate omitted' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    'tenant_type IS NOT DISTINCT FROM :tenant_type',
                    '',
                    $reader,
                ),
            ],
            'tenant ID predicate omitted' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    'tenant_id IS NOT DISTINCT FROM :tenant_id',
                    '',
                    $reader,
                ),
            ],
            'tenant ID predicate exists only in inert text' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    " AND tenant_id IS NOT DISTINCT FROM :tenant_id';",
                    "';\n            \$decoy = 'tenant_id IS NOT DISTINCT FROM :tenant_id';",
                    $reader,
                ),
            ],
            'operation predicate omitted' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    'operation_id = :operation_id',
                    'operation_type = :operation_type',
                    $reader,
                ),
            ],
            'operation predicate exists only in inert text' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    "operation_id = :operation_id';",
                    "';\n        \$decoy = 'operation_id = :operation_id';",
                    $reader,
                ),
            ],
            'scoped SQL where is reset before fetch' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    "            \$params = [...\$params, ...\$scope];\n        }\n        try {",
                    "            \$params = [...\$params, ...\$scope];\n        }\n        \$where = 'operation_id = :operation_id';\n        try {",
                    $reader,
                ),
            ],
            'scoped SQL params are reset before fetch' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    "            \$params = [...\$params, ...\$scope];\n        }\n        try {",
                    "            \$params = [...\$params, ...\$scope];\n        }\n        \$params = ['operation_id' => \$operationId->toString()];\n        try {",
                    $reader,
                ),
            ],
            'tenant scope builder returns an empty scope' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    "return [\n            'tenant_type' => \$tenant?->type(),\n            'tenant_id' => \$tenant?->id(),\n        ];",
                    'return [];',
                    $reader,
                ),
            ],
            'scoped Journal falls back to unscoped records' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    "recordsForTenant(\$operationId, \$scope['tenant'])",
                    'records($operationId)',
                    $source,
                ),
            ],
            'scoped Outcome falls back to unscoped lookup' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    "findForTenant(\$id, \$scope['tenant'])",
                    'find($id)',
                    $source,
                ),
            ],
            'deferred Outcome scope is dropped' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    '$this->deferredOutcome($state, $journal, $snapshot, $scope)',
                    '$this->deferredOutcome($state, $journal, $snapshot)',
                    $source,
                ),
            ],
            'hidden deferred Outcome lookup drops scope' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    '$this->outcome($state->operationId, $scope)',
                    '$this->outcome($state->operationId, null)',
                    $source,
                ),
            ],
            'scoped marker is overwritten after authorization' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    "        \$scope = ['tenant' => \$tenant];",
                    "        \$scope = ['tenant' => \$tenant];\n        \$scope = null;",
                    $source,
                ),
            ],
            'snapshot subject recheck is omitted' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    '$snapshotSubject = $this->readSubject($subject->operationId, $scope);',
                    '$snapshotSubject = null;',
                    $source,
                ),
            ],
            'snapshot subject recheck moves after protected projection' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->moveSnapshotSubjectRead($source),
            ],
            'snapshot identity recheck moves after protected projection' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' =>
                    $this->moveSubjectIdentityCheckAfterProjection($source),
            ],
            'scoped state guards are replaced by inert expressions' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceScopedLevel0Guards($source),
            ],
            'bound view gains a clone override' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->insertBeforeFinalBrace(
                    $bound,
                    "\n    public function __clone(): void {}\n",
                ),
            ],
            'bound view loses final modifier' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'final readonly class TenantBoundOperationStatusSource',
                    'readonly class TenantBoundOperationStatusSource',
                    $bound,
                ),
            ],
            'bound view loses readonly modifier' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'final readonly class TenantBoundOperationStatusSource',
                    'final class TenantBoundOperationStatusSource',
                    $bound,
                ),
            ],
            'bound view gains a superclass' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'final readonly class TenantBoundOperationStatusSource implements OperationStatusSource',
                    'final readonly class TenantBoundOperationStatusSource extends \\stdClass'
                    . ' implements OperationStatusSource',
                    $bound,
                ),
            ],
            'bound view gains a trait' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    "{\n    private \\SensitiveParameterValue " . '$snapshot;',
                    "{\n    use \\SomeInertTrait;\n\n    private \\SensitiveParameterValue " . '$snapshot;',
                    $bound,
                ),
            ],
            'bound view gains a public callable' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->insertBeforeFinalBrace(
                    $bound,
                    "\n    public function unexpected(): void {}\n",
                ),
            ],
            'bound view loses a constructor sensitive attribute' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    '#[\\SensitiveParameter]' . "\n        TenantScopedOperationStatusSource " . '$source',
                    'TenantScopedOperationStatusSource $source',
                    $bound,
                ),
            ],
            'bound view property type changes' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'private \\SensitiveParameterValue $snapshot;',
                    'private array $snapshot;',
                    $bound,
                ),
            ],
            'bound view gains an extra property' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->insertBeforeFinalBrace(
                    $bound,
                    "\n    private string " . '$unexpected;' . "\n",
                ),
            ],
            'bound view loses its snapshot property' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'private \\SensitiveParameterValue $snapshot;',
                    '',
                    $bound,
                ),
            ],
            'bound view gains a promoted constructor property' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'TenantScopedOperationStatusSource $source,',
                    'private TenantScopedOperationStatusSource $source,',
                    $bound,
                ),
            ],
            'bound view read subject visibility changes' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'public function findSubject(#[\\SensitiveParameter] OperationId $operationId)',
                    'private function findSubject(#[\\SensitiveParameter] OperationId $operationId)',
                    $bound,
                ),
            ],
            'bound view read subject type changes' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    'OperationId $operationId): ?OperationStatusSubject',
                    'string $operationId): ?OperationStatusSubject',
                    $bound,
                ),
            ],
            'bound view constructor performs I/O' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    "    ) {\n        \$this->snapshot",
                    "    ) {\n        \$source->findSubjectForTenant(\$operationId, \$tenant);\n        \$this->snapshot",
                    $bound,
                ),
            ],
            'bound view constructor performs an arbitrary call' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    "    ) {\n        \$this->snapshot",
                    "    ) {\n        file_get_contents('/tmp/inert');\n        \$this->snapshot",
                    $bound,
                ),
            ],
            'bound values documentation changes' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    '/** @return array{source: TenantScopedOperationStatusSource, tenant: TenantRef|null} */',
                    '/** @return array<string, mixed> */',
                    $bound,
                ),
            ],
            'bound debug information leaks the snapshot' => [
                ...$sources,
                'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->replaceOnce(
                    "    public function __debugInfo(): array\n    {\n        return [];\n    }",
                    "    public function __debugInfo(): array\n    {\n        return \$this->values();\n    }",
                    $bound,
                ),
            ],
            'source gains mutable scope state' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    '    private PostgreSqlStatusReader $reader;',
                    "    private ?TenantRef \$currentTenant;\n    private PostgreSqlStatusReader \$reader;",
                    $source,
                ),
            ],
            'source loses final modifier' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    'final readonly class PostgreSqlOperationStatusSource',
                    'readonly class PostgreSqlOperationStatusSource',
                    $source,
                ),
            ],
            'source gains a trait' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    "{\n    private PostgreSqlStatusReader " . '$reader;',
                    "{\n    use \\SomeInertTrait;\n\n    private PostgreSqlStatusReader " . '$reader;',
                    $source,
                ),
            ],
            'source gains a trait after its first property' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    "    private PostgreSqlStatusReader \$reader;\n",
                    "    private PostgreSqlStatusReader \$reader;\n\n    use \\SomeInertTrait;\n",
                    $source,
                ),
            ],
            'source gains an extra public method' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->insertBeforeFinalBrace(
                    $source,
                    "\n    public function unexpected(): void {}\n",
                ),
            ],
            'source gains an extra private method' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->insertBeforeFinalBrace(
                    $source,
                    "\n    private function hidden(): void {}\n",
                ),
            ],
            'source constructor gains a promoted optional tenant' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    "        string \$schema = 'blackops',",
                    "        private ?TenantRef \$tenant = null,",
                    $source,
                ),
            ],
            'source loses readonly modifier' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    'final readonly class PostgreSqlOperationStatusSource',
                    'final class PostgreSqlOperationStatusSource',
                    $source,
                ),
            ],
            'source scoped helper loses a sensitive attribute' => [
                ...$sources,
                'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->replaceOnce(
                    '#[\\SensitiveParameter]' . "\n        OperationId " . '$operationId',
                    'OperationId $operationId',
                    $source,
                ),
            ],
            'reader gains a public callable' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->insertBeforeFinalBrace(
                    $reader,
                    "\n    public function unexpected(): void {}\n",
                ),
            ],
            'reader loses final modifier' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    'final readonly class PostgreSqlStatusReader',
                    'readonly class PostgreSqlStatusReader',
                    $reader,
                ),
            ],
            'reader loses readonly modifier' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    'final readonly class PostgreSqlStatusReader',
                    'final class PostgreSqlStatusReader',
                    $reader,
                ),
            ],
            'reader scoped callable visibility changes' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    'public function outcomeExistsForTenant(',
                    'private function outcomeExistsForTenant(',
                    $reader,
                ),
            ],
            'reader scoped callable type changes' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    "public function deferredStateForTenant(\n        #[\\SensitiveParameter]\n        OperationId "
                    . '$operationId',
                    "public function deferredStateForTenant(\n        #[\\SensitiveParameter]\n        string "
                    . '$operationId',
                    $reader,
                ),
            ],
            'reader loses a scoped sensitive attribute' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    '#[\\SensitiveParameter]' . "\n        OperationId " . '$operationId',
                    'OperationId $operationId',
                    $reader,
                ),
            ],
            'purge scoped return documentation is removed' => [
                ...$sources,
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->replaceOnce(
                    "/** @return list<RetentionPurgeTarget> */\n    public function purgeTargetsForTenant(",
                    'public function purgeTargetsForTenant(',
                    $reader,
                ),
            ],
        ];

        foreach ($mutations as $label => $candidate) {
            $this->assertSyntaxValid($candidate, $label);
            self::assertNotEmpty($this->sourceViolations($candidate), $label . ' was accepted');
        }

        $productionMutation = $productionSources;
        $productionMutation['src/Internal/Status/ExtraCaller.php'] = <<<'PHP'
            <?php

            $source->findSubjectForTenant($operationId, null);
            $source->readDetailForTenant($subject, null);
            PHP;
        $this->assertSyntaxValid($productionMutation, 'extra production caller');
        self::assertNotEmpty(
            $this->productionCallerViolations($productionMutation),
            'an extra production scoped-source caller was accepted',
        );

        $subjectReaderMutation = $productionSources;
        $subjectReaderMutation['src/Internal/Status/ExtraSubjectReaderCaller.php'] = <<<'PHP'
            <?php

            $reader->findSubjectForTenant($operationId, $tenant);
            PHP;
        $this->assertSyntaxValid($subjectReaderMutation, 'extra subject reader caller');
        self::assertNotEmpty(
            $this->productionCallerViolations($subjectReaderMutation),
            'an extra production subject reader caller was accepted',
        );

        $readerMutation = $productionSources;
        $readerMutation['src/Internal/Status/ExtraReaderCaller.php'] = <<<'PHP'
            <?php

            $reader->deferredStateForTenant($operationId, $tenant);
            PHP;
        $this->assertSyntaxValid($readerMutation, 'extra reader scoped caller');
        self::assertNotEmpty(
            $this->productionCallerViolations($readerMutation),
            'an extra production reader scoped caller was accepted',
        );

        $constructorMutation = $productionSources;
        $constructorMutation['src/Internal/Status/ExtraBoundConstruction.php'] = <<<'PHP'
            <?php

            new \BlackOps\Internal\Status\TenantBoundOperationStatusSource($source, null);
            PHP;
        $this->assertSyntaxValid($constructorMutation, 'extra bound construction');
        self::assertNotEmpty(
            $this->productionCallerViolations($constructorMutation),
            'an extra production bound-view construction was accepted',
        );

        $aliasConstruction = $productionSources;
        $aliasConstruction['src/Internal/Status/ExtraBoundAlias.php'] = <<<'PHP'
            <?php

            namespace BlackOpsTestsArchitecture;

            use BlackOps\Internal\Status\TenantBoundOperationStatusSource as ScopedView;

            new ScopedView($source, null);
            PHP;
        $this->assertSyntaxValid($aliasConstruction, 'aliased bound construction');
        self::assertNotEmpty(
            $this->productionCallerViolations($aliasConstruction),
            'an aliased production bound-view construction was accepted',
        );

        $nullsafeMutation = $productionSources;
        $nullsafeMutation['src/Internal/Status/ExtraNullsafeCaller.php'] = <<<'PHP'
            <?php

            $source?->findSubjectForTenant($operationId, null);
            $source?->readDetailForTenant($subject, null);
            PHP;
        $this->assertSyntaxValid($nullsafeMutation, 'nullsafe production caller');
        self::assertNotEmpty(
            $this->productionCallerViolations($nullsafeMutation),
            'nullsafe production scoped callers were accepted',
        );

        $unrelatedAliasConstruction = $productionSources;
        $unrelatedAliasConstruction['src/Internal/Status/UnrelatedBoundAlias.php'] = <<<'PHP'
            <?php

            use Vendor\Unrelated\TenantBoundOperationStatusSource as UnrelatedView;

            new UnrelatedView($source, null);
            PHP;
        $this->assertSyntaxValid($unrelatedAliasConstruction, 'unrelated same-basename construction');
        self::assertSame(
            [],
            $this->productionCallerViolations($unrelatedAliasConstruction),
            'an unrelated same-basename construction was rejected',
        );

        $caseMutation = $productionSources;
        $caseMutation['src/Internal/Status/ExtraCaseVariantCaller.php'] = <<<'PHP'
            <?php

            $source->FINDSUBJECTFORTENANT($operationId, null);
            $source?->ReAdDeTaIlFoRtEnAnT($subject, null);
            PHP;
        $this->assertSyntaxValid($caseMutation, 'case-variant production caller');
        self::assertNotEmpty(
            $this->productionCallerViolations($caseMutation),
            'case-variant production scoped callers were accepted',
        );

        $literalBracedOrdinaryMutation = $productionSources;
        $literalBracedOrdinaryMutation['src/Internal/Status/ExtraLiteralBracedOrdinaryCaller.php'] = <<<'PHP'
            <?php

            $source->{'findSubjectForTenant'}($operationId, null);
            PHP;
        $this->assertSyntaxValid($literalBracedOrdinaryMutation, 'literal-braced ordinary production caller');
        self::assertNotEmpty(
            $this->productionCallerViolations($literalBracedOrdinaryMutation),
            'literal-braced ordinary production scoped caller was accepted',
        );

        $literalBracedNullsafeMutation = $productionSources;
        $literalBracedNullsafeMutation['src/Internal/Status/ExtraLiteralBracedNullsafeCaller.php'] = <<<'PHP'
            <?php

            $source?->{'readDetailForTenant'}($subject, null);
            PHP;
        $this->assertSyntaxValid($literalBracedNullsafeMutation, 'literal-braced nullsafe production caller');
        self::assertNotEmpty(
            $this->productionCallerViolations($literalBracedNullsafeMutation),
            'literal-braced nullsafe production scoped caller was accepted',
        );

        $classConstantMutation = $productionSources;
        $classConstantMutation['src/Internal/Status/ExtraClassConstantConstruction.php'] = <<<'PHP'
            <?php

            new (\BlackOps\Internal\Status\TenantBoundOperationStatusSource::class)($source, null);
            PHP;
        $this->assertSyntaxValid($classConstantMutation, 'class-constant bound construction');
        self::assertNotEmpty(
            $this->productionCallerViolations($classConstantMutation),
            'class-constant bound-view construction was accepted',
        );

        $unrelatedClassConstantMutation = $productionSources;
        $unrelatedClassConstantMutation['src/Internal/Status/UnrelatedClassConstantConstruction.php'] = <<<'PHP'
            <?php

            new (\Vendor\Unrelated\TenantBoundOperationStatusSource::class)($source, null);
            PHP;
        $this->assertSyntaxValid($unrelatedClassConstantMutation, 'unrelated class-constant construction');
        self::assertSame(
            [],
            $this->productionCallerViolations($unrelatedClassConstantMutation),
            'an unrelated class-constant construction was rejected',
        );

        $inertBracedMutation = $productionSources;
        $inertBracedMutation['src/Internal/Status/InertBracedCallText.php'] = <<<'PHP'
            <?php

            $text = '$source->{"findSubjectForTenant"}($operationId, null);';
            PHP;
        $this->assertSyntaxValid($inertBracedMutation, 'inert literal-braced call text');
        self::assertSame(
            [],
            $this->productionCallerViolations($inertBracedMutation),
            'inert literal-braced call text was rejected',
        );

        $staticMethodMutation = $sources;
        $staticMethodMutation['src/Internal/Status/TenantBoundOperationStatusSource.php'] = $this->insertBeforeFinalBrace(
            $bound,
            "\n    public static function unexpected(): void {}\n",
        );
        $this->assertSyntaxValid($staticMethodMutation, 'extra public static bound-view method');
        self::assertNotEmpty(
            $this->sourceViolations($staticMethodMutation),
            'an extra public static bound-view method was accepted',
        );

        $inertStaticMethodMutation = $sources;
        $inertStaticMethodMutation['src/Internal/Status/TenantBoundOperationStatusSource.php'] = $this->insertBeforeFinalBrace(
            $bound,
            "\n    // public static function unexpected(): void {}\n",
        );
        $this->assertSyntaxValid($inertStaticMethodMutation, 'inert static bound-view method text');
        self::assertSame([], $this->sourceViolations($inertStaticMethodMutation));
    }

    public function testInertCommentsAndStringsDoNotBecomeProductionCallers(): void
    {
        $sources = $this->productionSources();
        $sources['src/Internal/Status/InertText.php'] = <<<'PHP'
            <?php

            // $source->findSubjectForTenant($operationId, null);
            $text = 'new TenantBoundOperationStatusSource($source, null)';
            PHP;
        $sources['src/Internal/Status/InertUppercaseText.php'] = <<<'PHP'
            <?php

            $text = '$source->FINDSUBJECTFORTENANT($operationId, null);';
            $text .= '$source?->ReAdDeTaIlFoRtEnAnT($subject, null);';
            PHP;
        self::assertSame([], $this->productionCallerViolations($sources));
    }

    /** @return array<string,string> */
    private function statusSources(): array
    {
        return [
            'src/Internal/Status/TenantScopedOperationStatusSource.php' => $this->readSource(
                'src/Internal/Status/TenantScopedOperationStatusSource.php',
            ),
            'src/Internal/Status/TenantBoundOperationStatusSource.php' => $this->readSource(
                'src/Internal/Status/TenantBoundOperationStatusSource.php',
            ),
            'src/Internal/Status/PostgreSqlOperationStatusSource.php' => $this->readSource(
                'src/Internal/Status/PostgreSqlOperationStatusSource.php',
            ),
            'src/Transport/PostgreSql/PostgreSqlStatusReader.php' => $this->readSource(
                'src/Transport/PostgreSql/PostgreSqlStatusReader.php',
            ),
        ];
    }

    /** @return array<string,string> */
    private function productionSources(): array
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertNotFalse($root);
        $sources = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src'));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            self::assertIsString($relative);
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source, $relative);
            $sources[$relative] = $source;
        }

        return $sources;
    }

    private function readSource(string $relative): string
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertNotFalse($root);
        $source = file_get_contents($root . '/' . $relative);
        self::assertIsString($source, $relative);

        return $source;
    }

    /** @param array<string,string> $sources */
    private function sourceViolations(array $sources): array
    {
        $violations = [];
        $interface = $sources['src/Internal/Status/TenantScopedOperationStatusSource.php'] ?? '';
        $bound = $sources['src/Internal/Status/TenantBoundOperationStatusSource.php'] ?? '';
        $source = $sources['src/Internal/Status/PostgreSqlOperationStatusSource.php'] ?? '';
        $reader = $sources['src/Transport/PostgreSql/PostgreSqlStatusReader.php'] ?? '';
        $interfaceCompact = $this->compactSource($interface);
        $boundCompact = $this->compactSource($bound);
        $readerCompact = $this->compactSource($reader);

        $this->requireSource(
            $this->declarationTokens($interface, 'interface', 'TenantScopedOperationStatusSource') === [
                'interface',
                'TenantScopedOperationStatusSource',
                'extends',
                'OperationStatusSource',
            ],
            'interface inheritance',
            $violations,
        );
        $this->requireSource(
            $this->methodNamesFromSource($interface) === ['findSubjectForTenant', 'readDetailForTenant'],
            'interface callable inventory',
            $violations,
        );
        $this->requireSource(!preg_match('/\bconst\b/', $interfaceCompact), 'interface constants', $violations);
        $this->requireSource(
            str_contains($interfaceCompact, '#[\\SensitiveParameter]')
            && substr_count($interfaceCompact, '#[\\SensitiveParameter]') === 4,
            'interface sensitive attributes',
            $violations,
        );
        foreach ([
            'findSubjectForTenant' => 'functionfindSubjectForTenant(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?TenantRef$tenant,):?OperationStatusSubject',
            'readDetailForTenant' => 'functionreadDetailForTenant(#[\\SensitiveParameter]OperationStatusSubject$subject,#[\\SensitiveParameter]?TenantRef$tenant,):OperationStatusDetailResult',
        ] as $name => $expected) {
            $this->requireSource(
                $this->signatureMatches($interface, $name, $expected),
                'interface exact signature ' . $name,
                $violations,
            );
        }
        $this->requireSource(
            str_contains($bound, "/** @return array<never, never> */\n    public function __debugInfo(): array")
            && str_contains($bound, "/** @return array<never, never> */\n    public function __serialize(): array")
            && str_contains($bound, '/** @param array<never, never> $data */' . "\n    public function __unserialize("),
            'bound exact documentation',
            $violations,
        );
        $this->requireSource(
            str_contains(
                $bound,
                '/** @return array{source: TenantScopedOperationStatusSource, tenant: TenantRef|null} */'
                . "\n    private function values(): array",
            ),
            'bound values documentation',
            $violations,
        );
        $constructorSignature = $this->methodSignature($bound, '__construct');
        $this->requireSource(
            is_string($constructorSignature)
            && !str_contains($this->compactSource($constructorSignature), 'private')
            && !str_contains($this->compactSource($constructorSignature), 'protected')
            && !str_contains($this->compactSource($constructorSignature), 'public'),
            'bound constructor is not promoted',
            $violations,
        );

        $this->requireSource(
            $this->declarationTokens($bound, 'class', 'TenantBoundOperationStatusSource') === [
                'final',
                'readonly',
                'class',
                'TenantBoundOperationStatusSource',
                'implements',
                'OperationStatusSource',
            ]
            && !$this->hasTokenSequence($bound, ['class', 'TenantBoundOperationStatusSource', 'extends']),
            'bound declaration',
            $violations,
        );
        $this->requireSource(!$this->hasClassTraitUse($bound), 'bound trait exclusion', $violations);
        $this->requireSource(!preg_match('/\bconst\b/', $boundCompact), 'bound constants', $violations);
        $this->requireSource(
            $this->methodNamesFromSource($bound) === [
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'findSubject',
                'readDetail',
                'values',
            ],
            'bound callable inventory',
            $violations,
        );
        $this->requireSource(
            $this->methodVisibilityNames($bound, 'public') === [
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'findSubject',
                'readDetail',
            ],
            'bound public callable inventory',
            $violations,
        );
        $this->requireSource(
            $this->methodVisibilityNames($bound, 'private') === ['values'],
            'bound private callable inventory',
            $violations,
        );
        $this->requireSource(
            $this->propertyNamesFromSource($bound) === ['snapshot'],
            'bound property inventory',
            $violations,
        );
        $this->requireSource(
            $this->hasTokenSequence($bound, ['private', '\\SensitiveParameterValue', '$snapshot', ';']),
            'bound snapshot property type',
            $violations,
        );
        $this->requireSource(
            !$this->hasTokenSequence($bound, ['function', '__clone']),
            'bound clone override',
            $violations,
        );
        $constructor = $this->methodBody($bound, '__construct');
        $this->requireSource(
            is_string($constructor)
            && $this->compactSource($constructor)
                === '$this->snapshot=new\\SensitiveParameterValue([\'source\'=>$source,\'tenant\'=>$tenant,]);',
            'bound constructor has no I/O',
            $violations,
        );
        foreach ([
            '__construct' => 'function__construct(#[\\SensitiveParameter]TenantScopedOperationStatusSource$source,#[\\SensitiveParameter]?TenantRef$tenant,)',
            'findSubject' => 'functionfindSubject(#[\\SensitiveParameter]OperationId$operationId):?OperationStatusSubject',
            'readDetail' => 'functionreadDetail(#[\\SensitiveParameter]OperationStatusSubject$subject):OperationStatusDetailResult',
            '__debugInfo' => 'function__debugInfo():array',
            '__serialize' => 'function__serialize():array',
            '__unserialize' => 'function__unserialize(#[\\SensitiveParameter]array$data):void',
            'values' => 'functionvalues():array',
        ] as $name => $expected) {
            $this->requireSource(
                $this->signatureMatches($bound, $name, $expected),
                'bound exact signature ' . $name,
                $violations,
            );
        }
        $this->requireSource(
            $this->compactSource($this->methodBody($bound, 'values') ?? '')
            === 'try{$value=$this->snapshot->getValue();}catch(\\Throwable){throwOperationStatusSourceException::storageFailed();}'
                . 'if(!is_array($value)||array_keys($value)!==[\'source\',\'tenant\']||!$value[\'source\']instanceofTenantScopedOperationStatusSource||$value[\'tenant\']!==null&&!$value[\'tenant\']instanceofTenantRef){throwOperationStatusSourceException::storageFailed();}'
                . 'return[\'source\'=>$value[\'source\'],\'tenant\'=>$value[\'tenant\'],];',
            'bound snapshot validation',
            $violations,
        );
        $this->requireSource(
            $this->compactSource($this->methodBody($bound, 'findSubject') ?? '')
                === '$values=$this->values();return$values[\'source\']->findSubjectForTenant($operationId,$values[\'tenant\']);'
            && $this->compactSource($this->methodBody($bound, 'readDetail') ?? '')
                === '$values=$this->values();return$values[\'source\']->readDetailForTenant($subject,$values[\'tenant\']);',
            'bound scoped delegation',
            $violations,
        );
        foreach ([
            '__debugInfo' => 'return[];',
            '__serialize' => 'return[];',
            '__unserialize' => 'throwOperationStatusSourceException::storageFailed();',
        ] as $name => $expected) {
            $this->requireSource(
                $this->compactSource($this->methodBody($bound, $name) ?? '') === $expected,
                'bound protected body ' . $name,
                $violations,
            );
        }

        $this->requireSource(
            $this->declarationTokens($source, 'class', 'PostgreSqlOperationStatusSource') === [
                'final',
                'readonly',
                'class',
                'PostgreSqlOperationStatusSource',
                'implements',
                'OperationStatusSource',
                ',',
                'TenantScopedOperationStatusSource',
            ],
            'source declaration',
            $violations,
        );
        $this->requireSource(!$this->hasClassTraitUse($source), 'source trait exclusion', $violations);
        foreach ([
            'findSubjectForTenant' => 'functionfindSubjectForTenant(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?TenantRef$tenant,):?OperationStatusSubject',
            'readDetailForTenant' => 'functionreadDetailForTenant(#[\\SensitiveParameter]OperationStatusSubject$subject,#[\\SensitiveParameter]?TenantRef$tenant,):OperationStatusDetailResult',
            'findSubject' => 'functionfindSubject(OperationId$operationId):?OperationStatusSubject',
            'readDetail' => 'functionreadDetail(OperationStatusSubject$subject):OperationStatusDetailResult',
        ] as $name => $expected) {
            $this->requireSource(
                $this->signatureMatches($source, $name, $expected),
                'source exact signature ' . $name,
                $violations,
            );
        }
        $this->requireSource(
            $this->methodVisibilityNames($source, 'public') === [
                '__construct',
                'findSubject',
                'findSubjectForTenant',
                'readDetail',
                'readDetailForTenant',
            ],
            'source public callable inventory',
            $violations,
        );
        $this->requireSource(
            $this->signatureMatches(
                $source,
                '__construct',
                "function__construct(privateConnection\$connection,privateOperationRegistry\$registry,BopdEnvelopeCodec\$protection,string\$schema='blackops',privateOperationStatusJournalValidator\$validator=newOperationStatusJournalValidator(),)",
            ),
            'source exact signature __construct',
            $violations,
        );
        foreach ([
            'assertScopedLevel0' => 'try{if(!$this->connection->isAutoCommit()||$this->connection->isTransactionActive()||$this->connection->getTransactionNestingLevel()!==0){throwOperationStatusSourceException::storageFailed();}}catch(OperationStatusSourceException$exception){throw$exception;}catch(Throwable){throwOperationStatusSourceException::storageFailed();}',
            'assertScopedRootStarted' => 'try{if(!$this->connection->isAutoCommit()||!$this->connection->isTransactionActive()||$this->connection->getTransactionNestingLevel()!==1){throwOperationStatusSourceException::storageFailed();}}catch(OperationStatusSourceException$exception){throw$exception;}catch(Throwable){throwOperationStatusSourceException::storageFailed();}',
            'canRollbackScopedRoot' => 'try{return($this->connection->isAutoCommit()&&$this->connection->isTransactionActive()&&$this->connection->getTransactionNestingLevel()===1);}catch(Throwable){returnfalse;}',
        ] as $name => $expected) {
            $this->requireSource(
                $this->compactSource($this->methodBody($source, $name) ?? '') === $expected,
                'source exact state guard ' . $name,
                $violations,
            );
        }
        $this->requireSource(
            $this->methodNamesFromSource($source) === [
                '__construct',
                'assertScopedLevel0',
                'assertScopedRootStarted',
                'canRollbackScopedRoot',
                'containsDatabaseFailure',
                'deadLetterExists',
                'deadLetteredStatus',
                'deferred',
                'deferredOutcome',
                'deferredState',
                'failedStatus',
                'findSubject',
                'findSubjectForTenant',
                'isPreAcceptanceTerminal',
                'journalOnly',
                'journalOutcome',
                'outcome',
                'outcomeExists',
                'purgeTargets',
                'readDetail',
                'readDetailForTenant',
                'readDetailSnapshot',
                'readSubject',
                'readerFailure',
                'records',
                'rejectedStatus',
                'sameActor',
                'sameSubject',
                'sameTenant',
                'sameTime',
                'statusFromDeferred',
                'statusFromJournal',
                'statusReaderFailure',
                'uniqueTerminalRecord',
                'validateDeadLetter',
                'validateDeferredAttempt',
                'validateDeferredJournal',
                'validateExpectedOutcome',
                'validateOutcomeAbsence',
                'validateSubjectIdentity',
                'validateTransportRetention',
            ],
            'source callable inventory',
            $violations,
        );
        $this->requireSource(
            $this->signatureMatches(
                $source,
                '__construct',
                "function__construct(privateConnection\$connection,privateOperationRegistry\$registry,BopdEnvelopeCodec\$protection,string\$schema='blackops',privateOperationStatusJournalValidator\$validator=newOperationStatusJournalValidator(),)",
            ),
            'source exact signature __construct',
            $violations,
        );
        $this->requireSource($this->hasTokenSequence($this->methodBody($source, 'findSubjectForTenant') ?? '', [
            '$scope',
            '=',
            '[',
            "'tenant'",
            '=>',
            '$tenant',
            ']',
            ';',
        ]) && $this->hasTokenSequence($this->methodBody($source, 'readDetailForTenant') ?? '', [
            '$scope',
            '=',
            '[',
            "'tenant'",
            '=>',
            '$tenant',
            ']',
            ';',
        ]), 'closed scope marker', $violations);
        foreach (['findSubjectForTenant', 'readDetailForTenant'] as $name) {
            $body = $this->methodBody($source, $name);
            $scopeAssignments = is_string($body) ? $this->variableAssignmentPositions($body, '$scope') : [];
            $this->requireSource(
                is_string($body)
                && count($scopeAssignments) === 1
                && $this->hasTokenSequence($body, [
                    '$scope',
                    '=',
                    '[',
                    "'tenant'",
                    '=>',
                    '$tenant',
                    ']',
                    ';',
                ]),
                $name . ' single closed scope assignment',
                $violations,
            );
        }
        $this->requireSource(
            !preg_match(
                '/(?:public|protected|private)\s+[^;\n]*\$(?:scope|currentScope|currentTenant)\s*(?:=|;)/',
                $source,
            ),
            'mutable scope state',
            $violations,
        );
        $this->requireSource(!preg_match('/\bstatic\s+\$/', $source), 'static scope state', $violations);

        $findSubjectForTenant = $this->methodBody($source, 'findSubjectForTenant');
        $readDetailForTenant = $this->methodBody($source, 'readDetailForTenant');
        $snapshot = $this->methodBody($source, 'readDetailSnapshot');
        $deferred = $this->methodBody($source, 'deferred');
        $deferredOutcome = $this->methodBody($source, 'deferredOutcome');
        foreach ([
            'readDetailSnapshot' => 'functionreadDetailSnapshot(#[\\SensitiveParameter]OperationStatusSubject$subject,#[\\SensitiveParameter]?array$scope,):OperationStatusDetailResult',
            'deferred' => 'functiondeferred(#[\\SensitiveParameter]OperationStatusSubject$subject,#[\\SensitiveParameter]PostgreSqlStatusDeferredState$state,#[\\SensitiveParameter]OperationStatusSnapshot$snapshot,#[\\SensitiveParameter]?array$scope,):OperationStatusDetailResult',
            'deferredOutcome' => 'functiondeferredOutcome(#[\\SensitiveParameter]PostgreSqlStatusDeferredState$state,#[\\SensitiveParameter]?ValidatedOperationStatusJournal$journal,#[\\SensitiveParameter]OperationStatusSnapshot$snapshot,#[\\SensitiveParameter]?array$scope,):?Outcome',
            'deferredState' => 'functiondeferredState(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?array$scope,):?PostgreSqlStatusDeferredState',
            'purgeTargets' => 'functionpurgeTargets(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?array$scope,):array',
            'records' => 'functionrecords(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?array$scope,):array',
            'outcomeExists' => 'functionoutcomeExists(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?array$scope,):bool',
            'deadLetterExists' => 'functiondeadLetterExists(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?array$scope,):bool',
            'outcome' => 'functionoutcome(#[\\SensitiveParameter]string$operationId,#[\\SensitiveParameter]?array$scope,):?OutcomeRecord',
            'readSubject' => 'functionreadSubject(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?array$scope,):?OperationStatusSubject',
        ] as $name => $expected) {
            $this->requireSource(
                $this->signatureMatches($source, $name, $expected),
                'source exact signature ' . $name,
                $violations,
            );
        }
        $this->requireSource(
            is_string($findSubjectForTenant)
            && $this->hasTokenSequence($findSubjectForTenant, $this->tokenValues('readSubject($operationId, $scope)'))
            && !$this->hasTokenSequence($findSubjectForTenant, $this->tokenValues('readSubject($operationId)'))
            && is_string($readDetailForTenant)
            && $this->hasTokenSequence(
                $readDetailForTenant,
                $this->tokenValues('readSubject($subject->operationId, $scope)'),
            ),
            'scoped subject reads',
            $violations,
        );
        $this->requireSource(
            is_string($readDetailForTenant)
            && $this->hasTokenSequence(
                $readDetailForTenant,
                $this->tokenValues('readDetailSnapshot($snapshotSubject, $scope)'),
            )
            && $this->hasTokenSequence(
                $readDetailForTenant,
                $this->tokenValues('sameSubject($subject, $snapshotSubject)'),
            )
            && $this->hasTokenSequence($readDetailForTenant, $this->tokenValues('assertScopedRootStarted()'))
            && $this->tokenSequenceCount($readDetailForTenant, $this->tokenValues('assertScopedRootStarted()')) >= 2
            && $this->hasTokenSequence(
                $readDetailForTenant,
                $this->tokenValues('$this->assertScopedRootStarted(); try { $this->connection->commit();'),
            )
            && $this->hasTokenSequence(
                $readDetailForTenant,
                $this->tokenValues('instanceof PostgreSqlStatusReadFailed'),
            ),
            'scoped snapshot lifecycle',
            $violations,
        );
        $level0Call = $this->tokenValues('$this->assertScopedLevel0()');
        $findLevel0Positions = is_string($findSubjectForTenant)
            ? $this->tokenSequencePositions($findSubjectForTenant, $level0Call)
            : [];
        $detailLevel0Positions = is_string($readDetailForTenant)
            ? $this->tokenSequencePositions($readDetailForTenant, $level0Call)
            : [];
        $findReadPosition = is_string($findSubjectForTenant)
            ? $this->tokenSequencePosition(
                $findSubjectForTenant,
                $this->tokenValues('readSubject($operationId, $scope)'),
            )
            : null;
        $detailCommitPosition = is_string($readDetailForTenant)
            ? $this->tokenSequencePosition($readDetailForTenant, $this->tokenValues('$this->connection->commit()'))
            : null;
        $detailRollbackPosition = is_string($readDetailForTenant)
            ? $this->tokenSequencePosition($readDetailForTenant, $this->tokenValues('$this->connection->rollBack()'))
            : null;
        $this->requireSource(
            count($findLevel0Positions) === 5
            && count($detailLevel0Positions) === 3
            && is_int($findReadPosition)
            && $findLevel0Positions[0] < $findReadPosition
            && $findLevel0Positions[4] > $findReadPosition
            && is_int($detailCommitPosition)
            && is_int($detailRollbackPosition)
            && count(array_filter(
                $detailLevel0Positions,
                static fn(int $position): bool => $position > $detailCommitPosition,
            )) >= 1
            && count(array_filter(
                $detailLevel0Positions,
                static fn(int $position): bool => $position > $detailRollbackPosition,
            )) >= 1,
            'scoped entry and cleanup state guards',
            $violations,
        );
        $subjectReadPosition = is_string($readDetailForTenant)
            ? $this->tokenSequencePosition(
                $readDetailForTenant,
                $this->tokenValues('$snapshotSubject = $this->readSubject('),
            )
            : null;
        $projectionPosition = is_string($readDetailForTenant)
            ? $this->tokenSequencePosition($readDetailForTenant, $this->tokenValues('$this->readDetailSnapshot('))
            : null;
        $identityPosition = is_string($readDetailForTenant)
            ? $this->tokenSequencePosition(
                $readDetailForTenant,
                $this->tokenValues('sameSubject($subject, $snapshotSubject)'),
            )
            : null;
        $beginPosition = is_string($readDetailForTenant)
            ? $this->tokenSequencePosition($readDetailForTenant, $this->tokenValues('beginTransaction()'))
            : null;
        $this->requireSource(
            is_string($readDetailForTenant)
            && is_int($subjectReadPosition)
            && is_int($projectionPosition)
            && $subjectReadPosition < $projectionPosition
            && is_int($identityPosition)
            && $identityPosition < $projectionPosition
            && is_int($beginPosition)
            && $beginPosition < $subjectReadPosition,
            'snapshot subject recheck before protected projection',
            $violations,
        );
        $this->requireSource(
            is_string($snapshot)
            && $this->hasTokenSequence($snapshot, $this->tokenValues('deferredState($subject->operationId, $scope)'))
            && $this->hasTokenSequence($snapshot, $this->tokenValues('records($subject->operationId, $scope)'))
            && $this->hasTokenSequence($snapshot, $this->tokenValues('purgeTargets($subject->operationId, $scope)'))
            && $this->hasTokenSequence($snapshot, $this->tokenValues('outcomeExists($subject->operationId, $scope)'))
            && $this->hasTokenSequence($snapshot, $this->tokenValues('deadLetterExists($subject->operationId, $scope)'))
            && $this->hasTokenSequence($snapshot, $this->tokenValues('deferred($subject, $state, $snapshot, $scope)')),
            'snapshot scope threading',
            $violations,
        );
        $this->requireSource(
            is_string($deferred)
            && $this->hasTokenSequence(
                $deferred,
                $this->tokenValues('deferredOutcome($state, $journal, $snapshot, $scope)'),
            ),
            'deferred outcome scope threading',
            $violations,
        );
        $this->requireSource(
            is_string($deferredOutcome)
            && $this->hasTokenSequence(
                $deferredOutcome,
                $this->tokenValues('$this->outcome($state->operationId, $scope)'),
            )
            && !$this->hasTokenSequence(
                $deferredOutcome,
                $this->tokenValues('$this->outcome($state->operationId, null)'),
            ),
            'hidden deferred outcome scope threading',
            $violations,
        );

        foreach ([
            'deferredState' => [
                'reader->deferredState($operationId)',
                'reader->deferredStateForTenant($operationId, $scope[\'tenant\'])',
            ],
            'purgeTargets' => [
                'reader->purgeTargets($operationId)',
                'reader->purgeTargetsForTenant($operationId, $scope[\'tenant\'])',
            ],
            'records' => [
                'journal->records($operationId)',
                'journal->recordsForTenant($operationId, $scope[\'tenant\'])',
            ],
            'outcomeExists' => [
                'reader->outcomeExists($operationId)',
                'reader->outcomeExistsForTenant($operationId, $scope[\'tenant\'])',
            ],
            'deadLetterExists' => [
                'reader->deadLetterExists($operationId)',
                'reader->deadLetterExistsForTenant($operationId, $scope[\'tenant\'])',
            ],
            'outcome' => [
                'outcomes->find($id)',
                'outcomes->findForTenant($id, $scope[\'tenant\'])',
            ],
            'readSubject' => [
                'reader->findSubject($operationId)',
                'reader->findSubjectForTenant($operationId, $scope[\'tenant\'])',
            ],
        ] as $name => $needles) {
            $body = $this->methodBody($source, $name);
            $needlesTokens = array_map(fn(string $needle): array => $this->tokenValues($needle), $needles);
            $this->requireSource(
                is_string($body)
                && $this->hasTokenSequence($body, ['$scope', '===', 'null'])
                && $this->hasTokenSequence($body, $needlesTokens[0])
                && $this->hasTokenSequence($body, $needlesTokens[1]),
                $name . ' legacy/scoped branches',
                $violations,
            );
        }

        $this->requireSource(
            str_contains(
                $this->compactSource($this->methodBody($source, 'findSubject') ?? ''),
                'readSubject($operationId,null)',
            )
            && str_contains(
                $this->compactSource($this->methodBody($source, 'readDetail') ?? ''),
                'readDetailSnapshot($subject,null)',
            ),
            'legacy source marker',
            $violations,
        );
        $this->requireSource(
            is_string($readDetailForTenant)
            && $this->hasTokenSequence($readDetailForTenant, $this->tokenValues('beginTransaction()'))
            && $this->hasTokenSequence($readDetailForTenant, $this->tokenValues('commit()'))
            && $this->hasTokenSequence($readDetailForTenant, $this->tokenValues('rollBack()'))
            && $this->hasTokenSequence($readDetailForTenant, $this->tokenValues('canRollbackScopedRoot')),
            'owned root cleanup',
            $violations,
        );

        $this->requireSource(
            $this->declarationTokens($reader, 'class', 'PostgreSqlStatusReader') === [
                'final',
                'readonly',
                'class',
                'PostgreSqlStatusReader',
            ]
            && !$this->hasClassTraitUse($reader),
            'reader declaration and trait exclusion',
            $violations,
        );
        foreach ([
            '__construct' => "function__construct(privateConnection\$connection,string\$schema='blackops',)",
            'findSubject' => 'functionfindSubject(OperationId$operationId):?PostgreSqlStatusSubject',
            'findSubjectForTenant' => 'functionfindSubjectForTenant(OperationId$operationId,?TenantRef$tenant):?PostgreSqlStatusSubject',
            'deferredState' => 'functiondeferredState(OperationId$operationId):?PostgreSqlStatusDeferredState',
            'deferredStateForTenant' => 'functiondeferredStateForTenant(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?TenantRef$tenant,):?PostgreSqlStatusDeferredState',
            'outcomeExists' => 'functionoutcomeExists(OperationId$operationId):bool',
            'outcomeExistsForTenant' => 'functionoutcomeExistsForTenant(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?TenantRef$tenant,):bool',
            'deadLetterExists' => 'functiondeadLetterExists(OperationId$operationId):bool',
            'deadLetterExistsForTenant' => 'functiondeadLetterExistsForTenant(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?TenantRef$tenant,):bool',
            'purgeTargets' => 'functionpurgeTargets(OperationId$operationId):array',
            'purgeTargetsForTenant' => 'functionpurgeTargetsForTenant(#[\\SensitiveParameter]OperationId$operationId,#[\\SensitiveParameter]?TenantRef$tenant,):array',
        ] as $name => $expected) {
            $this->requireSource(
                $this->signatureMatches($reader, $name, $expected),
                'reader exact signature ' . $name,
                $violations,
            );
        }
        $this->requireSource(
            str_contains(
                $reader,
                "/** @return list<RetentionPurgeTarget> */\n    public function purgeTargetsForTenant(",
            )
            && str_contains($reader, "/** @return list<RetentionPurgeTarget> */\n    public function purgeTargets(")
            && str_contains($reader, "@param array{tenant_type?: string|null, tenant_id?: string|null} \$scope"),
            'reader return documentation',
            $violations,
        );
        $this->requireSource(
            str_contains($readerCompact, 'tenant_typeISNOTDISTINCTFROM:tenant_type')
            && str_contains($readerCompact, 'tenant_idISNOTDISTINCTFROM:tenant_id')
            && str_contains($readerCompact, 'operation_id=:operation_id'),
            'reader scoped predicates',
            $violations,
        );
        foreach ([
            'deferredStateForScope',
            'purgeTargetsForScope',
            'transportSubject',
            'journalSubject',
            'rowExists',
        ] as $name) {
            $body = $this->methodBody($reader, $name);
            $this->requireSource(
                is_string($body)
                && $this->hasTokenSequence($body, ['$where', '=', "'operation_id = :operation_id'", ';'])
                && $this->hasTokenSequence($body, [
                    '$where',
                    '.=',
                    "' AND tenant_type IS NOT DISTINCT FROM :tenant_type AND tenant_id IS NOT DISTINCT FROM :tenant_id'",
                    ';',
                ])
                && $this->hasTokenSequence($body, [
                    '$params',
                    '=',
                    '[',
                    '...',
                    '$params',
                    ',',
                    '...',
                    '$scope',
                    ']',
                    ';',
                ]),
                $name . ' exact predicates',
                $violations,
            );
            $this->requireSource(
                is_string($body)
                && $this->scopedQueryFlowIsExact($body, match ($name) {
                    'purgeTargetsForScope' => 'fetchAllAssociative',
                    'rowExists' => 'fetchOne',
                    default => 'fetchAssociative',
                }),
                $name . ' final scoped query flow',
                $violations,
            );
            if ($name === 'journalSubject') {
                $this->requireSource(
                    is_string($body)
                    && $this->hasTokenSequence($body, ['[', '...', '$subject', ',', '...', '$scope', ',', ']']),
                    $name . ' nested integrity scope parameters',
                    $violations,
                );
            }
        }
        foreach ([
            'findSubjectForTenant',
            'deferredStateForTenant',
            'outcomeExistsForTenant',
            'deadLetterExistsForTenant',
            'purgeTargetsForTenant',
        ] as $name) {
            $body = $this->methodBody($reader, $name);
            $this->requireSource(
                is_string($body) && $this->hasTokenSequence($body, ['tenantScope', '(', '$tenant', ')']),
                $name . ' closed reader scope',
                $violations,
            );
        }
        $tenantScopeBody = $this->methodBody($reader, 'tenantScope');
        $this->requireSource(
            $this->signatureMatches($reader, 'tenantScope', 'functiontenantScope(?TenantRef$tenant):array')
            && is_string($tenantScopeBody)
            && $this->tokenValues($tenantScopeBody) === [
                'return',
                '[',
                "'tenant_type'",
                '=>',
                '$tenant',
                '?->',
                'type',
                '(',
                ')',
                ',',
                "'tenant_id'",
                '=>',
                '$tenant',
                '?->',
                'id',
                '(',
                ')',
                ',',
                ']',
                ';',
            ],
            'closed tenant scope builder',
            $violations,
        );
        $purgeScopeBody = $this->methodBody($reader, 'purgeTargetsForScope');
        $purgeScopeCompact = is_string($purgeScopeBody) ? $this->compactSource($purgeScopeBody) : '';
        $this->requireSource(
            str_contains($purgeScopeCompact, 'ORDERBYpurged_atASC,audit_idASC')
            && !str_contains($readerCompact, '{$tenant'),
            'reader ordering and parameterization',
            $violations,
        );
        $this->requireSource(
            $this->methodVisibilityNames($reader, 'public') === [
                '__construct',
                'deadLetterExists',
                'deadLetterExistsForTenant',
                'deferredState',
                'deferredStateForTenant',
                'findSubject',
                'findSubjectForTenant',
                'outcomeExists',
                'outcomeExistsForTenant',
                'purgeTargets',
                'purgeTargetsForTenant',
            ],
            'reader public callable inventory',
            $violations,
        );

        return $violations;
    }

    private function publicApiReferences(array $sources): array
    {
        $references = [];
        foreach ($sources as $path => $source) {
            if ($this->containsCodeText($source, 'PublicApi')) {
                $references[] = $path;
            }
        }

        return $references;
    }

    private function containsCodeText(string $source, string $needle): bool
    {
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }
            if (in_array(
                $token[0],
                [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE],
                true,
            )) {
                continue;
            }
            if (str_contains($token[1], $needle)) {
                return true;
            }
        }

        return false;
    }

    private function executableText(string $source): string
    {
        $text = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $text .= is_array($token) ? $token[1] : $token;
        }

        return $text;
    }

    private function compactSource(string $source): string
    {
        return (string) preg_replace('/\s+/', '', $this->executableText($source));
    }

    /** @param list<string> $expected */
    private function hasTokenSequence(string $source, array $expected): bool
    {
        return $this->tokenSequencePosition($source, $expected) !== null;
    }

    /** @param list<string> $expected */
    private function tokenSequencePosition(string $source, array $expected): ?int
    {
        $actual = $this->tokenValues($source);
        $length = count($expected);
        if ($length === 0) {
            return 0;
        }
        for ($index = 0, $count = count($actual) - $length; $index <= $count; $index++) {
            if (array_slice($actual, $index, $length) === $expected) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<string> $expected @return list<int> */
    private function tokenSequencePositions(string $source, array $expected): array
    {
        $actual = $this->tokenValues($source);
        $length = count($expected);
        if ($length === 0) {
            return [];
        }
        $positions = [];
        for ($index = 0, $count = count($actual) - $length; $index <= $count; $index++) {
            if (array_slice($actual, $index, $length) === $expected) {
                $positions[] = $index;
            }
        }

        return $positions;
    }

    /** @param list<string> $expected */
    private function tokenSequenceCount(string $source, array $expected): int
    {
        $actual = $this->tokenValues($source);
        $length = count($expected);
        if ($length === 0) {
            return 0;
        }
        $matches = 0;
        for ($index = 0, $count = count($actual) - $length; $index <= $count; $index++) {
            if (array_slice($actual, $index, $length) === $expected) {
                $matches++;
            }
        }

        return $matches;
    }

    /** @return list<int> */
    private function variableAssignmentPositions(string $source, string $variable): array
    {
        $actual = $this->tokenValues($source);
        $positions = [];
        foreach ($actual as $index => $value) {
            if ($value !== $variable || !isset($actual[$index + 1])) {
                continue;
            }
            if (in_array($actual[$index + 1], ['=', '.=', '+=', '??='], true)) {
                $positions[] = $index;
            }
        }

        return $positions;
    }

    private function scopedQueryFlowIsExact(string $body, string $fetchMethod): bool
    {
        $baseWhere = $this->tokenValues("\$where = 'operation_id = :operation_id';");
        $scopedWhere = $this->tokenValues(
            "\$where .= ' AND tenant_type IS NOT DISTINCT FROM :tenant_type AND tenant_id IS NOT DISTINCT FROM :tenant_id';",
        );
        $baseParams = $this->tokenValues("\$params = ['operation_id' => \$operationId->toString()];");
        $scopedParams = $this->tokenValues("\$params = [...\$params, ...\$scope];");
        $baseWherePosition = $this->tokenSequencePosition($body, $baseWhere);
        $scopedWherePosition = $this->tokenSequencePosition($body, $scopedWhere);
        $baseParamsPosition = $this->tokenSequencePosition($body, $baseParams);
        $scopedParamsPosition = $this->tokenSequencePosition($body, $scopedParams);
        $fetchPosition = $this->tokenSequencePosition($body, [$fetchMethod, '(']);
        if (
            !is_int($baseWherePosition)
            || !is_int($scopedWherePosition)
            || !is_int($baseParamsPosition)
            || !is_int($scopedParamsPosition)
            || !is_int($fetchPosition)
        ) {
            return false;
        }

        $whereAssignments = $this->variableAssignmentPositions($body, '$where');
        $paramsAssignments = $this->variableAssignmentPositions($body, '$params');
        if (
            count($whereAssignments) !== 2
            || count($paramsAssignments) !== 2
            || $whereAssignments[0] !== $baseWherePosition
            || $whereAssignments[1] !== $scopedWherePosition
            || $paramsAssignments[0] !== $baseParamsPosition
            || $paramsAssignments[1] !== $scopedParamsPosition
            || $baseWherePosition >= $scopedWherePosition
            || $scopedWherePosition >= $scopedParamsPosition
            || $scopedParamsPosition >= $fetchPosition
        ) {
            return false;
        }

        $paramsCallPosition = null;
        foreach ($this->tokenSequencePositions($body, ['$params', ')']) as $position) {
            if ($position > $fetchPosition) {
                $paramsCallPosition = $position;
                break;
            }
        }
        if (!is_int($paramsCallPosition)) {
            return false;
        }

        $values = $this->tokenValues($body);
        for ($position = $fetchPosition; $position < $paramsCallPosition; $position++) {
            if (($values[$position] ?? null) === '$where') {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function tokenValues(string $source): array
    {
        $tokens = str_starts_with($source, '<?php') ? token_get_all($source) : token_get_all('<?php ' . $source);
        $values = [];
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $values[] = is_array($token) ? $token[1] : $token;
        }

        return $values;
    }

    /** @return list<string>|null */
    private function declarationTokens(string $source, string $keyword, string $name): ?array
    {
        $values = $this->tokenValues($source);
        $length = count($values);
        for ($index = 0; ($index + 1) < $length; $index++) {
            if ($values[$index] !== $keyword || $values[$index + 1] !== $name) {
                continue;
            }
            for ($end = $index + 2; $end < $length; $end++) {
                if ($values[$end] === '{' || $values[$end] === ';') {
                    $start = $index - $this->declarationModifierCount($values, $index);

                    return array_slice($values, $start, $end - $start);
                }
            }
        }

        return null;
    }

    /** @param list<string> $values */
    private function declarationModifierCount(array $values, int $keywordIndex): int
    {
        $count = 0;
        for ($index = $keywordIndex - 1; $index >= 0; $index--) {
            if (!in_array($values[$index], ['final', 'readonly', 'abstract'], true)) {
                break;
            }
            $count++;
        }

        return $count;
    }

    private function hasClassTraitUse(string $source): bool
    {
        $tokens = token_get_all($source);
        $pendingClass = false;
        $inClass = false;
        $depth = 0;
        $interpolation = 0;
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (!$inClass && $token[0] === T_CLASS) {
                    $pendingClass = true;
                    continue;
                }
                if ($inClass && $token[0] === T_CURLY_OPEN) {
                    $interpolation++;
                    continue;
                }
                if ($inClass && $depth === 1 && $token[0] === T_USE) {
                    return true;
                }
            }
            if ($pendingClass && $token === '{') {
                $pendingClass = false;
                $inClass = true;
                $depth = 1;
                continue;
            }
            if (!$inClass) {
                continue;
            }
            if ($interpolation > 0) {
                if ($token === '}') {
                    $interpolation--;
                }
                continue;
            }
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
                if ($depth === 0) {
                    $inClass = false;
                }
            }
        }

        return false;
    }

    private function signatureMatches(string $source, string $name, string $expected): bool
    {
        $signature = $this->methodSignature($source, $name);

        return is_string($signature) && $this->compactSource($signature) === $expected;
    }

    private function methodSignature(string $source, string $name): ?string
    {
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $cursor = $index + 1;
            while (isset($tokens[$cursor])) {
                $candidate = $tokens[$cursor];
                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $cursor++;
                    continue;
                }
                if ($candidate === '&') {
                    $cursor++;
                    continue;
                }
                break;
            }
            if (!isset($tokens[$cursor]) || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_STRING) {
                continue;
            }
            if ($tokens[$cursor][1] !== $name) {
                continue;
            }

            $parentheses = 0;
            for ($end = $index, $count = count($tokens); $end < $count; $end++) {
                $candidate = $tokens[$end];
                if ($candidate === '(') {
                    $parentheses++;
                } elseif ($candidate === ')') {
                    $parentheses--;
                } elseif ($parentheses === 0 && ($candidate === '{' || $candidate === ';')) {
                    return $this->tokensText($tokens, $index, $end - 1);
                }
            }

            return null;
        }

        return null;
    }

    private function requireSource(bool $condition, string $name, array &$violations): void
    {
        if (!$condition) {
            $violations[] = $name;
        }
    }

    /** @param array<string,string> $sources */
    private function assertSyntaxValid(array $sources, string $label): void
    {
        foreach ($sources as $path => $source) {
            try {
                token_get_all($source, TOKEN_PARSE);
            } catch (\ParseError $exception) {
                self::fail($label . ' produced invalid PHP in ' . $path . ': ' . $exception->getMessage());
            }
        }
    }

    /** @return list<string> */
    private function declaredMethods(ReflectionClass $reflection, ?int $filter = null): array
    {
        $methods = [];
        foreach ($filter === null ? $reflection->getMethods() : $reflection->getMethods($filter) as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);

        return $methods;
    }

    /** @return list<string> */
    private function declaredProperties(ReflectionClass $reflection): array
    {
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $reflection->getName()) {
                $properties[] = $property->getName();
            }
        }
        sort($properties);

        return $properties;
    }

    private function assertMethod(
        ReflectionMethod $method,
        string $visibility,
        ?string $return,
        array $expectedParameters,
    ): void {
        self::assertSame($visibility === 'public', $method->isPublic(), $method->getName());
        self::assertSame($visibility === 'private', $method->isPrivate(), $method->getName());
        self::assertFalse($method->isStatic(), $method->getName());
        self::assertSame($return, $this->typeName($method->getReturnType()), $method->getName());
        $actual = [];
        foreach ($method->getParameters() as $parameter) {
            $actual[] = [
                $parameter->getName(),
                $this->typeName($parameter->getType()),
                count($parameter->getAttributes(\SensitiveParameter::class)),
            ];
            self::assertFalse($parameter->isVariadic(), $method->getName());
            self::assertFalse($parameter->isDefaultValueAvailable(), $method->getName());
        }
        self::assertSame($expectedParameters, $actual, $method->getName());
    }

    private function typeName(?ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if (!$type instanceof ReflectionNamedType) {
            return (string) $type;
        }

        return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
    }

    /** @return list<string> */
    private function methodNamesFromSource(string $source): array
    {
        $names = [];
        foreach ($this->methodEntries($source) as $entry) {
            $names[] = $entry['name'];
        }
        sort($names);

        return $names;
    }

    /** @return list<string> */
    private function methodVisibilityNames(string $source, string $visibility): array
    {
        $names = [];
        foreach ($this->methodEntries($source) as $entry) {
            if ($entry['visibility'] === $visibility) {
                $names[] = $entry['name'];
            }
        }
        sort($names);

        return $names;
    }

    /** @return list<string> */
    private function propertyNamesFromSource(string $source): array
    {
        $names = [];
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }
            for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
                $previous = $tokens[$cursor];
                if (is_array($previous) && in_array($previous[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if (
                    $previous === '('
                    || $previous === ')'
                    || $previous === ';'
                    || $previous === '{'
                    || $previous === '}'
                ) {
                    break;
                }
                if (is_array($previous) && in_array($previous[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                    $names[] = substr($token[1], 1);
                    break;
                }
            }
        }
        sort($names);

        return $names;
    }

    /** @return list<array{name: string, visibility: string}> */
    private function methodEntries(string $source): array
    {
        try {
            $parser = new ParserFactory()->createForNewestSupportedVersion();
            $statements = $parser->parse($source);
            if ($statements === null) {
                return [];
            }

            $entries = [];
            foreach (new NodeFinder()->findInstanceOf($statements, ClassMethod::class) as $method) {
                if (!$method instanceof ClassMethod) {
                    continue;
                }
                $visibility = match (true) {
                    $method->isPublic() => 'public',
                    $method->isProtected() => 'protected',
                    $method->isPrivate() => 'private',
                    default => null,
                };
                if ($visibility === null) {
                    continue;
                }
                $entries[] = ['name' => $method->name->toString(), 'visibility' => $visibility];
            }

            return $entries;
        } catch (\Throwable) {
            return [];
        }
    }

    private function methodBody(string $source, string $name): ?string
    {
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $cursor = $index + 1;
            while (isset($tokens[$cursor])) {
                $candidate = $tokens[$cursor];
                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $cursor++;
                    continue;
                }
                if ($candidate === '&') {
                    $cursor++;
                    continue;
                }
                break;
            }
            if (!isset($tokens[$cursor]) || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_STRING) {
                continue;
            }
            if ($tokens[$cursor][1] !== $name) {
                continue;
            }
            while (isset($tokens[$cursor]) && $tokens[$cursor] !== '{') {
                if ($tokens[$cursor] === ';') {
                    continue 2;
                }
                $cursor++;
            }
            if (!isset($tokens[$cursor])) {
                return null;
            }
            $depth = 1;
            $interpolation = 0;
            for ($body = $cursor + 1, $count = count($tokens); $body < $count; $body++) {
                if (is_array($tokens[$body]) && $tokens[$body][0] === T_CURLY_OPEN) {
                    $interpolation++;
                    continue;
                }
                if ($tokens[$body] === '}' && $interpolation > 0) {
                    $interpolation--;
                    continue;
                }
                if ($interpolation > 0) {
                    continue;
                }
                if ($tokens[$body] === '{') {
                    $depth++;
                } elseif ($tokens[$body] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return $this->tokensText($tokens, $cursor + 1, $body - 1);
                    }
                }
            }

            return null;
        }

        return null;
    }

    private function tokensText(array $tokens, int $start, int $end): string
    {
        $text = '';
        for ($index = $start; $index <= $end; $index++) {
            $text .= is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
        }

        return $text;
    }

    private function replaceOnce(string $search, string $replace, string $subject): string
    {
        $position = strpos($subject, $search);
        self::assertIsInt($position);

        return substr($subject, 0, $position) . $replace . substr($subject, $position + strlen($search));
    }

    private function insertBeforeFinalBrace(string $source, string $text): string
    {
        $position = strrpos($source, '}');
        self::assertNotFalse($position);

        return substr($source, 0, $position) . $text . substr($source, $position);
    }

    private function moveSnapshotSubjectRead(string $source): string
    {
        $line = "            \$snapshotSubject = \$this->readSubject(\$subject->operationId, \$scope);\n";
        $without = str_replace($line, '', $source, $count);
        self::assertSame(1, $count);
        $needle = "            \$this->assertScopedRootStarted();\n            try {\n                \$this->connection->commit();";
        $replacement =
            "            \$snapshotSubject = \$this->readSubject(\$subject->operationId, \$scope);\n" . $needle;
        $moved = str_replace($needle, $replacement, $without, $count);
        self::assertSame(1, $count);

        return $moved;
    }

    private function moveSubjectIdentityCheckAfterProjection(string $source): string
    {
        $block =
            "                if (!\$this->sameSubject(\$subject, \$snapshotSubject)) {\n"
            . "                    throw OperationStatusSourceException::integrityFailed();\n"
            . "                }\n";
        $without = str_replace($block, '', $source, $count);
        self::assertSame(1, $count);
        $needle = "                \$result = \$this->readDetailSnapshot(\$snapshotSubject, \$scope);\n";
        $moved = str_replace($needle, $needle . $block, $without, $count);
        self::assertSame(1, $count);

        return $moved;
    }

    private function replaceScopedLevel0Guards(string $source): string
    {
        $mutated = preg_replace('/^(\s*)\$this->assertScopedLevel0\(\);$/m', '$1true;', $source, -1, $count);
        self::assertIsString($mutated);
        self::assertSame(8, $count);

        return $mutated;
    }

    /** @return list<string> */
    private function productionCallerViolations(array $sources): array
    {
        $violations = [];
        $allowed = [
            [
                'path' => 'src/Internal/Status/TenantBoundOperationStatusSource.php',
                'class' => 'TenantBoundOperationStatusSource',
                'method' => 'findSubject',
                'call' => '$values[\'source\']->findSubjectForTenant($operationId, $values[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/TenantBoundOperationStatusSource.php',
                'class' => 'TenantBoundOperationStatusSource',
                'method' => 'readDetail',
                'call' => '$values[\'source\']->readDetailForTenant($subject, $values[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/PostgreSqlOperationStatusSource.php',
                'class' => 'PostgreSqlOperationStatusSource',
                'method' => 'deferredState',
                'call' => '$this->reader->deferredStateForTenant($operationId, $scope[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/PostgreSqlOperationStatusSource.php',
                'class' => 'PostgreSqlOperationStatusSource',
                'method' => 'purgeTargets',
                'call' => '$this->reader->purgeTargetsForTenant($operationId, $scope[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/PostgreSqlOperationStatusSource.php',
                'class' => 'PostgreSqlOperationStatusSource',
                'method' => 'records',
                'call' => '$this->journal->recordsForTenant($operationId, $scope[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/PostgreSqlOperationStatusSource.php',
                'class' => 'PostgreSqlOperationStatusSource',
                'method' => 'outcomeExists',
                'call' => '$this->reader->outcomeExistsForTenant($operationId, $scope[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/PostgreSqlOperationStatusSource.php',
                'class' => 'PostgreSqlOperationStatusSource',
                'method' => 'deadLetterExists',
                'call' => '$this->reader->deadLetterExistsForTenant($operationId, $scope[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/PostgreSqlOperationStatusSource.php',
                'class' => 'PostgreSqlOperationStatusSource',
                'method' => 'outcome',
                'call' => '$this->outcomes->findForTenant($id, $scope[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/Status/PostgreSqlOperationStatusSource.php',
                'class' => 'PostgreSqlOperationStatusSource',
                'method' => 'readSubject',
                'call' => '$this->reader->findSubjectForTenant($operationId, $scope[\'tenant\'])',
            ],
            [
                'path' => 'src/Internal/OperationData/PostgreSqlOperationDataSubjectReader.php',
                'class' => 'PostgreSqlOperationDataSubjectReader',
                'method' => 'findSubject',
                'call' => '$this->reader->findSubjectForTenant($operationId, $tenant)',
            ],
            [
                'path' => 'src/Internal/OperationData/DefaultOperationJournalQuery.php',
                'class' => 'DefaultOperationJournalQuery',
                'method' => 'records',
                'call' => '$this->reader->recordsForTenant($operationId, $subject->originTenant)',
            ],
            [
                'path' => 'src/Internal/OperationData/DefaultOperationOutcomeQuery.php',
                'class' => 'DefaultOperationOutcomeQuery',
                'method' => 'find',
                'call' => '$this->reader->findForTenant($operationId, $subject->originTenant)',
            ],
            [
                'path' => 'src/Http/Status/OperationStatusRequestHandler.php',
                'class' => 'OperationStatusRequestHandler',
                'method' => 'handle',
                'call' => '$this->query->findForTenant($operationId, $actor, $tenant)',
            ],
            [
                'path' => 'src/Internal/Status/DefaultOperationStatusQuery.php',
                'class' => 'DefaultOperationStatusQuery',
                'method' => 'find',
                'call' => '$this->findForTenant($operationId, $currentActor, $currentTenant)',
            ],
        ];
        $expectedClasses = [];
        foreach ($allowed as $edge) {
            $expectedClasses[$edge['path']] = $edge['class'];
            $source = $sources[$edge['path']] ?? null;
            $this->requireSource(
                is_string($source) && $this->declaredClassNames($source) === [$edge['class']],
                $edge['path'] . ' class inventory',
                $violations,
            );
            $body = is_string($source) ? $this->methodBody($source, $edge['method']) : null;
            $this->requireSource(
                is_string($body) && $this->tokenSequenceCount($body, $this->tokenValues($edge['call'])) === 1,
                $edge['path'] . '::' . $edge['class'] . '::' . $edge['method'] . ' scoped edge',
                $violations,
            );
        }
        foreach ($expectedClasses as $path => $class) {
            if (!isset($sources[$path])) {
                $violations[] = $path . ' missing';
            }
        }
        foreach ([
            'findSubjectForTenant' => 3,
            'readDetailForTenant' => 1,
            'deferredStateForTenant' => 1,
            'purgeTargetsForTenant' => 1,
            'outcomeExistsForTenant' => 1,
            'deadLetterExistsForTenant' => 1,
            'recordsForTenant' => 2,
            'findForTenant' => 4,
        ] as $method => $expectedCount) {
            $actualCount = 0;
            foreach ($sources as $source) {
                $actualCount += $this->objectMethodCallCount($source, $method);
            }
            $this->requireSource(
                $actualCount === $expectedCount,
                'production scoped edge count ' . $method,
                $violations,
            );
        }
        foreach ($sources as $path => $source) {
            if ($this->newClassCount($source, 'BlackOps\Internal\Status\TenantBoundOperationStatusSource') > 0) {
                $violations[] = $path . '::new-bound-view';
            }
        }

        return $violations;
    }

    private function objectMethodCallCount(string $source, string $method): int
    {
        try {
            $parser = new ParserFactory()->createForNewestSupportedVersion();
            $statements = $parser->parse($source);
            if ($statements === null) {
                return 0;
            }

            $calls = [
                ...new NodeFinder()->findInstanceOf($statements, MethodCall::class),
                ...new NodeFinder()->findInstanceOf($statements, NullsafeMethodCall::class),
            ];
            $count = 0;
            foreach ($calls as $call) {
                if (!$call instanceof MethodCall && !$call instanceof NullsafeMethodCall) {
                    continue;
                }
                $calledName = match (true) {
                    $call->name instanceof Identifier => $call->name->toString(),
                    $call->name instanceof String_ => $call->name->value,
                    default => null,
                };
                if ($calledName !== null && strcasecmp($calledName, $method) === 0) {
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable) {
            return 1;
        }
    }

    /** @return list<string> */
    private function declaredClassNames(string $source): array
    {
        $names = [];
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }
            $next = $index + 1;
            while (isset($tokens[$next])) {
                $candidate = $tokens[$next];
                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $next++;
                    continue;
                }
                break;
            }
            if (isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                $names[] = $tokens[$next][1];
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function directCallLines(string $source, string $method): array
    {
        $tokens = token_get_all($source);
        $lines = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_OBJECT_OPERATOR) {
                continue;
            }
            $next = $index + 1;
            while (isset($tokens[$next])) {
                $candidate = $tokens[$next];
                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $next++;
                    continue;
                }
                break;
            }
            if (!isset($tokens[$next]) || !is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) {
                continue;
            }
            if ($tokens[$next][1] !== $method) {
                continue;
            }
            $lineNumber = $tokens[$next][2];
            $lines[] = $this->sourceLine($source, $lineNumber);
        }

        return $lines;
    }

    private function sourceLine(string $source, int $lineNumber): string
    {
        $lines = preg_split('/\R/', $source);
        self::assertIsArray($lines);

        return (string) ($lines[$lineNumber - 1] ?? '');
    }

    private function newClassCount(string $source, string $targetFqcn): int
    {
        $hasNewExpression = false;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_NEW) {
                $hasNewExpression = true;
                break;
            }
        }
        if (!$hasNewExpression) {
            return 0;
        }

        try {
            $parser = new ParserFactory()->createForNewestSupportedVersion();
            $statements = $parser->parse($source);
            if ($statements === null) {
                return 0;
            }

            $resolver = new NodeTraverser();
            $resolver->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
            $statements = $resolver->traverse($statements);

            $target = strtolower(trim($targetFqcn, '\\'));
            $count = 0;
            foreach (new NodeFinder()->findInstanceOf($statements, New_::class) as $new) {
                if (!$new instanceof New_) {
                    continue;
                }
                $class = $new->class;
                if ($class instanceof ClassConstFetch) {
                    if (
                        !$class->name instanceof Identifier
                        || strcasecmp($class->name->toString(), 'class') !== 0
                        || !$class->class instanceof \PhpParser\Node\Name
                    ) {
                        continue;
                    }
                    $class = $class->class;
                }
                if (!$class instanceof \PhpParser\Node\Name) {
                    continue;
                }
                $resolved = $class->getAttribute('resolvedName') ?? $class->getAttribute('namespacedName');
                if (!$resolved instanceof \PhpParser\Node\Name) {
                    continue;
                }
                if (strtolower(trim($resolved->toString(), '\\')) === $target) {
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable) {
            return 1;
        }
    }
}
