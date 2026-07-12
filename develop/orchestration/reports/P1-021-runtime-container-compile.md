# P1-021 Report: Runtime Container Compile Foundation

## Summary

Implemented and accepted the runtime container compile foundation.

`RuntimeContainerCompiler` creates a Symfony `ContainerBuilder`, compiles it, and returns the compiled container through the PSR-11 `ContainerInterface` boundary.

## Changed Files

- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`
- `docs/internals/runtime-container.md`
- `deptrac.yaml`
- `mago.toml`
- `develop/orchestration/tasks/P1-021-runtime-container-compile.md`
- `develop/orchestration/reports/P1-021-runtime-container-compile.md`
- `develop/STATE.md`

## Decisions and Assumptions

- The compiler is internal because Symfony `ContainerBuilder` is an implementation detail.
- The runtime boundary returned to framework code is PSR-11.
- Handler resolution uses the compiled container only at the framework composition boundary.
- Handlers still receive their own dependencies through constructor injection.
- Container PHP dumping, public service provider API, config loading, and production bootstrap remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter RuntimeContainerCompilerTest
Result: OK (2 tests, 3 assertions).
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
Result: OK (202 tests, 492 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 281 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Symfony `ContainerBuilder` can be created.
- [x] Container can be compiled and returned as a PSR-11 container.
- [x] Constructor autowiring can resolve a handler.
- [x] HTTP Manifest CLI Command can be resolved from a compiled container.
- [x] The container is not passed to handlers or envelopes.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Add a public service provider/configuration boundary, or add PHP container dumping for production bootstrap.
