# P19-002A CI Contract Closeout

## Summary

Closed the CI-only analyzer and documentation drift discovered after P19-002 acceptance. Incremental hash helper parameters now use PHP 8.5's native `HashContext` type, `IdempotencyClaimResult` proves its non-null record invariant in the property/constructor type, and the Core API Guide plus reader guard document and verify the two new Public API values (169 total).

## Changed Files

- `src/Internal/Idempotency/IdempotencyScopeHasher.php`
- `src/Internal/Idempotency/OperationValueFingerprinter.php`
- `src/Internal/Idempotency/IdempotencyClaimResult.php`
- `docs/guide/core-api.md`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/orchestration/reports/P19-002-idempotency-core-contract.md`
- `develop/orchestration/reports/P19-002A-ci-contract-closeout.md`
- `develop/STATE.md`

The Task Packet itself is present as the orchestrator-provided task artifact and was not semantically modified by this worker.

## Root Cause

- Mago on PHP 8.5 inferred `hash_init()` as `HashContext`, but the two helper DocBlocks declared `resource`, producing analyzer type errors.
- `IdempotencyClaimResult` stored a nullable union while returning a non-null union; static analysis could not prove the constructor invariant even though runtime validation rejected `null`.
- The Public API source inventory reached 169 types after P19-002, while `docs/guide/core-api.md` and its reader test retained the previous 167 count and lacked the two Public idempotency values.

## Decisions and Assumptions

- `\HashContext` is used directly; no wrapper, fallback, or runtime behavior changes were introduced.
- Claim Result constructor now accepts only `ProcessingRecord|TerminalRecord`; the meaningful `Claimed`=>`ProcessingRecord` check remains, while an impossible second union-type check was removed for analyzer cleanliness. `record()` remains non-null.
- Only `BlackOps\Idempotency\IdempotencyKey` and `IdempotencyKeyHash` were added to the guide. Internal Scope/Fingerprint/Store/Record types remain excluded.
- The accepted P19-002 hash algorithm, version, field inputs, fingerprints, storage semantics, and Public signatures are unchanged.

## Commands and Results

- `docker compose run --rm app mago format --check src tests examples` — PASS, all files formatted.
- `docker compose run --rm app mago lint src/Idempotency src/Internal/Idempotency src/Core/ExecutionContext.php src/Internal/Codec/ExecutionContextHydrator.php src/Internal/Codec/ExecutionContextNormalizer.php src/Internal/ExecutionContext/ExecutionContextFactory.php tests/Idempotency tests/Internal/Idempotency tests/Internal/Codec/ExecutionContextJsonCodecTest.php tests/Internal/Codec/ReflectionJsonOperationCodecTest.php tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php` — PASS, no issues found.
- `docker compose run --rm app mago analyze` — Orchestrator PASS, no issues found. Worker retries were blocked before startup by intermittent Docker API permission denial.
- `docker compose run --rm app mago lint` — Orchestrator PASS with the two accepted repository baseline diagnostics outside this Task scope.
- `docker compose run --rm app vendor/bin/phpunit tests/Idempotency tests/Internal/Idempotency tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php tests/Internal/Codec/ExecutionContextJsonCodecTest.php tests/Internal/Codec/ReflectionJsonOperationCodecTest.php tests/Architecture/PublicApiArchitectureTest.php` — PASS, 59 tests / 149 assertions.
- `mise exec -- pnpm --dir docs/website run test` — PASS, 42 tests / 42 assertions.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS.
- `git diff --check` — PASS.
- `git status --short` — only allowed P19-002A files and the orchestrator-provided task artifact are modified/untracked.

## Acceptance Criteria

- [x] HashContext annotations match PHP 8.5 analyzer inference.
- [x] Claim Result record is non-null by property/constructor type and status checks remain enforced.
- [x] Core API Guide documents both Public idempotency values.
- [x] Reader guard verifies 169 Public API types and both new values.
- [x] Focused PHPUnit, full format, Website Reader, management-ID, and diff checks pass.
- [x] Repository-wide Mago lint/analyze pass under Orchestrator verification; no baseline files were changed.
- [x] P19-002 Public Contract and HTTP/PostgreSQL/Retention/Outbox behavior remain unchanged.

## Remaining Issues

None within P19-002A. External publication and deployment remain outside scope.

## Suggested Next Action

Commit and push P19-002A, verify replacement GitHub Actions, then create P19-003 for HTTP／PHP duplicate lifecycle and retention.

## Orchestrator Acceptance

At `2026-07-24T00:44:27+09:00`, the Orchestrator independently reran the CI-equivalent Mago format／lint／analyze commands, focused PHPUnit 59 tests／149 assertions, Website Reader 42 tests, management-ID guard, and diff check. All required commands passed; analyzer output contains no issue, and lint only reports the two accepted repository baseline diagnostics outside this Task scope. P19-002A is Accepted for commit, push, and replacement CI verification.
