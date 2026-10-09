# P23-003F3: Audit AnchorIntent State

Status: In Progress — exact39-path Luna implementation; reviewed authority adopted

## Goal and Authority

Implement the complete deterministic current A5 AnchorIntent state prerequisite
under [specification126](../../spec/126-audit-anchor-intent-state.md) and
[D176](../../decisions/176-audit-anchor-intent-state.md), preserving all mandatory
remaining F and parent[006](P23-006-blackops-1-3-release.md) release obligations.
Root authorizes only the39 Product/support paths listed below under this packet.

## Workspace and Ownership

The implementation checkout is
`.phpunit.cache/f2-orchestration/accepted-integration`, branch
`agent/p23-006-accepted-integration`, starting committed HEAD
`f590f3a7b9e14bdb0d7b8e832850b39e6681f01d`. Root captured the clean pre-draft
source/configuration checkpoint in
`.phpunit.cache/f2-orchestration/20261009/remaining-f-preparation-baseline.json`,
SHA256 `2d111f11387c83548498b12911fad551fe18a94db3ecade4108221a145e8beff`.
The primary branch/worktree/index stays mixed and untouched by Product work.
All shell commands run from the primary Repository Root with explicit checkout
paths or runtime mounts; do not silently test the older primary Product source.

Root owns governance, resource lease, gates, Review/Acceptance, STATE and Git.
The configured Luna Max worker receives only the frozen Product/test/guard/
package allowlist and chat handoff ownership. Independent Transaction/Security
review is read-only. Root owns all PHP/Docker/DB gates under the dedicated
execution lease; the worker owns no shared runtime. Repository `.codex` profiles
apply. Product verification is not inferred from Ready status.

## Acceptance and Verification Plan

The frozen ownership, acceptance and command plan are below. All current A5 pure
operations and discriminating negatives are mandatory; the companion binding and
lease-origin proof boundary must be explicit. Actual durable/externally
authenticated behavior remains mandatory F4/F5 work and is not accepted by unit
doubles. No baseline, threshold, dependency or512M budget waiver is permitted.

The frozen plan below names commands, inputs/configuration/runtime, owners,
meaningful PHP/Node byte-comparison fixtures, guard/package controls, independent
Review and final qualification. Do not run speculative gates or reuse F2's full
suite as proof of not-yet-written F3 code. Management draft checks are limited to
links/scope/diff and the Root STATE checkpoint.

## Release Documentation Impact

No public API, route, configuration, command, capability or current-release claim
changes in the planned pure Internal prerequisite. Experimental Stable1.2.1 and
unreleased1.3.0 remain as given by release-authority.json. Exact authority tuple,
retained public Source/Search/LLM input evidence and final same-SHA/delivery
boundary are captured in the issued packet/report. Website/browser checks
are Not Run for this governance preparation and are not implied by it.

Read authority tuple: Framework1.2.1 direct1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3,
source4efee09f13bebedc1639333f79550a36a4e8ca91; Skeleton1.2.1 direct
7a47e344ecba6193628119f0e95cb23dc5e0be83, source87a025380df8abcd0c514abf50e139dbbc1953c7.
Capability IDs, public Source/routes and old-version classifications are unchanged;
the new internal note describes an unreleased primitive. Actual before/after maps
and retained artifact applicability are still required before implementation and
at closure; a website build is not implied by this no-impact classification.

## Remaining Work and Next Action

Implement F3, run its assigned gates, obtain independent implementation and
Documentation Review, then accept and commit it. Continue concrete online
authority/key and external control-policy/provenance contracts for F5 integration.
All F4/F5/F6, ordinary D/E, D-control/G,004A,005 and006 publication/delivery
requirements remain open. Report:[P23-003F3](../reports/P23-003F3-audit-anchor-intent-state.md).

## Exact issued ownership

The following39 Product/support paths are the complete issued worker allowlist.
The worker owns their implementation and chat handoff only. Root owns this Task,
Report/specification/Decision, index/TODO/STATE, runtime receipts and all Git.
Independent authority review r2 is Green and Root's Ready amendment issues this
list. No directory wildcard grants further helpers.

New Product paths, exactly the21 filenames in specification126 §7:

```text
src/Internal/Audit/Anchor/AuditAnchorState.php
src/Internal/Audit/Anchor/AuditAnchorFailure.php
src/Internal/Audit/Anchor/AuditAnchorException.php
src/Internal/Audit/Anchor/AuditAnchorConfiguration.php
src/Internal/Audit/Anchor/AuditAnchorIntent.php
src/Internal/Audit/Anchor/AuditAnchorIntentShape.php
src/Internal/Audit/Anchor/AuditAnchorPreparedShape.php
src/Internal/Audit/Anchor/AuditAnchorReceiptShape.php
src/Internal/Audit/Anchor/AuditAnchorCanonical.php
src/Internal/Audit/Anchor/AuditAnchorTime.php
src/Internal/Audit/Anchor/AuditAnchorBinding.php
src/Internal/Audit/Anchor/AuditAnchorExpectation.php
src/Internal/Audit/Anchor/AuditAnchorLeaseOrigin.php
src/Internal/Audit/Anchor/AuditAnchorFrame.php
src/Internal/Audit/Anchor/AuditAnchorTransitionGuard.php
src/Internal/Audit/Anchor/AuditAnchorClaims.php
src/Internal/Audit/Anchor/AuditAnchorPreCallTransition.php
src/Internal/Audit/Anchor/AuditAnchorPostCallTransition.php
src/Internal/Audit/Anchor/AuditAnchorReceiptTransition.php
src/Internal/Audit/Anchor/AuditAnchorExternalOutcome.php
src/Internal/Audit/Anchor/AuditAnchorReceiptBody.php
```

New proof/documentation and the four narrowly amended existing files:

```text
tests/Internal/Audit/Anchor/AuditAnchorConfigurationTest.php
tests/Internal/Audit/Anchor/AuditAnchorIntentTest.php
tests/Internal/Audit/Anchor/AuditAnchorFrameTest.php
tests/Internal/Audit/Anchor/AuditAnchorClaimsTest.php
tests/Internal/Audit/Anchor/AuditAnchorPreCallTransitionTest.php
tests/Internal/Audit/Anchor/AuditAnchorPostCallTransitionTest.php
tests/Internal/Audit/Anchor/AuditAnchorReceiptTransitionTest.php
tests/Internal/Audit/Anchor/AuditAnchorCanonicalTest.php
tests/Internal/Audit/Anchor/AuditAnchorPrivacyTest.php
tests/Internal/Audit/Anchor/AuditAnchorFixture.php
tests/Architecture/AuditAnchorStateArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditArtifactArchitectureTest.php
tests/Fixtures/Audit/audit-anchor-state-golden.mjs
tests/Fixtures/Audit/audit-anchor-state-v1.json
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
docs/internal/audit-anchor-intent-state.md
```

The existing Record/Artifact guards gain only the exact Time/ReceiptBody edges
specified in126, preserving all earlier positives/mutations. Package scripts
gain the exact21 paths, matching order/hash and omission controls; no existing
path/control disappears. No Outbox/F1/F2 Product edit, other architecture guard,
public-API inventory, migration, runtime composition, source publisher/provider,
baseline/configuration/dependency or documentation website path is allowed.
The new internal note explains pure mathematical guarantees and concrete later
storage/origin/current-trust boundaries, with no new Stable/runtime claim.

## Frozen acceptance criteria

- [ ] All39 allowed paths and no foreign Product/configuration/index changes;
 21 new internal types, exact named entry APIs, at most10 methods/type and5
 ordinary arguments; no quality waiver or arbitrary outside caller.
- [ ] Complete current A5 pure matrix and specification126 §9 proof families;
 actual named-claim positives and wrong-purpose/expected hash/token negatives;
 independent Node/PHP state/schedule/receipt-hash bytes, no failed fixture hidden.
- [ ] The38-field public intent and existing receipt wire remain exact. Supplied
 authenticated restore is explicit; pure tests do not claim durable provenance.
 All fixed receipt/chain/claim inputs are detached, immutable and private.
- [ ] Existing Record/Artifact guards retain all controls and only exact new edges;
 new actual source/caller/side-effect/privacy controls and package omission/order
 controls pass without a wildcard or baseline exception.
- [ ] Assigned strict gates and one frozen full PHPUnit run include every existing
 and new test identity under512M. Record inherited warnings honestly; no invented
 result by adding separate test totals.
- [ ] Independent Transaction/Security and narrow internal Documentation reviews
 are Green; Root accepts the implementation and exact evidence.
- [ ] Isolated source commit, clean committed package export and normal remote
 branch preservation; primary mixed work remains preserved. This is child
 delivery, not final release same-SHA CI or publication.

## Concrete verification and shared resources

Change class: new pure Internal security/state algebra plus exact guards/package
inventory. A full final suite is required because the new state/fencing boundary
and existing architecture/package guards affect security qualification. No new SQL
or public runtime path requires a new Consumer journey or browser run; existing
native cases are included by the full suite. No unrelated clean-install Consumer
is repeated solely for this pure non-migration prerequisite.

Root prepares a dedicated ignored evidence directory
`.phpunit.cache/f3-orchestration/20261009`. Before worker issuance it captures
the complete accepted source/mode baseline, primary index/foreign preservation
baseline, current public Source/artifact inventory and runtime/configuration/
vendor/image inputs. Vendor comes from unchanged installed dependencies, without
Composer install/update. The compose override replaces only the app's /app mount
with the accepted checkout; Compose reads the primary environment without printing
its values. Root owns that override, installed dependencies, existing PostgreSQL,
all full-suite schemas/caches/processes and cleanup. No worker starts Docker or
another full/native suite. Root publishes each long command's live handle and log.

All literal commands execute from /home/kubotak/projects/blackops. The app working
directory is /app, mapped to the accepted checkout. The override's exact bytes,
container image ID, Compose/PHP/PHPUnit/Node versions, primary environment hash,
lock/bootstrap/phpunit/Mago/Deptrac/vendor manifests and test fixture hashes are
recorded before qualification. Do not claim the earlier F2 runtime receipt proves
these later inputs without checking them.

Iteration is only the affected proof/format/syntax or measured failing quality
lane. Root runs PHP commands; worker may run the local Node/shell checks below
and reports input/output hashes, literal command, exit/time/log. When the fixture
does not yet exist, the worker may run the same Node script with --write once;
--check must subsequently succeed without changing bytes.

The same allowed .mjs file separates --write/--check fixture utility modes from
the node:test proof mode. The direct test runner must expose exactly eight named
cases: state_lp_domain_and_complete_member_binding,
idempotency_lp_domain_and_utf8_lengths, receipt_preimage_typed_encoding,
receipt_bytes_typed_encoding, public_receipt_hash_domain,
six_retry_schedules_preserve_microseconds, due_expiry_and_calendar_boundaries,
and fixture_bytes_are_exact_and_reproducible. These cases independently compute
the specified bytes and discriminate the domain/member/time boundary mutations;
fixture freshness alone is not the proof suite. Record all eight actual names,
expected8, actual pass/fail/skip counts and fixture hashes before/after; acceptance
requires8pass/0fail/0skip. No Node proof is claimed by this prospective plan.

```bash
node .phpunit.cache/f2-orchestration/accepted-integration/tests/Fixtures/Audit/audit-anchor-state-golden.mjs --check
node --test --test-isolation=none --test-reporter=tap .phpunit.cache/f2-orchestration/accepted-integration/tests/Fixtures/Audit/audit-anchor-state-golden.mjs
bash -n .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-export.sh .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-required-inventory.sh
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app php vendor/bin/phpunit --display-all-issues --cache-directory .phpunit.cache/f3 tests/Internal/Audit/Anchor
```

Root's final command set on the frozen candidate:

```bash
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app composer validate --strict
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app mago format --check src tests
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app mago lint
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app mago analyze
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app php vendor/bin/deptrac analyse --no-cache
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app php vendor/bin/phpunit --display-all-issues --cache-directory .phpunit.cache/f3 --log-junit .phpunit.cache/f3/full.xml
node .phpunit.cache/f2-orchestration/accepted-integration/tests/Fixtures/Audit/audit-anchor-state-golden.mjs --check
node --test --test-isolation=none --test-reporter=tap .phpunit.cache/f2-orchestration/accepted-integration/tests/Fixtures/Audit/audit-anchor-state-golden.mjs
bash -n .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-export.sh .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-required-inventory.sh
bash .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-required-inventory.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' .phpunit.cache/f2-orchestration/accepted-integration/src .phpunit.cache/f2-orchestration/accepted-integration/tests --glob '*.php'
git -C .phpunit.cache/f2-orchestration/accepted-integration diff --check
node .phpunit.cache/f2-orchestration/accepted-integration/develop/orchestration/state.mjs --check
```

Before the final full run, Root enumerates the actual candidate test identities:

```bash
docker compose -f compose.yaml -f .phpunit.cache/f3-orchestration/20261009/compose.override.yaml run --rm -T app php vendor/bin/phpunit --list-tests-xml .phpunit.cache/f3/test-inventory.xml
```

Compare that discovery with the retained F2 JUnit identity set and the actual
final JUnit, preserving every predecessor case and requiring every enumerated
candidate case. Discovery is not a test run; adding separate totals is not proof.

The final full run replaces same-input focused tests; do not repeat focused gates
merely for another reviewer. Final package inventory checks actual script hashes,
all retained/new paths and adversarial omissions. Quality/fixture failures are
corrected within ownership; outside files or helper needs return to Root before
editing. PHP lint/format/static/guard evidence is revisited only when its inputs
or a relevant failure change. Count total execution, full runs and review rounds;
approval/agent waits are recorded separately.

After Root accepts and commits exactly the scoped source, its clean committed
checkout runs this literal child export and preserves its log/cleanup/input SHA:

```bash
bash .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-export.sh
```

Parent006 still owns final release same-SHA CI/export/PR/merge/publication and
Documentation delivery. Export from a dirty or untracked-only tree is not proof.

## Root issuance receipt — 2026-10-09

Independent authority r2 is Green/Ready, SHA256
366342944db7c4e4e582a5f2fcb5e46ee3672f8999f85dea96d26b0c94ad0793;
input record4bbd42e64b32b41f70809367524b03dc2d4142790435c622aa99915d759f2fab.
AR1-01 is closed. Root adopts the reviewed contract and exact39-path scope;
implementation Acceptance remains pending. The substantive A5/API/security
contract is unchanged by the adoption-status amendment.

Runtime preparation5c78118d and baseline65b7c96a are in the dedicated evidence
directory. Effective /app maps to the accepted checkout; all6498 vendor files
match installed dependencies. Actual PHP8.5.7/PHPUnit13.2.2/Mago1.42.0/
Deptrac4.7.1/Composer2.10.3/Node24.18.0/Compose5.3.0 are captured. Root alone
owns Docker/PostgreSQL and PHP/full-suite cache outputs. The worker receives no
shared runtime lease; only the assigned local Node/shell checks are permitted.
Documentation preservation5862b76a contains146 unchanged accepted public Source
files and424 retained primary artifacts. The latter are historical preservation,
not a new accepted-checkout build. F3 changes no Website generation input.
