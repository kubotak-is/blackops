<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Audit\AuditOpaqueIdKeyProvider;
use BlackOps\Audit\AuditProtectionKeyProvider;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Key\ApplicationAuditProtectionKeySource;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditMutationTransactionCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\RandomNonceSource;
use BlackOps\StorageProtection\StorageKey;
use Psr\Container\ContainerInterface;

final readonly class ApplicationAuditRuntimeComposer
{
    public function compose(
        #[\SensitiveParameter]
        ApplicationConfigurationSnapshot $configuration,
        #[\SensitiveParameter]
        ApplicationOperationRuntimeComposition $operations,
        #[\SensitiveParameter]
        ImmutableAuditEventCatalogLookup $catalogs,
    ): ApplicationAuditRuntime {
        $audit = ApplicationAuditConfiguration::fromConfiguration($configuration->configuration());
        $database = $this->database($configuration);
        $this->assertConnection($database, $operations);
        [$opaqueKey, $protection] = $this->providers($operations->container);

        try {
            $opaqueIds = new HmacAuditOpaqueIdDeriver($opaqueKey->material());
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::KeyUnavailable);
        }

        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new \BlackOps\Internal\Audit\Record\AuditRecordValidator($catalogs);
        $records = new AuditRecordCodec($validator, $jcs);
        $finalizer = new AuditRecordFinalizer($validator, $records);
        $keySource = new ApplicationAuditProtectionKeySource($protection, $partitions, $opaqueKey);
        $binary = new BopdBinaryEnvelopeCodec();
        $nonces = new RandomNonceSource();
        $associatedData = new AuditAssociatedData();
        $ingress = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $body = new AuditProtectedBodyCodec($binary, $keySource, $nonces, $associatedData, $records, $ingress);
        $uuids = new SymfonyUuidv7Generator();
        $recordSchema = new PostgreSqlAuditSchema($database->schema);
        $recordStore = new PostgreSqlAuditRecordStore(
            $operations->connection,
            $recordSchema,
            $operations->clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $records,
            $body,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema($database->schema));
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $operations->connection,
            $outboxSchema,
            $operations->clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $ingress,
            $body,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(new PostgreSqlAuditProtectedIngressHydrator()),
            $audit->outbox(),
        );
        $selector = new AuditDeliverySelector($catalogs, $validator);
        $delivery = new AuditDeliveryCoordinator($operations->connection, $selector, $recordStore, $outboxStore);
        $mutations = new AuditMutationTransactionCoordinator($operations->transactions, $delivery);
        $protectedReads = new AuditProtectedReadGate($delivery);

        return ApplicationAuditRuntime::fromComposition(
            $audit->outbox(),
            $opaqueIds,
            $selector,
            $delivery,
            $mutations,
            $protectedReads,
            $recordStore,
            $outboxStore,
        );
    }

    private function database(
        #[\SensitiveParameter]
        ApplicationConfigurationSnapshot $configuration,
    ): ApplicationDatabaseConfiguration {
        try {
            return ApplicationDatabaseConfiguration::fromConfiguration($configuration->configuration());
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }
    }

    private function assertConnection(
        #[\SensitiveParameter]
        ApplicationDatabaseConfiguration $database,
        #[\SensitiveParameter]
        ApplicationOperationRuntimeComposition $operations,
    ): void {
        try {
            if ($operations->databases->connection($database->frameworkConnection) !== $operations->connection) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    /** @return array{0:StorageKey,1:AuditProtectionKeyProvider} */
    private function providers(#[\SensitiveParameter] ContainerInterface $container): array
    {
        try {
            if (
                !$container->has(AuditOpaqueIdKeyProvider::class) || !$container->has(AuditProtectionKeyProvider::class)
            ) {
                throw new \RuntimeException();
            }
            $opaqueProvider = $container->get(AuditOpaqueIdKeyProvider::class);
            $protection = $container->get(AuditProtectionKeyProvider::class);
            if (
                !$opaqueProvider instanceof AuditOpaqueIdKeyProvider
                || !$protection instanceof AuditProtectionKeyProvider
            ) {
                throw new \RuntimeException();
            }
            $opaqueKey = $opaqueProvider->key();
            if (!$opaqueKey instanceof StorageKey) {
                throw new \RuntimeException();
            }

            return [$opaqueKey, $protection];
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::KeyUnavailable);
        }
    }
}
