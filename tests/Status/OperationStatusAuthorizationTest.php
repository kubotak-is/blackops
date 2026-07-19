<?php

declare(strict_types=1);

namespace BlackOps\Tests\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Status\DenyOperationStatusAuthorizer;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationStatusAuthorizationTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testRequestCarriesOnlySafeAuthorizationSubject(): void
    {
        $operationId = OperationId::fromString(self::OPERATION_ID);
        $current = new ActorRef('current-user', 'user');
        $origin = new ActorRef('origin-user', 'user');
        $request = new OperationStatusAuthorizationRequest($operationId, 'report.generate', $current, $origin);

        self::assertSame($operationId, $request->operationId());
        self::assertSame('report.generate', $request->operationType());
        self::assertSame($current, $request->currentActor());
        self::assertSame($origin, $request->originActor());
    }

    public function testRequestAllowsAbsentActorsWithoutInventingOne(): void
    {
        $request = new OperationStatusAuthorizationRequest(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            null,
            null,
        );

        self::assertNull($request->currentActor());
        self::assertNull($request->originActor());
    }

    public function testRequestRejectsInvalidOperationType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationStatusAuthorizationRequest(
            OperationId::fromString(self::OPERATION_ID),
            'Report Generate',
            null,
            null,
        );
    }

    public function testDecisionRepresentsOnlyAllowOrDeny(): void
    {
        self::assertTrue(OperationStatusAuthorizationDecision::allow()->isAllowed());
        self::assertFalse(OperationStatusAuthorizationDecision::deny()->isAllowed());
    }

    public function testFrameworkDefaultAuthorizerAlwaysDenies(): void
    {
        $authorizer = new DenyOperationStatusAuthorizer();
        $request = new OperationStatusAuthorizationRequest(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            new ActorRef('current-user', 'user'),
            new ActorRef('origin-user', 'user'),
        );

        self::assertFalse($authorizer->decide($request)->isAllowed());
    }

    public function testAuthorizationTypesArePublicApi(): void
    {
        foreach ([
            OperationStatusAuthorizer::class,
            OperationStatusAuthorizationRequest::class,
            OperationStatusAuthorizationDecision::class,
            DenyOperationStatusAuthorizer::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }

        self::assertTrue(
            new ReflectionClass(OperationStatusAuthorizationDecision::class)->getConstructor()?->isPrivate(),
        );
    }
}
