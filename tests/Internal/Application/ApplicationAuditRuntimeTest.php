<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationAuditRuntime;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditMutationTransactionCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class ApplicationAuditRuntimeTest extends TestCase
{
    public function testRuntimeCarriesExactlyTheComposedGraphByIdentity(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $opaqueIds = new HmacAuditOpaqueIdDeriver(str_repeat('k', 32));
        $lookup = new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()]);
        $validator = new AuditRecordValidator($lookup);
        $selector = new AuditDeliverySelector($lookup, $validator);
        $connection = $this->createStub(Connection::class);
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();
        $delivery = new AuditDeliveryCoordinator($connection, $selector, $records, $outbox);
        $transactions = new ReflectionClass(OperationTransactionCoordinator::class)->newInstanceWithoutConstructor();
        $mutations = new AuditMutationTransactionCoordinator($transactions, $delivery);
        $protectedReads = new AuditProtectedReadGate($delivery);

        $runtime = ApplicationAuditRuntime::fromComposition(
            $configuration,
            $opaqueIds,
            $selector,
            $delivery,
            $mutations,
            $protectedReads,
            $records,
            $outbox,
        );

        self::assertSame($configuration, $runtime->outboxConfiguration());
        self::assertSame($opaqueIds, $runtime->opaqueIds());
        self::assertSame($selector, $runtime->deliverySelector());
        self::assertSame($delivery, $runtime->delivery());
        self::assertSame($mutations, $runtime->mutations());
        self::assertSame($protectedReads, $runtime->protectedReads());
        self::assertSame($records, $runtime->records());
        self::assertSame($outbox, $runtime->outbox());
    }

    public function testRuntimeIsFinalReadonlyAndExposesOnlyTheEightGettersAndFactory(): void
    {
        $reflection = new ReflectionClass(ApplicationAuditRuntime::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->getConstructor()?->isPrivate());
        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === ApplicationAuditRuntime::class) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);
        self::assertSame(
            [
                'delivery',
                'deliverySelector',
                'fromComposition',
                'mutations',
                'opaqueIds',
                'outbox',
                'outboxConfiguration',
                'protectedReads',
                'records',
            ],
            $methods,
        );
        self::assertSame(8, count($reflection->getProperties()));
        $parameters = $reflection->getMethod('fromComposition')->getParameters();
        self::assertCount(8, $parameters);
        self::assertSame(
            array_fill(0, 8, 1),
            array_map(
                static fn(\ReflectionParameter $parameter): int => count($parameter->getAttributes(\SensitiveParameter::class)),
                $parameters,
            ),
        );
    }
}
