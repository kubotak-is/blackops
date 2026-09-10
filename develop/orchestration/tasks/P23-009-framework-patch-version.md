# P23-009: Framework 1.2.1 Version Preparation

Status: Accepted — local version preparation; publication not performed

## Goal and Authority

Prepare the next Framework patch version, 1.2.1, following the user's request.
The active development branch contains unreleased 1.3 Audit and CLI work.
Use the verified 1.2 maintenance source instead: origin/main
`5ad87557c08e20c3bcc6d77be83804001ba3eb80`, whose Framework source, resources,
migrations, Composer manifests and Dockerfile match the published 1.2.0 source.
This task prepares version metadata locally; it does not accept or publish a release.
The subsequent question about Audit Trail was answered as a 1.3 roadmap question;
it does not cancel the patch request or activate the unreleased Audit work.

AGENTS and spec109 in the primary workspace govern ownership and verification.
Spec46 Version Policy, spec61 Publication Gate, spec103 Active source contract,
and spec104 Release Authority/Task contract remain the release authorities.
The maintenance checkout may identify its unpublished next version as 1.2.1;
published Framework/Skeleton authority and installation guidance remain 1.2.0.
No published immutable reference or historical release evidence is rewritten.

## Workspace and Ownership

Primary workspace: `/home/kubotak/projects/blackops`.
Maintenance checkout: `/tmp/blackops-framework-1.2.1`.
Local branch: `agent/p23-009-framework-1-2-1`.
Run commands from the primary repository root, selecting the checkout explicitly.
Use the primary workspace's current Astra/max and Luna/max profiles; older
profile values in the maintenance history do not override the user instructions.

Root owns this Packet/Report, primary STATE/TODO, evidence integration, the
reviewable develop/orchestration/artifacts/P23-009-framework-1.2.1.patch and
Acceptance. Luna owns the bounded implementation in the maintenance checkout.
Return implementation and command evidence in chat; Root writes the Report.
Read-only Documentation Reviewer checks the version boundary and changed prose.
Root owns any dependency preparation and container verification. No shared DB
is required for Telemetry tests. Workers must not build the website or modify
the accepted documentation tree or preview in the primary workspace.

Allowed maintenance paths, only where the version inventory proves relevance:

- Dockerfile; CHANGELOG.md
- src/Internal/Telemetry/TelemetryTracer.php and TelemetryMetrics.php
- tests/Internal/Telemetry/TelemetryTracerTest.php and TelemetryMetricsTest.php
- tests/Consumer/version-baseline.sh
- tests/Consumer/quickstart-e2e.sh, auth-generator-fresh.sh, scheduled-operation.sh,
  storage-protection-rotation.sh, frankenphp-worker-mode.sh
- tests/Consumer/framework-update-generators.sh and framework-update-runtime.sh
- tests/Consumer/skeleton-create-project.sh, skeleton-publication.sh,
  skeleton-publication-workflow.sh
- docs/internal/development-setup.md, mvp-e2e.md, production-observability.md,
  skeleton-publication.md, installed-application-status.md
- develop/spec/103-stable-1-2-release-plan.md, only the bounded candidate contract

Do not add a Composer `version` field or dependency changes. The package obtains
its published version from Git tags. Preserve Skeleton's compatible `^1.2`
constraint, public 1.2.0 commands, authority tuples and 1.3 roadmap state.
Do not globally replace 1.2.0; classify candidate, published, historical and
third-party occurrences first. If another file needs a change, return the
specific dependency to Root before extending scope.
No stage, commit, push, tag, release, Skeleton publication or deployment.
Preserve the primary docs work and unrelated D2S1 Report byte-for-byte.

## Acceptance Criteria

- [x] The separate 1.2 maintenance checkout identifies unpublished Framework 1.2.1
  consistently in Composer development metadata and Trace/Metric scope versions.
- [x] Current-source fixtures that simulate Framework package versions agree;
  published/historical install versions and compatible constraints remain valid.
- [x] Changelog/internal notes accurately distinguish preparation from publication.
  The candidate contains no 1.3 implementation, new API, migration or dependency.
- [x] Focused verification passes with input/runtime/log/elapsed evidence.
- [x] Root and read-only Documentation Review close material findings.
- [x] Primary accepted docs, live preview, authority and unrelated changes remain
  intact. Report and rotated STATE identify the separate checkout and remaining
  release steps without claiming a completed release gate.

## Verification Plan

Change class: version constants, development environment and related fixtures.
No algorithm, API, DI, SQL/schema or dependency changes. Full PHPUnit and full
static analysis are unnecessary for this preparation. Full release qualification
and same-SHA CI/export remain prerequisites for an eventual authorized release.
No redundant product tests for management prose. Reuse unchanged successful
checks once their input/environment match; verify actual Node case counts.

Luna: inventory before/after; bash syntax for changed shell files; the maintenance
`tests/Consumer/version-baseline.sh`; git diff --check. Record literal commands,
source/config/lock hashes, exit, runtime, elapsed and logs under
`/tmp/blackops-p23-009/`. Do not execute all runtime consumers merely because a
synthetic version label changes; Root selects a representative Composer journey
after auditing the actual delta.

Root: isolated Composer strict validation and install metadata; focused
`vendor/bin/phpunit tests/Internal/Telemetry`; `mago format --check src tests`;
the AGENTS management-ID PHP scan; a representative local Composer path-package
resolution if mappings change; source-release guard and its existing positive/
negative fixtures if a release claim surface changes. Review all public-source
version occurrences and preserve the source/Search/LLM boundary; unchanged
public artifact inputs do not require a website rebuild or browser rerun.
Record omitted publication-only gates explicitly. Verify all Framework PHP
changes against the base are solely the two version constants and assert
Composer manifests, resources and migrations remain identical to the base.

## Release Documentation Impact

Published tuple: Experimental Stable 1.2.0. Framework direct
`00e8c5875047a3c47acbebfe57f75b0e581d18b9`, peeled
`3332fd1dd0738fc7e79750facd93d49a59054ecf`; Skeleton direct
`fedcfda5f39caf320ad67196e8ced459176cedb1`, peeled
`fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`. Roadmap 1.3.0 unreleased.
No current capability changes; only future framework-core patch metadata.
Public guide/routes/landing/Search/LLM inputs remain unchanged. CHANGELOG
Unreleased and internal developer notes describe candidate preparation only.
No dated release entry or public download link to a nonexistent 1.2.1 tag.
Same-SHA CI/export, delivery, publication and production deployment: Not Run.

## Completion

Report: `develop/orchestration/reports/P23-009-framework-patch-version.md`.
Local version preparation and actual release publication are separate outcomes.
List remaining release qualification/publication steps and a concrete Next Action.
