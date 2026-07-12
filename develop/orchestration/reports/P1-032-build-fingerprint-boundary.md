# P1-032 Report: Build Fingerprint Boundary

## Summary

Implemented and accepted the internal build fingerprint boundary.

Build artifact generation can now store a lightweight fingerprint for explicit input files. When the fingerprint matches and all output artifacts already exist, the build artifacts command skips regeneration.

## Changed Files

- `src/Internal/Build/BuildFingerprint.php`
- `src/Internal/Build/BuildFingerprintFile.php`
- `src/Internal/Build/BuildArtifactFingerprintGuard.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `tests/Internal/Build/BuildFingerprintTest.php`
- `tests/Internal/Build/BuildFingerprintFileTest.php`
- `tests/Internal/Build/BuildArtifactFingerprintGuardTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `docs/internals/operation-registry.md`
- `docs/internals/runtime-container.md`
- `develop/orchestration/tasks/P1-032-build-fingerprint-boundary.md`
- `develop/orchestration/reports/P1-032-build-fingerprint-boundary.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Fingerprints use explicit input file path, modification time, and size.
- Operation provider config and service provider config are always included in the build artifacts command fingerprint.
- Additional fingerprint inputs can be provided with `--fingerprint-input` as a `PATH_SEPARATOR` separated string.
- The build artifacts command skips only when the fingerprint matches and all three output artifacts exist.
- Composer discovery, file scanning, token scanning, content hashing, distributed cache, schema versioning, build IDs, and production bootstrap scripts remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'BuildFingerprintTest|BuildFingerprintFileTest|BuildArtifactFingerprintGuardTest|CompileBuildArtifactsCommandTest'
Result: OK (11 tests, 17 assertions).
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
Result: OK (253 tests, 573 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 383 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Build fingerprint can create a stable value from input file path, modification time, and size.
- [x] Missing input files are rejected.
- [x] Fingerprint files can be written and matched.
- [x] Build artifacts command accepts a fingerprint file path.
- [x] Build artifacts command accepts additional fingerprint input paths.
- [x] Build is skipped when the fingerprint matches and output artifacts exist.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to Composer-based provider discovery, production bootstrap wrapper, or manifest schema/build ID metadata.
