<?php

declare(strict_types=1);

namespace App\Feature\Digest\GenerateWeeklyDigest;

use App\Domain\Board\DigestService;
use App\Domain\Board\InvalidDigestWeek;
use App\Domain\Board\IsoWeek;
use App\Feature\BoardTime;
use App\Feature\Digest\DigestAttemptGate;
use App\Security\AuthenticatedUser;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\Deferred;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;
use LogicException;

#[Route(method: 'POST', path: '/digests')]
#[OperationType('board.digest.weekly.generate')]
#[Deferred]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class GenerateWeeklyDigest implements Operation
{
    public function __construct(
        private DigestService $digests,
        private DigestAttemptGate $attempts,
    ) {}

    #[Transactional]
    public function handle(GenerateWeeklyDigestValue $value, ExecutionContext $context): DigestGenerated
    {
        $attempt = $context->attempt();
        if ($attempt === null) {
            throw new LogicException('Digest generation requires a deferred attempt.');
        }

        $this->attempts->beforeGeneration($attempt->number());

        try {
            $digest = $this->digests->generate(AuthenticatedUser::id($context), IsoWeek::fromString($value->week));
        } catch (InvalidDigestWeek) {
            throw OperationRejectedException::validation('board.digest.invalid_week');
        }

        return new DigestGenerated(
            $digest->id,
            $digest->week,
            $digest->postCount,
            $digest->commentCount,
            BoardTime::http($digest->createdAt),
        );
    }
}
