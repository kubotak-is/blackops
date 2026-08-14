<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

use InvalidArgumentException;

final readonly class FrameworkProxyDiagnostic
{
    public function __construct(
        public string $code,
        public ?string $serviceId = null,
        public ?string $sourceClass = null,
        public ?string $method = null,
        public ?string $attribute = null,
        public ?string $buildId = null,
        public ?string $sourcePath = null,
    ) {
        if ($code === '' || preg_match('/^BO_PROXY_[A-Z0-9_]+$/', $code) !== 1) {
            throw new InvalidArgumentException('Framework proxy diagnostic code is invalid.');
        }
    }

    /** @return array{code:string,service_id?:string,source_class?:string,method?:string,attribute?:string,build_id?:string,source_path?:string} */
    public function toArray(): array
    {
        $fields = ['code' => $this->code];

        foreach ([
            'service_id' => $this->serviceId,
            'source_class' => $this->sourceClass,
            'method' => $this->method,
            'attribute' => $this->attribute,
            'build_id' => $this->buildId,
            'source_path' => $this->sourcePath,
        ] as $key => $value) {
            if ($value === null) {
                continue;
            }

            $fields[$key] = $value;
        }

        return $fields;
    }
}
