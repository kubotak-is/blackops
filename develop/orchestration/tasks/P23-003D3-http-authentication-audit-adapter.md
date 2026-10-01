# P23-003D3: HTTP Authentication Audit Adapter

Status: Accepted locally — reviewed recovery complete; BlackOps1.3 unreleased

## Goal and Scope

Continue authorized1.3 work after locally Accepted D2X. Audit explicit HTTP
authentication results before the existing middleware releases success or401.
Anonymous absence stays event-free. Implement the latent bounded graph in
spec113/D162, including durable Outbox ordering and inherited exception privacy.

Read AGENTS/current profiles, STATE, spec index, spec109, D154 sections7/8,
spec106 D3 and authentication delivery clauses, D147's exact authentication
Catalog/anonymous/provenance rules, then most-specific spec113/D162. No worker
invents mutation, provider, tenant, retry, identity or HTTP failure semantics.

Preserve all prior dirty work and Accepted D2 inputs, shared PHPUnit512M, public
auth contracts, Session sources, Application/generated login/register/logout,
Catalog bytes, dependencies, SQL/migrations, runtime composition and release tuple.
No commit/push/tag, CI, package export or production delivery is selected.

## Team and Ownership

Root owns authority, Task/Report, scope integration, STATE/archive, spec index,
the precise D154 child cross-reference, TODO and all PHP/DB/cache verification.
The read-only Astra source explorer maps Authentication seams; independent
Astra/max reviews authority and implementation/security/transaction boundaries.

Luna/max Runtime owns the three sources and two unit files. Luna/max Architecture
owns the new architecture test, exact C3 test allowances and two package scripts.
The reviewed final recovery adds the one existing A2 reference test to this lane.
Luna/max PostgreSQL owns the one native integration file. Workers return chat
handoffs/hashes and never edit this Report or STATE. Only Root runs PHP/Docker/DB;
Architecture worker may run only the shell checks assigned below. No concurrent
shared schemas/caches, unrelated Consumers, website generation, staging or commits.

## Exact Product/Test Files

```text
src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php
src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php
src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php
tests/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriterTest.php
tests/Internal/Audit/Authentication/AuditedHttpAuthenticatorTest.php
tests/Architecture/AuthenticationAuditArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Transport/PostgreSql/PostgreSqlAuthenticationAuditTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

Exactly11 paths after the explicit final recovery amendment below; no existing
Production code changes. The added A2 test receives one writer's exact eleven
existing record-type allowances and focused negative controls. The C3 oracle receives
only the new graph's exact target/reference/direct-call/constructor allowances.
Existing C3 fixtures and every D2 oracle remain intact. Needed wider edits are
reported to Root before changing files.

## Required Evidence

- Same Anonymous result and zero writer/UUID/Clock/key/Store calls. Success and
  Invalid preserve the original result; successful Actor/Tenant are forwarded by
  existing middleware only after Audit; Invalid retains401/code/no Operation ID.
  Exercise the existing outer ApplicationHttpRequestHandler safe500 boundary for
  provider failure and authenticated Audit outage; no internal code/data disclosure.
  Distinguish Invalid+idle append outage401 from a leaked active transaction that
  the outer lifecycle closes and maps to safe500; prove the next request recovers.
- Known Invalid audit/idle failures remain denial. Success failure never reaches
  handler. Entry, post-delegate and post-writer idle checks; no transaction repair.
  Delegate/writer throwables and invalid result type/shape get the fixed fresh
  error mapping, no false Invalid event and no retry. Commit-then-throw has one
  durable event and no successful handler; repeated/clone calls carry no state.
- Exact Catalog/tuple/source/evidence; successful typed tenant and null global
  partitions; Invalid null principal/tenant; no invalid code or provider inference.
  One UUID/Clock per event, purpose-separated Action/Resource, exact idempotency
  key, invalid UUID and dependency failure; distinct calls have distinct identities.
- Native two-Connection PostgreSQL fixtures use actual encrypted Audit Outbox
  and canonical Stores/coordinator; middleware observes committed durable ingress
  before handler/401. No canonical row until separate relay. Actual failed append
  rolls back without ingress; already committed append may precede a failure.
  Actual existing Session touch stays committed when later Audit fails, explicitly
  proving the bounded result-event contract. Isolated owned schemas only.
- Privacy with real request/header/body/token/actor/tenant/dependency canaries,
  zend.exception_ignore_args=0 restored; fresh null-previous/code0 safe exceptions,
  protected D3 method parameters/snapshots, debug/serialize/JSON/var_export, corrupt
  snapshots and serialize-to-unserialize fail closed, no retained prior request.
  Preserve a positive canary in the unchanged upstream AuthenticationMiddleware
  request frame; distinguish D3-owned/direct-adapter protection from that caller
  limit. Actual outer safe500/class-only logging must still exclude credentials.
- Scoped AST/name-resolution architecture checks with meaningful negative controls:
  no early result, no anonymous write, no code/header/provider inference, fixed
  standalone durable delivery, no Store/relay/transaction control, closed writer
  implementation and graph/no activation, sensitive signatures/snapshots. Use
  installed PhpParser helpers where applicable; avoid huge combinatorial mutations.
- Package inventory97→100 ordered unique entries; preserve all48 existing controls
  and add at least one targeted negative for each new required type (at least51).

## Verification Plan

Root records literal argv, input/config/lock hashes, runtime/image, date/elapsed/
exit/counts and logs under /tmp/blackops-d3-20260915. Same-input successful evidence
is reused. No per-command memory override or lint baseline relaxation.

Focused feedback, after Runtime freeze:

```bash
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Internal/Audit/Authentication tests/Http/Authentication tests/Internal/Auth/Session/DefaultSessionManagerTest.php
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Architecture/AuthenticationAuditArchitectureTest.php tests/Architecture/AuditDeliveryArchitectureTest.php
docker compose run --rm -T app php vendor/bin/phpunit tests/Transport/PostgreSql/PostgreSqlAuthenticationAuditTest.php
```

If C3 contains slow unrelated mutations, Root may select its new graph/assertion
methods during iteration; final full retains the entire existing oracle. Format
new sources before freezing syntax-sensitive fixtures. After corrections, rerun
only failed/changed/insufficiently evidenced portions.

Architecture worker originally owned the following shell checks. Root reruns
them after the September15 runtime reset because their temporary logs were lost:

```bash
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
```

This new boundary affects credential privacy, tenant attribution and transactions.
Final gates on reviewed frozen Product/Test candidate:

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d3-full-junit.xml
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Full subsumes same-input final subsets. Static gates are reused across test-only
corrections when analysis inputs stay exact. No unrelated website build, native
Consumer or clean-SHA export claim; existing unactivated composition means no
current runtime journey changes. Later delivery retains same-SHA CI/export gates.

### Explicit final recovery amendment

The complete frozen full ran3,423 cases at512M:3,422 passed, one A2 reference
guard failed, zero errors/skips. All1,958 inputs remained unchanged. The failure
is an omitted per-file allowance for DurableAuthenticationAuditEventWriter, not
a Runtime change. Root explicitly adds AuditRecordArchitectureTest.php as the
eleventh allowed path, preserving the original ten candidate hashes and all
Production/config/bootstrap/lock bytes. Luna Architecture owns this one-file fix;
Root owns execution/Report/STATE. No directory-wide or whole-file exemption.
Add negative controls for an unrelated A2 dependency inside the permitted writer
and a permitted writer type in an unlisted sibling path; retain existing controls.

Independent recovery review is Green and identified the direct fixture reader in
AuditRelayArchitectureTest::testAuthorityMapRejectsCandidateSelfBaselineAndSharedWholePathBypass.
For this test-only recovery, execute the complete changed A2 class plus that
reader case and global format/management-ID/diff/STATE checks. Capture both
affected runs as JUnit. Reuse full results only outside those affected cases,
with exact unchanged dependency hashes; replace affected entries with current
PASS results and require the exact same3,423 case identities, including all3,378
prior cases and45 D3 additions. Discovery itself remains unchanged. Do not call
this a full PASS on the final test tree: final evidence is explicitly composed
from the completed full and the dependency-bounded successful recovery.

This amendment was reviewed before the test edit; it replaces a redundant full
rerun after this known test-only correction. Any Product/config/bootstrap change,
additional affected reader, new failure or unresolved dependency broadens checks.
Independent final review must approve the actual delta and evidence composition.
Unchanged source analysis and package checks remain valid. No Acceptance meaning
or security invariant is removed.

```bash
docker compose run --rm --no-deps -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d3-a2-recovery-junit.xml tests/Architecture/AuditRecordArchitectureTest.php
docker compose run --rm --no-deps -T app php vendor/bin/phpunit --filter testAuthorityMapRejectsCandidateSelfBaselineAndSharedWholePathBypass --log-junit .phpunit.cache/d3-relay-recovery-junit.xml tests/Architecture/AuditRelayArchitectureTest.php
```

## Acceptance Criteria

- [x] Independent most-specific authority review Green before implementation.
- [x] Exact bounded graph and required positive/failure/native/privacy evidence pass.
- [x] Root and independent source/security/transaction/evidence reviews Green.
- [x] Final same-candidate checks, Report/STATE and unreleased delivery boundary agree.

## Release Documentation Impact

Framework/Skeleton/site Experimental Stable1.2.1; roadmap1.3.0 unreleased. Exact
tuple/page capability map/historical allowlist saved in release-authority-before.json.
Proposed Internal capability: audit.http_authentication_result_adapter.v1.
No public source/route/version/command/config change. Before/after source and
artifact bytes must stay unchanged; existing historical allowlist6 is preserved.
Source/Search/LLM/browser checks are reused only where their inputs remain exact.
No regeneration or live delivery is claimed. Spec113 describes the new implementer
contract; public/internal runtime usage changes belong to parent activation.

## Preparation and Next Action

The original baseline.json captured3,197 tracked/untracked paths at HEAD
8106c3556248dca1f9719b9d55cf1f647255a422. Accepted D2X's exact9 Product/Test hashes
were checked unchanged before this Task; D3 initially owned10 paths and the
reviewed final recovery adds only the A2 test as path11.
Independent authority review preceded Luna dispatch. The runtime reset removed
the original /tmp logs and snapshots; no lost successful evidence is reused.
resume-baseline.json captures the current preserved checkout, and the three shared
pre-D3 file snapshots were recovered with their recorded exact hashes. After the
three review fixes, iteration selects the new unit and architecture files; the
final full still covers the existing HTTP/Session/C3 cases once. Acceptance and
memory settings are unchanged. Upper1.3 still needs D4/D5,
parent integration, E/F/G, hardening, documentation and release. D-control waits F.
