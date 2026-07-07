<?php

declare(strict_types=1);

namespace BlackOps\Internal\Identifier;

use DateTimeImmutable;

/**
 * UUID Version 7 文字列生成の内部Port。Test环境下で生成源を差し替え可能にする。
 *
 * このInterfaceはPHP Public APIではなく、Framework内部実装詳細である。
 */
interface Uuidv7Generator
{
    public function generate(DateTimeImmutable $time): string;
}
