<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\OperationDataRuntimeInjector;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\OperationData\OperationJournalQuery;
use BlackOps\OperationData\OperationOutcomeQuery;
use BlackOps\Outcome\OutcomeReader;
use BlackOps\StorageProtection\StorageKeyProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OperationDataRuntimeInjectorTest extends TestCase
{
    public function testInjectsAuthorizedQueriesWithoutRawReaderBindings(): void
    {
        $builder = new ContainerBuilder();
        $builder
            ->register(OperationJournalQuery::class, OperationJournalQuery::class)
            ->setSynthetic(true)
            ->setPublic(true);
        $builder
            ->register(OperationOutcomeQuery::class, OperationOutcomeQuery::class)
            ->setSynthetic(true)
            ->setPublic(true);
        $builder->register(BopdEnvelopeCodec::class, BopdEnvelopeCodec::class)->setSynthetic(true)->setPublic(true);
        $builder->compile();
        $container = $builder;
        $container->set(BopdEnvelopeCodec::class, new BopdEnvelopeCodec($this->createStub(StorageKeyProvider::class)));

        new OperationDataRuntimeInjector()->inject($container, $this->createStub(Connection::class), 'public');

        self::assertInstanceOf(OperationJournalQuery::class, $container->get(OperationJournalQuery::class));
        self::assertInstanceOf(OperationOutcomeQuery::class, $container->get(OperationOutcomeQuery::class));
        self::assertFalse($container->has(CanonicalJournalReader::class));
        self::assertFalse($container->has(OutcomeReader::class));
    }
}
