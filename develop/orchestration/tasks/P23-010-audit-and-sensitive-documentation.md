# P23-010: Audit Trail Roadmap and Sensitive Attribute Guide

Status: Accepted — local documentation delivery; not published

## Goal and Authority

The user asks whether the documentation site explains the planned Audit Trail,
and requests missing documentation plus the distinction between BlackOps
SensitiveValue and PHP SensitiveParameter. Preserve the accepted website design.
The actual public BlackOps attribute is `BlackOps\Core\Attribute\Sensitive`;
there is no public `SensitiveValue` type. Explain the actual API rather than
introducing an alias. PHP's distinct `SensitiveParameterValue` is its trace wrapper.

AGENTS, spec92, spec104 and spec109 govern this documentation-only task.
Use blackops-japanese-docs and its style checklist. Audit scope comes from the
current technical clauses in spec105/106 and the D147–D157 boundaries referenced
there. Their historical progress paragraphs are not current public capabilities.
Public Stable semantics must match release-authority's immutable Framework source.
Root checked PHP's primary manuals on 2026-09-09:
https://www.php.net/manual/ja/class.sensitiveparameter.php and
https://www.php.net/manual/ja/class.sensitiveparametervalue.php.

## Scope and Ownership

All implementation is in the primary workspace `/home/kubotak/projects/blackops`.
Docs Luna alone may modify:

- docs/guide/mvp-status.md: a bounded explicit 1.3 unreleased Audit Trail section
- docs/guide/journal.md: concise distinction and link to that section
- docs/guide/security.md: canonical Sensitive/SensitiveParameter comparison
- docs/guide/first-operation.md and attributes.md: contextual comparison links
- docs/website/content-map.mjs: only the affected descriptions/reader metadata
  if required to keep purpose and generated search summaries accurate

Root owns this Packet/Report, primary STATE/TODO, integration, all generated
website output, build/browser and Acceptance. Existing read-only Documentation
Reviewer checks technical/release/prose accuracy. P23-009 Luna independently
owns the isolated maintenance checkout; do not touch it or its version work.
Do not change PHP, diagram assets, layout/styles/animation, dependency/lock,
release authority, or unrelated D2S1 Report. No new page or sidebar architecture
is required. No production commit, push or deployment is requested.
If an existing guard demands changes outside this scope, return the finding.

## Acceptance Criteria

- [x] Releases clearly identifies Audit Trail as planned for unreleased 1.3,
  explains actor/action/resource/result accountability in plain Japanese, and
  separates it from current Journal/Application Log/Observability responsibilities.
  Do not promise release dates, compliance, automatic domain inference or a
  finished feature; describe intended protection/correlation only as planned.
- [x] Readers can reach the planned explanation from Journal and return to the
  current Journal/Security guidance. Retention/Replay/Rotation's existing limited
  operational audits are not incorrectly described as missing or as full Audit Trail.
- [x] Security compares actual `#[Sensitive]` (Property, BlackOps observed
  projection Omit/Mask/Hash) with PHP `#[SensitiveParameter]` (parameter in stack
  traces). Explain their complementary use; neither encrypts a variable or prevents
  explicit logging of the original value. PHP does not change the value received
  by the function or automatically apply BlackOps projection rules.
- [x] A short valid constructor-promoted Property example uses both attributes
  with the correct imports and explains the two independent effects. Avoid
  credential-in-Operation advice; use an existing non-credential business value.
  Distinguish PHP's SensitiveParameterValue wrapper if the name is mentioned.
  Link the official PHP manual. First Operation and Attributes link the canonical
  comparison rather than duplicating it.
- [x] Source, generated Markdown, Search and LLM artifacts retain the correct
  Stable/current versus unreleased boundary. Changed routes and links render
  correctly in the local preview with the accepted theme and no new overflow.
- [x] Scoped checks and independent documentation review close material findings.

## Verification Plan

Change class: public concept/reference prose and contextual links, no product PHP.
No full PHPUnit, Mago or broad website test suite for this task.
Luna checks prose against source/manual and git diff --check; returns its source
evidence, exact changed-file hashes, decisions and remaining issues in chat.
Do not write tests that merely mirror new sentences and do not build shared dist.

Root runs source release guard, content/reader validation and one website build
(includes diagram/artifact/site checks) on the completed delta. Run existing
release-claim positive/negative fixtures and relevant guide-code/reader tests only
if their inputs or coverage apply. Verify actual named Node case counts with
--test-isolation=none. Record source/config/lock inputs and runtime, command, exit,
elapsed and logs under `/tmp/blackops-p23-010/`. Reuse unchanged P23-008A visual/
motion evidence and P23-009 PHP checks; they are separate scopes.
Check new links/anchors and key content in actual HTML/generated Markdown/Search/
llms-full, plus affected pages at desktop/mobile widths in the local preview.
No broad all-route visual or animation rerun unless a new issue changes scope.
Root may run a bounded PHP snippet to substantiate the documented trace versus
projection behavior using only a synthetic business value.

## Release Documentation Impact

Authority unchanged: Experimental Stable1.2.0, Framework direct00e8c5875047a3c47acbebfe57f75b0e581d18b9,
peeled3332fd1dd0738fc7e79750facd93d49a59054ecf; Skeleton
directfedcfda5f39caf320ad67196e8ced459176cedb1, peeledfa5e8247fc8cf789cf73685e5be59cc498ffb4ce.
Roadmap1.3.0/unreleased. No current Capability ID is added or promoted.
Affected current capability: framework-core documentation; Audit remains roadmap.
Routes follow the existing content-map entries for Releases/current-status,
Journal, Security, First Operation and Attributes. Record before/after version
classification in Report; preserve historical allowlist and published commands.
Source/Search/LLM and positive/negative results are recorded at local closeout.
same-SHA CI/delivery/production deploy: Not Run; publication not requested.

## Completion

Report: `develop/orchestration/reports/P23-010-audit-and-sensitive-documentation.md`.
Root rotates STATE at completion and reports local URLs, remaining work and
Next Action, preserving the parallel Framework patch preparation status.
