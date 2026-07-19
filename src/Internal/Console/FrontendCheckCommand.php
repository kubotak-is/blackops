<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationBuildId;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationFrontendConfiguration;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Frontend\Generation\FrontendTreeChecker;
use BlackOps\Internal\Frontend\Generation\FrontendTreeCheckInspectionException;
use BlackOps\Internal\Frontend\Generation\FrontendTreeCheckState;
use BlackOps\Internal\Frontend\Generation\FrontendTypeScriptGenerator;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class FrontendCheckCommand extends Command
{
    public const NAME = 'frontend:check';

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
    ) {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $build = ApplicationBuildConfiguration::fromConfiguration($this->configuration->configuration());
            $expectedBuildId = ApplicationBuildId::fromConfiguration($this->configuration->configuration());
        } catch (InvalidArgumentException) {
            return $this->invalid($output, 'Frontend check failed: configuration is invalid.');
        }

        try {
            $artifact = new FrontendContractManifestFile()->loadArtifact($build->frontendManifest);
            if ($artifact->applicationBuildId !== $expectedBuildId) {
                throw new InvalidArgumentException('Frontend contract manifest build ID is stale.');
            }
        } catch (Throwable) {
            return $this->invalid($output, 'Frontend check failed: contract artifact is invalid.');
        }

        try {
            $frontend = ApplicationFrontendConfiguration::fromConfiguration(
                $this->configuration->basePath(),
                $this->configuration->configuration(),
            );
        } catch (InvalidArgumentException) {
            return $this->invalid($output, 'Frontend check failed: configuration is invalid.');
        }

        try {
            $tree = new FrontendTypeScriptGenerator()->generate($artifact);
        } catch (InvalidArgumentException) {
            return $this->invalid($output, 'Frontend check failed: generated contract is invalid.');
        }

        try {
            $state = new FrontendTreeChecker()->check($tree, $frontend->output);
        } catch (FrontendTreeCheckInspectionException) {
            return $this->invalid($output, 'Frontend check failed: generated tree could not be inspected.');
        }

        $relative = str_replace(search: DIRECTORY_SEPARATOR, replace: '/', subject: $frontend->relativeOutput);

        return match ($state) {
            FrontendTreeCheckState::Fresh => $this->result(
                $output,
                sprintf('Frontend generated tree is fresh in %s.', $relative),
                Command::SUCCESS,
            ),
            FrontendTreeCheckState::Missing => $this->result(
                $output,
                sprintf('Frontend generated tree is missing in %s.', $relative),
                Command::FAILURE,
            ),
            FrontendTreeCheckState::Drift => $this->result(
                $output,
                sprintf('Frontend generated tree has drift in %s.', $relative),
                Command::FAILURE,
            ),
        };
    }

    private function result(OutputInterface $output, string $message, int $exit): int
    {
        $output->writeln($message);

        return $exit;
    }

    private function invalid(OutputInterface $output, string $message): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->writeln($message);

        return Command::INVALID;
    }
}
