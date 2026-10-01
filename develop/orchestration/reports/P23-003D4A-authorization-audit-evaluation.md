# P23-003D4A Report

Status: Accepted (local) — D4A only; BlackOps1.3 unreleased

## Summary

D4A implements explicit trusted policy binding and distinct no-policy, allowed
and denied evaluations. Source/security/architecture/package reviews and the one
frozen full run pass; Root accepts this latent foundation locally. D4B separately
owns events, per-path delivery and mutation atomicity. The current Stable1.2.1
runtime and public documentation remain unchanged.

## Changed Files

All exact10 Product/Test files implemented: four types and two units under
Internal/Audit/Authorization, the new AuthorizationAuditArchitectureTest,
the narrow AuditRecordArchitectureTest allowance, and the two package inventory
scripts. Source and focused findings are fixed. Authority/index/checkpoint
ownership remains with Root; prior dirty work is preserved.

## Decisions and Assumptions

Spec114/D163 explicitly split D4A from D4B. Reuse the existing evaluator and
AuditRecordPolicy; no policy ID/version or delivery is inferred from class names.
The source-backed spec06 ordering correction records current Deferred acceptance,
without changing runtime ordering. Canonical metadata trust remains a future
composition obligation, not something a binding registry can manufacture.
The registry rebuilds its validated list by value: PHP array element references
from the caller must not replace an accepted policy binding. A regression test
failed before this correction and passes with the same test bytes afterwards.
Its strict three-field tuple comparison preserves exact matching while meeting
the existing complexity limit; no baseline or suppression changed.

## Commands and Results

- Root resumed from live AGENTS/STATE/Task/Report and Git at
  8106c3556248dca1f9719b9d55cf1f647255a422;129 prior dirty entries preserved.
- At D4A entry, D3 exact11 Product/Test hashes matched its accepted final-candidate
  map. This Task intentionally changes its shared A2 and two package scripts;
  the other eight D3 files remain exact.
- /tmp/blackops-d4-20260916/baseline.json captures3,215 current files;
  SHA25680734b4318f3091890b4689f1da4cfd571c237c1064eb4c33c45ee3dda53c535.
  Four shared-before files have byte copies;421 Website artifacts and release
  authority have separate snapshots. D3 acceptance receipts were copied.
- Existing independent source survey reused; a bounded follow-up maps reusable
  policy/projection values. No duplicate runtime source survey or Product tests.
- Initial Runtime format/unit: run-20260916T024255342060, PASS15/158,
  PHP8.5.7/PHPUnit13.2.2,22MiB; formatter0.930s, unit1.054s.
- Independent source review: source-review.md; required fixtures were incomplete
  and caller-array reference retention required a runtime probe. Source matching
  and no-policy snapshot validation were checked; no acceptance claimed yet.
- Source lint: run-20260916T095528160052, Failed1.163s, registry class complexity17
  above15; existing186 baseline entries unchanged. Later static commands not run.
- New Architecture: run-20260916T143232540123, Failed3/144 with2 assertion failures,
  1.222s. Fix incorrect reflection self handling and PhpParser namespacedName
  property access; require clean baseline and intended negative violations.
- Regression/affected checks: run-20260916T144707973143. Unit Failed19/184,
  0.860s: only the caller-array alias regression fails, confirming registry
  immutability violation. Added missing-principal/Worker/conflict/metadata fixtures
  pass. A2 Failed16/25,995,11.979s: new positive fixture incorrectly expected a
  violation for an allowed type. Relay direct-reader PASS1/24,0.883s.
- Package shell receipt: architecture-shell-receipt.json and architecture-*.log;
  PASS104 ordered unique entries and55 negative controls. Reuse if script hashes
  stay exact; PHP test-only corrections do not require rerunning shell checks.
- The first sandboxed formatter attempt was denied Docker socket access before
  any formatting/test: run-20260916T023803237376. Authorized container execution
  resumed the same command; this was environment failure, not Product failure.
- Final source/static iteration: run-20260916T145235474172. Unit PASS19/184,
  0.976s; A2 PASS16/25,997,12.476s; Relay reader PASS1/24,0.910s.
  Lint PASS1.020s with the existing186 entries unchanged; strict baseline check
  PASS0.980s; error-level analysis PASS1.647s; Deptrac PASS1.775s with6,844 allowed
  and zero violations/uncovered/warnings/errors. The new Architecture class still
  failed3/229 with2 assertions because it banned its own pure validation helper;
  that test-only correction does not invalidate successful source/static checks.
- Final Architecture iteration: run-20260916T145721537084. Its constructor guard
  permits only the exact private validation call, with clean-baseline and intended
  violation assertions for the added direct/dynamic work controls. PASS3/2,371,
  2.034s. Global format PASS1.199s; management-ID scan PASS0.017s (rg exit1 means
  no matches); diff check PASS0.130s. No Product/config change since the preceding
  successful unit/static run; do not repeat those successful checks.
- Source/security follow-up is Green, P1/P2/P3=0. Receipt
  source-followup-review.md, SHA256
  44b7350f73cd543357b0390bb6c44da59228cb54d7aa08f99c6ec55c1687fe83.
  It closes the confirmed caller-reference defect and required fixture gaps,
  verifies the same19 Unit case identities before/after the fix, and matches1,074
  relevant source/config/lock paths to successful execution inputs.
- Independent Architecture review found two bounded guard gaps despite the
  passing focused run: NullsafeMethodCall was not inspected, and the first-class/
  top-level-file checks could miss extra class-like declarations or nested PHP
  files. Initial receipt architecture-review.md has SHA256
  fc514654c72b15bc7c56c1252711ea8fbdcfb12ca38d392e0238647b6e2c194c
  (P1=0/P2=2/P3=0). Luna corrected only the new test: reject nullsafe calls,
  require exactly one class-like declaration per source, recursively count PHP
  files, and prove all three with bounded negatives. A temporary-directory
  regression checks the four-file positive then detects a nested fifth file;
  finally cleanup leaves production sources untouched.
- That correction's intermediate run-20260916T155712690883 passed3/2,376 in2.147s.
  Final run-20260916T160003122720 includes the nested regression and passes4/2,387
  in2.215s; global format PASS1.380s, identifiers PASS0.016s, diff PASS0.147s.
  Source/A2/C3/package inputs remain unchanged; affected independent review closes
  only the new guard. Earlier incomplete oracle success is not final acceptance.
- Final required identifier scan uses the Task's literal canonical expression,
  verified against the captured argv. run-20260916T160156875570: PASS0.024s,
  rg exit1/no matches. Earlier runner scan expressions are superseded by this
  receipt; no Product/Test change or repeated PHP/static execution was needed.
- All run directories are under /tmp/blackops-d4-20260916. results.json records
  each literal argv, date, exit, elapsed seconds and log; per-command before/after
  input maps and JUnit preserve exact evidence. Root owns all container commands.
  App image sha256:ab739e1d3bb8d9c7b6696401f7d4821eff72ae870db703feb537015a4fa14f5d;
  PostgreSQL image sha256:4aabea78cf39b90e834caf3af7d602a18565f6fe2508705c8d01aa63245c2e20.
  Shared PHPUnit remains512M. Approval waiting is not command execution time.
- Architecture follow-up is Green, P1/P2/P3=0. Receipt
  architecture-followup-review.md, SHA256
  60d0d95650fe70b017cb6839fc678bcebadb73eafb5c99dd9108c64a768254eb.
  It matches the final guard hash to successful focused before/after maps and
  reconstructs the original test exactly when only reviewed corrections are
  removed. All other nine candidate hashes retain their earlier Green reviews.
- Candidate freeze: frozen-candidate.json, SHA256
  ee04601e61ef7aa63d53d2735b9116c4d8f7460d2d466094f1c90506a73cfc8c,
  exact10 candidate paths and1,965 inputs. The runner verifies candidate, inputs
  and review receipts again before starting full, including after approval wait.
- Full PHPUnit: PASS, exit0,3,446 tests/7,019,563 assertions, zero failures/errors/
  skips. run-20260916T191738447431/full.log; wall timestamps2026-09-16T19:17:38
  to19:35:13+09:00, measured command elapsed992.706s, PHPUnit time990.862s,
  peak131MiB. This was D4A's only full run; no successful subset was rerun after it.
  JUnit SHA256 adee86df3b307eb932708975afcf95c186f087e660a3f8a8333bde78bc41a727.
  Footer is "OK, but there were issues":1 deprecation,2 PHPUnit deprecations,
  13 PHPUnit notices, the same counts as the known D3 baseline. This is not
  warning-free output. Dates use wall clock; command duration uses monotonic time.
  JUnit attributes most elapsed time to three unchanged existing architecture
  oracles: Operation-read mutations622.309s, tenant-status mutations114.293s,
  C3 caller mutations66.580s. These are measured future optimization candidates;
  this Task added no exhaustive mutation loop and changed none of those bodies.
- Candidate evidence assembly: audit-candidate.py PASS, candidate-evidence.json
  SHA256 7fbbdfbd9426735a4f2ff88cb4e17f843938d869a4b9ab7a28f4c5e971fb220d.
  All3,423 D3 case identities preserved; exactly23 added (19 Unit,4 Architecture).
  All1,965 inputs unchanged before/after/current, including12 Composer metadata
  files. Git-visible scope is compared separately to the3,215-file baseline:
  exact10 nonmanagement changes, no unauthorized changes. The helper's initial
  inventory-domain mismatch was corrected before assembly without changing the
  baseline, frozen inputs or Product, and did not restart full.
- Independent precheck confirms193 public-doc source files,421 generated files,
  the release tuple and all six historical references preserved. Prior gates are
  reused on matching inputs, with no duplicated PHP execution by the reviewer.
- Final independent evidence review: Green, P1/P2/P3=0. Receipt
  final-evidence-review.md, SHA256
  e0064bd5fe78a15c17561e20042f5d934790a0b6f944e8d33bbd3d522aca595d;
  exact inputs final-evidence-review-inputs.json, SHA256
  4dc4db1d2f06ac9a8958883ebf657371150c6fa1f4f85d3c303cf059500fdfb2.
  Reviewer independently parsed full JUnit, matched the23 additions to focused
  identities and verified all frozen inputs, scope and reused gates. Root then
  updates only acceptance/status/next-action management metadata.
- Final management closeout is recorded in final-closeout.json: STATE check and
  diff check, unchanged frozen Product/runtime inputs and review receipts, preserved
  authority bodies after status/checklist/handoff normalization, and exact scope.
  Its final file hashes belong to this metadata closeout, not the earlier review.

## Acceptance Criteria

Authority review Green, P1/P2/P3=0. Receipt /tmp/blackops-d4-20260916/authority-review.md,
SHA256 f0bad1cc15336bc06f7f7802608ebe7d139876d6de3fb1a5f497d84e623388e3.
Only status/checklists and the current next-action handoff changed after reviewed
authority hashes; Goal, Scope and verification/acceptance obligations are intact. Source,
Architecture, package and final evidence reviews plus the full gate are Green.
All four Task criteria are satisfied and Root records local Acceptance. This
accepts D4A only, not event delivery, D4/D integration or a public release.

## Release Documentation Impact

Experimental Stable1.2.1 and unreleased1.3 tuple remain unchanged. Proposed Internal
capability: audit.authorization_evaluation.v1, with no public registration. No public
Source/route/version/config/command change. Website/Search/LLM files are preserved;
User explicitly requires a local documentation-site preview and their review
before release. Provide the final candidate on a working local URL and wait for
that review before release. This persists into the later documentation/release
Task. No generation, browser or production check is selected for this latent internal
foundation. Spec114 supplies the implementer contract; runtime usage docs wait
for integration. No commit/push/tag, same-SHA CI/export or deployment was selected.
The unchanged release authority records Framework tag1.2.1 directRef
1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3 / peeledSource
4efee09f13bebedc1639333f79550a36a4e8ca91, and Skeleton tag1.2.1 directRef
7a47e344ecba6193628119f0e95cb23dc5e0be83 / peeledSource
87a025380df8abcd0c514abf50e139dbbc1953c7. Public source/route impact inventory is
empty; old-version occurrence classification and the six-entry historical
allowlist are unchanged. New documentation positive/negative fixtures are Not Run
because no public source, capability, authoring or artifact changed in this Task.

## Remaining Issues

D4A remaining issues: none. D4B and D4 parent remain incomplete. Upper1.3 still needs D5,
ordinary composition, E/F/G, hardening, documentation and release; D-control waits F.

## Suggested Next Action

Root prepares D4B's most-specific authority and exact Packet, obtains independent
authority review, then assigns scoped implementation to Luna. Root retains all
PHP/DB/cache execution and integration. The full run is finished; do not restart
it for management-only closeout. User review of the final local documentation
candidate remains required before release.

The bounded D4B handoff is preserved in projection-recommendation.md and
d4b-root-hook-options.md under the evidence directory. They identify existing
completion/invocation hooks, denial and acceptance roots, and the incomplete
Actor/execution projection contract. These are source-backed options, not D4B
authority. Root must settle append timing, duplicates, rollback survival and
failure routing before a separately reviewed Packet authorizes Product work.

## Subsequent integration boundary

The original local verification and attribution above remain unchanged.
P23-006C owns the subsequent isolated integration in the reviewed D3+D4A+D4B+D5H unit, its separate commits and final convergence checks.
See [the integration Report](P23-006C-accepted-audit-integration.md).
No historical missing intermediate or new same-SHA full is implied.
