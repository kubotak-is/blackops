<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract;

use Attribute;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;

#[Attribute(Attribute::TARGET_METHOD)]
final class UnrelatedAttribute {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final class UnrelatedWideAttribute {}

enum DefaultEnum
{
    case VALUE;
}

#[Transactional(connection: 'app')]
class PrecedenceService
{
    public function inherited(string|int $value = 1, ...$rest): string|int
    {
        return $value;
    }

    #[Transactional(connection: 'analytics')]
    public function override(): void {}

    #[Transactional]
    public function bareOverride(): void {}

    #[UnrelatedAttribute]
    #[Transactional]
    public function unrelated(): void {}

    #[AfterCommit]
    public function callback(): void {}
}

readonly class ReadonlyService
{
    public function __construct(
        public readonly string $name = 'service',
    ) {}

    #[Transactional]
    public function run(?string $value = null): static
    {
        return $this;
    }
}

class OperationService implements Operation
{
    #[Transactional]
    public function handle(): void {}
}

#[Transactional]
final class FinalService {}

#[Transactional]
class FinalMethodService
{
    final public function run(): void {}
}

class VisibilityService
{
    #[Transactional]
    protected function run(): void {}
}

class StaticService
{
    #[Transactional]
    public static function run(): void {}
}

class GeneratorService
{
    #[Transactional]
    public function run(): iterable
    {
        yield 'value';
    }
}

class ReferenceReturnService
{
    private string $value = 'value';

    #[Transactional]
    public function &run(): string
    {
        return $this->value;
    }
}

class ReferenceParameterService
{
    #[Transactional]
    public function run(string &$value): void {}
}

class ConflictService
{
    #[Transactional]
    #[AfterCommit]
    public function run(): void {}
}

class DuplicateAttributeService
{
    #[Transactional]
    #[Transactional]
    public function run(): void {}
}

class DuplicateAfterCommitAttributeService
{
    #[AfterCommit]
    #[AfterCommit]
    public function run(): void {}
}

class AfterCommitReturnService
{
    #[AfterCommit]
    public function run(): string
    {
        return 'value';
    }
}

class AfterCommitGeneratorService
{
    #[AfterCommit]
    public function run(): iterable
    {
        yield 'value';
    }
}

class AfterCommitReferenceReturnService
{
    private string $value = 'value';

    #[AfterCommit]
    public function &run(): string
    {
        return $this->value;
    }
}

class AfterCommitReferenceParameterService
{
    #[AfterCommit]
    public function run(string &$value): void {}
}

class ConstructorTargetService
{
    #[Transactional]
    public function __construct() {}
}

class DestructorTargetService
{
    #[AfterCommit]
    public function __destruct() {}
}

class PrivateAfterCommitService
{
    #[AfterCommit]
    private function run(): void {}
}

#[AfterCommit]
class ClassAfterCommitService {}

class PropertyTargetService
{
    #[Transactional]
    public string $value = '';
}

class ParameterTargetService
{
    public function run(#[Transactional] string $value): void {}
}

abstract class AbstractService
{
    #[Transactional]
    abstract public function run(): void;
}

interface ContractInterface
{
    #[Transactional]
    public function run(): void;
}

trait ContractTrait
{
    #[Transactional]
    public function run(): void {}
}

interface LeftValue {}

interface RightValue {}

interface FirstValue {}

interface SecondValue {}

class ComplexTypeService
{
    #[Transactional]
    public function complex(
        LeftValue|RightValue $union,
        (FirstValue&SecondValue)|null $dnf = null,
        mixed $mixed = null,
    ): static {
        return $this;
    }

    #[Transactional]
    public function neverReturns(): never
    {
        throw new \RuntimeException('never');
    }

    #[Transactional]
    public function defaults(int $scalar = 3, array $array = ['value'], int $constant = \PHP_INT_SIZE): self
    {
        return $this;
    }
}

class RootTypeService {}

class ParentTypeService extends RootTypeService
{
    #[Transactional]
    public function parentType(): parent
    {
        return $this;
    }
}

class InheritedTypeService extends ParentTypeService
{
    #[Transactional]
    public function inherited(): self
    {
        return $this;
    }
}

class NoDefaultService
{
    #[Transactional]
    public function run(string $required, ?string $nullable = null): void {}
}

class ObjectDefaultService
{
    #[Transactional]
    public function run(\DateTimeImmutable $value = new \DateTimeImmutable()): void {}
}

class InaccessibleDefaultService
{
    private const DEFAULT = 'private';

    #[Transactional]
    public function run(string $value = self::DEFAULT): void {}
}

class PrivateDefaultBaseService
{
    private const DEFAULT = 'private';

    #[Transactional]
    public function run(string $value = self::DEFAULT): void {}
}

class PrivateDefaultChildService extends PrivateDefaultBaseService {}

class PublicDefaultOwnerService
{
    public const DEFAULT = 'public';
}

class CollisionDefaultService
{
    private const DEFAULT = 'private';

    #[Transactional]
    public function run(string $value = PublicDefaultOwnerService::DEFAULT): void {}
}

class EnumDefaultService
{
    #[Transactional]
    public function run(DefaultEnum $value = DefaultEnum::VALUE): void {}
}

#[UnrelatedWideAttribute]
class UnrelatedTargetService
{
    #[Transactional]
    #[UnrelatedWideAttribute]
    public function run(#[UnrelatedWideAttribute] string $value): void {}
}
