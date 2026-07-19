<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationBuildId;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationFrontendConfiguration;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Frontend\Generation\FrontendOutputWriter;
use BlackOps\Internal\Frontend\Generation\FrontendTypeScriptGenerator;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FrontendGenerateCommand extends Command
{
    public const NAME = 'frontend:generate';

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
    ) {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $build = ApplicationBuildConfiguration::fromConfiguration($this->configuration->configuration());
        $expectedBuildId = ApplicationBuildId::fromConfiguration($this->configuration->configuration());
        $artifact = new FrontendContractManifestFile()->loadArtifact($build->frontendManifest);
        if ($artifact->applicationBuildId !== $expectedBuildId) {
            throw new InvalidArgumentException(
                'Frontend contract manifest application build ID does not match configuration.',
            );
        }

        $frontend = ApplicationFrontendConfiguration::fromConfiguration(
            $this->configuration->basePath(),
            $this->configuration->configuration(),
        );
        $tree = new FrontendTypeScriptGenerator()->generate($artifact);
        $count = new FrontendOutputWriter()->write($tree, $frontend->output);

        $output->writeln(sprintf(
            'Generated %d frontend files in %s.',
            $count,
            str_replace(search: DIRECTORY_SEPARATOR, replace: '/', subject: $frontend->relativeOutput),
        ));

        return Command::SUCCESS;
    }
}
