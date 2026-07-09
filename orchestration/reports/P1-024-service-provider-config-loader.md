# P1-024 Report: Service Provider Config Loader

## Summary

Implemented and accepted the internal service provider config loader.

Runtime bootstrap code can now load service providers from a PHP config file and pass them to the existing runtime container compiler. The loader accepts a single provider instance, a list of provider instances, and provider class names that can be instantiated without constructor arguments.

## Changed Files

- `src/Internal/DependencyInjection/ServiceProviderConfigLoader.php`
- `tests/Internal/DependencyInjection/ServiceProviderConfigLoaderTest.php`
- `docs/internals/runtime-container.md`
- `orchestration/tasks/P1-024-service-provider-config-loader.md`
- `orchestration/reports/P1-024-service-provider-config-loader.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Config loading remains an internal bootstrap concern; no new public API was added.
- The first config format is PHP-only and returns provider instances or provider class names.
- Provider class names must implement the public service provider contract and be instantiable without required constructor arguments.
- Automatic Composer discovery, YAML/JSON config, service tags, factories, aliases, parameters, scalar binding DSL, and production bootstrap command wiring remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter ServiceProviderConfigLoaderTest
Result: OK (8 tests, 11 assertions).
```

```text
docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.
```

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.
```

```text
docker compose run --rm app mago lint
Result: INFO No issues found.
```

```text
docker compose run --rm app mago analyze
Result: INFO No issues found.
```

```text
docker compose run --rm app vendor/bin/phpunit
Result: OK (215 tests, 514 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 296 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] PHP config file can load `ServiceProvider` instances.
- [x] PHP config file can load no-argument `ServiceProvider` class names.
- [x] A single provider return value is supported.
- [x] Loaded providers can be applied through the runtime container compiler.
- [x] Missing files, invalid return values, invalid entries, and non-instantiable providers are rejected.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to a production container compile command, provider discovery, or a richer service registry DSL depending on the next runtime integration need.
