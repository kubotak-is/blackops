<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Feature\Digest\DigestGenerationTemporarilyUnavailable;
use App\Infrastructure\Deferred\FailFirstDigestAttemptGate;
use App\Infrastructure\Deferred\NoOpDigestAttemptGate;
use App\Security\BoardOperationStatusAuthorizer;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Supervision\RetryableException;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use PHPUnit\Framework\TestCase;

final class DigestBoundaryTest extends TestCase
{
    private const string OPERATION = '019b4000-0000-7000-8000-000000000001';

    public function testAttemptGatesAreSafeAndOnlyTheFirstConfiguredAttemptRetries(): void
    {
        new NoOpDigestAttemptGate()->beforeGeneration(1);
        new FailFirstDigestAttemptGate()->beforeGeneration(2);

        try {
            new FailFirstDigestAttemptGate()->beforeGeneration(1);
            self::fail('Expected retryable failure.');
        } catch (DigestGenerationTemporarilyUnavailable $exception) {
            self::assertInstanceOf(RetryableException::class, $exception);
        }
    }

    public function testStatusAuthorizerAllowsOnlySameUserForDigestGeneration(): void
    {
        $authorizer = new BoardOperationStatusAuthorizer();
        $alice = new ActorRef('alice', 'user');
        $bob = new ActorRef('bob', 'user');
        $service = new ActorRef('alice', 'service');

        self::assertTrue(
            $authorizer->decide($this->request('board.digest.weekly.generate', $alice, $alice))->isAllowed(),
        );
        foreach ([
            $this->request('board.digest.weekly.generate', null, $alice),
            $this->request('board.digest.weekly.generate', $alice, null),
            $this->request('board.digest.weekly.generate', $alice, $bob),
            $this->request('board.digest.weekly.generate', $service, $alice),
            $this->request('board.post.create', $alice, $alice),
        ] as $request) {
            self::assertFalse($authorizer->decide($request)->isAllowed());
        }
    }

    private function request(string $type, ?ActorRef $current, ?ActorRef $origin): OperationStatusAuthorizationRequest
    {
        return new OperationStatusAuthorizationRequest(
            OperationId::fromString(self::OPERATION),
            $type,
            $current,
            $origin,
        );
    }
}
