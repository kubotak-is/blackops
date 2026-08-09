<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyGenerator;

use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyInvocation;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\DefaultEnum;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\LeftValue;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\RightValue;

#[Transactional]
class GeneratedMemberCollisionService
{
    public function __blackopsInitialize(FrameworkProxyInvocation $invocation): void {}

    public function run(): void {}
}

class OperationAfterCommitService implements Operation
{
    #[AfterCommit]
    public function callback(): void {}
}

#[Transactional]
class IntersectionService
{
    public function run(LeftValue&RightValue $value): static
    {
        return $this;
    }
}

#[Transactional]
class EnumDefaultService
{
    public function run(DefaultEnum $value = DefaultEnum::VALUE): DefaultEnum
    {
        return $value;
    }
}

#[Transactional]
#[\BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\UnrelatedWideAttribute]
class UnrelatedTargetService
{
    #[\BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\UnrelatedWideAttribute]
    public function run(
        #[\BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\UnrelatedWideAttribute]
        string $value = 'default',
    ): string {
        return $value;
    }
}
