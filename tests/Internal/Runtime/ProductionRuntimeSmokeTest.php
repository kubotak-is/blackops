<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Http\Attribute\Route;
use BlackOps\Internal\Console\CompileBuildArtifactsCommand;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ProductionRuntimeSmokeTest extends TestCase
{
    public function testBuildArtifactsLoadAndHandleHttpRequestInProductionRuntime(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $containerClass = 'SmokeContainer' . bin2hex(random_bytes(8));
        $containerNamespace = __NAMESPACE__ . '\\Generated';
        file_put_contents($operationProviders, '<?php return [\\' . SmokeOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . SmokeServiceProvider::class . '::class];');

        $status = new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'container' => $containerPath,
            '--container-class' => $containerClass,
            '--container-namespace' => $containerNamespace,
        ]);
        $artifacts = new ProductionRuntimeArtifactLoader()->load(
            $operationManifest,
            $httpManifest,
            $containerPath,
            $containerClass,
            $containerNamespace,
        );
        $journal = new SmokeJournalWriter();
        $psr17 = new Psr17Factory();
        $runtime = new ProductionRuntimeComposer()->compose($artifacts, new SmokeClock(), $journal, $psr17, $psr17);

        $response = $runtime->httpHandler->handle($psr17->createServerRequest('GET', '/smoke'));

        self::assertSame(0, $status);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"message":"Phase 1 smoke ready"}', (string) $response->getBody());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
    }

    private function path(string $name): string
    {
        return (
            sys_get_temp_dir() . '/blackops-production-runtime-smoke-' . $name . '-' . bin2hex(random_bytes(8)) . '.php'
        );
    }
}

final readonly class SmokeClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:00:00+00:00');
    }
}

final class SmokeJournalWriter implements CanonicalJournalWriter
{
    /** @var list<JournalRecord> */
    public array $records = [];

    public function append(JournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final readonly class SmokeOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [SmokeOperation::class];
    }
}

final readonly class SmokeServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(SmokeHandler::class);
    }
}

#[Route('GET', '/smoke')]
#[OperationType('runtime.smoke')]
#[Accepts(SmokeValue::class)]
#[HandledBy(SmokeHandler::class)]
#[Returns(SmokeOutcome::class)]
final readonly class SmokeOperation implements Operation {}

final readonly class SmokeValue implements OperationValue {}

final readonly class SmokeOutcome implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class SmokeHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new SmokeOutcome('Phase 1 smoke ready'));
    }
}
