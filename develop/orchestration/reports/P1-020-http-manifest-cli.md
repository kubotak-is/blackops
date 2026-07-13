# P1-020 Report: HTTP Manifest CLI

## Summary

Implemented and accepted the minimal HTTP manifest dump command.

`DumpHttpManifestCommand` receives an operation registry and definition list from bootstrap code, compiles an HTTP manifest, and writes it through the existing manifest file writer.

## Changed Files

- `src/Http/Console/DumpHttpManifestCommand.php`
- `tests/Http/DumpHttpManifestCommandTest.php`
- `docs/internal/http-api-slice.md`
- `deptrac.yaml`
- `mago.toml`
- `develop/orchestration/tasks/P1-020-http-manifest-cli.md`
- `develop/orchestration/reports/P1-020-http-manifest-cli.md`
- `develop/STATE.md`

## Decisions and Assumptions

- The command is HTTP-specific and lives under `BlackOps\Http\Console`.
- The command does not discover operations. It only consumes an injected registry and injected operation definition list.
- `Symfony\Component\Console` was added to the Library layer and Mago includes so the existing dependency can be referenced by source code.
- Framework console application bootstrap and DI container wiring remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter DumpHttpManifestCommandTest
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
Result: OK (200 tests, 489 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 277 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Symfony Console Command can write an HTTP Manifest to a PHP array file.
- [x] The generated Manifest can be loaded by the Loader.
- [x] The loaded Manifest can rebuild a route registry.
- [x] Operation Discovery and Provider remain separate and unimplemented.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to Runtime DI Container Compile, where this command can be registered by the framework bootstrap.
