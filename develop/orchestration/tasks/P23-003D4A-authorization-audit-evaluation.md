# P23-003D4A: Authorization Audit Evaluation

Status: Accepted (local) — D4A only; BlackOps1.3 unreleased

## Goal and Scope

Implement spec114/D163's latent typed policy evaluation after Accepted D3.
Explicit binding supplies policy ID/version/delivery; no-policy, allow and deny
remain distinct. D4B owns the later event/delivery and same-root integration.
Read AGENTS/current profiles, STATE, spec index/spec109, D154 sections7/8, D147's
authorization tuples, spec106's canonical policy clause, then spec114/D163.
Independent Astra/max authority review must be Green before Luna Product work.

Preserve prior dirty work, shared PHPUnit512M, D3 accepted graph, existing public
and runtime auth/transaction/composer code, Catalog bytes, dependencies, SQL,
release authority and public documentation/artifacts. No commit/push/tag, CI,
committed package export or deployment is selected.

## Team and Exact Files

Root owns authority/Task/Report/STATE/archive, spec index, D154 pointer, spec06's
one ordering correction, TODO, integration and all PHP/Docker/DB/cache execution.
Read-only Astra review owns authority and independent final source/security/
evidence findings. Luna Runtime owns exactly four Product and two unit files:

```text
src/Internal/Audit/Authorization/AuthorizationAuditPolicyBinding.php
src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php
src/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php
src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php
tests/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistryTest.php
tests/Internal/Audit/Authorization/AuthorizationAuditEvaluatorTest.php
```

Luna Architecture owns exactly these four files:

```text
tests/Architecture/AuthorizationAuditArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

The A2 guard gains only AuthorizationAuditEvaluation's exact AuditRecordPolicy
reference, with real positive and forbidden-type/unlisted-sibling negatives.
No whole-file/directory exemption, lost control or existing fixture relaxation.
Current C3 remains unchanged unless a specific dependency proves an explicitly
reviewed expansion necessary. Workers do not edit management, STATE or others'
files, run PHP/Docker or commit. They return hashes/findings/commands in chat;
Root integrates the Report. Architecture may run its two shell checks once.

## Required Evidence

- Real existing evaluator/resolver: no-policy has three false predicates, null
  policy/rejection and zero container/policy calls. Allow/Unauthorized/Forbidden
  and missing authorization Actor retain semantics/category/code; Worker execution
  Actor cannot become principal. Exactly one policy call per applicable evaluation;
  repeated calls reevaluate. Binding lookup fails before policy evaluation.
- Explicit non-default policy version and both AuditDelivery enum cases; same
  policy ID/version across consistent Operations works. Duplicate definitions/type
  IDs, conflicting ID/version meanings, malformed registry/list/metadata/version,
  missing or mismatched definition/type/policy bindings all fail closed. No default,
  case-folding or rejection-code-to-Audit-reason conversion. No-policy has no
  fabricated policy object. Non-authorization rejection is invalid.
- The same envelope definition must match metadata before delegation. The new
  evaluator never reads payload/context/handler results; the real policy still
  receives its existing full request through the old evaluator. Binding/result
  retain only permitted values, with no OperationMetadata or request graph.
- Backend/container canaries constructed inside failing delegate frames, not
  pre-created exceptions. With zend.exception_ignore_args=0 restored after test,
  direct new wrapper exception is fresh/code0/previous-null/value-free and does
  not retain original policy/request/payload/credential graph in its own frames.
  Preserve an explicit outside-caller argument-retention limitation. Corrupt
  evaluator snapshot and unserialization fail closed; no last-call state retained.
- AST/name-resolved bounded architecture oracle: exact four final Internal types,
  private evaluation constructor, sensitive new wrapper signatures/snapshot,
  closed typed graph, no producer/storage/clock/UUID/transaction/HTTP/payload calls,
  no runtime activation or public marker. Use installed PhpParser and a few
  meaningful syntax-valid alias/FQCN/method-case negative controls, not exhaustive
  combinatorial mutation loops. Existing guards continue passing.
- Required package inventory100→104 ordered unique entries; retain all51 controls
  and add at least one targeted omission control per new type (at least55 total).

## Verification Plan

Root records literal argv, input/config/lock hashes, image/runtime, date, elapsed,
exit/counts and logs in /tmp/blackops-d4-20260916. Iteration runs the two new units,
new architecture class, whole changed A2 class and its known direct Relay reader
as needed. Format runtime before syntax-sensitive fixtures freeze. C3's existing
reference test is a focused compatibility check if dependency changes warrant it.
Workers share no DB/cache. No command-specific memory override or baseline update.

```bash
docker compose run --rm --no-deps -T app mago format <six Runtime files>
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Internal/Audit/Authorization
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Architecture/AuthorizationAuditArchitectureTest.php
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Architecture/AuditRecordArchitectureTest.php
docker compose run --rm --no-deps -T app php vendor/bin/phpunit --filter testAuthorityMapRejectsCandidateSelfBaselineAndSharedWholePathBypass tests/Architecture/AuditRelayArchitectureTest.php
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
```

This is a new security boundary, so final on reviewed frozen Product/Test bytes:

```bash
docker compose run --rm --no-deps -T app mago format --check src tests
docker compose run --rm --no-deps -T app mago lint
docker compose run --rm --no-deps -T app mago lint --verify-baseline
docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d4a-full-junit.xml
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Full covers same-input final subsets once, including unchanged native runtime
authorization/transaction behavior. No new SQL path or additional native Consumer
journey exists in A. Same-input successful checks are reused by reviewers. A
test-only final correction may compose current affected-case success with unchanged
full evidence only after explicit dependency analysis and independent review,
preserving every case identity and the original failed run's status. Product,
config/bootstrap/dependency change or an unbounded failure broadens verification.

## Acceptance Criteria

- [x] Independent most-specific authority review Green before Product work.
- [x] Exact four-type graph, binding/evaluation/privacy and architecture evidence pass.
- [x] Full/security/static/package gates and independent final review Green.
- [x] Report/STATE and D4A-vs-D4/1.3 remaining work agree.

## Release Documentation Impact

Tuple: currentStable Framework/Skeleton/site Experimental Stable1.2.1;
roadmap1.3.0 unreleased, exact release-authority-before.json captured. Proposed
Internal capability audit.authorization_evaluation.v1. No public Source/route/
command/config/version occurrence changes. Preserve six historical references and
421 generated Website files; hash comparison confirms unchanged source/artifact
bytes but does not claim a new browser/build/live check. Spec114 serves implementer
documentation; usage docs wait for the future composition contract.

## Baseline and Next Action

Baseline captures3,215 files and129 dirty entries at
8106c3556248dca1f9719b9d55cf1f647255a422. Four shared-before files have exact byte
copies. D3's accepted evidence is composed3,423 unique cases; its original full
remains Failed with the one subsequently repaired A2 guard. D4A must preserve all
3,423 existing case identities and add its own cases without treating that old
full as Passed. D4A is locally Accepted after the new frozen full and final
independent evidence review. Next: prepare D4B's authority and exact Packet;
its event/root contract and subsequent runtime activation remain separate.
