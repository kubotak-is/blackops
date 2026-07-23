# Reliability and Delivery

## Goal

同じ利用者Requestの再送を一つのOperationへ収束させ、業務Data更新とDeferred child Operation発行を同じDatabase Transactionへ参加させ、Process Crashを越えてat-least-onceで配送・再投影できるContractを提供する。

Phase 19はExactly Onceや汎用Message Busを提供しない。Idempotency、Transactional Outbox、Relay、Operation Replay、Observer Replayを異なる責務として扱い、固定Identity、Fencing、監査可能な再開操作で重複を安全に扱う。

## Scope

- HTTP MutationとPHP DispatchのOptional Idempotency
- Actor-scoped Key、Canonical Fingerprint、重複Response
- PostgreSQL Idempotency Recordと独立Retention
- Deferred child Operation用Transactional Outbox
- Relay Claim／Lease／Heartbeat／Fencing／Retry／Dead Letter
- Outbox Dead Letter再開、Operation Replay、Canonical Observer Replay
- Community Board Digest／Comment Notification Journey
- Guide、Consumer、CLI、Migration、Scheduler、Full Quality Gate

次は対象外とする。

- GET／HEAD、Anonymous、Tenant-scoped Idempotency
- `#[Idempotent]` Attribute、Application必須Scope Resolver
- `EphemeralOutcome`のResponse Replay
- 任意JSON Message Bus、外部Broker、Email／Push Provider
- 外部API副作用のExactly Once
- 複数Connection／複数Databaseの原子的Commit
- Two-phase Commit、Distributed Transaction
- Canonical Journalの書換え
- Documentation Website／Community BoardのExternal Publication／Deploy

## Responsibility Boundary

| Area | Framework | Application |
| --- | --- | --- |
| Key | Shape検証、Hash、Actor Scope、Record競合 | Mutation Callごとの高Entropy Key生成と再利用 |
| Fingerprint | 型／Field境界付きCanonical Hash、Sensitive非保存 | 同じRequestへ同じValueを渡す |
| Duplicate | Atomic Claim、Conflict／In-progress／Expired、Safe Response再利用 | Conflict時に新しいKeyを選ぶか入力を修正する |
| Handler | ContextへOpaque Key Hashを公開 | 外部API等Framework外副作用の冪等取り込み |
| Outbox | 同一Connection Transaction参加、固定child Identity、Relay | Operation Coordinationでchild Operationを登録する |
| Relay | Lease、Fencing、Retry、Dead Letter、監査 | Transport先の固定Operation ID重複排除 |
| Replay | Identity規則、対象選択、監査 | Replay権限、業務上の再実行判断 |
| Retention | Record別Policy、Plan／Purge／Audit | Production保持期間とLegal Hold |

## Idempotency Entry Contract

HTTPのPOST／PUT／PATCH／DELETEだけがOptional `Idempotency-Key` Headerを受理する。Headerがなければ既存Lifecycleを一切変更しない。GET／HEADや`EphemeralOutcome` RouteへHeaderがある場合は、機能したように見せず400で拒否する。

PHP Public Dispatchは同じKeyをOptional Dispatch Optionとして受け取る。Operation Attributeは追加しない。Console Operation AdapterとDeferred Worker内部の再実行は新しい入口としてKeyを受け取らない。

Keyは1文字以上255文字以下で、空白／Control Characterを含まないPrintable ASCIIとする。複数Header Field、Comma結合値、空値、Shape不正はOperation IDを発行する前に拒否する。KeyはCredentialではないがReplay Capabilityとして扱い、Log、Journal、Diagnostics、Report、ErrorへRaw値を出さない。

Applicationは少なくとも128 bit相当の予測困難なKeyを生成し、同じ利用者ActionのNetwork Retryだけで再利用する。FrameworkはRaw Keyを永続化せず、Domain SeparatorとString byte lengthを含むSHA-256 Version 1 Hashだけを保存する。Public Key Valueは`hash()`だけを提供し、Raw Getter、`__toString()`、JSON Serializationを提供しない。

## Scope and Authorization Order

最初のScopeは次のTupleである。

```text
operation type id
authenticated authorization actor type
authenticated authorization actor id
idempotency key
```

TupleはField長と境界を含むCanonical Encode後にHash化する。ActorがないRequest／DispatchへKeyが指定された場合は安全に拒否する。TenantはPhase 20までScopeへ含めない。

Requestごとの順序は次に固定する。

```text
route match
  -> protocol and key shape validation
  -> value binding
  -> value validation
  -> authentication
  -> authorization
  -> scope and fingerprint calculation
  -> atomic idempotency claim or duplicate decision
  -> operation execution or safe response replay
```

Authentication／Authorizationは重複Requestでも毎回評価する。無効Credentialは既存401、Denyは既存403とし、Recordの存在、Operation ID、Original Resultを参照前に隠す。Binding／Validation／Authentication／Authorizationで拒否されたRequestはIdempotency Recordを作らない。

## Fingerprint

FingerprintはBindingとValue Validation後の具象Operation Valueから作る。Operation Type、Value Type、Property名、型、null／値境界、配列順序、String byte lengthを含む決定的なVersion付きCanonical RepresentationをIncremental SHA-256へ直接投入し、保存するのはHashだけとする。

- Sensitive Propertyも同一Input判定には参加するが、Raw値とCanonical Representation全体をBuffer化・永続化・記録しない
- Property宣言順を正本とし、連想配列はKey順をCanonical化する
- 既存Canonical Operation Codecが扱う有限Floatは決定的な表現で含め、非有限Float、Resource、Closure、Object等の非対応型を追加で許容しない
- Fingerprint Codec VersionをRecordへ保存し、未知Versionを一致扱いにしない
- 同じScope KeyでFingerprintが異なる場合、最初のRecordを変更しない

## Record and Execution Context

ExecutionContextはOptionalなOpaque Idempotency Key Hashを保持する。Raw Keyは保持しない。

- Root受信時にIdempotencyが有効なら設定する
- Attempt開始では同じHashを維持する
- Deferred Transport CodecはHashとCodec Versionだけを伝播する
- child Operationは新しいOperation Identityを持つため、親のHashを継承しない
- KeyなしOperationではGetterは`null`を返す

Idempotency Storeは少なくとも次を保持する。

```text
scope hash
key hash version
fingerprint hash and version
operation id
execution strategy
state: processing | terminal
safe response snapshot version
created at
expires at
```

Unique BoundaryはScope Hashであり、最初のClaimだけがOperation IDを発行して処理を開始できる。Terminal Snapshotは公開可能なHTTP Status、固定Allowlist Header、Safe BodyだけをVersion付きで保持する。Credential、Raw Key、Canonical Value、任意Application Header、Throwable Detailを保存しない。

## Duplicate Lifecycle

同じScope Keyを受け取ったときは次に固定する。

| Existing Record | Fingerprint | Result |
| --- | --- | --- |
| なし | 任意 | 新しいOperation IDでClaimして実行 |
| processing | 同じ | 409 `idempotency_in_progress` |
| processing／terminal | 異なる | 409 `idempotency_conflict` |
| terminal Inline | 同じ | Original Safe HTTP Responseを再利用 |
| terminal Deferred | 同じ | Original 202と同じOperation IDを再利用 |
| terminalだが必要なOutcome／Snapshot消失 | 同じ | 409 `idempotency_expired` |

再利用Responseには`Idempotency-Replayed: true`と`Cache-Control: private, no-store`を付ける。Conflict／In-progress／Expired ResponseへOriginal Operation ID、Fingerprint、Actor、Resultを含めない。

InlineではCompleted、Business／Conflict Rejected、Safe Internal FailureをTerminal Snapshotとする。DeferredではDurable Acceptance成功時の202だけをTerminal Snapshotとし、後続のWorker Stateに関係なく同じ202を返す。Operation Statusは既存`Location`から別Contractで取得する。

Record Claim後、Operation Lifecycleの受付確定前にProcessが停止した場合、Recordを暗黙に削除して同じ副作用を再実行しない。StorageはOperation／Journal Evidenceを使ってTerminal化できる場合だけ回復し、判断できなければ`idempotency_in_progress`を維持して運用上のRetention／監査対象にする。

## HTTP and PHP Failure Matrix

| Condition | HTTP | Stable Code | Record |
| --- | ---: | --- | --- |
| Keyなし | Existing | Existing | 作らない |
| Invalid／multiple Key | 400 | `invalid_idempotency_key` | 作らない |
| GET／HEADにKey | 400 | `idempotency_not_supported` | 作らない |
| Ephemeral OutcomeにKey | 400 | `idempotency_not_supported` | 作らない |
| Anonymous ActorにKey | 400 | `idempotency_requires_authenticated_actor` | 作らない |
| Binding Failure | Existing 400／422 | Existing | 作らない |
| Validation Failure | 422 | Existing | 作らない |
| Invalid Credential | 401 | Existing | 参照しない |
| Authorization Deny | 403 | Existing | 参照しない |
| Same Key, different Fingerprint | 409 | `idempotency_conflict` | 変更しない |
| Same Key, processing | 409 | `idempotency_in_progress` | 変更しない |
| Same Key, terminal | Original | Original | Snapshotを再利用 |
| Recordあり、Result消失 | 409 | `idempotency_expired` | 変更しない |
| Store／Hash／Decode Failure | 500 | `internal_error` | Safe Failure |

PHP DispatchではShape不正を`IdempotencyKey` Construction Failure、Actor欠落／Conflict／In-progress／ExpiredをStable Rejection、Storage／Integrity Failureを既存Safe Internal Failure Boundaryとして表す。重複Terminalは元の型付きOutcome／RejectionとOperation IDを再構成する。HTTP固有HeaderはPHP Resultへ混在させない。

## Retention

Idempotency RecordはTransport Payload、Journal、Outcome、Dead Letterと独立したRetention Targetとする。既定値はOperation／Outcomeに設定された最長保持期間へ合わせるが、Production Purgeは既存方針どおり明示Policyを要求する。

- Recordが残る間はKeyを再利用できない
- Outcome／Snapshotが先に消えた場合も同じOperationを再実行しない
- Record Purge後だけ同じKeyを新規RequestとしてClaimできる
- Legal Hold、Dry-run Plan、Confirmed Purge、Auditを既存Retention Contractへ統合する
- Purge AuditへCount／期間／Actor／Policyを記録し、Raw Key／Scope／Fingerprintを記録しない

## Transactional Outbox

Transactional Outboxは、実行中のTransactional OperationまたはDI管理Transactional ServiceからDeferred child Operationを登録するPublic Capabilityを提供する。Domain層はこのCapabilityへ依存せず、Operation Coordination／Application Infrastructureが呼び出す。

登録時にFrameworkは次を固定する。

- 新しいchild Operation ID
- 親Correlation IDと親Operation ID由来Causation ID
- 親のorigin／authorization Actor、Outbox登録主体のexecution Actor
- Operation Type／Schema／Value Payload
- Outbox Record ID、作成時刻、利用するNamed Connection

親のIdempotency Key Hashはchildへ伝播しない。Applicationがchild自体の重複制御を必要とする場合は、固定child Operation IDによるTransport重複排除とApplication-owned Domain Constraintを使う。

Outbox Insertは、Application Mutationと同じ`DatabaseManager`の同じNamed Connection Instanceで、Frameworkが所有する既存Transaction Scope内に参加した場合だけ原子的である。Transaction外、異なるConnection、Manual Transaction所有者不明ではFail-fastし、Direct TransportへFallbackしない。Rollback／Rollback-onlyではOutbox Rowも残らない。

## Relay and Dead Letter

PostgreSQL Relayは有限BatchをClaimし、Lease、Heartbeat、Fencing Tokenで所有権を管理する。成功時はTransport AcceptanceとSent更新の間でCrashし得るためat-least-onceであり、再配送では同じOutbox Record IDとchild Operation IDを維持する。

Relayは次を備える。

- `outbox:relay:run`: 有限回または空になるまでの一回実行
- `outbox:relay:daemon`: Polling IntervalとGraceful Shutdownを持つ明示Loop
- `outbox:dead-letter:retry`: 監査付きで同じRecordをRetry可能状態へ戻す
- 指数Backoff、最大Attempt、次回時刻、Failure Fingerprint
- Lease切れRecovery、Stale Fencing拒否、Heartbeat、Batch Isolation

Raw Payload、Credential、Connection Parameter、Throwable DetailをCLI／Logへ出さない。Dead Letter再開は同じRecord／child Operation Identityを維持し、新規Operationを作らない。

## Replay Separation

次の3操作を混同しない。

| Operation | Identity | Canonical Journal | Purpose |
| --- | --- | --- | --- |
| Outbox Dead Letter再開 | 同じOutbox Record／child Operation ID | 既存child Lifecycleを維持 | 未配送Recordを再配送 |
| Terminal Operation Replay | 新しいOperation ID、元をCausationに設定 | 新Lifecycleを追加 | 業務Operationを明示的に再実行 |
| Observer Replay | 同じCanonical Journal Record ID | 変更しない | 対象Observerへ再投影 |

Observer Replay CLIはOperation ID／Record ID／時刻範囲と対象Observerを明示し、Dry-run、有限Batch、Checkpoint、監査を備える。Canonical Storeへ追記・更新せず、Projection前に現在のSensitive Filterを適用する。ObserverはRecord IDによる冪等取り込みを維持する。

## Community Board Acceptance Journey

- Digest Formは一ActionごとにServer生成のIdempotency Keyを持つ
- 同じUser／Week／Keyの二重Submitは同じ202／Operation ID／Digestへ収束する
- 別Keyなら同じUser／WeekでもD107どおり別Digestを作る
- Comment作成Transactionは`NotifyPostOwner` child OperationをOutboxへ登録する
- Relay／Worker完了後、Application-owned Notification Rowを認証済みUIへ表示する
- Relay停止中もCommentはCommitされ、Outbox Rowが残る
- Relay再配送でもNotificationは固定Identityで重複しない

外部Email／Pushを使わず、Generated ClientはServer-only Boundaryを維持する。

## Compatibility and Security

- KeyなしHTTP／PHP Dispatch、Direct Deferred Transport、Operation ID、Status APIを維持する
- Raw Key、Credential、Sensitive Value、Canonical Fingerprint Inputを永続化・Journal・Log・Diagnosticsへ出さない
- Current Authentication／AuthorizationをDuplicate Lookup前に必ず実行する
- Conflict／In-progress／ExpiredでRecord存在以上の情報を漏らさない
- Idempotency Store、Outbox、RelayはSQL／Table／Connection／Throwable DetailをSafe Surfaceへ出さない
- Worker ModeでKey、Record、Claim、Response SnapshotをRequest間共有Stateへ残さない
- External API、異なるConnection、Relay全体についてExactly Onceを保証しない
- External Publication／Deployを行わない

## Acceptance Criteria

- [ ] Optional Key付きInline／Deferred Requestが同じActor／Inputで同じOperationへ収束する
- [ ] Current Authentication／Authorizationが重複Requestでも毎回評価される
- [ ] Conflict／In-progress／Expired／UnsupportedがStable Failure Matrixを満たす
- [ ] Raw Key／Sensitive Input／Credentialが永続化・Journal・Log・Diagnosticsへ残らない
- [ ] Idempotency Recordが独立Retention／Hold／Auditへ統合される
- [ ] 同一Named Connection内でApplication MutationとOutbox Insertが原子的にCommit／Rollbackする
- [ ] RelayがLease／Heartbeat／Fencing／Retry／Dead Letterをat-least-onceで実行する
- [ ] Dead Letter再開、Operation Replay、Observer ReplayのIdentity規則が分離される
- [ ] Community Boardの二重DigestとComment Notification JourneyがBrowserから完走する
- [ ] Framework／Consumer／Frontend／Website Full Gateが成功する
- [ ] External Publication／Deployを行わない

## Traceability

- Decision: [D109 Phase 19 Idempotency and Outbox](../decisions/109-phase-18-idempotency-and-outbox.md)
- Core Context: [ExecutionContext API](19-execution-context-api.md)
- Lifecycle Data: [Lifecycle Event Data](24-lifecycle-event-data.md)
- Transaction: [Durable Journal and Transactions](11-durable-journal-and-transactions.md)
- Retention: [Data Retention and Deletion](38-data-retention-and-deletion.md)
- Status: [Deferred Status and Outcome API](69-deferred-status-and-outcome-api.md)
- Consumer: [Full-stack Reference Application](71-full-stack-reference-application.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
