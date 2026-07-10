<?php

declare(strict_types=1);

namespace BlackOps\Internal\ArchitectureFixture {
    use BlackOps\Core\Attribute\PublicApi;

    class InternalParent {}

    interface InternalContract {}

    final class InternalDependency {}

    interface InternalLeft {}

    interface InternalRight {}

    #[PublicApi]
    final class InvalidInternalPublicApi {}
}

namespace BlackOps\Tests\Architecture\Fixture {
    use BlackOps\Core\Attribute\PublicApi;
    use BlackOps\Internal\ArchitectureFixture\InternalContract;
    use BlackOps\Internal\ArchitectureFixture\InternalDependency;
    use BlackOps\Internal\ArchitectureFixture\InternalLeft;
    use BlackOps\Internal\ArchitectureFixture\InternalParent;
    use BlackOps\Internal\ArchitectureFixture\InternalRight;

    #[PublicApi]
    final class ValidPublicApi
    {
        public function __construct(
            public string $name,
        ) {}

        public function resolve(?self $alternative): self
        {
            return $alternative ?? $this;
        }

        public function scalarUnion(string|int $value): string|int
        {
            return $value;
        }
    }

    #[PublicApi]
    final class PublicApiWithInternalSignatures extends InternalParent implements InternalContract
    {
        public InternalDependency $dependency;

        public function __construct(?InternalDependency $dependency) {}

        public function union(InternalDependency|string $dependency): InternalDependency|string
        {
            return $dependency;
        }

        public function intersection(InternalLeft&InternalRight $dependency): InternalLeft&InternalRight
        {
            return $dependency;
        }
    }
}
