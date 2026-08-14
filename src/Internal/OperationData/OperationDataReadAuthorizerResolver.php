<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\OperationData\DenyOperationDataReadAuthorizer;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use LogicException;
use Psr\Container\ContainerInterface;
use Throwable;

final readonly class OperationDataReadAuthorizerResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(): OperationDataReadAuthorizer
    {
        if (!$this->container->has(OperationDataReadAuthorizer::class)) {
            return new DenyOperationDataReadAuthorizer();
        }
        try {
            /** @var mixed $authorizer */
            $authorizer = $this->container->get(OperationDataReadAuthorizer::class);
        } catch (Throwable) {
            throw new LogicException('Operation data read authorizer service could not be resolved.');
        }
        if (!$authorizer instanceof OperationDataReadAuthorizer) {
            throw new LogicException('Operation data read authorizer service has an invalid type.');
        }

        return $authorizer;
    }
}
