<?php

declare(strict_types=1);

namespace BlackOps\Journal\Exception;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final class JournalWriteFailed extends \RuntimeException {}
