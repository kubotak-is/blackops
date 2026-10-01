# P23-003D5R2: Storage Rotation Audit Adapter

Status: Accepted (local) — frozen full512M and independent final review Green; BlackOps 1.3 unreleased

## Goal

Connect D154's four unchanged rotation phase definitions to their actual native
transaction roots, preserving Accepted D5R1 outcomes and native rotation behavior.
This internal opt-in source adapter does not activate ordinary composition or
complete parent D / BlackOps 1.3.

## Scope and Authority

Use specifications99/105/106/109/118, [119](../../spec/119-storage-rotation-audit-adapter.md)
and Decisions154/168/[169](../../decisions/169-storage-rotation-audit-adapter.md).
Accepted C/D1/D5R1 and D4B (C3 identity predicate only) are explicit prerequisites.
Spec119 fixes trusted provenance, run/action/subject identity, strict row tenant,
bounded interruption recovery, succeeded meaning, typed hooks, same-root delivery,
failure/privacy and exact caller/package boundaries. Independent authority review
must be Green before Product edits. The temporary next-brief is not authority.

Root owns this Task/Report, new spec119/D169, the bounded D154 dependency note,
spec index, TODO, STATE and generated state archives. Preserve all unrelated work,
Catalog bytes, public APIs, migrations/schema, CLI/config/composers and C3 logic.
No stage/commit/push/tag/export/release/deploy is inferred from continuing locally.
Do not relax baselines, thresholds or the repository 512M test limit.

Luna workers own only these17 Product/Test/package files after authority Green:

1. src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php
2. src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php
3. src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php
4. src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowExecutor.php
5. src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php
6. src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php
7. src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationPhaseHook.php (new)
8. src/Internal/Audit/Rotation/StorageRotationAuditHook.php (new)
9. src/Internal/Audit/Rotation/StorageRotationAuditWriter.php (new)
10. tests/Internal/Audit/Rotation/StorageRotationAuditHookTest.php (new)
11. tests/Internal/Audit/Rotation/StorageRotationAuditWriterTest.php (new)
12. tests/Transport/PostgreSql/PostgreSqlAuditedStorageProtectionRotationTest.php (new)
13. tests/Architecture/StorageRotationAuditArchitectureTest.php (new)
14. tests/Architecture/AuditRecordArchitectureTest.php (exact new writer allowances)
15. tests/Architecture/AuditDeliveryArchitectureTest.php (exact writer allowances and hook error-only types)
16. tests/Consumer/framework-package-export.sh (required inventory only)
17. tests/Consumer/framework-package-required-inventory.sh (matching three types/hash/negative fixtures)

No native Query/Context/Select/ColumnExecutor, existing D5R1 test, other adapter,
Catalog/C3, Deptrac config, PHP configuration, lockfile or docs change is allowed.
If another file is needed, return the bounded reason to Root before expansion.

Resume baseline `/tmp/blackops-d5r2-20260929/baseline.json`:3313 Git-visible files,
245 preexisting dirty entries, HEAD8106c3556248dca1f9719b9d55cf1f647255a422.
SHA256:0503e8ce5acb1ee21a58c1bcf6d4e7861046b092272ed0f0d2890cf041846064.
D5R1 frozen candidate3/runtime1700/repository2212 inputs match on resume.
Index is empty and remains unchanged. Published Reel main a419f375 remains delivery
authority; primary stale handoff and older docs are frozen until later integration.

## Team and Ownership

Root owns all authority, Acceptance and serial PHP/Docker/DB/cache/consumer gates.
Astra/max explorer owns read-only native-hook/source reuse investigation; its
output is `/tmp/blackops-d5r2-20260929/source-proposal.md`, no shared mutation.
Astra/max independent reviewer checks authority, transaction/security source and
final evidence read-only. Native/adapter Luna worker owns files1-11; PostgreSQL
evidence Luna worker owns file12 and the completed package files16-17; architecture
Luna worker owns files13-15 after the initial proof review. Ownership was split
explicitly before reassignment; no overlapping edits or scope increase. The three
workers use the frozen signatures and hand back chat plus separate temporary
evidence; Root owns this Report. The independent reviewer is idle while all three
implementation lanes run, respecting the four-agent concurrency limit.
After native handoff, that worker may prepare trace/rollback fixture snippets in
its own temporary artifact for the PostgreSQL proof owner. Only the latter edits
file12; this assistance adds no repository file or shared resource owner.
Workers may read/diff and use shell syntax checks, but do not run PHP/Docker/DB
gates. Root alone owns the new canonical test schema
blackops_rotation_audit and existing serial native schemas/cache. Fixtures reset
faults, close Connections and remove their schema even on failure. Consumer owns
its temporary project/schema and must clean them. No shared resource concurrency.

## Acceptance Criteria

- [x] Most-specific source contract and dependency graph independently Green before Product.
- [x] Exact typed opt-in graph; no current composer/CLI activation or Catalog mutation.
- [x] Four events reflect real persisted native state in the exact same root, including interruption and partial progress.
- [x] Trusted identity/provenance, strict tenancy, opaque references, bounded time/key ownership and safe failures proved.
- [x] Real PostgreSQL rollback/commit-after-effect/admission/append faults and native compatibility passed.
- [x] Frozen full512M, required static/architecture/consumer/package checks and final independent review passed.
- [x] Unrelated changes/index/HEAD/release/docs/artifacts preserved; Release Impact and parent remaining work recorded.

## Verification Plan

Change class: shared native transaction/tenant/security and internal source graph.
Spec119's real PostgreSQL matrix and negative guards are mandatory. Native graph
and C3 must be real; use controlled actual parent::commit then throw and separate
observer reads, not a model asserting rollback from exception alone. Unit tests
pin all event tuples/key inputs/time/provenance/snapshot/privacy and no-event paths.

| Phase | Literal command (Root unless stated) | Re-run trigger |
| --- | --- | --- |
| Iteration | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Rotation tests/Transport/PostgreSql/PostgreSqlAuditedStorageProtectionRotationTest.php tests/Architecture/StorageRotationAuditArchitectureTest.php` | Changed Product/fixtures or missing evidence |
| Iteration | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings tests/Transport/PostgreSql/PostgreSqlStorageProtectionRotationTransactionOutcomeTest.php` | D5R1 behavior concern; full subsumes identical fixture |
| Final | `docker compose run --rm --no-deps -T app mago format --check src tests` | Changed PHP |
| Final | `docker compose run --rm --no-deps -T app mago lint` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app mago lint --verify-baseline` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered` | Changed source graph |
| Final | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d5r2-full-junit.xml` | Frozen runtime/fixture/config change or failed full |
| Final | `bash tests/Consumer/storage-protection-rotation.sh` | Changed native runtime inputs |
| Final | `bash tests/Consumer/framework-package-required-inventory.sh` | Three-type inventory/check changes |
| Iteration/Final | `bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh` (worker or Root) | Shell change |
| Final | `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+\|D[0-9]{3}\|P[0-9]+-[0-9]+\|TODO[.]md:[0-9]+' src tests --glob '*.php'` | Changed PHP |
| Final | `git diff --check` and `node develop/orchestration/state.mjs --check` | Scope/management change |

The full includes D5R1's18 unchanged cases and all3601 predecessor identities,
plus the exact new set. It subsumes same-condition focused/architecture/native
matrix tests. Source-only static results can survive test-only edits when input
maps and analysis selection prove equality. The inventory check is local contract
proof; no HEAD archive/export or remote same-SHA claim is made from a dirty tree.
No Website repetition for unchanged public source/artifacts.

Every command receipt records literal argv, tool/image/environment, input hashes,
elapsed time, exit/log/JUnit and cleanup. Management-only updates do not invalidate
unchanged Product evidence. STATE checks and git diff --check apply to management.

## Release Documentation Impact

Release Authority remains Experimental Stable1.2.1;1.3 is unreleased. Rotation
and audit-trail development capability work does not change public command,
configuration or capability availability. Public Source/routes/Search/LLM and
historical version occurrences are preserved by baseline hashes. Source-specific
architecture is recorded in the new spec; public/internal reader documentation,
owner local preview, same-SHA CI/export and delivery remain integration/release work.
No remote gate or deployment is claimed in this local child.

## Failure and Completion

Stop Product expansion and return evidence if authority cannot meet a native
boundary. Preserve failed-command logs and successful unchanged inputs; no blind
full restart. Report: [D5R2](../reports/P23-003D5R2-storage-rotation-audit-adapter.md).

Remaining parent work: replay/generic Outbox, ordinary composition, E/F/G,
post-F control, hardening, Documentation/local owner preview and release delivery.
This child has no remaining work. Root accepted the fixed17-file candidate after
full3644/7693182 and final evidence review11917940; detailed limits and receipts
are in the Report. No ordinary activation or release action is included.
Next Action: Root investigates observer replay's native phase/transaction
boundaries and prepares its specification/Decision/Task Packet before Product work.
