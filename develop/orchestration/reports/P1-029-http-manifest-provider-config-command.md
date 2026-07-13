# P1-029 Report: HTTP Manifest Provider Config Command

## Summary

Implemented and accepted the internal HTTP manifest compile command backed by operation provider config.

Build bootstrap code can now read an operation provider config file, compile the operation registry, instantiate no-argument operation definitions, and write the HTTP route manifest to a PHP file.

## Changed Files

- `src/Internal/Console/CompileHttpManifestCommand.php`
- `src/Internal/Registry/OperationDefinitionFactory.php`
- `tests/Internal/Console/CompileHttpManifestCommandTest.php`
- `tests/Internal/Registry/OperationDefinitionFactoryTest.php`
- `docs/internal/http-api-slice.md`
- `docs/internal/operation-registry.md`
- `develop/orchestration/tasks/P1-029-http-manifest-provider-config-command.md`
- `develop/orchestration/reports/P1-029-http-manifest-provider-config-command.md`
- `develop/STATE.md`

## Decisions and Assumptions

- The command remains internal because it coordinates internal provider config loading and registry compilation with the HTTP route compiler.
- Operation definition instances are created only for build-time attribute inspection.
- The first definition factory supports no-argument operation definitions only.
- Runtime container compile, operation manifest compile, Composer discovery, file scanning, token scanning, and production bootstrap remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OperationDefinitionFactoryTest|CompileHttpManifestCommandTest'
Result: OK (4 tests, 8 assertions).
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
Result: OK (239 tests, 552 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 357 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Command accepts operation provider config path and output path.
- [x] Command loads operation provider config and builds an operation registry.
- [x] Command can create operation definition instances.
- [x] HTTP route manifest can be written to a PHP file.
- [x] Dumped HTTP manifest file can rebuild an HTTP route registry.
- [x] Missing files and non-instantiable operation definitions are rejected.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to operation/container build orchestration, Composer-based provider discovery, or a unified production bootstrap command.
