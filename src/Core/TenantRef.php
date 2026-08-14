<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class TenantRef
{
    private string $type;
    private string $id;

    public function __construct(string $type, string $id)
    {
        $type = trim($type);
        $id = trim($id);
        if ($type === '') {
            throw new InvalidArgumentException('TenantRef type must not be empty.');
        }
        if ($id === '') {
            throw new InvalidArgumentException('TenantRef id must not be empty.');
        }
        $this->type = $type;
        $this->id = $id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): string
    {
        return $this->id;
    }
}
