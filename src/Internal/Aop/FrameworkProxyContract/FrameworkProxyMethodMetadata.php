<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

final readonly class FrameworkProxyMethodMetadata
{
    /** @param list<array{name:string,type:?string,reference:bool,variadic:bool,hasDefault:bool,default:mixed,defaultConstantName:?string}> $parameters @param list<string> $unrelatedAttributes */
    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        public string $name,
        public string $declaringClass,
        public ?string $transactionalConnection,
        public bool $transactional,
        public bool $afterCommit,
        public FrameworkProxySignatureClassification $classification,
        public ?string $diagnosticCode,
        public string $signature,
        public array $parameters,
        public ?string $returnType,
        /** @var list<string> */
        public array $unrelatedAttributes = [],
    ) {}
}
