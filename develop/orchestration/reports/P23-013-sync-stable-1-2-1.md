# P23-013: Published1.2.1 Development Synchronization Report

Status: Source reviewed Green; local merge commit and final-SHA export next.

## Summary

Published main `08e4751c62a2a83ba0401f5aee97164984a27f5c` is resolved into the
1.3 development branch. Existing work was preserved first in checkpoint
`079dd2521d98aec7ff2092ace073759d7d173631` (parent70f9c47) and an external
byte/index backup. All reader-facing source matches published1.2.1. The only
runtime delta from the checkpoint is the two Telemetry version constants.
Accepted development CLI/Audit/tenant source, dependencies, profiles and
architecture tests remain unchanged. Source review is Green; exact final local
merge/export delivery follows, with no1.3 push/tag/deployment.

## Changed Files

Incoming published release metadata, public documentation/source contracts,
Consumer version labels and two Telemetry constants/tests are preserved from M.
Root retained its newer completed P23-012 Task/Report, D2S1 delivery receipt and
current STATE; the STATE tool archived the merge-conflicted prior text.

Luna resolved16 frozen docs paths to exact M, including Stable CLI/Retention
pages. Integration delta from M is limited to the product-framing script/test
and version-baseline: public Retention remains four options; accepted1.3
runtime is checked against exactly five required-value options. Missing/extra/
wrong definitions and fifth-option publication are rejected. No new runtime
behavior, schema, API, dependency, activation or diagram change.

## Decisions and Assumptions

The continuation request authorizes necessary local preservation and ordinary
main merge. This supersedes the prior P23-012 no-primary-commit boundary for
local sync only. Original133 dirty paths (59 untracked), full bytes, binary
patch and original index are at `/tmp/blackops-p23-013/before`;
index SHA25645b3460be4b01aba930b08b017fa1f509f727e9b2f4cb8c0954fb35a094c55fb.
The preservation checkpoint includes136 paths after Task/Report/STATE updates.

`integration-proof.json` confirms unchanged protected paths, exact2-constant
source delta and D2S1 Report SHA256
dc5ed5b6415be054b4b265e0e97a08007f3bfe253e9163cee5941e5b0f2fc955.
`public-inputs.json` compares194 public/build/test authority inputs against M:
192 byte-identical; the two differences are solely the reviewed development
Retention oracle and its test. All reader/route/design/font/diagram inputs match.
Reuse the Accepted P23-012 complete public review, build and browser evidence.
Actual published CI dist remains served on localhost4321, unchanged.

## Commands and Results

Root: repository root, PHP8.5.7 / PHPUnit13.2.2 / Mago1.42.0 / Deptrac4.7.1,
Docker image sha256:ab739e1d3bb8d9c7b6696401f7d4821eff72ae870db703feb537015a4fa14f5d.
PHP checks use no-deps because this selected set needs no DB. Source/test/config/
lock hashes and timestamped literal argv are under `quality-authorized/`.
Website checks use the existing installed toolchain and published CI artifact.

| Command | Result / exit | Seconds | Log |
| --- | --- | ---: | --- |
| `docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Internal/Telemetry --display-phpunit-deprecations --display-notices` | Passed / 0 | 1.558 | `/tmp/blackops-p23-013/quality-authorized/telemetry.log` |
| `docker compose run --rm --no-deps -T app mago format --check src tests` | Passed / 0 | 1.19 | `/tmp/blackops-p23-013/quality-authorized/format.log` |
| `docker compose run --rm --no-deps -T app mago lint` | Passed / 0 | 0.915 | `/tmp/blackops-p23-013/quality-authorized/lint.log` |
| `docker compose run --rm --no-deps -T app mago lint --verify-baseline` | Passed / 0 | 1.041 | `/tmp/blackops-p23-013/quality-authorized/baseline.log` |
| `docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count` | Passed / 0 | 1.484 | `/tmp/blackops-p23-013/quality-authorized/analyze.log` |
| `docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered` | Passed / 0 | 1.695 | `/tmp/blackops-p23-013/quality-authorized/deptrac.log` |
| `node docs/website/scripts/check-content.mjs` | Passed / 0 | 3.026 | `/tmp/blackops-p23-013/site-final/content.log` |
| `node docs/website/scripts/check-artifact.mjs` | Passed / 0 | 3.836 | `/tmp/blackops-p23-013/site-final/artifact.log` |
| `node docs/website/scripts/check-site.mjs` | Passed / 0 | 16.435 | `/tmp/blackops-p23-013/site-final/site.log` |

Telemetry:13 tests/96 assertions; existing1 PHPUnit deprecation and7 notices
remain disclosed (not introduced by version sync). Deptrac:1048 files,
6559 allowed,0 violations/uncovered/warnings/errors. PHP format and the required
management-ID scan pass. `management-id-scan.log` is empty (rg exit1).

Worker evidence: `/tmp/blackops-p23-013/worker`. `bash -n` and version-baseline
pass. Website cases: content-pipeline19, reader-contract43, reader-experience45,
release-claim-checker10 and final product-framing11 all pass. The last suite
includes source/artifact runtime boundary positives/negatives. Product-framing
final script SHA e1569bf8f1b10e40f71e1fde9804a832157000e3f0cc7141245682ba2e621270;
final test SHA d2bbcbc58a471733b5df56e0159de0d09dcde396e9c8314cb560c73867e748c8.
Root verified the diff and syntax-valid chained-option mutation correction.
Source/content determinism, artifact and full-site contracts pass against the
unchanged published dist; neither rebuild nor browser duplication is selected.

## Failures and Recovery

- Initial merge-tree on committed70f9 preceded checkpointing accepted local
  site work. Actual merge reported17 conflicts, in `merge-conflicts.json`.
- Initial local merge could not write .git/ORIG_HEAD.lock; authorized rerun
  proceeded. No Product failure or reset/restart occurred.
- Initial Docker call was denied at the socket before PHPUnit started.
  Failed receipt remains in `quality/`; authorized rerun is `quality-authorized/`.
- Initial public-M product-framing suite failed10 cases at the same stale
  four-option runtime oracle. Explicit frozen Task amendment now checks the
  development five-option runtime while retaining four-option public docs.
  A review correction made the missing/extra option fixtures valid PHP chains;
  only this affected suite was repeated. Earlier logs remain preserved.
- STATE check failed on the merge's duplicated footer. Expected-SHA state tool
  archived that exact prior text and wrote valid82-line state; after-check passes.
- Preservation diff whitespace warnings were confined to existing patch data,
  generated SVG and the copied font license; those accepted bytes were preserved.
  Scoped ordinary source diff check passes. The exact STATE conflict snapshot
  deliberately retains its three Git markers; unscoped diff-check reports those
  markers. Exclude only that immutable historical snapshot for ordinary diff
  validation, rather than alter archived evidence.

## Release Documentation Impact

Framework/Skeleton1.2.1 authority exactly matches M, including all tag/source
refs; all1.3 capabilities remain unreleased. No capability behavior changed.
Full reader source/route/old-version classifications and Search/LLM checks reuse
P23-012 with the explicit input match above; new source/artifact/site checks pass.
No remote CI, Documentation delivery or production deployment was selected for
this local integration, and no new delivery is claimed.

## Acceptance Criteria

Preservation, exact runtime/source boundaries, version guards and local review
are Green. Final local merge ancestry, clean-SHA package export and handoff
remain the delivery step; do not describe the Task as complete before it passes.

## Remaining Issues

Local merge commit/export, then D2S authority finalization and independent review.
Its source prerequisite is already Accepted; do not restart D2S1. Broader1.3
adapter/API/signature/query/export/runtime/docs/release work remains.

## Suggested Next Action

Root commits the reviewed ordinary merge, confirms its two ancestors and clean
state, runs the exact-SHA export, and records the receipt. Then freeze the
revised D2S packet and delegate implementation after authority reviews are Green.

## Workflow Feedback

Full PHPUnit runs for this metadata integration:0. Root selected PHP quality
checks total7.883 seconds; website source/artifact/site23.297 seconds.
Approval/agent waiting is excluded. Successful unchanged checks were reused;
repetition was confined to failed or review-corrected product-framing inputs.
