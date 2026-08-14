<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Execution\DeferredOperationContextValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeferredOperationContextValidatorTest extends TestCase
{
    public function testRejectsTenantMismatch(): void
    {
        $message = $this->message(new TenantRef('account', 'claim'));
        $context = $this->context(new TenantRef('account', 'context'));

        $this->expectException(DeferredTransportException::class);
        DeferredOperationContextValidator::assertMatches($message, $context);
    }

    public function testRejectsClearOriginMismatch(): void
    {
        $message = $this->message(null, new ActorRef('claim-origin', 'user'));
        $context = $this->context(null, null);

        $this->expectException(DeferredTransportException::class);
        DeferredOperationContextValidator::assertMatches($message, $context);
    }

    private function message(?TenantRef $tenant, ?ActorRef $origin = null): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687731'),
            'report.generate',
            1,
            '{}',
            '{}',
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            $tenant,
            $origin,
        );
    }

    private function context(?TenantRef $tenant, ?ActorRef $origin = null): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687731'),
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687732'),
            actorContext: $origin === null ? null : new ActorContext($origin, null, new ActorRef('worker', 'system')),
            tenant: $tenant,
        );
    }
}
