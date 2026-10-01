# P23-003D2J: Journal and Outcome Read Audit Adapters

Status: Accepted — local source and 512MiB PHPUnit verification

## Goal

Continue the authorized 1.3 work after accepted D2S and P23-017. Add latent
Journal/Outcome read adapters that commit requested before lookup/authorization
and exactly one terminal before releasing a result or safe query failure.
Preserve the existing public query contracts, tenant isolation and current
runtime activation. This child does not complete parent D or release 1.3.

## Scope and Authority

AGENTS/current profiles and spec109 govern team and verification. Read D154's
reader split, D155's factory/session and reserved reader-child contract,
spec99's Direct Journal and Outcome Read Authorization, and spec106's D slice.
The most-specific contract is spec111 with rationale D160.
D2S is a reviewed pattern, not a new implementation dependency.

Root management files: this Task/Report, spec111, D160, spec README, current
TODO pointer, STATE/archive, a precise cross-reference in D155 if needed, and
the user-requested PHPUnit memory-budget guidance in spec109.
Read-only investigation may inspect current Journal/Outcome queries, sources,
tests, D2 interfaces, architecture oracles, package and verification config.
The exact Product/Test scope below is reserved for dispatch after independent
authority review; no Product worker may infer or broaden that scope.
Preserve all earlier dirty documentation and D2S management files. No push,
tag, CI, deployment or public runtime activation is selected.

## Team and Ownership

Root owns authority, integration, Report/STATE, Git and all PHP/DB/cache and final
verification. The read-only explorer mapped source seams and inherited traces.
Astra/max independently reviews transaction/security before dispatch and at
acceptance. No reviewer edits Product or runs duplicate verification.

Luna/max Runtime worker owns the five new source files, the four existing query/
reader annotation corrections, the fixed codec decode annotation, and the
three new Internal/OperationData unit files. Luna/max Architecture worker owns
the new architecture test, the existing D2 outside-target oracle and the two
package scripts. Luna/max PostgreSQL worker owns the one new integration test.
Workers return chat handoffs and exact hashes; no shared Report/STATE writing.
Only the Architecture worker may run its two shell checks. Root serializes all
PHP and PostgreSQL commands; workers do not start PHP, DB, caches or Consumers.
No website generation or shared diagrams/fonts are used. The memory amendment
below assigns phpunit.xml alone to the Runtime worker; Root retains all checks.

## Exact Product and Test Scope

```text
src/Internal/OperationData/AuditedOperationJournalQuery.php
src/Internal/OperationData/AuditedOperationOutcomeQuery.php
src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php
src/Internal/OperationData/OperationJournalReadExecutionSource.php
src/Internal/OperationData/OperationOutcomeReadExecutionSource.php
src/Internal/OperationData/DefaultOperationJournalQuery.php
src/Internal/OperationData/DefaultOperationOutcomeQuery.php
src/Internal/OperationData/PostgreSqlTenantScopedCanonicalJournalReader.php
src/Internal/OperationData/PostgreSqlTenantScopedOutcomeReader.php
src/Transport/PostgreSql/PostgreSqlOutcomeCodec.php
tests/Internal/OperationData/AuditedOperationDataQueryTest.php
tests/Internal/OperationData/OperationDataAuditTenantAuthorizerTest.php
tests/Internal/OperationData/OperationDataReadExecutionSourceTest.php
tests/Architecture/AuditedOperationDataArchitectureTest.php
tests/Architecture/AuditOperationReadArchitectureTest.php
tests/Transport/PostgreSql/PostgreSqlAuditedOperationDataQueryTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
phpunit.xml
```

Existing query/reader edits are only SensitiveParameter additions and formatting:
18/7/7/11 parameters as spec111 fixes, plus exactly 3 decode() parameters in
PostgreSqlOutcomeCodec (46 total). No SQL or algorithm change. Accepted D2,
D2S, Catalog/Store, current runtime bindings, public APIs, migrations, dependencies,
baselines, other tests and public documentation remain exact. The original 18
adapter paths are frozen; the 19th path only sets the PHPUnit memory budget.
A needed wider
path or contract is returned to Root before editing.

## Required Fixtures

- Both resources: constructor no I/O; one requested before subject/authorization;
  Found/accessed, denied/missing/empty/null/unavailable; matching exception's five
  codes and object identity after successful unavailable. Unexpected throwable
  maps to storage, and unreachable unexpected-result branch has a scoped oracle.
- Journal generator fully consumes before terminal; a later iteration failure
  releases no prefix. Audit drafts contain fixed resource purpose, opaque target,
  one request identity and no application purpose, Journal or Outcome payload.
- Null/global, matching and different tenant type/ID, alternating callers on one
  adapter, denied and missing cases. A deliberately wrong-scope subject plus
  Allow authorizer proves the mandatory guard itself: no authorization or blob
  access. PostgreSQL foreign ciphertext produces zero key/decode calls.
- Entry/begin/read/terminal idle checks; requested failure does zero read/terminal;
  terminal failure supersedes Found, Unavailable and every pending safe code.
  No alternate terminal/retry, rollback or transaction repair. Poststate failure
  and commit-then-throw retain only the actual committed Audit prefix.
- Two-Connection PostgreSQL visibility proves requested before read and terminal
  before return for both resources, using real required Store/protection/session
  and actual tenant SQL. Two write roots, no claimed Status read snapshot. The
  integration owns isolated test schemas and cleans only those schemas.
- Generate-once server UUIDs on reused per-resource sources; bad/throwing generator
  fails safely. Execution UUID, request nonce and Store IDs are distinct owners.
- Snapshot/debug/serialization/fixed failure safety, all resource codes and
  inherited Framework reader frames with zend.exception_ignore_args=0, restored
  afterward. Annotate fake source frames where needed; prove actual Framework
  frames rather than hiding canaries in an empty mock object.
- Scoped AST/name-resolution controls for foreign callers, fixed purpose,
  mandatory guard, early release, direct writer/session-open, terminal retry,
  state/parameter protection and annotation-only algorithms. Keep fixture maps
  method-local and bounded; do not duplicate the entire old AST oracle or build
  a new parser/control-flow engine. Runtime tests own executable branch behavior.
- Required inventory 95 unique paths with prior order preserved and 45 controls.

## Acceptance Criteria

- [x] Most-specific contract fixes trusted execution, tenant ordering, query
  result/failure mapping, transaction guards and sensitive-state behavior.
- [x] Bounded implementation preserves public signatures and current wiring.
- [x] Required positive/negative, transaction, privacy and package evidence pass.
- [x] Root and affected specialist reviews pass; Report/STATE record remaining
  parent work and the exact local delivery boundary.

## Verification Plan

Evidence: /tmp/blackops-d2j-20260914. baseline.json binds 3163 tracked/untracked
paths and HEAD 8106c3556248dca1f9719b9d55cf1f647255a422; the release tuple and
prior dirty status are captured separately. Record each command's literal argv,
input/lock/config hashes, runtime/image, start/elapsed/exit/counts and log.

Preparation uses source inspection and isolated read-only trace probes; no full
PHP/website suite. Runtime iteration (Root, after its worker freezes) is:

```bash
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Internal/OperationData
```

Architecture worker runs, after its files settle:

```bash
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
```

Root runs the changed architecture and PostgreSQL tests once for review feedback:

```bash
docker compose run --rm -T app php vendor/bin/phpunit tests/Architecture/AuditedOperationDataArchitectureTest.php tests/Architecture/AuditOperationReadArchitectureTest.php tests/Transport/PostgreSql/PostgreSqlAuditedOperationDataQueryTest.php
```

This is a tenant/security/transaction change with inherited exception frames, so
one full PHPUnit run is required on the reviewed frozen source. Root Final:

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d2j-full-junit.xml
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Use the current Compose PHP image and the user-authorized 512MiB PHPUnit
configuration below; do not use an unlimited budget or relax baselines and
quality gates. Full PHPUnit covers same-input unit/integration/
architecture/regression subsets at Final; do not run the subsets again there.
Root additionally verifies allowed-path/annotation-only diffs, unactivated
runtime, unchanged public/SQL/Catalog/migration inputs and management links.
Reuse accepted D2 golden/Store and Composer/schema/dependency consumer evidence
because those inputs/contracts remain exact. No new wire or configuration exists.
The changed export script has dedicated inventory positive/negative fixtures;
source acceptance here does not claim an uncommitted Git/Composer export.

Local implementation acceptance and delivery remain separate. No commit/push/
tag/CI/export/deployment is selected by this Packet. Selected later delivery
must include clean source and same-SHA package/CI/public proof; this does not
waive the parent/release gates. Report that remaining work explicitly.

## User-requested PHPUnit Memory Amendment

The initial 128MiB full run stopped after 960.552s at the existing protected-body
cap fixture, with no preceding assertion failure and an empty JUnit artifact.
On 2026-09-14 the user asked to raise the memory limit. This supersedes this
Task's initial 128MiB constraint; retaining that constraint is not a Framework
runtime requirement. The failed run remains Failed and is not accepted evidence.

The Runtime worker may add exactly a php/ini memory_limit=512M setting to the
root phpunit.xml. Keep its suite, bootstrap and other options exact. Existing
local/CI entrypoints discover this same configuration; do not duplicate settings
in workflows, Compose, Dockerfiles or runtime configuration. Do not change
existing large-fixture bodies, values/assertions or process-isolation attributes.
Root records the finite default and future budget-change procedure in spec109.

Before dispatch, independent review approves only this configuration amendment.
Root verifies the exact XML delta and installed schema, then runs the two existing
Audit protection classes with full-suite discovery and a temporary logging-only
bootstrap. That observer requires the original bootstrap and records parent/
isolated-child memory_limit without altering it. Both must report 512M. The
previously failing cap fixture and existing isolated cases must pass unchanged.
Then run the original full command with the repository bootstrap/configuration.
No new Product/static/package/site run is selected for this XML-only amendment;
reuse unaffected evidence and bind the final full result to all 19 paths.

## Release Documentation Impact

Framework/Skeleton remain Experimental Stable 1.2.1; roadmap 1.3.0 is unreleased.
The exact unchanged tuple is captured from release-authority.json in the
evidence baseline. Proposed internal capability: audit.operation_data_read_adapters.v1.
No public source, route, CLI, example, configuration or version text changes
are selected. Existing source/Search/LLM fixtures and historical allowlist stay
unchanged; no website build/browser/CI/deployment is implied by this preparation.
Internal architecture documentation impact is decided with the frozen adapter
graph. Runtime activation stays in P23-003D-integration.

## Completion

Report: develop/orchestration/reports/P23-003D2J-operation-data-audit-adapters.md.
After this child: D2X and the other ordinary D adapters/composition, application
Audit authoring, signing/checkpoints, query/export/retention and release gates.
Production delivery of the accepted website changes remains separate.
