# P1-039 Report: Application Runtime Bootstrap Guide

## Summary

Implemented and accepted the application-facing runtime bootstrap guide for Phase 1.

The guide now explains how applications define provider config, use Composer provider metadata, compile build artifacts, load production artifacts, compose the HTTP inline runtime, and understand the current Phase 1 runtime boundary.

## Changed Files

- `docs/guide/runtime-bootstrap.md`
- `docs/guide/README.md`
- `develop/orchestration/tasks/P1-039-application-runtime-bootstrap-guide.md`
- `develop/orchestration/reports/P1-039-application-runtime-bootstrap-guide.md`
- `develop/STATE.md`

## Decisions and Assumptions

- This slice is documentation-only and does not change production code.
- The guide documents currently accepted Phase 1 capabilities only.
- Deferred execution, worker runtime, retry/recovery, retention, environment loading, and front-controller generation remain explicitly out of scope.
- Internal runtime classes are documented as the currently available Phase 1 bootstrap mechanism, not as stable public API.

## Commands and Results

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
Result: OK (274 tests, 609 assertions). Runtime PHP 8.5.7.
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 413 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Runtime Bootstrap Guide is added.
- [x] Provider config and Composer metadata usage is explained.
- [x] Build artifacts compile inputs, outputs, and options are explained.
- [x] Production artifact loading and runtime composition usage is explained.
- [x] Deferred/worker capabilities not implemented in Phase 1 are explicit.
- [x] Guide README links to the Runtime Bootstrap Guide.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to a minimal example application or smoke scenario that proves the guide path end to end.
