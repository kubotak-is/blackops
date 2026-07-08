# P1-022 Report: Runtime Container PHP Dump

## Summary

Implemented and accepted the runtime PHP container dump foundation.

`RuntimeContainerDumper` writes a compiled Symfony container to a single PHP file using Symfony's PHP dumper, with temporary-file write and atomic rename.

## Changed Files

- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `docs/internals/runtime-container.md`
- `orchestration/tasks/P1-022-runtime-container-dump.md`
- `orchestration/reports/P1-022-runtime-container-dump.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- The dumper is internal because Symfony dump options and generated class names are implementation details.
- Only single-file dumps are supported in this slice.
- The caller provides a compiled `ContainerBuilder`; compile orchestration remains with `RuntimeContainerCompiler`.
- Dumped containers are loaded by requiring the generated file and instantiating the generated class.
- Multi-file dumps, preload tuning, cache invalidation, service providers, config loading, and production bootstrap remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter RuntimeContainerDumperTest
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
Result: OK (204 tests, 497 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 283 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Compiled container can be written to a PHP file.
- [x] Output completes via atomic rename.
- [x] Dump file can be loaded to create the generated container class.
- [x] Dumped container can be treated as a PSR-11 container.
- [x] Dumped container can resolve a handler.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to the public service provider and configuration loader boundary so applications and packages can register services into the runtime container build.
