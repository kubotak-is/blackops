<?php

declare(strict_types=1);

namespace BlackOps\Internal\Identifier;

use DateTimeImmutable;
use Symfony\Component\Uid\UuidV7;

/**
 * Symfony UID Component を使う既定UUIDv7生成実装。公開APIへSymfony型を露出しない。
 *
 * このClassはPHP Public APIではなく、Framework内部実装詳細である。
 */
final readonly class SymfonyUuidv7Generator implements Uuidv7Generator
{
    public function generate(DateTimeImmutable $time): string
    {
        return UuidV7::generate($time);
    }
}
