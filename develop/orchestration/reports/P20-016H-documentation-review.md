# P20-016H Documentation Review

## Scope and Evidence

- Scope: P20-016H changed pages, navigation, content map, website tests, and the required reader journey
- Channel: Stable `1.1.0` and repository `main`
- Comparison: working tree against `HEAD`
- Sources: current implementation and tests, Git tag `1.1.0`, Specifications 92, 97, and 99, and the accepted P20-016A through G reports
- Browser: existing static artifact in Chromium from `mcr.microsoft.com/playwright:v1.61.1-noble`, at 1440px and 390px in Light and Dark themes
- Review mode: read-only; the Documentation Reviewer did not edit files, run write-producing website commands, commit, push, deploy, or publish

## Initial Verdict

P1: 2 findings. P2: 3 findings. P3: none. The initial working tree was not acceptable.

## P1 Findings

### P1-1. Repository main journey appeared usable on Stable 1.1.0

- Location: `docs/guide/tenant-protection.md` introduction and steps 1 through 7; `docs/guide/README.md`
- User impact: a Stable 1.1.0 user could attempt types, signatures, and commands that do not exist in that release.
- Evidence: tag `1.1.0` does not include `TenantRef`, Operation Data, Storage Protection, or Tenant Provider APIs. Its `Dispatcher::dispatch()` accepts only Operation and OperationValue, while the repository main signature also includes Actor Context, Idempotency Key, and Tenant. The release table correctly marks the feature unavailable on Stable.
- Required correction: label the journey and its landing-page link as repository-main only before the first executable step, and direct Stable users to the release table.
- Verification: compare Stable inventory and CLI to repository main, then confirm the boundary is visible in the rendered journey.
- Confidence: Confirmed

### P1-2. Migration envelope-detection claim contradicted the implementation

- Location: `docs/guide/tenant-protection.md`, Fresh Database and database verification sections
- User impact: a user could treat Migration as a ciphertext integrity check and assume a corrupt envelope was already detected.
- Evidence: the page claimed that Migration detects missing headers and malformed envelopes, while another section described those cases as runtime protection failures. The two protected-storage migrations only test whether target tables are non-empty before changing them; they do not decode or inspect existing rows.
- Required correction: state that any non-empty legacy protected table stops Migration before changes. Limit missing, malformed, unknown-key, and tampered-envelope handling to runtime protection failure.
- Verification: compare the final wording with both migration source guards and their tests.
- Confidence: Confirmed

## P2 Findings

### P2-1. Provider registration did not lead to runnable tenant operations

- Location: `docs/guide/tenant-protection.md` steps 1 through 4; `docs/guide/configuration.md`
- User impact: copied examples could fail at container compilation, and the reader could not complete tenant-aware HTTP, Console, and Scheduled execution.
- Evidence: the HTTP snippet omitted the `TenantRef` import. The Scheduled provider required a constructor `TenantRef` that the container example did not supply. The journey omitted `config/app.php` registration and concrete execution and expected-result steps. Application-owned key and authorizer implementations were referenced without a complete path to their contracts.
- Required correction: provide complete current-API bindings and navigation to implementation contracts, application provider registration, build, and concrete entry execution steps with expected results.
- Verification: run the sequence in a fresh Skeleton through compile, HTTP, Console, Schedule, and authorized Status, Journal, and Outcome reads.
- Confidence: Confirmed

### P2-2. Raw-value verification was incomplete for eight storage purposes

- Location: `docs/guide/tenant-protection.md`, database raw-value verification
- User impact: a user had to invent SQL and could misclassify nullable protected columns as plaintext.
- Evidence: only `journal.encoded_record` had complete SQL. Applying `count(*)` to nullable operation and idempotency fields would not give the documented comparison. The query was also described as bounded despite scanning the full table.
- Required correction: provide complete read-only SQL for all nine purposes using `count(column)` for nullable protected columns, connection context, expected equality, and empty-table interpretation without selecting sensitive content.
- Verification: run the SQL against fresh and populated databases and confirm only counts are returned.
- Confidence: Confirmed

### P2-3. Troubleshooting did not contain the required protection runbook

- Location: `docs/guide/tenant-protection.md` troubleshooting summary; `docs/guide/troubleshooting.md`
- User impact: users encountering missing providers, unknown keys, tag failures, legacy-schema stops, or rotation remainder had to invent diagnosis and recovery steps.
- Evidence: the tenant page only had a summary table, and the troubleshooting source lacked dedicated symptoms, causes, checks, and correction sections with safe fields, exit behavior, old-key recovery, and checkpoint resume.
- Required correction: add the four-part runbooks to the troubleshooting source of truth and link each tenant-page symptom to its heading.
- Verification: exercise the corresponding fixtures and confirm the documentation is sufficient to classify and recover without exposing secrets.
- Confidence: Confirmed

## P3 Findings

None.

## Cross-cutting Regression Guards

- `git diff --check`: passed during the initial review.
- Stable `1.1.0` public inventory and Dispatcher signature were compared with repository `main`.
- Current Tenant Provider, Operation Data Query, Storage Key Provider, rotation CLI, and Migration guard sources and tests were inspected.
- The static artifact did not expose `docs/internal/`, `develop/`, repository absolute paths, secret-key formats, or source maps.
- Mermaid used the local artifact runtime rather than an external CDN.
- Website test, check, and build were not rerun by the read-only Reviewer; the worker results were treated as reported evidence only.

## Positive Findings

- The release table correctly separates Stable-unavailable features from repository-main functionality.
- Default-deny Operation Outcome query parameters and authorization-before-decode matched current source.
- Rotation purposes, option constraints, exit codes, checkpoint behavior, and remaining boundary matched current source.
- Raw canonical journal data was not presented as a public HTTP surface.
- Key material and old-key lifecycle guidance respected the artifact and recovery boundaries.
- All eight reviewed routes returned HTTP 200 in four browser contexts, with no page-wide overflow, console error, failed request, heading jump, or unnamed visible link. Active navigation was unique, and mobile table overflow remained inside its local scroll host.

## Commands and Browser Evidence

- Read-only source review used `git status --short`, diff inspection, `git diff --check`, `git show 1.1.0:src/Execution/Dispatcher.php`, tag inventory, and focused repository searches.
- Browser image: `mcr.microsoft.com/playwright:v1.61.1-noble`.
- Routes: Tenant Protection, Security, Project CLI, Current Status, Worker Operations, Troubleshooting, Configuration, and Outcomes.
- Contexts: Desktop 1440 Light and Dark; Mobile 390 Light and Dark.
- Evidence: `/tmp/p20-016h-browser-output/evidence.json` and `/tmp/p20-016h-browser-output/*.png`.

## Not Verified and Limitations

- Changed pages outside the eight browser routes were inspected in source and artifact but did not each receive a screenshot.
- Keyboard focus behavior and numeric WCAG contrast ratios were not measured in the initial Reviewer pass.
- The read-only Reviewer did not execute the write-producing fresh Consumer journey.
- External publication was outside scope.

## Resolution and Final Verdict

The first review returned P1=2／P2=3／P3=0. The first correction round resolved the Stable／main boundary, Migration boundary, nine-purpose SQL, and troubleshooting structure, but re-review found P1=1／P2=1 in the troubleshooting detection surfaces and the runnable Provider／Authorizer／HTTP Tenant journey.

The second correction round separated Build from Runtime Provider resolution, limited Envelope Integrity checks to authorized protected reads, Worker processing, and confirmed Rotation, kept Plan／prefix counts／`operation:inspect` outside Integrity verification, and limited fingerprints to Rotation Audit. It also supplied a complete `SampleStorageKeyProvider`, `OperationDataReadAuthorizer`, Status Authorizer link, complete Tenant Provider bindings, and the Quickstart HTTP Tenant return.

The final read-only review confirmed every initial and follow-up finding resolved with no new finding:

- P1: 0
- P2: 0
- P3: 0
- Verdict: Acceptance permitted

Orchestrator independently reran the latest artifact in 32 Chromium cases across eight routes, Desktop 1440 Light／Dark, and Mobile 390 Light／Dark. All routes returned HTTP 200; page overflow, console errors, failed requests, heading jumps, unnamed visible links, active-navigation failures, and keyboard-focus failures were zero. Wide tables remained inside local horizontal-scroll hosts. Website tests, check, build, Quickstart E2E, and storage-rotation Consumer also passed.

## Suggested Review Order

Complete. Preserve the added reader-journey, safe diagnostics, and Browser regression guards when the separate OpenTelemetry work begins.
