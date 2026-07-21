<?php

declare(strict_types=1);

namespace BlackOps\Application;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Application\ApplicationEnvironment;
use InvalidArgumentException;

#[PublicApi]
final readonly class Environment
{
    /** @var array<string, string> */
    private array $variables;

    /** @param array<array-key, mixed> $variables */
    public function __construct(array $variables)
    {
        $this->variables = new ApplicationEnvironment()->validate($variables);
    }

    public function string(string $name, ?string $default = null): string
    {
        $this->assertName($name);

        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name];
        }

        if ($default !== null) {
            return $default;
        }

        throw $this->missing($name, 'a string');
    }

    public function optionalString(string $name): ?string
    {
        $this->assertName($name);

        return $this->variables[$name] ?? null;
    }

    public function int(string $name, ?int $default = null): int
    {
        $this->assertName($name);

        if (!array_key_exists($name, $this->variables)) {
            if ($default !== null) {
                return $default;
            }

            throw $this->missing($name, 'a canonical decimal integer');
        }

        $value = $this->variables[$name];

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ([preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $value), is_int($integer)] !== [1, true]) {
            throw $this->invalid($name, 'a canonical decimal integer within the PHP integer range');
        }

        return (int) $integer;
    }

    public function positiveInt(string $name, ?int $default = null): int
    {
        $this->assertName($name);

        if (!array_key_exists($name, $this->variables)) {
            if ($default !== null) {
                if ($default < 1) {
                    throw new InvalidArgumentException(sprintf(
                        'Default for environment variable "%s" must be a positive integer.',
                        $name,
                    ));
                }

                return $default;
            }

            throw $this->missing($name, 'a positive canonical decimal integer');
        }

        $value = $this->variables[$name];

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ([preg_match('/\A[1-9][0-9]*\z/D', $value), is_int($integer)] !== [1, true]) {
            throw $this->invalid($name, 'a positive canonical decimal integer within the PHP integer range');
        }

        return (int) $integer;
    }

    public function bool(string $name, ?bool $default = null): bool
    {
        $this->assertName($name);

        if (!array_key_exists($name, $this->variables)) {
            if ($default !== null) {
                return $default;
            }

            throw $this->missing($name, 'a boolean (true, false, 1, or 0)');
        }

        return match (strtolower($this->variables[$name])) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw $this->invalid($name, 'a boolean (true, false, 1, or 0)'),
        };
    }

    private function assertName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Environment variable name must be a non-empty string.');
        }
    }

    private function missing(string $name, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Environment variable "%s" is required and must be %s.',
            $name,
            $expected,
        ));
    }

    private function invalid(string $name, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('Environment variable "%s" must be %s.', $name, $expected));
    }
}
