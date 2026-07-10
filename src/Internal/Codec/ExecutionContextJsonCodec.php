<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\ExecutionContext;

final readonly class ExecutionContextJsonCodec
{
    public function __construct(
        private JsonDocumentCodec $json = new JsonDocumentCodec(),
        private ExecutionContextNormalizer $normalizer = new ExecutionContextNormalizer(),
        private ExecutionContextHydrator $hydrator = new ExecutionContextHydrator(),
    ) {}

    public function encode(ExecutionContext $context): string
    {
        return $this->json->encode($this->normalizer->normalize($context));
    }

    public function decode(string $encodedContext): ExecutionContext
    {
        return $this->hydrator->hydrate($this->json->decodeObject($encodedContext));
    }
}
