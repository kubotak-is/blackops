# Independent History tag contract review r1

Status: **Green for adoption of the bounded contract amendment.** No material Finding remains in the six-file candidate. This is an independent authority/security declaration review, not Product, whole-child, native/runtime or release Acceptance.

Reviewer: `history_contract_review`, repository-configured Astra/max. All shell commands started at P=`/home/kubotak/projects/blackops`. C=`.phpunit.cache/f2-orchestration/accepted-integration`; E14=`.phpunit.cache/f5-orchestration/20261014`; K=`E14/tag-contract-candidate`. The assigned candidate pin is `E14/tag-contract-candidate-inputs.json`.

## Summary

The three backed-int enums preserve every original code/name pair and native case identity while closing the actual 50-case versus 20-case lint conflict without a rule change. The exact union, Reader conversion order, finite caller inventory and eager preload obligations remain explicit. Nothing in the amendment promotes parsed history to current authority or grants another floor-held operation.

The six candidate hashes and five pre-existing baseline hashes match Root's pin. The companion receipt records 71 start/end inputs, identical at completion; the four concurrently edited carrier/codec/test paths were deliberately excluded from inspection and evidence. Frozen `AuditHistoryTag.php` was read only as the unchanged 50-case reference. No inherited runtime lease was used.

## Evidence and bounded conclusions

| Question | Independent result and evidence |
| --- | --- |
| Does the conflict exist under the current quality contract? | C's `composer.lock:3163–3173` pins Mago 1.42.0 / `1e843d2e6e95f186022b6752768f12795fec513a`; installed `schema.json:684–687` enables Error/20; `mago.toml` has no enum override. Retained `E13/history-layer-r1-runtime/lint-20261014T161942858631/output.log:137–144` explicitly reports this 50-case enum exceeding 20. This review did not rerun Mago or independently re-fetch the author's upstream Rust source. The local schema, configuration and actual retained diagnostic independently establish the conflict needed for this review. |
| Is the vocabulary exactly preserved? | Parsed the original type-closure §3 literal table independently of the proposal, compared it with frozen `AuditHistoryTag.php` and the supplement's three literal tables. All three have the same 50 code/name pairs; candidate names and codes are each unique. Counts are exactly 15/20/15. The three Reader dispatch arms have exactly those respective code sets; 71 remains absent. Supplement:24–58,80–104. |
| Is runtime nominal typing closed at every affected declaration? | T is expressly the native PHP union `AuditHistoryTag\|AuditHistoryCheckpointTag\|AuditHistoryControlTag`, expanded in NativeGroup's four declarations, Record's four, and GroupCodec's two, plus both NativeGroup and all three Record storage-shape annotations. The existing Record's third native argument and getter remain as corrected by HT; no int, BackedEnum, interface, docblock-only signature or alias facade is admitted. Relevant original references are type-closure:34/42–43, history-map:400 and HT:17–31; the bounded search across 15 primary contract documents found no other explicit tag declaration needing a different decision. Supplement:62–78. |
| Is conversion still admitted at one producer method? | Only Reader.next may convert integers, at exactly three native `from` sites in one fixed match. Complete five-byte prefix, exact known code, uint32 length, per-tag cap/zero/trailer relation, overflow and framing admission dominate conversion; original payload reads follow in chunks no larger than 16384. Unknown codes cannot invoke conversion or read payload. No tryFrom chain, dynamic target, fallback dispatcher or helper is permitted. Supplement:80–104; original type-closure:147–150 and HT:44–56. |
| Are native/group/record identity and payload classes unchanged? | The frozen 31 scalar, five empty, 13 byte and one trailer code sets are disjoint and complete over all 50 codes. Original caps, empty/trailer lengths and NativeGroup's exclusion of all 19 non-scalar cases remain. Record compares `$native->tag() === $tag`; exactly one original-byte decode and the same local tag/payload/group reach fromRead. Capture uses the same local encoded group; Writer.finish remains common Trailer/48 bytes/null. Existing native-tag/scalars/record-tag/record-bytes/record-native consumer counts remain 4/13/12/12/10. Supplement:97–112; HT:31–94. |
| Is the affected scope finite? | Reconstructed the literal affected Product inventory: 23 unique paths, 21 already allowed plus exactly the two new enum paths. All seven guard/test/fixture/consumer owners are already allowed. allowlist changes only editPaths 295→297 and the matching count; 62 references, 35 new PHP tests and 26 existing PHP-test edits are byte-equivalent JSON values. No reference path becomes writable and no namespace grant appears. Supplement:114–143,190–234. |
| Is eager loading still real and outside both floors? | spec130 has only the count 16→18 and two adjacent FQCN additions after AuditHistoryTag. All prior 16 names remain once and in order. Original 15 F1/JCS/validation types, three artifact/fact types, separately constructed Preflight, nonempty Normalizer FORM_C/table warmup, the same two composition callers and the initializer's two declarations remain unchanged. Both new enums must actually resolve; `::class` alone and floor-held resolution remain forbidden. Supplement:128–143; candidate spec130 §9.2. |
| Are chronology and immutable authority preserved? | D179's entire former byte sequence is an exact prefix of the candidate. Only a new entry is appended, explicitly superseding the prior additional count of 16. README adds finite replacement item 11 and updates effective scope/preload counts; its original 25-type historical inventory stays distinguishable from effective 27. All 47 existing manifest entry objects remain exactly equal and ordered. Every retained original's bytes/hash matches; all three JSON-UTF8 envelopes decode to their exact original hashes/lengths. The sole appended immutable entry is the supplement; its hash/length and both integration-file receipts match candidate bytes. |
| Are meaningful verification obligations retained? | The fixed owner mapping requires independent literal 50 `(FQCN,case,code)` expectations, exact signatures/storage/call sites, resolved import positives and discriminating mutations, real Reader/grammar coverage, all 31 native shapes and 19 rejections, foreign-enum runtime rejection and preserved HL-01..04 tests. Existing actual maintenance/installation cold positives must observe both new types before floor; missing/late/empty controls and exact 58 native IDs remain. Both package inventories explicitly gain the new paths. No new test path, dropped predecessor, dynamic expected map or quality waiver is granted. Supplement:190–213; verification correction §5 and test/CI binding §§2–4. |

The new enums are data only. Their families cannot decide checkpoint-versus-control origin or permission: control grammars still consume the required checkpoint/B2 cases. Exact current trust, original F1/F2 bytes, scope/capture/endpoint identity, historical issuer replay, native close/disposal and the three pure F1 call sites retain their existing contracts. No unchanged old finding was reopened.

## Changed Files

Only these reviewer artifacts were written:

- `E14/history-tag-contract-review-r1.md`
- `E14/history-tag-contract-review-r1-inputs.json`

Candidate management files, original authority, Product/test files, STATE, index and branch were not modified. No Git command or commit was performed; Root retains that ownership.

## Decisions and Assumptions

The candidate remains unadopted until Root completes adoption and committed issuance. Green applies to the exact six-file pin, with the ordinary adoption/status/Task/Report bookkeeping still owned by Root. The README's finite precedence rule and the new supplement control the named historical single-enum/type-count statements; older reviews are not relabelled as approving this new representation.

Three is the minimum declaration count for 50 distinct cases with a maximum of 20 per enum. That arithmetic is not a measured complexity result. Exact budgets remain NativeGroup 9/max2, Record 10/max3, GroupCodec ≤8/max2, Reader exactly10, overall complexity15, ordinary arguments5 and PHP512M. A future implementation that cannot meet them must return its concrete conflict; this review authorizes no extra helper, baseline, suppression or threshold change.

The release tuple, public capabilities/commands/wire bytes and unreleased boundary do not change. A bounded search found no literal old tag/preload count in current C/docs/internal matching the recorded pattern. That no-hit result is not all-public Source/Search/LLM qualification; full child documentation/release obligations remain.

## Commands and Results

The companion JSON contains exact read commands, exits and available tool-reported durations, the start/end path/hash/byte records, independent set comparisons, affected paths and test owners. All 39 content/inventory read commands returned 0 except the intentional docs/internal no-hit search, which returned 1 with empty output. One combined response truncated two command durations; those durations are marked unavailable, and bounded later reads supplied the relevant complete sections. No duration or runtime result was inferred.

Four recorded standard-library `python3 -` bookkeeping passes captured pins, checked manifest/envelopes and management diffs, compared code/category/path/preload sets, and re-hashed inputs; all returned 0. Their measured times are in the companion receipt. These are text/hash checks only. Artifact assembly and a final stability recheck wrote only the two assigned reviewer files. The first assembly returned 0 with a Python SyntaxWarning for the literal Markdown `\|` escape; the output retained the intended table text. This report records that tooling diagnostic rather than treating assembly as warning-free. No network, credential, PHP, Node, Mago binary, test, Docker, DB or native operation was used.

Not Run, by assignment: every Product test/static/architecture/cold/native/SQL/full/export command. Nothing in this review is a PASS for those obligations or for the moving four-file HL correction.

## Acceptance Criteria

The assigned review is complete: exact candidate identity, immutable preservation, finite override, 50-pair partition/dispatch, ten affected signatures/storage, same-case and original-payload binding, preload chronology, scope and meaningful gate ownership have all been independently assessed. **No material Finding. Green for contract adoption only.**

## Remaining Issues and Suggested Next Action

残り工程: Root adoption/status/Task/Report integration and its source checkpoint; separate review/qualification of the four-file HL correction; explicitly issued enum-family implementation and source/guard/relevant runtime evidence; actual Reader/cold/native/SQL/full/strict/export gates; the complete F5P/F5 and remaining parent 1.3/release/delivery work. None of these later outcomes is claimed here.

Next Action: Root adopts the six-file contract after its integration review, records this exact independent result, commits the completed management unit, and issues the precise implementation paths. Actual implementation and resource leases remain separate. No additional reviewer action is required for unchanged contract semantics.
