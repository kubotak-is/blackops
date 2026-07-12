# P1-028 Report: Operation Manifest Compile Command

## Summary

Implemented and accepted the internal operation manifest compile command.

Build bootstrap code can now read an operation provider config file, compile provider definitions into an operation registry, and write registry metadata to a PHP manifest file. The manifest file can be loaded back into an operation registry.

## Changed Files

- `src/Internal/Console/CompileOperationManifestCommand.php`
- `src/Internal/Registry/OperationManifestFile.php`
- `src/Internal/Registry/OperationManifestMetadataCodec.php`
- `tests/Internal/Console/CompileOperationManifestCommandTest.php`
- `tests/Internal/Registry/OperationManifestFileTest.php`
- `docs/internals/operation-registry.md`
- `develop/orchestration/tasks/P1-028-operation-manifest-compile-command.md`
- `develop/orchestration/reports/P1-028-operation-manifest-compile-command.md`
- `develop/STATE.md`

## Decisions and Assumptions

- The command and manifest file boundary remain internal bootstrap/build concerns.
- The operation manifest contains scalar values and class names only.
- The first manifest shape is a PHP array with operation metadata entries; schema version, build ID, lock, and fingerprint are left out of scope.
- Metadata encode/decode is split into a codec so the file writer stays focused on filesystem concerns.
- HTTP route manifest generation, runtime container compilation, Composer discovery, file scanning, token scanning, and production bootstrap remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OperationManifestFileTest|CompileOperationManifestCommandTest'
Result: OK (10 tests, 16 assertions).
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
Result: OK (235 tests, 544 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 341 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Command accepts operation provider config path and output path.
- [x] Command loads operation provider config and builds an operation registry.
- [x] Operation registry metadata can be written to a PHP manifest file.
- [x] Dumped manifest file can rebuild an operation registry.
- [x] Missing files, invalid manifest return values, and invalid metadata shapes are rejected.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to operation/container build orchestration, HTTP route manifest integration with operation provider config, or Composer-based provider discovery.
