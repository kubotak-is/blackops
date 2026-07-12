<?php

declare(strict_types=1);

namespace BlackOps\Examples\Mvp;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Core\Supervision\RetryableException;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\Route;
use LogicException;
use RuntimeException;

final readonly class MvpOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ShowWelcome::class, GenerateReport::class];
    }
}

final readonly class MvpServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ShowWelcomeHandler::class);
        $services->autowire(GenerateReportHandler::class);
    }
}

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('welcome.show')]
#[Accepts(WelcomeValue::class)]
#[HandledBy(ShowWelcomeHandler::class)]
#[Returns(WelcomeShown::class)]
final readonly class ShowWelcome implements Operation {}

final readonly class WelcomeValue implements OperationValue
{
    public function __construct(
        #[FromHeader('X-Sample-Token')]
        #[Sensitive(SensitiveMode::Mask)]
        public string $sampleToken,
    ) {}
}

final readonly class WelcomeShown implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

/** @implements OperationHandler<WelcomeValue, WelcomeShown> */
final readonly class ShowWelcomeHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        if (!$operation->value() instanceof WelcomeValue) {
            throw new LogicException('Welcome handler requires WelcomeValue.');
        }

        return OperationResult::completed(new WelcomeShown('Welcome to BlackOps'));
    }
}

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[Accepts(GenerateReportValue::class)]
#[HandledBy(GenerateReportHandler::class)]
#[Returns(ReportGenerated::class)]
#[ExecuteWith(Deferred::class)]
final readonly class GenerateReport implements Operation {}

final readonly class GenerateReportValue implements OperationValue
{
    public function __construct(
        public string $reportName,
        #[Sensitive(SensitiveMode::Mask)]
        public string $apiToken,
    ) {}
}

final readonly class ReportGenerated implements Outcome
{
    public function __construct(
        public string $reportName,
        public string $location,
    ) {}
}

/** @implements OperationHandler<GenerateReportValue, ReportGenerated> */
final readonly class GenerateReportHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        $value = $operation->value();
        $attempt = $operation->context()->attempt();

        if (!$value instanceof GenerateReportValue || $attempt === null) {
            throw new LogicException('Report handler requires a deferred report attempt.');
        }

        if ($attempt->number() === 1) {
            throw new ReportGenerationTemporarilyUnavailable('Report backend is temporarily unavailable.');
        }

        return OperationResult::completed(
            new ReportGenerated($value->reportName, '/reports/generated/' . $value->reportName . '.json'),
        );
    }
}

final class ReportGenerationTemporarilyUnavailable extends RuntimeException implements RetryableException {}
