# P23-013: Synchronize Published1.2.1 into Development

Status: Accepted — clean final merge/export complete

## Goal and Authorization

The user requests continuing1.3 after the proposed next action: synchronize
published1.2.1 into the development branch, then resume the Status Audit adapter.
This Task owns the synchronization prerequisite only. Necessary local checkpoints
and the ordinary merge are authorized by that direction. No1.3 tag, push,
publication, deployment or unrelated feature implementation is selected.

## Source and Preservation

Development starts at70f9c4733bed8bb0960165cddf8c451b78b96184 on
agent/p23-001-blackops-1-3-feasibility. Published main is
08e4751c62a2a83ba0401f5aee97164984a27f5c, confirmed against origin.
The133 pre-existing dirty paths, full working bytes, binary patch and original
Git index are backed up under /tmp/blackops-p23-013/before before any changes.
Preserve all prior work in a local checkpoint before merging. In particular,
retain D2S1's final delivery receipt and the current workflow/profile changes.

The user's synchronization request supersedes P23-012's preserve-primary-HEAD
boundary for these local integration steps. Published tags remain immutable.
The existing localhost4321 server and published generated artifact stay available.

## Team and Scope

Root owns Git, snapshots, Task/Report, STATE/archive, TODO/spec index, integration
decisions, shared Docker/DB/generation, verification and Acceptance. Root may
commit the saved prior accepted work and perform the ordinary main merge.
Worker does not stage, commit, push or edit STATE. Current repository profiles
remain Astra/max for orchestration/review and Luna/max for implementation.

Site Luna owns only conflict resolution and compatibility corrections in the
exact file inventory assigned by Root after the merge. That inventory is added
to this Task before dispatch. Public guide/website content must match the already
reviewed published main; currentStable and exact refs become1.2.1 while1.3 remains
unreleased. Retain development-only CLI/Audit implementation and its tests.
Consumer guards must distinguish published package behavior from unreleased
development behavior rather than delete either assertion.

The only incoming PHP runtime delta is TelemetryMetrics::VERSION and
TelemetryTracer::VERSION,1.2.0 to1.2.1. The associated two tests update their
expected values. The incoming heartbeat test stabilization is already present
in development and must not be duplicated. No API, SQL, schema, dependency,
runtime activation or diagram geometry change belongs to this synchronization.

## Frozen Worker Inventory

The actual merge began after preservation checkpoint `079dd25`. Site Luna owns
only the following paths; handoff goes to chat and `/tmp/blackops-p23-013/worker`
(Root owns the Report). Resolve public reader inputs to the exact published M bytes.
Integration exception: `product-framing-contract.mjs` and its dedicated test
may reconcile their runtime-source oracle to the exact accepted development
five-option Retention definition. Published docs/artifacts must still require
exactly the four Stable options and reject publication of the fifth option.
Do not accept arbitrary option sets, remove the runtime oracle, or infer release
status from an optional/absent source. Add positive/negative boundary fixtures.
The observed initial ten failures all stop at the stale four-option runtime
oracle; retain these failed logs, re-run only the affected contract and callers.
This deliberately preserves public Stable CLI/retention descriptions; development
CLI/retention implementation remains in src/tests and the unreleased authority.
Reconcile version-baseline assertions explicitly for both boundaries. Do not
remove negative controls or change Product code. No Git, STATE, Docker, dist,
package installation, diagram regeneration or public prose rewrite is assigned.

```text
docs/guide/attributes.md
docs/guide/first-operation.md
docs/guide/journal.md
docs/guide/mvp-status.md
docs/website/README.md
docs/website/pages/index.astro
docs/website/scripts/check-site.mjs
docs/website/scripts/product-framing-contract.mjs
docs/website/scripts/reader-contract.mjs
docs/website/tests/content-pipeline.test.mjs
docs/website/tests/product-framing-contract.test.mjs
docs/website/tests/reader-contract.test.mjs
docs/website/tests/reader-experience.test.mjs
docs/website/tests/release-claim-checker.test.mjs
docs/guide/project-cli.md
docs/guide/retention.md
tests/Consumer/version-baseline.sh
```

Run `bash -n tests/Consumer/version-baseline.sh`, the baseline itself, and the
relevant website unit tests with `--test-isolation=none`. Runtime and full public
artifact evidence are Root-owned. If the exact inventory is insufficient,
return the missing file and reason before expanding it.

## Verification Plan

Root first verifies the exact merged Product delta and preserved1.3 paths.
Reuse P23-012 all-public review/build/browser evidence if public inputs exactly
match published M; recheck only changed integration guards and complete source
claims. If public bytes differ, classify and review before acceptance.
Worker runs syntax and focused tests only for the reconciled JavaScript/shell
guards. Root owns generation and Docker; no shared-output or DB overlap.

For the two PHP version constants, run related Telemetry tests, required global
PHP format/management-ID scan and affected Mago analysis/Deptrac checks once.
Full PHPUnit is not selected for metadata-only integration: the accepted1.3
implementation is preserved and its previous completed evidence remains valid.
Any new semantic change, failed boundary or uncertain impact expands this plan.
The later tenant/security-sensitive D2S implementation has its own full gate.

Run the version baseline, relevant shell syntax and source/management guards.
At a clean final local merge commit, run framework-package-export once and
record that exact SHA. No remote CI/deploy claim is made for this local Task.
Record commands, inputs/environment, results, seconds, logs, reuse and failures
under /tmp/blackops-p23-013 and in the Report. Do not rerun successful identical
checks just to change their owner or append management status.

## Release Documentation Impact

Published tuple: Framework/Skeleton1.2.1. Framework direct
1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3, source4efee09f13bebedc1639333f79550a36a4e8ca91;
Skeleton direct7a47e344ecba6193628119f0e95cb23dc5e0be83,
source87a025380df8abcd0c514abf50e139dbbc1953c7. Roadmap1.3.0 is unreleased.
Capability behavior is unchanged; synchronize published version metadata and
reader-source authority only. Public inventory and historical classifications
reuse P23-012 against identical M inputs. This Task does not deploy the site.

## Acceptance Criteria

- [x] Prior dirty work and original index are recoverable; no accepted1.3 code lost.
- [x] Ordinary merge contains M and the development checkpoint as ancestors.
- [x] Current public source/authority matches1.2.1; unreleased1.3 remains separate.
- [x] Exact runtime delta remains the two version constants; focused checks pass.
- [x] Reconciled development/published guards retain their required boundaries.
- [x] Local integration review and clean final-SHA package export pass.
- [x] Report/STATE identify completed synchronization and the D2S restart point.

## Next Action

Resume revised D2S authority. Synchronization is Accepted at clean merge
7d90753485c8bf9e63578e8ce83cc1762234c083; exact-SHA export receipt is in the Report.
