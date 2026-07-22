<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;
use SensitiveParameter;

#[PublicApi]
interface SessionManager
{
    public function issue(string $identityId): IssuedSession;

    public function authenticate(#[SensitiveParameter] string $rawToken): ?ActorRef;

    public function rotate(#[SensitiveParameter] string $rawToken): IssuedSession;

    public function revoke(#[SensitiveParameter] ?string $rawToken): void;

    public function cleanup(DateTimeImmutable $retentionCutoff): int;
}
