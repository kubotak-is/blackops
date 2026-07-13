# P1-035 Report: Installed Composer Provider Discovery

## Summary

Implemented and accepted installed Composer provider discovery and build integration.

The internal discovery boundary can now aggregate BlackOps operation and service provider class names from Composer installed package metadata. The build artifacts command can merge those discovered providers with explicit provider config and root Composer metadata, and include the installed metadata file in build fingerprint inputs.

## Changed Files

- `src/Internal/Discovery/InstalledComposerProviderDiscovery.php`
- `src/Internal/Discovery/ComposerProviderDiscovery.php`
- `src/Internal/Build/BuildArtifactProviderLoader.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `tests/Internal/Discovery/InstalledComposerProviderDiscoveryTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `docs/internal/operation-registry.md`
- `docs/internal/runtime-container.md`
- `develop/orchestration/tasks/P1-035-installed-composer-provider-discovery.md`
- `develop/orchestration/reports/P1-035-installed-composer-provider-discovery.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Installed package discovery supports Composer metadata with a top-level `packages` list and the older root package-list shape.
- Packages without BlackOps provider metadata are ignored.
- Installed package discovery returns provider class names only and does not instantiate providers.
- Build integration passes discovered provider class names through the same provider instantiation boundary used by explicit config files.
- Root Composer metadata and installed package metadata can be used together, but Composer Runtime API and Composer plugin behavior remain out of scope.
- Operation Manifest and Runtime Container single-purpose commands remain explicit-config only for this slice.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'InstalledComposerProviderDiscoveryTest|CompileBuildArtifactsCommandTest'
Result: OK (13 tests, 26 assertions).
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
Result: OK (268 tests, 598 assertions). Runtime PHP 8.5.7.
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 393 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Composer 2 installed metadata can expose provider class names.
- [x] Legacy package-list JSON can expose provider class names.
- [x] Packages without provider metadata are ignored.
- [x] Invalid installed metadata, invalid package entries, and invalid provider entries are rejected.
- [x] Build Artifacts compile command can accept an installed packages metadata file.
- [x] Installed packages metadata file participates in the build fingerprint input set.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to production bootstrap wrapper or command registration/bootstrap documentation.
