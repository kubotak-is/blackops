<?php

declare(strict_types=1);

namespace BlackOps\Internal\Authorization;

use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Registry\OperationMetadata;
use LogicException;
use Psr\Container\ContainerInterface;

final readonly class AuthorizationPolicyResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(OperationMetadata $metadata): ?AuthorizationPolicy
    {
        $id = $metadata->authorizationPolicy;

        if ($id === null) {
            return null;
        }

        if (!$this->container->has($id)) {
            throw new LogicException('Operation authorization policy service is unavailable.');
        }

        /** @var mixed $policy */
        $policy = $this->container->get($id);

        if (!$policy instanceof AuthorizationPolicy) {
            throw new LogicException('Operation authorization policy service has an invalid type.');
        }

        return $policy;
    }
}
