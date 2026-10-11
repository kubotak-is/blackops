<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\AuditStringValidator;

final readonly class AuditAnchorExpectation
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        int $version,
        #[\SensitiveParameter]
        ?string $stateHash,
        #[\SensitiveParameter]
        ?string $token,
    ) {
        try {
            if ($version < 0 || $version > \BlackOps\Internal\Audit\AuditJcsCodec::MAX_SAFE_INTEGER) {
                throw new \RuntimeException();
            }
            if ($version === 0 && ($stateHash !== null || $token !== null)) {
                throw new \RuntimeException();
            }
            if ($version > 0 && ($stateHash === null || preg_match('/^[a-f0-9]{64}$/D', $stateHash) !== 1)) {
                throw new \RuntimeException();
            }
            if ($token !== null) {
                AuditStringValidator::assertSafe($token);
            }
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
        $this->snapshot = new \SensitiveParameterValue([
            'version' => $version,
            'stateHash' => $stateHash,
            'token' => $token,
        ]);
    }

    /** @return array{version:int,stateHash:?string,token:?string} */
    public function toArray(): array
    {
        /** @var array{version:int,stateHash:?string,token:?string} $value */
        $value = $this->snapshot->getValue();
        return [...$value];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
    }
}
