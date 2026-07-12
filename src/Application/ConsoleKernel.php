<?php

declare(strict_types=1);

namespace BlackOps\Application;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationConsoleKernel;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[PublicApi]
final readonly class ConsoleKernel
{
    private ApplicationConsoleKernel $kernel;

    private function __construct(ApplicationConfigurationSnapshot $configuration)
    {
        $this->kernel = new ApplicationConsoleKernel($configuration);
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        try {
            return $this->kernel->run($input, $output);
        } catch (InvalidArgumentException $exception) {
            throw new ApplicationBootstrapException($exception->getMessage());
        } catch (Throwable $exception) {
            throw new ApplicationBootstrapException('Application console command failed.');
        }
    }
}
