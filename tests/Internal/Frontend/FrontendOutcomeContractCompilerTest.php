<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend;

use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;
use BlackOps\Internal\Frontend\FrontendOutcomeContractCompiler;
use BlackOps\Tests\Fixtures\Frontend\Outcome\CollisionOutcome;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FrontendOutcomeContractCompilerTest extends TestCase
{
    public function testCompilesRecursiveDiscriminatedOutcomeSchema(): void
    {
        $contract = new FrontendOutcomeContractCompiler()->compile(FrontendStructuredOutcomeFixture::class);

        self::assertSame('outcome', $contract->mode);
        self::assertSame(['items', 'optionalOwner', 'owner'], array_column($contract->fields, 'name'));
        self::assertSame(
            ['list', 'dto', 'dto'],
            array_map(static fn($field): string => $field->type->kind, $contract->fields),
        );
        self::assertFalse($contract->fields[0]->type->nullable);
        self::assertSame(FrontendItemDataFixture::class, $contract->fields[0]->type->class);
        self::assertSame(['owner', 'quantity'], array_column($contract->fields[0]->type->fields, 'name'));
        self::assertTrue($contract->fields[0]->type->fields[0]->type->nullable);
        self::assertSame('integer', $contract->fields[0]->type->fields[1]->type->scalar);
    }

    public function testRejectsSensitiveFieldReachableThroughListElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sensitive outcome field');
        new FrontendOutcomeContractCompiler()->compile(FrontendSensitiveListOutcomeFixture::class);
    }

    public function testRejectsCaseInsensitiveShortDtoNameCollision(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ambiguous');
        new FrontendOutcomeContractCompiler()->compile(CollisionOutcome::class);
    }
}

final readonly class FrontendOwnerDataFixture implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}

final readonly class FrontendItemDataFixture implements OutcomeData
{
    public function __construct(
        public ?FrontendOwnerDataFixture $owner,
        public int $quantity,
    ) {}
}

final readonly class FrontendStructuredOutcomeFixture implements Outcome
{
    /** @param list<FrontendItemDataFixture> $items */
    public function __construct(
        #[ListOf(FrontendItemDataFixture::class)]
        public array $items,
        public ?FrontendOwnerDataFixture $optionalOwner,
        public FrontendOwnerDataFixture $owner,
    ) {}
}

final readonly class FrontendSensitiveDataFixture implements OutcomeData
{
    public function __construct(
        #[Sensitive]
        public string $token,
    ) {}
}

final readonly class FrontendSensitiveListOutcomeFixture implements Outcome
{
    /** @param list<FrontendSensitiveDataFixture> $items */
    public function __construct(
        #[ListOf(FrontendSensitiveDataFixture::class)]
        public array $items,
    ) {}
}
