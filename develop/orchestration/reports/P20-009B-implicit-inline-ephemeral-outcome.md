# P20-009B: Implicit Inline Ephemeral Outcome

## Summary

Ephemeral Outcome compiler validation now checks the resolved execution strategy rather than requiring an explicit `ExecuteWith` attribute. Attribute-free Ephemeral HTTP Operations compile as Inline while the existing explicit Inline form remains compatible; both canonical `#[Deferred]` and compatibility `#[ExecuteWith(Deferred::class)]` are rejected. Auth generator stubs, Community Board identity Operations, fresh Auth Consumer, Frontend/PostgreSQL fixtures, and the selected reader-facing Guides now use the canonical implicit Inline form.

## Changed Files

- `src/Internal/Registry/OperationMetadataCompiler.php`
- `tests/Internal/Registry/EphemeralOutcomeContractCompilerTest.php` (canonical `#[Deferred]` rejection regression)
- `tests/Internal/Generator/AuthGeneratorTest.php`
- `tests/Internal/Frontend/EphemeralFrontendContractTest.php`
- `tests/Transport/PostgreSql/PostgreSqlEphemeralOutcomeIntegrationTest.php`
- `tests/Frontend/fixture/app/Feature/Identity/IssueCredential/IssueCredential.php`
- `tests/Consumer/fixtures/auth-fresh/RotateSession/RotateSession.php`
- `resources/stubs/auth-register.php.stub`
- `resources/stubs/auth-login.php.stub`
- `resources/stubs/auth-logout.php.stub`
- `examples/community-board/app/Feature/Identity/Register/Register.php`
- `examples/community-board/app/Feature/Identity/Login/Login.php`
- `examples/community-board/app/Feature/Identity/Logout/Logout.php`
- `docs/guide/authentication.md`
- `docs/guide/operations.md`
- `docs/guide/attributes.md`
- `docs/guide/core-api.md`
- `docs/guide/community-board.md`
- `docs/guide/glossary.md`
- `docs/guide/security.md`
- `docs/internal/auth-generator.md`
- `docs/internal/ephemeral-outcome.md`
- `examples/community-board/README.md`
- `develop/decisions/112-authentication-credential-response-boundary.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/17-core-api.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`
- `develop/spec/87-documentation-second-review-and-feature-parity.md`
- `develop/spec/90-documentation-third-review-accuracy.md`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/spec/93-implicit-inline-ephemeral-outcome.md`
- `develop/decisions/126-implicit-inline-ephemeral-outcome.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This report

## Decisions and Assumptions

- The resolved strategy is the security boundary; an absent strategy attribute is normalized to `BlackOps\\Core\\Execution\\Inline` before Ephemeral validation.
- Exactly one HTTP Route, no ConsoleCommand, Sensitive Outcome Shape, and non-persistence contracts remain unchanged.
- Existing explicit Inline coverage remains in the compiler compatibility fixture; Deferred and custom strategies are rejected by the resolved-strategy guard.
- Community Board, Glossary, and Security Guide now describe Route-bound Ephemeral Operations as resolving to Inline when no strategy attribute is present.

## Commands and Results

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Registry/EphemeralOutcomeContractCompilerTest.php tests/Internal/Generator/AuthGeneratorTest.php tests/Internal/Frontend/EphemeralFrontendContractTest.php` — PASS (27 tests, 147 assertions)
- `docker compose run --rm app vendor/bin/phpunit tests/Transport/PostgreSql/PostgreSqlEphemeralOutcomeIntegrationTest.php` — PASS (1 test, 17 assertions)
- `bash tests/Consumer/auth-generator-fresh.sh` — PASS
- `bash tests/Consumer/community-board-identity.sh` — PASS
- `mise exec -- pnpm --dir docs/website run test` — PASS (59 tests, including authoritative Specification/internal-doc regression)
- `mise exec -- pnpm --dir docs/website run check` — PASS (37 pages, 0 errors/warnings/hints)
- `mise exec -- pnpm --dir docs/website run build` — PASS (38 pages; artifact and site guards passed)
- `docker compose run --rm app vendor/bin/phpunit` — PASS (1881 tests, 7586 assertions; existing 1 deprecation)
- `docker compose run --rm app mago format --check src tests` — PASS after formatting the changed compiler file
- `docker compose run --rm app mago lint src tests` — FAIL on existing repository-wide findings (1634 issues); changed fixture files also contain pre-existing sensitive-parameter and multi-class warnings
- `docker compose run --rm app mago analyze src tests` — FAIL on existing repository-wide analysis findings (1024 issues, including unresolved PHPUnit TestCase types)
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress` — FAIL before analysis because the existing parser hits `unexpected token "("` in `NikicFileReferenceVisitor.php`
- `! rg -n -F "#[ExecuteWith('BlackOps\\\\Core\\\\Execution\\\\Inline')]" resources/stubs examples/community-board/app tests/Consumer/fixtures docs/guide` — PASS
- Guide stale-phrase guard — PASS
- Final corrected authoritative-source stale-phrase guard over Specifications, internal docs, and Community Board README — PASS
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'` — PASS
- `git diff --check` — PASS
- Task Status — Accepted after Orchestrator Review; no commit created
- Orchestrator rerun: targeted PHPUnit — PASS (27 tests, 147 assertions)
- Orchestrator rerun: Website tests — PASS (59 tests)
- Orchestrator rerun: Mago format, explicit Inline／Guide／Specification stale guards, Management ID, `git diff --check` — PASS

## Acceptance Criteria

- [x] Attribute-free Ephemeral Outcome Inline compiles and records Inline strategy.
- [x] Explicit Inline compatibility is retained.
- [x] Canonical `#[Deferred]`, compatibility Deferred, custom strategy, route-less, and Console operations are rejected.
- [x] Credential non-persistence, Sensitive shape, and Frontend boundaries remain covered by regression tests.
- [x] Auth Generator emits starters without `ExecuteWith`.
- [x] Community Board and Auth Consumer journeys complete with implicit Inline authoring.
- [x] Selected reader-facing Guides no longer require explicit Inline.
- [x] Stable/compatibility references were retained.
- [x] Every required command was executed; Task-related gates are green and pre-existing repository-wide failures are documented.
- [x] Report and STATE are updated.
- [x] No commit created.

## Remaining Issues

Repository-wide Mago lint/analyze and Deptrac parser failures predate this Task and were not changed outside the allowed scope. D112 remains Partially Superseded by D126; its Route, resolved-Inline, Deferred／Console, and non-persistence guards remain authoritative.

## Suggested Next Action

Track the existing repository-wide quality-gate debt separately. Continue with P20-010 using the implicit Inline contract and keep this Task uncommitted until the Phase 20 handoff is requested.
