<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Operation;
use BlackOps\Internal\Discovery\ComposerAutoloadMetadataFile;
use BlackOps\Internal\Discovery\OperationSourceDiscovery;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final readonly class DevelopmentDiscoveryInput
{
    public function __construct(
        private ComposerAutoloadMetadataFile $metadata = new ComposerAutoloadMetadataFile(),
        private OperationSourceDiscovery $discovery = new OperationSourceDiscovery(),
    ) {}

    public function configure(Command $command): void
    {
        $command
            ->addOption(
                'discovery-root',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Application source root searched for operation definitions; repeat for multiple roots.',
            )
            ->addOption('composer-base', null, InputOption::VALUE_REQUIRED, 'Base directory for Composer paths.')
            ->addOption('composer-psr4', null, InputOption::VALUE_REQUIRED, 'Composer generated PSR-4 PHP file.')
            ->addOption(
                'composer-classmap',
                null,
                InputOption::VALUE_REQUIRED,
                'Composer generated classmap PHP file.',
            );
    }

    /**
     * @return list<class-string<Operation>>
     */
    public function optionalDefinitions(InputInterface $input): array
    {
        if (!$this->hasAnyOption($input)) {
            return [];
        }

        return $this->requiredDefinitions($input);
    }

    /**
     * @return list<class-string<Operation>>
     */
    public function requiredDefinitions(InputInterface $input): array
    {
        $roots = $this->roots($input->getOption('discovery-root'));

        $metadata = $this->metadata->load(
            $this->requiredStringOption($input, 'composer-base'),
            $this->requiredStringOption($input, 'composer-psr4'),
            $this->requiredStringOption($input, 'composer-classmap'),
        );

        return $this->discovery->discover($roots, $metadata);
    }

    private function hasAnyOption(InputInterface $input): bool
    {
        return (
            $input->getOption('discovery-root') !== []
            || $input->getOption('composer-base') !== null
            || $input->getOption('composer-psr4') !== null
            || $input->getOption('composer-classmap') !== null
        );
    }

    private function requiredStringOption(InputInterface $input, string $name): string
    {
        return $this->nonEmptyString($input->getOption($name));
    }

    private function nonEmptyString(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Development discovery option must be a non-empty string.');
        }

        return $value;
    }

    /**
     * @return non-empty-list<string>
     */
    private function roots(mixed $roots): array
    {
        if (!is_array($roots) || $roots === []) {
            throw new InvalidArgumentException('Development discovery requires at least one discovery root.');
        }

        return array_map($this->root(...), array_values($roots));
    }

    private function root(mixed $root): string
    {
        if (!is_string($root)) {
            throw new InvalidArgumentException('Development discovery root must be a string.');
        }

        return $root;
    }
}
