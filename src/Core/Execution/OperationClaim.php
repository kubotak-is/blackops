<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use SensitiveParameter;

#[PublicApi]
final readonly class OperationClaim
{
    public function __construct(
        private DeferredOperationMessage $message,
        #[SensitiveParameter]
        private string $claimToken,
    ) {
        if ($claimToken === '') {
            throw new InvalidArgumentException('Claim token must not be empty.');
        }
    }

    public function message(): DeferredOperationMessage
    {
        return $this->message;
    }

    public function claimToken(): string
    {
        return $this->claimToken;
    }
}
