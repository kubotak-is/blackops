<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxException;
use BlackOps\Internal\Audit\Delivery\AuditOutboxFailure;
use BlackOps\Internal\Audit\Delivery\AuditOutboxState;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Delivery\AuditOutboxTransition;
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
 * @mago-expect lint:excessive-parameter-list
 */
final class AuditOutboxStateArchitectureTest extends TestCase
{
    public function testDirectoryInventoryAndTypeModifiersAreExact(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../..');
        self::assertNotFalse($sourceRoot);

        $expected = $this->expectedInventory();
        self::assertSame($expected, $this->inventory($sourceRoot));

        $fixtureRoot = sys_get_temp_dir() . '/blackops-audit-delivery-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($fixtureRoot . '/src/Internal/Audit/Delivery', 0700, true));
        try {
            foreach (array_keys($expected) as $relativePath) {
                $target = $fixtureRoot . '/' . $relativePath;
                self::assertTrue(copy($sourceRoot . '/' . $relativePath, $target));
            }
            $extraPath = $fixtureRoot . '/src/Internal/Audit/Delivery/ExtraState.php';
            self::assertIsInt(file_put_contents($extraPath, <<<'PHP'
                <?php

                namespace BlackOps\Internal\Audit\Delivery;

                enum ExtraState: string
                {
                    case Present = 'present';
                }
                PHP));
            $mutated = $this->inventory($fixtureRoot);
            self::assertArrayHasKey('src/Internal/Audit/Delivery/ExtraState.php', $mutated);
            self::assertNotSame($expected, $mutated);
        } finally {
            $this->removeDirectory($fixtureRoot);
        }

        self::assertTrue(new ReflectionClass(AuditOutboxConfiguration::class)->isFinal());
        self::assertTrue(new ReflectionClass(AuditOutboxConfiguration::class)->isReadOnly());
        self::assertTrue(new ReflectionClass(AuditOutboxStateSnapshot::class)->isFinal());
        self::assertTrue(new ReflectionClass(AuditOutboxStateSnapshot::class)->isReadOnly());
        self::assertTrue(new ReflectionClass(AuditOutboxTransition::class)->isFinal());
        self::assertTrue(new ReflectionClass(AuditOutboxTransition::class)->isReadOnly());
        self::assertTrue(new ReflectionClass(AuditOutboxException::class)->isFinal());
        self::assertFalse(new ReflectionClass(AuditOutboxException::class)->isReadOnly());
    }

    public function testEnumCasesAndPublicCallableMatricesAreExact(): void
    {
        self::assertSame(
            [
                'Pending' => 'pending',
                'Leased' => 'leased',
                'Relayed' => 'relayed',
                'Poisoned' => 'poisoned',
            ],
            array_combine(
                array_map(static fn(\BackedEnum $case): string => $case->name, AuditOutboxState::cases()),
                array_map(static fn(\BackedEnum $case): string => $case->value, AuditOutboxState::cases()),
            ),
        );
        self::assertSame(
            [
                'ConfigurationInvalid' => 'configuration_invalid',
                'IntegrityInvalid' => 'integrity_invalid',
                'TransitionConflict' => 'transition_conflict',
                'ResourceUnavailable' => 'resource_unavailable',
            ],
            array_combine(
                array_map(static fn(\BackedEnum $case): string => $case->name, AuditOutboxFailure::cases()),
                array_map(static fn(\BackedEnum $case): string => $case->value, AuditOutboxFailure::cases()),
            ),
        );

        $expectedPublic = [
            AuditOutboxConfiguration::class => ['__construct'],
            AuditOutboxException::class => ['__construct', 'code', 'failure'],
            AuditOutboxFailure::class => ['cases', 'from', 'tryFrom'],
            AuditOutboxState::class => ['cases', 'from', 'tryFrom'],
            AuditOutboxStateSnapshot::class => [
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'attempt',
                'availableAt',
                'fromArray',
                'leaseExpiresAt',
                'leaseFencingToken',
                'pending',
                'state',
                'toArray',
                'version',
            ],
            AuditOutboxTransition::class => ['__construct', 'claim', 'poison', 'recover', 'relay', 'retry'],
        ];
        foreach ($expectedPublic as $type => $expectedNames) {
            $actualNames = [];
            foreach (new ReflectionClass($type)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() === $type) {
                    $actualNames[] = $method->getName();
                }
            }
            sort($actualNames);
            sort($expectedNames);
            self::assertSame($expectedNames, $actualNames, $type);
        }

        $snapshot = new ReflectionClass(AuditOutboxStateSnapshot::class);
        $this->assertMethod(
            $snapshot->getMethod('pending'),
            'public',
            AuditOutboxStateSnapshot::class,
            [
                ['availableAt',   'string',                        1],
                ['configuration', AuditOutboxConfiguration::class, 0],
            ],
            true,
        );
        $this->assertMethod(
            $snapshot->getMethod('fromArray'),
            'public',
            AuditOutboxStateSnapshot::class,
            [
                ['data',          'array',                         1],
                ['configuration', AuditOutboxConfiguration::class, 0],
            ],
            true,
        );
        $exception = new ReflectionClass(AuditOutboxException::class);
        $this->assertMethod($exception->getMethod('failure'), 'public', AuditOutboxFailure::class, [], false);
        $this->assertMethod($exception->getMethod('code'), 'public', 'string', [], false);

        $this->assertConstructor(
            AuditOutboxConfiguration::class,
            'public',
            [
                ['leaseDurationSeconds', 'int', false, 300,  0],
                ['maxAttempts',          'int', false, 5,    0],
                ['backoffBaseSeconds',   'int', false, 1,    0],
                ['backoffCapSeconds',    'int', false, 3600, 0],
            ],
        );
        $this->assertConstructor(
            AuditOutboxException::class,
            'public',
            [['failure', AuditOutboxFailure::class, false, null, 0]],
        );
        $this->assertConstructor(
            AuditOutboxStateSnapshot::class,
            'private',
            [
                ['state',             'string',  false, null, 0],
                ['attempt',           'int',     false, null, 0],
                ['availableAt',       '?string', false, null, 1],
                ['leaseFencingToken', '?string', false, null, 1],
                ['leaseExpiresAt',    '?string', false, null, 1],
                ['version',           'int',     false, null, 0],
            ],
        );
        $this->assertConstructor(
            AuditOutboxTransition::class,
            'public',
            [['configuration', AuditOutboxConfiguration::class, false, null, 0]],
        );

        $expectedMethods = [
            'state' => ['public', AuditOutboxState::class, [], 0],
            'attempt' => ['public', 'int', [], 0],
            'availableAt' => ['public', '?string', [], 0],
            'leaseFencingToken' => ['public', '?string', [], 0],
            'leaseExpiresAt' => ['public', '?string', [], 0],
            'version' => ['public', 'int', [], 0],
            'toArray' => ['public', 'array', [], 0],
            '__debugInfo' => ['public', 'array', [], 0],
            '__serialize' => ['public', 'array', [], 0],
            '__unserialize' => ['public', 'void', [['data', 'array', 1]], 0],
        ];
        foreach ($expectedMethods as $name => [$visibility, $return, $parameters, $static]) {
            $method = new ReflectionClass(AuditOutboxStateSnapshot::class)->getMethod($name);
            $this->assertMethod($method, $visibility, $return, $parameters, (bool) $static);
        }

        foreach (['claim', 'retry', 'recover', 'relay', 'poison'] as $name) {
            $method = new ReflectionClass(AuditOutboxTransition::class)->getMethod($name);
            $tokenName = $name === 'claim' ? 'newToken' : 'expectedToken';
            $this->assertMethod(
                $method,
                'public',
                AuditOutboxStateSnapshot::class,
                [
                    ['snapshot',        AuditOutboxStateSnapshot::class, 1],
                    ['expectedVersion', 'int',                           0],
                    [$tokenName,        'string',                        1],
                    ['trustedNow',      'string',                        1],
                ],
                false,
            );
        }
        $claim = new ReflectionClass(AuditOutboxTransition::class)->getMethod('claim');
        self::assertSame('newToken', $claim->getParameters()[2]->getName());
    }

    public function testSensitiveParametersAndPublicApiBoundaryAreExact(): void
    {
        $expectedSensitive = [
            AuditOutboxStateSnapshot::class => [
                'pending' => [0],
                'fromArray' => [0],
                '__unserialize' => [0],
            ],
            AuditOutboxTransition::class => [
                'claim' => [0, 2, 3],
                'retry' => [0, 2, 3],
                'recover' => [0, 2, 3],
                'relay' => [0, 2, 3],
                'poison' => [0, 2, 3],
            ],
        ];
        $this->assertSensitiveMethods($expectedSensitive);
        $this->assertSensitiveMethods([
            AuditOutboxStateSnapshot::class => [
                '__construct' => [2, 3, 4],
                'assertStateMatrix' => [2, 3, 4],
                'assertTimestamp' => [0],
                'assertUuidV7' => [0],
            ],
            AuditOutboxTransition::class => [
                'settle' => [0, 2, 3],
                'assertLeased' => [0, 2],
                'assertToken' => [0],
                'assertTransitionTime' => [0],
                'addSeconds' => [0],
                'makeSnapshot' => [0],
            ],
        ]);

        foreach (array_values($this->expectedInventory()) as $types) {
            foreach ($types as $type) {
                self::assertSame([], new ReflectionClass($type)->getAttributes(PublicApi::class), $type);
            }
        }

        $sourceRoot = realpath(__DIR__ . '/../..');
        self::assertNotFalse($sourceRoot);
        foreach (glob($sourceRoot . '/src/Internal/Audit/Delivery/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString('PublicApi', $source, $path);
        }
    }

    /** @param array<class-string,array<string,list<int>>> $expected */
    private function assertSensitiveMethods(array $expected): void
    {
        foreach ($expected as $type => $methods) {
            $reflection = new ReflectionClass($type);
            foreach ($methods as $name => $indexes) {
                $parameters = $reflection->getMethod($name)->getParameters();
                foreach ($parameters as $index => $parameter) {
                    $expectedCount = in_array($index, $indexes, true) ? 1 : 0;
                    self::assertCount(
                        $expectedCount,
                        $parameter->getAttributes(\SensitiveParameter::class),
                        $type . '::' . $name,
                    );
                }
            }
        }
    }

    public function testAllowedDependenciesAndOutsideCallersAreMutationResistant(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../..');
        self::assertNotFalse($sourceRoot);
        $expectedUses = [
            'AuditOutboxConfiguration.php' => [],
            'AuditOutboxException.php' => ['RuntimeException'],
            'AuditOutboxFailure.php' => [],
            'AuditOutboxState.php' => [],
            'AuditOutboxStateSnapshot.php' => ['BlackOps\\Core\\Audit\\AuditRecordTime'],
            'AuditOutboxTransition.php' => ['BlackOps\\Core\\Audit\\AuditRecordTime'],
        ];
        foreach ($expectedUses as $basename => $expected) {
            $source = file_get_contents($sourceRoot . '/src/Internal/Audit/Delivery/' . $basename);
            self::assertIsString($source);
            self::assertSame($expected, $this->imports($source), $basename);
            if (in_array($basename, ['AuditOutboxStateSnapshot.php', 'AuditOutboxTransition.php'], true)) {
                $expectedCalls = $basename === 'AuditOutboxTransition.php' ? 2 : 1;
                self::assertSame($expectedCalls, substr_count($source, 'AuditRecordTime::assertTimestamp'));
                self::assertNotSame(
                    $expected,
                    $this->imports(str_replace('use BlackOps\\Core\\Audit\\AuditRecordTime;', '', $source)),
                    $basename,
                );
                self::assertNotSame(
                    $expectedCalls,
                    substr_count(
                        str_replace('AuditRecordTime::assertTimestamp', 'AuditEventCategory::tryFrom', $source),
                        'AuditRecordTime::assertTimestamp',
                    ),
                    $basename,
                );
            }
            foreach ([
                'Doctrine\\DBAL',
                'Psr\\Clock',
                'Uuidv7Generator',
                'AuditProtectedBodyCodec',
                'AuditProtection',
                'AuditStore',
                'BlackOps\\Application',
                'BlackOps\\Container',
                'BlackOps\\Transport',
                'BlackOps\\Console',
                'BlackOps\\Http',
                'BlackOps\\Outbox',
                'Internal\\Outbox',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $basename);
            }
        }

        $outside = $this->outsideCallerPaths($sourceRoot . '/src');
        self::assertSame([], $outside);
        $mutated = $this->outsideCallerPathsFromSources([
            'Internal/Audit/Delivery/Allowed.php' => '<?php new AuditOutboxState();',
            'Outside/Caller.php' => '<?php use BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxState; new AuditOutboxState();',
        ]);
        self::assertSame(['Outside/Caller.php'], $mutated);

        $stateSource = file_get_contents($sourceRoot . '/src/Internal/Audit/Delivery/AuditOutboxState.php');
        self::assertIsString($stateSource);
        self::assertSame(
            ['Pending' => 'pending', 'Leased' => 'leased', 'Relayed' => 'relayed', 'Poisoned' => 'poisoned'],
            $this->enumCasesFromSource($stateSource),
        );
        self::assertNotSame(
            $this->enumCasesFromSource($stateSource),
            $this->enumCasesFromSource(str_replace("case Poisoned = 'poisoned';", '', $stateSource)),
        );

        $transitionSource = file_get_contents($sourceRoot . '/src/Internal/Audit/Delivery/AuditOutboxTransition.php');
        self::assertIsString($transitionSource);
        $methodNames = $this->methodNamesFromSource($transitionSource);
        self::assertContains('claim', $methodNames);
        self::assertNotContains('missingMethod', $methodNames);
        self::assertNotSame(
            $methodNames,
            $this->methodNamesFromSource($transitionSource . "\npublic function addedMethod(): void {}\n"),
        );
        self::assertNotSame(
            $methodNames,
            $this->methodNamesFromSource(str_replace(
                'public function claim(',
                'public function removedClaim(',
                $transitionSource,
            )),
        );

        $forbiddenFixture = $transitionSource . "\nuse Doctrine\\DBAL\\Connection;\n";
        self::assertFalse($this->hasForbiddenDependency($transitionSource));
        self::assertTrue($this->hasForbiddenDependency($forbiddenFixture));
        self::assertSame([], $this->backoffExclusions($this->backoffImplementation($transitionSource)));
        $forbiddenBackoffFixture = <<<'PHP'
            $delay = (float) pow(2, $attempt);
            $delay = $delay ** 2;
            $delay += random_int(0, 1) + random_bytes(1) + mt_rand() + rand() + lcg_value();
            $jitter = $delay;
            PHP;
        self::assertSame(
            [
                'exponentiation',
                'pow',
                'floating-point',
                'jitter',
                'random_int',
                'random_bytes',
                'mt_rand',
                'rand',
                'lcg_value',
            ],
            $this->backoffExclusions($forbiddenBackoffFixture),
        );
        self::assertFalse(str_contains($transitionSource, 'PublicApi'));
        self::assertTrue(str_contains($transitionSource . "\n#[PublicApi]\n", 'PublicApi'));
    }

    /** @return array<string,list<class-string>> */
    private function expectedInventory(): array
    {
        return [
            'src/Internal/Audit/Delivery/AuditOutboxConfiguration.php' => [AuditOutboxConfiguration::class],
            'src/Internal/Audit/Delivery/AuditOutboxException.php' => [AuditOutboxException::class],
            'src/Internal/Audit/Delivery/AuditOutboxFailure.php' => [AuditOutboxFailure::class],
            'src/Internal/Audit/Delivery/AuditOutboxState.php' => [AuditOutboxState::class],
            'src/Internal/Audit/Delivery/AuditOutboxStateSnapshot.php' => [AuditOutboxStateSnapshot::class],
            'src/Internal/Audit/Delivery/AuditOutboxTransition.php' => [AuditOutboxTransition::class],
        ];
    }

    /** @return array<string,list<string>> */
    private function inventory(string $sourceRoot): array
    {
        $directory = $sourceRoot . '/src/Internal/Audit/Delivery';
        $inventory = [];
        foreach (glob($directory . '/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            $relative = substr($path, strlen($sourceRoot) + 1);
            self::assertIsString($relative);
            $inventory[$relative] = $this->declaredTypes($source);
        }
        ksort($inventory);
        return $inventory;
    }

    /** @return list<string> */
    private function declaredTypes(string $source): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $types = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = $this->qualifiedTokenName($tokens, $index + 1);
                continue;
            }
            if (!is_array($token) || !in_array($token[0], [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT], true)) {
                continue;
            }
            if ($token[0] === T_CLASS && $this->previousSignificantToken($tokens, $index) === T_NEW) {
                continue;
            }
            $name = $this->nextNameToken($tokens, $index + 1);
            if ($name !== null) {
                $types[] = $namespace . '\\' . $name;
            }
        }
        sort($types);
        return $types;
    }

    private function qualifiedTokenName(array $tokens, int $start): string
    {
        $name = '';
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_string($token) && $token === ';') {
                break;
            }
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
            }
        }
        return trim($name, '\\');
    }

    private function nextNameToken(array $tokens, int $start): ?string
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
            if (is_string($token) && $token === '{') {
                return null;
            }
        }
        return null;
    }

    private function previousSignificantToken(array $tokens, int $index): int|string|null
    {
        for ($index--; $index >= 0; $index--) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return is_array($token) ? $token[0] : $token;
        }
        return null;
    }

    private function assertConstructor(string $type, string $visibility, array $expected): void
    {
        $constructor = new ReflectionClass($type)->getConstructor();
        self::assertNotNull($constructor, $type);
        self::assertSame($visibility === 'public', $constructor->isPublic(), $type);
        self::assertSame($visibility === 'private', $constructor->isPrivate(), $type);
        $actual = [];
        foreach ($constructor->getParameters() as $parameter) {
            $actual[] = [
                $parameter->getName(),
                $this->typeName($parameter->getType()),
                $parameter->isVariadic(),
                $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                count($parameter->getAttributes(\SensitiveParameter::class)),
            ];
        }
        self::assertSame($expected, $actual, $type);
    }

    private function assertMethod(
        ReflectionMethod $method,
        string $visibility,
        string $return,
        array $expectedParameters,
        bool $static,
    ): void {
        self::assertSame($visibility === 'public', $method->isPublic());
        self::assertSame($visibility === 'private', $method->isPrivate());
        self::assertSame($static, $method->isStatic());
        self::assertSame($return, $this->typeName($method->getReturnType()), $method->getName());
        $actual = [];
        foreach ($method->getParameters() as $parameter) {
            $actual[] = [
                $parameter->getName(),
                $this->typeName($parameter->getType()),
                count($parameter->getAttributes(\SensitiveParameter::class)),
            ];
        }
        self::assertSame($expectedParameters, $actual, $method->getName());
    }

    private function typeName(?ReflectionType $type): string
    {
        if (!$type instanceof ReflectionNamedType) {
            return $type === null ? '' : (string) $type;
        }
        return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
    }

    /** @return list<string> */
    private function imports(string $source): array
    {
        preg_match_all('/^use\\s+([^;]+);/m', $source, $matches);
        $imports = [];
        foreach ($matches[1] ?? [] as $import) {
            $imports[] = trim((string) preg_replace('/\\s+as\\s+.*$/i', '', $import));
        }
        sort($imports);
        return $imports;
    }

    /** @return list<string> */
    private function outsideCallerPaths(string $sourceDirectory): array
    {
        $sources = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDirectory));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($sourceDirectory) + 1);
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $sources[$relative] = $source;
        }
        return $this->outsideCallerPathsFromSources($sources);
    }

    /** @param array<string,string> $sources */
    private function outsideCallerPathsFromSources(array $sources): array
    {
        $names = [
            'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxConfiguration',
            'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxException',
            'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxFailure',
            'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxState',
            'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxStateSnapshot',
            'BlackOps\\Internal\\Audit\\Delivery\\AuditOutboxTransition',
        ];
        $paths = [];
        foreach ($sources as $relative => $source) {
            if (
                str_contains($relative, 'Internal/Audit/Delivery/') || str_contains($relative, 'Internal/Audit/Outbox/')
            ) {
                continue;
            }
            foreach ($names as $name) {
                if (str_contains($source, $name)) {
                    $paths[] = $relative;
                    break;
                }
            }
        }
        sort($paths);
        return $paths;
    }

    /** @return array<string,string> */
    private function enumCasesFromSource(string $source): array
    {
        preg_match_all("/case\\s+([A-Za-z_][A-Za-z0-9_]*)\\s*=\\s*'([^']+)'\\s*;/", $source, $matches, PREG_SET_ORDER);
        $cases = [];
        foreach ($matches as $match) {
            $cases[$match[1]] = $match[2];
        }
        return $cases;
    }

    /** @return list<string> */
    private function methodNamesFromSource(string $source): array
    {
        preg_match_all('/\\bfunction\\s+([A-Za-z_][A-Za-z0-9_]*)\\s*\\(/', $source, $matches);
        return $matches[1] ?? [];
    }

    private function hasForbiddenDependency(string $source): bool
    {
        foreach (['Doctrine\\DBAL', 'Psr\\Clock', 'Uuidv7Generator', 'AuditProtected', 'AuditStore'] as $forbidden) {
            if (str_contains($source, $forbidden)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private function backoffExclusions(string $source): array
    {
        $forbidden = [
            'exponentiation' => '**',
            'pow' => 'pow(',
            'floating-point' => 'float',
            'jitter' => 'jitter',
            'random_int' => 'random_int(',
            'random_bytes' => 'random_bytes(',
            'mt_rand' => 'mt_rand(',
            'rand' => 'rand(',
            'lcg_value' => 'lcg_value(',
        ];
        $violations = [];
        foreach ($forbidden as $name => $needle) {
            if (str_contains($source, $needle)) {
                $violations[] = $name;
            }
        }
        return $violations;
    }

    private function backoffImplementation(string $source): string
    {
        $start = strpos($source, 'private function backoffSeconds');
        self::assertIsInt($start);
        $end = strpos($source, "\n    private function addSeconds", $start);
        self::assertIsInt($end);

        return substr($source, $start, $end - $start);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
