<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\ClaimHeartbeat;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\ClaimSettlement;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\ExecutionTransport;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Execution\OperationReceiver;
use BlackOps\Core\Execution\OperationSender;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DeferredTransportContractTest extends TestCase
{
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    /**
     * @return array<string, array{class-string}>
     */
    public static function publicReadonlyClasses(): array
    {
        return [
            'DeferredOperationMessage' => [DeferredOperationMessage::class],
            'DeferredAcknowledgement' => [DeferredAcknowledgement::class],
            'ClaimRequest' => [ClaimRequest::class],
            'OperationClaim' => [OperationClaim::class],
        ];
    }

    /**
     * @param class-string $type
     */
    #[DataProvider('publicReadonlyClasses')]
    public function testValueObjectsArePublicFinalReadonlyClasses(string $type): void
    {
        $reflection = new ReflectionClass($type);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function publicInterfaces(): array
    {
        return [
            'OperationSender' => [OperationSender::class],
            'OperationReceiver' => [OperationReceiver::class],
            'ClaimHeartbeat' => [ClaimHeartbeat::class],
            'ClaimSettlement' => [ClaimSettlement::class],
            'ExecutionTransport' => [ExecutionTransport::class],
        ];
    }

    /**
     * @param class-string $type
     */
    #[DataProvider('publicInterfaces')]
    public function testPortsArePublicInterfaces(string $type): void
    {
        $reflection = new ReflectionClass($type);

        self::assertTrue($reflection->isInterface());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testMessageHoldsEncodedOperationData(): void
    {
        $operationId = OperationId::fromString(self::OPERATION_ID);
        $availableAt = $this->time('2026-07-10T00:00:00+00:00');

        $message = new DeferredOperationMessage(
            $operationId,
            'report.generate',
            1,
            '{"reportId":"r1"}',
            '{"correlationId":"c1"}',
            $availableAt,
        );

        self::assertSame($operationId, $message->operationId());
        self::assertSame('report.generate', $message->operationType());
        self::assertSame(1, $message->schemaVersion());
        self::assertSame('{"reportId":"r1"}', $message->encodedPayload());
        self::assertSame('{"correlationId":"c1"}', $message->encodedContext());
        self::assertSame($availableAt, $message->availableAt());
    }

    public function testMessageRejectsEmptyOperationType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeferredOperationMessage(
            OperationId::fromString(self::OPERATION_ID),
            '',
            1,
            '{}',
            '{}',
            $this->time('2026-07-10T00:00:00+00:00'),
        );
    }

    public function testMessageRejectsNonPositiveSchemaVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeferredOperationMessage(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            0,
            '{}',
            '{}',
            $this->time('2026-07-10T00:00:00+00:00'),
        );
    }

    public function testAcknowledgementHoldsDurableAcceptanceData(): void
    {
        $operationId = OperationId::fromString(self::OPERATION_ID);
        $acceptedAt = $this->time('2026-07-10T00:00:01+00:00');

        $acknowledgement = new DeferredAcknowledgement($operationId, $acceptedAt);

        self::assertSame($operationId, $acknowledgement->operationId());
        self::assertSame($acceptedAt, $acknowledgement->acceptedAt());
    }

    public function testClaimRequestHoldsClaimClockTime(): void
    {
        $claimedAt = $this->time('2026-07-10T00:00:02+00:00');

        $request = new ClaimRequest($claimedAt);

        self::assertSame($claimedAt, $request->claimedAt());
    }

    public function testOperationClaimHoldsMessageAndOpaqueToken(): void
    {
        $message = $this->message();

        $claim = new OperationClaim($message, 'claim-token-1');

        self::assertSame($message, $claim->message());
        self::assertSame('claim-token-1', $claim->claimToken());
    }

    public function testOperationClaimRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationClaim($this->message(), '');
    }

    public function testExecutionTransportCombinesResponsibilitySpecificPorts(): void
    {
        $reflection = new ReflectionClass(ExecutionTransport::class);

        self::assertTrue($reflection->implementsInterface(OperationSender::class));
        self::assertTrue($reflection->implementsInterface(OperationReceiver::class));
        self::assertTrue($reflection->implementsInterface(ClaimHeartbeat::class));
        self::assertTrue($reflection->implementsInterface(ClaimSettlement::class));
    }

    public function testPortMethodShapes(): void
    {
        self::assertSame(
            DeferredAcknowledgement::class,
            (string) new ReflectionClass(OperationSender::class)
                ->getMethod('enqueue')
                ->getReturnType(),
        );
        self::assertSame(
            '?' . OperationClaim::class,
            (string) new ReflectionClass(OperationReceiver::class)
                ->getMethod('claim')
                ->getReturnType(),
        );
        self::assertSame(
            OperationClaim::class,
            (string) new ReflectionClass(ClaimHeartbeat::class)
                ->getMethod('heartbeat')
                ->getReturnType(),
        );
        self::assertSame(
            'void',
            (string) new ReflectionClass(ClaimSettlement::class)
                ->getMethod('acknowledge')
                ->getReturnType(),
        );
        self::assertSame(
            'void',
            (string) new ReflectionClass(ClaimSettlement::class)
                ->getMethod('release')
                ->getReturnType(),
        );
    }

    public function testDeferredTransportExceptionIsPublicRuntimeException(): void
    {
        $reflection = new ReflectionClass(DeferredTransportException::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isSubclassOf(\RuntimeException::class));
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    private function message(): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            1,
            '{}',
            '{}',
            $this->time('2026-07-10T00:00:00+00:00'),
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
