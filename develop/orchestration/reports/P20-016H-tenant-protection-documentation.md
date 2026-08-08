# P20-016H Tenant Isolation and Storage Protection Documentation — Completion Report

Status: Accepted

## Documentation Reviewer Round 3 Rework

The second read-only re-review returned P1=1／P2=1. Corrections completed in this reopen:

- P1: Rewrote the Provider／Tamper runbook to separate `build:compile` from Runtime composition. Provider resolution is described only at HTTP／Worker／Console／Scheduled／Outbox／Query／Rotation Runtime boundaries; build success no longer implies a Provider check. Prefix counts and `operation:inspect` are explicitly clear-lifecycle diagnostics, not Envelope Integrity checks. Unknown Key／Tag Tamper verification is limited to authorized Journal／Outcome reads, Worker, and confirmed Rotation; Rotation Audit is the only Fingerprint surface.
- P2: Replaced undefined Secret Source bindings with the complete existing `SampleStorageKeyProvider` Environment-backed implementation from `examples/quickstart`, added a complete minimal raw-value-free `OperationDataReadAuthorizer`, linked the existing `OperationStatusAuthorizer` example, synchronized `configuration.md` bindings with the Tenant guide and Quickstart classes, and added a complete Tenant-aware `SampleTokenAuthenticator` success return for `/invoices`.

## Summary

Synchronized the public and internal documentation with the accepted P20-016A–G implementation. The new reader journey covers Stable `1.1.0` versus Repository `main`, TenantRef entry and propagation, provider registration, default-deny Status／Journal／Outcome reads, BOPD v1 storage, breaking upgrade behavior, safe database checks, nine-purpose rotation, retention／replay／outbox／idempotency, deployment, troubleshooting, reference, and release boundaries. Removed stale public `OutcomeReader` and plaintext-storage claims.

## Independent Documentation Review Rework

The first read-only reviewer returned P1=2 and P2=3. This reopen corrected the following findings without changing Production Code or Migration:

- P1-1: Added an explicit Repository `main`-only callout at the start of the Tenant guide and a main-only notice in the Guide landing; Stable `1.1.0` users are linked back to `Stableとmain`.
- P1-2: Changed the migration boundary to stop before any change when a legacy protected table is non-empty without inspecting row contents. Missing Header, Malformed, Unknown Key, and Tampered Tag are documented only as Runtime Protection Failure.
- P2-1: Added the HTTP `TenantRef` import, a complete local `ConfiguredApplicationTenantResolver` example plus `ServiceRegistry::autowire` Binding, `config/app.php` registration, Build, exact `report:export`／`/invoices`／`reports.daily` journey links, expected HTTP／Console／Scheduled status and exit fields, and the Application-owned replacement boundary.
- P2-2: Replaced the representative SQL with a read-only aggregate covering all nine protected purposes. Nullable columns use `count(column)`; only table／column counts and BOPD-prefix match booleans are returned. Key ID is explicitly BOPD Header metadata, not a clear column, and “Bounded Scan” is removed from the read-only check.
- P2-3: Added four complete troubleshooting runbooks for Provider registration, Unknown Key／Tag Tamper, non-empty legacy Schema migration stop, and non-zero Rotation `remaining`, each with symptom, cause, verification, fix, safe output／exit boundary, old-key recovery, and checkpoint resume guidance. The Tenant summary links each concrete heading.

## Changed Files

- Public guide: `docs/guide/tenant-protection.md`, `README.md`, `configuration.md`, `core-api.md`, `deployment.md`, `first-operation.md`, `journal.md`, `mvp-sample.md`, `mvp-status.md`, `outcome-retrieval.md`, `project-cli.md`, `security.md`, `troubleshooting.md`
- Internal contract: `docs/internal/tenant-protection.md`, `README.md`, `deferred-transport-contract.md`, `idempotency.md`, `journal-ports.md`, `outcome-store.md`, `postgresql-journal-store.md`, plus stale inline-strategy wording corrections in `auth-generator.md` and `ephemeral-outcome.md`
- Website map/navigation/tests: `docs/website/content-map.mjs`, `site-navigation.mjs`, `tests/guide-code.test.mjs`, `tests/reader-experience.test.mjs`, `tests/site-navigation.test.mjs`
- Workflow: this Task Packet, `develop/TODO.md`, `develop/STATE.md`

## Decisions and Assumptions

- Public application reads use `OperationJournalQuery`／`OperationOutcomeQuery` with Current Actor, Current Tenant, and `OperationDataPurpose`; raw readers remain infrastructure SPI.
- `StorageKeyProvider` is registered through `ServiceRegistry`; examples contain no key material, credential, absolute repository path, or real secret.
- Migration stops for non-empty legacy protected rows. Malformed／unknown／tampered BOPD is documented as a runtime Protection Failure, not as a migration detection claim.
- Rotation examples use the exact current options, default batch 100, bounded range 1–1000, explicit confirmed checkpoint, required actor/reason, safe result fields, and exit 0／1／2. Old-key deletion and replica／backup／retention verification remain application operations.
- Browser screenshot automation was not executed in this worker environment: Docker API access is unavailable and separate shell sessions cannot reach the temporary static server. Static artifact generation, Blume checks, navigation, accessibility, and source-level responsive overflow contracts pass; Orchestrator should perform the independent Desktop Light／Dark and Mobile browser evidence pass.

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS, 75 tests.
- `mise exec -- pnpm --dir docs/website run check` — PASS: content, diagrams, Blume validation/type-check (0 errors, 0 warnings, 0 hints).
- `mise exec -- pnpm --dir docs/website run build` — PASS: 40 source pages / 41 static pages, artifact boundary, navigation, accessibility, version notice, and search checks; existing Vite chunk-size warning only.
- `docker compose run --rm app mago format --check src tests` — PASS: all files formatted.
- `! rg -n 'Project CLI|ExecuteWith\\([^)]*Inline' docs/guide docs/internal` — PASS after removing existing stale wording.
- `git diff --check` — PASS.
- Round 3 correction guards: no public `ApplicationStorageProtectionResolver`／undefined Secret Source class names; website reader guard covers Runtime composition separation, diagnostics boundary, Rotation-only fingerprints, complete Quickstart Tenant return, and Sample Provider contract.
- Focused website tests were rerun after all corrections — PASS, including new Tenant／Storage reader-journey and 201-type Core API coverage.
- `bash tests/Consumer/quickstart-e2e.sh` — PASS, including build, migration, seed, authenticated HTTP, Worker, Status／Outcome, and generated frontend flow.
- `bash tests/Consumer/storage-protection-rotation.sh` — PASS, including plan, CAS, crash／resume, and redaction.
- Orchestrator browser evidence — PASS, 8 routes × Desktop 1440 Light／Dark × Mobile 390 Light／Dark = 32 cases. HTTP／console／request／heading／accessible-name／active-navigation／page-overflow／keyboard-focus failures were all 0; 18 wide content instances remained inside local scroll hosts. Screenshots are under `/tmp/P20-016H-*.png`.
- Read-only Documentation Reviewer final review — PASS, P1=0／P2=0／P3=0 after all initial and follow-up findings were resolved.

## Acceptance Criteria

- [x] Required Reader Journey is sequential and runnable with current APIs and commands.
- [x] Tenant entry, propagation, global boundary, and Authorization distinction match accepted implementation.
- [x] Status／Journal／Outcome default-deny ordering and Query contracts are documented without public raw-reader claims.
- [x] Provider registration and no-key-material Artifact boundary are explicit.
- [x] Protected field table, BOPD envelope, AAD, `#[Sensitive]` distinction, and safe failure boundary are synchronized.
- [x] Experimental v1 breaking upgrade and non-automatic deletion／conversion are explicit.
- [x] Rotation options, outputs, exit codes, CAS, checkpoint resume, remaining boundary, and old-key handling match source.
- [x] Security／Deployment／Troubleshooting／Reference／Release and navigation are synchronized.
- [x] Website test／check／build and required guards pass.
- [x] Desktop Light／Dark and Mobile browser screenshots／overflow evidence — Orchestrator verified 32 cases.
- [x] Documentation Reviewer finding report and Orchestrator acceptance — final P1=0／P2=0／P3=0.
- [x] Task／Report／STATE／TODO synchronized; no commit, push, deploy, or publication performed.

## Remaining Issues

None within P20-016H. External publication and deployment remain outside this Task.

## Suggested Next Action

Commit the accepted P20-016H documentation slice. Start the separate Decision／Task boundary for the remaining Phase 20 structured log schema and OpenTelemetry adapter; do not treat the future adapter as part of this documentation change.
