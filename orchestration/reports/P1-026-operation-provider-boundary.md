# P1-026 Report: Operation Provider Boundary

## Summary

Implemented and accepted the operation provider boundary.

Packages and applications can now implement a public `OperationProvider` contract that returns operation definition class names. The internal provider compiler folds providers into metadata and builds the read-only operation registry.

## Changed Files

- `src/Core/Registry/OperationProvider.php`
- `src/Internal/Registry/OperationProviderCompiler.php`
- `tests/Core/Registry/OperationProviderTest.php`
- `tests/Internal/Registry/OperationProviderCompilerTest.php`
- `docs/internals/operation-registry.md`
- `orchestration/tasks/P1-026-operation-provider-boundary.md`
- `orchestration/reports/P1-026-operation-provider-boundary.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- `OperationProvider` is public because package and application code implement it.
- Providers return operation definition class names only; they do not create handlers, values, outcomes, services, or runtime dependencies.
- The internal compiler reuses the existing metadata compiler and registry duplicate checks.
- Config loading, Composer discovery, file scanning, token scanning, manifest file orchestration, and production bootstrap remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OperationProviderTest|OperationProviderCompilerTest'
Result: OK (4 tests, 5 assertions).
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
Result: OK (221 tests, 524 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 310 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Public `OperationProvider` contract can be implemented.
- [x] Operation providers can return operation definition class names.
- [x] Operation providers can be compiled into an operation registry.
- [x] Provider metadata can be searched by type ID and definition class.
- [x] Invalid operation definitions are rejected.
- [x] Duplicate type IDs or definition classes are rejected.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to operation provider config loading, Composer-based provider discovery, or operation/container build orchestration.
