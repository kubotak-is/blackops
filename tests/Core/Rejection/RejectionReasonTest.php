<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Rejection;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Validation\Violation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RejectionReasonTest extends TestCase
{
    public static function categories(): array
    {
        return [
            [RejectionCategory::Validation,   'validation'],
            [RejectionCategory::Unauthorized, 'unauthorized'],
            [RejectionCategory::Forbidden,    'forbidden'],
            [RejectionCategory::NotFound,     'not_found'],
            [RejectionCategory::Conflict,     'conflict'],
            [RejectionCategory::BusinessRule, 'business_rule'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('categories')]
    public function testCategoryWireValues(RejectionCategory $category, string $value): void
    {
        self::assertSame($value, $category->value);
    }

    public function testPublicApiShape(): void
    {
        $reason = new ReflectionClass(RejectionReason::class);
        $category = new ReflectionClass(RejectionCategory::class);

        self::assertTrue($reason->isFinal());
        self::assertTrue($reason->isReadOnly());
        self::assertTrue($reason->getConstructor()?->isPrivate());
        self::assertCount(1, $reason->getAttributes(PublicApi::class));
        self::assertCount(1, $category->getAttributes(PublicApi::class));
    }

    public function testCategoryFactoriesAndCode(): void
    {
        $cases = [
            [RejectionReason::validation('name.required'),          RejectionCategory::Validation],
            [RejectionReason::unauthorized('authentication_required'), RejectionCategory::Unauthorized],
            [RejectionReason::forbidden('operation-forbidden'),     RejectionCategory::Forbidden],
            [RejectionReason::notFound('order.not_found'),          RejectionCategory::NotFound],
            [RejectionReason::conflict('inventory_unavailable'),    RejectionCategory::Conflict],
            [RejectionReason::businessRule('minimum_quantity'),     RejectionCategory::BusinessRule],
        ];

        foreach ($cases as [$reason, $category]) {
            self::assertSame($category, $reason->category());
            self::assertNotSame('', $reason->code());
        }
    }

    public function testValidationReasonCarriesOnlyStructuredViolations(): void
    {
        $violations = [new Violation('email', 'email', 'validation.email')];
        $reason = RejectionReason::validation('validation.failed', $violations);

        self::assertSame($violations, $reason->violations());
    }

    public function testValidationReasonRejectsMalformedViolationList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection reason violations must be a list of validation violations.');

        /** @phpstan-ignore-next-line */
        RejectionReason::validation('validation.failed', ['raw-secret']);
    }

    public static function invalidCodes(): array
    {
        return [
            [''],
            ['UPPERCASE'],
            ['has space'],
            ['.leading'],
            ['trailing.'],
            ['two..dots'],
            ['日本語'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCodes')]
    public function testInvalidCodeIsRejectedWithoutLeakingInput(string $code): void
    {
        try {
            RejectionReason::conflict($code);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Rejection reason requires a valid stable code.', $exception->getMessage());

            if ($code !== '') {
                self::assertStringNotContainsString($code, $exception->getMessage());
            }
        }
    }
}
