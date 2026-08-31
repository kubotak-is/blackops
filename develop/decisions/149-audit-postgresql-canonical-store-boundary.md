# D149: Audit PostgreSQL Canonical Store Boundary

Status: P23-003B2 Accepted / Parent P23-003B Accepted — Post-Acceptance Commit and Package Export Pending; BlackOps 1.3 unreleased

Updated At: 2026-08-31T20:54:49+09:00

## Current Handoff

P23-003B1はAcceptedである。Reviewed Product candidate
`c3b75ca0afe6d8c756caa8404b7e95922987c1da`と、そのclean candidate SHAでの
Composer package exportはGreenである。Accepted B1 exact33 ordered aggregateは
`bb6ab89a222a72eafa601f588f46177ee443e554c036bcc00575b211961c8847`である。

このDecisionはP23-003B2だけを閉じる。P23-003B2はAccepted B1を入力として、
PostgreSQL canonical append、partition-global chain、explicit segment close、
tenant predicate-before-decrypt、migrationを実装する。

Stable `1.2.0`はcurrentであり、BlackOps `1.3`はunreleasedである。P23-003B2と
parent P23-003BはAcceptedである。P23-003B2は
Application composition、Container registration、CLI、HTTP route、public Documentation、
Audit Outbox、relay、checkpoint sealing、public queryをactivateしない。Parent P23-003Bは
P23-003C以降のruntime／delivery実装を自動的にactivateしない。

Earlier preflight and review lanes are Historical. The accepted Product exact26 is
`ecacafc99cbdf4fd2287b7554b88a34f6524a23d92c9adb3a8bf201a2a1d50bb`.
Its Complete Final Gate passed PHPUnit `2,939/85,080`, Deptrac
`999/0/0/6,079`, four independent Node goldens, Community Board clean install,
package exact37／mutation guards, and ending freeze. Fresh DB and General Sol
closure reviews separately returned `P1=0/P2=0/P3=0`; the quote-aware catalog
oracle closes the last review root without a Production change.

Remaining: post-Acceptance candidate commit and same-SHA package export.

Next Action: Orchestratorがreviewed candidateをcommitし、そのclean exact SHAで
`framework-package-export.sh`を実行する。Pushは行わない。

## Context

D148はB2の責務を示したが、exact SQL DDL、index名、migration version、store APIを
B2 preflightへ延期していた。これらをWorker判断へ残すと、transaction owner、
idempotency、segment crash recovery、tenant-aware readの実装が分岐する。

また、`src/Transport/PostgreSql`はDeptrac上でB1の`BlackOps\Internal\Audit`型へ
依存できない。B2 storeはDoctrine DBALを使うInternal componentとして
`src/Internal/Audit/Storage`へ置く。Stable transport／Public APIへ新しい依存辺を追加しない。

## Decision

### 1. Transaction ownership

`PostgreSqlAuditRecordStore`は一つのinjected Doctrine DBAL `Connection`だけを使う。
すべてのpublic operationは最初に`isAutoCommit()===true`を要求する。`false`はSQL、Connection
connect、advisory lock、row read、Clock、UUID、KeySource、Hydrator、decrypt、begin／commit／
rollback／savepoint／closeより前に`storage_unavailable`で拒否し、`setAutoCommit(true)`で
正規化しない。Append／closeは、そのprecondition後に`isTransactionActive()===false`かつ
`getTransactionNestingLevel()===0`なら一つのroot transactionを開始してcommit／rollbackする。
`isTransactionActive()===true`かつnesting level exactly 1なら、そのsame rootへ参加し、nested
transaction／savepointを作らず、rootをcommit／rollbackしない。Nesting level 2以上、または
active flagとnesting levelがこの二状態に一致しないConnectionは、advisory lock／row read／Clock／
UUID／KeySource／mutationより前に`storage_unavailable`で拒否する。失敗は例外としてcallerへ返し、
accepted caller-owned root transactionのrollback判断はcallerが持つ。
ただし、Storeが最初のSQL mutationへ到達した後のCAS／row-count異常は§5／§6の一文atomic
write fenceがrootを必ずabortedにする。Callerが例外をcatchしてrootをcommitしても、部分的な
Audit writeを耐久化できる状態を返してはならない。

これにより、B2単体のatomic appendと、P23-003Cのbusiness mutationと同じunderlying
Connection／root transactionで行うrequired appendを同じcode pathで実装する。
別Connection、name-only transaction、post-commit append、best-effort fallbackは禁止する。

### 2. Exact internal API

新規型はすべてPHP Public APIではなく、`#[PublicApi]`を持たない。

```text
AuditStoreFailure: string enum
  resource_unavailable
  idempotency_conflict
  segment_conflict
  storage_unavailable
  integrity_invalid

AuditStoreException extends RuntimeException
  __construct(AuditStoreFailure)
  failure(): AuditStoreFailure
  code(): string
  message: "Audit store operation failed."
  previous exception、SQL、schema、record／tenant／partition／hash／payloadを公開しない

AuditAppendResult
  __construct(#[SensitiveParameter] AuditStoredRecord record, bool inserted)
  record(): AuditStoredRecord
  inserted(): bool
  __debugInfo(): []
  __serialize(): []
  __unserialize(array): void; always throws AuditStoreException(integrity_invalid)

AuditLookupResult
  private __construct(bool found, #[SensitiveParameter] ?CompleteAuditRecord record)
  fromRecord(#[SensitiveParameter] CompleteAuditRecord): self
  notFound(): self
  isFound(): bool
  record(): ?CompleteAuditRecord
  __debugInfo(): []
  __serialize(): []
  __unserialize(array): void; always throws AuditStoreException(integrity_invalid)

AuditSegmentCloseResult
  __construct(#[SensitiveParameter] string partitionId,
              #[SensitiveParameter] string closedSegmentId,
              #[SensitiveParameter] string successorSegmentId,
              int firstSequence, int finalSequence,
              #[SensitiveParameter] ?string predecessorRecordHash,
              #[SensitiveParameter] string headHash,
              #[SensitiveParameter] string closeFencingToken,
              string closedAt, bool changed)
  partitionId(): string
  closedSegmentId(): string
  successorSegmentId(): string
  firstSequence(): int
  finalSequence(): int
  predecessorRecordHash(): ?string
  headHash(): string
  closeFencingToken(): string
  closedAt(): string
  changed(): bool
  __debugInfo(): []
  __serialize(): []
  __unserialize(array): void; always throws AuditStoreException(integrity_invalid)

PostgreSqlAuditSchema
  __construct(string schema="blackops")
  schemaName(): string
  partitionStateTable(): string
  segmentsTable(): string
  recordsTable(): string
  statements(): list<string>

PostgreSqlAuditStoredRecordHydrator
  __construct(AuditPartitionIdDeriver)
  hydrate(#[SensitiveParameter] array row): AuditStoredRecord

PostgreSqlAuditRecordStore
  appendRequired(#[SensitiveParameter] AuditRecordDraft): AuditAppendResult
  appendDurable(#[SensitiveParameter] CanonicalAuditLogicalRecord): AuditAppendResult
  find(#[SensitiveParameter] string recordId,
       #[SensitiveParameter] ?AuditTypedReference expectedTenant,
       #[SensitiveParameter] string expectedPartitionId): AuditLookupResult
  closeSegment(#[SensitiveParameter] string partitionId,
               #[SensitiveParameter] string expectedSegmentId): AuditSegmentCloseResult
```

Resultのprotected／logical valuesはprivate `SensitiveParameterValue`へ保持する。Getterだけが
consumer access surfaceであり、public promoted property、`JsonSerializable`、`Stringable`、
`__set_state`は持たない。`notFound()`は毎回新しい同値objectを返してよく、`===` singletonを
契約にしない。Observable valueは`isFound()===false`かつ`record()===null`だけである。
Failure／Exception／Result／Schema／Hydrator／Storeはすべて`final`、Result／Schema／Hydratorは
`readonly`である。Storeはmutable Connectionを保持するため`final`だがclass-level readonlyは
要求しない。

UUID portはexactly `BlackOps\Internal\Identifier\Uuidv7Generator`であり、callableは
`generate(DateTimeImmutable):string`である。Stable Public APIの
`BlackOps\Identifier\Uuidv7Generator::generate():string`をB2へinjectしない。

`appendRequired()`はCatalog definitionとrecord policyのdeliveryがともに`required`のDraftだけを
受ける。partition lock後にtrusted stampを一度作り、Accepted B1 Finalizerを一度呼ぶ。
`appendDurable()`はすでにtrusted ingress stampを持つ、definition／policyとも`durable`の
Canonical logical recordだけを受け、recordId／recordedAtを変更しない。
P23-003Cはdurable ingressからCanonical logical recordを復元するauthorityを別packetで持つ。
B2はそのhydratorやAudit Outboxを実装しない。

### 3. Trusted clock and identifiers

Storeは`Psr\Clock\ClockInterface`、Accepted
`BlackOps\Internal\Identifier\Uuidv7Generator`、
`AuditPartitionIdDeriver`をinjectする。Database `CURRENT_TIMESTAMP`、session timezone、
random UUID、caller-supplied record／segment IDをauthorityにしない。

Clock／UUID回数とhigh-waterはbranch別にexactly次である。

- `appendRequired()`はpartition mutation lock後にClockを一度読む。UTC six-fraction
  `canonicalNow`へ変換し、existing stateでは
  `assignedAt=max(canonicalNow,lastAssignedRecordedAt)`、new stateでは
  `assignedAt=canonicalNow`とする。Clock rollbackはclampし、failureにしない。同値でも
  1 microsecondを加えない。Existing partitionではrecord UUIDを一度、new partitionでは
  initial segment UUID、record UUIDの順に各一度、どちらも`assignedAt`を渡して作る。
- `appendDurable()`はlogical `recordId`／`time.recordedAt`を変更しない。Existing same-ID retryは
  Clock／UUID 0回である。Existing partitionへのnew insertもClock／UUID 0回で、state
  high-waterを`max(previousHighWater,logicalRecordedAt)`へ更新する。New partitionだけは
  logical `recordedAt`をinitial segment `openedAt`とUUID timeに使ってsegment UUIDを一度作り、
  state high-waterをlogical `recordedAt`へ置く。Clockは0回である。
- `closeSegment()`のalready-closed／sealed retryはClock／UUID 0回である。Changed closeだけ
  Clockを一度読み、`closedAt=max(canonicalNow,segment.openedAt,
  lastAssignedRecordedAt)`へclampし、successor UUIDを一度作る。CloseはrecordedAt high-waterを
  変更しない。
- `find()`はClock／UUID 0回である。

Required bootstrap segmentの`opened_at`は`assignedAt`、durable bootstrapはlogical
`recordedAt`、close successorは`closedAt`である。Close対象old segmentの`closed_at`も
同じ`closedAt`である。

Clock valueのUTC変換不能、date overflow、UUID grammar不正、事前照合で検出したUUID collisionは
`resource_unavailable`でno writeである。Clock rollback自体はvalid clampである。

### 4. Exact schema and migration

Migration classは
`BlackOps\Migrations\PostgreSql\Version20260829000000`とする。
一つのirreversible migrationで、configured schemaへ次の三tableを追加する。

Configured schemaは`\A[a-z_][a-z0-9_]{0,62}\z`の1..63 ASCII bytesに固定する。
Existing shared `PostgreSqlMigrationSchema`がこのgrammarの唯一のraw-string validatorであり、
`DoctrineMigrationDependencyFactory`の`SELECT current_schema()`を含む最初のSQLより前に構築される。
64 bytes以上、同一63-byte prefix、uppercase、non-ASCII、quoted aliasはそこでzero-SQLで拒否する。
`PostgreSqlAuditSchema`はshared validatorへdelegateし、そのcanonical nameだけを保持する。
`PostgreSqlAuditRecordStore`はvalidated `PostgreSqlAuditSchema`をinjectし、table qualificationと
advisory lock keyの両方へその同一`schemaName()`値を使う。Raw schema stringや別regexを保持しない。

#### `audit_partition_state`

```text
partition_id                 varchar(71) primary key
tenant_type                  varchar(256) null
tenant_id                    varchar(256) null
active_segment_id            uuid not null
last_sequence                bigint not null default 0
head_record_hash             char(64) null
last_assigned_recorded_at    timestamptz not null
state_version                bigint not null default 1
```

Constraints:

- `partition_id`は`system:global|tenant:[0-9a-f]{64}`。
- system partitionはtenant pairがboth null、tenant partitionはboth non-null。
- tenant membersは1..256 UTF-8 bytes相当として`octet_length`で拘束し、controlを拒否する。
- `last_sequence`は`0..9007199254740991`、`state_version`は`1..9007199254740991`。
- `last_sequence=0` iff `head_record_hash is null`、positive iff 64-lower-hex non-null。

#### `audit_segments`

```text
partition_id             varchar(71) not null
segment_id               uuid not null
state                    text not null             -- open|closed|sealed
first_sequence           bigint not null
final_sequence           bigint null
head_record_hash         char(64) null
predecessor_record_hash  char(64) null
opened_at                timestamptz not null
closed_at                timestamptz null
successor_segment_id     uuid null
close_fencing_token      varchar(71) null
state_version            bigint not null default 1
primary key (partition_id, segment_id)
```

Genesis empty openは`first_sequence=1`、`predecessor_record_hash=null`、
`final_sequence=head_record_hash=null`である。Non-genesis empty successorは
`first_sequence=old.final_sequence+1`、`predecessor_record_hash=old.head_record_hash`、
`final_sequence=head_record_hash=null`である。Non-emptyは
`final_sequence>=first_sequence`とhead hashをboth non-nullで持つ。Openはclose三値をall null、
closed／sealedはnon-emptyかつ`closed_at`、successor、tokenをall non-nullで持つ。
SealedはP23-003Fだけがclosedから遷移させ、B2は既存sealed boundaryをread／retryできるが
作成しない。Successorはselfでなく、一つのsuccessorへincoming edgeは一つだけである。
sequence／hash／time、`aft:v1:[0-9a-f]{64}`、safe integer／state versionをCHECKする。
`audit_segments_one_open_idx`は
`(partition_id) WHERE state='open'`のunique partial indexである。
`audit_segments_successor_uq`は
`unique(partition_id,successor_segment_id) WHERE successor_segment_id IS NOT NULL`である。
`audit_segments_segment_id_uq`は`unique(segment_id)`であり、segment IDをcanonical store全体で
uniqueにする。Composite PK／same-partition FKは引き続き保持する。

#### `audit_records`

```text
schema_version        smallint not null              -- exactly 1
kind                  text not null                  -- audit_stored_record
record_id             uuid not null
partition_id          varchar(71) not null
tenant_type           varchar(256) null
tenant_id             varchar(256) null
segment_id            uuid not null
sequence              bigint not null
previous_record_hash  char(64) null
content_hash          char(64) not null
record_hash           char(64) not null
recorded_at           timestamptz not null
event_category        text not null
protected_body        bytea not null
action_type           varchar(256) not null
resource_type         varchar(256) not null
resource_id           varchar(256) not null
primary key (partition_id, record_id)
```

`tenant_type`／`tenant_id`は`AuditStoredRecord.tenantBinding`のphysical pairであり、hydrate時に
closed unionへ戻す。`action_type`／`resource_type`／`resource_id`はD148が許可する
non-authoritative query indexであり、stored-record/AAD memberではない。Actor、policy、
evidence、correlation、occurredAt、plaintext、key IDをclear columnへ置かない。

ConstraintsはUUIDv7、partition／tenant pair、sequence safe range、genesis previous-null、
hash grammar、Audit event category exact9、BOPD magic、protected body `53..1048756` bytes、
query index `1..256` bytes／no-controlを拘束する。

Indexes／relationsはexactly次を持つ。

```text
audit_records_record_id_uq            unique(record_id)
audit_records_partition_sequence_uq  unique(partition_id, sequence)
audit_records_partition_hash_uq      unique(partition_id, record_hash)
audit_records_segment_sequence_idx   (partition_id, segment_id, sequence)
audit_records_recorded_at_idx         (partition_id, recorded_at, sequence)
audit_records_action_idx              (partition_id, action_type, recorded_at, sequence)
audit_records_resource_idx            (partition_id, resource_type, resource_id, recorded_at, sequence)
```

`recordId`はcanonical store全体でuniqueであり、partitionだけにscopeしない。
`audit_records(partition_id,segment_id)`は`audit_segments`へimmediate FKを持つ。
Partition stateの`(partition_id,active_segment_id)`とsegmentの
`(partition_id,successor_segment_id)`はsegmentへDEFERRABLE INITIALLY DEFERRED FKを持つ。
Migration／schema helper／testは同じexact DDLを別々に複製せず、一つの
`PostgreSqlAuditSchema` sourceを共有する。

Constraint／index namesとstatement orderはexactly次である。

```text
audit_segments_pk
audit_segments_segment_id_uq
audit_segments_state_check
audit_segments_segment_id_check
audit_segments_sequence_check
audit_segments_boundary_check
audit_segments_lifecycle_check
audit_segments_time_check
audit_segments_successor_check
audit_segments_state_version_check
audit_segments_successor_fk
audit_partition_state_pk
audit_partition_state_partition_check
audit_partition_state_tenant_pair_check
audit_partition_state_tenant_bounds_check
audit_partition_state_sequence_check
audit_partition_state_head_check
audit_partition_state_version_check
audit_partition_state_active_segment_fk
audit_records_pk
audit_records_schema_kind_check
audit_records_record_id_check
audit_records_partition_tenant_check
audit_records_sequence_check
audit_records_previous_check
audit_records_hash_check
audit_records_category_check
audit_records_body_check
audit_records_action_check
audit_records_resource_check
audit_records_segment_fk
audit_segments_one_open_idx
audit_segments_successor_uq
audit_records_record_id_uq
audit_records_partition_sequence_uq
audit_records_partition_hash_uq
audit_records_segment_sequence_idx
audit_records_recorded_at_idx
audit_records_action_idx
audit_records_resource_idx
```

`statements()`の順序はsegments table、partition-state table、records table、上記index順である。
PK／CHECK／FK、segmentsのglobal `segment_id` UNIQUE、recordsの三つの`*_uq`はtable DDL内で
上記名を明示する。
`audit_segments_one_open_idx`、`audit_segments_successor_uq`、recordsの四つのnon-unique
`*_idx`だけを後続CREATE INDEX statementにする。Table／index作成に`IF NOT EXISTS`を使わず、
partial／pre-existing objectをsilent acceptしない。

### 5. Partition initialization and append

Append mutationはtransaction開始後、全pathで次のtransaction-scoped lockを取得する。

```sql
SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))
```

Partition lock keyは
`blackops.audit.partition.v1:<schemaName>:<partitionId>`である。Appendはpartition lockの後、
record IDが確定した時点で
`blackops.audit.record.v1:<schemaName>:<recordId>`も取得する。Closeはpartition lockだけを使う。
Hash collisionは無関係partition／recordを余分に直列化するだけでcorrectnessを変えない。
Caller-owned rootではlockはそのroot終了まで保持される。すべてのmutationが同じlock order
`partition -> record`を使い、absent rowもこのlockでserializeする。

Appendは次の順序だけで行う。

1. §1の`isAutoCommit()===true` preconditionを全resourceより前に検証する。
2. 入力のclosed shape／Catalog delivery／tenant partitionをDB、Clock、ID、KeySourceより前に検証する。
3. §1のtransaction state matrixを検証し、level 0ではrootを開始、level 1では既存rootへ参加する。
   他のlevel／active-state combinationはSQLより前に`storage_unavailable`で拒否する。
4. partition advisory lockを取得し、partition rowを`SELECT ... FOR UPDATE`する。Missingなら同じlock下で
   segments／recordsのsame-partition existenceを一つのread-only queryで確認する。Projectionは
   `CASE WHEN EXISTS(...) THEN 1 ELSE 0 END AS segment_exists`と同じ`record_exists`のexact2であり、
   native intまたはcanonical decimal `0|1`だけを受ける。Either 1、missing／extra／multiple／wrong scalarは
   `integrity_invalid`で、Clock／UUID／KeySource／Finalizer／protect／mutationは0回である。Both 0だけを
   serialized bootstrap stateとしてmemory上で扱い、resource work完了前にINSERTしない。
5. tenant binding、state、active segmentを検証する。Existing active segmentは同じtransactionで
   lockする。Persisted scalar grammar／range／structural integrityを検証する。Required appendとcloseは
   valid MAX exhaustionもresource callより前に判定する。Durable appendはvalid MAX exhaustionだけを
   same-ID resolution後のnew-record branchまで延期する。
   Appendとchanged closeは以下のcross-row検証を同じread-only boundaryとして再利用する。
   Active segmentがemptyなら`final_sequence`／segment headはboth null、
   `first_sequence=state.last_sequence+1`、segment predecessorはstate headと`IS NOT DISTINCT FROM`で
   一致する。Non-emptyならsegment final＝state last、segment head＝state headである。Genesisは
   state last 0、state head／segment predecessor null、first 1である。State lastがpositiveなら
   same-partition endpoint recordを`partition_id + sequence`でexactly one row読み、そのsequence＝state
   last、record hash＝state headを検証する。Non-empty active segmentではendpoint segment ID＝active
   segmentを要求する。Empty non-genesis active successorではendpoint segment ID≠active segmentを要求し、
   `first_sequence=state.last_sequence+1`／predecessor＝state headにより、このendpointをexact
   `first_sequence-1` predecessor recordとして共有する。Non-empty active segmentでは
   same-partition／same-segmentのminimum-sequence recordをexactly one読み、そのsequence＝segment first、
   previous hashがsegment predecessorと`IS NOT DISTINCT FROM`で一致することを検証する。
   `first_sequence>1`ではsame-partitionの`first_sequence-1` recordもexactly one読み、そのrecord hash＝
   segment predecessor、segment ID≠active segmentを要求する。Empty successorではこのrowとstate
   endpointは同一rowである。First `1`ではpredecessor nullを維持する。
   一項でも不一致／boundary row missingなら
   `integrity_invalid`で、Clock／UUID／KeySource／Finalizer／protect／mutationは0回である。
6. durableはglobal `record_id` advisory lock後にstore-wide IDを検索する。別partitionならzero
   KeySource／decryptで`idempotency_conflict`。Same partitionならclear `content_hash`の一致／不一致を
   authorityにせず、physical exact17 rowからquery index三値を分離したexact14をhydrate／unprotectし、
   conceptual exact13のauthenticated complete recordと
   physical query indexesを先に検証する。Binding成功後にauthenticated stored content hashをincoming
   hashへ比較し、一致なら`inserted=false`、不一致なら`idempotency_conflict`を返す。IDが存在しない
   new-record branchだけがvalid MAX exhaustionを`resource_unavailable`として判定する。
7. requiredは§3のClockを読み、missing stateならinitial segment UUID、続いてrecord UUIDを作る。
   Record lock後のstore-wide collisionはcontentを比較せず`resource_unavailable`で拒否し、
   Finalizer／protect／writeを0回にする。CollisionがなければstampからFinalizerを一度呼ぶ。
   Durableはoriginal stampを保ち、missing stateだけ§3のinitial segmentを作る。生成したinitial／
   successor segment IDのstore-wide collisionも`resource_unavailable`でwrite前に拒否する。
8. `sequence=last_sequence+1`、`previous=head_record_hash`、active segmentで
   `AuditRecordCodec::complete()`を一度呼び、`AuditProtectedBodyCodec::protectRecord()`を一度呼ぶ。
9. resource work成功後にだけ、bootstrap／record insert、segment boundary、partition
   `last_sequence`／head／high-water／state versionを一つのdependency-ordered
   data-modifying CTE statementで変更する。New state／segmentはversion 1、existing appendは
   各versionをexactly +1し、expected prior versionをpredicateへ含める。Final SELECTは
   expected row countsがすべて一致する場合だけ1を返し、不一致なら同じstatement内の
   `CAST(CASE WHEN <all-counts-match> THEN '1' ELSE 'audit_store_abort' END AS bigint)`で
   PostgreSQL root transactionをabortedにする。最初のSQL mutation後にapplication exceptionだけを
   throwしてrootをcommittableなまま残さない。
10. own root transactionだけcommitし、resultを返す。

`last_sequence=MAX_SAFE_INTEGER`、increment対象state／segment versionがMAX_SAFE_INTEGER、
close successor `first_sequence` overflowはvalid-but-exhausted `resource_unavailable`とする。
Required／closeはClock／UUID／key／protect／writeより前に拒否し、durableはexisting-ID resultを
解決した後のnew-record branchだけで拒否する。Persisted cap+1／非canonical scalar、
missing active segment、row/hash/binding divergenceは`integrity_invalid`である。DB error、unique race、
deadlockはraw detailsを公開しない。Crash before root commitはno committed mutation、commit後の
durable retryはsame record ID/contentHashから同じsequence/hash/ciphertext rowを返し、
再暗号化しない。Required Draftにはreplay identityがないため、commit後return-lossのblind retryを
B2は安全とclaimしない。P23-003Cのsame-root business idempotencyがrequired operation replayを
所有する。

### 6. Explicit segment close

Automatic count／size／time thresholdはない。Callerはauthorized internal partition IDと
expected active segment IDを渡す。

Storeは§5のpartition advisory lock後にpartition rowを`FOR UPDATE`し、次を行う。

- expected segmentがすでにclosed／sealedなら、persisted boundaryを`changed=false`で返す。
- expected segmentがactiveでない、unknown、またはemptyなら`segment_conflict`でno mutation。
- non-empty active segmentなら§3のclose timeとsuccessor UUIDv7を生成する。
- old segmentをclosedへ、successorを
  `first_sequence=old.final_sequence+1`／`predecessor_record_hash=old.head_record_hash`の
  empty openとして作り、partition active ID／versionを同じtransactionで変更する。
  Old `closed_at`とsuccessor `opened_at`はともにexact `closedAt`である。
  sequence／head／`last_assigned_recorded_at`は変更しない。Old segment／partition versionは
  expected prior versionからexactly +1、successor versionは1である。

Closeのold update／successor insert／partition updateも一つのdependency-ordered
data-modifying CTE statementで行い、appendと同じexact final count fenceでlate CAS不一致を
PostgreSQL transaction-aborting errorにする。Callerがexceptionをcatchしてcommitを試みても
partial closeをcommitできない。

Close fencing tokenは次のexact preimageから導出する。

```text
"aft:v1:" || HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.segment.close.v1") ||
  ENC_STRING(partitionId) || ENC_STRING(closedSegmentId) ||
  ENC_INTEGER(finalSequence) || ENC_BYTES(decoded32(headHash)) ||
  ENC_STRING(successorSegmentId) || ENC_STRING(closedAt)
))
```

Crash before commitはno mutation。commit後に同じexpected segmentでretryすると、stored
successor／token／boundaryを返す。Concurrent append／closeはpartition row lock順で直列化し、
commit順がどのsegmentへappendされたかを一意に決める。P23-003Fだけが後続sealed CASを持つ。

### 7. Tenant predicate before decrypt

`find()`はrecord ID grammar、expected tenant、expected partitionのcanonical derivation一致を
DB／KeySourceより前に検証する。SQL predicateは必ず
`record_id + partition_id + tenant pair`を含む。record IDだけのopen APIを作らない。

System tenant SQL branchは`tenant_type IS NULL AND tenant_id IS NULL`、present tenant branchは
`tenant_type=:tenant_type AND tenant_id=:tenant_id`である。Nullable pairへ通常の`=`を使わない。

0 rowは常に`AuditLookupResult::notFound()`であり、absent／cross-tenantを区別しない。
Hydrator、KeySource、decryptは0回である。SQL projectionはphysical stored exact14に
`action_type,resource_type,resource_id`を続けたexact17である。Physical exact14はlogical
`tenantBinding`を`tenant_type`／`tenant_id`へ展開したshapeである。Storeはindex三値をseparate
captureし、exact14だけを`PostgreSqlAuditStoredRecordHydrator`へ渡す。Hydratorはtenant pairを
closed unionへfoldし、conceptual exact13で`AuditStoredRecord::fromArray()`を一度呼び、続けて
`AuditProtectedBodyCodec::unprotectRecord()`を一度呼ぶ。Malformed／extra／wrong DB scalar、
tenant／partition swap、BOPD／AAD／complete-record不一致は`integrity_invalid`へ縮約する。
Decrypt後のlogical `action.type`／`resource.type`／`resource.id`をphysical三値へexact比較し、
一つでも不一致なら`integrity_invalid`である。同じ比較をdurable same-ID pathも行う。

SQL exact17 orderは次であり、Hydrator inputは末尾三indexを除いた先頭exact14である。

```text
schema_version,kind,record_id,partition_id,tenant_type,tenant_id,segment_id,sequence,
previous_record_hash,content_hash,record_hash,recorded_at,event_category,protected_body,
action_type,resource_type,resource_id
```

`recorded_at`、`last_assigned_recorded_at`、`opened_at`、nullable `closed_at`のすべては
SQL literal
`to_char(<column> AT TIME ZONE 'UTC','YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS <alias>`
でcanonicalizeする。Nullable valueはSQL nullのままである。Readはexact UTC six-fraction
以外とPostgreSQL `infinity|-infinity`を拒否する。Write parameterもcanonical UTC
six-fraction stringをDoctrine `ParameterType::STRING`でbindする。DB session timezoneや
DateStyleをwire authorityにしない。
`protected_body`はexisting `BlackOps\Transport\PostgreSql\PostgreSqlBytea::string()`で
string／resourceをbinaryへ戻す。WriteはDoctrine `ParameterType::BINARY`でのみbindし、
NULを含むBOPD bytesをtext parameterとして送らない。`schema_version`／`sequence`／state versionsはnative intまたは
canonical decimal string `0|[1-9][0-9]*`だけを受け、符号、space、leading zero、floatを拒否し、
decimal digit count／lexical compareでfield rangeを確認してからintへcastする。

### 8. Failure precedence and no-leak

一つのglobal precedenceは持たない。未到達dependencyを仮想failureとして比較せず、public
operationごとの実call orderをexactly次とする。

- `appendRequired`: auto-commit precondition (`storage_unavailable`) -> input／`validateDraft`／delivery／partition (`integrity_invalid`) -> root begin／
  advisory＋row lock／persisted-state read (`storage_unavailable`; malformed persisted stateは
  `integrity_invalid`) -> capacity exhaustion (`resource_unavailable`) -> Clock／UUID
  (`resource_unavailable`) -> Finalizer／complete
  (`integrity_invalid`) -> protect (`resource_unavailable`) -> DB writes (`storage_unavailable`)。
- `appendDurable`: auto-commit precondition (`storage_unavailable`) -> input／definition／delivery／partition／content hash (`integrity_invalid`) -> root／
  partition＋record lock／ID lookup (`storage_unavailable`) -> cross-partition (`idempotency_conflict`) ->
  same-partition row hydrate／decrypt／binding／index check (`integrity_invalid`) -> authenticated stored
  content mismatch (`idempotency_conflict`) or match return、またはID-absent new-path capacity exhaustion
  (`resource_unavailable`) -> initial-segment UUID if needed
  (`resource_unavailable`) -> complete
  (`integrity_invalid`) -> protect (`resource_unavailable`) -> writes (`storage_unavailable`)。
- `closeSegment`: auto-commit precondition (`storage_unavailable`) -> input (`integrity_invalid`) -> root／partition lock／state reads
  (`storage_unavailable`) -> malformed persisted state (`integrity_invalid`) -> unknown／empty／
  wrong-active open (`segment_conflict`) or closed／sealed return -> capacity exhaustion
  (`resource_unavailable`) -> Clock／UUID
  (`resource_unavailable`) -> writes (`storage_unavailable`)。
- `find`: auto-commit precondition (`storage_unavailable`) -> input／tenant-partition derivation
  (`integrity_invalid`) -> select (`storage_unavailable`) -> zero-row notFound -> exact17／exact14 hydrate／
  conceptual exact13／decrypt／index check (`integrity_invalid`)。

Required generated UUID pre-query collisionは`resource_unavailable`、durable cross-partition IDまたは
authenticated same-partition content conflictは`idempotency_conflict`、post-lock DB unique race／
deadlock／transaction errorは
`storage_unavailable`である。Write-side `AuditProtectionException`は`resource_unavailable`、
read／existing-row `AuditProtectionException`はB1がcauseをcollapseするため
`integrity_invalid`へ写す。Compound fixturesは同時に到達可能な隣接stageとzero-callだけを固定する。
すべてのB2 exception surfaceはfixed codeと
fixed messageだけを返す。SQL、schema、table、record ID、tenant、partition、hash、key ID、
nonce、ciphertext、plaintext、exception previous／trace argsを出力しない。

Root-owned operationのbegin／commit failureは`storage_unavailable`であり、StoreはそのConnectionを
exactly once `close()`してnative connectionとDBAL nesting stateをquarantineする。Primary failure後の
rollbackが成功した場合はprimary codeを保つ。Rollbackも失敗した場合は`storage_unavailable`がprimaryを
置換し、同じくConnectionをexactly once `close()`する。Cleanup `close()` failureはcodeを変えず、
previous／raw detailsを捨てる。Caller-owned ConnectionはStoreがcloseしない。
Caller-owned rootでwrite phase開始後の異常は§5／§6のtransaction-aborting fenceを必ず通り、
callerがcatchしてrootをcommitできる状態で返さない。

### 9. Architecture, migration, and release boundary

B2 Product typesは`src/Internal/Audit/Storage`へ置き、`deptrac.yaml`を変更しない。
Stable `StoragePurpose` exact9、Application builder、Container、CLI、HTTP、Guide／Websiteは変更しない。
Public API marker inventoryはexact zeroである。

B2 hydratorが`AuditStoredRecord::fromArray()`の二つ目のProduction callerになり、StoreがB1 protection
codecを直接consumeするため、Accepted B1の`tests/Architecture/AuditRecordArchitectureTest.php`だけを
authorized test deltaとして更新する。`fromArray()` callerはBodyCodec＋Hydrator exact2、
`protectRecord()`／`unprotectRecord()` callerはStore exact1ずつ、Outbox protect／unprotect callerは
exact0を維持する。Allowed B1-type reference directoriesへ`src/Internal/Audit/Storage`を加える一方、
B2専用Architecture testが各B2 fileのexact dependency mapを固定し、dynamic callとextra callerを拒否する。
B1 Production／other tests／fixtures exact32、Accepted A1 exact48、Accepted A2 exact28は
byte-identicalに維持する。B2専用Architecture testはnew type inventory、public callable、
Internal placement、no activation、no forbidden clear columnsを固定する。

Current source migrationは11本から12本へ増え、既存unreleased
`database:status`／`database:migrate`が列挙／適用するFramework migrationも12本になる。
これは新CLI command／route／registrationのactivationではないが、source-level observable
changeである。Current migration inventory testsとpackage required-file inventoryは更新する。
一方、published Stable `1.2` fixtureである
`tests/Consumer/framework-update-runtime.sh`と`tests/Consumer/version-baseline.sh`のhistorical
migration count／bytesは変更しない。

Current Community Board permanent journeyはFramework 12＋Application 5＝17を表すため、READMEの
`all 11 Framework and 5 Community Board migrations (16 total)`を
`all 17 migrations (12 Framework + 5 Community Board Application migrations = 17)`へ、consumerの
`migrations: 16`を`migrations: 17`へ各exactly once置換する。さらにREADMEのstale sentence
`The permanent consumers perform equivalent cleanup automatically. The application and Documentation Website are Local／CI examples; this repository does not publish or host them externally.`を
`The permanent consumers perform equivalent cleanup automatically. BlackOps Board is a Local／CI reference application and is not published or hosted externally. The Documentation Website is published on Cloudflare Pages.`へ
exactly once置換する。それ以外の`tests/Consumer/community-board-clean-install.sh`／
`examples/community-board/README.md` bytesは不変とする。
前者をComplete Final Gateで実行し、後者は
Audit capabilityのcurrent／released claimを追加しない。

`tests/Consumer/framework-package-export.sh`はGit側を`git archive HEAD`から作るため、
uncommitted B2のIteration／Complete Final Gateでは実行しない。Fresh implementation reviewと
Orchestrator Acceptance後にcandidateをcommitし、そのclean exact SHAで初めて実行する。
Source-changing closeout commit後は同一gateをその新しいclean SHAで再実行する。
Reusable `tests/Consumer/framework-package-required-inventory.sh`はscript全体の文字列検索を使わず、
`for required_path in \\` block exactly oneだけをsyntax boundary付きで抽出し、existing ordered
inventory＋B2 exact10へbyte-exact compareする。Comment-only、existing／new entry欠落、order swap、
duplicate loopはいずれもnegativeである。Unique functionのtop-level executable depth、pre-loop
terminationとlater redefinitionの不在、Git／Composer archiveへのexact two ordered top-level callsも
固定し、wrapper-before-loop、early return、call removal、later redefinitionをnegativeにする。
Iteration／Complete Final Gateの双方がこのtestを直接実行する。

## Consequences

P23-003B2はPostgreSQL storeとmigrationを実装できるが、runtimeへ自動登録されない。
P23-003Cは同じConnection/root transactionへrequired appendを組み込み、durable Audit Outboxと
relayを追加する。P23-003Fはcheckpoint／sealed boundary、P23-003Gはauthorization／queryを所有する。

Migrationがsource treeへ存在してもBlackOps `1.3` capabilityのReleaseを意味しない。
Stable `1.2.0` behaviorとFramework Guide／Websiteは変わらない。Community Board READMEだけは
current migration countとBoard／Documentation Websiteのpublication-state説明を正す。

Release Authority tupleは次である。

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.postgresql_canonical_store.v1
publicSourceInventory=[examples/community-board/README.md]
publicRouteInventory=[]
```

Public source deltaはCommunity Board READMEのmigration arithmeticとstale shared publication-state
sentenceのaccuracy correctionだけである。Guide／Website
source、generated HTML／Raw／Search／LLM artifactは変更も再生成もしない。
そのためB2 gateはREADMEとclean-install consumerのsole replacement後のexact SHA-256、追加release
claimを拒否するnegative、tracked `docs/guide`／`docs/website` sourceのdiffとstatus emptyを検証する。
Ignored generated artifactはsource no-diffへ混ぜず、再生成結果をAcceptance evidenceとしてclaimしない。

## Remaining Issues

P23-003B2 implementation、gates、fresh review、and Acceptance are Green.
Candidate commit and same-SHA package export remain. P23-003C以降のcomposition／Outbox／
authorizationはこのTaskに含めない。

## Suggested Next Action

OrchestratorはAccepted candidateをcommitし、そのclean exact SHAでpackage exportを実行する。
Pushは行わない。Closeout後、P23-003Cへ進める。
