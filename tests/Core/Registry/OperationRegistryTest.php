<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Registry;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationRegistryTest extends TestCase
{
    public function testIndexesMetadataAndPreservesOrder(): void
    {
        $first = $this->metadata('one.show', RegistryOperationOne::class);
        $second = $this->metadata('two.show', RegistryOperationTwo::class);
        $registry = new OperationRegistry([$first, $second]);

        self::assertSame($first, $registry->findByTypeId('one.show'));
        self::assertSame($second, $registry->findByDefinition(RegistryOperationTwo::class));
        self::assertSame([$first, $second], $registry->all());
        self::assertNull($registry->findByTypeId('missing'));
        self::assertNull($registry->findByDefinition(RegistryMissingOperation::class));
    }

    public function testPublicReadonlyShape(): void
    {
        $reflection = new ReflectionClass(OperationRegistry::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testDuplicateTypeIdIsRejectedWithoutLeakingValue(): void
    {
        try {
            new OperationRegistry([
                $this->metadata('duplicate.show', RegistryOperationOne::class),
                $this->metadata('duplicate.show', RegistryOperationTwo::class),
            ]);
            self::fail('Expected duplicate rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('duplicate.show', $exception->getMessage());
        }
    }

    private function metadata(string $typeId, string $definition): OperationMetadata
    {
        return new OperationMetadata(
            $typeId,
            $definition,
            RegistryValue::class,
            RegistryHandler::class,
            EmptyOutcome::class,
            Inline::class,
        );
    }
}

final readonly class RegistryOperationOne implements Operation {}

final readonly class RegistryOperationTwo implements Operation {}

final readonly class RegistryMissingOperation implements Operation {}

final readonly class RegistryValue implements OperationValue {}

abstract class RegistryHandler implements OperationHandler {}
