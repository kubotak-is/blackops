# P1-030 Report: Build Artifacts Command

## Summary

Implemented and accepted the internal build artifacts command.

Build bootstrap code can now generate the operation manifest, HTTP route manifest, and runtime container PHP file in one command from operation provider config and service provider config.

## Changed Files

- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `docs/internal/http-api-slice.md`
- `docs/internal/operation-registry.md`
- `docs/internal/runtime-container.md`
- `develop/orchestration/tasks/P1-030-build-artifacts-command.md`
- `develop/orchestration/reports/P1-030-build-artifacts-command.md`
- `develop/STATE.md`

## Decisions and Assumptions

- The command is internal because it coordinates internal provider config loading, manifest generation, and container dumping.
- Operation providers and service providers stay as separate config inputs.
- The command composes existing build boundaries instead of replacing individual compile commands.
- Composer discovery, file scanning, token scanning, build locks, cache invalidation, schema versioning, build IDs, preload tuning, and production bootstrap scripts remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter CompileBuildArtifactsCommandTest
Result: OK (2 tests, 6 assertions).
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
Result: OK (241 tests, 558 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 374 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Command accepts operation provider config path, service provider config path, and all output paths.
- [x] Operation manifest PHP file can be written and loaded back into an operation registry.
- [x] HTTP route manifest PHP file can be written and loaded back into an HTTP route registry.
- [x] Runtime container PHP file can be written and used as a PSR-11 container.
- [x] Missing files or invalid config are rejected.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to Composer-based provider discovery, build locks/fingerprints, or a production bootstrap wrapper.
