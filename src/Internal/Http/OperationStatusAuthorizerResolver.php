<?php

declare(strict_types=1);

namespace BlackOps\Internal\Http;

use BlackOps\Status\DenyOperationStatusAuthorizer;
use BlackOps\Status\OperationStatusAuthorizer;
use LogicException;
use Psr\Container\ContainerInterface;
use Throwable;

final readonly class OperationStatusAuthorizerResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(): OperationStatusAuthorizer
    {
        if (!$this->container->has(OperationStatusAuthorizer::class)) {
            return new DenyOperationStatusAuthorizer();
        }

        try {
            /** @var mixed $authorizer */
            $authorizer = $this->container->get(OperationStatusAuthorizer::class);
        } catch (Throwable) {
            throw new LogicException('Operation status authorizer service could not be resolved.');
        }

        if (!$authorizer instanceof OperationStatusAuthorizer) {
            throw new LogicException('Operation status authorizer service has an invalid type.');
        }

        return $authorizer;
    }
}
