<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use Closure;
use InvalidArgumentException;
use JsonException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class AuthGenerator
{
    public const int VERSION = 1;

    private const string MARKER = 'config/auth.php';

    /** @var list<string> */
    private const array FRAMEWORK_OWNED = [
        'app/AuthServiceProvider.php',
        'app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php',
        self::MARKER,
    ];

    /** @var array<string, array{stub: string, replacements?: array<string, string>}> */
    private const array TARGETS = [
        'app/Domain/Identity/User.php' => ['stub' => 'auth-user.php.stub'],
        'app/Domain/Identity/UserRepository.php' => ['stub' => 'auth-user-repository.php.stub'],
        'app/Domain/Identity/PasswordHasher.php' => ['stub' => 'auth-password-hasher.php.stub'],
        'app/Domain/Identity/RegistrationPolicy.php' => ['stub' => 'auth-registration-policy.php.stub'],
        'app/Domain/Identity/EnabledRegistrationPolicy.php' => ['stub' => 'auth-enabled-registration-policy.php.stub'],
        'app/Domain/Identity/DisabledRegistrationPolicy.php' => [
            'stub' => 'auth-disabled-registration-policy.php.stub',
        ],
        'app/Domain/Identity/IdentityIdentifier.php' => ['stub' => 'auth-identity-identifier.php.stub'],
        'app/Domain/Identity/IdentityService.php' => ['stub' => 'auth-identity-service.php.stub'],
        'app/Domain/Identity/Exception/DuplicateEmail.php' => ['stub' => 'auth-duplicate-email.php.stub'],
        'app/Domain/Identity/Exception/InvalidCredentials.php' => ['stub' => 'auth-invalid-credentials.php.stub'],
        'app/Domain/Identity/Exception/RegistrationDisabled.php' => ['stub' => 'auth-registration-disabled.php.stub'],
        'app/Infrastructure/Identity/DoctrineUserRepository.php' => [
            'stub' => 'auth-doctrine-user-repository.php.stub',
        ],
        'app/Infrastructure/Identity/RandomIdentityIdentifier.php' => [
            'stub' => 'auth-random-identity-identifier.php.stub',
        ],
        'app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php' => [
            'stub' => 'auth-session-identity-provider.php.stub',
        ],
        'app/Feature/Identity/Register/Register.php' => ['stub' => 'auth-register.php.stub'],
        'app/Feature/Identity/Register/RegisterValue.php' => ['stub' => 'auth-register-value.php.stub'],
        'app/Feature/Identity/Register/RegistrationCompleted.php' => [
            'stub' => 'auth-registration-completed.php.stub',
        ],
        'app/Feature/Identity/Login/Login.php' => ['stub' => 'auth-login.php.stub'],
        'app/Feature/Identity/Login/LoginValue.php' => ['stub' => 'auth-login-value.php.stub'],
        'app/Feature/Identity/Login/LoginCompleted.php' => ['stub' => 'auth-login-completed.php.stub'],
        'app/Feature/Identity/Logout/Logout.php' => ['stub' => 'auth-logout.php.stub'],
        'app/Feature/Identity/Logout/LogoutValue.php' => ['stub' => 'auth-logout-value.php.stub'],
        'app/Feature/Identity/Logout/LogoutCompleted.php' => ['stub' => 'auth-logout-completed.php.stub'],
        'app/AuthServiceProvider.php' => ['stub' => 'auth-service-provider.php.stub'],
        'config/auth.php' => ['stub' => 'auth-config.php.stub'],
        'migrations/Version20260722000000.php' => [
            'stub' => 'auth-user-migration.php.stub',
            'replacements' => ['{{ version }}' => 'Version20260722000000'],
        ],
        'migrations/Version20260722000100.php' => [
            'stub' => 'auth-session-migration.php.stub',
            'replacements' => ['{{ version }}' => 'Version20260722000100'],
        ],
    ];

    /** @var Closure(string): void */
    private Closure $beforeStubRead;

    /** @param null|Closure(string): void $beforeStubRead */
    public function __construct(
        private string $basePath,
        private string $stubDirectory,
        private ProjectFileWriter $writer = new ProjectFileWriter(),
        ?Closure $beforeStubRead = null,
    ) {
        $this->beforeStubRead = $beforeStubRead ?? static function (string $_path): void {};
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    public function generate(bool $force = false): AuthGenerationResult
    {
        $root = project_file_root($this->basePath);
        $this->assertComposerAutoload($root);
        $state = $this->state($root);

        if ($state['present'] === []) {
            $files = $this->renderAll();
            $this->writer->write($root, $files);

            return new AuthGenerationResult(array_keys($files), [], false);
        }

        if ($state['missing'] !== []) {
            throw new InvalidArgumentException(sprintf(
                'Authentication starter is incomplete; existing target: %s',
                $state['present'][0],
            ));
        }

        $version = $this->markerVersion($root);
        if ($version === self::VERSION && !$force) {
            return new AuthGenerationResult([], [], true);
        }

        if (!$force) {
            throw new InvalidArgumentException('Authentication starter version is not current; rerun with --force.');
        }

        if ($version === null || $version > self::VERSION) {
            throw new InvalidArgumentException('Authentication starter marker is invalid.');
        }

        $files = $this->render(self::FRAMEWORK_OWNED);
        $this->writer->replace($root, $files);

        return new AuthGenerationResult([], array_keys($files), false);
    }

    /** @return array{present: list<string>, missing: list<string>} */
    private function state(string $root): array
    {
        $present = [];
        $missing = [];
        foreach (array_keys(self::TARGETS) as $relative) {
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            project_assert_existing_ancestor_within_root($root, $target);
            if (is_link($target)) {
                throw new InvalidArgumentException(sprintf(
                    'Authentication starter target must not be a symlink: %s',
                    $relative,
                ));
            }
            if (file_exists($target)) {
                if (!is_file($target)) {
                    throw new InvalidArgumentException(sprintf(
                        'Authentication starter target must be a regular file: %s',
                        $relative,
                    ));
                }
                $present[] = $relative;

                continue;
            }

            $missing[] = $relative;
        }

        return ['present' => $present, 'missing' => $missing];
    }

    private function assertComposerAutoload(string $root): void
    {
        $path = $root . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Authentication starter requires a readable composer.json.');
        }

        $contents = $this->readStub($path);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Authentication starter requires a readable composer.json.');
        }

        try {
            $composer = $this->arrayData(json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new InvalidArgumentException('Authentication starter requires a valid composer.json.');
        }

        if (($composer['autoload']['psr-4']['App\\'] ?? null) !== 'app/') {
            throw new InvalidArgumentException('Authentication starter requires App\\ PSR-4 autoloading from app/.');
        }
    }

    private function markerVersion(string $root): ?int
    {
        $contents = $this->readStub($root . DIRECTORY_SEPARATOR . self::MARKER);
        $matches = [];
        if (!is_string($contents) || preg_match("/'generator_version'\\s*=>\\s*([0-9]+)/", $contents, $matches) !== 1) {
            return null;
        }

        $version = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return is_int($version) ? $version : null;
    }

    /** @return array<array-key, mixed> */
    private function arrayData(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Authentication starter requires a valid composer.json.');
        }

        return $data;
    }

    /** @return array<string, string> */
    private function renderAll(): array
    {
        return $this->render(array_keys(self::TARGETS));
    }

    /**
     * @param list<string> $targets
     * @return array<string, string>
     */
    private function render(array $targets): array
    {
        $files = [];
        foreach ($targets as $target) {
            $definition = self::TARGETS[$target];
            $path = rtrim($this->stubDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $definition['stub'];
            if (!is_file($path) || !is_readable($path)) {
                throw new InvalidArgumentException('Authentication generator stub is unavailable.');
            }

            ($this->beforeStubRead)($path);
            $template = $this->readStub($path);
            if (!is_string($template)) {
                throw new InvalidArgumentException('Authentication generator stub is unavailable.');
            }

            $rendered = strtr($template, $definition['replacements'] ?? []);
            if (str_contains($rendered, '{{')) {
                throw new InvalidArgumentException('Authentication generator stub is invalid.');
            }
            $files[$target] = $rendered;
        }

        return $files;
    }

    private function readStub(string $path): string|false
    {
        set_error_handler(static fn(int $_severity, string $_message, string $_file, int $_line): bool => true);
        try {
            return file_get_contents($path);
        } finally {
            restore_error_handler();
        }
    }
}
