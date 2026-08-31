# P23-003B2: Audit PostgreSQL Canonical Store

Status: Accepted — P23-003B2 Audit PostgreSQL Canonical Store / Parent P23-003B Accepted; Candidate Commit and Same-SHA Package Export Green

Updated At: 2026-08-31T23:10:13+09:00

## Current Checkpoint

P23-003B1 is Accepted. Reviewed Product candidate
`c3b75ca0afe6d8c756caa8404b7e95922987c1da` and clean-candidate package export are Green.
Accepted B1 exact33 aggregate is
`bb6ab89a222a72eafa601f588f46177ee443e554c036bcc00575b211961c8847`.

D149 and this packet close the PostgreSQL transaction／schema／append／segment／read contract
that D148 deferred to B2. Earlier preflight and review lanes are Historical. Accepted Product exact26 is
`ecacafc99cbdf4fd2287b7554b88a34f6524a23d92c9adb3a8bf201a2a1d50bb`.
The Complete Final Gate is Green at PHPUnit `2,939/85,080`, Deptrac
`999/0/0/6,079`, four Node goldens, Community Board clean install, package
exact37／mutation guards, and ending freeze. Fresh DB and General Sol closure
separately returned `P1=0/P2=0/P3=0`. The Orchestrator accepts P23-003B2 and
parent P23-003B.

Stable `1.2.0` remains current. BlackOps `1.3` and every Audit capability remain unreleased.
No Application／Container／CLI／HTTP／public Documentation／Audit Outbox activation is authorized.

Reviewed candidate `968afe2b0c4bbff86e0013592d69dc7115cf8b4f` is committed,
and `framework-package-export.sh` is Green on that clean exact SHA. The
management-only closeout changes no package-visible source or required inventory.
No push was performed. Remaining within B2／parent B: none.

Next Action: proceed to P23-003C from the Accepted parent P23-003B boundary.

## Goal

Accepted B1 canonical record／protected-body foundationをconsumeし、次を一つのbounded
PostgreSQL componentとして実装する。

- partition-global sequence／head／trusted time high-water
- required Draftとdurable Canonical logical recordのcanonical append
- same record ID + contentHash idempotency
- one-open-segment and explicit crash-safe segment close／successor
- exact `AuditStoredRecord v1` row persistence
- tenant／partition SQL predicate before hydration／decrypt
- exact one versioned migration

P23-003B2 Acceptanceによりparent P23-003BをAcceptance可能にする。ただしruntime wiring、
required business orchestration、durable Outbox／relay、checkpoint、query、Documentation、Releaseは
後続Taskのままである。

## Model and Workflow

- Orchestrator／Reviewer: repository設定の`gpt-5.6-sol`／`max`
- Production Worker: repository設定の`gpt-5.6-luna`／`max`
- Documentation Reviewer: repository設定の`gpt-5.6-sol`／`xhigh`、read-only
- Workerはpreflight Green後にだけ開始する。
- WorkerはこのTaskのexact Files Allowedだけを変更する。
- Workerはreview前にcommit／stage／pushしない。
- Exact26内の任意のchange後は、以前のGate evidenceを再利用しない。
- Orchestrator Acceptance前にpackage exportを実行しない。これは`git archive HEAD`とlive
  worktreeを比較するため、uncommitted candidateでは構造上Greenにならない。

## Prerequisites

1. Current branch is `agent/p23-001-blackops-1-3-feasibility`.
2. Accepted B1 candidate commit `c3b75ca0afe6d8c756caa8404b7e95922987c1da` is an ancestor.
3. Work starts from clean HEAD `50a4863b87fdb063bdc78b982eb06e03daf88c09` unless a later
   Orchestrator-only packet correction commit is explicitly recorded.
4. Accepted A1 exact48 aggregate remains
   `9e9ba592e5203c17d814c3f0cc8772b2fc95e7ddc75b67df6a5c16c9a7030fed`.
5. Accepted A2 exact28 aggregate remains
   `ba06dd0f1886f45b373c02880abb06d662e11e2f53d177c5b67cca3fd0b376ea`.
6. Accepted B1 exact32 excluding `AuditRecordArchitectureTest.php` remains
   `a653ffb3fdfbf5963a02012e59281cfca94308668533a8db48ec6991e5bec490`.
7. `tests/Architecture/AuditRecordArchitectureTest.php` starts at
   `839cd58b0bcec1840b04e02f16145e622bb06894445c758d0871bb272e9254d2` and is the
   only authorized B1-file delta.
8. Staged path count is zero.

If any prerequisite drifts, stop before Product edit and return a Blocker.

## Source of Truth

Read in this order:

1. `AGENTS.md`
2. `develop/STATE.md`
3. this Task
4. `develop/spec/README.md`
5. `develop/spec/92-documentation-review-agent.md`
6. `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
7. `develop/spec/105-blackops-1-3-capability-contract.md`
8. `develop/spec/106-phase-23-delivery-plan.md`
9. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
10. `develop/decisions/147-audit-catalog-and-safe-projection-boundary.md`
11. `develop/decisions/148-audit-canonical-record-and-protected-store-boundary.md`
12. `develop/decisions/149-audit-postgresql-canonical-store-boundary.md`
13. `develop/orchestration/reports/P23-003B1-audit-canonical-record-foundation.md`
14. `develop/orchestration/reports/P23-003B2-audit-postgresql-store.md`
15. current B1 Source／Tests、DBAL／migration／PostgreSQL patterns

D149がB2 PostgreSQL boundaryのmost-specific authorityである。矛盾時はProductだけで
解決せず、Reportへ記録してOrchestratorへ返す。

## Release Documentation Impact

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.postgresql_canonical_store.v1
publicSourceInventory=[examples/community-board/README.md]
publicRouteInventory=[]
```

Community Board READMEのexact substring
`all 11 Framework and 5 Community Board migrations (16 total)`を
`all 17 migrations (12 Framework + 5 Community Board Application migrations = 17)`へ一度だけ置換し、
clean-install consumerのexact substring `migrations: 16`を`migrations: 17`へ一度だけ置換する。
同READMEのstale exact sentence
`The permanent consumers perform equivalent cleanup automatically. The application and Documentation Website are Local／CI examples; this repository does not publish or host them externally.`を
`The permanent consumers perform equivalent cleanup automatically. BlackOps Board is a Local／CI reference application and is not published or hosted externally. The Documentation Website is published on Cloudflare Pages.`へ一度だけ置換する。
Framework Guide／Websiteは変更しない。Migration／storeがsource treeに存在しても、BlackOps `1.3` Audit
Trailをcurrent capabilityとして記載しない。Stable `1.2.0` public surface、
`StoragePurpose` exact9、new CLI／HTTP route、Application configurationは不変である。
ただしunreleased sourceの既存`database:status`／`database:migrate` migration inventoryは
11から12へ変わり、Community Board permanent journeyは12＋5＝17へ変わる。Published Stable
fixturesはbyte-identicalに維持する。Guide／Website／
Search／LLM source/artifactは変更も再生成もしないため、artifact再生成をGreen evidenceとして
claimしない。

## In Scope

### 1. Exact failure and result values

D149 §2どおり、次をexact実装する。

- `AuditStoreFailure` exact5
- `AuditStoreException` fixed safe message／code／no previous
- `AuditAppendResult`
- `AuditLookupResult`
- `AuditSegmentCloseResult`

All constructors／public methods／enum backing valuesはArchitecture testで固定する。
Result／exceptionはraw protected bytes、logical record、tenant、partition、record ID、hash、
SQL、schema、key IDをdebug／serialize／JSON／var_export／traceへ出さない。
ResultはD149 §2のexact private sensitive storage、getter、`__debugInfo`／`__serialize`／
rejecting `__unserialize`を持つ。Lookup factoryは`fromRecord()`、predicateは`isFound()`であり、
PHP method overloadを使わない。Not-foundはvalue-equivalentでありobject identityを契約にしない。

### 2. Exact PostgreSQL schema

`PostgreSqlAuditSchema`がD149 §4のexact three-table DDLとindex／constraint namesを一元所有する。
Migration `Version20260829000000`はそのstatementsをaddSqlし、downはirreversibleである。

Schema nameはexisting shared `PostgreSqlMigrationSchema`がlowercase ASCII 1..63 bytesとして、
Migration dependency factoryの最初のSQLより前に一度だけ検証する。`PostgreSqlAuditSchema`はその
validatorへdelegateし、Store constructorはraw `string schema`ではなくvalidated Audit Schema objectを
受ける。DDL qualificationとpartition／record advisory keyへ同じ`schemaName()`を使い、64-byte
truncation aliasや別regexを許可しない。

Migration／schemaの両方で次を直接検証する。

- schema identifier quoting／invalid schema fail closed
- exact table／column／index／constraint inventory
- `audit_partition_state` genesis／head／tenant pair／safe integer constraints
- `audit_segments` open／closed／sealed nullability、genesis／successor predecessor、one-open and
  one-incoming-successor unique partial indexes、store-wide `segment_id` unique
- `audit_records` exact stored members、allowed query indexes、BOPD magic／body bounds
- forbidden clear columns absent
- composite identities／FK／recordedAt indexes
- migration count current 11 -> 12
- existing migration order followed by exact new version

`PostgreSqlAuditSchema` public APIはD149 §2のconstructor、schema/table getters、`statements()`
だけである。D149 §4の全named PK／CHECK／FK／UNIQUE／indexとstatement orderを
`pg_constraint`／`pg_indexes`へexact比較し、unexpected nameもfailureにする。

### 3. Hydrator

`PostgreSqlAuditStoredRecordHydrator` constructorはexactly
`AuditPartitionIdDeriver`一つである。`hydrate()` inputはlogical `tenantBinding`を
`tenant_type`／`tenant_id`へ展開したphysical exact14である。Physical exact14 input orderは次である。

```text
schema_version,kind,record_id,partition_id,tenant_type,tenant_id,segment_id,sequence,
previous_record_hash,content_hash,record_hash,recorded_at,event_category,protected_body
```

Hydratorはpairをclosed unionへfoldし、次のconceptual exact13 detached output orderへmapする。

```text
schemaVersion,kind,recordId,partitionId,tenantBinding,segmentId,sequence,
previousRecordHash,contentHash,recordHash,recordedAt,eventCategory,protectedBody
```

- integerはnative intまたはcanonical decimal string `0|[1-9][0-9]*`だけを受け、sign／space／
  leading zero／floatを拒否し、digit length／lexical bound後にcastする。Fieldごとの0／positive／
  JSON-safe boundsはD149に従う。
- byteaはexisting `BlackOps\Transport\PostgreSql\PostgreSqlBytea::string()`だけで
  string／resourceをnormalizeする。Binary NULを保持する。
- tenant physical pairは`null|{type,id}`へ戻す。
- Record、partition-state、segmentの全DB timestamptzはD149 §7のexact
  `to_char(... AT TIME ZONE 'UTC',...)` projectionだけを受け、UTC six-fraction以外、
  infinity、offset wireを拒否する。Nullable `closed_at`だけはSQL nullを許す。
- tenantから導出したpartitionとrow partitionをhydrator内でもexact比較する。
- `AuditStoredRecord::fromArray()`をexactly once呼ぶ。
- missing／extra／wrong type／unsafe integer／partial tenant／bad timestamp／bad binaryは
  `integrity_invalid`へ縮約する。
- row、exception、traceへsecret-bearing bytesを残さない。

All timestamp writesはcanonical UTC six-fraction `ParameterType::STRING`、`protected_body`は
`ParameterType::BINARY`でbindする。BOPDのNULをtext bindingへ渡さない。

`AuditRecordArchitectureTest`のauthorized deltaは、Production
`AuditStoredRecord::fromArray()` callerをBodyCodec＋Hydrator exact2、`protectRecord()`／
`unprotectRecord()` callerをStore exact1ずつへ更新する。Outbox protect／unprotect callerはexact0を
維持する。Allowed B1-type reference directoriesへ`src/Internal/Audit/Storage`を追加し、B2専用
Architecture testがB2 fileごとのexact type dependency mapを固定する。Dynamic call、extra caller、
Storage外参照を拒否し、他のB1 construction inventoryを変更しない。

### 4. Store constructor and transaction boundary

`PostgreSqlAuditRecordStore` constructorはexactly次である。

```text
Connection
PostgreSqlAuditSchema
ClockInterface
BlackOps\Internal\Identifier\Uuidv7Generator
AuditRecordValidator
AuditRecordFinalizer
AuditPartitionIdDeriver
AuditRecordCodec
AuditProtectedBodyCodec
PostgreSqlAuditStoredRecordHydrator
```

Public methodsはD149 §2のexact fourのみである。

Mutationはroot transaction開始／参加後、D149 §5のtransaction-level PostgreSQL advisory lockを
`partition -> record`順に取得する。Partition keyはschema＋partition、record keyは
schema＋record IDへdomain-separateする。Every append／close pathがpartition lockを使うため、
存在しないrowもserializeされる。Advisory hash collisionはextra serializationだけを生む。

全public methodは最初に`Connection::isAutoCommit()===true`を要求する。`false`はConnection connect／
SQL／lock／row read／Clock／UUID／KeySource／Hydrator／decrypt／transaction control／closeより前に
`storage_unavailable`で拒否し、`setAutoCommit(true)`で正規化しない。その後、Append／closeは、
inactiveかつnesting level 0のConnectionではroot transactionを一つだけ開始する。
Activeかつnesting level exactly 1のConnectionだけがsame caller rootへ参加し、begin／commit／
rollback／savepointを呼ばない。Nesting level 2以上またはactive flagとlevelの不整合は、lock／row
read／Clock／UUID／KeySource／mutationより前に`storage_unavailable`で拒否する。
Exceptionはcallerへ伝播し、accepted caller-owned rootを暗黙commitしない。Readはtransactionを
開始しない。Append／closeのmutation phaseはD149 §5／§6の一つのdata-modifying CTEと
transaction-aborting final count fenceだけで行う。最初のwrite後のCAS／row-count異常を
committableなcaller rootへapplication exceptionだけで返さない。

### 5. Required and durable append

`appendRequired()`:

- `AuditRecordValidator::validateDraft()` exactly once before DB
- typed policy `required` only
- tenant partition derive before DB
- partition advisory／row lock後にClock once。Rollback／equal ClockはD149のclampでvalid
- existing partitionはrecord UUID once。New partitionはinitial segment UUID then record UUID once each
- bootstrap segment `opened_at=assignedAt`
- record advisory lock and store-wide collision check before Finalizer／protect／write
- generated record／segment collisionはcontent比較なしの`resource_unavailable`、zero Finalizer／protect／write
- Finalizer once
- Finalizerがresolved definition／policy `required`を再確認

`appendDurable()`:

- trusted Canonical logical only
- definition and policy `durable` only
- store-wide record ID check before UUID／protect
- original recordId／recordedAt byte-identical preservation
- existing partition insert and idempotent retry use Clock／UUID 0回
- new partition uses logical recordedAt for initial segment openedAt／UUID, Clock 0回
- state high-water becomes `max(previous,logicalRecordedAt)` or logical recordedAt at bootstrap
- B2 does not construct `CanonicalAuditLogicalRecord`

Both paths:

- D149 §5 exact transaction order
- absent bootstrap advisory serialization; existing partition row／active segment locked
- missing partition state performs one exact-two same-partition segment／record existence probe under the
  partition lock; either exists is early `integrity_invalid`, and only both zero permits bootstrap
- active empty segment exact first=`state.last+1`／predecessor=`state.head`; active non-empty exact
  final=`state.last`／head=`state.head`; positive state has exactly one matching endpoint record; endpoint
  segment ID is active only for non-empty active state and differs from active for an empty successor
- non-empty active segment has exactly one minimum-sequence same-segment first row at `segment.first`,
  with first-row previous hash `IS NOT DISTINCT FROM segment.predecessor`; when first>1, exactly one
  same-partition record at first-1 has that predecessor hash and a different segment ID; first1 keeps null
  predecessor; for an empty successor this first-1 row is the same state endpoint
- one active segment and exact `open|closed|sealed` state validation
- genesis sequence1/previous null, otherwise prior head
- one complete call and one protection call only on insert path
- exact row＋derived non-authoritative query indexes
- one dependency-ordered data-modifying CTE writes bootstrap／record／segment／state and an exact
  final count fence aborts the PostgreSQL root on every late CAS／row-count mismatch
- partition and segment compare/update; no caller-catch-and-commit partial write
- no committed sequence gap
- required commit-after-return-loss replay is not claimed by B2; P23-003C same-root business
  idempotency owns that replay boundary

Durable only:

- same ID in another partition returns `idempotency_conflict` with zero KeySource／decrypt
- same ID in the same partition always hydrates／unprotects and verifies physical indexes before clear
  `content_hash` can influence the result
- authenticated stored content equal to incoming returns `inserted=false` without new protection;
  authenticated different content returns `idempotency_conflict`
- valid MAX exhaustion is evaluated only after ID lookup proves a new-record path; malformed cap+1 remains
  an earlier `integrity_invalid`

### 6. Segment close

D149 §6のexplicit-only closeを実装する。

- expected segment required
- empty／unknown／not-active-open -> `segment_conflict`
- already-closed／sealed expected segment -> same persisted result, `changed=false`, Clock／UUID 0回
- non-empty active -> Clock once, successor UUID once, exact domain-hashed token
- close old＋create successor with exact next firstSequence／predecessor＋update state in one transaction
- old `closed_at=closedAt` and successor `opened_at=closedAt`
- one dependency-ordered data-modifying CTE plus exact final count fence; late CAS aborts caller root
- old segment／partition version +1; successor version1
- sequence／head／recordedAt high-water unchanged
- crash retry returns same boundary
- concurrent append／close is partition-lock ordered
- no automatic rotate threshold and no sealed/checkpoint mutation

### 7. Tenant-aware find

D149 §7 exact behavior:

- validate record ID, tenant, partition derivation before SQL
- SQL predicate exact record ID＋partition＋tenant pair。System uses both `IS NULL`; present tenant uses
  both equality predicates
- absent／cross-tenant both `notFound`
- zero hydrator／KeySource／decrypt on zero rows
- one row exact17 -> split three indexes／hydrate physical exact14 once -> fold tenant pair to conceptual
  exact13 -> unprotect once -> compare
  logical action/resource to all three physical indexes -> complete result
- malformed/multiple row impossible or `integrity_invalid`
- record-ID-only query API absent
- read starts no transaction and emits no authorization claim

### 8. Failure precedence and no-leak

D149 §8のoperation別実call orderをdirect compound fixturesで固定する。一つのglobal tableへ
統合しない。`appendRequired`、`appendDurable`、`closeSegment`、`find`ごとに、到達可能な隣接
failureと未到達dependency zero-callを検証する。Required UUID collisionは
`resource_unavailable`、durable cross-partition IDまたはauthenticated same-partition content conflictは
`idempotency_conflict`、DB unique race／
deadlock／transaction failureは`storage_unavailable`である。Write-side protection failureは
`resource_unavailable`、read／idempotent-row unprotect failureは`integrity_invalid`である。
Valid MAX_SAFE counter／version exhaustionは`resource_unavailable`、persisted cap+1／noncanonical
scalarは`integrity_invalid`である。Required／close capacityはClock／UUIDより前、durable valid
exhaustionはID-absent new-record branchに置く。Root begin／commit failureは`storage_unavailable`で、
StoreはConnectionをexactly once `close()`する。Primary failure後のrollback成功はprimary codeを
保つ。Rollbackも失敗した場合だけ`storage_unavailable`へ置換し、Connectionをexactly once
`close()`する。Cleanup close failureは同じsafe codeのままraw detailを捨てる。Caller-owned
Connectionはcloseしない。Late count fenceは必ずcaller-owned level-1 rootをabortedにする。
DBAL exception text、SQLSTATE、schema/table、raw record／tenant／partition／hash、BOPD parts、
key/nonce/ciphertext/plaintextを公開しない。Canaryはexception message、previous、trace args、
debug、serialize、JSON、var_export、Report outputへ出ない。

Store、Result、Hydratorのrecord／tenant／partition／row／protected inputはD149 §2の
`#[SensitiveParameter]`をexactly持つ。Public callableとmagic method inventoryもArchitectureで
固定する。

### 9. Migration inventory and package boundary

Current migration testsを12本へ更新し、新migrationのtable／index／constraintを検証する。
Community Board permanent journeyは上記exact three accuracy replacementsだけでFramework 12＋Application 5＝17と
Board非公開／Documentation Website公開の境界へ更新し、
`examples/community-board/README.md`と`tests/Consumer/community-board-clean-install.sh`を同期する。
`tests/Consumer/framework-package-export.sh` required inventoryには、既存列挙漏れの
`migrations/postgresql/Version20260724000000.php`と新migration／B2 sourceを追加する。
New reusable `tests/Consumer/framework-package-required-inventory.sh`はscript全体を文字列検索せず、
`for required_path in \\` block exactly oneだけをsyntax boundary付きで抽出する。Existing ordered
inventory＋B2 exact10のexact37とbyte-exact比較し、comment-only、existing entry removal、new entry
omission、order swap、duplicate loopをself-contained in-memory negativesで拒否する。さらにunique
`validate_package()`のtop-level executable body、pre-loop termination不在、later redefinition不在、
Git／Composer archiveへのexact two ordered top-level callsと引数を固定し、wrapper-before-loop、early
return、call removal、later redefinitionをnegativeで拒否する。Iteration／Complete Final Gateの双方が
同じtestを直接実行する。
Exact37 orderはcurrent exact27を保持し、`ConfigurationFailure.php`直後へFiles Allowed順のB2 source
exact8、`Version20260712010000.php`直後へ`Version20260724000000.php`、現行last migration
`Version20260808100000.php`直後へ`Version20260829000000.php`を挿入する。Stub exact11の順序は不変である。

Stable fixtureである`tests/Consumer/framework-update-runtime.sh`と
`tests/Consumer/version-baseline.sh`は変更しない。Package exportはAcceptance／commit後の
clean exact SHAだけで実行する。

## Out of Scope

- Audit Outbox table／state／lease／relay／poison
- business mutation orchestration and response gating
- durable ingress logical hydrator／Canonical constructor caller
- Application／Container／runtime registration
- CLI／HTTP route／public JSONL／Documentation beyond the exact Community Board migration-count correction
- checkpoint／seal／anchor／verification
- actor authorization／public query／export／retention／hold／deletion
- automatic segment thresholds
- ciphertext rotation／re-encryption
- Stable `1.2` migration history or public behavior changes
- commit／stage／push by Worker

## Files Allowed

Production／Test／Fixture／accuracy-only public source exact26:

1. `migrations/postgresql/Version20260829000000.php`
2. `src/Internal/Audit/Storage/AuditStoreFailure.php`
3. `src/Internal/Audit/Storage/AuditStoreException.php`
4. `src/Internal/Audit/Storage/AuditAppendResult.php`
5. `src/Internal/Audit/Storage/AuditLookupResult.php`
6. `src/Internal/Audit/Storage/AuditSegmentCloseResult.php`
7. `src/Internal/Audit/Storage/PostgreSqlAuditSchema.php`
8. `src/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php`
9. `src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php`
10. `src/Internal/Migration/PostgreSqlMigrationSchema.php`
11. `tests/Internal/Audit/Storage/AuditStoreValueTypesTest.php`
12. `tests/Internal/Audit/Storage/PostgreSqlAuditSchemaTest.php`
13. `tests/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydratorTest.php`
14. `tests/Internal/Audit/Storage/PostgreSqlAuditRecordStoreTest.php`
15. `tests/Architecture/AuditStoreArchitectureTest.php`
16. `tests/Architecture/AuditRecordArchitectureTest.php`
17. `tests/Internal/Migration/PostgreSqlMigrationSchemaTest.php`
18. `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
19. `tests/Internal/Console/DatabaseMigrationCommandTest.php`
20. `tests/Integration/ApplicationConsoleKernelTest.php`
21. `tests/Consumer/framework-package-export.sh`
22. `tests/Consumer/framework-package-required-inventory.sh`
23. `tests/Consumer/community-board-clean-install.sh`
24. `examples/community-board/README.md`
25. `tests/Fixtures/Audit/audit-postgresql-store-v1.json`
26. `tests/Fixtures/Audit/audit-postgresql-store-golden.mjs`

Worker management exact2:

27. `develop/orchestration/reports/P23-003B2-audit-postgresql-store.md`
28. `develop/STATE.md`

Orchestrator-only immutable exact9 are frozen before Worker start:

- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/decisions/147-audit-catalog-and-safe-projection-boundary.md`
- `develop/decisions/148-audit-canonical-record-and-protected-store-boundary.md`
- `develop/decisions/149-audit-postgresql-canonical-store-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P23-003B2-audit-postgresql-store.md`

Preflight snapshotは上記exact9とmutable Report／STATEを合わせたexact11でre-freezeする。
Worker開始後はTaskを含むexact9がimmutable authorityであり、Report／STATEだけがcheckpointごとに
更新できる。Preflight開始時とAcceptance時にはglobal exact5 management authorityを再同期する。
Worker実行中のmoving checkpoint authorityはReport／STATEだけであり、Task／D149／TODO／Spec
READMEはaccepted preflight時点のfrozen normative handoffである。
Worker diffはProduct／Test／Fixture／accuracy source exact26＋management exact2のexact28、
full worktree statusはimmutable exact9を含むexact37である。
範囲外変更が必要なら実装を広げずReportへBlockerとして返す。

## Required Fixtures

### Schema and migration

- exact three tables／columns／types／constraint/index names
- shared `PostgreSqlMigrationSchema` direct 63-byte positive and 64-byte／same-prefix alias／uppercase／
  non-ASCII／quoted／trailing-LF negatives; invalid values reach zero metadata query／SQL／lock through
  migration dependency factory、runner、and command entrypoints
- system/present tenant rows; partial pair negative
- genesis/non-genesis state constraints
- genesis/non-genesis empty open、non-empty open、closed、sealed segment matrices
- exact firstSequence／predecessor at genesis and successor
- empty closed、two open segments、duplicate incoming successor、self successor、invalid token negative
- cross-partition duplicate `segment_id` DDL negative; pre-existing generated collision; concurrent unique race
- exact BOPD min/max and magic; cap+1／wrong magic
- exact9 event categories; unknown negative
- forbidden clear columns exact absence
- query index byte cap/cap+1 and control negative
- current migration exact12/order/schema; rollback irreversible
- Community Board exact Framework12＋Application5＝17 in README and clean-install consumer
- Stable fixture counts unchanged

### Hydration

- exact17 SQL projection -> physical exact14 Hydrator input -> conceptual exact13 positives for system and tenant
- exact17 column names／order、three query-index split、tenant-pair fold; partial pair、missing／extra column、
  query-index substitution negatives
- DBAL bytea string/resource representations if current driver can return both
- native int／canonical decimal string positives; plus／space／leading-zero／float／overflow negatives
- missing／extra／list／wrong scalar／unsafe integer
- partial tenant／partition mismatch／offset or non-six-fraction timestamp／infinity
- BOPD short／cap+1／wrong magic
- `AuditStoredRecord::fromArray()` exact one Production callsite in hydrator
- unique canaries absent from all exception surfaces

### Append and transaction

- first system append exact sequence1/null previous/open segment boundaries
- second append exact sequence2/previous head
- tenant partition independent sequence1
- required vs durable selector; opposite delivery rejected before DB
- required existing Clock1/UUID1 and bootstrap Clock1/UUID2; durable existing Clock0/UUID0 and
  bootstrap Clock0/UUID1; canonical UTC six fraction
- equal／backward Clock clamps; future durable updates high-water then later required clamps to it
- record／segment UUIDv7 canonical; invalid/collision
- active caller nesting level 1: no nested begin/commit/rollback/savepoint
- `autoCommit=false` disconnected level0、connected ambient level1、`find()`、quarantine/reconnect are all
  early `storage_unavailable` with zero SQL／Clock／UUID／KeySource／Hydrator／decrypt／transaction control／close;
  the connected ambient root remains unchanged and `setAutoCommit(true)` is never called
- active caller nesting level 2/savepoint and active-level inconsistency: early `storage_unavailable`, zero
  B2 lock/read/write; outer business row can commit only after caller rolls back its own savepoint
- inactive caller: exactly one begin/commit; exception rollback
- Store-owned begin／commit／rollback failure closes the DBAL Connection exactly once; nesting resets to
  zero, native connection is discarded, and the next operation reconnects into one fresh root
- rollback after insert leaves no partition/segment/record
- missing partition state with open segment、closed segment、or record is `integrity_invalid` before
  Clock／UUID／KeySource／Finalizer／protect／mutation; a truly empty partition alone bootstraps
- append and changed-close cross-row matrix mutates one of state last/head、segment first/final/head/
  predecessor、first-row sequence/segment/previous、preceding-record sequence/hash/segment、or
  last-endpoint sequence/hash/segment at a time; empty/non-empty/genesis equations and every exact-one
  boundary read must fail `integrity_invalid` before Clock／UUID／KeySource／Finalizer／protect／mutation
- valid non-empty close followed by append accepts the empty successor branch: sequence advances into the
  successor and previous hash remains the closed predecessor head; empty-successor endpoint sequence/hash/
  segment one-field negatives fail early
- active caller late append CAS mismatch aborts root; catch＋commit cannot persist any B2 write
- exact segment/state version +1; required valid MAX exhaustion `resource_unavailable`; cap+1
  `integrity_invalid`
- durable same global record ID/content hash in same partition returns same exact row and `inserted=false`
- durable same-partition legitimate different content -> one hydrate/unprotect then idempotency conflict;
  coherent clear content/record-hash substitution -> one hydrate/unprotect then integrity invalid
- durable same ID in another partition -> idempotency conflict with zero KeySource/decrypt/protect/mutation
- durable valid MAX partition compounds: same-ID same content -> inserted false; same-ID different content ->
  conflict; cross-partition -> conflict; new ID -> resource unavailable, with exact dependency counts
- required generated record／segment collision -> resource unavailable; zero content compare／Finalizer／protect／write
- `protected_body` exact `ParameterType::BINARY`; NUL-containing min/max roundtrip across second connection
- stored row/body/query-index corruption -> integrity invalid
- two-connection empty-partition bootstrap has one state/open segment and sequences 1/2, one head, no gap
- partition then record lock SQL/key order fixed; two connections using the same advisory token block/unblock
  in order and preserve mutation correctness, establishing that hash collision only over-serializes
- unique race/deadlock safe failure and retry

### Segment close

- first non-empty close exact old/new rows、token preimage／full hash、changed=true
- retry exact same persisted result、changed=false、zero Clock/UUID
- empty／unknown／wrong active expected ID conflict
- append-before-close vs close-before-append under two connections
- crash-before-commit no mutation; post-commit retry same boundary
- state/old-segment version update; successor version1; sequence/head/high-water unchanged
- old `closed_at` and successor `opened_at` exact `closedAt`; next-close clamp fixed
- active caller late close CAS mismatch aborts root; catch＋commit cannot persist any B2 write
- no automatic threshold／sealed mutation
- independent Node exact token recomputation and one-field substitutions

### Tenant-aware read

- system and tenant found across a second store/protection codec instance
- absent and cross-tenant value-equivalent notFound results; no `===` singleton assertion
- system exact `IS NULL` and present exact equality SQL branches
- zero hydrator／KeySource／decrypt for zero rows
- expected tenant/partition mismatch before SQL
- row ID only API absent
- non-UTC DB session: required clamp、durable high-water、changed close、closed retry、Node token bytes identical
- every physical exact14 field one-at-a-time substitution and conceptual exact13 reconstruction
- query-index substitution detected after decrypt
- missing old key／wrong AAD／malformed body／complete hash mismatch safe failure
- read starts no transaction

### Failure／architecture／release

- every exact5 failure and D149 operation-specific compound precedence／zero-call matrix
- appendRequired／close MAX_SAFE before resource calls; appendDurable MAX_SAFE after ID resolution only on
  new-record branch; cap+1 remains early integrity failure
- begin／commit failures and primary＋rollback double failure exact code／no-leak／Connection quarantine
- fixed message/code; previous null; canaries absent from trace/debug/serialize/JSON/var_export
- public callable／constructor／enum／magic-method inventory exact; internal UUID FQCN only
- new PublicApi markers exact zero
- Product B2 classes only under `Internal/Audit/Storage`; Deptrac zero violation/uncovered/error
- `StoragePurpose::cases()` exact9 unchanged
- B1 exact32、A1 exact48、A2 exact28 unchanged
- authorized B1 Architecture before/end hash; `fromArray()` BodyCodec＋Hydrator exact2;
  protect/unprotect Record Store exact1 each; protect/unprotect Outbox exact0; Storage directory B1-type
  reference plus B2 per-file exact dependency map; removal/addition/dynamic-call/outside-directory negatives
- no Application／Container／CLI／HTTP／public docs registration
- reusable package required-inventory test syntax-bound extraction equals the existing ordered inventory plus
  B2 exact10; comment-only、existing-entry removal、new-entry omission、order-swap、duplicate-loop negatives fail
- pure manifest checker accepts live allowlist/hashes and rejects one synthetic unauthorized Product path and
  one synthetic wrong immutable exact9 hash without modifying the worktree
- full AGENTS management-ID scan、package-script syntax/inventory static checks
- Release Authority tuple、current migration-command exact12、Stable fixture unchanged、README／consumer
  exact target hashes and extra-claim negatives、tracked Guide／Website source no-diff／status empty、generated
  Search/LLM not regenerated/not claimed

## Acceptance Criteria

1. D149 and this Task receive fresh Sol Max preflight Green before Product edit.
2. Product／Test／Fixture／accuracy-source diff is exact26; Worker diff is exact28; full worktree
   status is exact37 including the
   immutable Orchestrator exact9.
3. B1 exact32、A1 exact48、A2 exact28 remain byte-identical; Architecture is the only B1 delta and fixes
   the exact B2 Record protection/hydration call graph.
4. Transaction participation is exact: every public operation requires `autoCommit=true`; required append may
   join only a caller-owned nesting-level-1 root; nested/savepoint state is rejected before SQL and Store-owned
   transaction-control failure quarantines the Connection.
5. Schema／bootstrap locks／append／close／find contracts are one-to-one with D149 and
   mutation-resistant fixtures.
6. Tenant and partition predicate execute before hydration／KeySource／decrypt.
7. Failure code／precedence／no-leak contracts are exact.
8. Stable `1.2` behavior／fixtures／Public API／Guide／Website remain unchanged; Community Board README
   has only the exact migration-count and stale publication-state accuracy deltas.
9. Iteration Gate and one complete post-freeze Final Gate are Green.
10. Fresh Sol Max implementation review is Green before Acceptance.
11. Worker leaves staged0 and does not commit/push.
12. Orchestrator commits only the reviewed candidate, runs package export on that clean exact SHA,
    and reruns it after any source-changing closeout commit.

## Iteration Gate

Run from repository root after every exact26 change. Stop at first failure.
The Orchestrator implementation dispatch supplies all immutable exact9 individual SHA-256 values
from the accepted re-preflight snapshot. Worker verifies those nine values before batch 1 and at
every Iteration／Final ending; Report records both manifests. A mismatch stops work before further
Product mutation.
The dispatch exports them as newline-delimited `<lower64hex> <path>` in
`P23_B2_IMMUTABLE_SHA256_MANIFEST`; the Gate validates its exact path set、live positive、and a
synthetic wrong-hash negative without changing repository files.

```bash
set -euo pipefail

test -f migrations/postgresql/Version20260829000000.php
test -f src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php
test -f tests/Internal/Migration/PostgreSqlMigrationSchemaTest.php
test -f tests/Fixtures/Audit/audit-postgresql-store-golden.mjs

verify_sha256_manifest() {
  local count=0 expected path actual
  while read -r expected path; do
    test -n "$expected" && test -n "$path"
    test "$expected" = "$(sha256sum "$path" | cut -d' ' -f1)" || return 1
    count=$((count + 1))
  done
  test "$count" -eq 9
}

test -n "${P23_B2_IMMUTABLE_SHA256_MANIFEST:-}"
immutable_manifest_paths="$(printf '%s\n' "$P23_B2_IMMUTABLE_SHA256_MANIFEST" \
  | awk '{print $2}' | LC_ALL=C sort)"
expected_immutable_paths="$(printf '%s\n' \
  develop/TODO.md \
  develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md \
  develop/decisions/147-audit-catalog-and-safe-projection-boundary.md \
  develop/decisions/148-audit-canonical-record-and-protected-store-boundary.md \
  develop/decisions/149-audit-postgresql-canonical-store-boundary.md \
  develop/orchestration/tasks/P23-003B2-audit-postgresql-store.md \
  develop/spec/105-blackops-1-3-capability-contract.md \
  develop/spec/106-phase-23-delivery-plan.md \
  develop/spec/README.md | LC_ALL=C sort)"
diff -u <(printf '%s\n' "$expected_immutable_paths") \
  <(printf '%s\n' "$immutable_manifest_paths")
printf '%s\n' "$P23_B2_IMMUTABLE_SHA256_MANIFEST" | verify_sha256_manifest
! printf '%s\n' "$P23_B2_IMMUTABLE_SHA256_MANIFEST" \
  | sed '1s/^[0-9a-f]/x/' | verify_sha256_manifest

expected_paths="$(printf '%s\n' \
    develop/STATE.md \
    develop/TODO.md \
    develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md \
    develop/decisions/147-audit-catalog-and-safe-projection-boundary.md \
    develop/decisions/148-audit-canonical-record-and-protected-store-boundary.md \
    develop/decisions/149-audit-postgresql-canonical-store-boundary.md \
    develop/orchestration/reports/P23-003B2-audit-postgresql-store.md \
    develop/orchestration/tasks/P23-003B2-audit-postgresql-store.md \
    develop/spec/105-blackops-1-3-capability-contract.md \
    develop/spec/106-phase-23-delivery-plan.md \
    develop/spec/README.md \
    examples/community-board/README.md \
    migrations/postgresql/Version20260829000000.php \
    src/Internal/Audit/Storage/AuditAppendResult.php \
    src/Internal/Audit/Storage/AuditLookupResult.php \
    src/Internal/Audit/Storage/AuditSegmentCloseResult.php \
    src/Internal/Audit/Storage/AuditStoreException.php \
    src/Internal/Audit/Storage/AuditStoreFailure.php \
    src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php \
    src/Internal/Audit/Storage/PostgreSqlAuditSchema.php \
    src/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php \
    src/Internal/Migration/PostgreSqlMigrationSchema.php \
    tests/Architecture/AuditRecordArchitectureTest.php \
    tests/Architecture/AuditStoreArchitectureTest.php \
    tests/Consumer/community-board-clean-install.sh \
    tests/Consumer/framework-package-export.sh \
    tests/Consumer/framework-package-required-inventory.sh \
    tests/Fixtures/Audit/audit-postgresql-store-golden.mjs \
    tests/Fixtures/Audit/audit-postgresql-store-v1.json \
    tests/Integration/ApplicationConsoleKernelTest.php \
    tests/Internal/Audit/Storage/AuditStoreValueTypesTest.php \
    tests/Internal/Audit/Storage/PostgreSqlAuditRecordStoreTest.php \
    tests/Internal/Audit/Storage/PostgreSqlAuditSchemaTest.php \
    tests/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydratorTest.php \
    tests/Internal/Console/DatabaseMigrationCommandTest.php \
    tests/Internal/Migration/PostgreSqlMigrationSchemaTest.php \
    tests/Internal/Migration/DatabaseMigrationRunnerTest.php | LC_ALL=C sort)"
actual_paths="$(git status --short --untracked-files=all | cut -c4- | LC_ALL=C sort)"

compare_exact_path_lists() {
  diff -u \
    <(printf '%s\n' "$1" | sed '/^$/d' | LC_ALL=C sort) \
    <(printf '%s\n' "$2" | sed '/^$/d' | LC_ALL=C sort)
}

compare_exact_path_lists "$expected_paths" "$actual_paths"
! compare_exact_path_lists "$expected_paths" "${actual_paths}"$'\n'src/unauthorized-b2-path.php

docker compose run --rm app mago format --check \
  migrations/postgresql/Version20260829000000.php \
  src/Internal/Audit/Storage \
  src/Internal/Migration/PostgreSqlMigrationSchema.php \
  tests/Internal/Audit/Storage \
  tests/Architecture/AuditStoreArchitectureTest.php \
  tests/Architecture/AuditRecordArchitectureTest.php \
  tests/Internal/Migration/PostgreSqlMigrationSchemaTest.php \
  tests/Internal/Migration/DatabaseMigrationRunnerTest.php \
  tests/Internal/Console/DatabaseMigrationCommandTest.php \
  tests/Integration/ApplicationConsoleKernelTest.php

docker compose run --rm app php vendor/bin/phpunit \
  tests/Internal/Audit/Storage \
  tests/Architecture/AuditStoreArchitectureTest.php \
  tests/Architecture/AuditRecordArchitectureTest.php \
  tests/Architecture/AuditPublicApiTest.php \
  tests/Architecture/PublicApiArchitectureTest.php \
  tests/Internal/Migration/PostgreSqlMigrationSchemaTest.php \
  tests/Internal/Migration/DatabaseMigrationRunnerTest.php \
  tests/Internal/Console/DatabaseMigrationCommandTest.php \
  tests/Integration/ApplicationConsoleKernelTest.php

node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs

docker compose run --rm app php vendor/bin/deptrac analyse \
  --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered

bash tests/Consumer/version-baseline.sh
bash -n tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
bash -n tests/Consumer/community-board-clean-install.sh
test "$(sha256sum examples/community-board/README.md | cut -d' ' -f1)" = \
  d120b91c48b63fc8f2e67ae4e6ac5e8439873174a960b08e485fc36d51f99538
test "$(sha256sum tests/Consumer/community-board-clean-install.sh | cut -d' ' -f1)" = \
  d12c11c7c1c6fe895a14015576346b499ae8a235d1998ba0f1ff67e80b8e8220
test "$(awk '1; END { print "Audit Trail is Stable 1.2." }' \
  examples/community-board/README.md | sha256sum | cut -d' ' -f1)" != \
  d120b91c48b63fc8f2e67ae4e6ac5e8439873174a960b08e485fc36d51f99538
! rg -n '11 Framework|migrations: 16' \
  examples/community-board/README.md tests/Consumer/community-board-clean-install.sh
grep -Fqx 'The permanent consumers perform equivalent cleanup automatically. BlackOps Board is a Local／CI reference application and is not published or hosted externally. The Documentation Website is published on Cloudflare Pages.' \
  examples/community-board/README.md
! grep -Fqx 'The permanent consumers perform equivalent cleanup automatically. The application and Documentation Website are Local／CI examples; this repository does not publish or host them externally.' \
  examples/community-board/README.md
test -d docs/guide
test -d docs/website
verify_public_doc_status() {
  test -z "$1"
}
public_doc_status="$(git status --short --untracked-files=all -- docs/guide docs/website)"
verify_public_doc_status "$public_doc_status"
! verify_public_doc_status ' M docs/website/src/content.config.ts'

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests --glob '*.php'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  migrations/postgresql/Version20260829000000.php --glob '*.php'

mapfile -t p23_a1_paths < <(sed -n '/^## Files Allowed$/,/^## Required Fixtures$/ {
  s/^[0-9][0-9]*\. `\([^`]*\)`$/\1/p
}' develop/orchestration/tasks/P23-003A1-audit-catalog-foundation.md | head -n 48)
test "${#p23_a1_paths[@]}" -eq 48
test "$(sha256sum "${p23_a1_paths[@]}" | sha256sum | cut -d' ' -f1)" = \
  9e9ba592e5203c17d814c3f0cc8772b2fc95e7ddc75b67df6a5c16c9a7030fed

mapfile -t p23_a2_paths < <(sed -n '/^## Files Allowed$/,/^## Required Fixtures$/ {
  s/^[0-9][0-9]*\. `\([^`]*\)`$/\1/p
}' develop/orchestration/tasks/P23-003A2-audit-record-and-observed-projection.md \
  | head -n 29 | rg -v '^tests/Architecture/AuditRecordArchitectureTest\.php$')
test "${#p23_a2_paths[@]}" -eq 28
test "$(sha256sum "${p23_a2_paths[@]}" | sha256sum | cut -d' ' -f1)" = \
  ba06dd0f1886f45b373c02880abb06d662e11e2f53d177c5b67cca3fd0b376ea

mapfile -t p23_b1_paths < <(sed -n '/^## Files Allowed$/,/^## Required Fixtures$/ {
  s/^[0-9][0-9]*\. `\([^`]*\)`$/\1/p
}' develop/orchestration/tasks/P23-003B1-audit-canonical-record-foundation.md \
  | head -n 33 | rg -v '^tests/Architecture/AuditRecordArchitectureTest\.php$')
test "${#p23_b1_paths[@]}" -eq 32
test "$(sha256sum "${p23_b1_paths[@]}" | sha256sum | cut -d' ' -f1)" = \
  a653ffb3fdfbf5963a02012e59281cfca94308668533a8db48ec6991e5bec490

git diff --check
test -z "$(git diff --cached --name-only)"
```

After Green, record all exact26 individual SHA-256 values and the ordered
`sha256sum <exact26 paths> | sha256sum` aggregate. Any exact26 change invalidates the freeze.

## Complete Final Gate

Run exactly once from an unchanged exact26 freeze after Orchestrator pre-gate review. Stop at the
first failure. Before command 1 and after the last command, rerun the Iteration Gate exact37 scope
diff、immutable exact9 hashes、A1/A2/B1 preservation blocks、and reusable package required-inventory
test. Any mismatch invalidates this Gate.
`framework-package-export.sh` execution is intentionally excluded until candidate commit, but its
syntax and required inventory remain part of this Gate.
The migration formatting target is the sole migration in Files Allowed. The exact37 scope guard
proves that every other migration remains unchanged; pre-existing out-of-scope migration formatting
is not folded into this Task.

```bash
set -euo pipefail

docker compose run --rm app mago format --check \
  src tests migrations/postgresql/Version20260829000000.php
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app php vendor/bin/phpunit
docker compose run --rm app php vendor/bin/deptrac analyse \
  --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/community-board-clean-install.sh
bash -n tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
test -d docs/guide
test -d docs/website
git diff --exit-code -- docs/guide docs/website
test -z "$(git status --short --untracked-files=all -- docs/guide docs/website)"
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests --glob '*.php'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  migrations/postgresql/Version20260829000000.php --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

Record full counts、warnings／notices、exact26 start/end hashes、B1/A1/A2 preservation、
Architecture before/end hash、tracked Guide／Website source no-diff、generated artifact Not Verified、staged0。

## Post-Acceptance Commit and Package Gate

Only Orchestrator performs these steps after fresh implementation review Green and Acceptance.

1. Commit the reviewed Product／Test／management candidate.
2. Verify clean worktree and exact commit SHA.
3. Run `bash tests/Consumer/framework-package-export.sh` on that SHA.
4. If a management closeout commit changes package-visible source or required inventory, rerun the
   package gate on the new clean SHA.
5. Do not push unless the user separately requests it.

## Report Requirements

Report must contain:

- Summary
- Changed Files exact list
- Decisions and Assumptions
- Commands and Results with exit codes/counts
- exact26 individual hashes and ordered aggregate
- exact37 status allowlist and immutable exact9 start/end hashes
- B1 exact32／A1 exact48／A2 exact28 preservation
- Architecture before/end hash
- transaction／idempotency／close／tenant-read evidence
- no-leak and failure precedence evidence
- Stable／public-doc／non-activation evidence
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

未実行commandは理由とともに明記する。GreenでないものをGreenと記録しない。

## Remaining Issues

Implementation、Iteration／Complete Final Gates、fresh DB／General review、Acceptance、candidate commit、
and same-SHA package export are Green. P23-003C以降はこのTaskのscope外である。

## Suggested Next Action

Accepted parent Bを入力としてP23-003Cへ進む。Pushは行わない。
