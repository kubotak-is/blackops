<?php

declare(strict_types=1);

namespace App\Feature\Digest\ShowDigest;

use App\Domain\Board\DigestNotFound;
use App\Domain\Board\DigestService;
use App\Feature\BoardTime;
use App\Security\AuthenticatedUser;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/digests/{digestId}')]
#[OperationType('board.digest.show')]
#[Authorize(AuthenticatedUserPolicy::class)]
final readonly class ShowDigest implements Operation
{
    public function __construct(
        private DigestService $digests,
    ) {}

    public function handle(ShowDigestValue $value, ExecutionContext $context): DigestShown
    {
        try {
            $digest = $this->digests->show($value->digestId, AuthenticatedUser::id($context));
        } catch (DigestNotFound) {
            throw OperationRejectedException::notFound('board.digest.not_found');
        }

        return new DigestShown(
            $digest->id,
            $digest->week,
            $digest->content,
            $digest->postCount,
            $digest->commentCount,
            BoardTime::http($digest->createdAt),
        );
    }
}
