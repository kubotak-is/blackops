<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Internal\OperationData\AuditedOperationJournalQuery;
use BlackOps\Internal\OperationData\AuditedOperationOutcomeQuery;
use BlackOps\Internal\OperationData\OperationDataAuditTenantAuthorizer;
use BlackOps\Internal\OperationData\OperationJournalReadExecutionSource;
use BlackOps\Internal\OperationData\OperationOutcomeReadExecutionSource;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
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
 * Checks the latent operation-data Audit adapters at their Internal boundary.
 *
 * The source oracle parses only the five adapter files and keeps mutation
 * fixtures method-local so the executable tests remain the primary behavior
 * proof.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuditedOperationDataArchitectureTest extends TestCase
{
    public function testExactDeclarationsPropertiesAndSensitiveSurfaces(): void
    {
        $this->assertRuntimeDeclaration(
            new ReflectionClass(AuditedOperationJournalQuery::class),
            \BlackOps\OperationData\OperationJournalQuery::class,
            [
                '__construct' => 'public',
                'records' => 'public',
                '__debugInfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'assertIdle' => 'private',
                'value' => 'private',
            ],
        );
        $this->assertRuntimeDeclaration(
            new ReflectionClass(AuditedOperationOutcomeQuery::class),
            \BlackOps\OperationData\OperationOutcomeQuery::class,
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
            new ReflectionClass(OperationDataAuditTenantAuthorizer::class),
            \BlackOps\OperationData\OperationDataReadAuthorizer::class,
            [
                '__construct' => 'public',
                'decide' => 'public',
                '__debugInfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'sameTenant' => 'private',
                'value' => 'private',
            ],
        );
        $this->assertRuntimeDeclaration(
            new ReflectionClass(OperationJournalReadExecutionSource::class),
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
        $this->assertRuntimeDeclaration(
            new ReflectionClass(OperationOutcomeReadExecutionSource::class),
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

        $journal = new ReflectionClass(AuditedOperationJournalQuery::class);
        $this->assertMethodContract(
            $journal->getMethod('__construct'),
            null,
            [
                ['subjects',   'OperationDataSubjectReader',         true, false],
                ['authorizer', 'OperationDataReadAuthorizer',        true, false],
                ['reader',     'TenantScopedCanonicalJournalReader', true, false],
                ['sessions',   'OperationReadAuditSessionFactory',   true, false],
                ['connection', 'Connection',                         true, false],
            ],
        );
        $this->assertMethodContract(
            $journal->getMethod('records'),
            'OperationJournalReadResult',
            [
                ['operationId',   'OperationId',          true, false],
                ['currentActor',  '?ActorRef',            true, false],
                ['currentTenant', '?TenantRef',           true, false],
                ['purpose',       'OperationDataPurpose', true, false],
            ],
        );
        $this->assertCommonQueryMethods($journal);

        $outcome = new ReflectionClass(AuditedOperationOutcomeQuery::class);
        $this->assertMethodContract(
            $outcome->getMethod('__construct'),
            null,
            [
                ['subjects',   'OperationDataSubjectReader',       true, false],
                ['authorizer', 'OperationDataReadAuthorizer',      true, false],
                ['reader',     'TenantScopedOutcomeReader',        true, false],
                ['sessions',   'OperationReadAuditSessionFactory', true, false],
                ['connection', 'Connection',                       true, false],
            ],
        );
        $this->assertMethodContract(
            $outcome->getMethod('find'),
            'OperationOutcomeReadResult',
            [
                ['operationId',   'OperationId',          true, false],
                ['currentActor',  '?ActorRef',            true, false],
                ['currentTenant', '?TenantRef',           true, false],
                ['purpose',       'OperationDataPurpose', true, false],
            ],
        );
        $this->assertCommonQueryMethods($outcome);

        $authorizer = new ReflectionClass(OperationDataAuditTenantAuthorizer::class);
        $this->assertMethodContract(
            $authorizer->getMethod('__construct'),
            null,
            [['authorizer', 'OperationDataReadAuthorizer', true, false]],
        );
        $this->assertMethodContract(
            $authorizer->getMethod('decide'),
            'OperationDataReadAuthorizationDecision',
            [['request', 'OperationDataReadAuthorizationRequest', true, false]],
        );
        $this->assertMethodContract(
            $authorizer->getMethod('sameTenant'),
            'bool',
            [
                ['current', '?TenantRef', true, false],
                ['origin',  '?TenantRef', true, false],
            ],
        );
        $this->assertCommonHiddenMethods($authorizer);
        $this->assertMethodContract($authorizer->getMethod('value'), 'mixed', [['key', 'string', true, false]]);

        foreach ([
            OperationJournalReadExecutionSource::class,
            OperationOutcomeReadExecutionSource::class,
        ] as $class) {
            $execution = new ReflectionClass($class);
            $this->assertMethodContract(
                $execution->getMethod('__construct'),
                null,
                [['identifiers', 'Uuidv7Generator', true, false]],
            );
            $this->assertMethodContract($execution->getMethod('current'), 'AuditTypedReference', []);
            $this->assertCommonHiddenMethods($execution);
            $this->assertMethodContract($execution->getMethod('value'), 'mixed', [['key', 'string', true, false]]);
        }
    }

    public function testTokenAwareBoundariesAndCallFlowsAreExact(): void
    {
        $sources = $this->adapterSources();
        self::assertSame([], $this->architectureViolations($sources));
    }

    public function testBoundedMutationsRejectArchitectureDriftAndIgnoreInertText(): void
    {
        $sources = $this->adapterSources();
        $inert = $sources;
        $inert['src/Internal/OperationData/AuditedOperationJournalQuery.php'] .= <<<'PHP'

            // $session->unavailable(); $connection->beginTransaction();
            $text = 'AuditReadPurpose::Outcome OperationDataAuditTenantAuthorizer';
            PHP;
        self::assertSame([], $this->architectureViolations($inert));

        $journalPath = 'src/Internal/OperationData/AuditedOperationJournalQuery.php';
        $outcomePath = 'src/Internal/OperationData/AuditedOperationOutcomeQuery.php';
        $guardPath = 'src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php';
        $journalExecutionPath = 'src/Internal/OperationData/OperationJournalReadExecutionSource.php';
        $outcomeExecutionPath = 'src/Internal/OperationData/OperationOutcomeReadExecutionSource.php';
        $mutations = [
            'journal purpose is not fixed' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    'AuditReadPurpose::Journal',
                    'AuditReadPurpose::Outcome',
                ),
            ],
            'outcome purpose is not fixed' => [
                $outcomePath => $this->replaceOnce(
                    $sources[$outcomePath],
                    'AuditReadPurpose::Outcome',
                    'AuditReadPurpose::Journal',
                ),
            ],
            'journal begin is repeated' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    '            $session = $sessions->begin(AuditReadPurpose::Journal, $operationId, $currentActor, $currentTenant);',
                    "            \$session = \$sessions->begin(AuditReadPurpose::Journal, \$operationId, \$currentActor, \$currentTenant);\n"
                    . "            \$sessions->begin(AuditReadPurpose::Journal, \$operationId, \$currentActor, \$currentTenant);",
                ),
            ],
            'journal unexpected result loses integrity mapping' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    "            } else {\n                \$failure = OperationJournalQueryException::integrityFailed();\n            }",
                    "            } else {\n                \$failure = OperationJournalQueryException::storageFailed();\n            }",
                ),
            ],
            'outcome unexpected result is returned' => [
                $outcomePath => $this->replaceOnce(
                    $sources[$outcomePath],
                    "            } else {\n                \$failure = OperationOutcomeQueryException::integrityFailed();\n            }",
                    "            } else {\n                \$result = \$candidate;\n            }",
                ),
            ],
            'outcome terminal is repeated' => [
                $outcomePath => $this->replaceOnce(
                    $sources[$outcomePath],
                    '                $session->unavailable();',
                    "                \$session->unavailable();\n                \$session->unavailable();",
                ),
            ],
            'tenant guard is bypassed' => [
                $guardPath => $this->replaceOnce(
                    $sources[$guardPath],
                    'if (!$this->sameTenant($request->currentTenant(), $request->originTenant()))',
                    'if (true || !$this->sameTenant($request->currentTenant(), $request->originTenant()))',
                ),
            ],
            'journal execution type changes' => [
                $journalExecutionPath => $this->replaceOnce(
                    $sources[$journalExecutionPath],
                    "'operation_journal_read'",
                    "'operation_outcome_read'",
                ),
            ],
            'outcome execution wrapper is removed' => [
                $outcomeExecutionPath => $this->replaceOnce(
                    $sources[$outcomeExecutionPath],
                    'new ValidatedUuidv7Generator($identifiers)',
                    '$identifiers',
                ),
            ],
            'journal constructor performs I/O' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    "    ) {\n        \$this->snapshot =",
                    "    ) {\n        \$connection->isAutoCommit();\n        \$this->snapshot =",
                ),
            ],
            'journal terminal gains transaction repair' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    "        try {\n            if (\$result instanceof OperationJournalFound)",
                    "        \$connection->beginTransaction();\n\n        try {\n            if (\$result instanceof OperationJournalFound)",
                ),
            ],
            'journal terminal failure retries' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    "                \$session->unavailable();\n            }\n        } catch (Throwable) {",
                    "                \$session->unavailable();\n            }\n        } catch (Throwable) {\n            \$session->unavailable();",
                ),
            ],
            'outcome terminal is released early' => [
                $outcomePath => $this->replaceOnce(
                    $sources[$outcomePath],
                    "        try {\n            if (\$result instanceof OperationOutcomeFound)",
                    "        return \$result;\n\n        try {\n            if (\$result instanceof OperationOutcomeFound)",
                ),
            ],
            'journal query parameter loses sensitivity' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    "        #[\\SensitiveParameter]\n        OperationId \$operationId,",
                    "        OperationId \$operationId,",
                ),
            ],
            'query retains extra state' => [
                $outcomePath => $this->replaceOnce(
                    $sources[$outcomePath],
                    '    private \\SensitiveParameterValue $snapshot;',
                    "    private \\SensitiveParameterValue \$snapshot;\n    private string \$unexpected;",
                ),
            ],
            'journal terminal opens a session directly' => [
                $journalPath => $this->replaceOnce(
                    $sources[$journalPath],
                    "        try {\n            if (\$result instanceof OperationJournalFound)",
                    "        \$session->open();\n\n        try {\n            if (\$result instanceof OperationJournalFound)",
                ),
            ],
        ];
        foreach ($mutations as $name => $mutation) {
            self::assertNotSame($sources, [...$sources, ...$mutation], $name);
            self::assertNotEmpty($this->architectureViolations([...$sources, ...$mutation]), $name);
        }
    }

    /** @param array<string,string> $sources @return list<string> */
    private function architectureViolations(array $sources): array
    {
        $expectedPaths = [
            'src/Internal/OperationData/AuditedOperationJournalQuery.php',
            'src/Internal/OperationData/AuditedOperationOutcomeQuery.php',
            'src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php',
            'src/Internal/OperationData/OperationJournalReadExecutionSource.php',
            'src/Internal/OperationData/OperationOutcomeReadExecutionSource.php',
        ];
        $actualPaths = array_keys($sources);
        sort($expectedPaths);
        sort($actualPaths);
        $violations = $expectedPaths === $actualPaths ? [] : ['source-path-inventory'];

        $declarations = [
            'src/Internal/OperationData/AuditedOperationJournalQuery.php' => [
                'class' => 'AuditedOperationJournalQuery',
                'interface' => 'OperationJournalQuery',
                'methods' => [
                    '__construct' => 'public',
                    'records' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'assertIdle' => 'private',
                    'value' => 'private',
                ],
            ],
            'src/Internal/OperationData/AuditedOperationOutcomeQuery.php' => [
                'class' => 'AuditedOperationOutcomeQuery',
                'interface' => 'OperationOutcomeQuery',
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
            'src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php' => [
                'class' => 'OperationDataAuditTenantAuthorizer',
                'interface' => 'OperationDataReadAuthorizer',
                'methods' => [
                    '__construct' => 'public',
                    'decide' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'sameTenant' => 'private',
                    'value' => 'private',
                ],
            ],
            'src/Internal/OperationData/OperationJournalReadExecutionSource.php' => [
                'class' => 'OperationJournalReadExecutionSource',
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
            'src/Internal/OperationData/OperationOutcomeReadExecutionSource.php' => [
                'class' => 'OperationOutcomeReadExecutionSource',
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
            $implements = [];
            foreach ($class->implements as $interface) {
                $implements[] = $this->terminalName($interface);
            }
            if ($implements !== [$expected['interface']]) {
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

        foreach ([
            'src/Internal/OperationData/AuditedOperationJournalQuery.php' => ['records', 'Journal'],
            'src/Internal/OperationData/AuditedOperationOutcomeQuery.php' => ['find', 'Outcome'],
        ] as $path => [$methodName, $purpose]) {
            if (!isset($sources[$path])) {
                continue;
            }
            $violations = [...$violations, ...$this->queryViolations($path, $sources[$path], $methodName, $purpose)];
        }
        if (isset($sources['src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php'])) {
            $violations = [
                ...$violations,
                ...$this->authorizerViolations(
                    $sources['src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php'],
                ),
            ];
        }
        foreach ([
            'src/Internal/OperationData/OperationJournalReadExecutionSource.php' => 'operation_journal_read',
            'src/Internal/OperationData/OperationOutcomeReadExecutionSource.php' => 'operation_outcome_read',
        ] as $path => $purpose) {
            if (isset($sources[$path])) {
                $violations = [...$violations, ...$this->executionViolations($path, $sources[$path], $purpose)];
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function queryViolations(string $path, string $source, string $methodName, string $purpose): array
    {
        $violations = [];
        $method = $this->methodNode($source, $methodName);
        $constructor = $this->methodNode($source, '__construct');
        $value = $this->methodNode($source, 'value');
        if ($method === null || $constructor === null || $value === null) {
            return ['methods:' . $path];
        }
        $resource = 'Operation' . ($purpose === 'Journal' ? 'Journal' : 'Outcome');
        $found = $resource . 'Found';
        $unavailableType = $resource . 'Unavailable';
        $exception = $resource . 'QueryException';
        if (
            $this->newTypeInventory($constructor) !== [
                'DefaultOperation' . ($purpose === 'Journal' ? 'Journal' : 'Outcome') . 'Query',
                'OperationDataAuditTenantAuthorizer',
                'SensitiveParameterValue',
            ]
        ) {
            $violations[] = 'construction:' . $path;
        }
        if ($this->countNew($constructor, 'OperationDataAuditTenantAuthorizer', [['variable', 'authorizer']]) !== 1) {
            $violations[] = 'mandatory-guard:' . $path;
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'constructor-I/O:' . $path;
        }
        $calls = $this->methodCalls($method);
        if ($this->countCalls($calls, '$this', 'value', [[
            'string',
            'connection',
        ]]) !== 1 || $this->countCalls($calls, '$this', 'value', [[
            'string',
            'sessions',
        ]]) !== 1 || $this->countCalls($calls, '$this', 'value', [['string', 'query']]) !== 1) {
            $violations[] = 'snapshot-access:' . $path;
        }
        $begin = $this->matchingCalls($calls, '$sessions', 'begin');
        $delegate = $this->matchingCalls($calls, '$query', $methodName);
        $accessed = $this->matchingCalls($calls, '$session', 'accessed');
        $unavailable = $this->matchingCalls($calls, '$session', 'unavailable');
        $idle = $this->matchingCalls($calls, '$this', 'assertIdle');
        if (count($begin) !== 1) {
            $violations[] = 'single-begin:' . $path;
        } elseif (!$this->beginCallMatches($begin[0], $purpose)) {
            $violations[] = 'fixed-purpose:' . $path;
        }
        if (count($delegate) !== 1 || !$this->argumentsMatch($delegate[0]->args, [
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
            ['variable', 'purpose'],
        ])) {
            $violations[] = 'default-call:' . $path;
        }
        if (!$this->unexpectedResultFallbackIsExact($method, $found, $unavailableType, $exception)) {
            $violations[] = 'unexpected-result-fallback:' . $path;
        }
        if (count($accessed) !== 1 || count($unavailable) !== 1 || count($idle) !== 4) {
            $violations[] = 'terminal-protocol:' . $path;
        }
        if ($begin !== [] && $delegate !== [] && $begin[0]->getStartFilePos() >= $delegate[0]->getStartFilePos()) {
            $violations[] = 'requested-before-read:' . $path;
        }
        $terminalPosition = max(array_map(
            static fn(MethodCall $call): int => $call->getStartFilePos(),
            [...$accessed, ...$unavailable],
        ));
        $releasePositions = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Return_::class) as $return) {
            if ($this->expressionLabel($return->expr) === '$result') {
                $releasePositions[] = $return->getStartFilePos();
            }
        }
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], Throw_::class) as $throw) {
            if ($this->expressionLabel($throw->expr) === '$failure') {
                $releasePositions[] = $throw->getStartFilePos();
            }
        }
        if ($releasePositions === [] || $terminalPosition >= min($releasePositions)) {
            $violations[] = 'terminal-before-release:' . $path;
        }
        if ($this->matchingCalls($calls, '$session', 'terminal') !== []) {
            $violations[] = 'direct-terminal:' . $path;
        }
        foreach ($calls as $call) {
            if (
                $call->name instanceof Identifier
                && in_array(
                    strtolower($call->name->toString()),
                    ['begintransaction', 'commit', 'rollback', 'retry', 'save', 'open', 'terminal'],
                    true,
                )
            ) {
                $violations[] = 'forbidden-' . strtolower($call->name->toString()) . ':' . $path;
            }
        }
        if (
            str_contains($source, 'new OperationReadAuditSession(')
            || str_contains($source, 'new OperationReadAuditSessionFactory(')
            || str_contains($source, 'OperationReadAuditSession::open(')
        ) {
            $violations[] = 'direct-session-open:' . $path;
        }
        $tries = new NodeFinder()->findInstanceOf($method->stmts ?? [], TryCatch::class);
        $delegateTry = null;
        $terminalTry = null;
        foreach ($tries as $try) {
            $tryCalls = $this->methodCalls($try);
            if ($this->matchingCalls($tryCalls, '$query', $methodName) !== []) {
                $delegateTry = $try;
            }
            if (
                $this->matchingCalls($tryCalls, '$session', 'accessed') !== []
                || $this->matchingCalls($tryCalls, '$session', 'unavailable') !== []
            ) {
                $terminalTry = $try;
            }
        }
        if (
            $delegateTry === null
            || $this->matchingCalls($this->methodCalls($delegateTry), '$session', 'accessed') !== []
        ) {
            $violations[] = 'delegate-terminal-separation:' . $path;
        }
        if (
            $terminalTry === null
            || count($terminalTry->catches) !== 1
            || !$this->catchIsThrowable($terminalTry->catches[0])
            || $terminalTry->finally !== null
        ) {
            $violations[] = 'terminal-catch:' . $path;
        }
        if (!str_contains($source, "array_keys(\$value) !== ['query', 'sessions', 'connection']")) {
            $violations[] = 'snapshot-keys:' . $path;
        }
        if (
            !$this->methodReturnsEmptyArray($source, '__debugInfo')
            || !$this->methodReturnsEmptyArray($source, '__serialize')
            || !str_contains(
                $source,
                'throw Operation'
                . ($purpose === 'Journal' ? 'Journal' : 'Outcome')
                . 'QueryException::storageFailed();',
            )
        ) {
            $violations[] = 'hidden-state:' . $path;
        }
        if (
            $this->countInstanceof($method, $exception) !== 1
            || !str_contains($source, '$failure = $exception;')
            || !str_contains($source, 'throw $failure;')
        ) {
            $violations[] = 'failure-precedence:' . $path;
        }
        if (
            $this->countInstanceof(
                $value,
                'DefaultOperation' . ($purpose === 'Journal' ? 'Journal' : 'Outcome') . 'Query',
            ) !== 1
        ) {
            $violations[] = 'snapshot-type:' . $path;
        }
        return $violations;
    }

    /** @return list<string> */
    private function authorizerViolations(string $source): array
    {
        $path = 'src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php';
        $violations = [];
        $decide = $this->methodNode($source, 'decide');
        $sameTenant = $this->methodNode($source, 'sameTenant');
        $value = $this->methodNode($source, 'value');
        $constructor = $this->methodNode($source, '__construct');
        if ($decide === null || $sameTenant === null || $value === null || $constructor === null) {
            return ['methods:' . $path];
        }
        if ($this->newTypeInventory($constructor) !== ['SensitiveParameterValue']) {
            $violations[] = 'authorizer-construction:' . $path;
        }
        if ($this->methodCalls($constructor) !== []) {
            $violations[] = 'authorizer-constructor-I/O:' . $path;
        }
        $calls = $this->methodCalls($decide);
        $guard = $this->matchingCalls($calls, '$this', 'sameTenant');
        $delegate = $this->matchingCalls($calls, '$authorizer', 'decide');
        if (count($guard) !== 1 || count($delegate) !== 1 || !$this->argumentsMatch($guard[0]->args, [
            ['method-call', '$request', 'currentTenant'],
            ['method-call', '$request', 'originTenant'],
        ]) || !$this->argumentsMatch($delegate[0]->args, [[
            'variable',
            'request',
        ]]) || $guard[0]->getStartFilePos() >= $delegate[0]->getStartFilePos() || !$this->tenantGuardConditionIsExact($decide)) {
            $violations[] = 'mandatory-tenant-guard:' . $path;
        }
        $authorizerValue = $this->matchingCalls($calls, '$this', 'value');
        if (count($authorizerValue) !== 1 || !$this->argumentsMatch($authorizerValue[0]->args, [[
            'string',
            'authorizer',
        ]]) || $guard === [] || $authorizerValue[0]->getStartFilePos() <= $guard[0]->getStartFilePos()) {
            $violations[] = 'guard-snapshot-order:' . $path;
        }
        if ($this->newTypeInventory($decide) !== []) {
            $violations[] = 'decide-construction:' . $path;
        }
        $identical = new NodeFinder()->findInstanceOf($sameTenant, Node\Expr\BinaryOp\Identical::class);
        $and = new NodeFinder()->findInstanceOf($sameTenant, BooleanAnd::class);
        $or = new NodeFinder()->findInstanceOf($sameTenant, BooleanOr::class);
        if (count($identical) !== 6 || count($and) !== 2 || count($or) !== 1) {
            $violations[] = 'strict-tenant-comparison:' . $path;
        }
        if (!str_contains($source, "array_keys(\$value) !== ['authorizer']")) {
            $violations[] = 'authorizer-snapshot:' . $path;
        }
        if (
            !$this->methodReturnsEmptyArray($source, '__debugInfo')
            || !$this->methodReturnsEmptyArray($source, '__serialize')
            || substr_count($source, 'Operation data audit authorizer is unavailable.') !== 3
        ) {
            $violations[] = 'authorizer-hidden-state:' . $path;
        }
        return $violations;
    }

    /** @return list<string> */
    private function executionViolations(string $path, string $source, string $purpose): array
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
            ['string', $purpose],
            ['method-call', '$identifiers', 'generate'],
        ])) {
            $violations[] = 'fixed-execution-purpose:' . $path;
        }
        if (str_contains($source, 'AuditReadPurpose') || str_contains($source, 'OperationId')) {
            $violations[] = 'ambient-execution-identity:' . $path;
        }
        if (!str_contains($source, "array_keys(\$value) !== ['identifiers']")) {
            $violations[] = 'execution-snapshot:' . $path;
        }
        if (
            !$this->methodReturnsEmptyArray($source, '__debugInfo')
            || !$this->methodReturnsEmptyArray($source, '__serialize')
            || !$this->catchWrapsResourceUnavailable($current)
        ) {
            $violations[] = 'execution-hidden-state:' . $path;
        }
        return $violations;
    }

    /** @return array<string,string> */
    private function adapterSources(): array
    {
        $paths = [
            'src/Internal/OperationData/AuditedOperationJournalQuery.php',
            'src/Internal/OperationData/AuditedOperationOutcomeQuery.php',
            'src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php',
            'src/Internal/OperationData/OperationJournalReadExecutionSource.php',
            'src/Internal/OperationData/OperationOutcomeReadExecutionSource.php',
        ];
        $sources = [];
        foreach ($paths as $path) {
            $source = file_get_contents(__DIR__ . '/../../' . $path);
            self::assertIsString($source, $path);
            $sources[$path] = $source;
        }
        return $sources;
    }

    private function assertRuntimeDeclaration(ReflectionClass $class, string $interface, array $methods): void
    {
        self::assertTrue($class->isFinal(), $class->getName());
        self::assertTrue($class->isReadOnly(), $class->getName());
        self::assertSame([$interface], $class->getInterfaceNames(), $class->getName());
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

    private function assertCommonQueryMethods(ReflectionClass $class): void
    {
        $this->assertCommonHiddenMethods($class);
        $this->assertMethodContract(
            $class->getMethod('assertIdle'),
            'void',
            [['connection', 'Connection', true, false]],
        );
        $this->assertMethodContract($class->getMethod('value'), 'mixed', [['key', 'string', true, false]]);
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

    private function beginCallMatches(MethodCall $call, string $purpose): bool
    {
        return $this->argumentsMatch($call->args, [
            ['class-constant', 'AuditReadPurpose', $purpose],
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
        $properties = [];
        foreach ($class->getProperties() as $property) {
            $properties[] = $property;
        }
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

    private function tenantGuardConditionIsExact(ClassMethod $method): bool
    {
        $ifs = new NodeFinder()->findInstanceOf($method, If_::class);
        if (count($ifs) !== 1) {
            return false;
        }
        $condition = $ifs[0]->cond;
        if (!$condition instanceof BooleanNot || !$condition->expr instanceof MethodCall) {
            return false;
        }
        return (
            $this->expressionLabel($condition->expr->var) === '$this'
            && $condition->expr->name instanceof Identifier
            && $condition->expr->name->toString() === 'sameTenant'
            && $this->argumentsMatch($condition->expr->args, [
                ['method-call', '$request', 'currentTenant'],
                ['method-call', '$request', 'originTenant'],
            ])
        );
    }

    private function unexpectedResultFallbackIsExact(
        ClassMethod $method,
        string $found,
        string $unavailable,
        string $exception,
    ): bool {
        $matches = 0;
        foreach (new NodeFinder()->findInstanceOf($method, If_::class) as $if) {
            if (!$if->cond instanceof BooleanOr || $if->else === null) {
                continue;
            }
            $types = [];
            foreach ([$if->cond->left, $if->cond->right] as $condition) {
                if (!$condition instanceof Instanceof_ || $this->expressionLabel($condition->expr) !== '$candidate') {
                    $types = [];
                    break;
                }
                $types[] = $this->terminalName($condition->class);
            }
            sort($types);
            $expectedTypes = [$found, $unavailable];
            sort($expectedTypes);
            if ($types !== $expectedTypes || !$this->hasCandidateResultAssignment($if->stmts)) {
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($if->else, Assign::class) as $assignment) {
                if (
                    $this->expressionLabel($assignment->var) === '$failure'
                    && $assignment->expr instanceof StaticCall
                    && $this->terminalName($assignment->expr->class) === $exception
                    && $assignment->expr->name instanceof Identifier
                    && $assignment->expr->name->toString() === 'integrityFailed'
                ) {
                    $matches++;
                }
            }
        }
        return $matches === 1;
    }

    /** @param list<Node> $statements */
    private function hasCandidateResultAssignment(array $statements): bool
    {
        foreach (new NodeFinder()->findInstanceOf($statements, Assign::class) as $assignment) {
            if (
                $this->expressionLabel($assignment->var) === '$result'
                && $this->expressionLabel($assignment->expr) === '$candidate'
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

    private function catchWrapsResourceUnavailable(ClassMethod $method): bool
    {
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

    private function replaceOnce(string $source, string $needle, string $replacement): string
    {
        self::assertSame(1, substr_count($source, $needle), 'mutation marker count for ' . $needle);
        return str_replace($needle, $replacement, $source);
    }
}
