<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Board\DigestService;
use App\Feature\Digest\DigestAttemptGate;
use App\Feature\Digest\GenerateWeeklyDigest\GenerateWeeklyDigest;
use App\Feature\Digest\GenerateWeeklyDigest\GenerateWeeklyDigestValue;
use App\Infrastructure\Deferred\FailFirstDigestAttemptGate;
use App\Infrastructure\Deferred\NoOpDigestAttemptGate;
use App\Tests\Support\FrozenBoardClock;
use App\Tests\Support\InMemoryDigestRepository;
use App\Tests\Support\SequenceBoardIdGenerator;
use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\AttemptContext;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class DigestOperationTest extends TestCase
{
    private const string USER = '019b1000-0000-7000-8000-000000000001';
    private const string DIGEST = '019b5000-0000-7000-8000-000000000001';

    public function testMissingAttemptFailsBeforeTheGateAndDomain(): void
    {
        $gate = new RecordingDigestAttemptGate();
        $repository = new InMemoryDigestRepository();
        $operation = new GenerateWeeklyDigest($this->service($repository), $gate);

        $this->expectException(LogicException::class);
        try {
            $operation->handle(new GenerateWeeklyDigestValue('2026-W30'), $this->context(null));
        } finally {
            self::assertSame([], $gate->attempts);
            self::assertSame([], $repository->digests);
        }
    }

    public function testFirstAttemptRetriesAndSecondAttemptGeneratesTypedOutcome(): void
    {
        $repository = new InMemoryDigestRepository();
        $first = new GenerateWeeklyDigest($this->service($repository), new FailFirstDigestAttemptGate());
        try {
            $first->handle(new GenerateWeeklyDigestValue('2026-W30'), $this->context(1));
            self::fail('Expected retryable first attempt.');
        } catch (\App\Feature\Digest\DigestGenerationTemporarilyUnavailable) {
            self::assertSame([], $repository->digests);
        }

        $second = new GenerateWeeklyDigest($this->service($repository), new NoOpDigestAttemptGate());
        $outcome = $second->handle(new GenerateWeeklyDigestValue('2026-W30'), $this->context(2));
        self::assertSame(self::DIGEST, $outcome->digestId);
        self::assertSame('2026-W30', $outcome->week);
        self::assertSame(0, $outcome->postCount);
        self::assertSame(0, $outcome->commentCount);
        self::assertCount(1, $repository->digests);
    }

    public function testSemanticInvalidWeekMapsToStableValidationRejection(): void
    {
        $operation = new GenerateWeeklyDigest(
            $this->service(new InMemoryDigestRepository()),
            new NoOpDigestAttemptGate(),
        );

        try {
            $operation->handle(new GenerateWeeklyDigestValue('2021-W53'), $this->context(1));
            self::fail('Expected semantic validation rejection.');
        } catch (OperationRejectedException $exception) {
            self::assertSame('validation', $exception->reason()->category()->value);
            self::assertSame('board.digest.invalid_week', $exception->reason()->code());
            self::assertSame([], $exception->reason()->violations());
        }
    }

    private function service(InMemoryDigestRepository $repository): DigestService
    {
        return new DigestService(
            $repository,
            new FrozenBoardClock(new DateTimeImmutable('2026-07-21T00:00:00Z')),
            new SequenceBoardIdGenerator([self::DIGEST]),
        );
    }

    private function context(?int $attempt): ExecutionContext
    {
        $actor = new ActorRef(self::USER, 'user');

        return new ExecutionContext(
            OperationId::fromString('019b4000-0000-7000-8000-000000000001'),
            new DateTimeImmutable('2026-07-21T00:00:00Z'),
            CorrelationId::fromString('019b4000-0000-7000-8000-000000000002'),
            attempt: $attempt === null
                ? null
                : new AttemptContext(
                    AttemptId::fromString('019b4000-0000-7000-8000-000000000003'),
                    $attempt,
                    new DateTimeImmutable('2026-07-21T00:00:00Z'),
                ),
            actorContext: new ActorContext($actor, $actor, new ActorRef('worker', 'service')),
        );
    }
}

final class RecordingDigestAttemptGate implements DigestAttemptGate
{
    /** @var list<int> */
    public array $attempts = [];

    public function beforeGeneration(int $attemptNumber): void
    {
        $this->attempts[] = $attemptNumber;
    }
}
