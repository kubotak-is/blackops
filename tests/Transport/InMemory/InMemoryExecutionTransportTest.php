<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\InMemory;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\ExecutionTransport;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\InMemory\InMemoryExecutionTransport;
use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class InMemoryExecutionTransportTest extends TestCase
{
    private const FIRST_OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const SECOND_OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687698';
    private const THIRD_OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687699';

    public function testImplementsExecutionTransportAndAcknowledgesEnqueueAtClockTime(): void
    {
        $clock = new InMemoryTransportClock('2026-07-10T00:00:01.123456Z');
        $transport = new InMemoryExecutionTransport($clock, 30);
        $message = $this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z');

        $acknowledgement = $transport->enqueue($message);

        self::assertInstanceOf(ExecutionTransport::class, $transport);
        self::assertSame($message->operationId(), $acknowledgement->operationId());
        self::assertSame($clock->now(), $acknowledgement->acceptedAt());
    }

    public function testRejectsDuplicateOperationIdEvenWhenMessageContentDiffers(): void
    {
        $transport = $this->transport();
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));

        $this->expectException(DeferredTransportException::class);

        $transport->enqueue(
            new DeferredOperationMessage(
                OperationId::fromString(self::FIRST_OPERATION_ID),
                'report.different',
                2,
                '{"different":true}',
                '{}',
                $this->time('2026-07-10T00:01:00Z'),
            ),
        );
    }

    public function testAvailableAtUsesInstantAndIsEligibleAtExactBoundary(): void
    {
        $transport = $this->transport();
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T09:00:00+09:00'));

        self::assertNull($transport->claim(new ClaimRequest($this->time('2026-07-09T23:59:59.999999Z'))));

        $claim = $transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:00.000000Z')));

        self::assertNotNull($claim);
        self::assertSame(self::FIRST_OPERATION_ID, $claim->message()->operationId()->toString());
    }

    public function testClaimsOneEligibleMessageByAvailableAtThenOperationId(): void
    {
        $transport = $this->transport();
        $transport->enqueue($this->message(self::THIRD_OPERATION_ID, '2026-07-10T00:00:01Z'));
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:01Z'));
        $transport->enqueue($this->message(self::SECOND_OPERATION_ID, '2026-07-10T00:00:00Z'));
        $request = new ClaimRequest($this->time('2026-07-10T00:00:02Z'));

        $first = $transport->claim($request);
        $second = $transport->claim($request);
        $third = $transport->claim($request);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotNull($third);
        self::assertSame(self::SECOND_OPERATION_ID, $first->message()->operationId()->toString());
        self::assertSame(self::FIRST_OPERATION_ID, $second->message()->operationId()->toString());
        self::assertSame(self::THIRD_OPERATION_ID, $third->message()->operationId()->toString());
        self::assertNull($transport->claim($request));
    }

    public function testLeaseExpiryReclaimsAtExactBoundaryWithIncreasingFencingToken(): void
    {
        $transport = $this->transport();
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));

        $first = $transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:00Z')));

        self::assertNotNull($first);
        self::assertSame(self::FIRST_OPERATION_ID . ':1', $first->claimToken());
        self::assertStringNotContainsString('payload', $first->claimToken());
        self::assertNull($transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:29.999999Z'))));

        $second = $transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:30.000000Z')));

        self::assertNotNull($second);
        self::assertSame(self::FIRST_OPERATION_ID . ':2', $second->claimToken());
    }

    public function testHeartbeatExtendsLeaseFromClockTime(): void
    {
        $clock = new InMemoryTransportClock('2026-07-10T00:00:00Z');
        $transport = new InMemoryExecutionTransport($clock, 30);
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));
        $claim = $transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:00Z')));
        self::assertNotNull($claim);
        $clock->set('2026-07-10T00:00:20Z');

        $returned = $transport->heartbeat($claim);

        self::assertSame($claim, $returned);
        self::assertNull($transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:30Z'))));
        self::assertNull($transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:49.999999Z'))));
        self::assertNotNull($transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:50Z'))));
    }

    public function testHeartbeatAtLeaseExpiryIsRejectedWithoutChangingState(): void
    {
        $clock = new InMemoryTransportClock('2026-07-10T00:00:00Z');
        $transport = new InMemoryExecutionTransport($clock, 30);
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));
        $claim = $transport->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($claim);
        $clock->set('2026-07-10T00:00:30Z');

        $this->assertTransportException(static fn(): OperationClaim => $transport->heartbeat($claim));

        $replacement = $transport->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($replacement);
        self::assertSame(self::FIRST_OPERATION_ID . ':2', $replacement->claimToken());
    }

    public function testAcknowledgeSettlesMessageAndSettledClaimOperationsAreRejected(): void
    {
        $clock = new InMemoryTransportClock('2026-07-10T00:00:00Z');
        $transport = new InMemoryExecutionTransport($clock, 30);
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));
        $claim = $transport->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($claim);
        $clock->set('2026-07-10T00:00:10Z');

        $transport->acknowledge($claim);

        self::assertNull($transport->claim(new ClaimRequest($this->time('2026-07-10T01:00:00Z'))));
        $this->assertClaimOperationsRejected($transport, $claim);
    }

    public function testReleaseRequeuesAtSpecifiedInstantAndInvalidatesOldClaim(): void
    {
        $clock = new InMemoryTransportClock('2026-07-10T00:00:00Z');
        $transport = new InMemoryExecutionTransport($clock, 30);
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));
        $claim = $transport->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($claim);
        $clock->set('2026-07-10T00:00:10Z');
        $availableAt = $this->time('2026-07-10T00:01:00Z');

        $transport->release($claim, $availableAt);

        $this->assertTransportException(static fn(): OperationClaim => $transport->heartbeat($claim));
        self::assertNull($transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:59.999999Z'))));
        $replacement = $transport->claim(new ClaimRequest($availableAt));
        self::assertNotNull($replacement);
        self::assertSame(self::FIRST_OPERATION_ID . ':2', $replacement->claimToken());
        self::assertSame($availableAt, $replacement->message()->availableAt());
    }

    public function testReleaseAtLeaseExpiryIsRejectedWithoutChangingState(): void
    {
        $clock = new InMemoryTransportClock('2026-07-10T00:00:00Z');
        $transport = new InMemoryExecutionTransport($clock, 30);
        $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));
        $claim = $transport->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($claim);
        $clock->set('2026-07-10T00:00:30Z');

        $this->assertTransportException(static function () use ($transport, $claim): void {
            $transport->release($claim, new DateTimeImmutable('2026-07-10T01:00:00Z'));
        });

        $replacement = $transport->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($replacement);
        self::assertSame(self::FIRST_OPERATION_ID . ':2', $replacement->claimToken());
    }

    public function testStaleFencingTokenCannotChangeCurrentClaimState(): void
    {
        foreach (['heartbeat', 'acknowledge', 'release'] as $operation) {
            $clock = new InMemoryTransportClock('2026-07-10T00:00:00Z');
            $transport = new InMemoryExecutionTransport($clock, 30);
            $transport->enqueue($this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z'));
            $stale = $transport->claim(new ClaimRequest($clock->now()));
            self::assertNotNull($stale);
            $current = $transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:30Z')));
            self::assertNotNull($current);
            $clock->set('2026-07-10T00:00:40Z');

            $this->assertTransportException(fn(): mixed => $this->operate($transport, $stale, $operation));

            self::assertNull($transport->claim(new ClaimRequest($this->time('2026-07-10T00:00:59.999999Z'))));
            $replacement = $transport->claim(new ClaimRequest($this->time('2026-07-10T00:01:00Z')));
            self::assertNotNull($replacement);
            self::assertSame(self::FIRST_OPERATION_ID . ':3', $replacement->claimToken());
        }
    }

    public function testUnknownClaimOperationsAreRejected(): void
    {
        foreach (['heartbeat', 'acknowledge', 'release'] as $operation) {
            $transport = $this->transport();
            $message = $this->message(self::FIRST_OPERATION_ID, '2026-07-10T00:00:00Z');
            $unknown = new OperationClaim($message, self::FIRST_OPERATION_ID . ':1');

            $this->assertTransportException(fn(): mixed => $this->operate($transport, $unknown, $operation));
        }
    }

    public function testRejectsNonPositiveLeaseDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InMemoryExecutionTransport(new InMemoryTransportClock('2026-07-10T00:00:00Z'), 0);
    }

    private function transport(): InMemoryExecutionTransport
    {
        return new InMemoryExecutionTransport(new InMemoryTransportClock('2026-07-10T00:00:00Z'), 30);
    }

    private function message(string $operationId, string $availableAt): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($operationId),
            'report.generate',
            1,
            '{"payload":"weekly"}',
            '{"context":"test"}',
            $this->time($availableAt),
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    private function assertClaimOperationsRejected(InMemoryExecutionTransport $transport, OperationClaim $claim): void
    {
        foreach (['heartbeat', 'acknowledge', 'release'] as $operation) {
            $this->assertTransportException(fn(): mixed => $this->operate($transport, $claim, $operation));
        }
    }

    private function operate(InMemoryExecutionTransport $transport, OperationClaim $claim, string $operation): mixed
    {
        if ($operation === 'heartbeat') {
            return $transport->heartbeat($claim);
        }

        if ($operation === 'acknowledge') {
            $transport->acknowledge($claim);

            return null;
        }

        $transport->release($claim, $this->time('2026-07-10T01:00:00Z'));

        return null;
    }

    private function assertTransportException(Closure $operation): void
    {
        try {
            $operation();
            self::fail('Expected deferred transport operation to fail.');
        } catch (DeferredTransportException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }
}

final class InMemoryTransportClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->set($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(string $now): void
    {
        $this->now = new DateTimeImmutable($now);
    }
}
