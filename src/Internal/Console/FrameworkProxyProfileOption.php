<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/** The one build-time switch for selecting the proxy owner. */
final class FrameworkProxyProfileOption
{
    public const NAME = 'proxy-profile';

    public static function configure(Command $command): void
    {
        $command->addOption(
            self::NAME,
            null,
            InputOption::VALUE_REQUIRED,
            'Proxy owner for generated artifacts (ray or framework).',
            FrameworkProxyProfile::RAY,
        );
    }

    public static function fromInput(InputInterface $input): FrameworkProxyProfile
    {
        $value = (string) $input->getOption(self::NAME);
        if ($value === '') {
            throw new InvalidArgumentException('The --proxy-profile option must be ray or framework.');
        }

        try {
            return FrameworkProxyProfile::from($value);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('The --proxy-profile option must be ray or framework.');
        }
    }
}
