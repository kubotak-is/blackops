<?php

declare(strict_types=1);

namespace BlackOps\Internal\About;

interface AboutMetadataSource
{
    public function read(): mixed;
}
