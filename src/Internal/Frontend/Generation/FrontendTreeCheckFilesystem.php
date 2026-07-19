<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

interface FrontendTreeCheckFilesystem
{
    public function exists(string $path): bool;

    /** @return array<array-key, int>|false */
    public function status(string $path): array|false;

    /** @return array<int, string>|false */
    public function list(string $path): array|false;

    public function read(string $path): string|false;
}
