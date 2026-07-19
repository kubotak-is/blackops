<?php

declare(strict_types=1);

namespace BlackOps\Tests\Status;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatus;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusQuery;
use BlackOps\Status\OperationStatusResult;
use BlackOps\Status\OperationStatusUnavailable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationStatusResultAndExceptionTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testFoundUnavailableAndExpiredAreExclusiveResultTypes(): void
    {
        $status = OperationStatus::accepted(OperationId::fromString(self::OPERATION_ID), 'report.generate');
        $found = new OperationStatusFound($status);
        $unavailable = new OperationStatusUnavailable();
        $expired = new OperationStatusExpired();

        self::assertSame($status, $found->status());
        self::assertInstanceOf(OperationStatusResult::class, $found);
        self::assertInstanceOf(OperationStatusResult::class, $unavailable);
        self::assertInstanceOf(OperationStatusResult::class, $expired);
        self::assertNotInstanceOf(OperationStatusUnavailable::class, $found);
        self::assertNotInstanceOf(OperationStatusExpired::class, $unavailable);
    }

    public function testQueryFailuresExposeOnlyStableSafeCodes(): void
    {
        $exceptions = [
            [OperationStatusQueryException::authorizationFailed(), OperationStatusQueryException::AUTHORIZATION_FAILED],
            [OperationStatusQueryException::storageFailed(), OperationStatusQueryException::STORAGE_FAILED],
            [OperationStatusQueryException::decodeFailed(), OperationStatusQueryException::DECODE_FAILED],
            [OperationStatusQueryException::integrityFailed(), OperationStatusQueryException::INTEGRITY_FAILED],
        ];

        foreach ($exceptions as [$exception, $code]) {
            self::assertSame($code, $exception->queryCode());
            self::assertSame($code, $exception->getMessage());
            self::assertStringNotContainsString(self::OPERATION_ID, $exception->getMessage());
        }
    }

    public function testResultQueryAndExceptionTypesArePublicApi(): void
    {
        foreach ([
            OperationStatusResult::class,
            OperationStatusFound::class,
            OperationStatusUnavailable::class,
            OperationStatusExpired::class,
            OperationStatusQuery::class,
            OperationStatusQueryException::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }
    }
}
