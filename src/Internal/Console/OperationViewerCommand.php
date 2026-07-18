<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Application\ApplicationDiagnosticsViewerConfiguration;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerNativeServer;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRouter;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerServerException;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerTokens;
use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class OperationViewerCommand extends Command
{
    public const NAME = 'operation:viewer';

    /** @var Closure(): ApplicationDiagnosticsViewerConfiguration */
    private Closure $configuration;

    /** @var Closure(ApplicationDiagnosticsViewerConfiguration, OperationViewerTokens): OperationViewerRouter */
    private Closure $router;

    /** @var Closure(ApplicationDiagnosticsViewerConfiguration, OperationViewerRouter, Closure(): void): void */
    private Closure $serve;

    /**
     * @param Closure(): ApplicationDiagnosticsViewerConfiguration $configuration
     * @param Closure(ApplicationDiagnosticsViewerConfiguration, OperationViewerTokens): OperationViewerRouter $router
     * @param null|Closure(ApplicationDiagnosticsViewerConfiguration, OperationViewerRouter, Closure(): void): void $serve
     */
    public function __construct(Closure $configuration, Closure $router, ?Closure $serve = null)
    {
        parent::__construct(self::NAME);
        $this->setDescription('Start the read-only local operation diagnostics viewer.');
        $this->configuration = $configuration;
        $this->router = $router;
        $this->serve = $serve ?? static function (
            ApplicationDiagnosticsViewerConfiguration $config,
            OperationViewerRouter $viewer,
            Closure $started,
        ): void {
            new OperationViewerNativeServer($config)->serve($viewer, $started);
        };
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configuration = ($this->configuration)();
        } catch (Throwable) {
            return $this->fail($output, 'viewer.invalid_configuration');
        }
        if (!$configuration->enabled) {
            return $this->fail($output, 'viewer.disabled');
        }

        try {
            $tokens = OperationViewerTokens::generate();
            $router = ($this->router)($configuration, $tokens);
            ($this->serve)($configuration, $router, static function () use ($output, $tokens, $configuration): void {
                $output->write($tokens->bootstrapUrl($configuration->authority()) . "\n");
            });
        } catch (OperationViewerServerException $exception) {
            return $this->fail($output, $exception->safeCode);
        } catch (Throwable) {
            return $this->fail($output, 'viewer.start_failed');
        }

        return Command::SUCCESS;
    }

    private function fail(OutputInterface $output, string $code): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write($code . "\n");

        return Command::FAILURE;
    }
}
