# P23-003C3 Report: Audit Delivery Orchestration and Key Composition

Status: Review correction authorized — Product correction pending

Updated At: 2026-09-03T17:38:59+09:00

## Summary

Post-Iteration review returned one P1 and two P2 findings. A synthetic 32-byte
key directly confirmed that `HmacAuditOpaqueIdDeriver::var_export()` exposes
the retained raw key. The Architecture oracle also misses consumed
class-string and same-namespace dependencies, and this Report did not retain
the Task-required exact command lines／exit codes／Not Verified boundary.

D152 and the Task now authorize only the opaque-key sensitive-storage source
and direct test, existing Architecture oracles, and Report／STATE evidence
corrections. The Product／Test scope is exact35 and worker scope exact37. The
prior Iteration evidence is Historical after the source/test correction begins.

## Changed Files

This Orchestrator management correction changes:

- `develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md`
- `develop/orchestration/tasks/P23-003C3-audit-delivery-orchestration-and-key-composition.md`
- `develop/orchestration/reports/P23-003C3-audit-delivery-orchestration-and-key-composition.md`
- `develop/STATE.md`

The existing Product／Test candidate is unchanged at this checkpoint. The scope
adds only `HmacAuditOpaqueIdDeriver.php` and its direct test. After this
management correction is committed, the configured worker may change exact35
Product／Test paths plus Report／STATE only.

## Decisions and Assumptions

The raw-key leak is a supported-input secret-exposure defect and therefore P1.
It cannot be corrected inside the prior exact33 scope. The deriver may change
only its private retention representation to `SensitiveParameterValue`; public
API, validation, known-answer derivation, and every other Accepted Production
byte remain fixed. Architecture and Report findings are evidence-only P2s.

## Commands and Results

- Read-only independent reviews returned General P1=0／P2=1, Transaction
  P1=1／P2=0, and Documentation P1=0／P2=1.
- The direct diagnostic command used
  `docker compose run --rm app php -r '<construct deriver; var_export; reject canary>'`.
  It exited `91` because the output contained the synthetic 32-byte key.
- No correction test, Iteration Gate, Complete Final Gate, package export,
  stage, push, release, deploy, or public activation was run in this
  management checkpoint.
- Browser behavior and generated package artifacts are Not Verified.

## Acceptance Criteria

- [x] Review findings and the bounded authority／scope correction are recorded.
- [ ] Opaque-key retention and direct no-leak regression are corrected.
- [ ] Consumed class-string／same-namespace Architecture mutations are closed.
- [ ] Report records exact runnable commands, exits, and Not Verified boundary.
- [ ] Replacement Iteration Gate Green on exact37.
- [ ] Fresh independent implementation reviews after replacement Iteration.
- [ ] Unchanged exact37 refreeze.
- [ ] Complete Final Gate Green.
- [ ] Orchestrator Acceptance.
- [ ] Candidate commit.
- [ ] Clean same-SHA package export.

## Remaining Issues

The management-only correction commit, post-commit staged0／existing-status／
immutable freeze, bounded General／Transaction／Documentation authority review,
worker correction, affected focused checks, replacement Iteration, fresh
implementation reviews, exact37 refreeze, Complete Final Gate, Acceptance,
candidate commit, and clean same-SHA package export remain. C4 and parent
P23-003C Acceptance remain downstream. Stable `1.2.0` remains current and C3
remains unreleased.

## Suggested Next Action

The Orchestrator should commit this management-only scope correction, refreeze
the resulting HEAD／staged0／existing Product status／immutable exact10, and
obtain fresh bounded authority review before worker dispatch. The subsequent
sequence is: focused correction checks; replacement Iteration Gate; fresh
independent implementation reviews; unchanged exact37 refreeze; Complete Final
Gate; Acceptance; candidate commit; clean same-SHA package export.

No Product commit, push, package export, release, deploy, or public activation
was performed.

## Historical Evidence (superseded)

The last complete Iteration Gate passed on exact35
`2ef23a4d74ddad98803c868b5346da22e0ecd8f38ec870a7ae8dbc1960de6907`:
exact30 format; focused PHPUnit `175 tests／1,163,518 assertions` with zero
issues; four Node goldens; Deptrac `1,029` analyzed with all failure counters
zero and `6,315` allowed; version `1.2.0／1.1.0`; exact68 inventory with
call-contract `14` and total mutations `27`; migration `13 + 5 = 18`;
StoragePurpose exact9; and all static／post-freeze guards. The subsequent fresh
reviews found the P1／P2 roots above, so none of this evidence may be reused
after correction.

The latest attempted Iteration Gate started from exact35
`d1d4f71559be275a2b7fbf50629b425706db64809f4f2d25e9cdbcba219ceb61`. Command 1,
exact30 format exit0, focused PHPUnit 175 tests / 1,163,518 assertions with
zero issues, Node4, Deptrac analyzed 1,029 with violations, skipped,
uncovered, warnings, and errors all 0 and 6,315 allowed, version baseline
1.2.0 / 1.1.0, Bash syntax, and exact68 inventory with call-contract 14 and
total mutations 27 all passed. The subsequent static-guard wrapper exited
before final PASS because the Orchestrator supplied the wrong expected file for
the `AuditOpaqueIdKeyProvider` references; the actual paths are
`src/Audit/AuditOpaqueIdKeyProvider.php` and
`src/Internal/Application/ApplicationAuditRuntimeComposer.php`. The entire
attempt is Historical/not reusable under stop-on-first-failure. No file
changed; this was an Orchestrator guard-expectation error, not a Product
failure.

An earlier attempted Iteration Gate started from exact35
`3a00561b711b66e714c330936c0b2e04e994ef6725a5c9c30bc17acb5bace5c5`. Its
final post-freeze wrapper used malformed/truncated immutable individual full
hashes and exited 1 before checks. A corrected read-only post-freeze check
passed, but the attempt is not accepted or reusable because the Task requires
one complete run with stop-on-first-failure. No file changed; this was an
Orchestrator verification-data error, not a Product failure.

Prior successful evidence from that superseded freeze is Historical only:
exact30 format exit0; focused PHPUnit 175 tests / 1,163,518 assertions with
zero issues; Node goldens 4 pass; Deptrac analyzed 1,029 with violations,
skipped, uncovered, warnings, and errors all 0 and 6,315 allowed; version
1.2.0 / 1.1.0; Bash syntax; exact68 inventory with call-contract 14 and total
mutations 27; migration 13 Framework + 5 application = 18; StoragePurpose
exact9; nonactivation, documentation, ID, diff, staged, and corrected
post-freeze guards. None is current evidence for the new Gate freeze.

Earlier exact33/exact35 aggregates, issue-bearing focused results, package
hashes, old dispatch wording, and prior Gate claims are Historical and are not
reused. C3 remains proposed and unreleased.
