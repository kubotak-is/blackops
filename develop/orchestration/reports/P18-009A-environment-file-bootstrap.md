# P18-009A Environment File Bootstrap

## Summary

Implemented the public `ApplicationBuilder::withEnvironmentFile(?string $path = null): self` capability. The builder now resolves an explicitly selected environment source once per `create()`, merges an optional Dotenv file beneath the current Process Environment, validates a string-only snapshot, and keeps the existing array and Process-only `withEnvironment()` paths intact. Quickstart bootstrap now uses the Framework capability and no longer imports Dotenv directly.

The Framework package now owns its Dotenv runtime dependency. Quickstart and Community Board Composer dependencies were not removed; that closeout is intentionally deferred to P18-009D.

## Changed Files

- `src/Application/ApplicationBuilder.php`
- `src/Internal/Application/ApplicationEnvironmentFile.php`
- `composer.json`
- `composer.lock`
- `mago.toml` (vendor source included for static analysis type resolution)
- `tests/Application/ApplicationTest.php`
- `tests/Internal/Application/ApplicationBuilderTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `examples/quickstart/bootstrap/app.php`
- `develop/orchestration/reports/P18-009A-environment-file-bootstrap.md`
- `develop/STATE.md`

No HTTP Runtime, UUID, Community Board, Quickstart Composer dependency, or external publication files were changed.

## Final Public API and Environment Source Precedence

```php
Application::configure($basePath)
    ->withEnvironmentFile(?string $path = null)
    ->withConfiguration(?string $directory = null)
    ->create();
```

`withEnvironmentFile()` defaults to `<basePath>/.env`. A missing selected file is optional. A present regular file is parsed by the Framework-owned Dotenv dependency. After parse and shape validation, the resolved string snapshot is synchronized once to `$_ENV` for existing Application Consumers; `putenv()` and `$_SERVER` are not mutated. Parsed file values are merged first and Process Environment values are merged second, so Process Environment always wins. The last explicitly selected source (`withEnvironmentFile()` or `withEnvironment()`) replaces the previous source; `withConfiguration()` call order does not alter the selected source. Applications that do not select a source do not implicitly search for `.env`.

## Missing / Present / Invalid / Unreadable / Process Override Matrix

| Source state | Result |
| --- | --- |
| Default `.env` missing | Process-only string snapshot; bootstrap succeeds |
| Explicit file missing | Process-only string snapshot; bootstrap succeeds |
| Present regular file | File values fill missing keys; bootstrap succeeds |
| Process key also present in file | Process value wins |
| Existing directory at selected path | Safe `ApplicationBootstrapException`; no path contents or values exposed |
| Unreadable / read race | Safe `ApplicationBootstrapException` |
| Dotenv parse failure or non-string result | Safe `ApplicationBootstrapException`; parser detail and values are discarded |
| Explicit array via `withEnvironment()` | Existing resolved-array behavior is preserved |
| Omitted `withEnvironment()` | Existing Process-only capture-at-call behavior is preserved |

## Single Snapshot and Secret-safe Failure Evidence

`ApplicationBuilder::create()` resolves the selected file and Process Environment exactly once before constructing one `Environment` instance for all configuration closures. After successful parse and shape validation, the resolved string snapshot is synchronized once to `$_ENV`; parse failures do not partially mutate it. The resulting `Environment` is not retained in `ApplicationConfigurationSnapshot`; subsequent file or Process changes affect only a later `create()` call. Loader failures are converted to fixed safe messages without retaining the parser Throwable, file contents, or secret values in the public exception chain.

## Existing `withEnvironment` / Quickstart Consumer Compatibility

The existing Process-only capture regression remains green. Source replacement is covered in both orders (`withEnvironment()` then `withEnvironmentFile()`, and the reverse). Quickstart `bootstrap/app.php` now contains only the public Framework bootstrap call and no `Dotenv\\` or superglobal environment wiring. Quickstart setup consumer regression passed. The worker execution ceiling interrupted its long-lived Quickstart E2E attempts, but the Orchestrator independently reran the complete consumer and confirmed it passes after the `$_ENV` compatibility synchronization.

## Commands and Results

| Command | Result |
| --- | --- |
| `docker compose run --rm app vendor/bin/phpunit` | PASS — 1,715 tests, 6,851 assertions |
| Focused Application / Builder / Quickstart architecture PHPUnit | PASS — 31 tests, 345 assertions |
| `docker compose run --rm app mago format --check src tests` | PASS |
| `docker compose run --rm app mago lint` | PASS |
| `docker compose run --rm app mago analyze` | PASS — no issues |
| `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress` | PASS — 0 violations, 2,840 allowed, 1 uncovered |
| `docker compose run --rm app composer validate --strict` | PASS |
| `docker compose run --rm app composer validate --strict --working-dir=examples/quickstart` | PASS |
| Management-ID guard over `src tests examples/quickstart/bootstrap` | PASS — no matches |
| `git diff --check` | PASS |
| `bash tests/Consumer/quickstart-setup.sh` | PASS |
| `bash tests/Consumer/quickstart-e2e.sh` | PASS — Orchestrator independent verification; complete Quickstart consumer E2E passed |

## Acceptance Criteria

- [x] Public `withEnvironmentFile(?string $path = null): self` added.
- [x] Default and explicit optional file paths, Process precedence, source replacement, invalid directory, and parser-safe failures covered.
- [x] Environment snapshot is normalized through the existing string-only `Environment` contract.
- [x] Existing array, Process-only, and external-loader-compatible `withEnvironment()` paths remain green.
- [x] Environment source selection is independent of configuration call order and file reads are create-scoped.
- [x] Secret, raw file value, and parser Throwable detail are absent from bootstrap failure messages and exception chains.
- [x] Successful resolved snapshots synchronize to `$_ENV` once without changing `putenv()`; missing-file Process values synchronize and parse failure leaves no partial mutation.
- [x] Quickstart bootstrap uses the Framework capability without a Dotenv import.
- [x] Quickstart setup, Framework PHPUnit, Mago, Deptrac, Composer strict, management-ID, and diff gates pass.
- [x] Full Quickstart E2E passed in the Orchestrator independent verification.

## Remaining Issues

None for P18-009A. The worker's execution ceiling prevented it from collecting the long-lived E2E result, and the Orchestrator closed that evidence gap with an independent passing run.

## Suggested Next Action

Commit the accepted P18-009A change set, then proceed to P18-009B Framework-owned SAPI Runtime.

## Orchestrator Verification

- Source, lockfile, Task scope, public API inventory, safe failure boundary, and `$_ENV` compatibility synchronization reviewed: accepted.
- Focused Application／Builder／Quickstart architecture PHPUnit: PASS — 31 tests, 345 assertions.
- Mago Analyze: PASS — no issues.
- Deptrac: PASS — 0 violations, 2,840 allowed, 1 uncovered.
- Quickstart E2E: PASS — complete consumer journey.
- Management-ID guard and `git diff --check`: PASS.
