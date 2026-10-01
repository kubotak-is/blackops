# P23-006A: Guard endpoint recovery report

Status: Accepted — exact historical recovery only; parent integration remains open

## Summary

Both missing D5H-final shared guards were recovered exactly from the preserved
D5P endpoints. Root independently confirmed their target hashes and mode0644.
No repository guard, Product or Git input was changed by recovery. Independent
attribution review is Green and Root accepts this bounded recovery. Import and
integration qualification remain separate parent work.

## Changed Files

Only this Report and its Task are new repository files. Worker outputs are the
four exact temporary paths in the Task. Root alone owns management and STATE.

## Decisions and Assumptions

Use exact retained D5H target hashes as the recovery oracle, preserving the
original D5P inputs. Plausible code is not historical byte evidence. Failure to
match returns a bounded attribution finding; later commit granularity requires
an explicit Root decision and independent review. Keep D5P separate from Docs.

## Commands and Results

Root copied the two named D5R2-original guard payloads into
`/tmp/blackops-guard-recovery-20261001/inputs/`, verified their actual SHA256 and
modes, and recorded `inputs.json` against the actual D5H final-candidate manifest.
Inputs receipt SHA2564096ae6b75587d010a9fa1cb1bd90abdbc67b52a8d4203daa78c11fa4f5ba97f;
D5H target manifest SHA256fded91f9bc202bcd5f5bffde206af0a473581a6c30b18d0fb4a48100afee046d.
No PHP/Node/Docker/test/static/package/DB/cache/Git/network command was run for
the initial input preparation.

Worker handoff `/tmp/blackops-guard-recovery-20261001/handoff.md` SHA256
`db3f02f8950e9b389ae085498f2c74550dc9ee0cd202723bab5e53bddf499ce9`
records its literal Python/hash/mode comparison commands and deletion spans.
Deterministic `recover.py` SHA256 is
`5e6db4b36d7f6f1cdfd5f208cd784bac365768d0ec1daa42f38bcb3be0e02b12`.
It writes output only on exact target equality. The bounded attempt completed
within the assigned30-minute window; the reported0.000017913 seconds is tool
wall-time feedback, not a measured process-duration or performance claim.

| Recovered file | Exact SHA256 | Bytes / mode | D5P-to-D5H deletion |
| --- | --- | --- | --- |
| AuditRecordArchitectureTest.php | 1c5cb7f2ed3ea3fafb0ad98eea58193f2fcb91f4ce9fadd724dec38e4c7a3665 | 202298 /0644 | 68 lines |
| AuditDeliveryArchitectureTest.php | b13fb4f18e996ee783fcfc9254d199745dd783d6c9c8e0d20870b524a7c00d92 | 179240 /0644 | 119 lines |

Root's direct Python SHA256/mode/input-original comparison at
2026-10-01T15:02:28+09:00 passed; `root-comparison.json` SHA256 is
`0c21511d3e4bad9c5381a4d15f93704ea58c72458883e87a763b193cc4b1974f`.
It records both recovered outputs, unchanged input originals, actual target
manifest and unchanged HEAD8106c355. It records no import. No PHP/test gate was
run to relabel the historical endpoint. Root assigned independent read-only
attribution review to `recovery_review`.

`python3 /tmp/blackops-o2-f1-20261001/evidence.py scope recovery-completed`
passed in0.314135 tool wall seconds. Receipt SHA256
`c5aaf672c566bd38263380af39011b83e10795416c1762c37732feba6d9f8e7e`
confirms zero foreign bytes/modes and unchanged HEAD/index/Release Authority,
public Source202/artifact424 and O1 fixture. Active O2/F1 edits remain explicitly
allowed primary work and are not credited to recovery. Root `git diff --check`
and `node develop/orchestration/state.mjs --check` also pass.

Independent `independent-review.md` SHA256
`308eb669a4cf4b758e5ee005086902bb37603d417cf5b700870ce2837637e878`
is Green with no severity finding. Its independent complete-entry deletion
reproduces both exact outputs without executing the recovery script; all earlier
authentication/authorization/hold controls and public test-method inventories
remain. It distinguishes semantic deletion spans from generic diff alignment
of identical array suffixes. Read-only Python checks measured0.077766105,
0.019040810 and0.006738188 seconds. The input-capture to Root-comparison window
is1077.808222 seconds, within30 minutes; it is not worker CPU time.

Root read the full review and accepts the two exact payloads/modes. All9 D5H
endpoint payloads are now available using the earlier seven plus these guards.
Earlier standalone D3/D4A/D4B shared snapshots remain missing; no further recovery
is claimed. The new parent integration addendum
`/tmp/blackops-1-3-release/commit-separation-map-recovery-addendum.md`
SHA256 `0ebd14327c5c7977206a586c13a016961f9b85cb616e00971e85ade8344ff4a5`
proposes combined D2J+D2X and D3+D4A+D4B+D5H qualification units, followed by
separate exact D5P, R1, R2 and Accepted O1 units. Independent review/adoption of
that granularity is parent preparation, not a missing recovery criterion.

## Acceptance Criteria

All four recovery criteria pass: bounded exact matching, preservation, Root plus
independent attribution/mode review, and honest disposition/next integration
decision. Root locally Accepts006A. This does not qualify an integration candidate
or create a new historical test result.

## Release Documentation Impact

No public Source/route/API/capability/configuration/Release/Search/LLM change;
temporary historical test text only. No child CI/export or deployment. Stable
authority and all parent005/006 release qualification/delivery checks remain.

## Remaining Issues

No child-local issue remains. No isolated integration checkout or commit exists.
O2/F1 and all other mandatory parent ledger work remain open, including review
of integration granularity, actual import and complete candidate qualification.

## Suggested Next Action

Parent006 closes the bounded granularity review, freezes exact integration
manifests and rehashes the recovered endpoints at later isolated import. Keep
primary mixed work intact and D5P separate from Documentation; continue O2/F1
corrections and the remaining release ledger meanwhile.
