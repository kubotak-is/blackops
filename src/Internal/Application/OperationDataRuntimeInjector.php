<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\OperationData\DefaultOperationJournalQuery;
use BlackOps\Internal\OperationData\DefaultOperationOutcomeQuery;
use BlackOps\Internal\OperationData\OperationDataReadAuthorizerResolver;
use BlackOps\Internal\OperationData\PostgreSqlOperationDataSubjectReader;
use BlackOps\Internal\OperationData\PostgreSqlTenantScopedCanonicalJournalReader;
use BlackOps\Internal\OperationData\PostgreSqlTenantScopedOutcomeReader;
use BlackOps\OperationData\OperationJournalQuery;
use BlackOps\OperationData\OperationOutcomeQuery;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReader;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;

final readonly class OperationDataRuntimeInjector
{
    public function inject(ContainerInterface $container, Connection $connection, string $schema): void
    {
        if (!$container instanceof Container) {
            throw new InvalidArgumentException('Runtime container does not support operation data injection.');
        }

        $subjectReader = new PostgreSqlOperationDataSubjectReader(new PostgreSqlStatusReader($connection, $schema));
        $authorizer = new OperationDataReadAuthorizerResolver($container)->resolve();
        $protection = ApplicationStorageProtectionResolver::resolve($container);
        $container->set(
            OperationJournalQuery::class,
            new DefaultOperationJournalQuery(
                $subjectReader,
                $authorizer,
                new PostgreSqlTenantScopedCanonicalJournalReader($connection, $protection, $schema),
            ),
        );
        $container->set(
            OperationOutcomeQuery::class,
            new DefaultOperationOutcomeQuery(
                $subjectReader,
                $authorizer,
                new PostgreSqlTenantScopedOutcomeReader($connection, $protection, $schema),
            ),
        );
    }
}
