# P23-010: Audit Trail Roadmap and Sensitive Attribute Guide

Status: Accepted — local documentation delivery; not published

## Summary

Added the unpublished 1.3 Audit Trail plan to Releases and linked it from Journal.
Security now compares BlackOps `#[Sensitive]` with PHP `#[SensitiveParameter]`,
explains their independent effects and limits, and shows both on a promoted
Property/Parameter. First Operation and Attributes link to that comparison.

Local reader destinations:

- http://localhost:4321/releases/current-status/#130のaudit-trail計画
- http://localhost:4321/security/#blackopsのsensitiveとphpのsensitiveparameter

## Changed Files

Five primary guide sources: mvp-status.md, journal.md, security.md,
first-operation.md and attributes.md. Existing content-map descriptions remain
accurate; no new route, layout, PHP or release-authority change. P23-011 owns the
independent TOP presentation delta. Root owns this Task/Report, TODO and STATE.

## Decisions and Assumptions

BlackOps has no public SensitiveValue type; its public Property attribute is
Sensitive. It controls the observed projection. PHP SensitiveParameter controls
parameter values in stack traces without changing the function's input value.
The sample uses a synthetic billing reference, not an authentication credential.
PHP's SensitiveParameterValue wrapper is distinguished from both APIs. Neither
attribute encrypts the original variable or blocks explicit logging of it.

Audit Trail remains planned for unreleased 1.3. Journal and limited operational
Purge/Replay/Rotation records remain current. The wording allows Framework-owned
security/control events while requiring explicit business action/resource meaning
from the application. No current capability or delivery date is promised.

## Commands and Results

Root's shared P23-010/P23-011 gate is recorded once in the
[P23-011 Report](P23-011-walkthrough-progress-control.md), including exact input
hashes, commands, runtime, durations, artifact/browser receipts and recovery.
Final combined input SHA256:
`084737ab153dda4a10db8e67e3330b87ec4676005afb53c43538148796db089f`.

- Luna source audit: five pages and 41 local links, exit0 in0.045s. Public type
  name audit exit0 in0.034s. Prose/diff receipts are in `/tmp/blackops-p23-010/`.
- Root PHP comparison probe: exit0 in1.450s. The actual documented constructor
  preserves its input; observed projection masks it; a separate parameter trace
  is wrapped by SensitiveParameterValue. Exact command is recorded in
  `/tmp/blackops-p23-009/sensitive-probe.json`; its inherited checkout/files fields
  are explicitly superseded by `/tmp/blackops-p23-010/sensitive-probe.inputs.json`,
  which binds the actual primary source, snippet, lock and PHP implementation.
  All six relevant implementation files match published 1.2.0 byte-for-byte.
- Final heading/anchor source audit: five pages/five references, exit0 in0.037s;
  `/tmp/blackops-p23-011/guide-anchor-evidence.receipt`. Final guide diff check
  exit0 in0.007s; final hashes in `guide-sha256-final.log` in the same directory.
- Combined typecheck PASS with0 errors/warnings/hints; named guide tests2/2,
  release fixtures10/10, source/artifact release guards and41-page build PASS.
- Source, generated Markdown, HTML, Search and LLM content verified for all five
  routes: `python3 /tmp/blackops-p23-011/verify-guide-artifacts.py`, exit0 in0.012s;
  `/tmp/blackops-p23-011/guide-artifacts.json` includes artifact hashes.
- Five generated cross-page links resolve to the correct heading IDs after URL
  decoding; `/tmp/blackops-p23-011/guide-cross-page-links.json`,0.095s.
- Actual Chromium pages return200 and have no document overflow at390/1440px.
  New Releases/Security sections pass axe with0 violations. Receipt:
  `/tmp/blackops-p23-011/browser/review-finish.json`; browser command exit0 in5.007s.

## Review and Recovery

Read-only Documentation Review found no P1/P2. The final P3 removed an overly
restrictive “Applicationが明示した” from the Audit comparison table; Root verified
that exact correction. The adjacent business-operation explanation stays valid.

The first build rejected manual HTML anchor IDs because Blume validates heading
slugs. Luna removed those manual IDs and used the existing github-slugger heading
anchors in the five guides. The final build and generated links pass; the guard
was not weakened. This changed no reviewed API or prose meaning. A first ad hoc
artifact assertion expected the PHP name in Journal, whose link label is instead
“SecurityのSensitive比較”; correcting that probe produced the25-surface result.

## Release Documentation Impact

Experimental Stable1.2.0 remains Framework direct
`00e8c5875047a3c47acbebfe57f75b0e581d18b9`, peeled
`3332fd1dd0738fc7e79750facd93d49a59054ecf`; Skeleton direct
`fedcfda5f39caf320ad67196e8ced459176cedb1`, peeled
`fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`. Roadmap1.3.0/unreleased.
No Capability ID is added or promoted; framework-core explanatory prose changes.

Affected inventory: Releases/current-status, Journal, Security, First Operation
and Attributes, including their Markdown, Search and LLM representations. Before
and after, published installs retain1.2.0 and historical occurrences retain their
historical roles. Added1.3.0 references explicitly identify an unpublished plan.
Historical allowlist is unchanged. Source/artifact guards and positive/negative
fixtures pass. same-SHA CI, remote delivery and production deploy are Not Run:
publication was not requested. Full PHPUnit/static analysis are not needed for
prose/link changes; the bounded snippet probe supplies the relevant PHP evidence.

## Acceptance Criteria

All six local Task criteria pass. Technical/release/prose review and generated
reader-path checks are closed. Shared verification is reused across the two docs
Tasks; no duplicate build or broad PHP/website suite was added for this Task.

## Remaining Issues / 残り工程

Local documentation: none. Publication and the actual 1.3 Audit implementation
remain separate, unrequested work; this explanation does not accept that feature.

## Suggested Next Action

Review the two new sections through the existing local preview. Retain the
unpublished boundary until the separate implementation and release gates close.
