<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Http;

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use BlackOps\Internal\Http\OperationFailureErrorBoundary;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\FrameworkOperationFailureReporter;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

final class OperationFailureErrorBoundaryTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testReturnsSafeCorrelatedResponseAndReportsPrimaryAndSecondaryFailureTypes(): void
    {
        $primary = new \RuntimeException('primary credential detail');
        $secondary = new \LogicException('secondary credential detail');
        $failure = new OperationExecutionFailed(self::envelope(), 'boundary.test', $primary, false, $secondary);
        $backend = new ErrorBoundaryLogger();
        $scope = new ExecutionScopeProvider();
        $psr17 = new Psr17Factory();
        $boundary = new OperationFailureErrorBoundary(
            new ThrowingErrorBoundaryHandler($failure),
            new JsonOperationResponder($psr17, $psr17),
            new FrameworkOperationFailureReporter(new ExecutionScopedLogger($backend, $scope), $scope),
        );

        $response = $boundary->handle($psr17->createServerRequest('GET', '/failure'));
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"status":"error","code":"internal_error","operationId":"' . self::ID . '"}', $body);
        self::assertStringNotContainsString('credential', $body);
        self::assertCount(1, $backend->records);
        self::assertSame(self::ID, $backend->records[0]['context']['operation']['id']);
        self::assertSame(\RuntimeException::class, $backend->records[0]['context']['context']['failure']['type']);
        self::assertFalse($backend->records[0]['context']['context']['failure']['journalRecorded']);
        self::assertSame(
            'failure_recording_failed',
            $backend->records[0]['context']['context']['failure']['secondary']['classification'],
        );
        self::assertSame(
            \LogicException::class,
            $backend->records[0]['context']['context']['failure']['secondary']['type'],
        );
        self::assertStringNotContainsString('credential', serialize($backend->records));
        self::assertNull($scope->current());
    }

    public function testDoesNotConvertFailureWithoutEstablishedOperation(): void
    {
        $psr17 = new Psr17Factory();
        $scope = new ExecutionScopeProvider();
        $boundary = new OperationFailureErrorBoundary(
            new ThrowingErrorBoundaryHandler(new \RuntimeException('protocol failure')),
            new JsonOperationResponder($psr17, $psr17),
            new FrameworkOperationFailureReporter(new ExecutionScopedLogger(new ErrorBoundaryLogger(), $scope), $scope),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('protocol failure');

        $boundary->handle($psr17->createServerRequest('GET', '/failure'));
    }

    private static function envelope(): OperationEnvelope
    {
        return new OperationEnvelope(
            new ErrorBoundaryOperation(),
            new ErrorBoundaryValue(),
            new ExecutionContext(
                OperationId::fromString(self::ID),
                new DateTimeImmutable('2026-07-18T00:00:00Z'),
                CorrelationId::fromString(self::ID),
            ),
            new Inline(),
        );
    }
}

final readonly class ThrowingErrorBoundaryHandler implements RequestHandlerInterface
{
    public function __construct(
        private \Throwable $failure,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->failure;
    }
}

final class ErrorBoundaryLogger extends AbstractLogger
{
    /** @var list<array{message: string|Stringable, context: array<array-key, mixed>}> */
    public array $records = [];

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['message' => $message, 'context' => $context];
    }
}

final readonly class ErrorBoundaryOperation implements Operation {}

final readonly class ErrorBoundaryValue implements OperationValue {}
