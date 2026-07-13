# P1-034 Report: Composer Provider Build Integration

## Summary

Implemented and accepted Composer provider integration for the internal build artifacts command.

The build command can now accept a Composer metadata file, merge discovered operation providers into operation and HTTP manifest generation, merge discovered service providers into runtime container compilation, and include the Composer metadata file in build fingerprint inputs.

## Changed Files

- `src/Internal/Build/BuildArtifactProviderLoader.php`
- `src/Internal/Build/BuildArtifactProviders.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `src/Internal/DependencyInjection/ServiceProviderConfigLoader.php`
- `src/Internal/Registry/OperationProviderConfigLoader.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `docs/internal/operation-registry.md`
- `docs/internal/runtime-container.md`
- `develop/orchestration/tasks/P1-034-composer-provider-build-integration.md`
- `develop/orchestration/reports/P1-034-composer-provider-build-integration.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Composer metadata integration is scoped to the unified build artifacts command.
- Operation manifest and runtime container single-purpose commands remain explicit-config only for this slice.
- Composer provider discovery still returns class names only.
- Provider instantiation is handled by the existing provider config loaders through a shared entry conversion boundary.
- The Composer metadata file participates in the lightweight build fingerprint when the option is provided.
- `vendor/composer/installed.json` traversal, Composer plugin behavior, PSR-4/classmap operation scanning, token scanning, and production bootstrap remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter CompileBuildArtifactsCommandTest
Result: OK (5 tests, 14 assertions).
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
Result: OK (260 tests, 586 assertions). Runtime PHP 8.5.7.
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 392 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Build Artifacts compile command can accept a Composer metadata file.
- [x] Composer metadata operation providers are reflected in the operation manifest.
- [x] Composer metadata service providers are reflected in the runtime container.
- [x] Explicit provider config and Composer metadata providers can be used in the same build.
- [x] Composer metadata file participates in the build fingerprint input set.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to installed package provider discovery, production bootstrap wrapper, or command registration/bootstrap documentation.
