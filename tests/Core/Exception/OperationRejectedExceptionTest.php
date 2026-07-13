<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Exception;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Rejection\RejectionCategory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationRejectedExceptionTest extends TestCase
{
    /** @return iterable<string, array{OperationRejectedException, RejectionCategory, string}> */
    public static function factories(): iterable
    {
        yield 'validation' => [
            OperationRejectedException::validation('input.invalid'),
            RejectionCategory::Validation,
            'input.invalid',
        ];
        yield 'unauthorized' => [
            OperationRejectedException::unauthorized('auth.required'),
            RejectionCategory::Unauthorized,
            'auth.required',
        ];
        yield 'forbidden' => [
            OperationRejectedException::forbidden('report.forbidden'),
            RejectionCategory::Forbidden,
            'report.forbidden',
        ];
        yield 'not found' => [
            OperationRejectedException::notFound('report.not_found'),
            RejectionCategory::NotFound,
            'report.not_found',
        ];
        yield 'conflict' => [
            OperationRejectedException::conflict('inventory_unavailable'),
            RejectionCategory::Conflict,
            'inventory_unavailable',
        ];
        yield 'business rule' => [
            OperationRejectedException::businessRule('order.cannot_create'),
            RejectionCategory::BusinessRule,
            'order.cannot_create',
        ];
    }

    #[DataProvider('factories')]
    public function testProvidesPublicStableRejectionReason(
        OperationRejectedException $exception,
        RejectionCategory $category,
        string $code,
    ): void {
        self::assertSame($category, $exception->reason()->category());
        self::assertSame($code, $exception->reason()->code());
        self::assertSame('Operation was rejected.', $exception->getMessage());
        self::assertNotEmpty(new ReflectionClass($exception)->getAttributes(PublicApi::class));
    }

    public function testDelegatesCodeValidationToRejectionReason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OperationRejectedException::conflict('SECRET VALUE');
    }
}
