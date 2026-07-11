<?php

declare(strict_types=1);

namespace BlackOps\Outcome;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface OutcomeStore extends OutcomeReader, OutcomeWriter {}
