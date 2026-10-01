# P23-003D3 Report: HTTP Authentication Audit Adapter

Status: Accepted locally — amended recovery gates PASS; BlackOps1.3 unreleased

## Summary

The latent HTTP authentication audit adapter is implemented. Explicit success
waits for one committed encrypted Audit Outbox event. Anonymous absence performs
no Audit work. Explicit Invalid retains the existing denial when append fails
and rolls back idle. Provider failure never becomes a false authentication denial.
Fresh bounded exceptions protect the new adapter's credential-bearing frames.
The existing outer lifecycle still returns safe500 for a leaked transaction.

Root and independent pre-full source/security/transaction reviews are Green.
Final verification covers3,423 unique cases:3,406 successful cases reused from
the complete full run plus17 current affected-case replacements. All3,378 old
cases remain and45 D3 cases were added. The original full had one A2 test failure
and remains recorded as Failed; this report claims amended recovery gates PASS,
not a full PASS on the final test tree. Final independent evidence review is Green (P1/P2/P3=0); Root has accepted
this local candidate. Framework/Skeleton/site remain Stable1.2.1 and
BlackOps1.3 is unreleased; the new graph has no runtime activation.

## Changed Files

Exactly11 Product/Test paths after the reviewed A2 recovery amendment. Runtime5
and PostgreSQL1 were implemented by Luna/max; Architecture5 includes the new
oracle, precise C3 and A2 allowances, and the two package scripts. Root formatted
and ran all PHP/DB/cache gates. Workers did not stage or commit.

| File | Final SHA256 |
| --- | --- |
| `src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php` | `11d896f43b85cbd8179b4f191b10ac00bd9ceb23c13afd8101ee1a178ef3c1ee` |
| `src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php` | `cb74ca696d657ae55b048009f0dc555747d3e5ddf093cee30d7832450992d816` |
| `src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php` | `61feab729f69e08a9cebcfb96cbd2c6adc87a20ae6338dc913027c822ab447ff` |
| `tests/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriterTest.php` | `038ac7c7f6f757d7fd2e8a2e438539abe9628def0d13f1574f25e4f17f14d00c` |
| `tests/Internal/Audit/Authentication/AuditedHttpAuthenticatorTest.php` | `b48bf30cf7416fd4cc6aa910f8e0081e1f00964899b9744cbde380b97e47f4f5` |
| `tests/Architecture/AuthenticationAuditArchitectureTest.php` | `14b3432cd7e070aef5ab85534b1edf4fb72920b6dbb2d60c3c91b6dc6c575dbb` |
| `tests/Architecture/AuditDeliveryArchitectureTest.php` | `7661b66aadf0dd657a5e75caca71b32b134489e5d0d72fc49ba0a2b5f25acafc` |
| `tests/Transport/PostgreSql/PostgreSqlAuthenticationAuditTest.php` | `ebbd00abaea77e7f18fa7c7348aa16b731d748e9e652b5eeef38acd24d166897` |
| `tests/Consumer/framework-package-export.sh` | `c4cf69d411a6238ede841c0e78859686058cf0340a18a06c7bc77f9182e89141` |
| `tests/Consumer/framework-package-required-inventory.sh` | `b98442a1d775db64b3b346211019f1f5c90a1eb66a80ea4b185d262e681d9e1f` |
| `tests/Architecture/AuditRecordArchitectureTest.php` | `924aeb47de222478ec49f1c85f9022a73a9b0e61ad92af189acd4562a9bb5713` |

Management: Task/Report, spec113/D162, spec index, D154 child pointer, TODO and
STATE/archive. Prior tenant documentation, landing, D2 and other dirty work are
preserved. The original ten-file candidate SHA256 remains
`687be333a411bfc1a7085473b73c0efaf6ec21cd98c2b1ee386ba6131409611a`.
final-candidate.json adds the eleventh A2 test; its file SHA256 is
`fada98c1d89b0f0b8216a0b01a6a709dec554856e4feac9ee20dd3929226a5f1`.

## Decisions and Assumptions

- Repository profiles: Astra/max Orchestrator/reviewer, Luna/max implementation.
  No fallback. Root owns integration, shared DB/cache, Report/STATE and Acceptance.
- This is a credential-check result event. An existing Session last_used_at
  mutation may already be committed when Audit fails; no atomicity with that
  earlier mutation is claimed. Application login/register/logout remains separate.
- The typed success Actor/Tenant are intentional record fields. Request/body/token,
  provider inference and Invalid response codes do not enter Audit evidence.
  Execution identity is one fresh server UUIDv7; no Operation/client ID inference.
- Snapshot/remap privacy covers D3-owned frames. The actual unchanged middleware
  caller can still retain its request argument; a positive canary demonstrates
  that limit. Actual outer provider/writer safe500 bodies and class-only logs
  exclude credentials with exception argument capture enabled.
- Mago metric expectations are narrowly documented on explicit guarded branches
  and typed draft construction. No lint baseline, source behavior, dependency,
  transaction owner, public contract or shared512M setting was relaxed.

## Commands and Results

Evidence root: `/tmp/blackops-d3-20260915`. Each run directory contains literal
argv, before/after source/test/config/bootstrap/lock hashes, candidate, image,
start/end times, elapsed/exit and raw logs. Root owned every Product command.
PHP8.5.7 / PHPUnit13.2.2; app image
`sha256:ab739e1d3bb8d9c7b6696401f7d4821eff72ae870db703feb537015a4fa14f5d`;
PostgreSQL image `sha256:4aabea78cf39b90e834caf3af7d602a18565f6fe2508705c8d01aa63245c2e20`.
Root phpunit.xml sets512M without a command override. Database fixtures use owned
schemas and a separate probe Connection; no concurrent fixture execution occurred.
HEAD stayed `8106c3556248dca1f9719b9d55cf1f647255a422` on
`agent/p23-001-blackops-1-3-feasibility`.

| Gate | Result and elapsed | Receipt/log |
| --- | --- | --- |
| Focused D3 units | PASS31/209,0.988s; no new warnings | run-20260915T194921680441/unit.log |
| New architecture | PASS4/4,441,2.363s | same run/architecture.log |
| Native PostgreSQL | PASS10/345,8.122s | run-20260915T192540898671/pg.log |
| Mago lint / strict baseline | PASS0.873s /0.980s; 186 existing baseline issues; baseline unchanged | run-20260915T194921680441/lint.log and lint_baseline.log |
| Mago analyze | PASS1.360s, error/count mode | same run/analyze.log |
| Deptrac | PASS1.595s;6,813 allowed,0 violations/uncovered/warnings/errors | same run/deptrac.log |
| Package syntax / inventory | PASS0.003s /3.064s;100 ordered unique paths,51 controls (37 inventory+14 call-contract) | run-20260915T192540898671/package_syntax.log and package_inventory.log |
| Full PHPUnit | Completed Failed:3,423/6,975,409, one A2 guard failure,0 errors/skips;1,006.785s,131MiB peak | run-20260915T230519879454/full.log and full-junit.xml |
| Final A2 entire class | PASS16/25,897,11.255s | run-20260916T013131824413/a2.log and a2-junit.xml |
| Direct Relay fixture reader | PASS1/24,0.904s | same run/relay_reader.log and relay_reader-junit.xml |
| Final global format / PHP management-ID scan | PASS1.161s /0.025s | same run/format_check.log and identifiers.log |
| Diff / STATE | PASS0.174s /0.034s before final management closeout | same run/diff.log and state.log |

The full command was exactly
`docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d3-full-junit.xml`.
Its complete JUnit SHA256 is
`710795ab90386a53a38d88a72282f6f314e81da405c125fe8c370de27592e69a`.
The literal focused/static/recovery argv and assignment are in the Task and each
results.json; no unrelated Consumer, website build, package export or CI ran.

### Reviewed recovery and exact coverage

The full's sole failure rejected the new writer's legitimate eleven A2 record
references. Before any test edit, Root and the independent reviewer amended the
Task from10 to11 paths and specified dependency-bounded recovery. The A2 test
now allows only that writer path/type set. Its negatives reject an unrelated
fully-qualified Finalizer and a permitted Core draft in an unlisted sibling.
The existing guards and case names remain. The direct Relay reader was included
because it reads this A2 test as its own fixture; this was independently traced.

Full before/after hashes match for all1,958 inputs. The only subsequent input
change is the A2 test; all1,957 other inputs, including every Product file,
bootstrap/config/lock and other test, remain exact. The final17 JUnit entries
replace the corresponding old entries. Their union with3,406 retained successful
cases is exactly3,423, with no missing/duplicate identities or failure/error/skip.
The reconciled assertion count is6,977,034. This is case coverage assembled from
recorded runs, not a synthetic claim that the original full succeeded.

The recovered accepted D2X JUnit has3,378 cases and exact recorded SHA256
`f4fb0f940f95cd2b755afd07950da99326822dbc07fff240c3d6b9be18433f15`.
Every old case is preserved. New45:22 adapter unit,9 writer unit,4 architecture,
10 native PostgreSQL. Discovery counts unique dataset-bearing IDs. Known full
suite diagnostics remain1 deprecation,2 PHPUnit deprecations and13 notices;
no new warning was introduced. final-evidence.json and finish-evidence.py bind
this coverage, scope, inputs and retained Failed full result.

### Corrections and evidence recovery

Before the runtime reset, authority review closed two wording findings and became
Green. The first implementation review found three P2 issues: unprotected remap
parameters, missing native Invalid failure fixtures, and syntactically invalid
architecture negatives. Luna corrected them. Fresh resumed review also corrected
an anonymous-branch oracle/position assertion and the outer provider-failure
fixture. A real original-trace canary now inspects its intended frame directly,
avoiding circular var_export warnings without suppressing warnings.

Older /tmp logs and the whole-tree pre-D3 baseline were lost. They are not cited
as reusable current PASS evidence. Current resume snapshots cover3,222 paths and
421 website artifacts. The three shared pre-D3 files were recovered with their
recorded exact hashes. The accepted old JUnit survived in .phpunit.cache and was
recovered by hash. No whole-tree original baseline reconstruction is claimed.

Fresh failed feedback and its reason are retained in the run directories. The
first full ran exactly once. The initial successful17-case recovery was rerun
only after the two negative references were qualified; no Product/static rerun
was added for that test-only change. A prior unit diagnostic rerun added warning
output because its original details were missing. Total recorded verification
time since resume:1,071.629s; full runs1. Old missing-log totals are unrecoverable.
Source-comment-proof.json verifies the narrow metric comments against the native-
tested source bytes. No unchanged successful final subset followed the final
recovery. Pre-full review and recovery-plan review are saved separately from the
final evidence review.

## Release Documentation Impact

Proposed Internal capability: `audit.http_authentication_result_adapter.v1`.
Release authority remains Experimental Stable1.2.1 for Framework/Skeleton/site;
roadmap1.3.0 unreleased. Exact unchanged refs:

- Framework direct `1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3`, source `4efee09f13bebedc1639333f79550a36a4e8ca91`.
- Skeleton direct `7a47e344ecba6193628119f0e95cb23dc5e0be83`, source `87a025380df8abcd0c514abf50e139dbbc1953c7`.

No public source/route/command/config/version occurrence changed. The historical
allowlist's six entries and all421 existing generated artifacts are unchanged
from the resume snapshot, including Search/LLM artifacts. No regeneration/browser
or positive/negative delivery validation is newly claimed or reused from missing
logs. Public and current internal runtime usage documentation belongs to later
activation; spec113 provides this unactivated implementer contract. No same-SHA
CI/export, commit/push/tag or Production deployment is selected or claimed.

## Acceptance Criteria

All four Task criteria are satisfied. Most-specific authority, implementation,
security/transaction boundaries, exact scope and amended verification are Green.
Independent final evidence review confirms the case union and unchanged inputs,
with unresolved P1/P2/P3=0. Its receipt SHA256 is
`80146b19b30da095eddef809f7c298b269765e866bb9f53a72fb90bb33145fcf`;
independent-composition-check.json contains the independently parsed XML result.
Root accepted the local candidate on 2026-09-16T01:36:40+09:00. The original full failure
is retained. No release or delivery claim follows from this local Acceptance.

## Remaining Issues

D3: no remaining implementation, review or verification issues. Upper1.3 needs D4
Authorization and D5 operational adapters, ordinary composition, Application
authoring, signatures/checkpoints, query/export/retention, hardening, documentation
and release. D-control starts only after Accepted F. Website delivery is separate.

## Suggested Next Action

Prepare D4's most-specific authority and exact Packet after this accepted child. Read-only preparation found that
AuthorizationEvaluator::evaluate() collapses no-policy/allow to null and provides
no trusted policy version/delivery metadata. Inline/Worker evaluation precedes
mutation roots; Deferred acceptance evaluates inside its root. Bind policy
provenance and the per-path transaction/failure matrix before implementation,
and reconcile spec06's Received-order sentence with the current acceptance code.
The temporary survey is d4-source-survey.md; no D4 implementation is claimed.

Final management closeout: STATE check and git diff --check PASS on 2026-09-16T01:39:11+09:00.
STATE is66 lines/3,849 bytes; prior text was archived automatically. Final scope,
all421 artifacts, release tuple and unchanged recovery inputs are recorded in
management-closeout.json. No Product check was repeated for these metadata edits.

## Subsequent integration boundary

The original local verification and attribution above remain unchanged.
P23-006C owns the subsequent isolated integration in the reviewed D3+D4A+D4B+D5H unit, its separate commits and final convergence checks.
See [the integration Report](P23-006C-accepted-audit-integration.md).
No historical missing intermediate or new same-SHA full is implied.
