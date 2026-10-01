# P23-006B: Read-adapter integration package boundary

Status: Accepted — temporary construction qualified; no primary import
Parent: [P23-006](P23-006-blackops-1-3-release.md)

## Goal and Authority

Construct the two missing package scripts for the adopted D2J+D2X integration
unit. Parent006's reviewed granularity choice and import manifest8a8a2b34 identify
97 ordered unique required paths and48 negative controls, including14 call
controls. Exact historical D2X-final payloads/hashes are unavailable. This work
produces newly reviewed integration code, never historical byte recovery or a
claim of earlier standalone qualification. Specification109 governs verification;
specifications111/112 and D160/D161 retain the read-adapter Product contracts.

Use the exact saved D5H109-path/60-control scripts as inputs. Remove only the
twelve later Authentication/Authorization/Hold inventory paths enumerated in
`/tmp/blackops-1-3-release/integration-import-manifest-draft.json`, their twelve
associated omission mutations and corresponding dispatch/invocation entries.
Preserve relative order of every remaining path, the34 other inventory controls,
all14 call-contract controls, executable validation logic, package exclusions
and other security/failure safeguards. Update counts/messages and the reviewed
export SHA pin to the actual constructed export bytes. If attribution is unclear
or the target requires a broader behavior change, return the exact finding to Root.

## Ownership and Exact Outputs

Root captures immutable inputs under
`/tmp/blackops-package-boundary-20261001/inputs/` and owns this Task/Report,
STATE, gates, independent Review, Acceptance and all later integration/Git work.
One configured Luna Max worker may write only these temporary outputs:

- `/tmp/blackops-package-boundary-20261001/candidate/tests/Consumer/framework-package-export.sh`
- `/tmp/blackops-package-boundary-20261001/candidate/tests/Consumer/framework-package-required-inventory.sh`
- `/tmp/blackops-package-boundary-20261001/construct.py`, if useful
- `/tmp/blackops-package-boundary-20261001/handoff.md`

Do not edit inputs, historical originals, the import manifest, primary repository
Product/tests/package files, O2/F1, configuration, baselines, public Documentation,
Release Authority, Reports/STATE or Git state. No other agent writes these outputs.
No subagent, shared DB/cache/generation, Docker, network, checkout, commit or deploy.
All commands start in the WSL repository root. Bound this construction to one
coherent pass and at most30 minutes active work; report unresolved attribution
honestly rather than relaxing the contract.

## Verification Plan and Acceptance

Worker may run local text/hash/mode comparisons and `bash -n` on each temporary
script; preserve exact commands, elapsed time, input/output SHA256 and diffs in
handoff. Root runs the temporary inventory script once after source inspection:

`bash /tmp/blackops-package-boundary-20261001/candidate/tests/Consumer/framework-package-required-inventory.sh`

Its temporary mutation fixtures are separate from every live package/DB/cache
resource. Root records actual names/counts, all48 rejected mutations and positive
97-path ordered/unique output, hashes/modes before/after, environment/time/log.
Do not execute framework-package-export.sh here: committed-SHA archive/export is
a later integration/release gate. No PHP file changes, so PHP format/lint/full
tests are inapplicable to this temporary shell-only construction.

- [x] Both candidates have recorded SHA256/mode and a complete bounded diff
  from exact D5H inputs; only declared later additions/counts/pin are changed.
- [x] Syntax and actual inventory controls pass with97 ordered unique paths,
  34 inventory and14 call-contract failures rejected; no assertion is bypassed.
- [x] Independent read-only review verifies attribution and retained safeguards;
  Root records disposition, exact accepted payloads and evidence.
- [x] Primary bytes/index/Release/public Source/artifacts remain preserved, and
  Report distinguishes construction from historical recovery and final export.

Root also runs `git diff --check`, STATE check and the existing O2/F1 scope audit
with this Task/Report explicitly admitted as governance only. Input-changing
failures require focused reruns; unchanged successful checks are reused.

## Release Documentation Impact

Experimental Stable1.2.1 remains authoritative; roadmap1.3.0 is unreleased.
No public API/capability/command/configuration/release/example or published route
changes. No Source/Search/LLM generation or CI/export/publish/deploy. This prepares
a temporary package-validation boundary; final whole-source Documentation,
same-SHA CI/export, Owner preview and delivery remain with parent005/006.

## Remaining Work and Next Action

Root accepts the two exact temporary candidates after Green independent review
b7bc014e and preservation check1c3cd47c. This child has no remaining work.
The outputs resolve only the two manifest gaps. Root adopts their explicit
new-construction provenance, then issues the separate exact isolated integration
Task and rehashes all selected payloads at import; all parent release dependencies
remain. No primary import or release candidate freeze occurred in this child.
