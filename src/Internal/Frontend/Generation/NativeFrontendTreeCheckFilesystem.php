<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

use ErrorException;

final readonly class NativeFrontendTreeCheckFilesystem implements FrontendTreeCheckFilesystem
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /** @return array<array-key, int>|false */
    public function status(string $path): array|false
    {
        $this->convertWarningsToExceptions();

        try {
            return lstat($path);
        } finally {
            restore_error_handler();
        }
    }

    /** @return array<int, string>|false */
    public function list(string $path): array|false
    {
        $this->convertWarningsToExceptions();

        try {
            return scandir($path);
        } finally {
            restore_error_handler();
        }
    }

    public function read(string $path): string|false
    {
        $this->convertWarningsToExceptions();

        try {
            return file_get_contents($path);
        } finally {
            restore_error_handler();
        }
    }

    private function convertWarningsToExceptions(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }
}
