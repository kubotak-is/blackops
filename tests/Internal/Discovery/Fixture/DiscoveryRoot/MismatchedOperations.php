<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery\Fixture\DiscoveryRoot;

use BlackOps\Core\Operation;

interface DiscoveryOperationContract extends Operation {}

trait DiscoveryTrait {}

enum DiscoveryEnum
{
    case Example;
}

final readonly class ClassmapOperation implements Operation {}

final readonly class TokenOnlyOperation implements Operation {}

final readonly class IndirectOperation implements DiscoveryOperationContract {}

abstract class AbstractOperation implements Operation {}

final readonly class NonOperation {}

new class implements Operation {};
