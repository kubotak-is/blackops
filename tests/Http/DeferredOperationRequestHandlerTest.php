<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Execution\Dispatcher;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpOperationRoute;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class DeferredOperationRequestHandlerTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_008';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687711';

    private Psr17Factory $psr17;
    private Connection $connection;
    private PostgreSqlCanonicalJournalStore $journal;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
    }

    public function testDeferredRouteReturnsAcceptedResponseAndPersistsStateAndJournal(): void
    {
        $handler = $this->handler(new ReflectionJsonOperationCodec());
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withBody($this->psr17->createStream('{"reportName":"weekly"}'));

        $response = $handler->handle($request);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"status":"accepted","operationId":"'
            . self::OPERATION_ID
            . '","acceptedAt":"2026-07-10T00:00:01.123456Z"}',
            (string) $response->getBody(),
        );

        $operationRow = $this->operationRow();
        $records = $this->records();

        self::assertSame(self::OPERATION_ID, $operationRow['operation_id']);
        self::assertSame('report.generate', $operationRow['operation_type']);
        self::assertSame('accepted', $operationRow['state']);
        self::assertSame(3, (int) $operationRow['next_sequence']);
        self::assertSame('{"reportName":"weekly"}', $operationRow['payload']);
        self::assertStringContainsString('"operation_id":"' . self::OPERATION_ID . '"', $operationRow['context']);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2], array_column($records, 'sequence'));
        self::assertInstanceOf(OperationReceivedData::class, $records[0]->data);
        self::assertInstanceOf(ReportRequestValue::class, $records[0]->data->value);
        self::assertSame('weekly', $records[0]->data->value->reportName);
        self::assertInstanceOf(EmptyJournalData::class, $records[1]->data);
    }

    public function testDeferredRouteDoesNotCallInlineDispatcher(): void
    {
        $handler = $this->handler(new ReflectionJsonOperationCodec());
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withBody($this->psr17->createStream('{"reportName":"daily"}'));

        $response = $handler->handle($request);

        self::assertSame(202, $response->getStatusCode());
    }

    private function handler(OperationCodec $codec): OperationRequestHandler
    {
        $clock = new DeferredHttpClock();
        $identifiers = new IdentifierFactory(new DeferredHttpUuidv7Generator(), $clock);
        $sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.123456Z'),
        );
        $sender->migrate();
        $this->journal->migrate();
        $registry = new OperationRegistry([$this->metadata()]);
        $orchestrator = new DeferredAcceptanceOrchestrator(
            $this->connection,
            $sender,
            $this->journal,
            new JournalRecordFactory($identifiers, $clock),
        );

        return new OperationRequestHandler(
            new HttpRouteRegistry([new HttpOperationRoute(
                'POST',
                '/reports',
                new GenerateReport(),
                ReportRequestValue::class,
            )]),
            new OperationValueBinder(),
            new DeferredHttpFailingDispatcher(),
            new JsonOperationResponder($this->psr17, $this->psr17),
            $this->psr17,
            new DeferredHttpOperationAcceptor(
                $registry,
                new ExecutionContextFactory($identifiers, $clock),
                $codec,
                $orchestrator,
            ),
        );
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            GenerateReport::class,
            ReportRequestValue::class,
            GenerateReportHandler::class,
            EmptyOutcome::class,
            Deferred::class,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationRow(): array
    {
        $row = $this->connection->fetchAssociative('SELECT operation_id::text AS operation_id,
                operation_type,
                state,
                next_sequence,
                convert_from(encoded_payload, \'UTF8\') AS payload,
                convert_from(encoded_context, \'UTF8\') AS context
            FROM ' . self::SCHEMA . '.operations');

        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return list<JournalRecord>
     */
    private function records(): array
    {
        return array_values(iterator_to_array($this->journal->records(OperationId::fromString(self::OPERATION_ID))));
    }

    private function connection(): Connection
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $port = (int) (getenv('POSTGRES_PORT') ?: '5432');
        $db = (string) (getenv('POSTGRES_DB') ?: 'blackops');
        $user = (string) (getenv('POSTGRES_USER') ?: 'blackops');
        $password = (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops');

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $host,
            'port' => $port,
            'dbname' => $db,
            'user' => $user,
            'password' => $password,
        ]);
    }
}

#[Route(method: 'POST', path: '/reports')]
final readonly class GenerateReport implements Operation {}

final readonly class ReportRequestValue implements OperationValue
{
    public function __construct(
        public string $reportName,
    ) {}
}

abstract class GenerateReportHandler implements OperationHandler {}

final readonly class DeferredHttpFailingDispatcher implements Dispatcher
{
    public function dispatch(Operation $definition, OperationValue $value): OperationResult
    {
        self::fail('Inline dispatcher should not be called for a deferred route.');
    }
}

final readonly class DeferredHttpClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:00:00.000000Z');
    }
}

final class DeferredHttpUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687711',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687712',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687713',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687714',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687715',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687716',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
