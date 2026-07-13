# P1-031 Report: Build Lock Boundary

## Summary

Implemented and accepted the internal build lock boundary.

Build artifact generation can now be guarded by a local lock file. The build artifacts command accepts an optional lock path and runs the full artifact generation step inside that lock.

## Changed Files

- `src/Internal/Build/BuildLock.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `tests/Internal/Build/BuildLockTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `docs/internal/operation-registry.md`
- `docs/internal/runtime-container.md`
- `develop/orchestration/tasks/P1-031-build-lock-boundary.md`
- `develop/orchestration/reports/P1-031-build-lock-boundary.md`
- `develop/STATE.md`

## Decisions and Assumptions

- The lock is local file based and internal.
- The lock uses a non-blocking exclusive lock and fails fast if another process already holds it.
- The build artifacts command keeps lock usage optional so existing build flows continue to work.
- Distributed locks, timeout/retry policy, fingerprint skip logic, cache invalidation, schema versioning, build IDs, and production bootstrap scripts remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'BuildLockTest|CompileBuildArtifactsCommandTest'
Result: OK (5 tests, 10 assertions).
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
Result: OK (244 tests, 562 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 378 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Build lock can execute a critical section through a lock file.
- [x] Missing lock directories are rejected.
- [x] Build artifacts command accepts an optional lock file path.
- [x] Build artifacts generation runs inside the lock when a lock path is provided.
- [x] Build artifacts generation still works without a lock path.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to build fingerprints, Composer-based provider discovery, or a production bootstrap wrapper.
