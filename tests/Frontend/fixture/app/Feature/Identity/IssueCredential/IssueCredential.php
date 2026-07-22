<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Identity\IssueCredential;

use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/identity/credentials')]
#[OperationType('identity.credential.issue')]
#[ExecuteWith(Inline::class)]
final readonly class IssueCredential implements Operation
{
    public function handle(IssueCredentialValue $value): CredentialIssued
    {
        return new CredentialIssued('fixture-runtime-value', '2026-07-23T00:00:00+00:00');
    }
}
