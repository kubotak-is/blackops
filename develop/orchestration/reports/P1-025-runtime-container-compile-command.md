# P1-025 Report: Runtime Container Compile Command

## Summary

Implemented and accepted the internal runtime container compile command.

The command reads a service provider config file, applies loaded providers to a fresh runtime container builder, compiles the container, and dumps a production-style PHP container file.

## Changed Files

- `src/Internal/Console/CompileRuntimeContainerCommand.php`
- `tests/Internal/Console/CompileRuntimeContainerCommandTest.php`
- `docs/internal/runtime-container.md`
- `develop/orchestration/tasks/P1-025-runtime-container-compile-command.md`
- `develop/orchestration/reports/P1-025-runtime-container-compile-command.md`
- `develop/STATE.md`

## Decisions and Assumptions

- The command is internal because it wires internal loader, compiler, and dumper components together.
- The command accepts a provider config path and output path as required arguments.
- Generated container class name and namespace are command options.
- Command registration in an application console remains a bootstrap concern.
- Composer discovery, manifest/container one-shot build, cache invalidation, multi-file dump, preload tuning, and richer service registry DSL remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter CompileRuntimeContainerCommandTest
Result: OK (2 tests, 5 assertions).
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
Result: OK (217 tests, 519 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 307 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Command accepts provider config path and output path.
- [x] Command can receive the generated container class name and namespace.
- [x] Command loads provider config and applies providers to the runtime container.
- [x] Command dumps the compiled container to a PHP file.
- [x] Dumped container can be used as a PSR-11 container.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to provider discovery, operation/container build orchestration, or a richer service registry DSL.
