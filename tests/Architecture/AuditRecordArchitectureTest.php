<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Internal\Audit\Projection\ObservedAuditRecordProjector;
use BlackOps\Internal\Audit\Record\AuditLogicalRecord;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\ValidatedAuditLogicalRecord;
use PHPUnit\Framework\TestCase;

final class AuditRecordArchitectureTest extends TestCase
{
    public function testA2TypesHaveNoPublicApiMarker(): void
    {
        $paths = [
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditAttemptKind.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditSourceRuntime.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditRecord*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Core/Audit/AuditProjection*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/Audit/Record/*.php') ?: []),
            ...(glob(__DIR__ . '/../../src/Internal/Audit/Projection/*.php') ?: []),
        ];
        foreach ($paths as $path) {
            $namespace = str_contains($path, '/src/Core/') ? 'BlackOps\\Core\\Audit\\' : 'BlackOps\\Internal\\Audit\\';
            $class = $namespace . basename($path, '.php');
            if (str_contains($path, '/src/Internal/Audit/Record/'))
                $class = 'BlackOps\\Internal\\Audit\\Record\\' . basename($path, '.php');
            if (str_contains($path, '/src/Internal/Audit/Projection/'))
                $class = 'BlackOps\\Internal\\Audit\\Projection\\' . basename($path, '.php');
            self::assertSame([], new \ReflectionClass($class)->getAttributes(PublicApi::class), $class);
        }
    }

    public function testLogicalBoundaryHasInterfaceOnlyAndValidatedResultDoesNotImplementIt(): void
    {
        self::assertTrue(interface_exists(AuditLogicalRecord::class));
        self::assertFalse(is_a(ValidatedAuditLogicalRecord::class, AuditLogicalRecord::class, true));
        foreach (glob(__DIR__ . '/../../src/Internal/Audit/Record/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString('implements AuditLogicalRecord', $source, $path);
        }
        $source = file_get_contents(__DIR__ . '/../../src/Internal/Audit/Record/AuditLogicalRecord.php');
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, 'function toArray'));
    }

    public function testValidatorAndProjectorExposeOnlyTheBoundedA2Seams(): void
    {
        $validator = new \ReflectionClass(AuditRecordValidator::class);
        $validateDraft = $validator->getMethod('validateDraft');
        self::assertSame(
            [AuditRecordDraft::class],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $validateDraft->getParameters()),
        );
        self::assertSame('void', $validateDraft->getReturnType()?->getName());
        $validateLogical = $validator->getMethod('validateLogical');
        self::assertSame(
            [AuditLogicalRecord::class],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $validateLogical->getParameters()),
        );
        self::assertSame(ValidatedAuditLogicalRecord::class, $validateLogical->getReturnType()?->getName());

        $project = new \ReflectionClass(ObservedAuditRecordProjector::class)->getMethod('project');
        self::assertSame(
            [AuditLogicalRecord::class],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $project->getParameters()),
        );
        self::assertSame('string', $project->getReturnType()?->getName());
        self::assertSame(
            [],
            array_filter(
                new \ReflectionClass(ObservedAuditRecordProjector::class)->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn(\ReflectionMethod $method): bool => (
                    $method->getName() !== '__construct'
                    && $method->getName() !== 'project'
                ),
            ),
        );
    }

    public function testCorrelationUsesPrivateConstructionAndOneFactorySignature(): void
    {
        $reflection = new \ReflectionClass(AuditRecordCorrelation::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertSame(
            ['fromArray', 'toArray'],
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
        );
        $factory = $reflection->getMethod('fromArray');
        self::assertSame(
            ['array'],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter
                ->getType()
                ?->getName(), $factory->getParameters()),
        );
        self::assertSame(AuditRecordCorrelation::class, $factory->getReturnType()?->getName());
    }

    public function testValidatedResultHasOnlyItsDetachedSnapshotAndDefinition(): void
    {
        $reflection = new \ReflectionClass(ValidatedAuditLogicalRecord::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['snapshot', 'definition'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
        self::assertSame(
            ['__construct'],
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
        );
    }

    public function testA2DoesNotAddRuntimeOrPublicIntegrationFiles(): void
    {
        self::assertSame([], glob(__DIR__ . '/../../src/Application/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Http/Audit/*') ?: []);
        self::assertSame([], glob(__DIR__ . '/../../src/Console/Audit/*') ?: []);
    }

    public function testA2ProductTypesHaveNoSourceRegistrationOutsideTheirAllowedDirectories(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($sourceRoot);
        $allowedDirectories = array_filter([
            realpath($sourceRoot . '/Core/Audit'),
            realpath($sourceRoot . '/Internal/Audit/Record'),
            realpath($sourceRoot . '/Internal/Audit/Projection'),
        ]);
        $productTypes = [
            'AuditAttemptKind',
            'AuditSourceRuntime',
            'AuditRecordAttempt',
            'AuditRecordEvent',
            'AuditRecordAction',
            'AuditRecordActor',
            'AuditRecordResource',
            'AuditRecordOutcome',
            'AuditRecordPolicy',
            'AuditRecordSource',
            'AuditRecordCorrelation',
            'AuditRecordEvidence',
            'AuditRecordTime',
            'AuditRecordDraft',
            'AuditProjectionFailure',
            'AuditProjectionException',
            'AuditLogicalRecord',
            'ValidatedAuditLogicalRecord',
            'AuditRecordValidator',
            'AuditProjectionByteGuard',
            'ObservedAuditRecordProjector',
        ];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php')
                continue;
            $path = $file->getPathname();
            $normalized = realpath($path);
            self::assertNotFalse($normalized);
            foreach ($allowedDirectories as $directory) {
                if ($normalized === $directory || str_starts_with($normalized, $directory . DIRECTORY_SEPARATOR))
                    continue 2;
            }
            $source = file_get_contents($path);
            self::assertIsString($source);
            foreach ($productTypes as $productType)
                self::assertStringNotContainsString($productType, $source, $path);
            self::assertStringNotContainsString('observed_v1', $source, $path);
        }
    }
}
